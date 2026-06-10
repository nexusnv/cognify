<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderChangeOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_material_change_order_applies_immediately(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'changeType' => 'amendment',
                'expectedDeliveryDate' => '2026-07-15',
                'buyerNote' => 'Updated through controlled change order.',
                'lines' => [[
                    'lineId' => (string) $po->lines->first()->id,
                    'action' => 'update',
                    'expectedDeliveryDate' => '2026-07-16',
                    'deliveryLocation' => 'Dock 7',
                    'notes' => 'Supplier confirmed revised dock booking.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.materialChange', false);

        $changeOrderId = $this->latestChangeOrderId($po);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.purchaseOrder.status', 'issued')
            ->assertJsonPath('data.purchaseOrder.expectedDeliveryDate', '2026-07-15')
            ->assertJsonPath('data.purchaseOrder.supplierIssue.supplierVersionNumber', 2);
    }

    public function test_material_change_order_routes_for_approval_without_mutating_commitment(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
        $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'changeType' => 'amendment',
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'update',
                    'quantity' => '8.0000',
                    'unitPrice' => '12500.0000',
                    'notes' => 'Reduced scope and revised supplier price.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.materialChange', true)
            ->assertJsonPath('data.requiresApproval', true)
            ->assertJsonPath('data.delta.totalAmount.after', '100000.00');

        $changeOrderId = $this->latestChangeOrderId($po);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.purchaseOrder.status', 'change_pending');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'change_pending',
            'total_amount' => '131100.00',
        ]);
        $this->assertDatabaseHas('approval_instances', [
            'tenant_id' => $po->tenant_id,
            'subject_type' => PurchaseOrder::class,
            'subject_id' => $po->id,
        ]);
    }

    public function test_approval_applies_material_change_order(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Admin->value);
        $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'update',
                    'quantity' => '8.0000',
                    'unitPrice' => '12500.0000',
                ]],
            ]))
            ->assertCreated();

        $changeOrderId = $this->latestChangeOrderId($po);
        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk();

        $task = ApprovalTask::query()
            ->where('subject_type', PurchaseOrder::class)
            ->where('subject_id', $po->id)
            ->firstOrFail();
        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'issued',
            'total_amount' => '100000.00',
            'supplier_version_number' => 2,
        ]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'quantity' => '8.0000',
            'unit_price' => '12500.0000',
            'total_amount' => '100000.00',
        ]);
    }

    public function test_cancel_change_order_releases_purchase_order_for_other_changes(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'changeType' => 'amendment',
                'expectedDeliveryDate' => '2026-07-15',
            ]))
            ->assertCreated();

        $changeOrderId = $this->latestChangeOrderId($po);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/cancel", [
                'lockVersion' => 1,
                'reason' => 'Supplier withdrew the change request.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.purchaseOrder.status', 'issued');

        $this->assertDatabaseHas('purchase_order_change_orders', [
            'id' => $changeOrderId,
            'status' => 'cancelled',
        ]);
    }

    public function test_full_and_partial_cancellations_are_change_orders(): void
    {
        $partial = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($partial->tenant, TenantRole::Buyer->value);
        $line = $partial->lines->first();

        $this->actingAsTenant($partial->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$partial->id}/change-orders", $this->changePayload($partial, [
                'changeType' => 'partial_cancellation',
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'cancel',
                    'notes' => 'Supplier cannot fulfill this line.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.changeType', 'partial_cancellation')
            ->assertJsonPath('data.materialChange', true);

        $full = $this->issuedPurchaseOrder();
        $fullBuyer = $this->tenantUser($full->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($full->tenant, $fullBuyer)
            ->postJson("/api/purchase-orders/{$full->id}/change-orders", $this->changePayload($full, [
                'changeType' => 'full_cancellation',
                'lines' => [],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.changeType', 'full_cancellation')
            ->assertJsonPath('data.materialChange', true);
    }

    public function test_change_orders_enforce_state_lock_tenant_role_and_single_active_order(): void
    {
        $po = $this->issuedPurchaseOrder(lockVersion: 4);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, ['lockVersion' => 3]))
            ->assertConflict();

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertForbidden();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertCreated();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertConflict();

        $draft = $this->purchaseOrder(status: 'draft');
        $draftBuyer = $this->tenantUser($draft->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($draft->tenant, $draftBuyer)
            ->postJson("/api/purchase-orders/{$draft->id}/change-orders", $this->changePayload($draft))
            ->assertConflict();
    }

    private function changePayload(PurchaseOrder $po, array $overrides = []): array
    {
        return array_replace_recursive([
            'lockVersion' => $po->lock_version,
            'reason' => 'Supplier confirmed updated commitment.',
            'changeType' => 'amendment',
            'expectedDeliveryDate' => '2026-07-15',
            'buyerNote' => 'Updated through controlled change order.',
            'financeNote' => null,
            'lines' => [],
        ], $overrides);
    }

    private function latestChangeOrderId(PurchaseOrder $po): string
    {
        return (string) $po->changeOrders()->latest('created_at')->value('id');
    }

    private function publishedApprovalPolicy(Tenant $tenant, User $actor, User $approver): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Purchase order approval',
            'description' => 'Finance and procurement review for purchase orders.',
            'subject_type' => 'purchase_order',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'purchase_order',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'amount', 'operator' => 'gte', 'value' => 1]],
            'route_template' => [
                'stages' => [[
                    'name' => 'Finance review',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                    ],
                    'fallbackApprovers' => [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ]],
            ],
            'sla_rules' => [['stage' => 'Finance review', 'dueInHours' => 48]],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }

    private function issuedPurchaseOrder(int $lockVersion = 1): PurchaseOrder
    {
        $po = $this->purchaseOrder(status: 'approved', lockVersion: $lockVersion);

        $supplierVersion = [
            'versionNumber' => 1,
            'issuedAt' => now()->toISOString(),
            'issueMethod' => 'manual_email',
            'supplierContactName' => 'Priya Supplier',
            'supplierContactEmail' => 'priya.supplier@example.com',
            'message' => 'Please confirm receipt and planned delivery date.',
            'purchaseOrder' => [
                'id' => (string) $po->id,
                'number' => $po->number,
                'currency' => $po->currency,
                'subtotalAmount' => (string) $po->subtotal_amount,
                'taxAmount' => (string) $po->tax_amount,
                'freightAmount' => (string) $po->freight_amount,
                'discountAmount' => (string) $po->discount_amount,
                'totalAmount' => (string) $po->total_amount,
                'paymentTerms' => $po->payment_terms,
                'deliveryTerms' => $po->delivery_terms,
            ],
            'vendor' => ['id' => (string) $po->vendor_id, 'name' => 'Northwind Traders'],
            'lines' => [[
                'id' => (string) $po->lines->first()->id,
                'lineNumber' => 1,
                'description' => 'Pallet rack bay',
                'quantity' => '10.0000',
                'unit' => 'set',
                'unitPrice' => '13110.0000',
                'lineTotal' => '131100.00',
                'currency' => 'MYR',
            ]],
            'source' => [
                'handoffId' => (string) $po->purchase_order_request_handoff_id,
                'rfqId' => (string) $po->rfq_id,
                'recommendationId' => (string) $po->rfq_award_recommendation_id,
            ],
            'approval' => [
                'approvalInstanceId' => $po->approval_instance_id !== null ? (string) $po->approval_instance_id : null,
                'approvedAt' => $po->approved_at?->toISOString(),
                'approvedByUserId' => $po->approved_by_user_id !== null ? (string) $po->approved_by_user_id : null,
            ],
        ];

        $po->forceFill([
            'status' => 'issued',
            'issued_by_user_id' => $po->created_by_user_id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'supplier_contact_name' => 'Priya Supplier',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'issue_message' => 'Please confirm receipt and planned delivery date.',
            'supplier_version' => $supplierVersion,
            'supplier_version_number' => 1,
        ])->save();

        return $po->fresh(['tenant', 'lines']);
    }

    private function purchaseOrder(string $status, int $lockVersion = 1): PurchaseOrder
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northwind Traders',
            'status' => 'active',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-2026-POC',
            'title' => 'Warehouse racking',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Purchase pallet rack bay materials.',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Pallet rack bay',
                'description' => 'Pallet rack bay',
                'quantity' => '10',
                'unit_of_measure' => 'set',
                'currency' => 'MYR',
            ]],
        ]);
        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => 'northwind@example.com',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'manual_entry_complete' => true,
        ]);
        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);
        $quotation->forceFill(['current_version_id' => $version->id])->save();
        $recommendation = RfqAwardRecommendation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'status' => 'approved',
            'recommended_vendor_id' => $vendor->id,
            'recommended_quotation_id' => $quotation->id,
            'recommended_quotation_version_id' => $version->id,
            'rationale' => 'Best operational fit.',
            'tradeoff_summary' => 'Higher price but lower delivery risk.',
            'risk_summary' => 'No unresolved risks.',
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
        ]);
        $handoff = PurchaseOrderRequestHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'approval_instance_id' => null,
            'rfq_id' => $rfq->id,
            'requisition_id' => null,
            'project_id' => null,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'POH-2026-POC',
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'currency' => 'MYR',
            'subtotal_amount' => '131100.00',
            'total_amount' => '131100.00',
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name], 'rfq' => ['number' => $rfq->number]],
            'line_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'readiness_warnings' => [],
            'lock_version' => 1,
        ]);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $handoff->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'approval_instance_id' => null,
            'rfq_id' => $rfq->id,
            'requisition_id' => null,
            'project_id' => null,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-2026-POC-'.Str::upper(Str::random(4)),
            'status' => $status,
            'currency' => 'MYR',
            'subtotal_amount' => '131100.00',
            'tax_amount' => '0.00',
            'freight_amount' => '0.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'requested_po_date' => '2026-06-18',
            'expected_delivery_date' => '2026-07-02',
            'billing_name' => 'Acme Finance',
            'billing_address' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
            'delivery_attention' => 'Warehouse receiving',
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'buyer_note' => 'Confirm delivery slot before dispatch.',
            'finance_note' => 'Charge to expansion budget.',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name], 'rfq' => ['number' => $rfq->number]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'ready_for_review_by_user_id' => $buyer->id,
            'ready_for_review_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'approved_at' => $status === 'approved' ? now() : null,
            'lock_version' => $lockVersion,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'source_line_id' => 'rfq-line-1',
            'line_number' => 1,
            'description' => 'Pallet rack bay',
            'unit' => 'set',
            'quantity' => '10.0000',
            'unit_price' => '13110.0000',
            'subtotal_amount' => '131100.00',
            'total_amount' => '131100.00',
            'currency' => 'MYR',
        ]);

        return $po->fresh(['tenant', 'lines']);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(Tenant $tenant, string $role): User
    {
        [, $user] = $this->tenantUserPair($role, $tenant);

        return $user;
    }

    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }
}

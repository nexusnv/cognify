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

class PurchaseOrderReviewApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_submit_ready_purchase_order_for_approval(): void
    {
        $po = $this->readyPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
        $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review')
            ->assertJsonPath('data.approval.approvalInstanceId', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.permissions.canSubmitForApproval', false);

        $this->assertDatabaseHas('approval_tasks', [
            'tenant_id' => $po->tenant_id,
            'subject_type' => PurchaseOrder::class,
            'subject_id' => $po->id,
            'assignee_id' => $approver->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'purchase_order.approval_submitted',
        ]);
    }

    public function test_submit_requires_ready_or_changes_requested_status(): void
    {
        $buyerStatuses = ['draft', 'in_review', 'approved', 'rejected', 'cancelled'];

        foreach ($buyerStatuses as $status) {
            $po = $this->purchaseOrder(status: $status);
            $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
            $approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
            $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);

            $this->actingAsTenant($po->tenant, $buyer)
                ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                    'lockVersion' => $po->lock_version,
                ])
                ->assertConflict();
        }
    }

    public function test_submit_requires_current_lock_version(): void
    {
        $po = $this->readyPurchaseOrder(lockVersion: 3);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
        $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => 2,
            ])
            ->assertConflict();
    }

    public function test_submit_without_matching_policy_returns_conflict(): void
    {
        $po = $this->readyPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertConflict()
            ->assertJsonPath('error.message', 'No approval policy versions are available.');
    }

    public function test_cross_tenant_submit_is_denied(): void
    {
        $po = $this->readyPurchaseOrder();
        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertForbidden();
    }

    public function test_approval_task_approve_marks_purchase_order_approved(): void
    {
        [$po, $task, $approver] = $this->submittedPurchaseOrderForApproval();

        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", [
                'lockVersion' => $task->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'approved',
            'approved_by_user_id' => $approver->id,
        ]);
    }

    public function test_approval_task_reject_marks_purchase_order_rejected(): void
    {
        [$po, $task, $approver] = $this->submittedPurchaseOrderForApproval();

        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/reject", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Tax coding does not match the approved quotation.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'rejected',
            'rejected_by_user_id' => $approver->id,
            'rejected_reason' => 'Tax coding does not match the approved quotation.',
        ]);
    }

    public function test_approval_task_request_changes_marks_purchase_order_changes_requested(): void
    {
        [$po, $task, $approver] = $this->submittedPurchaseOrderForApproval();

        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Payment terms and tax amount require correction.',
                'requestedFields' => ['taxAmount', 'paymentTerms'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'changes_requested');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'changes_requested',
            'changes_requested_by_user_id' => $approver->id,
            'changes_requested_reason' => 'Payment terms and tax amount require correction.',
            'changes_requested_fields' => json_encode(['taxAmount', 'paymentTerms']),
        ]);
    }

    public function test_buyer_can_update_changes_requested_purchase_order_and_resubmit(): void
    {
        [$po, $task, $approver, $buyer] = $this->submittedPurchaseOrderForApproval();

        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/request-changes", [
                'lockVersion' => $task->lock_version,
                'reason' => 'Payment terms need correction.',
                'requestedFields' => ['paymentTerms'],
            ])
            ->assertOk();

        $po = $po->refresh();

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'paymentTerms' => 'Net 45',
                'buyerNote' => 'Updated after finance review.',
            ])
            ->assertOk()
            ->assertJsonPath('data.paymentTerms', 'Net 45');

        $po = $po->refresh();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_review');
    }

    public function test_approval_task_list_filters_purchase_order_subjects(): void
    {
        [$po, $task, $approver] = $this->submittedPurchaseOrderForApproval();

        $this->actingAsTenant($po->tenant, $approver)
            ->getJson('/api/approval-tasks?subjectType=purchase_order')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $task->id)
            ->assertJsonPath('data.0.subject.type', 'purchase_order')
            ->assertJsonPath('data.0.subject.href', "/purchase-orders/{$po->id}");
    }

    private function readyPurchaseOrder(int $lockVersion = 1): PurchaseOrder
    {
        return $this->purchaseOrder(status: 'ready_for_review', lockVersion: $lockVersion);
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
            'number' => 'RFQ-2026-POA',
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
            'number' => 'POH-2026-POA',
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
            'number' => 'PO-2026-POA-'.Str::upper(Str::random(4)),
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

    private function submittedPurchaseOrderForApproval(): array
    {
        $po = $this->readyPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
        $this->publishedApprovalPolicy($po->tenant, $buyer, $approver);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertOk();

        $task = ApprovalTask::query()
            ->where('tenant_id', $po->tenant_id)
            ->where('subject_type', PurchaseOrder::class)
            ->where('subject_id', $po->id)
            ->firstOrFail();

        return [$po->refresh(), $task, $approver, $buyer];
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

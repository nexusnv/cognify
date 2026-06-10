<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
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

class PurchaseOrderIssueToSupplierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_issue_approved_purchase_order_to_supplier(): void
    {
        $po = $this->approvedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/issue", [
                'lockVersion' => $po->lock_version,
                'method' => 'manual_email',
                'supplierContactName' => 'Priya Supplier',
                'supplierContactEmail' => 'priya.supplier@example.com',
                'message' => 'Please confirm receipt and planned delivery date.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.supplierIssue.issueMethod', 'manual_email')
            ->assertJsonPath('data.supplierIssue.supplierContactEmail', 'priya.supplier@example.com')
            ->assertJsonPath('data.supplierIssue.supplierVersionNumber', 1)
            ->assertJsonPath('data.permissions.canIssueToSupplier', false)
            ->assertJsonPath('data.permissions.canExportSupplierVersion', true)
            ->assertJsonPath('data.permissions.canAcknowledgeSupplier', true);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'issued',
            'issue_method' => 'manual_email',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'supplier_version_number' => 1,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'purchase_order.issued',
        ]);
    }

    public function test_issue_requires_approved_status(): void
    {
        foreach (['draft', 'ready_for_review', 'in_review', 'changes_requested', 'rejected', 'cancelled'] as $status) {
            $po = $this->purchaseOrder(status: $status);
            $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

            $this->actingAsTenant($po->tenant, $buyer)
                ->postJson("/api/purchase-orders/{$po->id}/issue", $this->issuePayload($po))
                ->assertConflict();
        }
    }

    public function test_issue_requires_current_lock_version(): void
    {
        $po = $this->approvedPurchaseOrder(lockVersion: 5);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/issue", array_merge($this->issuePayload($po), [
                'lockVersion' => 4,
            ]))
            ->assertConflict();
    }

    public function test_issue_requires_supplier_facing_fields(): void
    {
        $po = $this->approvedPurchaseOrder();
        $po->forceFill(['payment_terms' => null])->save();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/issue", $this->issuePayload($po))
            ->assertConflict()
            ->assertJsonPath('error.message', fn (string $message): bool => str_contains($message, 'payment terms'));
    }

    public function test_supplier_exports_are_generated_from_stored_issue_snapshot(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $po->forceFill(['payment_terms' => 'Changed after issue'])->save();

        $this->actingAsTenant($po->tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/supplier-export.json")
            ->assertOk()
            ->assertJsonPath('format', 'json')
            ->assertJsonPath('purchaseOrder.number', $po->number)
            ->assertJsonPath('purchaseOrder.paymentTerms', 'Net 30');

        $this->actingAsTenant($po->tenant, $buyer)
            ->get("/api/purchase-orders/{$po->id}/supplier-export.csv")
            ->assertOk()
            ->assertSee($po->number)
            ->assertSee('Net 30');
    }

    public function test_recorded_supplier_export_updates_metadata_and_audit(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/supplier-export.json")
            ->assertOk()
            ->assertJsonPath('format', 'json');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'last_supplier_exported_by_user_id' => $buyer->id,
            'last_supplier_export_format' => 'json',
            'lock_version' => $po->lock_version + 1,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'purchase_order.supplier_exported',
        ]);
    }

    public function test_buyer_can_acknowledge_issued_purchase_order(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/acknowledge", [
                'lockVersion' => $po->lock_version,
                'acknowledgedContactName' => 'Priya Supplier',
                'acknowledgementReference' => 'ACK-PO-100',
                'acknowledgementNote' => 'Supplier confirmed delivery in week 29.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged')
            ->assertJsonPath('data.supplierIssue.acknowledgementReference', 'ACK-PO-100')
            ->assertJsonPath('data.permissions.canAcknowledgeSupplier', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'purchase_order.acknowledged',
        ]);
    }

    public function test_acknowledgement_requires_issued_status_lock_version_and_evidence(): void
    {
        $approved = $this->approvedPurchaseOrder();
        $buyer = $this->tenantUser($approved->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($approved->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$approved->id}/acknowledge", [
                'lockVersion' => $approved->lock_version,
                'acknowledgedContactName' => 'Priya Supplier',
            ])
            ->assertConflict();

        $issued = $this->issuedPurchaseOrder(lockVersion: 3);
        $issuedBuyer = $this->tenantUser($issued->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($issued->tenant, $issuedBuyer)
            ->postJson("/api/purchase-orders/{$issued->id}/acknowledge", [
                'lockVersion' => 2,
                'acknowledgedContactName' => 'Priya Supplier',
            ])
            ->assertConflict();

        $this->actingAsTenant($issued->tenant, $issuedBuyer)
            ->postJson("/api/purchase-orders/{$issued->id}/acknowledge", [
                'lockVersion' => $issued->lock_version,
            ])
            ->assertUnprocessable();
    }

    private function issuePayload(PurchaseOrder $po): array
    {
        return [
            'lockVersion' => $po->lock_version,
            'method' => 'manual_email',
            'supplierContactName' => 'Priya Supplier',
            'supplierContactEmail' => 'priya.supplier@example.com',
            'message' => 'Please confirm receipt and planned delivery date.',
        ];
    }

    private function approvedPurchaseOrder(int $lockVersion = 1): PurchaseOrder
    {
        return $this->purchaseOrder(status: 'approved', lockVersion: $lockVersion);
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
            'number' => 'RFQ-2026-POI',
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
            'number' => 'POH-2026-POI',
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
            'number' => 'PO-2026-POI-'.Str::upper(Str::random(4)),
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

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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_record_full_goods_receipt(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'receiptReference' => 'D/O 98765',
                'notes' => 'Delivered on time.',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '10.0000',
                    'quantityAccepted' => '10.0000',
                    'notes' => 'All items in good condition.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.lines.0.quantityReceived', '10.0000')
            ->assertJsonPath('data.lines.0.quantityAccepted', '10.0000');

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '10.0000',
            'cumulative_quantity_accepted' => '10.0000',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'action' => 'goods_receipt.recorded',
        ]);
    }

    public function test_partial_receipts_accumulate(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '4.0000',
                    'quantityAccepted' => '4.0000',
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '4.0000',
        ]);

        $po->refresh();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '6.0000',
                    'quantityAccepted' => '5.0000',
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '10.0000',
            'cumulative_quantity_accepted' => '9.0000',
        ]);
    }

    public function test_over_receipt_beyond_tolerance_is_rejected(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        $line->forceFill(['over_receipt_tolerance_percent' => '10.00'])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '12.0000',
                    'quantityAccepted' => '12.0000',
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_receipt_against_cancelled_line_is_rejected(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        $line->forceFill(['status' => 'cancelled'])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertStatus(422);
    }

    public function test_requester_and_buyer_can_confirm_receipt(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '10.0000',
                    'quantityAccepted' => '10.0000',
                ]],
            ]);

        $receiptId = $response->json('data.id');

        [$otherTenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'requester_confirmed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-buyer", [
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'buyer_confirmed');
    }

    public function test_lock_version_conflict_on_stale_receipt(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer, 4);
        $line = $po->lines->first();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => 3,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertStatus(409);
    }

    public function test_cross_tenant_po_access_is_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_non_buyer_cannot_record_receipt(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->first();

        [$otherTenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_cannot_record_receipt_against_draft_po(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->purchaseOrder($tenant, $buyer, 'draft');
        $line = $po->lines->first();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    private function issuedPurchaseOrder(Tenant $tenant, User $buyer, int $lockVersion = 1): PurchaseOrder
    {
        $po = $this->purchaseOrder($tenant, $buyer, 'issued', $lockVersion);

        $po->forceFill([
            'issued_by_user_id' => $buyer->id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'supplier_contact_name' => 'Priya Supplier',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'supplier_version_number' => 1,
            'supplier_version' => ['versionNumber' => 1, 'purchaseOrder' => ['number' => $po->number]],
        ])->save();

        return $po->fresh('lines');
    }

    private function purchaseOrder(Tenant $tenant, User $buyer, string $status = 'draft', int $lockVersion = 1): PurchaseOrder
    {
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
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'MYR',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'expected_delivery_date' => '2026-07-02',
            'billing_name' => 'Acme Finance',
            'billing_address' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
            'lock_version' => $lockVersion,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'description' => 'Pallet rack bay',
            'category' => 'Warehouse',
            'unit' => 'each',
            'quantity' => '10.0000',
            'unit_price' => '12000.0000',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'currency' => 'MYR',
            'expected_delivery_date' => '2026-07-02',
            'delivery_location' => 'Dock 4',
            'source_snapshot' => [],
            'status' => 'open',
        ]);

        return $po->fresh('lines');
    }

    private function tenantUserPair(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }
}

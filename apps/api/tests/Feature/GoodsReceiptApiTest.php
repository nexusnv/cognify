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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_record_full_goods_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
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
            'tenant_id' => $po->tenant_id,
            'action' => 'goods_receipt.recorded',
        ]);
    }

    public function test_partial_receipts_accumulate(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
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

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-14',
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
        $po = $this->issuedPurchaseOrder(lockVersion: 1);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $line->forceFill(['over_receipt_tolerance_percent' => '10.00'])->save();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '12.0000',
                    'quantityAccepted' => '12.0000',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.quantityReceived']);
    }

    public function test_receipt_against_cancelled_line_is_rejected(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $line->forceFill(['status' => 'cancelled'])->save();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.purchaseOrderLineId']);
    }

    public function test_requester_and_buyer_can_confirm_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $response = $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '10.0000',
                    'quantityAccepted' => '10.0000',
                ]],
            ]);

        $receiptId = $response->json('data.id');

        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertForbidden();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'requester_confirmed');

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-buyer", [
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'buyer_confirmed');
    }

    public function test_lock_version_conflict_on_stale_receipt(): void
    {
        $po = $this->issuedPurchaseOrder(lockVersion: 4);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => 3,
                'receiptDate' => '2026-06-12',
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
        $po = $this->issuedPurchaseOrder();
        $otherTenant = Tenant::factory()->create();
        $buyer = $this->tenantUser($otherTenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($otherTenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertNotFound();
    }

    public function test_non_buyer_cannot_record_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
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
        $po = $this->purchaseOrder(status: 'draft');
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    private function issuedPurchaseOrder(int $lockVersion = 1): PurchaseOrder
    {
        $po = $this->purchaseOrder(status: 'issued', lockVersion: $lockVersion);

        $po->forceFill([
            'issued_by_user_id' => $this->tenantUser($po->tenant, TenantRole::Buyer->value)->id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'supplier_contact_name' => 'Priya Supplier',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'supplier_version_number' => 1,
            'supplier_version' => ['versionNumber' => 1, 'purchaseOrder' => ['number' => $po->number]],
        ])->save();

        return $po->fresh('lines');
    }

    private function purchaseOrder(string $status = 'draft', int $lockVersion = 1): PurchaseOrder
    {
        $tenant = Tenant::factory()->create();
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Northwind Traders']);
        $rfq = Rfq::factory()->for($tenant)->create(['status' => RfqStatus::Awarded]);
        $invitation = RfqInvitation::factory()->for($tenant)->for($rfq)->for($vendor)->create(['status' => RfqInvitationStatus::Awarded]);
        $quotation = Quotation::factory()->for($tenant)->for($rfq)->for($invitation)->for($vendor)->create(['status' => QuotationStatus::Awarded]);
        $version = QuotationVersion::factory()->for($tenant)->for($quotation)->create(['version_number' => 1]);
        $recommendation = RfqAwardRecommendation::factory()->for($tenant)->for($rfq)->for($vendor, 'recommendedVendor')->create();
        $handoff = PurchaseOrderRequestHandoff::factory()->for($tenant)->for($recommendation, 'awardRecommendation')->create([
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
        ]);
        $buyer = $this->tenantUser($tenant, TenantRole::Buyer->value);

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

    private function tenantUser(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }
}

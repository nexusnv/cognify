<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\Carbon;
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
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_shipment(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'carrierName' => 'DHL Supply Chain',
                'trackingReference' => 'TRACK-001',
                'shipmentDate' => Carbon::today()->toDateString(),
                'estimatedArrivalDate' => Carbon::today()->addDays(2)->toDateString(),
                'notes' => 'Split across two pallets.',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '10.0000',
                    'backorderQuantity' => '0.0000',
                    'notes' => 'Ready for unloading.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.lines.0.quantityShipped', '10.0000');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'action' => 'fulfillment.shipment.recorded',
        ]);
    }

    public function test_multiple_shipments_allowed(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'carrierName' => 'DHL Supply Chain',
                'trackingReference' => 'TRACK-001',
                'shipmentDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '4.0000',
                ]],
            ])
            ->assertCreated();

        $po->refresh();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'carrierName' => 'Ninja Van',
                'trackingReference' => 'TRACK-002',
                'shipmentDate' => Carbon::today()->addDay()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '6.0000',
                ]],
            ])
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/shipments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_add_tracking_event(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'in_transit',
                'occurredAt' => now()->toISOString(),
                'location' => 'Port Klang',
                'notes' => 'Loaded for final mile.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_transit');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'action' => 'fulfillment.shipment.tracking_event',
        ]);
    }

    public function test_created_tracking_event_status_matches_contract(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'created',
                'occurredAt' => now()->toISOString(),
                'notes' => 'Supplier created tracking record.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'created');
    }

    public function test_delivered_tracking_event_updates_shipment_status(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'delivered',
                'occurredAt' => now()->toISOString(),
            ])
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/shipments/{$shipmentId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    public function test_update_backorder(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        [$shipmentId, $shipmentLineId] = $this->createShipmentWithLine($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/shipments/{$shipmentId}/lines/{$shipmentLineId}/backorder", [
                'backorderQuantity' => '3.0000',
                'backorderExpectedAt' => Carbon::today()->addDays(5)->toDateString(),
                'notes' => 'Supplier advised later vessel arrival.',
            ])
            ->assertOk()
            ->assertJsonPath('data.backorderQuantity', '3.0000');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'action' => 'fulfillment.shipment.backorder_updated',
        ]);
    }

    public function test_cancel_shipment(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/shipments/{$shipmentId}", [
                'lockVersion' => 1,
                'reason' => 'Supplier merged into consolidated shipment.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_shipment_preserves_notes_from_contract_payload(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/shipments/{$shipmentId}", [
                'lockVersion' => 1,
                'notes' => 'Supplier merged into consolidated shipment.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.notes', 'Supplier merged into consolidated shipment.');
    }

    public function test_fulfillment_status_computed_correctly(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/fulfillment")
            ->assertOk()
            ->assertJsonPath('data.overallStatus', 'pending_shipment');

        $po->refresh();
        $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/fulfillment")
            ->assertOk()
            ->assertJsonPath('data.overallStatus', 'awaiting_delivery');
    }

    public function test_cross_tenant_access_is_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();
        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'shipmentDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_non_buyer_cannot_create_shipment(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $line = $po->lines->firstOrFail();

        $requester = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($requester->id, ['role' => TenantRole::Requester->value]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'shipmentDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    public function test_requisition_requester_can_read_fulfillment_status_shipments_and_tracking_events(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $requester = $this->attachRequesterToPurchaseOrder($tenant, $po);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'in_transit',
                'occurredAt' => now()->toISOString(),
            ])
            ->assertCreated();

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/purchase-orders/{$po->id}/fulfillment")
            ->assertOk()
            ->assertJsonPath('data.overallStatus', 'awaiting_delivery');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/purchase-orders/{$po->id}/shipments")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/shipments/{$shipmentId}/tracking-events")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_requisition_requester_cannot_mutate_fulfillment(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $requester = $this->attachRequesterToPurchaseOrder($tenant, $po);
        [$shipmentId, $shipmentLineId] = $this->createShipmentWithLine($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/shipments/{$shipmentId}", [
                'lockVersion' => 1,
                'notes' => 'Requester cannot edit shipment notes.',
            ])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'in_transit',
                'occurredAt' => now()->toISOString(),
            ])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/shipments/{$shipmentId}/lines/{$shipmentLineId}/backorder", [
                'backorderQuantity' => '1.0000',
            ])
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->deleteJson("/api/shipments/{$shipmentId}", [
                'lockVersion' => 1,
            ])
            ->assertForbidden();
    }

    public function test_cancel_delivered_shipment_is_rejected(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $shipmentId = $this->createShipment($tenant, $buyer, $po);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/shipments/{$shipmentId}/tracking-events", [
                'status' => 'delivered',
                'occurredAt' => now()->toISOString(),
            ])
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/shipments/{$shipmentId}", [
                'lockVersion' => 2,
            ])
            ->assertStatus(422);
    }

    public function test_lock_version_conflict(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer, 3);
        $line = $po->lines->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => 2,
                'shipmentDate' => Carbon::today()->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityShipped' => '1.0000',
                ]],
            ])
            ->assertStatus(409);
    }

    public function test_fulfillment_status_detects_delayed(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $po = $this->issuedPurchaseOrder($tenant, $buyer);
        $po->forceFill(['expected_delivery_date' => Carbon::today()->subDays(2)->toDateString()])->save();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/purchase-orders/{$po->id}/fulfillment")
            ->assertOk()
            ->assertJsonPath('data.overallStatus', 'delayed')
            ->assertJsonPath('data.isDelayed', true);
    }

    private function createShipment(Tenant $tenant, User $buyer, PurchaseOrder $po): string
    {
        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'carrierName' => 'DHL Supply Chain',
                'trackingReference' => 'TRACK-001',
                'shipmentDate' => Carbon::today()->toDateString(),
                'estimatedArrivalDate' => Carbon::today()->addDays(2)->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $po->lines->firstOrFail()->id,
                    'quantityShipped' => '10.0000',
                ]],
            ]);

        return (string) $response->json('data.id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createShipmentWithLine(Tenant $tenant, User $buyer, PurchaseOrder $po): array
    {
        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/shipments", [
                'lockVersion' => $po->lock_version,
                'carrierName' => 'DHL Supply Chain',
                'trackingReference' => 'TRACK-001',
                'shipmentDate' => Carbon::today()->toDateString(),
                'estimatedArrivalDate' => Carbon::today()->addDays(2)->toDateString(),
                'lines' => [[
                    'purchaseOrderLineId' => (string) $po->lines->firstOrFail()->id,
                    'quantityShipped' => '7.0000',
                    'backorderQuantity' => '3.0000',
                ]],
            ]);

        return [
            (string) $response->json('data.id'),
            (string) $response->json('data.lines.0.id'),
        ];
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

    private function attachRequesterToPurchaseOrder(Tenant $tenant, PurchaseOrder $po): User
    {
        $requester = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($requester->id, ['role' => TenantRole::Requester->value]);

        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-'.Str::upper(Str::random(6)),
            'title' => 'Requester-owned warehouse racking',
            'business_justification' => 'Needed for warehouse capacity.',
            'needed_by_date' => Carbon::today()->addWeeks(2)->toDateString(),
            'department' => 'Operations',
            'currency' => 'MYR',
            'status' => 'approved',
            'submitted_at' => now(),
            'approved_at' => now(),
            'approved_by_id' => $po->approved_by_user_id,
        ]);

        $po->forceFill(['requisition_id' => $requisition->id])->save();
        $po->handoff?->forceFill(['requisition_id' => $requisition->id])->save();

        return $requester;
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

    /**
     * @return array{0: Tenant, 1: User}
     */
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

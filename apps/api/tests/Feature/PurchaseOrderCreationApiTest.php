<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
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
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_draft_purchase_order_from_ready_handoff(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source.handoffId', (string) $handoff->id)
            ->assertJsonPath('data.vendor.id', (string) $handoff->vendor_id)
            ->assertJsonPath('data.currency', 'MYR')
            ->assertJsonPath('data.totalAmount', '131100.00')
            ->assertJsonPath('data.lines.0.description', 'Pallet rack bay')
            ->assertJsonPath('data.permissions.canUpdate', true)
            ->assertJsonPath('data.permissions.canMarkReadyForReview', true);

        $this->assertDatabaseHas('purchase_orders', [
            'tenant_id' => $handoff->tenant_id,
            'purchase_order_request_handoff_id' => $handoff->id,
            'status' => 'draft',
            'currency' => 'MYR',
            'total_amount' => '131100.00',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $handoff->tenant_id,
            'action' => 'purchase_order.created',
        ]);
    }

    public function test_exported_handoff_can_create_purchase_order(): void
    {
        $handoff = $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Exported);
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source.handoffId', (string) $handoff->id);
    }

    public function test_draft_and_cancelled_handoffs_cannot_create_purchase_order(): void
    {
        $draft = $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Draft);
        $cancelled = $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Cancelled);
        $draftBuyer = $this->tenantUser($draft->tenant, TenantRole::Buyer->value);
        $cancelledBuyer = $this->tenantUser($cancelled->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($draft->tenant, $draftBuyer)
            ->postJson("/api/po-handoffs/{$draft->id}/purchase-order")
            ->assertConflict()
            ->assertJsonPath('error.message', 'PO handoff must be ready or exported before creating a purchase order.');

        $this->assertNoPurchaseOrderForHandoff($draft->id);

        $this->actingAsTenant($cancelled->tenant, $cancelledBuyer)
            ->postJson("/api/po-handoffs/{$cancelled->id}/purchase-order")
            ->assertConflict()
            ->assertJsonPath('error.message', 'Cancelled PO handoffs cannot create purchase orders.');

        $this->assertNoPurchaseOrderForHandoff($cancelled->id);
    }

    public function test_duplicate_creation_reveals_existing_purchase_order(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

        $first = $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, PurchaseOrder::query()->where('purchase_order_request_handoff_id', $handoff->id)->count());
    }

    public function test_creation_persists_lines_from_handoff_snapshot(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

        $purchaseOrderId = $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseHas('purchase_order_lines', [
            'purchase_order_id' => $purchaseOrderId,
            'tenant_id' => $handoff->tenant_id,
            'line_number' => 1,
            'description' => 'Pallet rack bay',
            'quantity' => '10.0000',
            'unit' => 'set',
            'unit_price' => '13110.0000',
            'total_amount' => '131100.00',
            'currency' => 'MYR',
        ]);
    }

    public function test_cross_tenant_handoff_access_is_denied(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertForbidden();
    }

    public function test_requester_cannot_create_or_update_purchase_order(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $requester = $this->tenantUser($handoff->tenant, TenantRole::Requester->value);
        $po = $this->draftPurchaseOrder($handoff);
        $vendorLike = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->actingAsTenant($handoff->tenant, $requester)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertForbidden();

        $this->actingAsTenant($po->tenant, $requester)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'buyerNote' => 'Requester should not update purchase orders.',
            ])
            ->assertForbidden();

        Sanctum::actingAs($vendorLike);
        app(CurrentTenant::class)->set($handoff->tenant);

        $this->withHeader('X-Tenant-Id', (string) $handoff->tenant->id)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', (string) $po->tenant->id)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'buyerNote' => 'Vendor-like actor should not update purchase orders.',
            ])
            ->assertForbidden();
    }

    public function test_draft_operational_fields_update_with_lock_version(): void
    {
        $po = $this->draftPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'requestedPoDate' => '2026-06-18',
                'expectedDeliveryDate' => '2026-07-02',
                'billingName' => 'Acme Finance',
                'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                'shippingName' => 'Acme Warehouse',
                'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
                'deliveryAttention' => 'Warehouse receiving',
                'paymentTerms' => 'Net 30',
                'deliveryTerms' => 'DAP',
                'buyerNote' => 'Confirm delivery slot before dispatch.',
                'financeNote' => 'Charge to expansion budget.',
            ])
            ->assertOk()
            ->assertJsonPath('data.requestedPoDate', '2026-06-18')
            ->assertJsonPath('data.shippingAddress.city', 'Shah Alam')
            ->assertJsonPath('data.lockVersion', $po->lock_version + 1);
    }

    public function test_patch_requires_at_least_one_mutable_field_besides_lock_version(): void
    {
        $po = $this->draftPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertUnprocessable();
    }

    public function test_patch_with_unchanged_values_does_not_increment_lock_version_or_write_audit_noise(): void
    {
        $po = $this->draftPurchaseOrder(attributes: [
            'requested_po_date' => '2026-06-18',
            'expected_delivery_date' => '2026-07-02',
            'billing_name' => 'Acme Finance',
            'billing_address' => json_encode(['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY']),
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => json_encode(['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY']),
            'delivery_attention' => 'Warehouse receiving',
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'buyer_note' => 'Confirm delivery slot before dispatch.',
            'finance_note' => 'Charge to expansion budget.',
        ]);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $auditCountBefore = AuditEvent::query()
            ->where('tenant_id', $po->tenant->id)
            ->where('action', 'purchase_order.updated')
            ->count();

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'requestedPoDate' => '2026-06-18',
                'expectedDeliveryDate' => '2026-07-02',
                'billingName' => 'Acme Finance',
                'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                'shippingName' => 'Acme Warehouse',
                'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
                'deliveryAttention' => 'Warehouse receiving',
                'paymentTerms' => 'Net 30',
                'deliveryTerms' => 'DAP',
                'buyerNote' => 'Confirm delivery slot before dispatch.',
                'financeNote' => 'Charge to expansion budget.',
            ])
            ->assertOk()
            ->assertJsonPath('data.lockVersion', $po->lock_version);

        $this->assertPurchaseOrderState($po, [
            'lock_version' => 1,
            'billing_name' => 'Acme Finance',
            'shipping_name' => 'Acme Warehouse',
            'buyer_note' => 'Confirm delivery slot before dispatch.',
        ]);
        $this->assertSame(
            $auditCountBefore,
            AuditEvent::query()->where('tenant_id', $po->tenant->id)->where('action', 'purchase_order.updated')->count(),
        );
    }

    public function test_patch_rejects_iso_timestamp_dates_for_purchase_order_updates(): void
    {
        $po = $this->draftPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'requestedPoDate' => '2026-06-18T00:00:00Z',
            ])
            ->assertUnprocessable();
    }

    public function test_stale_update_returns_conflict(): void
    {
        $po = $this->draftPurchaseOrder(lockVersion: 2, attributes: ['buyer_note' => 'original note']);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", ['lockVersion' => 1, 'buyerNote' => 'stale'])
            ->assertConflict();

        $this->assertPurchaseOrderState($po, [
            'status' => 'draft',
            'lock_version' => 2,
            'buyer_note' => 'original note',
        ]);
    }

    public function test_ready_for_review_validates_required_fields_and_changes_status(): void
    {
        $po = $this->draftPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/ready-for-review", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertConflict();

        $this->assertPurchaseOrderState($po, [
            'status' => 'draft',
            'lock_version' => 1,
            'requested_po_date' => null,
            'expected_delivery_date' => null,
            'billing_name' => null,
            'shipping_name' => null,
            'buyer_note' => null,
        ]);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->fresh()->lock_version,
                'requestedPoDate' => '2026-06-18',
                'expectedDeliveryDate' => '2026-07-02',
                'billingName' => 'Acme Finance',
                'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                'shippingName' => 'Acme Warehouse',
                'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
                'deliveryAttention' => 'Warehouse receiving',
                'paymentTerms' => 'Net 30',
                'deliveryTerms' => 'DAP',
                'buyerNote' => 'Confirm delivery slot before dispatch.',
                'financeNote' => 'Charge to expansion budget.',
            ])
            ->assertOk();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->fresh()->id}/ready-for-review", [
                'lockVersion' => $po->fresh()->lock_version,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_review');
    }

    public function test_ready_for_review_rejects_blank_required_text_fields(): void
    {
        $po = $this->draftPurchaseOrder(attributes: [
            'billing_name' => '   ',
            'billing_address' => json_encode(['line1' => 'Level 10']),
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => json_encode(['line1' => 'Dock 4']),
            'payment_terms' => 'Net 30',
        ]);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/ready-for-review", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertConflict();

        $this->assertPurchaseOrderState($po, [
            'status' => 'draft',
            'lock_version' => 1,
        ]);
    }

    public function test_cancelled_purchase_order_cannot_be_updated_or_marked_ready(): void
    {
        $po = $this->cancelledPurchaseOrder(attributes: ['buyer_note' => 'cancelled note']);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$po->id}", [
                'lockVersion' => $po->lock_version,
                'buyerNote' => 'Cancelled records should stay immutable.',
            ])
            ->assertConflict();

        $this->assertPurchaseOrderState($po, [
            'status' => 'cancelled',
            'lock_version' => 1,
            'buyer_note' => 'cancelled note',
        ]);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/ready-for-review", [
                'lockVersion' => $po->lock_version,
            ])
            ->assertConflict();

        $this->assertPurchaseOrderState($po, [
            'status' => 'cancelled',
            'lock_version' => 1,
            'buyer_note' => 'cancelled note',
        ]);
    }

    public function test_purchase_order_list_is_paginated_and_filterable(): void
    {
        $matchingHandoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($matchingHandoff->tenant, TenantRole::Buyer->value);
        $requester = $this->tenantUser($matchingHandoff->tenant, TenantRole::Requester->value);
        $otherHandoff = $this->readyPurchaseOrderHandoff();
        $otherRequester = $this->tenantUser($otherHandoff->tenant, TenantRole::Requester->value);

        $matchingHandoff->forceFill(['requested_by_user_id' => $requester->id])->save();
        $otherHandoff->forceFill(['requested_by_user_id' => $otherRequester->id])->save();

        $matching = $this->actingAsTenant($matchingHandoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$matchingHandoff->id}/purchase-order")
            ->assertCreated()
            ->json('data');

        $this->draftPurchaseOrder($otherHandoff, attributes: [
            'number' => 'PO-2026-OTHER',
            'status' => 'cancelled',
            'updated_at' => '2026-06-01 10:00:00',
        ]);

        $this->actingAsTenant($matchingHandoff->tenant, $buyer)
            ->getJson('/api/purchase-orders?status=draft&vendorId='.$matchingHandoff->vendor_id.'&requestedByUserId='.$requester->id.'&search=Northwind&perPage=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', data_get($matching, 'id'))
            ->assertJsonPath('meta.currentPage', 1)
            ->assertJsonPath('meta.perPage', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.lastPage', 1);
    }

    public function test_purchase_order_list_rejects_unknown_status_filter(): void
    {
        $po = $this->draftPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->getJson('/api/purchase-orders?status=not-a-status')
            ->assertUnprocessable();
    }

    public function test_purchase_order_actions_record_context_rich_audit_metadata(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

        $purchaseOrderId = $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->json('data.id');

        $this->actingAsTenant($handoff->tenant, $buyer)
            ->patchJson("/api/purchase-orders/{$purchaseOrderId}", [
                'lockVersion' => 1,
                'billingName' => 'Acme Finance',
                'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                'shippingName' => 'Acme Warehouse',
                'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
                'paymentTerms' => 'Net 30',
                'buyerNote' => 'Updated buyer note',
            ])
            ->assertOk();

        $this->actingAsTenant($handoff->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$purchaseOrderId}/ready-for-review", [
                'lockVersion' => 2,
            ])
            ->assertOk();

        $cancelled = $this->draftPurchaseOrder();
        $this->actingAsTenant($cancelled->tenant, $this->tenantUser($cancelled->tenant, TenantRole::Buyer->value))
            ->postJson("/api/purchase-orders/{$cancelled->id}/cancel", [
                'lockVersion' => $cancelled->lock_version,
                'reason' => 'Buyer cancelled duplicate draft.',
            ])
            ->assertOk();

        $createdPurchaseOrder = PurchaseOrder::query()->with('handoff')->findOrFail($purchaseOrderId);
        $createdEvent = AuditEvent::query()->where('action', 'purchase_order.created')->latest('id')->firstOrFail();
        $updatedEvent = AuditEvent::query()->where('action', 'purchase_order.updated')->latest('id')->firstOrFail();
        $readyEvent = AuditEvent::query()->where('action', 'purchase_order.ready_for_review')->latest('id')->firstOrFail();
        $cancelledEvent = AuditEvent::query()->where('action', 'purchase_order.cancelled')->latest('id')->firstOrFail();

        $this->assertSame($purchaseOrderId, data_get($createdEvent->metadata, 'purchaseOrderId'));
        $this->assertSame($createdPurchaseOrder->number, data_get($createdEvent->metadata, 'purchaseOrderNumber'));
        $this->assertSame((string) $handoff->id, data_get($createdEvent->metadata, 'handoffId'));
        $this->assertSame('POH-2026-000001', data_get($createdEvent->metadata, 'handoffNumber'));
        $this->assertSame((string) $handoff->rfq_award_recommendation_id, data_get($createdEvent->metadata, 'recommendationId'));
        $this->assertSame((string) $handoff->vendor_id, data_get($createdEvent->metadata, 'vendorId'));
        $this->assertSame('131100.00', data_get($createdEvent->metadata, 'totalAmount'));
        $this->assertSame('MYR', data_get($createdEvent->metadata, 'currency'));
        $this->assertSame('billingName', data_get($updatedEvent->metadata, 'changedFields.0'));
        $this->assertSame('ready_for_review', data_get($readyEvent->metadata, 'toStatus'));
        $this->assertSame('Buyer cancelled duplicate draft.', data_get($cancelledEvent->metadata, 'reason'));
    }

    public function test_purchase_order_routes_require_real_session_auth_and_tenant_context(): void
    {
        $handoff = $this->readyPurchaseOrderHandoff();
        $buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);
        $otherTenant = Tenant::query()->create(['name' => 'Second tenant for PO session']);
        $otherTenant->users()->attach($buyer->id, ['role' => TenantRole::Buyer->value]);

        $buyer->forceFill([
            'email' => 'purchase-order-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        Auth::forgetGuards();
        app(CurrentTenant::class)->clear();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'purchase-order-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withoutHeader('X-Tenant-Id')
            ->withHeader('Origin', 'http://localhost:8880')
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'X-Tenant-Id header is required for users with multiple tenants.')
            ->assertJsonPath('error.code', 'ambiguous_tenant');

        $createdPurchaseOrderId = $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $handoff->tenant_id)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertCreated()
            ->json('data.id');

        $this->assertIsString($createdPurchaseOrderId);
        $this->assertNotSame('', $createdPurchaseOrderId);

        $purchaseOrder = new PurchaseOrderReference($createdPurchaseOrderId, $handoff->tenant, 1);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $handoff->tenant_id)
            ->patchJson("/api/purchase-orders/{$purchaseOrder->id}", [
                'lockVersion' => $purchaseOrder->lock_version,
                'requestedPoDate' => '2026-06-18',
                'expectedDeliveryDate' => '2026-07-02',
                'billingName' => 'Acme Finance',
                'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
                'shippingName' => 'Acme Warehouse',
                'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
                'deliveryAttention' => 'Warehouse receiving',
                'paymentTerms' => 'Net 30',
                'deliveryTerms' => 'DAP',
                'buyerNote' => 'session update',
                'financeNote' => 'Charge to expansion budget.',
            ])
            ->assertOk()
            ->assertJsonPath('data.lockVersion', $purchaseOrder->lock_version + 1);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $handoff->tenant_id)
            ->postJson("/api/purchase-orders/{$purchaseOrder->fresh()->id}/ready-for-review", [
                'lockVersion' => $purchaseOrder->fresh()->lock_version,
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $handoff->tenant_id)
            ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
            ->assertUnauthorized();
    }

    private function readyPurchaseOrderHandoff(): PurchaseOrderRequestHandoff
    {
        return $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Ready);
    }

    private function purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus $status): PurchaseOrderRequestHandoff
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        [, $approver] = $this->tenantUserPair(TenantRole::Approver->value, $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);

        $attributes = match ($status) {
            PurchaseOrderRequestHandoffStatus::Ready => [
                'status' => $status->value,
                'ready_by_user_id' => $buyer->id,
                'ready_at' => now(),
            ],
            PurchaseOrderRequestHandoffStatus::Exported => [
                'status' => $status->value,
                'ready_by_user_id' => $buyer->id,
                'ready_at' => now()->subMinute(),
                'last_exported_by_user_id' => $buyer->id,
                'last_exported_at' => now(),
                'last_export_format' => 'json',
            ],
            PurchaseOrderRequestHandoffStatus::Cancelled => [
                'status' => $status->value,
                'cancelled_by_user_id' => $buyer->id,
                'cancelled_at' => now(),
                'cancelled_reason' => 'Superseded by corrected recommendation.',
            ],
            default => [
                'status' => $status->value,
            ],
        };

        $handoffId = $this->seedPurchaseOrderRequestHandoff(
            tenant: $tenant,
            buyer: $buyer,
            rfq: $rfq,
            recommendation: $recommendation,
            quotation: $quotation,
            version: $version,
            attributes: $attributes,
        );

        return PurchaseOrderRequestHandoff::query()->with('tenant')->findOrFail($handoffId);
    }

    private function draftPurchaseOrder(
        ?PurchaseOrderRequestHandoff $handoff = null,
        int $lockVersion = 1,
        array $attributes = [],
    ): PurchaseOrderReference
    {
        return $this->seedPurchaseOrderReference('draft', $handoff, $lockVersion, $attributes);
    }

    private function cancelledPurchaseOrder(
        ?PurchaseOrderRequestHandoff $handoff = null,
        int $lockVersion = 1,
        array $attributes = [],
    ): PurchaseOrderReference
    {
        return $this->seedPurchaseOrderReference('cancelled', $handoff, $lockVersion, $attributes);
    }

    private function seedPurchaseOrderReference(
        string $status,
        ?PurchaseOrderRequestHandoff $handoff = null,
        int $lockVersion = 1,
        array $attributes = [],
    ): PurchaseOrderReference
    {
        $handoff ??= $this->readyPurchaseOrderHandoff();
        $reference = new PurchaseOrderReference((string) Str::uuid(), $handoff->tenant, $lockVersion);

        if (! Schema::hasTable('purchase_orders')) {
            return $reference;
        }

        $now = now();

        DB::table('purchase_orders')->updateOrInsert(
            ['id' => $reference->id],
            array_merge([
                'id' => $reference->id,
                'tenant_id' => $handoff->tenant_id,
                'purchase_order_request_handoff_id' => $handoff->id,
                'rfq_award_recommendation_id' => $handoff->rfq_award_recommendation_id,
                'approval_instance_id' => $handoff->approval_instance_id,
                'rfq_id' => $handoff->rfq_id,
                'requisition_id' => $handoff->requisition_id,
                'project_id' => $handoff->project_id,
                'vendor_id' => $handoff->vendor_id,
                'quotation_id' => $handoff->quotation_id,
                'quotation_version_id' => $handoff->quotation_version_id,
                'number' => 'PO-2026-000001',
                'status' => $status,
                'currency' => 'MYR',
                'subtotal_amount' => '131100.00',
                'tax_amount' => null,
                'freight_amount' => null,
                'discount_amount' => null,
                'total_amount' => '131100.00',
                'requested_po_date' => null,
                'expected_delivery_date' => null,
                'billing_name' => null,
                'billing_address' => null,
                'shipping_name' => null,
                'shipping_address' => null,
                'delivery_attention' => $handoff->delivery_attention,
                'payment_terms' => null,
                'delivery_terms' => null,
                'buyer_note' => null,
                'finance_note' => $handoff->finance_note,
                'source_snapshot' => json_encode($handoff->source_snapshot),
                'approval_snapshot' => json_encode($handoff->approval_snapshot),
                'evidence_snapshot' => json_encode($handoff->evidence_snapshot),
                'created_by_user_id' => $handoff->requested_by_user_id,
                'ready_for_review_by_user_id' => null,
                'ready_for_review_at' => null,
                'cancelled_by_user_id' => $status === 'cancelled' ? $handoff->requested_by_user_id : null,
                'cancelled_at' => $status === 'cancelled' ? $now : null,
                'cancelled_reason' => $status === 'cancelled' ? 'Cancelled before buyer review.' : null,
                'lock_version' => $lockVersion,
                'created_at' => $now,
                'updated_at' => $now,
            ], $attributes),
        );

        if (Schema::hasTable('purchase_order_lines')) {
            DB::table('purchase_order_lines')->updateOrInsert(
                ['purchase_order_id' => $reference->id, 'line_number' => 1],
                [
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $handoff->tenant_id,
                    'purchase_order_id' => $reference->id,
                    'source_line_id' => 'rfq-line-1',
                    'line_number' => 1,
                    'description' => 'Pallet rack bay',
                    'category' => null,
                    'sku' => null,
                    'unit' => 'set',
                    'quantity' => '10.0000',
                    'unit_price' => '13110.0000',
                    'subtotal_amount' => '131100.00',
                    'tax_amount' => null,
                    'freight_amount' => null,
                    'discount_amount' => null,
                    'total_amount' => '131100.00',
                    'currency' => 'MYR',
                    'needed_by_date' => null,
                    'expected_delivery_date' => null,
                    'delivery_location' => null,
                    'notes' => null,
                    'source_snapshot' => json_encode([
                        'lineNumber' => 1,
                        'description' => 'Pallet rack bay',
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        return $reference;
    }

    private function assertPurchaseOrderState(PurchaseOrderReference $po, array $expected): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        $this->assertDatabaseHas('purchase_orders', array_merge(
            ['id' => $po->id, 'tenant_id' => $po->tenant->id],
            $expected,
        ));
    }

    private function assertNoPurchaseOrderForHandoff(string $handoffId): void
    {
        if (! Schema::hasTable('purchase_orders')) {
            return;
        }

        $this->assertSame(0, PurchaseOrder::query()
            ->where('purchase_order_request_handoff_id', $handoffId)
            ->count());
    }

    private function approvedRecommendation(Tenant $tenant, User $buyer, User $approver): array
    {
        [$rfq, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);
        $task = ApprovalTask::query()->where('subject_id', $recommendation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();

        return [$rfq, $recommendation->refresh(), $quotation, $version];
    }

    private function routedRecommendation(Tenant $tenant, User $buyer, User $approver): array
    {
        [$rfq, $recommendation] = $this->pendingRecommendation($tenant, $buyer);
        $this->createAwardPolicy($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/approval-route")
            ->assertOk();

        return [$rfq, $recommendation->refresh()];
    }

    private function pendingRecommendation(Tenant $tenant, User $buyer): array
    {
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Best overall value with lower delivery risk.',
                'tradeoffSummary' => 'Higher price than lowest bid; stronger implementation plan.',
                'riskSummary' => 'No unresolved normalization issues.',
                'exceptionSummary' => null,
                'evidenceReferences' => [],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk();

        $recommendation = RfqAwardRecommendation::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->firstOrFail();

        return [$rfq, $recommendation];
    }

    private function createAwardPolicy(Tenant $tenant, User $actor, User $approver): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Award recommendation approval',
            'description' => 'Commercial approval route for award recommendations.',
            'subject_type' => 'rfq_award_recommendation',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'rfq_award_recommendation',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => 100,
            'rules' => [['field' => 'recommendedAmount', 'operator' => 'gte', 'value' => 1]],
            'route_template' => [
                'stages' => [[
                    'name' => 'Commercial approval',
                    'completionRule' => 'all',
                    'approvers' => [
                        ['type' => 'user', 'userId' => (string) $approver->id, 'label' => $approver->name],
                    ],
                    'fallbackApprovers' => [
                        ['type' => 'role', 'role' => 'approver', 'label' => 'Approver fallback'],
                    ],
                ]],
            ],
            'sla_rules' => [['stage' => 'Commercial approval', 'dueInHours' => 48]],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
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

    private function rfqWithApprovedQuotation(Tenant $tenant, User $buyer): Rfq
    {
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-2026-POH',
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

        $this->createQuotationForRfq($tenant, $buyer, $rfq, 'Northwind Traders', 'MYR', '131100.00', 'per_line');
        $this->approveQuotationForComparison($tenant, $buyer, $rfq, 'MYR', '131100.00', 'per_line');

        return $rfq;
    }

    private function createQuotationForRfq(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $vendorName,
        string $currency,
        string $total,
        string $pricingMode,
    ): Quotation {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $vendorName,
            'status' => 'active',
        ]);

        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($vendorName).'@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'manual_entry_complete' => true,
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $version->lineItems()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Pallet rack bay',
            'quantity' => '10.0000',
            'unit' => 'set',
            'unit_price' => $pricingMode === 'bundle' ? null : '13110.0000',
            'total_amount' => $pricingMode === 'bundle' ? null : $total,
            'position' => 1,
        ]);

        return $quotation;
    }

    private function approveQuotationForComparison(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $currency,
        string $total,
        string $pricingMode,
    ): void {
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => QuotationNormalizationStatus::Approved->value,
            'is_current_for_version' => true,
            'approved_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'algorithm_version' => 'deterministic-v1',
        ]);

        $normalization->fields()->createMany([
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.currency',
                'normalized_value' => $currency,
                'data_type' => 'currency',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.totalAmount',
                'normalized_value' => $total,
                'data_type' => 'money',
                'currency' => $currency,
                'source' => 'manual_entry',
            ],
        ]);

        $lineGroup = $normalization->lineGroups()->create([
            'tenant_id' => $tenant->id,
            'group_number' => 1,
            'pricing_mode' => $pricingMode,
            'description' => 'Pallet rack bay',
            'currency' => $currency,
            'bundle_total_amount' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value ? $total : null,
        ]);

        $lineGroup->mappings()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'mapping_type' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value
                ? QuotationNormalizationMappingType::Bundled->value
                : QuotationNormalizationMappingType::Full->value,
            'quantity' => '10',
            'unit' => 'set',
            'line_total' => $pricingMode === QuotationNormalizationPricingMode::Bundle->value ? null : $total,
        ]);
    }

    private function seedPurchaseOrderRequestHandoff(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        RfqAwardRecommendation $recommendation,
        Quotation $quotation,
        QuotationVersion $version,
        array $attributes = [],
    ): string {
        $handoffId = DB::table('purchase_order_request_handoffs')
            ->where('tenant_id', $tenant->id)
            ->where('rfq_award_recommendation_id', $recommendation->id)
            ->value('id') ?? (string) Str::uuid();
        $now = now();
        $defaults = [
            'id' => $handoffId,
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'approval_instance_id' => null,
            'rfq_id' => $rfq->id,
            'requisition_id' => null,
            'project_id' => null,
            'vendor_id' => $quotation->vendor_id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'POH-2026-000001',
            'status' => 'draft',
            'currency' => 'MYR',
            'subtotal_amount' => '131100.00',
            'tax_amount' => null,
            'freight_amount' => null,
            'discount_amount' => null,
            'total_amount' => '131100.00',
            'requested_po_date' => null,
            'delivery_attention' => null,
            'finance_note' => null,
            'export_memo' => null,
            'requested_by_user_id' => $buyer->id,
            'ready_by_user_id' => null,
            'ready_at' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'cancelled_reason' => null,
            'last_exported_by_user_id' => null,
            'last_exported_at' => null,
            'last_export_format' => null,
            'source_snapshot' => json_encode([
                'rfq' => ['number' => $rfq->number],
                'vendor' => ['name' => 'Northwind Traders'],
                'quotation' => ['number' => $quotation->number],
            ]),
            'line_snapshot' => json_encode([
                [
                    'lineNumber' => 1,
                    'itemCode' => null,
                    'description' => 'Pallet rack bay',
                    'quantity' => '10.0000',
                    'unitOfMeasure' => 'set',
                    'unitPrice' => '13110.0000',
                    'taxAmount' => null,
                    'freightAmount' => null,
                    'discountAmount' => null,
                    'lineTotal' => '131100.00',
                    'currency' => 'MYR',
                    'notes' => null,
                ],
            ]),
            'approval_snapshot' => json_encode([
                'finalDecision' => 'approved',
                'approvalInstanceId' => null,
                'stages' => [
                    [
                        'stage' => 'Commercial approval',
                        'actor' => $buyer->name,
                    ],
                ],
            ]),
            'evidence_snapshot' => json_encode([
                [
                    'type' => 'comparison_note',
                    'summary' => 'Pallet rack bay selected for commercial and delivery fit.',
                ],
            ]),
            'readiness_warnings' => json_encode([]),
            'lock_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('purchase_order_request_handoffs')->updateOrInsert(
            ['id' => $handoffId],
            array_merge($defaults, $attributes),
        );

        return $handoffId;
    }
}

final class PurchaseOrderReference
{
    public function __construct(
        public string $id,
        public Tenant $tenant,
        public int $lock_version,
    ) {}

    public function fresh(): self
    {
        if (Schema::hasTable('purchase_orders')) {
            $row = DB::table('purchase_orders')->where('id', $this->id)->first();

            if ($row !== null) {
                $this->lock_version = (int) $row->lock_version;
            }
        }

        return $this;
    }
}

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
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationComparisonNote;
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
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderRequestHandoffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_award_recommendation_auto_creates_draft_po_handoff(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);

        $task = ApprovalTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', RfqAwardRecommendation::class)
            ->where('subject_id', $recommendation->id)
            ->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk()
            ->assertJsonPath('data.subject.type', 'rfq_award_recommendation');

        $this->assertDatabaseHas('purchase_order_request_handoffs', [
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $recommendation->recommended_vendor_id,
            'quotation_id' => $recommendation->recommended_quotation_id,
            'quotation_version_id' => $recommendation->recommended_quotation_version_id,
            'status' => 'draft',
            'currency' => 'MYR',
            'total_amount' => '131100.00',
        ]);

        $handoff = PurchaseOrderRequestHandoff::query()->firstOrFail();

        $this->assertSame('POH-', substr($handoff->number, 0, 4));
        $this->assertSame('RFQ-2026-POH', data_get($handoff->source_snapshot, 'rfq.number'));
        $this->assertSame('Northwind Traders', data_get($handoff->source_snapshot, 'vendor.name'));
        $this->assertSame('Pallet rack bay', data_get($handoff->line_snapshot, '0.description'));
    }

    public function test_award_approval_callback_is_idempotent_for_po_handoff_creation(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->routedRecommendation($tenant, $buyer, $approver);

        $task = ApprovalTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', RfqAwardRecommendation::class)
            ->where('subject_id', $recommendation->id)
            ->firstOrFail();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->lock_version])
            ->assertOk();

        $this->actingAsTenant($tenant, $approver)
            ->postJson("/api/approval-tasks/{$task->id}/approve", ['lockVersion' => $task->refresh()->lock_version])
            ->assertConflict();

        $this->assertDatabaseCount('purchase_order_request_handoffs', 1);
    }

    public function test_buyer_can_show_existing_handoff_for_already_approved_recommendation(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq] = $this->approvedRecommendation($tenant, $buyer, $approver);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source.rfq.number', 'RFQ-2026-POH')
            ->assertJsonPath('data.lines.0.description', 'Pallet rack bay')
            ->assertJsonPath('data.permissions.canMarkReady', true);
    }

    public function test_get_handoff_for_rfq_is_read_only_when_handoff_is_missing(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->approvedRecommendation($tenant, $buyer, $approver);

        PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_award_recommendation_id', $recommendation->id)
            ->delete();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('purchase_order_request_handoffs', [
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
        ]);
    }

    public function test_post_handoff_for_rfq_creates_or_reveals_for_already_approved_recommendation(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation] = $this->approvedRecommendation($tenant, $buyer, $approver);

        PurchaseOrderRequestHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_award_recommendation_id', $recommendation->id)
            ->delete();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.source.rfq.number', 'RFQ-2026-POH');

        $this->assertDatabaseHas('purchase_order_request_handoffs', [
            'tenant_id' => $tenant->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'status' => 'draft',
        ]);
    }

    public function test_non_approved_recommendations_cannot_create_handoffs(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$rfq] = $this->pendingRecommendation($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertConflict();
    }

    public function test_handoff_snapshot_contains_source_line_approval_and_evidence_details(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertOk()
            ->assertJsonPath('data.source.rfq.number', 'RFQ-2026-POH')
            ->assertJsonPath('data.lines.0.description', 'Pallet rack bay')
            ->assertJsonPath('data.approval.stages.0.stage', 'Commercial approval')
            ->assertJsonPath('data.evidence.0.type', 'comparison_note');
    }

    public function test_handoff_response_includes_existing_purchase_order_reference(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertOk()
            ->assertJsonPath('data.purchaseOrderId', null);

        $purchaseOrder = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $handoffId,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $quotation->vendor_id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-2026-EXISTING',
            'status' => PurchaseOrderStatus::Draft,
            'currency' => 'MYR',
            'total_amount' => '131100.00',
            'source_snapshot' => [],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'lock_version' => 1,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertOk()
            ->assertJsonPath('data.purchaseOrderId', (string) $purchaseOrder->id);
    }

    public function test_buyer_can_update_optional_handoff_fields_with_lock_version(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/po-handoffs/{$handoffId}", [
                'lockVersion' => 1,
                'requestedPoDate' => '2026-06-15',
                'deliveryAttention' => 'Warehouse receiving',
                'financeNote' => 'Charge to expansion budget.',
                'exportMemo' => 'Upload to ERP batch MY-0626.',
            ])
            ->assertOk()
            ->assertJsonPath('data.review.requestedPoDate', '2026-06-15')
            ->assertJsonPath('data.review.deliveryAttention', 'Warehouse receiving')
            ->assertJsonPath('data.review.financeNote', 'Charge to expansion budget.')
            ->assertJsonPath('data.review.exportMemo', 'Upload to ERP batch MY-0626.');
    }

    public function test_update_preserves_omitted_optional_handoff_fields(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'requested_po_date' => '2026-06-15',
            'delivery_attention' => 'Warehouse receiving',
            'finance_note' => 'Charge to expansion budget.',
            'export_memo' => 'Upload to ERP batch MY-0626.',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/po-handoffs/{$handoffId}", [
                'lockVersion' => 1,
                'requestedPoDate' => '2026-06-20',
            ])
            ->assertOk()
            ->assertJsonPath('data.review.requestedPoDate', '2026-06-20')
            ->assertJsonPath('data.review.deliveryAttention', 'Warehouse receiving')
            ->assertJsonPath('data.review.financeNote', 'Charge to expansion budget.')
            ->assertJsonPath('data.review.exportMemo', 'Upload to ERP batch MY-0626.');
    }

    public function test_ready_action_validates_blockers_and_records_ready_actor(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'readiness_warnings' => json_encode(['Finance note missing.']),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/ready", [
                'lockVersion' => 1,
            ])
            ->assertConflict();

        $this->updateSeededHandoff($handoffId, [
            'readiness_warnings' => json_encode([]),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/ready", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.readyByUserId', (string) $buyer->id)
            ->assertJsonPath('data.permissions.canMarkReady', false);
    }

    public function test_json_export_get_returns_structured_payload_without_mutating_handoff_state(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'status' => 'ready',
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertOk()
            ->assertJsonPath('format', 'json')
            ->assertJsonPath('handoff.number', 'POH-2026-000001')
            ->assertJsonPath('handoff.lines.0.description', 'Pallet rack bay');

        $this->assertDatabaseHas('purchase_order_request_handoffs', [
            'id' => $handoffId,
            'status' => 'ready',
            'last_exported_by_user_id' => null,
            'last_exported_at' => null,
            'last_export_format' => null,
            'lock_version' => 1,
        ]);
    }

    public function test_json_export_post_records_export_state_change(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'status' => 'ready',
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertOk()
            ->assertJsonPath('format', 'json')
            ->assertJsonPath('handoff.number', 'POH-2026-000001');

        $this->assertDatabaseHas('purchase_order_request_handoffs', [
            'id' => $handoffId,
            'status' => 'exported',
            'last_exported_by_user_id' => $buyer->id,
            'last_export_format' => 'json',
            'lock_version' => 2,
        ]);
    }

    public function test_csv_export_returns_expected_headers_and_line_rows(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'status' => 'ready',
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->get("/api/po-handoffs/{$handoffId}/export.csv")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('handoff_number,')
            ->assertSee('Pallet rack bay');

        $this->assertDatabaseHas('purchase_order_request_handoffs', [
            'id' => $handoffId,
            'status' => 'ready',
            'last_exported_by_user_id' => null,
            'last_exported_at' => null,
            'last_export_format' => null,
            'lock_version' => 1,
        ]);
    }

    public function test_repeat_export_records_audit_without_duplicate_handoff(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'status' => 'ready',
            'ready_by_user_id' => $buyer->id,
            'ready_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertOk();

        $this->assertDatabaseCount('purchase_order_request_handoffs', 1);
    }

    public function test_cancelled_handoff_cannot_be_exported(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version, [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by_user_id' => $buyer->id,
            'cancelled_reason' => 'Vendor award replaced by corrected recommendation.',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertConflict();
    }

    public function test_cross_tenant_view_update_ready_export_and_cancel_fail(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->patchJson("/api/po-handoffs/{$handoffId}", [
                'lockVersion' => 1,
                'requestedPoDate' => '2026-06-15',
            ])
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/po-handoffs/{$handoffId}/ready", [
                'lockVersion' => 1,
            ])
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->get("/api/po-handoffs/{$handoffId}/export.csv")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/po-handoffs/{$handoffId}/cancel", [
                'lockVersion' => 1,
                'reason' => 'Cross-tenant access attempt.',
            ])
            ->assertForbidden();
    }

    public function test_requester_and_vendor_like_users_cannot_access_handoff_endpoints(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        [, $requester] = $this->tenantUser('requester', $tenant);
        $vendorLike = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertForbidden();

        Sanctum::actingAs($vendorLike);
        app(CurrentTenant::class)->set($tenant);

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/po-handoffs/{$handoffId}")
            ->assertForbidden();
    }

    public function test_handoff_routes_require_real_session_auth_and_tenant_context(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$rfq, $recommendation, $quotation, $version] = $this->approvedRecommendation($tenant, $buyer, $approver);
        $handoffId = $this->seedPurchaseOrderRequestHandoff($tenant, $buyer, $rfq, $recommendation, $quotation, $version);

        $buyer->forceFill([
            'email' => 'purchase-order-handoff-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'purchase-order-handoff-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/po-handoffs/{$handoffId}/ready", [
                'lockVersion' => 1,
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->postJson("/api/po-handoffs/{$handoffId}/export.json")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
            ->assertUnauthorized();
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

    private function tenantUser(string $role, ?Tenant $tenant = null): array
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

    private function saveDraftRecommendation(Tenant $tenant, User $buyer, Rfq $rfq, Quotation $quotation, QuotationVersion $version): void
    {
        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Draft recommendation for selected quotation.',
                'tradeoffSummary' => 'Primary tradeoff summary for recommendation.',
                'riskSummary' => 'No critical blockers identified.',
                'exceptionSummary' => null,
                'evidenceReferences' => [],
            ])
            ->assertOk();
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

    private function updateSeededHandoff(string $handoffId, array $attributes): void
    {
        DB::table('purchase_order_request_handoffs')->where('id', $handoffId)->update($attributes);
    }
}

<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqAwardRecommendationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_load_award_recommendation_context(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertOk()
            ->assertJsonPath('data.rfq.id', (string) $rfq->id)
            ->assertJsonPath('data.recommendation', null)
            ->assertJsonPath('data.vendorOptions.0.vendorName', 'Acme Supply')
            ->assertJsonPath('data.permissions.canManageAwardRecommendation', true)
            ->assertJsonPath('data.readiness.comparisonStatus', 'ready');
    }

    public function test_buyer_can_save_and_update_draft_recommendation_with_evidence_references(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();
        $evidence = $this->comparisonNote($tenant, $buyer, $rfq, $quotation, 'Evidence note for award rationale.');

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Preferred commercial terms and delivery certainty.',
                'tradeoffSummary' => 'Slight lead-time premium for lower total cost.',
                'riskSummary' => 'Manageable onboarding risk with mitigation owner.',
                'exceptionSummary' => null,
                'evidenceReferences' => [[
                    'type' => 'comparison_note',
                    'id' => (string) $evidence->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'draft');

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Updated draft recommendation summary.',
                'tradeoffSummary' => 'Updated tradeoff context.',
                'riskSummary' => 'Updated mitigation details.',
                'exceptionSummary' => null,
                'evidenceReferences' => [[
                    'type' => 'comparison_note',
                    'id' => (string) $evidence->id,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'draft')
            ->assertJsonPath('data.recommendation.rationale', 'Updated draft recommendation summary.');
    }

    public function test_buyer_can_submit_recommendation_to_pending_approval(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'pending_approval');
    }

    public function test_pending_approval_recommendation_is_read_only_except_withdrawal(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Should be rejected while pending.',
                'tradeoffSummary' => 'Pending approval lock check.',
                'riskSummary' => 'Pending lock should prevent edits.',
                'exceptionSummary' => null,
                'evidenceReferences' => [],
            ])
            ->assertConflict();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/withdraw", [
                'reason' => 'Additional pricing clarification required.',
            ])
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'withdrawn');
    }

    public function test_approval_routed_and_decided_recommendations_are_read_only_for_draft_save(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        foreach (['approval_routed', 'approved', 'rejected', 'changes_requested'] as $status) {
            RfqAwardRecommendation::query()->where('tenant_id', $tenant->id)->where('rfq_id', $rfq->id)->delete();
            $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);

            RfqAwardRecommendation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->update(['status' => $status]);

            $this->actingAsTenant($tenant, $buyer)
                ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                    'recommendedVendorId' => (string) $quotation->vendor_id,
                    'recommendedQuotationId' => (string) $quotation->id,
                    'recommendedQuotationVersionId' => (string) $version->id,
                    'scorecardId' => null,
                    'rationale' => "Should be rejected while {$status}.",
                    'tradeoffSummary' => 'Read-only approval state check.',
                    'riskSummary' => 'Approval state should prevent edits.',
                    'exceptionSummary' => null,
                    'evidenceReferences' => [],
                ])
                ->assertConflict();
        }
    }

    public function test_withdraw_requires_reason_and_records_withdrawal_metadata(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/withdraw", [])
            ->assertUnprocessable();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/withdraw", [
                'reason' => 'Correction to recommendation package required.',
            ])
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'withdrawn')
            ->assertJsonPath('data.recommendation.withdrawalReason', 'Correction to recommendation package required.')
            ->assertJsonPath('data.recommendation.withdrawnByUserId', (string) $buyer->id)
            ->assertJsonStructure([
                'data' => [
                    'recommendation' => [
                        'withdrawnAt',
                    ],
                ],
            ]);
    }

    public function test_submit_rejects_stale_quotation_version(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);
        $this->newCurrentVersion($tenant, $quotation, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertConflict();
    }

    public function test_submit_rejects_incomplete_scorecard_when_scorecard_exists(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->createScorecard($tenant, $admin, $buyer, $rfq, false);
        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertConflict();
    }

    public function test_submit_can_proceed_without_scorecard_when_comparison_is_ready(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk()
            ->assertJsonPath('data.recommendation.status', 'pending_approval');
    }

    public function test_evidence_references_must_belong_to_same_rfq_and_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $otherRfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Other RFQ Vendor');
        $foreignRfq = $this->rfqWithApprovedQuotation($otherTenant, $otherBuyer, 'Foreign RFQ Vendor');

        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();
        $otherQuotation = Quotation::query()->where('rfq_id', $otherRfq->id)->firstOrFail();
        $foreignQuotation = Quotation::query()->where('rfq_id', $foreignRfq->id)->firstOrFail();
        $otherEvidence = $this->comparisonNote($tenant, $buyer, $otherRfq, $otherQuotation, 'Other RFQ evidence');
        $foreignEvidence = $this->comparisonNote($otherTenant, $otherBuyer, $foreignRfq, $foreignQuotation, 'Foreign evidence');

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Should reject cross-RFQ evidence.',
                'tradeoffSummary' => 'Cross-RFQ validation test.',
                'riskSummary' => 'Cross-reference validation test.',
                'exceptionSummary' => null,
                'evidenceReferences' => [[
                    'type' => 'comparison_note',
                    'id' => (string) $otherEvidence->id,
                ]],
            ])
            ->assertUnprocessable();

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Should reject cross-tenant evidence.',
                'tradeoffSummary' => 'Cross-tenant validation test.',
                'riskSummary' => 'Cross-tenant validation test.',
                'exceptionSummary' => null,
                'evidenceReferences' => [[
                    'type' => 'comparison_note',
                    'id' => (string) $foreignEvidence->id,
                ]],
            ])
            ->assertUnprocessable();
    }

    public function test_requester_approver_vendor_and_cross_tenant_users_cannot_access_award_recommendation(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        $vendorUser = User::factory()->create();
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $vendorUser)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertNotFound();
    }

    public function test_award_recommendation_actions_record_audit_events(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->saveDraftRecommendation($tenant, $buyer, $rfq, $quotation, $version);
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/submit")
            ->assertOk();
        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/award-recommendation/withdraw", [
                'reason' => 'Revision required after internal challenge.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'rfq_award_recommendation.saved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'rfq_award_recommendation.submitted',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'rfq_award_recommendation.withdrawn',
        ]);
    }

    public function test_award_recommendation_routes_require_real_session_auth_and_tenant_context(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $secondTenant = Tenant::query()->create(['name' => 'Second tenant '.Str::uuid()]);
        $secondTenant->users()->attach($buyer->id, ['role' => TenantRole::Buyer->value]);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $buyer->forceFill([
            'email' => 'award-recommendation-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'award-recommendation-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withoutHeader('X-Tenant-Id')
            ->withHeader('Origin', 'http://localhost:8880')
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertStatus(400);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) Str::uuid())
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertForbidden();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->putJson("/api/rfqs/{$rfq->id}/award-recommendation", [
                'recommendedVendorId' => (string) $quotation->vendor_id,
                'recommendedQuotationId' => (string) $quotation->id,
                'recommendedQuotationVersionId' => (string) $version->id,
                'scorecardId' => null,
                'rationale' => 'Session-authenticated recommendation save.',
                'tradeoffSummary' => 'Session middleware verification.',
                'riskSummary' => 'No blocking risk.',
                'exceptionSummary' => null,
                'evidenceReferences' => [],
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/award-recommendation")
            ->assertUnauthorized();
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

    private function rfqWithApprovedQuotation(
        Tenant $tenant,
        User $buyer,
        string $vendorName = 'Acme Supply',
        string $currency = 'USD',
        string $total = '12500.00',
        string $pricingMode = 'per_line',
    ): Rfq {
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => 'Laptop refresh',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Purchase laptops',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Laptop',
                'description' => 'Business laptop',
                'quantity' => '10',
                'unit_of_measure' => 'each',
                'currency' => $currency,
            ]],
        ]);

        $this->createQuotationForRfq($tenant, $buyer, $rfq, $vendorName, $currency, $total, $pricingMode);
        $this->approveQuotationForComparison($tenant, $buyer, $rfq, $currency, $total, $pricingMode);

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
            'description' => 'Laptop',
            'quantity' => '10.0000',
            'unit' => 'each',
            'unit_price' => $pricingMode === 'bundle' ? null : number_format(((float) $total) / 10, 4, '.', ''),
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
            'description' => 'Laptop',
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
            'unit' => 'each',
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

    private function createScorecard(Tenant $tenant, User $admin, User $buyer, Rfq $rfq, bool $complete): void
    {
        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Award Decision Scorecard',
                'description' => 'Template for recommendation submission checks.',
                'criteria' => [[
                    'category' => 'cost',
                    'label' => 'Commercial competitiveness',
                    'guidance' => 'Score commercial offer quality.',
                    'weight' => 100,
                    'maxScore' => 10,
                    'required' => true,
                    'displayOrder' => 1,
                ]],
            ])
            ->assertOk()
            ->json('data.template.id');

        $scorecardResponse = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        if (! $complete) {
            return;
        }

        $criterionId = $scorecardResponse->json('data.criteria.0.id');
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [[
                    'criterionId' => $criterionId,
                    'vendorId' => (string) $quotation->vendor_id,
                    'quotationId' => (string) $quotation->id,
                    'quotationVersionId' => (string) $version->id,
                    'score' => 8,
                    'note' => 'Completed score for recommendation readiness.',
                ]],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard/complete")
            ->assertOk();
    }

    private function newCurrentVersion(Tenant $tenant, Quotation $quotation, User $buyer): QuotationVersion
    {
        QuotationVersion::query()
            ->where('quotation_id', $quotation->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);

        $next = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 2,
            'is_current' => true,
            'submission_source' => 'buyer_upload',
            'status' => QuotationStatus::submitted->value,
            'currency' => $quotation->currency,
            'total_amount' => $quotation->total_amount,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $next->id])->save();

        return $next;
    }

    private function comparisonNote(Tenant $tenant, User $buyer, Rfq $rfq, Quotation $quotation, string $note): QuotationComparisonNote
    {
        return QuotationComparisonNote::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $quotation->vendor_id,
            'section' => 'overall',
            'note' => $note,
            'created_by_user_id' => $buyer->id,
            'updated_by_user_id' => $buyer->id,
        ]);
    }
}

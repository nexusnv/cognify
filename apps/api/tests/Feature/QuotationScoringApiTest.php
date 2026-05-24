<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
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

class QuotationScoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_list_and_deactivate_scoring_templates(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');

        $response = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 50,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty',
                        'guidance' => 'Score the confidence in delivery timing.',
                        'weight' => 25,
                        'maxScore' => 10,
                        'required' => false,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.template.name', 'Balanced RFQ Evaluation')
            ->assertJsonPath('data.criteria.0.category', 'cost')
            ->assertJsonPath('data.criteria.0.weight', '50.00')
            ->assertJsonPath('data.criteria.0.maxScore', 10)
            ->assertJsonPath('data.criteria.0.required', true);

        $templateId = (string) $response->json('data.template.id');

        $this->actingAsTenant($tenant, $admin)
            ->patchJson("/api/quotation-scoring/templates/{$templateId}", [
                'name' => 'Balanced RFQ Evaluation - Updated',
                'description' => 'Updated template copy.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial discipline',
                        'guidance' => 'Updated commercial guidance.',
                        'weight' => 60,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Lead time certainty',
                        'guidance' => 'Updated delivery guidance.',
                        'weight' => 40,
                        'maxScore' => 10,
                        'required' => false,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.template.name', 'Balanced RFQ Evaluation - Updated')
            ->assertJsonPath('data.criteria.0.label', 'Commercial discipline')
            ->assertJsonPath('data.criteria.0.weight', '60.00');

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/quotation-scoring/templates')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Balanced RFQ Evaluation - Updated')
            ->assertJsonPath('data.0.active', true);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/quotation-scoring/templates/{$templateId}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.template.active', false);

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/quotation-scoring/templates')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Balanced RFQ Evaluation - Updated')
            ->assertJsonPath('data.0.active', false);
    }

    public function test_buyer_can_apply_active_template_to_rfq_and_snapshot_criteria(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 50,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty',
                        'guidance' => 'Score the confidence in delivery timing.',
                        'weight' => 50,
                        'maxScore' => 10,
                        'required' => false,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk()
            ->assertJsonPath('data.scorecard.templateName', 'Balanced RFQ Evaluation')
            ->assertJsonPath('data.criteria.0.category', 'cost')
            ->assertJsonPath('data.criteria.0.weight', '50.00')
            ->assertJsonPath('data.criteria.0.maxScore', 10)
            ->assertJsonPath('data.criteria.0.required', true);
    }

    public function test_template_edits_do_not_change_existing_rfq_scorecard_criteria(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 50,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty',
                        'guidance' => 'Score the confidence in delivery timing.',
                        'weight' => 50,
                        'maxScore' => 10,
                        'required' => false,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $admin)
            ->patchJson("/api/quotation-scoring/templates/{$templateId}", [
                'name' => 'Balanced RFQ Evaluation v2',
                'description' => 'Template updated after scorecard application.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness revised',
                        'guidance' => 'Updated guidance.',
                        'weight' => 60,
                        'maxScore' => 12,
                        'required' => false,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty revised',
                        'guidance' => 'Updated guidance.',
                        'weight' => 40,
                        'maxScore' => 12,
                        'required' => true,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.criteria.0.label', 'Commercial competitiveness')
            ->assertJsonPath('data.criteria.0.weight', '50.00')
            ->assertJsonPath('data.criteria.0.maxScore', 10)
            ->assertJsonPath('data.criteria.0.required', true);
    }

    public function test_buyer_can_update_scores_and_notes_and_receives_weighted_totals(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotations($tenant, $buyer);

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty',
                        'guidance' => 'Score the confidence in delivery timing.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        $vendors = Vendor::query()
            ->where('tenant_id', $tenant->id)
            ->whereHas('quotations', fn ($query) => $query->where('rfq_id', $rfq->id))
            ->orderBy('id')
            ->get();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $vendors[0]->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[0]->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[0]->id))->firstOrFail()->id,
                        'score' => 9,
                        'note' => 'Strong commercial position.',
                    ],
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $vendors[0]->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[0]->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[0]->id))->firstOrFail()->id,
                        'score' => 8,
                        'note' => 'Delivery remains acceptable.',
                    ],
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $vendors[1]->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[1]->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[1]->id))->firstOrFail()->id,
                        'score' => 6,
                        'note' => 'Needs improvement.',
                    ],
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $vendors[1]->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[1]->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('vendor_id', $vendors[1]->id))->firstOrFail()->id,
                        'score' => 7,
                        'note' => 'Delivery is acceptable.',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertOk()
            ->assertJsonPath('data.vendors.0.weightedTotal', '8.50')
            ->assertJsonPath('data.vendors.0.missingRequiredCount', 0)
            ->assertJsonPath('data.completion.status', 'complete');
    }

    public function test_score_validation_rejects_values_outside_criterion_max_score(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $vendor = Vendor::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $vendor->id,
                        'quotationId' => (string) $quotation->id,
                        'quotationVersionId' => (string) $version->id,
                        'score' => 11,
                        'note' => 'Score above max should fail.',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.fields.entries.0.score.0', 'The score may not be greater than 10.');
    }

    public function test_scorecard_completion_requires_required_scores_and_reopen_allows_edits(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                    [
                        'category' => 'delivery',
                        'label' => 'Delivery certainty',
                        'guidance' => 'Score the confidence in delivery timing.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard/complete")
            ->assertUnprocessable();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) Vendor::query()->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id))->firstOrFail()->id,
                        'score' => 9,
                        'note' => 'Required score entered.',
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard/complete")
            ->assertOk()
            ->assertJsonPath('data.scorecard.status', 'completed');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) Vendor::query()->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id))->firstOrFail()->id,
                        'score' => 10,
                        'note' => 'Completed scorecard should be read only.',
                    ],
                ],
            ])
            ->assertConflict();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard/reopen")
            ->assertOk()
            ->assertJsonPath('data.scorecard.status', 'in_progress');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) Vendor::query()->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationId' => (string) Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail()->id,
                        'quotationVersionId' => (string) QuotationVersion::query()->whereHas('quotation', fn ($query) => $query->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id))->firstOrFail()->id,
                        'score' => 8,
                        'note' => 'Edits should be allowed after reopen.',
                    ],
                ],
            ])
            ->assertOk();
    }

    public function test_requester_approver_vendor_and_cross_tenant_users_cannot_access_scoring(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertForbidden();

        $this->actingAsTenant($tenant, $requester)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Should not be allowed',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertNotFound();
    }

    public function test_scoring_actions_do_not_change_rfq_quotation_normalization_or_invitation_state(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $normalization = QuotationNormalization::query()->where('quotation_id', $quotation->id)->firstOrFail();
        $invitation = RfqInvitation::query()->where('rfq_id', $rfq->id)->where('tenant_id', $tenant->id)->firstOrFail();

        $before = [
            'rfq_status' => $rfq->status,
            'quotation_status' => $quotation->status,
            'version_status' => $version->status,
            'normalization_status' => $normalization->status,
            'invitation_status' => $invitation->status,
            'quotation_count' => Quotation::query()->where('rfq_id', $rfq->id)->count(),
            'normalization_count' => QuotationNormalization::query()->where('quotation_id', $quotation->id)->count(),
        ];

        $templateId = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/quotation-scoring/templates', [
                'name' => 'Balanced RFQ Evaluation',
                'description' => 'Baseline scoring criteria for quote evaluation.',
                'criteria' => [
                    [
                        'category' => 'cost',
                        'label' => 'Commercial competitiveness',
                        'guidance' => 'Score the quote against the commercial offer.',
                        'weight' => 5,
                        'maxScore' => 10,
                        'required' => true,
                        'displayOrder' => 1,
                    ],
                ],
            ])
            ->assertOk()
            ->json('data.template.id');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/scorecard", [
                'templateId' => $templateId,
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $quotation->vendor_id,
                        'quotationId' => (string) $quotation->id,
                        'quotationVersionId' => (string) $version->id,
                        'score' => 8,
                        'note' => 'Scoring should not mutate comparison state.',
                    ],
                ],
            ])
            ->assertOk();

        $this->assertSame($before['rfq_status'], $rfq->refresh()->status);
        $this->assertSame($before['quotation_status'], $quotation->refresh()->status);
        $this->assertSame($before['version_status'], $version->refresh()->status);
        $this->assertSame($before['normalization_status'], $normalization->refresh()->status);
        $this->assertSame($before['invitation_status'], $invitation->refresh()->status);
        $this->assertSame($before['quotation_count'], Quotation::query()->where('rfq_id', $rfq->id)->count());
        $this->assertSame($before['normalization_count'], QuotationNormalization::query()->where('quotation_id', $quotation->id)->count());
    }

    public function test_scoring_routes_require_real_session_auth_and_tenant_context(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $buyer->forceFill([
            'email' => 'quotation-scoring-session@example.com',
            'password' => Hash::make('secret123'),
        ])->save();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'quotation-scoring-session@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson("/api/rfqs/{$rfq->id}/scorecard/scores", [
                'entries' => [
                    [
                        'criterionId' => (string) Str::uuid(),
                        'vendorId' => (string) $rfq->quotations()->firstOrFail()->vendor_id,
                        'quotationId' => (string) $rfq->quotations()->firstOrFail()->id,
                        'quotationVersionId' => (string) $rfq->quotations()->firstOrFail()->currentVersion->id,
                        'score' => 8,
                        'note' => 'Session-authenticated scoring update.',
                    ],
                ],
            ])
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/rfqs/{$rfq->id}/scorecard")
            ->assertUnauthorized();
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function rfqWithApprovedQuotations(Tenant $tenant, User $buyer): Rfq
    {
        $rfq = $this->rfqWithQuotation($tenant, $buyer, 'Balanced Vendor One', 'USD', '12500.00', true);
        $this->addApprovedQuotation($tenant, $buyer, $rfq, 'Balanced Vendor Two', 'USD', '11750.00', 'per_line');

        return $rfq;
    }

    private function rfqWithApprovedQuotation(
        Tenant $tenant,
        User $buyer,
        string $vendorName = 'Acme Supply',
        string $currency = 'USD',
        string $total = '12500.00',
        string $pricingMode = 'per_line',
    ): Rfq {
        return $this->rfqWithQuotation($tenant, $buyer, $vendorName, $currency, $total, true, $pricingMode);
    }

    private function rfqWithQuotation(
        Tenant $tenant,
        User $buyer,
        string $vendorName,
        string $currency,
        string $total,
        bool $approved,
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

        if (! $approved) {
            return $rfq;
        }

        $this->approveQuotationForComparison($tenant, $buyer, $rfq, $currency, $total, $pricingMode);

        return $rfq;
    }

    private function addApprovedQuotation(
        Tenant $tenant,
        User $buyer,
        Rfq $rfq,
        string $vendorName,
        string $currency,
        string $total,
        string $pricingMode,
    ): void {
        $this->createQuotationForRfq($tenant, $buyer, $rfq, $vendorName, $currency, $total, $pricingMode);
        $this->approveQuotationForComparison($tenant, $buyer, $rfq, $currency, $total, $pricingMode);
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
            'source' => 'buyer_manual_entry',
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
            'unit_price' => $pricingMode === 'bundle' ? null : $total,
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
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.leadTimeDays',
                'normalized_value' => '14',
                'data_type' => 'integer',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.paymentTerms',
                'normalized_value' => 'Net 30',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.deliveryTerms',
                'normalized_value' => 'DAP',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.warrantyTerms',
                'normalized_value' => '12 months',
                'data_type' => 'text',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.complianceNotes',
                'normalized_value' => 'Compliant',
                'data_type' => 'text',
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
}

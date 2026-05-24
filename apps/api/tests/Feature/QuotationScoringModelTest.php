<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\UpdateQuotationScoringTemplate;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationScoringTemplateCriterion;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\RfqScorecardEntry;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class QuotationScoringModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoring_models_reject_cross_tenant_parent_graphs(): void
    {
        $first = $this->scoringGraph('First');
        $second = $this->scoringGraph('Second');

        $this->assertInvalidArgument(
            fn () => QuotationScoringTemplateCriterion::query()->create([
                'tenant_id' => $first['tenant']->id,
                'template_id' => $second['template']->id,
                'category' => QuotationScoringCriterionCategory::Cost->value,
                'label' => 'Cross tenant criterion',
                'weight' => '50.00',
                'max_score' => 10,
                'is_required' => true,
                'display_order' => 2,
            ]),
            'Scoring template criterion must belong to the same tenant as the template.',
        );

        $this->assertInvalidArgument(
            fn () => RfqScorecard::query()->create([
                'tenant_id' => $first['tenant']->id,
                'rfq_id' => $second['rfq']->id,
                'template_id' => $first['template']->id,
                'template_name' => 'Cross tenant RFQ',
                'status' => RfqScorecardStatus::InProgress->value,
                'applied_by_user_id' => $first['user']->id,
                'applied_at' => now(),
            ]),
            'RFQ scorecard must belong to the same tenant as the RFQ.',
        );

        $this->assertInvalidArgument(
            fn () => RfqScorecardCriterion::query()->create([
                'tenant_id' => $first['tenant']->id,
                'scorecard_id' => $first['scorecard']->id,
                'source_template_criterion_id' => $second['templateCriterion']->id,
                'category' => QuotationScoringCriterionCategory::Cost->value,
                'label' => 'Cross tenant snapshot',
                'weight' => '50.00',
                'max_score' => 10,
                'is_required' => true,
                'display_order' => 2,
            ]),
            'RFQ scorecard criterion source template criterion must belong to the same tenant.',
        );

        $this->assertInvalidArgument(
            fn () => RfqScorecardEntry::query()->create([
                'tenant_id' => $first['tenant']->id,
                'scorecard_id' => $first['scorecard']->id,
                'scorecard_criterion_id' => $first['scorecardCriterion']->id,
                'vendor_id' => $second['vendor']->id,
                'quotation_id' => $first['quotation']->id,
                'quotation_version_id' => $first['version']->id,
                'score' => '8.00',
                'scored_by_user_id' => $first['user']->id,
                'scored_at' => now(),
            ]),
            'RFQ scorecard entry vendor must belong to the scorecard RFQ and tenant.',
        );

        $this->assertInvalidArgument(
            fn () => RfqScorecardEntry::query()->create([
                'tenant_id' => $first['tenant']->id,
                'scorecard_id' => $first['scorecard']->id,
                'scorecard_criterion_id' => $first['scorecardCriterion']->id,
                'vendor_id' => $first['vendor']->id,
                'quotation_id' => $second['quotation']->id,
                'quotation_version_id' => $second['version']->id,
                'score' => '8.00',
                'scored_by_user_id' => $first['user']->id,
                'scored_at' => now(),
            ]),
            'RFQ scorecard entry quotation must belong to the same RFQ, vendor, and tenant.',
        );
    }

    public function test_one_scorecard_is_allowed_per_rfq(): void
    {
        $graph = $this->scoringGraph('Unique');

        $this->expectException(QueryException::class);

        RfqScorecard::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'rfq_id' => $graph['rfq']->id,
            'template_id' => $graph['template']->id,
            'template_name' => 'Duplicate scorecard',
            'status' => RfqScorecardStatus::InProgress->value,
            'applied_by_user_id' => $graph['user']->id,
            'applied_at' => now(),
        ]);
    }

    public function test_optional_scoring_user_references_null_on_delete(): void
    {
        $graph = $this->scoringGraph('Audit');
        $updater = User::factory()->create();
        $deactivator = User::factory()->create();
        $completer = User::factory()->create();
        $scorer = User::factory()->create();

        $graph['template']->forceFill([
            'updated_by_user_id' => $updater->id,
            'deactivated_by_user_id' => $deactivator->id,
            'deactivated_at' => now(),
        ])->save();

        $graph['scorecard']->forceFill([
            'completed_by_user_id' => $completer->id,
            'completed_at' => now(),
        ])->save();

        $entry = RfqScorecardEntry::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'scorecard_id' => $graph['scorecard']->id,
            'scorecard_criterion_id' => $graph['scorecardCriterion']->id,
            'vendor_id' => $graph['vendor']->id,
            'quotation_id' => $graph['quotation']->id,
            'quotation_version_id' => $graph['version']->id,
            'score' => '8.00',
            'note' => 'Audited score.',
            'scored_by_user_id' => $scorer->id,
            'scored_at' => now(),
        ]);

        $updater->delete();
        $deactivator->delete();
        $completer->delete();
        $scorer->delete();

        $this->assertNull($graph['template']->refresh()->updated_by_user_id);
        $this->assertNull($graph['template']->refresh()->deactivated_by_user_id);
        $this->assertNull($graph['scorecard']->refresh()->completed_by_user_id);
        $this->assertNull($entry->refresh()->scored_by_user_id);
    }

    public function test_template_updates_soft_delete_removed_criteria_without_mutating_existing_scorecard_sources(): void
    {
        $graph = $this->scoringGraph('Snapshot');
        app(CurrentTenant::class)->set($graph['tenant']);
        $removedCriterion = QuotationScoringTemplateCriterion::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'template_id' => $graph['template']->id,
            'category' => QuotationScoringCriterionCategory::Delivery->value,
            'label' => 'Delivery certainty',
            'weight' => '50.00',
            'max_score' => 10,
            'is_required' => true,
            'display_order' => 2,
        ]);
        $scorecardSource = RfqScorecardCriterion::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'scorecard_id' => $graph['scorecard']->id,
            'source_template_criterion_id' => $removedCriterion->id,
            'category' => $removedCriterion->category->value,
            'label' => $removedCriterion->label,
            'weight' => $removedCriterion->weight,
            'max_score' => $removedCriterion->max_score,
            'is_required' => $removedCriterion->is_required,
            'display_order' => $removedCriterion->display_order,
        ]);

        app(UpdateQuotationScoringTemplate::class)->handle($graph['tenant'], $graph['user'], $graph['template'], [
            'name' => 'Snapshot template updated',
            'description' => 'Removed delivery for future scorecards.',
            'criteria' => [[
                'category' => QuotationScoringCriterionCategory::Cost->value,
                'label' => 'Commercial competitiveness updated',
                'guidance' => 'Updated guidance.',
                'weight' => '100.00',
                'maxScore' => 10,
                'required' => true,
                'displayOrder' => 1,
            ]],
        ]);

        $this->assertSame($removedCriterion->id, $scorecardSource->refresh()->source_template_criterion_id);
        $this->assertCount(1, $graph['template']->refresh()->criteria);
        $this->assertNotNull(QuotationScoringTemplateCriterion::withTrashed()->find($removedCriterion->id)?->deleted_at);
    }

    private function assertInvalidArgument(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     user: User,
     *     rfq: Rfq,
     *     vendor: Vendor,
     *     quotation: Quotation,
     *     version: QuotationVersion,
     *     template: QuotationScoringTemplate,
     *     templateCriterion: QuotationScoringTemplateCriterion,
     *     scorecard: RfqScorecard,
     *     scorecardCriterion: RfqScorecardCriterion
     * }
     */
    private function scoringGraph(string $prefix): array
    {
        $tenant = Tenant::query()->create(['name' => "{$prefix} tenant"]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => TenantRole::Admin->value]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => "{$prefix} RFQ",
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
        ]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => "{$prefix} Vendor",
            'status' => 'active',
        ]);

        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($prefix).'@example.com',
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => 'USD',
            'total_amount' => '1000.00',
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'status' => QuotationStatus::submitted->value,
            'is_current' => true,
            'submitted_by_user_id' => $user->id,
            'submitted_at' => now(),
        ]);

        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $template = QuotationScoringTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => "{$prefix} template",
            'description' => 'Reusable scoring template.',
            'is_active' => true,
            'created_by_user_id' => $user->id,
        ]);

        $templateCriterion = QuotationScoringTemplateCriterion::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'category' => QuotationScoringCriterionCategory::Cost->value,
            'label' => 'Commercial competitiveness',
            'guidance' => 'Score commercial competitiveness.',
            'weight' => '50.00',
            'max_score' => 10,
            'is_required' => true,
            'display_order' => 1,
        ]);

        $scorecard = RfqScorecard::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'template_id' => $template->id,
            'template_name' => $template->name,
            'template_description' => $template->description,
            'status' => RfqScorecardStatus::InProgress->value,
            'applied_by_user_id' => $user->id,
            'applied_at' => now(),
        ]);

        $scorecardCriterion = RfqScorecardCriterion::query()->create([
            'tenant_id' => $tenant->id,
            'scorecard_id' => $scorecard->id,
            'source_template_criterion_id' => $templateCriterion->id,
            'category' => $templateCriterion->category->value,
            'label' => $templateCriterion->label,
            'guidance' => $templateCriterion->guidance,
            'weight' => $templateCriterion->weight,
            'max_score' => $templateCriterion->max_score,
            'is_required' => $templateCriterion->is_required,
            'display_order' => $templateCriterion->display_order,
        ]);

        return [
            'tenant' => $tenant,
            'user' => $user,
            'rfq' => $rfq,
            'vendor' => $vendor,
            'quotation' => $quotation,
            'version' => $version,
            'template' => $template,
            'templateCriterion' => $templateCriterion,
            'scorecard' => $scorecard,
            'scorecardCriterion' => $scorecardCriterion,
        ];
    }
}

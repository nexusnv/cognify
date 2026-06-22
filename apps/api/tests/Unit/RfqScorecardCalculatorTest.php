<?php

namespace Tests\Unit;

use App\Models\User;
use App\Tenancy\Tenant;
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
use Domains\Quotation\Support\RfqScorecardCalculator;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RfqScorecardCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_total_uses_score_divided_by_max_score_times_weight(): void
    {
        $calculator = app(RfqScorecardCalculator::class);

        $this->assertSame(40.0, $calculator->weightedContribution(8, 10, 50));
        $this->assertSame('40.00', $calculator->formattedWeightedContribution(8, 10, 50));
    }

    public function test_missing_scores_are_not_treated_as_zero(): void
    {
        $graph = $this->scoringGraph('Partial', 2);

        RfqScorecardEntry::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'scorecard_id' => $graph['scorecard']->id,
            'scorecard_criterion_id' => $graph['criteria'][0]->id,
            'vendor_id' => $graph['vendors'][0]->id,
            'quotation_id' => $graph['quotations'][0]->id,
            'quotation_version_id' => $graph['versions'][0]->id,
            'score' => '8.00',
            'note' => 'Only one score entered.',
            'scored_by_user_id' => $graph['user']->id,
            'scored_at' => now(),
        ]);

        $totals = app(RfqScorecardCalculator::class)->vendorTotals(
            $graph['scorecard']->fresh()->load('criteria', 'entries')
        );

        $this->assertCount(2, $totals);
        $this->assertSame('8.00', $totals[0]['rawTotal']);
        $this->assertSame('40.00', $totals[0]['weightedTotal']);
        $this->assertSame(0, $totals[0]['missingRequiredCount']);
        $this->assertSame('0.00', $totals[1]['rawTotal']);
        $this->assertSame('0.00', $totals[1]['weightedTotal']);
        $this->assertSame(1, $totals[1]['missingRequiredCount']);
    }

    public function test_completion_requires_required_scores_for_all_scoreable_vendors(): void
    {
        $graph = $this->scoringGraph('Completion', 2);

        RfqScorecardEntry::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'scorecard_id' => $graph['scorecard']->id,
            'scorecard_criterion_id' => $graph['criteria'][0]->id,
            'vendor_id' => $graph['vendors'][0]->id,
            'quotation_id' => $graph['quotations'][0]->id,
            'quotation_version_id' => $graph['versions'][0]->id,
            'score' => '8.00',
            'scored_by_user_id' => $graph['user']->id,
            'scored_at' => now(),
        ]);

        $summary = app(RfqScorecardCalculator::class)->completionSummary(
            $graph['scorecard']->fresh()->load('criteria', 'entries')
        );

        $this->assertSame('incomplete', $summary['status']);
        $this->assertSame(2, $summary['scoreableVendorCount']);
        $this->assertSame(2, $summary['requiredScoreCount']);
        $this->assertSame(1, $summary['completedRequiredScoreCount']);
        $this->assertSame(1, $summary['missingRequiredScoreCount']);

        RfqScorecardEntry::query()->create([
            'tenant_id' => $graph['tenant']->id,
            'scorecard_id' => $graph['scorecard']->id,
            'scorecard_criterion_id' => $graph['criteria'][0]->id,
            'vendor_id' => $graph['vendors'][1]->id,
            'quotation_id' => $graph['quotations'][1]->id,
            'quotation_version_id' => $graph['versions'][1]->id,
            'score' => '7.00',
            'scored_by_user_id' => $graph['user']->id,
            'scored_at' => now(),
        ]);

        $completeSummary = app(RfqScorecardCalculator::class)->completionSummary(
            $graph['scorecard']->fresh()->load('criteria', 'entries')
        );

        $this->assertSame('complete', $completeSummary['status']);
        $this->assertSame(0, $completeSummary['missingRequiredScoreCount']);
    }

    /**
     * @return array{
     *     tenant: Tenant,
     *     user: User,
     *     rfq: Rfq,
     *     scorecard: RfqScorecard,
     *     criteria: array<int, RfqScorecardCriterion>,
     *     vendors: array<int, Vendor>,
     *     quotations: array<int, Quotation>,
     *     versions: array<int, QuotationVersion>
     * }
     */
    private function scoringGraph(string $prefix, int $vendorCount): array
    {
        $tenant = Tenant::query()->create(['name' => "{$prefix} tenant"]);
        $user = User::factory()->create();

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => "{$prefix} RFQ",
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
        ]);

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

        $criterion = RfqScorecardCriterion::query()->create([
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

        $vendors = [];
        $quotations = [];
        $versions = [];

        for ($index = 1; $index <= $vendorCount; $index++) {
            $vendor = Vendor::query()->create([
                'tenant_id' => $tenant->id,
                'name' => "{$prefix} Vendor {$index}",
                'status' => 'active',
            ]);

            $invitation = RfqInvitation::query()->create([
                'tenant_id' => $tenant->id,
                'rfq_id' => $rfq->id,
                'vendor_id' => $vendor->id,
                'status' => RfqInvitationStatus::Sent->value,
                'contact_email' => Str::slug("{$prefix}-{$index}").'@example.com',
            ]);

            $quotation = Quotation::query()->create([
                'tenant_id' => $tenant->id,
                'rfq_id' => $rfq->id,
                'rfq_invitation_id' => $invitation->id,
                'vendor_id' => $vendor->id,
                'number' => 'Q-'.Str::upper(Str::random(6)),
                'status' => QuotationStatus::submitted->value,
                'currency' => 'USD',
                'total_amount' => (string) (1000 + $index * 100),
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

            $vendors[] = $vendor;
            $quotations[] = $quotation;
            $versions[] = $version;
        }

        return [
            'tenant' => $tenant,
            'user' => $user,
            'rfq' => $rfq,
            'scorecard' => $scorecard,
            'criteria' => [$criterion],
            'vendors' => $vendors,
            'quotations' => $quotations,
            'versions' => $versions,
        ];
    }
}

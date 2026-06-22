<?php

namespace Domains\Quotation\Support;

use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\RfqScorecardEntry;
use Illuminate\Support\Collection;

class RfqScorecardCalculator
{
    public function weightedContribution(float $score, int $maxScore, float $weight): float
    {
        if ($maxScore <= 0) {
            return 0.0;
        }

        return ($score / $maxScore) * $weight;
    }

    public function formattedWeightedContribution(float $score, int $maxScore, float $weight): string
    {
        return $this->decimal($this->weightedContribution($score, $maxScore, $weight));
    }

    /**
     * @return array<int, array{
     *     vendorId: string,
     *     quotationId: string,
     *     quotationVersionId: ?string,
     *     rawTotal: string,
     *     weightedTotal: string,
     *     missingRequiredCount: int
     * }>
     */
    public function vendorTotals(RfqScorecard $scorecard): array
    {
        $criteria = $scorecard->relationLoaded('criteria')
            ? $scorecard->criteria
            : $scorecard->criteria()->get();
        $entries = $scorecard->relationLoaded('entries')
            ? $scorecard->entries
            : $scorecard->entries()->get();

        $entriesByVendor = $entries
            ->groupBy('vendor_id')
            ->map(fn ($vendorEntries) => $vendorEntries->keyBy('scorecard_criterion_id'));

        return $this->scoreableQuotations($scorecard)
            ->map(function (Quotation $quotation) use ($criteria, $entriesByVendor): array {
                $rawTotal = 0.0;
                $weightedTotal = 0.0;
                $missingRequiredCount = 0;
                $vendorEntries = $entriesByVendor->get($quotation->vendor_id, collect());

                foreach ($criteria as $criterion) {
                    /** @var RfqScorecardCriterion $criterion */
                    /** @var RfqScorecardEntry|null $entry */
                    $entry = $vendorEntries->get($criterion->id);

                    if ($entry === null || $entry->score === null) {
                        if ($criterion->is_required) {
                            $missingRequiredCount++;
                        }

                        continue;
                    }

                    $score = (float) $entry->score;
                    $rawTotal += $score;
                    $weightedTotal += $this->weightedContribution(
                        $score,
                        $criterion->max_score,
                        (float) $criterion->weight,
                    );
                }

                return [
                    'vendorId' => (string) $quotation->vendor_id,
                    'quotationId' => (string) $quotation->id,
                    'quotationVersionId' => $quotation->current_version_id !== null ? (string) $quotation->current_version_id : null,
                    'rawTotal' => $this->decimal($rawTotal),
                    'weightedTotal' => $this->decimal($weightedTotal),
                    'missingRequiredCount' => $missingRequiredCount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     status: string,
     *     scoreableVendorCount: int,
     *     requiredScoreCount: int,
     *     completedRequiredScoreCount: int,
     *     missingRequiredScoreCount: int
     * }
     */
    public function completionSummary(RfqScorecard $scorecard): array
    {
        $vendorTotals = $this->vendorTotals($scorecard);

        return $this->completionSummaryFromTotals($scorecard, $vendorTotals);
    }

    /**
     * @param  array<int, array{missingRequiredCount: int}>  $vendorTotals
     * @return array{
     *     status: string,
     *     scoreableVendorCount: int,
     *     requiredScoreCount: int,
     *     completedRequiredScoreCount: int,
     *     missingRequiredScoreCount: int
     * }
     */
    public function completionSummaryFromTotals(RfqScorecard $scorecard, array $vendorTotals): array
    {
        $scoreableVendorCount = count($vendorTotals);
        $requiredCriterionCount = $this->requiredCriterionCount($scorecard);
        $requiredScoreCount = $scoreableVendorCount * $requiredCriterionCount;
        $missingRequiredScoreCount = array_sum(array_map(
            static fn (array $vendor): int => $vendor['missingRequiredCount'],
            $vendorTotals,
        ));
        $completedRequiredScoreCount = $requiredScoreCount - $missingRequiredScoreCount;

        return [
            'status' => $missingRequiredScoreCount === 0 ? 'complete' : 'incomplete',
            'scoreableVendorCount' => $scoreableVendorCount,
            'requiredScoreCount' => $requiredScoreCount,
            'completedRequiredScoreCount' => $completedRequiredScoreCount,
            'missingRequiredScoreCount' => $missingRequiredScoreCount,
        ];
    }

    /**
     * @return Collection<int, Quotation>
     */
    private function scoreableQuotations(RfqScorecard $scorecard)
    {
        return Quotation::query()
            ->where('tenant_id', $scorecard->tenant_id)
            ->where('rfq_id', $scorecard->rfq_id)
            ->orderBy('vendor_id')
            ->orderBy('id')
            ->get()
            ->unique('vendor_id')
            ->values();
    }

    private function requiredCriterionCount(RfqScorecard $scorecard): int
    {
        $criteria = $scorecard->relationLoaded('criteria')
            ? $scorecard->criteria
            : $scorecard->criteria()->get();

        return $criteria
            ->filter(static fn (RfqScorecardCriterion $criterion): bool => $criterion->is_required)
            ->count();
    }

    private function decimal(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}

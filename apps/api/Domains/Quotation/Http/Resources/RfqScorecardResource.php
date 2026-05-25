<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\RfqScorecardEntry;
use Domains\Quotation\Support\RfqScorecardCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqScorecardResource extends JsonResource
{
    /**
     * @param array<string, mixed>|null $comparisonContext
     */
    public function __construct($resource, private readonly ?array $comparisonContext = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RfqScorecard $scorecard */
        $scorecard = $this->resource;
        $scorecard->loadMissing(['rfq.requisition.requester', 'rfq.project', 'criteria', 'entries']);
        /** @var Rfq $rfq */
        $rfq = $scorecard->rfq;
        $calculator = app(RfqScorecardCalculator::class);
        $criteria = $scorecard->criteria;
        $entries = $scorecard->entries;
        $comparison = $this->comparisonContext ?? [];
        $comparisonVendors = collect($comparison['vendors'] ?? [])->keyBy('vendorId');
        $vendorTotals = collect($calculator->vendorTotals($scorecard))
            ->map(function (array $vendor) use ($comparisonVendors): array {
                $context = $comparisonVendors->get($vendor['vendorId'], []);

                return [
                    'vendorId' => $vendor['vendorId'],
                    'vendorName' => $context['vendorName'] ?? 'Unknown vendor',
                    'quotationId' => $vendor['quotationId'],
                    'quotationVersionId' => $vendor['quotationVersionId'],
                    'scoreable' => (bool) ($context !== []),
                    'rawTotal' => $vendor['rawTotal'],
                    'weightedTotal' => $vendor['weightedTotal'],
                    'missingRequiredCount' => $vendor['missingRequiredCount'],
                    'readiness' => $context['readiness'] ?? 'unknown',
                    'currency' => $context['currency'] ?? null,
                    'totalAmount' => $context['totalAmount'] ?? null,
                    'leadTimeDays' => $context['leadTimeDays'] ?? null,
                    'paymentTerms' => $context['paymentTerms'] ?? null,
                    'deliveryTerms' => $context['deliveryTerms'] ?? null,
                    'warrantyTerms' => $context['warrantyTerms'] ?? null,
                    'complianceNotes' => $context['complianceNotes'] ?? null,
                    'issueCounts' => $context['issueCounts'] ?? null,
                    'links' => $context['links'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'rfq' => [
                'id' => (string) $rfq->id,
                'number' => $rfq->number,
                'title' => $rfq->title,
                'status' => $rfq->status?->value ?? $rfq->status,
                'responseDueAt' => $rfq->response_due_at?->toISOString(),
                'scopeSummary' => $rfq->scope_summary,
                'requisition' => $rfq->requisition ? [
                    'id' => (string) $rfq->requisition->id,
                    'number' => $rfq->requisition->number,
                    'title' => $rfq->requisition->title,
                ] : null,
                'project' => $rfq->project ? [
                    'id' => (string) $rfq->project->id,
                    'number' => $rfq->project->number,
                    'name' => $rfq->project->name,
                ] : null,
            ],
            'scorecard' => [
                'id' => (string) $scorecard->id,
                'templateId' => (string) $scorecard->template_id,
                'templateName' => $scorecard->template_name,
                'templateDescription' => $scorecard->template_description,
                'status' => $scorecard->statusState()->value,
                'appliedAt' => $scorecard->applied_at?->toISOString(),
                'completedAt' => $scorecard->completed_at?->toISOString(),
            ],
            'criteria' => $criteria
                ->map(fn (RfqScorecardCriterion $criterion): array => $this->criterion($criterion))
                ->values()
                ->all(),
            'vendors' => $vendorTotals,
            'entries' => $entries
                ->map(fn (RfqScorecardEntry $entry): array => $this->entry($entry, $criteria, $calculator))
                ->sortBy(['vendorId', 'criterionId'])
                ->values()
                ->all(),
            'completion' => $calculator->completionSummaryFromTotals($scorecard, $vendorTotals),
            'comparisonContext' => [
                'comparisonPath' => "/quotations/comparisons/{$rfq->id}",
                'normalizationPaths' => $comparisonVendors
                    ->mapWithKeys(fn (array $vendor): array => [
                        $vendor['vendorId'] => $vendor['links']['normalization'] ?? null,
                    ])
                    ->filter()
                    ->all(),
                'quotationVersionPaths' => $comparisonVendors
                    ->mapWithKeys(fn (array $vendor): array => [
                        $vendor['vendorId'] => $vendor['links']['quotationVersion'] ?? null,
                    ])
                    ->filter()
                    ->all(),
                'readiness' => $comparison['readiness'] ?? null,
                'vendors' => array_values($comparison['vendors'] ?? []),
                'lineRows' => array_values($comparison['lineRows'] ?? []),
                'commercialTerms' => array_values($comparison['commercialTerms'] ?? []),
                'notes' => array_values($comparison['notes'] ?? []),
                'noteGroups' => array_values($comparison['noteGroups'] ?? []),
            ],
            'permissions' => [
                'canViewScorecard' => $request->user()?->can('view', $scorecard) ?? false,
                'canApplyScorecard' => $request->user()?->can('create', [RfqScorecard::class, $rfq]) ?? false,
                'canManageScores' => ($request->user()?->can('update', $scorecard) ?? false)
                    || ($request->user()?->can('complete', $scorecard) ?? false)
                    || ($request->user()?->can('reopen', $scorecard) ?? false),
                'canManageScoringTemplates' => $request->user()?->can('create', QuotationScoringTemplate::class) ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function criterion(RfqScorecardCriterion $criterion): array
    {
        return [
            'id' => (string) $criterion->id,
            'sourceTemplateCriterionId' => $criterion->source_template_criterion_id !== null ? (string) $criterion->source_template_criterion_id : null,
            'category' => $criterion->category?->value ?? $criterion->category,
            'label' => $criterion->label,
            'guidance' => $criterion->guidance,
            'weight' => (string) $criterion->weight,
            'maxScore' => $criterion->max_score,
            'required' => (bool) $criterion->is_required,
            'displayOrder' => $criterion->display_order,
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, RfqScorecardCriterion> $criteria
     * @return array<string, mixed>
     */
    private function entry(RfqScorecardEntry $entry, $criteria, RfqScorecardCalculator $calculator): array
    {
        /** @var RfqScorecardCriterion|null $criterion */
        $criterion = $criteria->firstWhere('id', $entry->scorecard_criterion_id);
        $weightedContribution = null;

        if ($criterion !== null && $entry->score !== null) {
            $weightedContribution = $calculator->formattedWeightedContribution(
                (float) $entry->score,
                $criterion->max_score,
                (float) $criterion->weight,
            );
        }

        return [
            'criterionId' => (string) $entry->scorecard_criterion_id,
            'vendorId' => (string) $entry->vendor_id,
            'quotationId' => $entry->quotation_id !== null ? (string) $entry->quotation_id : null,
            'quotationVersionId' => $entry->quotation_version_id !== null ? (string) $entry->quotation_version_id : null,
            'score' => $entry->score !== null ? (string) $entry->score : null,
            'note' => $entry->note,
            'weightedContribution' => $weightedContribution,
            'scoredAt' => $entry->scored_at?->toISOString(),
        ];
    }
}

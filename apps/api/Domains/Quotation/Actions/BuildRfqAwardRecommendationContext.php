<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Domains\Quotation\Support\RfqScorecardCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class BuildRfqAwardRecommendationContext
{
    public function __construct(
        private readonly BuildQuotationComparison $comparisonBuilder,
        private readonly RfqScorecardCalculator $scorecardCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq): array
    {
        Gate::forUser($actor)->authorize('view', [RfqAwardRecommendation::class, $rfq]);

        $rfq->loadMissing(['requisition.requester', 'project']);

        $quotations = Quotation::query()
            ->with(['vendor', 'currentVersion'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->orderBy('vendor_id')
            ->orderBy('id')
            ->get();

        $recommendation = RfqAwardRecommendation::query()
            ->with('evidenceReferences')
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $scorecard = RfqScorecard::query()
            ->with(['criteria', 'entries'])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->first();

        $comparison = $this->comparisonBuilder->handle($tenant, $rfq);
        $comparisonVendors = collect($comparison['vendors'] ?? [])->keyBy('quotationId');
        $vendorTotals = $scorecard !== null
            ? collect($this->scorecardCalculator->vendorTotals($scorecard))->keyBy('quotationId')
            : collect();

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
            'recommendation' => $recommendation,
            'vendorOptions' => $quotations->map(function (Quotation $quotation) use ($comparisonVendors, $vendorTotals): array {
                $comparisonVendor = $comparisonVendors->get((string) $quotation->id, []);
                $vendorTotal = $vendorTotals->get((string) $quotation->id, []);

                return [
                    'vendorId' => (string) $quotation->vendor_id,
                    'vendorName' => $quotation->vendor?->name ?? 'Unknown vendor',
                    'quotationId' => (string) $quotation->id,
                    'quotationNumber' => $quotation->number,
                    'quotationVersionId' => $quotation->current_version_id !== null ? (string) $quotation->current_version_id : null,
                    'readiness' => $comparisonVendor['readiness'] ?? 'unknown',
                    'currency' => $comparisonVendor['currency'] ?? $quotation->currency,
                    'totalAmount' => $comparisonVendor['totalAmount'] ?? $this->decimalOrNull($quotation->total_amount),
                    'leadTimeDays' => $comparisonVendor['leadTimeDays'] ?? $quotation->lead_time_days,
                    'paymentTerms' => $comparisonVendor['paymentTerms'] ?? $quotation->payment_terms,
                    'deliveryTerms' => $comparisonVendor['deliveryTerms'] ?? $quotation->delivery_terms,
                    'warrantyTerms' => $comparisonVendor['warrantyTerms'] ?? $quotation->warranty_terms,
                    'complianceNotes' => $comparisonVendor['complianceNotes'] ?? $quotation->compliance_notes,
                    'issueCounts' => $comparisonVendor['issueCounts'] ?? null,
                    'scorecard' => $vendorTotal !== [] ? [
                        'rawTotal' => $vendorTotal['rawTotal'],
                        'weightedTotal' => $vendorTotal['weightedTotal'],
                        'missingRequiredCount' => $vendorTotal['missingRequiredCount'],
                    ] : null,
                    'links' => [
                        'quotationVersion' => $comparisonVendor['links']['quotationVersion'] ?? null,
                        'normalization' => $comparisonVendor['links']['normalization'] ?? null,
                    ],
                ];
            })->values()->all(),
            'scorecard' => $scorecard !== null ? [
                'id' => (string) $scorecard->id,
                'status' => $scorecard->statusState()->value,
                'completedAt' => $scorecard->completed_at?->toISOString(),
                'completion' => $this->scorecardCalculator->completionSummary($scorecard),
                'vendorTotals' => $vendorTotals->values()->all(),
            ] : null,
            'readiness' => $this->readiness($comparison, $scorecard),
            'evidenceReferences' => $this->evidenceReferences($tenant, $rfq, $quotations, $scorecard, $recommendation),
            'links' => [
                'comparison' => "/quotations/comparisons/{$rfq->id}",
                'scoring' => "/quotations/scoring/{$rfq->id}",
            ],
            'permissions' => [
                'canViewAwardRecommendation' => Gate::forUser($actor)->check('view', [RfqAwardRecommendation::class, $rfq]),
                'canManageAwardRecommendation' => Gate::forUser($actor)->check('manage', [RfqAwardRecommendation::class, $rfq]),
                'canSubmitAwardRecommendation' => $recommendation !== null
                    && $recommendation->statusState() === RfqAwardRecommendationStatus::Draft
                    && Gate::forUser($actor)->check('submit', $recommendation),
                'canWithdrawAwardRecommendation' => $recommendation !== null
                    && $recommendation->statusState() === RfqAwardRecommendationStatus::PendingApproval
                    && Gate::forUser($actor)->check('withdraw', $recommendation),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array{comparisonStatus: string, scoringStatus: string, blockingMessages: array<int, string>}
     */
    private function readiness(array $comparison, ?RfqScorecard $scorecard): array
    {
        $comparisonReadyCount = (int) data_get($comparison, 'readiness.approvedNormalizationCount', 0);
        $pendingNormalizationCount = (int) data_get($comparison, 'readiness.pendingNormalizationCount', 0);
        $comparisonStatus = $comparisonReadyCount > 0 && $pendingNormalizationCount === 0 ? 'ready' : 'incomplete';
        $scoringStatus = match (true) {
            $scorecard === null => 'not_started',
            $scorecard->statusState() === RfqScorecardStatus::Completed => 'complete',
            default => 'in_progress',
        };

        $blockingMessages = [];

        if ($comparisonReadyCount === 0) {
            $blockingMessages[] = 'At least one ready comparison vendor is required before submission.';
        }

        if ($pendingNormalizationCount > 0) {
            $blockingMessages[] = 'Pending quotation normalization blockers must be resolved before submission.';
        }

        if ($scorecard !== null && $scorecard->statusState() !== RfqScorecardStatus::Completed) {
            $blockingMessages[] = 'The RFQ scorecard must be completed before submission.';
        }

        return [
            'comparisonStatus' => $comparisonStatus,
            'scoringStatus' => $scoringStatus,
            'blockingMessages' => $blockingMessages,
        ];
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     * @return array<int, array<string, mixed>>
     */
    private function evidenceReferences(
        Tenant $tenant,
        Rfq $rfq,
        $quotations,
        ?RfqScorecard $scorecard,
        ?RfqAwardRecommendation $recommendation,
    ): array {
        $selected = collect($recommendation?->evidenceReferences ?? [])
            ->map(fn ($evidence): string => $evidence->evidence_type->value.'::'.$evidence->evidence_id)
            ->all();

        $quotationIds = $quotations->pluck('id')->all();

        $attachments = Attachment::query()
            ->where('tenant_id', $tenant->id)
            ->where('attachable_type', Quotation::class)
            ->whereIn('attachable_id', $quotationIds)
            ->orderByDesc('id')
            ->get();

        $notes = QuotationComparisonNote::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $references = [];

        foreach ($quotations as $quotation) {
            if ($quotation->current_version_id !== null) {
                $references[] = [
                    'type' => 'quotation_version',
                    'id' => (string) $quotation->current_version_id,
                    'label' => sprintf(
                        '%s quotation version %s',
                        $quotation->vendor?->name ?? 'Unknown vendor',
                        $quotation->currentVersion?->version_number ?? 'current'
                    ),
                    'selected' => in_array('quotation_version::'.$quotation->current_version_id, $selected, true),
                    'vendorId' => (string) $quotation->vendor_id,
                    'quotationId' => (string) $quotation->id,
                ];
            }
        }

        foreach ($attachments as $attachment) {
            $references[] = [
                'type' => 'quotation_attachment',
                'id' => (string) $attachment->id,
                'label' => $attachment->original_filename,
                'selected' => in_array('quotation_attachment::'.$attachment->id, $selected, true),
                'quotationId' => (string) $attachment->attachable_id,
            ];
        }

        foreach ($notes as $note) {
            $references[] = [
                'type' => 'comparison_note',
                'id' => (string) $note->id,
                'label' => $note->note,
                'selected' => in_array('comparison_note::'.$note->id, $selected, true),
                'quotationId' => $note->quotation_id !== null ? (string) $note->quotation_id : null,
                'vendorId' => $note->vendor_id !== null ? (string) $note->vendor_id : null,
            ];
        }

        if ($scorecard !== null) {
            $references[] = [
                'type' => 'scorecard',
                'id' => (string) $scorecard->id,
                'label' => $scorecard->template_name,
                'selected' => in_array('scorecard::'.$scorecard->id, $selected, true),
            ];
        }

        return $references;
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}

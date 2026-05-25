<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Quotation\States\RfqScorecardStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitRfqAwardRecommendation
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SaveRfqAwardRecommendation $saveRecommendation,
        private readonly BuildQuotationComparison $comparisonBuilder,
    ) {}

    /**
     * @param array<string, mixed>|null $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, ?array $data = null): RfqAwardRecommendation
    {
        if ($data !== null) {
            $this->saveRecommendation->handle($tenant, $actor, $rfq, $data);
        } else {
            Gate::forUser($actor)->authorize('manage', [RfqAwardRecommendation::class, $rfq]);
        }

        return DB::transaction(function () use ($tenant, $actor, $rfq): RfqAwardRecommendation {
            $lockedRfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($rfq->id)
                ->lockForUpdate()
                ->firstOrFail();

            $recommendation = RfqAwardRecommendation::query()
                ->with('evidenceReferences')
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $lockedRfq->id)
                ->where('status', RfqAwardRecommendationStatus::Draft->value)
                ->lockForUpdate()
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($recommendation === null) {
                throw new ConflictHttpException('A draft award recommendation is required before submission.');
            }

            Gate::forUser($actor)->authorize('submit', $recommendation);

            if ($recommendation->recommended_vendor_id === null) {
                throw ValidationException::withMessages([
                    'recommendedVendorId' => ['A recommended vendor is required before submission.'],
                ]);
            }

            if ($recommendation->recommended_quotation_id === null) {
                throw ValidationException::withMessages([
                    'recommendedQuotationId' => ['A recommended quotation is required before submission.'],
                ]);
            }

            if ($recommendation->recommended_quotation_version_id === null) {
                throw ValidationException::withMessages([
                    'recommendedQuotationVersionId' => ['A current quotation version is required before submission.'],
                ]);
            }

            if ($recommendation->rationale === null || trim($recommendation->rationale) === '') {
                throw ValidationException::withMessages([
                    'rationale' => ['A rationale is required before submission.'],
                ]);
            }

            $quotation = Quotation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $lockedRfq->id)
                ->whereKey($recommendation->recommended_quotation_id)
                ->lockForUpdate()
                ->first();

            if ($quotation === null || (int) $quotation->vendor_id !== (int) $recommendation->recommended_vendor_id) {
                throw ValidationException::withMessages([
                    'recommendedQuotationId' => ['The recommended quotation must belong to the selected vendor and RFQ.'],
                ]);
            }

            if ((int) $quotation->current_version_id !== (int) $recommendation->recommended_quotation_version_id) {
                throw new ConflictHttpException('The recommended quotation version is stale.');
            }

            $scorecard = RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $lockedRfq->id)
                ->lockForUpdate()
                ->first();

            if ($scorecard !== null && $scorecard->statusState() !== RfqScorecardStatus::Completed) {
                throw new ConflictHttpException('The RFQ scorecard must be completed before submission.');
            }

            $this->assertComparisonReady($tenant, $lockedRfq);
            $this->saveRecommendation->assertPersistedEvidenceReferencesBelongToRfq($tenant, $lockedRfq, $recommendation);

            $before = [
                'status' => $recommendation->statusState()->value,
                'submittedAt' => $recommendation->submitted_at?->toISOString(),
            ];

            $recommendation->forceFill([
                'status' => RfqAwardRecommendationStatus::PendingApproval->value,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            $recommendation = $recommendation->refresh()->load('evidenceReferences');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_award_recommendation.submitted',
                subject: $lockedRfq,
                metadata: ['recommendationId' => (string) $recommendation->id],
                before: $before,
                after: [
                    'status' => $recommendation->statusState()->value,
                    'submittedAt' => $recommendation->submitted_at?->toISOString(),
                ],
                subjectDisplay: $lockedRfq->number,
            ));

            return $recommendation;
        });
    }

    private function assertComparisonReady(Tenant $tenant, Rfq $rfq): void
    {
        $comparison = $this->comparisonBuilder->handle($tenant, $rfq);
        $readyCount = (int) data_get($comparison, 'readiness.approvedNormalizationCount', 0);
        $pendingCount = (int) data_get($comparison, 'readiness.pendingNormalizationCount', 0);

        if ($readyCount === 0 || $pendingCount > 0) {
            throw new ConflictHttpException('Quotation comparison must be ready before submission.');
        }
    }
}

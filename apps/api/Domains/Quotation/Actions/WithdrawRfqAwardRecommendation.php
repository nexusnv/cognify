<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WithdrawRfqAwardRecommendation
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): RfqAwardRecommendation
    {
        return DB::transaction(function () use ($tenant, $actor, $rfq, $data): RfqAwardRecommendation {
            $lockedRfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($rfq->id)
                ->lockForUpdate()
                ->firstOrFail();

            $recommendation = RfqAwardRecommendation::query()
                ->with('evidenceReferences')
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $lockedRfq->id)
                ->where('status', RfqAwardRecommendationStatus::PendingApproval->value)
                ->lockForUpdate()
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($recommendation === null) {
                throw new ConflictHttpException('Only pending approval award recommendations can be withdrawn.');
            }

            Gate::forUser($actor)->authorize('withdraw', $recommendation);

            $rawReason = $data['reason'] ?? null;

            if (! is_string($rawReason)) {
                throw ValidationException::withMessages([
                    'reason' => ['A withdrawal reason is required.'],
                ]);
            }

            $reason = trim($rawReason);

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A withdrawal reason is required.'],
                ]);
            }

            $before = [
                'status' => $recommendation->statusState()->value,
                'withdrawalReason' => $recommendation->withdrawal_reason,
            ];

            $recommendation->forceFill([
                'status' => RfqAwardRecommendationStatus::Withdrawn->value,
                'withdrawal_reason' => $reason,
                'withdrawn_by_user_id' => $actor->id,
                'withdrawn_at' => now(),
                'updated_by_user_id' => $actor->id,
            ])->save();

            $recommendation = $recommendation->refresh()->load('evidenceReferences');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_award_recommendation.withdrawn',
                subject: $lockedRfq,
                metadata: ['recommendationId' => (string) $recommendation->id],
                before: $before,
                after: [
                    'status' => $recommendation->statusState()->value,
                    'withdrawalReason' => $recommendation->withdrawal_reason,
                ],
                subjectDisplay: $lockedRfq->number,
            ));

            return $recommendation;
        });
    }
}

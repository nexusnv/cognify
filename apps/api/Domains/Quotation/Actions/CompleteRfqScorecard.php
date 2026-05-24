<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Support\RfqScorecardCalculator;
use Domains\Quotation\States\RfqScorecardStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CompleteRfqScorecard
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RfqScorecardCalculator $calculator,
    ) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, RfqScorecard $scorecard): RfqScorecard
    {
        Gate::forUser($actor)->authorize('complete', $scorecard);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $scorecard): RfqScorecard {
            $lockedScorecard = RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($scorecard->id)
                ->lockForUpdate()
                ->firstOrFail()
                ->load('criteria', 'entries');

            $summary = $this->calculator->completionSummary($lockedScorecard);

            if ($summary['status'] !== 'complete') {
                throw ValidationException::withMessages([
                    'scorecard' => ['Required scores must be completed for all scoreable vendors before completion.'],
                ]);
            }

            $lockedScorecard->forceFill([
                'status' => RfqScorecardStatus::Completed->value,
                'completed_by_user_id' => $actor->id,
                'completed_at' => now(),
            ])->save();

            $lockedScorecard = $lockedScorecard->refresh()->load('criteria', 'entries');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_scorecard.completed',
                subject: $rfq,
                metadata: ['scorecardId' => (string) $lockedScorecard->id],
                subjectDisplay: $rfq->number,
            ));

            return $lockedScorecard;
        });
    }
}

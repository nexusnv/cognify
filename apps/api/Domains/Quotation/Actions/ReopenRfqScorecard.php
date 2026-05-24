<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\RfqScorecardStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReopenRfqScorecard
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, RfqScorecard $scorecard): RfqScorecard
    {
        Gate::forUser($actor)->authorize('reopen', $scorecard);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $scorecard): RfqScorecard {
            $lockedScorecard = RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($scorecard->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedScorecard->forceFill([
                'status' => RfqScorecardStatus::InProgress->value,
                'completed_by_user_id' => null,
                'completed_at' => null,
            ])->save();

            $lockedScorecard = $lockedScorecard->refresh()->load('criteria', 'entries');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_scorecard.reopened',
                subject: $rfq,
                metadata: ['scorecardId' => (string) $lockedScorecard->id],
                subjectDisplay: $rfq->number,
            ));

            return $lockedScorecard;
        });
    }
}

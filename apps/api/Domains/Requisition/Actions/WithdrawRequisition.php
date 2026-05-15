<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class WithdrawRequisition
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition, string $reason): Requisition
    {
        if (! in_array($requisition->status, [
            RequisitionStatus::Draft,
            RequisitionStatus::Submitted,
            RequisitionStatus::ChangesRequested,
        ], true)) {
            throw new ConflictHttpException('Only draft, submitted, or change-requested requisitions can be withdrawn.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $reason): Requisition {
            $requisition->forceFill([
                'status' => RequisitionStatus::Withdrawn,
                'withdrawn_at' => now(),
                'withdrawn_by_id' => $actor->id,
                'withdrawal_reason' => $reason,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.withdrawn',
                subject: $requisition,
                metadata: [],
                before: ['status' => $requisition->getOriginal('status')->value],
                after: ['status' => RequisitionStatus::Withdrawn->value],
                subjectDisplay: $requisition->number,
            ));

            return $requisition->refresh()->load([
                'requester',
                'lineItems',
                'changesRequestedBy',
                'withdrawnBy',
                'cancelledBy',
            ]);
        });
    }
}

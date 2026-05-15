<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelRequisition
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition, string $reason): Requisition
    {
        if (! in_array($requisition->status, [RequisitionStatus::Submitted, RequisitionStatus::ChangesRequested], true)) {
            throw new ConflictHttpException('Only submitted or change-requested requisitions can be cancelled.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $reason): Requisition {
            $requisition->forceFill([
                'status' => RequisitionStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_id' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.cancelled',
                subject: $requisition,
                metadata: [],
                before: ['status' => $requisition->getOriginal('status')->value],
                after: ['status' => RequisitionStatus::Cancelled->value],
                subjectDisplay: $requisition->number,
            ));

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: collect([$requisition->requester])->reject(fn (User $recipient): bool => $recipient->id === $actor->id),
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_REQUISITION_CANCELLED,
                    title: 'Requisition cancelled',
                    body: "{$requisition->number} was cancelled.",
                    href: "/requisitions/{$requisition->id}",
                    subject: $requisition,
                    subjectLabel: $requisition->number,
                    metadata: [
                        'number' => $requisition->number,
                    ],
                    actor: $actor,
                ),
            );

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

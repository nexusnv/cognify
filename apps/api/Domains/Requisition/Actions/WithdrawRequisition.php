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

class WithdrawRequisition
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {}

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

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: collect([$requisition->requester])
                    ->filter(fn (?User $recipient): bool => $recipient !== null)
                    ->reject(fn (User $recipient): bool => $recipient->id === $actor->id),
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_REQUISITION_WITHDRAWN,
                    title: 'Requisition withdrawn',
                    body: "{$requisition->number} was withdrawn.",
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

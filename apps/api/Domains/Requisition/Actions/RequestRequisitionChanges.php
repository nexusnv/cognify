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

class RequestRequisitionChanges
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {}

    /**
     * @param  array{reason:string,requestedFields?:array<int,string>}  $data
     */
    public function handle(Tenant $tenant, User $actor, Requisition $requisition, array $data): Requisition
    {
        if ($requisition->status !== RequisitionStatus::Submitted) {
            throw new ConflictHttpException('Only submitted requisitions can receive change requests.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $data): Requisition {
            $before = [
                'status' => $requisition->status->value,
                'changeRequestReason' => $requisition->change_request_reason,
                'changeRequestFields' => $requisition->change_request_fields ?? [],
            ];

            $requisition->forceFill([
                'status' => RequisitionStatus::ChangesRequested,
                'changes_requested_at' => now(),
                'changes_requested_by_id' => $actor->id,
                'change_request_reason' => $data['reason'],
                'change_request_fields' => $data['requestedFields'] ?? [],
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.changes_requested',
                subject: $requisition,
                metadata: [],
                before: $before,
                after: [
                    'status' => $requisition->status->value,
                    'changeRequestReason' => $requisition->change_request_reason,
                    'changeRequestFields' => $requisition->change_request_fields ?? [],
                ],
                subjectDisplay: $requisition->number,
            ));

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: collect([$requisition->requester])
                    ->filter(fn (?User $recipient): bool => $recipient !== null)
                    ->reject(fn (User $recipient): bool => $recipient->id === $actor->id),
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_REQUISITION_CHANGES_REQUESTED,
                    title: 'Changes requested',
                    body: "{$requisition->number} needs updates before it can proceed.",
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

<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Auth\TenantRole;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResubmitRequisition
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
        private readonly SubmitRequisition $submitRequisition,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition): Requisition
    {
        if ($requisition->status !== RequisitionStatus::ChangesRequested) {
            throw new ConflictHttpException('Only change-requested requisitions can be resubmitted.');
        }

        $this->submitRequisition->validateSubmission($requisition);

        return DB::transaction(function () use ($tenant, $actor, $requisition): Requisition {
            $requisition->forceFill([
                'status' => RequisitionStatus::Submitted,
                'submitted_at' => now(),
                'changes_requested_at' => null,
                'changes_requested_by_id' => null,
                'change_request_reason' => null,
                'change_request_fields' => [],
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.resubmitted',
                subject: $requisition,
                metadata: [],
                before: ['status' => RequisitionStatus::ChangesRequested->value],
                after: ['status' => RequisitionStatus::Submitted->value],
                subjectDisplay: $requisition->number,
            ));

            $recipients = $tenant->users()
                ->wherePivotIn('role', [TenantRole::Buyer->value, TenantRole::Admin->value])
                ->get()
                ->reject(fn (User $recipient): bool => $recipient->id === $actor->id);

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: $recipients,
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_REQUISITION_RESUBMITTED,
                    title: 'Requisition resubmitted',
                    body: "{$requisition->number} is ready for review again.",
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

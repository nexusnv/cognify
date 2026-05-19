<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DelegateApprovalTask
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, ApprovalTask $task, ApprovalDelegation $delegation, int $lockVersion): ApprovalTask
    {
        return DB::transaction(function () use ($tenant, $actor, $task, $delegation, $lockVersion): ApprovalTask {
            $task = ApprovalTask::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $delegation = ApprovalDelegation::query()
                ->with(['delegate', 'delegator'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($delegation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeActor($actor, $task);
            $this->assertActiveTask($task, $lockVersion);
            $this->assertDelegationIsUsable($tenant, $task, $delegation, $actor);

            $now = now();
            $metadata = array_filter([
                ...(array) ($task->metadata ?? []),
                'delegationId' => (string) $delegation->id,
                'delegatedById' => (string) $actor->id,
                'delegatedToId' => (string) $delegation->delegate_id,
                'delegatedAt' => $now->toISOString(),
                'delegationScope' => $delegation->scope,
                'delegationEndsAt' => $delegation->ends_at?->toISOString(),
            ], static fn (mixed $value): bool => $value !== null);

            $task->forceFill([
                'assignee_id' => $delegation->delegate_id,
                'original_assignee_id' => $task->original_assignee_id ?? $actor->id,
                'status' => ApprovalTaskStatus::Active,
                'assigned_at' => $now,
                'lock_version' => $task->lock_version + 1,
                'metadata' => $metadata,
            ])->save();

            $subject = $task->subject;
            $delegate = $delegation->delegate;
            $originalAssignee = $task->originalAssignee;
            if ($subject instanceof Requisition) {
                $recipients = collect([$delegate, $originalAssignee])->filter()->unique('id')->values();

                if ($recipients->isNotEmpty()) {
                    $this->notificationRecorder->record($tenant, $recipients, new NotificationData(
                        type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                        title: 'Approval task delegated',
                        body: $subject->title,
                        href: "/approvals/tasks/{$task->id}",
                        subject: $subject,
                        subjectLabel: $subject->number,
                        actor: $actor,
                        metadata: [
                            'delegationId' => (string) $delegation->id,
                        ],
                    ));
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_task.delegated',
                subject: $task->subject,
                metadata: [
                    'approvalTaskId' => (string) $task->id,
                    'approvalDelegationId' => (string) $delegation->id,
                    'delegateId' => (string) $delegation->delegate_id,
                ],
            ));

            return $task->refresh()->load(['assignee', 'originalAssignee', 'decidedBy', 'stage', 'instance', 'subject.requester', 'subject.lineItems']);
        });
    }

    private function authorizeActor(User $actor, ApprovalTask $task): void
    {
        if ((int) $task->assignee_id !== (int) $actor->id) {
            throw new AuthorizationException('Only the assigned approver can delegate this task.');
        }
    }

    private function assertActiveTask(ApprovalTask $task, int $lockVersion): void
    {
        if ($task->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Approval task has changed. Refresh before trying again.');
        }

        if ($task->status !== ApprovalTaskStatus::Active) {
            throw new ConflictHttpException('Only active approval tasks can be delegated.');
        }
    }

    private function assertDelegationIsUsable(Tenant $tenant, ApprovalTask $task, ApprovalDelegation $delegation, User $actor): void
    {
        if ((int) $delegation->delegator_id !== (int) $actor->id) {
            throw new AuthorizationException('You can only use your own delegations.');
        }

        if ($delegation->status !== \Domains\Approval\States\ApprovalDelegationStatus::Active) {
            throw ValidationException::withMessages([
                'approvalDelegationId' => ['The selected delegation is not active.'],
            ]);
        }

        $startsAt = Carbon::instance($delegation->starts_at);
        $endsAt = Carbon::instance($delegation->ends_at);

        if ($startsAt->isFuture() || $endsAt->isPast()) {
            throw ValidationException::withMessages([
                'approvalDelegationId' => ['The selected delegation is expired.'],
            ]);
        }

        $task->loadMissing('subject.requester');
        if ($task->subject instanceof Requisition && (int) $task->subject->requester_id === (int) $delegation->delegate_id) {
            throw ValidationException::withMessages([
                'approvalDelegationId' => ['The delegate cannot be the requester of the requisition.'],
            ]);
        }
    }
}

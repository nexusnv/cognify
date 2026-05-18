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
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalStage;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\Services\ApprovalSlaCalculator;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Actions\MarkRequisitionApproved;
use Domains\Requisition\Models\Requisition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveApprovalTask
{
    public function __construct(
        private readonly MarkRequisitionApproved $markRequisitionApproved,
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
        private readonly ApprovalSlaCalculator $slaCalculator,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, ApprovalTask $task, int $lockVersion): ApprovalTask
    {
        return DB::transaction(function () use ($tenant, $actor, $task, $lockVersion): ApprovalTask {
            $task = $this->lockedTask($tenant, $task);
            $this->authorizeAssignee($actor, $task);
            $this->assertActiveTask($task, $lockVersion);

            $task->forceFill([
                'status' => ApprovalTaskStatus::Approved,
                'decision' => 'approved',
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $stage = $task->stage()->lockForUpdate()->firstOrFail();

            if ($this->stageShouldComplete($stage, $task)) {
                if ($stage->completion_rule === 'any') {
                    $this->cancelSiblingTasks($stage, $task);
                }

                $stage->forceFill([
                    'status' => ApprovalStageStatus::Completed,
                    'completed_at' => now(),
                ])->save();

                $instance = $task->instance()->lockForUpdate()->firstOrFail();
                $nextStage = $this->nextBlockedStage($instance, $stage);

                if ($nextStage instanceof ApprovalStage) {
                    $this->activateStage($tenant, $actor, $instance, $nextStage);

                    $instance->forceFill([
                        'current_stage_sequence' => $nextStage->sequence,
                    ])->save();
                } else {
                    $instance->forceFill([
                        'status' => ApprovalInstanceStatus::Approved,
                        'completed_at' => now(),
                    ])->save();

                    $subject = $task->subject;
                    if ($subject instanceof Requisition) {
                        $this->markRequisitionApproved->handle($subject, $instance, $actor);
                    }
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_task.approved',
                subject: $task->subject,
                metadata: ['approvalTaskId' => (string) $task->id],
            ));

            return $task->refresh()->load(['assignee', 'stage', 'instance', 'subject']);
        });
    }

    private function stageShouldComplete(ApprovalStage $stage, ApprovalTask $decidingTask): bool
    {
        if ($stage->completion_rule === 'any') {
            return true;
        }

        return $stage->tasks()
            ->whereKeyNot($decidingTask->id)
            ->where('status', ApprovalTaskStatus::Active)
            ->doesntExist();
    }

    private function cancelSiblingTasks(ApprovalStage $stage, ApprovalTask $decidingTask): void
    {
        $siblings = $stage->tasks()
            ->whereKeyNot($decidingTask->id)
            ->whereIn('status', [ApprovalTaskStatus::Active, ApprovalTaskStatus::Blocked])
            ->lockForUpdate()
            ->get();

        foreach ($siblings as $sibling) {
            $metadata = $sibling->metadata ?? [];
            $metadata['cancelledByTaskId'] = (string) $decidingTask->id;
            $metadata['cancelledReason'] = 'parallel_any_completed';

            $sibling->forceFill([
                'status' => ApprovalTaskStatus::Cancelled,
                'metadata' => $metadata,
                'lock_version' => $sibling->lock_version + 1,
            ])->save();
        }
    }

    private function activateStage(Tenant $tenant, User $actor, ApprovalInstance $instance, ApprovalStage $stage): void
    {
        $activatedAt = now();
        $dueAt = $this->slaCalculator->calculateDueAtForStage(
            $instance->policyVersion?->sla_rules ?? [],
            $stage->name,
            $activatedAt,
        );

        $stage->forceFill([
            'status' => ApprovalStageStatus::Active,
            'activated_at' => $activatedAt,
            'due_at' => $dueAt,
        ])->save();

        $tasks = $stage->tasks()->lockForUpdate()->get();

        foreach ($tasks as $task) {
            $task->forceFill([
                'status' => ApprovalTaskStatus::Active,
                'assigned_at' => $activatedAt,
                'due_at' => $dueAt,
                'lock_version' => $task->lock_version + 1,
            ])->save();
        }

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'approval_stage.activated',
            subject: $instance->subject,
            metadata: [
                'approvalInstanceId' => (string) $instance->id,
                'approvalStageId' => (string) $stage->id,
            ],
        ));

        $recipients = $tasks->map->assignee->filter()->unique('id')->values();
        if ($recipients->isNotEmpty()) {
            $this->notificationRecorder->record($tenant, $recipients, new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                title: 'Approval task assigned',
                body: $instance->subject?->title ?? 'Approval task',
                href: "/approvals/tasks/{$tasks->first()?->id}",
                subject: $instance->subject,
                subjectLabel: $instance->subject instanceof Requisition ? $instance->subject->number : null,
                actor: $actor,
            ));
        }
    }

    private function nextBlockedStage(ApprovalInstance $instance, ApprovalStage $currentStage): ?ApprovalStage
    {
        return $instance->stages()
            ->where('sequence', '>', $currentStage->sequence)
            ->whereIn('status', [ApprovalStageStatus::Blocked, ApprovalStageStatus::Pending])
            ->orderBy('sequence')
            ->lockForUpdate()
            ->first();
    }

    private function lockedTask(Tenant $tenant, ApprovalTask $task): ApprovalTask
    {
        return ApprovalTask::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($task->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function authorizeAssignee(User $actor, ApprovalTask $task): void
    {
        if ((int) $task->assignee_id !== (int) $actor->id) {
            throw new AuthorizationException('Only the assigned approver can act on this task.');
        }

        if ((int) $task->original_assignee_id === (int) $task->assignee_id) {
            return;
        }

        $this->assertDelegationStillActive($task, $actor);
    }

    private function assertActiveTask(ApprovalTask $task, int $lockVersion): void
    {
        if ($task->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Approval task has changed. Refresh before trying again.');
        }

        if ($task->status !== ApprovalTaskStatus::Active) {
            throw new ConflictHttpException('Only active approval tasks can be actioned.');
        }
    }

    private function assertDelegationStillActive(ApprovalTask $task, User $actor): void
    {
        $delegationId = data_get($task->metadata, 'delegationId');

        $delegation = ApprovalDelegation::query()
            ->whereKey($delegationId)
            ->where('tenant_id', $task->tenant_id)
            ->where('delegator_id', $task->original_assignee_id)
            ->where('delegate_id', $actor->id)
            ->where('status', ApprovalDelegationStatus::Active)
            ->where('starts_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->exists();

        if (! $delegation) {
            throw new AuthorizationException('This delegated task is no longer actionable.');
        }
    }
}

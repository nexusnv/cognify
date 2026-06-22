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
use Domains\Approval\Services\ApprovalSubjectRegistry;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveApprovalTask
{
    public function __construct(
        private readonly ApprovalSubjectRegistry $subjectRegistry,
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
        private readonly ApprovalSlaCalculator $slaCalculator,
    ) {}

    public function handle(Tenant $tenant, User $actor, ApprovalTask $task, int $lockVersion): ApprovalTask
    {
        return DB::transaction(function () use ($tenant, $actor, $task, $lockVersion): ApprovalTask {
            $instance = $this->lockedInstanceForTask($tenant, $task);
            $task = $this->lockedTask($tenant, $task);
            $this->authorizeAssignee($actor, $task);
            $this->assertActiveTask($task, $lockVersion);
            $this->assertActiveInstance($instance);

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
                    if ($subject instanceof Model) {
                        try {
                            $this->subjectRegistry
                                ->forStoredSubject($task->subject_type)
                                ->onApproved($tenant, $subject, $instance, $actor);
                        } catch (InvalidArgumentException $exception) {
                            Log::warning('Approval task subject handler could not be resolved after approval.', [
                                'approval_task_id' => $task->id,
                                'subject_type' => $task->subject_type,
                                'exception' => $exception->getMessage(),
                            ]);
                        }
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

    private function lockedInstanceForTask(Tenant $tenant, ApprovalTask $task): ApprovalInstance
    {
        $taskSnapshot = ApprovalTask::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($task->id)
            ->firstOrFail();

        return ApprovalInstance::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($taskSnapshot->approval_instance_id)
            ->lockForUpdate()
            ->firstOrFail();
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

        foreach ($tasks as $task) {
            $assignee = $task->assignee;
            if ($assignee === null) {
                continue;
            }

            $subject = $instance->subject;
            $handler = null;
            if ($subject instanceof Model) {
                try {
                    $handler = $this->subjectRegistry->forStoredSubject($instance->subject_type);
                } catch (InvalidArgumentException $exception) {
                    Log::warning('Approval task subject handler could not be resolved for stage activation notification.', [
                        'approval_task_id' => $task->id,
                        'subject_type' => $task->subject_type,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }

            $this->notificationRecorder->record($tenant, collect([$assignee]), new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                title: 'Approval task assigned',
                body: $subject instanceof Model && $handler !== null ? $handler->notificationBody($subject) : 'Approval task',
                href: "/approvals/tasks/{$task->id}",
                subject: $subject,
                subjectLabel: $subject instanceof Model && $handler !== null ? $handler->notificationSubjectLabel($subject) : null,
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

    private function assertActiveInstance(ApprovalInstance $instance): void
    {
        if ($instance->status !== ApprovalInstanceStatus::Active) {
            throw new ConflictHttpException('Approval instance is no longer active.');
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

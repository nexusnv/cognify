<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Actions\Concerns\TerminatesApprovalInstanceTasks;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\Services\ApprovalSubjectRegistry;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RequestApprovalChanges
{
    use TerminatesApprovalInstanceTasks;

    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ApprovalSubjectRegistry $subjectRegistry,
    ) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(Tenant $tenant, User $actor, ApprovalTask $task, int $lockVersion, string $reason, array $requestedFields = []): ApprovalTask
    {
        return DB::transaction(function () use ($tenant, $actor, $task, $lockVersion, $reason, $requestedFields): ApprovalTask {
            $instance = $this->lockedInstanceForTask($tenant, $task);
            $task = ApprovalTask::query()->where('tenant_id', $tenant->id)->whereKey($task->id)->lockForUpdate()->firstOrFail();

            if ((int) $task->assignee_id !== (int) $actor->id) {
                throw new AuthorizationException('Only the assigned approver can act on this task.');
            }

            if ((int) $task->original_assignee_id !== (int) $task->assignee_id) {
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

            if ($task->lock_version !== $lockVersion || $task->status !== ApprovalTaskStatus::Active) {
                throw new ConflictHttpException('Approval task has changed. Refresh before trying again.');
            }

            if ($instance->status !== ApprovalInstanceStatus::Active) {
                throw new ConflictHttpException('Approval instance is no longer active.');
            }

            $task->forceFill([
                'status' => ApprovalTaskStatus::ChangesRequested,
                'decision' => 'changes_requested',
                'decision_reason' => $reason,
                'requested_fields' => $requestedFields,
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $task->stage()->update([
                'status' => ApprovalStageStatus::Completed,
                'completed_at' => now(),
            ]);

            $instance->forceFill([
                'status' => ApprovalInstanceStatus::ChangesRequested,
                'completed_at' => now(),
            ])->save();
            $this->cancelRemainingTasks($instance, $task);

            $subject = $task->subject;
            if ($subject instanceof Model) {
                $this->subjectRegistry
                    ->forStoredSubject($task->subject_type)
                    ->onChangesRequested($tenant, $subject, $instance, $actor, $reason, $requestedFields);
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_task.changes_requested',
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
}

<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalDelegation;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalDelegationStatus;
use Domains\Approval\States\ApprovalInstanceStatus;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Actions\MarkRequisitionRejected;
use Domains\Requisition\Models\Requisition;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectApprovalTask
{
    public function __construct(
        private readonly MarkRequisitionRejected $markRequisitionRejected,
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, ApprovalTask $task, int $lockVersion, string $reason): ApprovalTask
    {
        return DB::transaction(function () use ($tenant, $actor, $task, $lockVersion, $reason): ApprovalTask {
            $task = $this->lockedTask($tenant, $task);
            $this->authorizeAssignee($actor, $task);
            $this->assertActiveTask($task, $lockVersion);

            $task->forceFill([
                'status' => ApprovalTaskStatus::Rejected,
                'decision' => 'rejected',
                'decision_reason' => $reason,
                'decided_by_id' => $actor->id,
                'decided_at' => now(),
                'lock_version' => $task->lock_version + 1,
            ])->save();

            $task->stage()->update([
                'status' => ApprovalStageStatus::Completed,
                'completed_at' => now(),
            ]);
            $instance = $task->instance()->lockForUpdate()->firstOrFail();
            $instance->forceFill([
                'status' => ApprovalInstanceStatus::Rejected,
                'completed_at' => now(),
            ])->save();
            $task->instance->tasks()
                ->where('id', '!=', $task->id)
                ->where('status', ApprovalTaskStatus::Active)
                ->update(['status' => ApprovalTaskStatus::Cancelled]);

            $subject = $task->subject;
            if ($subject instanceof Requisition) {
                $this->markRequisitionRejected->handle($subject, $instance, $actor, $reason);
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'approval_task.rejected',
                subject: $task->subject,
                metadata: ['approvalTaskId' => (string) $task->id],
            ));

            return $task->refresh()->load(['assignee', 'stage', 'instance', 'subject']);
        });
    }

    private function lockedTask(Tenant $tenant, ApprovalTask $task): ApprovalTask
    {
        return ApprovalTask::query()->where('tenant_id', $tenant->id)->whereKey($task->id)->lockForUpdate()->firstOrFail();
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

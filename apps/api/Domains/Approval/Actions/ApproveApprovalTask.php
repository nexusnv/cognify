<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Models\ApprovalTask;
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
            $remainingActiveTasks = $stage->tasks()
                ->where('status', ApprovalTaskStatus::Active)
                ->count();

            if ($remainingActiveTasks === 0) {
                $stage->forceFill([
                    'status' => ApprovalStageStatus::Completed,
                    'completed_at' => now(),
                ])->save();

                $instance = $task->instance()->lockForUpdate()->firstOrFail();
                $instance->forceFill([
                    'status' => ApprovalInstanceStatus::Approved,
                    'completed_at' => now(),
                ])->save();

                $subject = $task->subject;
                if ($subject instanceof Requisition) {
                    $this->markRequisitionApproved->handle($subject, $instance, $actor);
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
}

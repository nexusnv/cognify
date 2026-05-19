<?php

namespace Domains\Approval\Queries;

use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;

class ApprovalSlaSummaryQuery
{
    public function handle(Tenant $tenant): array
    {
        $tasks = ApprovalTask::query()
            ->with(['assignee', 'stage', 'instance'])
            ->where('tenant_id', $tenant->id)
            ->where('status', ApprovalTaskStatus::Active)
            ->get();

        $now = now();
        $dueSoonWindow = $now->copy()->addDays(2);

        $assigned = $tasks->count();
        $dueSoon = $tasks->filter(function (ApprovalTask $task) use ($now, $dueSoonWindow): bool {
            return $task->due_at !== null
                && $task->due_at->greaterThanOrEqualTo($now)
                && $task->due_at->lessThanOrEqualTo($dueSoonWindow);
        })->count();
        $overdue = $tasks->filter(function (ApprovalTask $task) use ($now): bool {
            return $task->due_at !== null && $task->due_at->isPast();
        })->count();
        $escalated = $tasks->filter(function (ApprovalTask $task): bool {
            return data_get($task->metadata, 'escalationKey') !== null || $task->escalated_from_task_id !== null;
        })->count();

        $agedTasks = $tasks->map(function (ApprovalTask $task) use ($now): array {
            $baseline = $task->assigned_at ?? $task->created_at ?? $now;

            return [
                'task' => $task,
                'ageMinutes' => (int) round($baseline->diffInMinutes($now)),
            ];
        })->sortByDesc('ageMinutes')->values();

        $averageAgeMinutes = $assigned > 0
            ? (int) round($agedTasks->avg('ageMinutes'))
            : 0;

        $oldest = $agedTasks->first();
        $oldestPendingApproval = $oldest !== null ? $this->formatTask($oldest['task'], $oldest['ageMinutes']) : null;

        return [
            'assigned' => $assigned,
            'dueSoon' => $dueSoon,
            'overdue' => $overdue,
            'escalated' => $escalated,
            'averageAgeMinutes' => $averageAgeMinutes,
            'oldestPendingApproval' => $oldestPendingApproval,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTask(ApprovalTask $task, int $ageMinutes): array
    {
        return [
            'taskId' => (string) $task->id,
            'approvalInstanceId' => (string) $task->approval_instance_id,
            'approvalStageId' => (string) $task->approval_stage_id,
            'subjectType' => $task->subject_type,
            'subjectId' => (string) $task->subject_id,
            'title' => $task->title,
            'status' => $task->status->value,
            'ageMinutes' => $ageMinutes,
            'assignedAt' => $task->assigned_at?->toISOString(),
            'dueAt' => $task->due_at?->toISOString(),
            'assignee' => $task->assignee ? [
                'id' => (string) $task->assignee->id,
                'name' => $task->assignee->name,
                'email' => $task->assignee->email,
            ] : null,
            'stage' => $task->stage ? [
                'id' => (string) $task->stage->id,
                'name' => $task->stage->name,
                'sequence' => $task->stage->sequence,
            ] : null,
        ];
    }
}

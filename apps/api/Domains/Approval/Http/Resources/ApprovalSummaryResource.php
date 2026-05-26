<?php

namespace Domains\Approval\Http\Resources;

use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\States\ApprovalStageStatus;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalInstance
 */
class ApprovalSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeTasks = $this->tasks->where('status', ApprovalTaskStatus::Active);
        $completedTasks = $this->tasks->reject(fn ($task): bool => $task->status === ApprovalTaskStatus::Active);
        $currentStage = $this->stages->firstWhere('sequence', $this->current_stage_sequence);
        $currentUserTask = $activeTasks->firstWhere('assignee_id', $request->user()?->id);

        return [
            'id' => (string) $this->id,
            'instanceId' => (string) $this->id,
            'status' => $this->status->value,
            'currentStage' => $currentStage !== null ? [
                'id' => (string) $currentStage->id,
                'sequence' => $currentStage->sequence,
                'name' => $currentStage->name,
                'status' => $currentStage->status->value,
                'completionRule' => $currentStage->completion_rule,
                'activatedAt' => $currentStage->activated_at?->toISOString(),
                'completedAt' => $currentStage->completed_at?->toISOString(),
                'dueAt' => $currentStage->due_at?->toISOString(),
                'isOverdue' => $currentStage->due_at !== null && $currentStage->due_at->isPast(),
                'isActionable' => $currentStage->status === ApprovalStageStatus::Active,
            ] : null,
            'activeApprovers' => $activeTasks
                ->filter(fn ($task): bool => $task->assignee !== null)
                ->map(fn ($task): array => [
                    'id' => (string) $task->assignee->id,
                    'name' => $task->assignee->name,
                    'email' => $task->assignee->email,
                    'taskId' => (string) $task->id,
                ])->values()->all(),
            'completedDecisions' => $completedTasks->map(fn ($task): array => [
                'taskId' => (string) $task->id,
                'decision' => $task->decision,
                'reason' => $task->decision_reason,
                'decidedAt' => $task->decided_at?->toISOString(),
                'decidedBy' => $task->decidedBy !== null ? [
                    'id' => (string) $task->decidedBy->id,
                    'name' => $task->decidedBy->name,
                    'email' => $task->decidedBy->email,
                ] : null,
            ])->values()->all(),
            'dueAt' => $currentStage?->due_at?->toISOString(),
            'isOverdue' => $currentStage?->due_at !== null && $currentStage->due_at->isPast(),
            'currentUserTaskId' => $currentUserTask !== null ? (string) $currentUserTask->id : null,
            'startedAt' => $this->started_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
        ];
    }
}

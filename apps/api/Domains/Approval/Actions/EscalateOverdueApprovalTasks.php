<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EscalateOverdueApprovalTasks
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {
    }

    public function handle(Tenant $tenant): int
    {
        $escalated = 0;

        ApprovalTask::query()
            ->with([
                'assignee',
                'originalAssignee',
                'stage.instance.policyVersion',
                'subject.requester',
            ])
            ->where('tenant_id', $tenant->id)
            ->where('status', ApprovalTaskStatus::Active)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->chunkById(50, function ($tasks) use ($tenant, &$escalated): void {
                foreach ($tasks as $task) {
                    if (Arr::get($task->metadata, 'escalationKey') !== null) {
                        continue;
                    }

                    if ($this->escalateTask($tenant, $task)) {
                        $escalated++;
                    }
                }
            });

        return $escalated;
    }

    private function escalateTask(Tenant $tenant, ApprovalTask $task): bool
    {
        return DB::transaction(function () use ($tenant, $task): bool {
            $task = ApprovalTask::query()
                ->with([
                    'assignee',
                    'originalAssignee',
                    'stage.instance.policyVersion',
                    'subject.requester',
                ])
                ->where('tenant_id', $tenant->id)
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($task->status !== ApprovalTaskStatus::Active || $task->due_at === null || $task->due_at->isFuture()) {
                return false;
            }

            if (Arr::get($task->metadata, 'escalationKey') !== null) {
                return false;
            }

            $stage = $task->stage()->with('instance.policyVersion')->lockForUpdate()->firstOrFail();
            $instance = $task->instance()->with('policyVersion')->lockForUpdate()->firstOrFail();
            $policyVersion = $instance->policyVersion;
            $routeTemplate = $policyVersion?->route_template ?? ['stages' => []];
            $stageTemplate = $this->stageTemplateFor($routeTemplate['stages'] ?? [], $stage->name);
            $fallbackAssignee = $this->fallbackAssigneeForTask($tenant, $task, $stageTemplate);
            $now = now();
            $escalationKey = sha1(sprintf('%s|%s|%s', $task->id, $task->approval_stage_id, $task->due_at?->toISOString() ?? ''));
            $escalationMetadata = array_filter([
                ...(array) ($task->metadata ?? []),
                'escalationKey' => $escalationKey,
                'escalatedAt' => $now->toISOString(),
                'escalatedFromTaskId' => (string) $task->id,
                'escalatedFromAssigneeId' => $task->assignee_id !== null ? (string) $task->assignee_id : null,
                'escalatedFromStageId' => (string) $stage->id,
            ], static fn (mixed $value): bool => $value !== null);

            $escalatedTask = null;

            if ($fallbackAssignee instanceof User) {
                $escalatedTask = ApprovalTask::query()->create([
                    'tenant_id' => $tenant->id,
                    'approval_instance_id' => $task->approval_instance_id,
                    'approval_stage_id' => $task->approval_stage_id,
                    'subject_type' => $task->subject_type,
                    'subject_id' => $task->subject_id,
                    'assignee_id' => $fallbackAssignee->id,
                    'original_assignee_id' => $task->original_assignee_id ?? $task->assignee_id,
                    'delegated_from_task_id' => null,
                    'escalated_from_task_id' => $task->id,
                    'title' => $task->title,
                    'status' => ApprovalTaskStatus::Active,
                    'assigned_at' => $now,
                    'due_at' => $task->due_at,
                    'metadata' => array_filter([
                        ...$escalationMetadata,
                        'escalatedToId' => (string) $fallbackAssignee->id,
                        'escalatedTaskId' => null,
                    ], static fn (mixed $value): bool => $value !== null),
                ]);

                $escalationMetadata['escalatedTaskId'] = (string) $escalatedTask->id;
            }

            $task->forceFill([
                'status' => $fallbackAssignee instanceof User ? ApprovalTaskStatus::Cancelled : ApprovalTaskStatus::Active,
                'lock_version' => $task->lock_version + 1,
                'metadata' => array_filter([
                    ...$escalationMetadata,
                    'escalatedTaskId' => $escalatedTask !== null ? (string) $escalatedTask->id : null,
                    'escalatedToId' => $fallbackAssignee?->id !== null ? (string) $fallbackAssignee->id : null,
                    'escalationOutcome' => $fallbackAssignee instanceof User ? 'reassigned' : 'annotated',
                ], static fn (mixed $value): bool => $value !== null),
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: null,
                action: 'approval_task.escalated',
                subject: $task->subject,
                metadata: [
                    'approvalTaskId' => (string) $task->id,
                    'escalationKey' => $escalationKey,
                    'escalatedTaskId' => $escalatedTask !== null ? (string) $escalatedTask->id : null,
                    'escalatedToId' => $fallbackAssignee?->id !== null ? (string) $fallbackAssignee->id : null,
                ],
            ));

            if ($fallbackAssignee instanceof User) {
                $subject = $task->subject;

                if ($subject instanceof Requisition) {
                    $this->notificationRecorder->record($tenant, [$fallbackAssignee], new NotificationData(
                        type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                        title: 'Approval task escalated',
                        body: $subject->title,
                        href: "/approvals/tasks/{$escalatedTask->id}",
                        subject: $subject,
                        subjectLabel: $subject->number,
                        metadata: [
                            'approvalTaskId' => (string) $task->id,
                            'escalatedTaskId' => (string) $escalatedTask->id,
                            'escalationKey' => $escalationKey,
                        ],
                    ));
                }
            }

            return true;
        });
    }

    /**
     * @param array<int, mixed> $stages
     * @return array<string, mixed>
     */
    private function stageTemplateFor(array $stages, string $stageName): array
    {
        foreach ($stages as $stage) {
            if ((string) ($stage['name'] ?? '') === $stageName) {
                return is_array($stage) ? $stage : [];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed> $stageTemplate
     */
    private function fallbackAssigneeForTask(Tenant $tenant, ApprovalTask $task, array $stageTemplate): ?User
    {
        $fallbackApprovers = collect($stageTemplate['fallbackApprovers'] ?? []);

        if ($fallbackApprovers->isEmpty()) {
            return $this->usersForRole($tenant, 'buyer')
                ->merge($this->usersForRole($tenant, 'admin'))
                ->unique('id')
                ->values()
                ->first();
        }

        $candidates = $fallbackApprovers
            ->flatMap(function (mixed $approver) use ($tenant): Collection {
                if (! is_array($approver)) {
                    return collect();
                }

                if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                    $user = $tenant->users()->whereKey((int) $approver['userId'])->first();
                    return $user instanceof User ? collect([$user]) : collect();
                }

                if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                    return $this->usersForRole($tenant, (string) $approver['role']);
                }

                return collect();
            })
            ->unique('id')
            ->values();

        return $candidates->first();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForRole(Tenant $tenant, string $role): Collection
    {
        return $tenant->users()
            ->wherePivot('role', $role)
            ->orderBy('users.id')
            ->get();
    }
}

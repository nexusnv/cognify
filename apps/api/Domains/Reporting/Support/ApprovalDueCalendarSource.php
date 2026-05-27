<?php

namespace Domains\Reporting\Support;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ApprovalDueCalendarSource implements ProcurementCalendarSource
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function sourceType(): string
    {
        return 'approvalDue';
    }

    public function availableSource(): array
    {
        return [
            'sourceType' => $this->sourceType(),
            'label' => 'Approval due',
            'available' => true,
        ];
    }

    public function listEvents(Tenant $tenant, User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $role = $this->currentTenant->roleFor($actor);

        if (! in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value, TenantRole::Approver->value], true)) {
            return collect();
        }

        return ApprovalTask::query()
            ->select(['id', 'tenant_id', 'subject_type', 'subject_id', 'assignee_id', 'title', 'status', 'due_at'])
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from, $to])
            ->when(
                $role === TenantRole::Approver->value,
                fn (Builder $query) => $query->where('assignee_id', $actor->id),
            )
            ->get()
            ->map(function (ApprovalTask $task): ProcurementCalendarEvent {
                $dueAt = CarbonImmutable::instance($task->due_at);

                return new ProcurementCalendarEvent(
                    id: sprintf('approval-task:%s:due_at', (string) $task->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $task->id,
                    sourceLabel: 'Approval due',
                    title: (string) ($task->title ?: 'Approval due'),
                    description: null,
                    startsAt: $dueAt,
                    endsAt: null,
                    allDay: false,
                    status: $this->statusFor($task, $dueAt),
                    priority: 'high',
                    record: [
                        'type' => 'approvalTask',
                        'id' => (string) $task->id,
                        'label' => (string) ($task->title ?: 'Approval task'),
                        'href' => sprintf('/approvals/tasks/%s', (string) $task->id),
                    ],
                    context: [
                        'subjectType' => $task->subject_type,
                        'subjectId' => (string) $task->subject_id,
                    ],
                );
            })
            ->values();
    }

    private function statusFor(ApprovalTask $task, CarbonImmutable $date): string
    {
        if ($task->status !== ApprovalTaskStatus::Active) {
            return 'completed';
        }

        if ($date->isPast()) {
            return 'overdue';
        }

        if ($date->lessThanOrEqualTo(CarbonImmutable::now()->addDays(7))) {
            return 'dueSoon';
        }

        return 'scheduled';
    }
}

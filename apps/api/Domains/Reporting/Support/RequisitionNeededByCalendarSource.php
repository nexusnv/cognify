<?php

namespace Domains\Reporting\Support;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class RequisitionNeededByCalendarSource implements ProcurementCalendarSource
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function sourceType(): string
    {
        return 'requisitionNeededBy';
    }

    public function availableSource(): array
    {
        return [
            'sourceType' => $this->sourceType(),
            'label' => 'Requisition needed by',
            'available' => true,
        ];
    }

    public function listEvents(Tenant $tenant, User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $role = $this->currentTenant->roleFor($actor);

        if ($role === null) {
            return collect();
        }

        return Requisition::query()
            ->select(['id', 'tenant_id', 'requester_id', 'number', 'title', 'needed_by_date', 'status', 'department', 'cost_center'])
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('needed_by_date')
            ->whereBetween('needed_by_date', [$from->toDateString(), $to->toDateString()])
            ->when(
                $role === TenantRole::Requester->value,
                fn (Builder $query) => $query->where('requester_id', $actor->id),
            )
            ->get()
            ->map(function (Requisition $requisition): ProcurementCalendarEvent {
                $neededBy = CarbonImmutable::instance($requisition->needed_by_date)->startOfDay();

                return new ProcurementCalendarEvent(
                    id: sprintf('requisition:%s:needed_by', (string) $requisition->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $requisition->id,
                    sourceLabel: 'Requisition needed by',
                    title: (string) ($requisition->title ?: $requisition->number ?: 'Requisition needed by'),
                    description: $requisition->number ? sprintf('Requisition %s needed-by date', $requisition->number) : null,
                    startsAt: $neededBy,
                    endsAt: null,
                    allDay: true,
                    status: $this->statusFor($requisition, $neededBy),
                    priority: 'normal',
                    record: [
                        'type' => 'requisition',
                        'id' => (string) $requisition->id,
                        'label' => (string) ($requisition->title ?: $requisition->number ?: 'Requisition'),
                        'href' => sprintf('/requisitions/%s', (string) $requisition->id),
                    ],
                    context: array_filter([
                        'number' => $requisition->number,
                        'department' => $requisition->department,
                        'costCenter' => $requisition->cost_center,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                );
            })
            ->values();
    }

    private function statusFor(Requisition $requisition, CarbonImmutable $date): string
    {
        if (in_array($requisition->status, [
            RequisitionStatus::Approved,
            RequisitionStatus::Rejected,
            RequisitionStatus::Withdrawn,
            RequisitionStatus::Cancelled,
        ], true)) {
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

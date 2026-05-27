<?php

namespace Domains\Reporting\Support;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Collection;

final class PurchaseOrderHandoffCalendarSource implements ProcurementCalendarSource
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function sourceType(): string
    {
        return 'poHandoff';
    }

    public function availableSource(): array
    {
        return [
            'sourceType' => $this->sourceType(),
            'label' => 'PO handoffs',
            'available' => true,
        ];
    }

    public function listEvents(Tenant $tenant, User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if (! in_array($this->currentTenant->roleFor($actor), [
            TenantRole::Buyer->value,
            TenantRole::Admin->value,
        ], true)) {
            return collect();
        }

        return PurchaseOrderRequestHandoff::query()
            ->select(['id', 'tenant_id', 'rfq_id', 'number', 'status', 'requested_po_date'])
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('requested_po_date')
            ->whereBetween('requested_po_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->map(function (PurchaseOrderRequestHandoff $handoff): ProcurementCalendarEvent {
                $requestedDate = CarbonImmutable::instance($handoff->requested_po_date)->startOfDay();

                return new ProcurementCalendarEvent(
                    id: sprintf('po-handoff:%s:requested_po_date', (string) $handoff->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $handoff->id,
                    sourceLabel: 'PO handoffs',
                    title: sprintf('PO handoff %s', (string) $handoff->number),
                    description: null,
                    startsAt: $requestedDate,
                    endsAt: null,
                    allDay: true,
                    status: $this->statusFor($handoff, $requestedDate),
                    priority: 'normal',
                    record: [
                        'type' => 'poHandoff',
                        'id' => (string) $handoff->id,
                        'label' => (string) $handoff->number,
                        'href' => sprintf('/quotations/awards/%s', (string) $handoff->rfq_id),
                    ],
                    context: [
                        'number' => (string) $handoff->number,
                        'rfqId' => (string) $handoff->rfq_id,
                    ],
                );
            })
            ->values();
    }

    private function statusFor(PurchaseOrderRequestHandoff $handoff, CarbonImmutable $date): string
    {
        if ($handoff->status === PurchaseOrderRequestHandoffStatus::Cancelled) {
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

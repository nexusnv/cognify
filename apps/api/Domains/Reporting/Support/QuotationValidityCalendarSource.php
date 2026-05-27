<?php

namespace Domains\Reporting\Support;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class QuotationValidityCalendarSource implements ProcurementCalendarSource
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function sourceType(): string
    {
        return 'quotationValidity';
    }

    public function availableSource(): array
    {
        return [
            'sourceType' => $this->sourceType(),
            'label' => 'Quotation validity',
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

        return Quotation::query()
            ->select(['id', 'tenant_id', 'rfq_id', 'current_version_id', 'number', 'valid_until'])
            ->with(['currentVersion:id,quotation_id,valid_until'])
            ->where('tenant_id', $tenant->id)
            ->where(function (Builder $query) use ($from, $to): void {
                $query
                    ->whereHas('currentVersion', function (Builder $versionQuery) use ($from, $to): void {
                        $versionQuery->whereNotNull('valid_until')
                            ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()]);
                    })
                    ->orWhere(function (Builder $quotationQuery) use ($from, $to): void {
                        $quotationQuery->whereNotNull('valid_until')
                            ->whereBetween('valid_until', [$from->toDateString(), $to->toDateString()]);
                    });
            })
            ->get()
            ->map(function (Quotation $quotation): ?ProcurementCalendarEvent {
                $date = $this->validUntilFor($quotation);

                if ($date === null) {
                    return null;
                }

                $startsAt = $date->startOfDay();

                return new ProcurementCalendarEvent(
                    id: sprintf('quotation:%s:valid_until', (string) $quotation->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $quotation->id,
                    sourceLabel: 'Quotation validity',
                    title: $quotation->number ? sprintf('Quotation %s validity', $quotation->number) : 'Quotation validity',
                    description: null,
                    startsAt: $startsAt,
                    endsAt: null,
                    allDay: true,
                    status: 'informational',
                    priority: 'low',
                    record: [
                        'type' => 'quotation',
                        'id' => (string) $quotation->id,
                        'label' => (string) ($quotation->number ?: 'Quotation'),
                        'href' => sprintf('/quotations/comparisons/%s', (string) $quotation->rfq_id),
                    ],
                    context: array_filter([
                        'number' => $quotation->number,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                );
            })
            ->filter()
            ->values();
    }

    private function validUntilFor(Quotation $quotation): ?CarbonImmutable
    {
        $date = $quotation->currentVersion instanceof QuotationVersion && $quotation->currentVersion->valid_until !== null
            ? $quotation->currentVersion->valid_until
            : $quotation->valid_until;

        if ($date === null) {
            return null;
        }

        return CarbonImmutable::instance($date);
    }
}

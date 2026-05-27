<?php

namespace Domains\Search\Providers;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseOrderRequestHandoffSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'po_handoff';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        if (! $this->canSearch($tenant, $user)) {
            return collect();
        }

        $normalizedQuery = mb_strtolower(trim($query));

        $builder = PurchaseOrderRequestHandoff::query()
            ->with(['rfq', 'vendor', 'quotation'])
            ->where('tenant_id', $tenant->id);

        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (PurchaseOrderRequestHandoff $handoff): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $handoff->id,
                title: $handoff->number,
                subtitle: $handoff->vendor?->name
                    ?? data_get($handoff->source_snapshot, 'vendor.name')
                    ?? $handoff->rfq?->number,
                status: $handoff->statusState()->value,
                href: "/quotations/awards/{$handoff->rfq_id}",
                updatedAt: $handoff->updated_at?->toISOString(),
            ));
    }

    private function canSearch(Tenant $tenant, User $user): bool
    {
        return in_array($tenant->roleFor($user), [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query.'%'])
                ->orWhereRaw('lower(number) like ?', ['%'.$query.'%'])
                ->orWhereRaw('lower(status) like ?', ['%'.$query.'%'])
                ->orWhereHas('rfq', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%'.$query.'%'])
                        ->orWhereRaw('lower(title) like ?', ['%'.$query.'%']);
                })
                ->orWhereHas('vendor', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(name) like ?', ['%'.$query.'%']);
                })
                ->orWhereHas('quotation', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%'.$query.'%']);
                });
        });
    }

    private function applyOrdering(Builder $builder, string $query): void
    {
        $builder->orderByRaw(
            'CASE
                WHEN lower(number) = ? THEN 0
                WHEN lower(number) LIKE ? THEN 1
                WHEN lower(status) LIKE ? THEN 2
                ELSE 3
            END',
            [
                $query,
                $query.'%',
                '%'.$query.'%',
            ],
        )->orderByDesc('updated_at');
    }
}

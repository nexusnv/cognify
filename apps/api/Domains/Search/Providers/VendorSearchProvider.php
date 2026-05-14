<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class VendorSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'vendor';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = Vendor::query()
            ->where('tenant_id', $tenant->id);

        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Vendor $vendor): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $vendor->id,
                title: $vendor->name,
                subtitle: $vendor->category,
                status: $vendor->status,
                href: '/system',
                updatedAt: $vendor->updated_at?->toISOString(),
            ));
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(name) = ?', [$query])
                ->orWhereRaw('lower(name) like ?', [$query . '%'])
                ->orWhereRaw('lower(name) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(category) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(risk_rating) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(status) like ?', ['%' . $query . '%']);
        });
    }

    private function applyOrdering(Builder $builder, string $query): void
    {
        $builder->orderByRaw(
            'CASE
                WHEN lower(name) = ? THEN 0
                WHEN lower(name) LIKE ? THEN 1
                WHEN lower(category) LIKE ? THEN 2
                WHEN lower(risk_rating) LIKE ? THEN 3
                ELSE 4
            END',
            [
                $query,
                $query . '%',
                '%' . $query . '%',
                '%' . $query . '%',
            ],
        )->orderByDesc('updated_at');
    }
}

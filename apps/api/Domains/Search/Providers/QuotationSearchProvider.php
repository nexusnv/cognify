<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuotationSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'quotation';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = Quotation::query()
            ->with(['rfq.project', 'vendor'])
            ->where('tenant_id', $tenant->id);

        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Quotation $quotation): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $quotation->id,
                title: $quotation->number,
                subtitle: $quotation->vendor?->name,
                status: $quotation->status,
                href: '/system',
                updatedAt: $quotation->updated_at?->toISOString(),
            ));
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(status) like ?', ['%' . $query . '%'])
                ->orWhereHas('vendor', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(name) like ?', ['%' . $query . '%']);
                })
                ->orWhereHas('rfq', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%' . $query . '%'])
                        ->orWhereRaw('lower(title) like ?', ['%' . $query . '%']);
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
                $query . '%',
                '%' . $query . '%',
            ],
        )->orderByDesc('updated_at');
    }
}

<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RfqSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'rfq';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = Rfq::query()
            ->with(['project', 'requisition'])
            ->where('tenant_id', $tenant->id);

        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Rfq $rfq): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $rfq->id,
                title: $rfq->title,
                subtitle: $rfq->number,
                status: $rfq->status,
                href: $rfq->requisition_id ? "/requisitions/{$rfq->requisition_id}" : '/system',
                updatedAt: $rfq->updated_at?->toISOString(),
            ));
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(title) like ?', [$query . '%'])
                ->orWhereRaw('lower(title) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(status) like ?', ['%' . $query . '%'])
                ->orWhereHas('project', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%' . $query . '%'])
                        ->orWhereRaw('lower(name) like ?', ['%' . $query . '%']);
                })
                ->orWhereHas('requisition', function (Builder $builder) use ($query): void {
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
                WHEN lower(title) LIKE ? THEN 2
                WHEN lower(title) LIKE ? THEN 3
                ELSE 4
            END',
            [
                $query,
                $query . '%',
                $query . '%',
                '%' . $query . '%',
            ],
        )->orderByDesc('updated_at');
    }
}

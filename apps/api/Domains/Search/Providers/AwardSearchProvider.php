<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Award\Models\Award;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AwardSearchProvider implements SearchProvider
{
    use AppliesActorSearchVisibility;

    public function type(): string
    {
        return 'award';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = Award::query()
            ->with(['project', 'rfq', 'quotation', 'vendor'])
            ->where('tenant_id', $tenant->id);

        $this->applyAwardVisibility($builder, $tenant, $user);
        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Award $award): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $award->id,
                title: $award->number,
                subtitle: $award->vendor?->name ?? $award->project?->number,
                status: $award->status,
                href: $award->rfq?->requisition_id ? "/requisitions/{$award->rfq->requisition_id}" : '/system',
                updatedAt: $award->updated_at?->toISOString(),
            ));
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(status) like ?', ['%' . $query . '%'])
                ->orWhereHas('project', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%' . $query . '%'])
                        ->orWhereRaw('lower(name) like ?', ['%' . $query . '%']);
                })
                ->orWhereHas('rfq', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%' . $query . '%'])
                        ->orWhereRaw('lower(title) like ?', ['%' . $query . '%']);
                })
                ->orWhereHas('quotation', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(number) like ?', ['%' . $query . '%']);
                })
                ->orWhereHas('vendor', function (Builder $builder) use ($query): void {
                    $builder->whereRaw('lower(name) like ?', ['%' . $query . '%']);
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

<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProcurementProjectSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'project';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = ProcurementProject::query()
            ->with('owner')
            ->where('tenant_id', $tenant->id);

        $this->applyVisibility($builder, $user, $tenant);
        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (ProcurementProject $project): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $project->id,
                title: $project->name,
                subtitle: $project->number,
                status: $project->status?->value ?? (string) $project->status,
                href: "/projects/{$project->id}",
                updatedAt: $project->updated_at?->toISOString(),
            ));
    }

    private function applyVisibility(Builder $builder, User $user, Tenant $tenant): void
    {
        $builder->visibleTo($user, $tenant->roleFor($user), $tenant->id);
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(name) like ?', [$query . '%'])
                ->orWhereRaw('lower(name) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(status) like ?', ['%' . $query . '%'])
                ->orWhereHas('owner', function (Builder $builder) use ($query): void {
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
                WHEN lower(name) LIKE ? THEN 2
                WHEN lower(name) LIKE ? THEN 3
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

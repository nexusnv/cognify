<?php

namespace Domains\Search\Providers;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RequisitionSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'requisition';
    }

    /**
     * @return Collection<int, SearchResultData>
     */
    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        $builder = Requisition::query()
            ->with('requester')
            ->where('tenant_id', $tenant->id);

        $this->applyVisibility($builder, $user);
        $this->applySearchConstraint($builder, $normalizedQuery);
        $this->applyOrdering($builder, $normalizedQuery);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Requisition $requisition): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $requisition->id,
                title: $requisition->title,
                subtitle: $requisition->number,
                status: $requisition->status->value,
                href: "/requisitions/{$requisition->id}",
                updatedAt: $requisition->updated_at?->toISOString(),
            ));
    }

    private function applyVisibility(Builder $builder, User $user): void
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        if ($role === TenantRole::Buyer->value || $role === TenantRole::Approver->value) {
            $builder->where('status', RequisitionStatus::Submitted);

            return;
        }

        if ($role !== TenantRole::Admin->value) {
            $builder->where('requester_id', $user->id);
        }
    }

    private function applySearchConstraint(Builder $builder, string $query): void
    {
        $builder->where(function (Builder $builder) use ($query): void {
            $builder->whereRaw('lower(number) = ?', [$query])
                ->orWhereRaw('lower(number) like ?', [$query . '%'])
                ->orWhereRaw('lower(number) like ?', ['%' . $query . '%'])
                ->orWhereRaw('lower(title) like ?', [$query . '%'])
                ->orWhereRaw('lower(title) like ?', ['%' . $query . '%'])
                ->orWhereHas('requester', function (Builder $builder) use ($query): void {
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

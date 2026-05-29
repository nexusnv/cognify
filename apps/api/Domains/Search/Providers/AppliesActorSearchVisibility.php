<?php

namespace Domains\Search\Providers;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait AppliesActorSearchVisibility
{
    private function applyRfqVisibility(Builder $builder, Tenant $tenant, User $user): void
    {
        if ($this->canSearchAllPreviewRecords($tenant, $user)) {
            return;
        }

        $role = $tenant->roleFor($user);

        $builder->where(function (Builder $query) use ($tenant, $user, $role): void {
            $query->whereHas('requisition', function (Builder $query) use ($tenant, $user, $role): void {
                $query->visibleTo($user, $role, $tenant->id);
            })->orWhereHas('project', function (Builder $query) use ($tenant, $user, $role): void {
                $query->visibleTo($user, $role, $tenant->id);
            });
        });
    }

    private function applyQuotationVisibility(Builder $builder, Tenant $tenant, User $user): void
    {
        if ($this->canSearchAllPreviewRecords($tenant, $user)) {
            return;
        }

        $builder->whereHas('rfq', function (Builder $query) use ($tenant, $user): void {
            $this->applyRfqVisibility($query, $tenant, $user);
        });
    }

    private function applyAwardVisibility(Builder $builder, Tenant $tenant, User $user): void
    {
        if ($this->canSearchAllPreviewRecords($tenant, $user)) {
            return;
        }

        $role = $tenant->roleFor($user);

        $builder->where(function (Builder $query) use ($tenant, $user, $role): void {
            $query->whereHas('rfq', function (Builder $query) use ($tenant, $user): void {
                $this->applyRfqVisibility($query, $tenant, $user);
            })->orWhereHas('project', function (Builder $query) use ($tenant, $user, $role): void {
                $query->visibleTo($user, $role, $tenant->id);
            })->orWhereHas('quotation.rfq', function (Builder $query) use ($tenant, $user): void {
                $this->applyRfqVisibility($query, $tenant, $user);
            });
        });
    }

    private function applyVendorVisibility(Builder $builder, Tenant $tenant, User $user): void
    {
        if ($this->canSearchAllPreviewRecords($tenant, $user)) {
            return;
        }

        $builder->whereHas('quotations.rfq', function (Builder $query) use ($tenant, $user): void {
            $this->applyRfqVisibility($query, $tenant, $user);
        });
    }

    private function canSearchAllPreviewRecords(Tenant $tenant, User $user): bool
    {
        return in_array($tenant->roleFor($user), [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }
}

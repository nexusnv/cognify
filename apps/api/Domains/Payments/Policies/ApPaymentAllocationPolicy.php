<?php

namespace Domains\Payments\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Payments\Models\ApPaymentAllocation;

class ApPaymentAllocationPolicy
{
    public function view(User $user, ApPaymentAllocation $allocation): bool
    {
        return $this->isTenantScoped($allocation->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(int|string $tenantId): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $tenant->id === (int) $tenantId;
    }
}

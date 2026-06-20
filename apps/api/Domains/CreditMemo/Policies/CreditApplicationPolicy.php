<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\CreditApplication;

class CreditApplicationPolicy
{
    public function view(User $user, CreditApplication $application): bool
    {
        return $this->isTenantScoped($application->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function void(User $user, CreditApplication $application): bool
    {
        return $this->isTenantScoped($application->tenant_id)
            && $this->buyerOrAdmin($user)
            && $application->voided_at === null;
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

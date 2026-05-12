<?php

namespace App\Audit\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;

class AuditEventPolicy
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    public function viewAny(User $user): bool
    {
        $role = $this->currentTenant->roleFor($user);

        return in_array($role, [
            TenantRole::Buyer->value,
            TenantRole::Approver->value,
            TenantRole::Admin->value,
        ], true);
    }
}

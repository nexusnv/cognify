<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;

class RfqPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function view(User $user, Rfq $rfq): bool
    {
        return $this->isTenantScoped($user, $rfq) && $this->canManageSourcing($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageSourcing($user);
    }

    public function update(User $user, Rfq $rfq): bool
    {
        return $this->isTenantScoped($user, $rfq)
            && $this->canManageSourcing($user)
            && $rfq->isEditable();
    }

    public function cancel(User $user, Rfq $rfq): bool
    {
        return $this->isTenantScoped($user, $rfq)
            && $this->canManageSourcing($user)
            && $rfq->isEditable();
    }

    private function canManageSourcing(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(User $user, Rfq $rfq): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $rfq->tenant_id === (int) $tenant->id;
    }
}

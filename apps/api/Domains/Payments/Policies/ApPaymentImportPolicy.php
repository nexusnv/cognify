<?php

namespace Domains\Payments\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Payments\Models\ApPaymentImport;

class ApPaymentImportPolicy
{
    public function view(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function upload(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function update(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function reconcile(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function discard(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
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

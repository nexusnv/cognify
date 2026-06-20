<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\SupplierCreditMemoException;

class SupplierCreditMemoExceptionPolicy
{
    public function view(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function acknowledge(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->acknowledged_at === null;
    }

    public function resolve(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->resolved_at === null;
    }

    public function escalate(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->escalated_at === null;
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

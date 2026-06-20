<?php

namespace Domains\AccountsPayable\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;

class ApPaymentHandoffPolicy
{
    public function view(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function update(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function markReady(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function cancel(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function export(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function refresh(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function removeInvoice(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function schedule(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function addAllocation(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markPaid(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function closeWithVariance(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markFailed(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function void(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function reschedule(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
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

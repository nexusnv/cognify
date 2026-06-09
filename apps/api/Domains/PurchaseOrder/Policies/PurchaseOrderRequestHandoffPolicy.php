<?php

namespace Domains\PurchaseOrder\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;

class PurchaseOrderRequestHandoffPolicy
{
    public function view(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff) && $this->buyerOrAdmin($user);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        $currentTenant = app(CurrentTenant::class)->nullable();

        return $currentTenant !== null
            && (int) $currentTenant->id === (int) $tenant->id
            && $this->buyerOrAdmin($user);
    }

    public function update(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->view($user, $handoff);
    }

    public function markReady(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $handoff->statusState() === PurchaseOrderRequestHandoffStatus::Draft
            && $this->view($user, $handoff);
    }

    public function export(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->view($user, $handoff);
    }

    public function cancel(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->view($user, $handoff);
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(PurchaseOrderRequestHandoff $handoff): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $handoff->tenant_id === (int) $tenant->id;
    }
}

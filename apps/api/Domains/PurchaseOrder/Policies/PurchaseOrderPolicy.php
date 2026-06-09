<?php

namespace Domains\PurchaseOrder\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantScoped($purchaseOrder->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function createFromHandoff(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function markReadyForReview(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function submitApproval(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
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

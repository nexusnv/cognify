<?php

namespace Domains\Receiving\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Receiving\Models\GoodsReceipt;

class GoodsReceiptPolicy
{
    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->isTenantScoped($goodsReceipt->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function confirmRequester(User $user, GoodsReceipt $goodsReceipt): bool
    {
        if (! $this->isTenantScoped($goodsReceipt->tenant_id)) {
            return false;
        }

        $po = $goodsReceipt->purchaseOrder;

        if ($po !== null && $po->requisition_id !== null) {
            $requisition = $po->requisition;

            if ($requisition !== null && $requisition->requester_id === $user->id) {
                return true;
            }
        }

        return $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function confirmBuyer(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $this->isTenantScoped($goodsReceipt->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    private function isTenantScoped(int|string $tenantId): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $tenant->id === (int) $tenantId;
    }

    private function roleIs(User $user, TenantRole ...$roles): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, array_map(fn (TenantRole $r) => $r->value, $roles), true);
    }
}

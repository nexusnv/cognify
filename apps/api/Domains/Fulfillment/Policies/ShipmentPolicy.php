<?php

namespace Domains\Fulfillment\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Fulfillment\Models\Shipment;
use Domains\PurchaseOrder\Models\PurchaseOrder;

class ShipmentPolicy
{
    public function view(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
            && ($this->roleIs($user, TenantRole::Buyer, TenantRole::Admin)
                || $this->isRequesterForShipment($user, $shipment));
    }

    public function updateShipment(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function addTrackingEvent(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function updateBackorder(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
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

    private function isRequesterForShipment(User $user, Shipment $shipment): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);
        if ($role !== TenantRole::Requester->value) {
            return false;
        }

        $purchaseOrder = $shipment->purchaseOrder;
        if (! $purchaseOrder instanceof PurchaseOrder || $purchaseOrder->requisition_id === null) {
            return false;
        }

        return (int) ($purchaseOrder->requisition?->requester_id) === (int) $user->id;
    }
}

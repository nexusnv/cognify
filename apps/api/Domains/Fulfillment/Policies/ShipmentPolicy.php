<?php

namespace Domains\Fulfillment\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Fulfillment\Models\Shipment;

class ShipmentPolicy
{
    public function view(User $user, Shipment $shipment): bool
    {
        return $this->isTenantScoped($shipment->tenant_id)
            && $this->roleIs($user, TenantRole::Buyer, TenantRole::Admin);
    }

    public function updateShipment(User $user, Shipment $shipment): bool
    {
        return $this->view($user, $shipment);
    }

    public function addTrackingEvent(User $user, Shipment $shipment): bool
    {
        return $this->view($user, $shipment);
    }

    public function updateBackorder(User $user, Shipment $shipment): bool
    {
        return $this->view($user, $shipment);
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $this->view($user, $shipment);
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

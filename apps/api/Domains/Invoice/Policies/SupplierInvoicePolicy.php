<?php

namespace Domains\Invoice\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Invoice\Models\SupplierInvoice;

class SupplierInvoicePolicy
{
    public function view(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function upload(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function review(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function submitForApproval(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function placeHold(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function releaseHold(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
    }

    public function retryPaymentInduction(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $this->isTenantScoped($supplierInvoice->tenant_id)
            && $this->buyerOrAdmin($user);
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

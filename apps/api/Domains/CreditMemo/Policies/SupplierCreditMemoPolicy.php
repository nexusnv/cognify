<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;

class SupplierCreditMemoPolicy
{
    public function view(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function update(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Draft;
    }

    public function submit(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Draft;
    }

    public function post(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Approved;
    }

    public function apply(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState()->canAcceptCreditApplications();
    }

    public function void(User $user, SupplierCreditMemo $creditMemo): bool
    {
        $voidable = [
            SupplierCreditMemoStatus::Draft,
            SupplierCreditMemoStatus::PendingApproval,
            SupplierCreditMemoStatus::Approved,
            SupplierCreditMemoStatus::Open,
            SupplierCreditMemoStatus::PartiallyApplied,
        ];

        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && in_array($creditMemo->statusState(), $voidable, true);
    }

    public function voidApplication(User $user, SupplierCreditMemo $creditMemo): bool
    {
        $applicationsPresent = [
            SupplierCreditMemoStatus::Open,
            SupplierCreditMemoStatus::PartiallyApplied,
            SupplierCreditMemoStatus::FullyApplied,
        ];

        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && in_array($creditMemo->statusState(), $applicationsPresent, true);
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

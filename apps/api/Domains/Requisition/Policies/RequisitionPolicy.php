<?php

namespace Domains\Requisition\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class RequisitionPolicy
{
    public function create(User $user): bool
    {
        return app(CurrentTenant::class)->userIsMember($user);
    }

    public function viewAny(User $user): bool
    {
        return app(CurrentTenant::class)->userIsMember($user);
    }

    public function view(User $user, Requisition $requisition): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        if ($role === TenantRole::Admin->value) {
            return true;
        }

        if ($requisition->requester_id === $user->id) {
            return true;
        }

        if (in_array($role, [TenantRole::Buyer->value, TenantRole::Approver->value], true)) {
            return $requisition->status === RequisitionStatus::Submitted;
        }

        return false;
    }

    public function update(User $user, Requisition $requisition): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return $role === TenantRole::Admin->value || $requisition->requester_id === $user->id;
    }

    public function submit(User $user, Requisition $requisition): bool
    {
        return $this->update($user, $requisition);
    }
}

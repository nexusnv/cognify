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

    public function requestChanges(User $user, Requisition $requisition): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return $this->view($user, $requisition)
            && in_array($role, [TenantRole::Buyer->value, TenantRole::Approver->value, TenantRole::Admin->value], true)
            && $requisition->status === RequisitionStatus::Submitted;
    }

    public function resubmit(User $user, Requisition $requisition): bool
    {
        return $requisition->requester_id === $user->id || app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value;
    }

    public function withdraw(User $user, Requisition $requisition): bool
    {
        return $requisition->requester_id === $user->id || app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value;
    }

    public function cancel(User $user, Requisition $requisition): bool
    {
        return app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value;
    }

    public function comment(User $user, Requisition $requisition): bool
    {
        return $this->view($user, $requisition)
            && ! in_array($requisition->status, [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled], true);
    }

    public function mention(User $user, Requisition $requisition): bool
    {
        return $this->comment($user, $requisition);
    }
}

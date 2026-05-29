<?php

namespace Domains\Approval\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Approval\Models\ApprovalTask;

class ApprovalTaskPolicy
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function view(User $user, ApprovalTask $task): bool
    {
        $role = $this->currentTenant->roleFor($user);

        if (in_array($role, [TenantRole::Admin->value, TenantRole::Buyer->value], true)) {
            return true;
        }

        return (int) $task->assignee_id === (int) $user->id
            || (int) $task->original_assignee_id === (int) $user->id;
    }
}

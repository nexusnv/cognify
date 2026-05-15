<?php

namespace Domains\Project\Policies;

use App\Models\User;
use Domains\Project\Models\ProcurementProject;

class ProcurementProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProcurementProject $project): bool
    {
        return $user->tenants()->whereKey($project->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, ['buyer', 'admin']);
    }

    public function update(User $user, ProcurementProject $project): bool
    {
        return $this->hasRole($user, ['buyer', 'admin']) || (int) $project->owner_id === (int) $user->id;
    }

    public function transition(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function cancel(User $user, ProcurementProject $project): bool
    {
        return ! $project->status->isTerminal() && $this->hasRole($user, ['buyer', 'admin']);
    }

    public function linkRequisitions(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function unlinkRequisitions(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    private function hasRole(User $user, array $roles): bool
    {
        return $user->tenants()->wherePivotIn('role', $roles)->exists();
    }
}

<?php

namespace Domains\Project\Policies;

use App\Auth\TenantRole;
use App\Tenancy\CurrentTenant;
use App\Models\User;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class ProcurementProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return app(CurrentTenant::class)->userIsMember($user);
    }

    public function view(User $user, ProcurementProject $project): bool
    {
        $currentTenant = app(CurrentTenant::class);

        if (! $this->belongsToCurrentTenant($project, $currentTenant)) {
            return false;
        }

        $role = $currentTenant->roleFor($user);

        if ($role === null) {
            return false;
        }

        if (in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true)) {
            return true;
        }

        if ((int) $project->owner_id === (int) $user->id) {
            return true;
        }

        return $this->hasVisibleLinkedRequisition($user, $project);
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, ['buyer', 'admin']);
    }

    public function update(User $user, ProcurementProject $project): bool
    {
        if (! $this->belongsToCurrentTenant($project, app(CurrentTenant::class))) {
            return false;
        }

        return $this->hasRole($user, ['buyer', 'admin']) || (int) $project->owner_id === (int) $user->id;
    }

    public function transition(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function cancel(User $user, ProcurementProject $project): bool
    {
        return ! $project->status->isTerminal()
            && $this->hasRole($user, ['buyer', 'admin'])
            && $this->update($user, $project);
    }

    public function linkRequisitions(User $user, ProcurementProject $project): bool
    {
        return ! $project->status->isTerminal() && $this->update($user, $project);
    }

    public function unlinkRequisitions(User $user, ProcurementProject $project): bool
    {
        return ! $project->status->isTerminal() && $this->update($user, $project);
    }

    private function belongsToCurrentTenant(ProcurementProject $project, CurrentTenant $currentTenant): bool
    {
        $tenant = $currentTenant->get();

        return $tenant !== null && (int) $project->tenant_id === (int) $tenant->id;
    }

    private function hasRole(User $user, array $roles): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return $role !== null && in_array($role, $roles, true);
    }

    private function hasVisibleLinkedRequisition(User $user, ProcurementProject $project): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        if ($role === null) {
            return false;
        }

        return Requisition::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('project_id', $project->id)
            ->where(function ($query) use ($user, $role): void {
                if ($role === TenantRole::Requester->value) {
                    $query->where('requester_id', $user->id);

                    return;
                }

                if ($role === TenantRole::Approver->value) {
                    $query->where('status', RequisitionStatus::Submitted->value);

                    return;
                }

                $query->whereRaw('1 = 0');
            })
            ->exists();
    }
}

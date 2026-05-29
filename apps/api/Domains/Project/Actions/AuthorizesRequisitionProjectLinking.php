<?php

namespace Domains\Project\Actions;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\States\ProjectStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait AuthorizesRequisitionProjectLinking
{
    private function findVisibleLinkableProject(Tenant $tenant, User $actor, int|string $projectId): ProcurementProject
    {
        $project = ProcurementProject::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($projectId)
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->firstOrFail();

        $visible = ProcurementProject::query()
            ->visibleTo($actor, $tenant->roleFor($actor), $tenant->id)
            ->whereKey($project->id)
            ->exists();

        if (! $visible) {
            throw new HttpException(403, 'You are not allowed to link requisitions to this project.');
        }

        return $project;
    }

    private function canLinkOrUnlinkRequisition(Tenant $tenant, User $actor, Requisition $requisition): bool
    {
        $role = $tenant->roleFor($actor);

        if ($role === TenantRole::Admin->value) {
            return true;
        }

        if ($role === TenantRole::Buyer->value) {
            return $requisition->status === RequisitionStatus::Submitted;
        }

        if ($role === TenantRole::Requester->value && (int) $requisition->requester_id === (int) $actor->id) {
            return $requisition->status === RequisitionStatus::Draft
                || $requisition->status === RequisitionStatus::ChangesRequested;
        }

        return false;
    }
}

<?php

namespace Domains\Project\Actions;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

trait AuthorizesRequisitionProjectLinking
{
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

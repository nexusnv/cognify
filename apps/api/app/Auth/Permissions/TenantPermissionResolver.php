<?php

namespace App\Auth\Permissions;

use App\Auth\TenantRole;

class TenantPermissionResolver
{
    /**
     * @return array<string, bool>
     */
    public function forRole(?string $role): array
    {
        return match ($role) {
            TenantRole::Requester->value => [
                'canCreateRequisition' => true,
                'canViewSubmittedRequisitions' => false,
                'canUpdateOwnDraftRequisition' => true,
                'canSubmitOwnDraftRequisition' => true,
                'canAccessAdmin' => false,
                'canManageSourcingIntake' => false,
            ],
            TenantRole::Buyer->value => [
                'canCreateRequisition' => false,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => false,
                'canSubmitOwnDraftRequisition' => false,
                'canAccessAdmin' => false,
                'canManageSourcingIntake' => true,
            ],
            TenantRole::Approver->value => [
                'canCreateRequisition' => false,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => false,
                'canSubmitOwnDraftRequisition' => false,
                'canAccessAdmin' => false,
                'canManageSourcingIntake' => false,
            ],
            TenantRole::Admin->value => [
                'canCreateRequisition' => true,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => true,
                'canSubmitOwnDraftRequisition' => true,
                'canAccessAdmin' => true,
                'canManageSourcingIntake' => true,
            ],
            default => [
                'canCreateRequisition' => false,
                'canViewSubmittedRequisitions' => false,
                'canUpdateOwnDraftRequisition' => false,
                'canSubmitOwnDraftRequisition' => false,
                'canAccessAdmin' => false,
                'canManageSourcingIntake' => false,
            ],
        };
    }
}

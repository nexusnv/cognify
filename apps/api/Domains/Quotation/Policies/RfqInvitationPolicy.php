<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqInvitationStatus;

class RfqInvitationPolicy
{
    public function viewAny(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user) && $this->rfqInCurrentTenant($rfq);
    }

    public function create(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user) && $this->rfqInCurrentTenant($rfq) && $rfq->isEditable();
    }

    public function resend(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && $invitation->canBeResent();
    }

    public function cancel(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && $invitation->canBeCancelled();
    }

    public function updateStatus(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && $invitation->statusState() === RfqInvitationStatus::Sent;
    }

    public function regeneratePortalLink(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && $invitation->canBeViewedInPortal();
    }

    private function canManageInvitation(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageSourcing($user) && $this->invitationInCurrentTenant($invitation);
    }

    private function canManageSourcing(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function rfqInCurrentTenant(Rfq $rfq): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $rfq->tenant_id === (int) $tenant->id;
    }

    private function invitationInCurrentTenant(RfqInvitation $invitation): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $invitation->tenant_id === (int) $tenant->id;
    }
}

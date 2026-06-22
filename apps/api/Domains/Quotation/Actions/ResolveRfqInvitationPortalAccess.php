<?php

namespace Domains\Quotation\Actions;

use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveRfqInvitationPortalAccess
{
    public function handle(string $token): RfqInvitation
    {
        $hash = hash('sha256', $token);

        $invitation = RfqInvitation::query()
            ->with(['tenant', 'vendor', 'rfq'])
            ->where('portal_token_hash', $hash)
            ->first();

        if ($invitation === null) {
            throw (new ModelNotFoundException)->setModel(RfqInvitation::class);
        }

        if ($invitation->portalTokenExpired() || ! $invitation->canBeViewedInPortal()) {
            throw new ConflictHttpException('This vendor portal link is no longer available.');
        }

        return $invitation;
    }
}

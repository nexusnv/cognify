<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnsureRfqInvitationPortalToken
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return array{invitation: RfqInvitation, token: ?string}
     */
    public function handle(Tenant $tenant, User $actor, RfqInvitation $invitation, bool $forceRegenerate = false): array
    {
        if (! $invitation->canBeViewedInPortal()) {
            throw new ConflictHttpException('This invitation is not available in the vendor portal.');
        }

        if (! $forceRegenerate && $invitation->portal_token_hash !== null && ! $invitation->portalTokenExpired()) {
            return [
                'invitation' => $invitation,
                'token' => null,
            ];
        }

        $token = Str::random(64);
        $expiresAt = $invitation->defaultPortalTokenExpiry();

        $invitation->forceFill([
            'portal_token_hash' => hash('sha256', $token),
            'portal_token_created_at' => now(),
            'portal_token_expires_at' => $expiresAt,
        ])->save();

        $this->audit->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: $forceRegenerate ? 'rfq_invitation.portal_token_regenerated' : 'rfq_invitation.portal_token_created',
            subject: $invitation,
            metadata: [
                'rfqId' => (string) $invitation->rfq_id,
                'vendorId' => (string) $invitation->vendor_id,
                'expiresAt' => $expiresAt?->toISOString(),
            ],
            subjectDisplay: $invitation->vendor?->name,
        ));

        return [
            'invitation' => $invitation->refresh()->loadMissing('vendor', 'rfq'),
            'token' => $token,
        ];
    }
}

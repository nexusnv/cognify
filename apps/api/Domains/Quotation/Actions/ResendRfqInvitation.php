<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResendRfqInvitation
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly EnsureRfqInvitationPortalToken $portalTokens,
    ) {}

    public function handle(Tenant $tenant, User $actor, RfqInvitation $invitation): RfqInvitation
    {
        Gate::forUser($actor)->authorize('resend', $invitation);

        return DB::transaction(function () use ($tenant, $actor, $invitation): RfqInvitation {
            $lockedInvitation = RfqInvitation::query()
                ->with('vendor')
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvitation->canBeResent()) {
                throw new ConflictHttpException('This invitation cannot be resent.');
            }

            $lockedInvitation->forceFill([
                'status' => RfqInvitationStatus::Sent->value,
                'sent_at' => now(),
                'acknowledged_at' => null,
                'declined_at' => null,
                'expired_at' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_invitation.resent',
                subject: $lockedInvitation,
                metadata: [
                    'rfqId' => (string) $lockedInvitation->rfq_id,
                    'vendorId' => (string) $lockedInvitation->vendor_id,
                ],
                subjectDisplay: $lockedInvitation->vendor?->name,
            ));

            $this->portalTokens->handle($tenant, $actor, $lockedInvitation->refresh()->load('vendor', 'rfq'));

            return $lockedInvitation->refresh()->load('vendor');
        });
    }
}

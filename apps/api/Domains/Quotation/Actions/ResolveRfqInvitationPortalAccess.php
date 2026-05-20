<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveRfqInvitationPortalAccess
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(string $token): RfqInvitation
    {
        $hash = hash('sha256', $token);

        return DB::transaction(function () use ($hash): RfqInvitation {
            $invitation = RfqInvitation::query()
                ->with(['tenant', 'vendor', 'rfq'])
                ->where('portal_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw (new ModelNotFoundException())->setModel(RfqInvitation::class);
            }

            if ($invitation->portalTokenExpired() || ! $invitation->canBeViewedInPortal()) {
                throw new ConflictHttpException('This vendor portal link is no longer available.');
            }

            $invitation->forceFill([
                'portal_last_viewed_at' => now(),
                'portal_view_count' => (int) $invitation->portal_view_count + 1,
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $invitation->tenant,
                actor: null,
                action: 'rfq_invitation.portal_viewed',
                subject: $invitation,
                metadata: [
                    'rfqId' => (string) $invitation->rfq_id,
                    'vendorId' => (string) $invitation->vendor_id,
                ],
                subjectDisplay: $invitation->vendor?->name,
            ));

            return $invitation->refresh()->load(['tenant', 'vendor', 'rfq']);
        });
    }
}

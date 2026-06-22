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

class CancelRfqInvitation
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, RfqInvitation $invitation, array $data): RfqInvitation
    {
        Gate::forUser($actor)->authorize('cancel', $invitation);

        return DB::transaction(function () use ($tenant, $actor, $invitation, $data): RfqInvitation {
            $lockedInvitation = RfqInvitation::query()
                ->with('vendor')
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedInvitation->canBeCancelled()) {
                throw new ConflictHttpException('This invitation cannot be cancelled.');
            }

            $lockedInvitation->forceFill([
                'status' => RfqInvitationStatus::Cancelled->value,
                'cancel_reason' => $data['cancelReason'],
                'cancelled_at' => now(),
                'acknowledged_at' => $lockedInvitation->statusState() === RfqInvitationStatus::Acknowledged
                    ? $lockedInvitation->acknowledged_at
                    : null,
                'declined_at' => null,
                'expired_at' => null,
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_invitation.cancelled',
                subject: $lockedInvitation,
                metadata: [
                    'rfqId' => (string) $lockedInvitation->rfq_id,
                    'vendorId' => (string) $lockedInvitation->vendor_id,
                ],
                subjectDisplay: $lockedInvitation->vendor?->name,
            ));

            return $lockedInvitation->refresh()->load('vendor');
        });
    }
}

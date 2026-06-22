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

class UpdateRfqInvitationStatus
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, RfqInvitation $invitation, array $data): RfqInvitation
    {
        Gate::forUser($actor)->authorize('updateStatus', $invitation);

        return DB::transaction(function () use ($tenant, $actor, $invitation, $data): RfqInvitation {
            $lockedInvitation = RfqInvitation::query()
                ->with('vendor')
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $status = RfqInvitationStatus::from($data['status']);

            if (! $lockedInvitation->canUpdateStatusTo($status)) {
                throw new ConflictHttpException('This invitation status cannot be updated.');
            }

            $now = now();

            $attributes = [
                'status' => $status->value,
                'acknowledged_at' => $status === RfqInvitationStatus::Acknowledged ? $now : null,
                'declined_at' => $status === RfqInvitationStatus::Declined ? $now : null,
                'expired_at' => $status === RfqInvitationStatus::Expired ? $now : null,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ];

            $lockedInvitation->forceFill($attributes)->save();

            $this->audit->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_invitation.'.$status->value,
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

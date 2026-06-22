<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordRfqInvitationPortalView
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(RfqInvitation $invitation, Request $request): RfqInvitation
    {
        return DB::transaction(function () use ($invitation): RfqInvitation {
            $locked = RfqInvitation::query()
                ->with(['tenant', 'vendor', 'rfq'])
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'portal_last_viewed_at' => now(),
                'portal_view_count' => (int) $locked->portal_view_count + 1,
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $locked->tenant,
                actor: null,
                action: 'rfq_invitation.portal_viewed',
                subject: $locked,
                metadata: [
                    'tenantId' => (string) $locked->tenant_id,
                    'invitationId' => (string) $locked->id,
                    'rfqId' => (string) $locked->rfq_id,
                    'vendorId' => (string) $locked->vendor_id,
                ],
                subjectDisplay: $locked->vendor?->name,
            ));

            return $locked->refresh()->load(['tenant', 'vendor', 'rfq']);
        });
    }
}

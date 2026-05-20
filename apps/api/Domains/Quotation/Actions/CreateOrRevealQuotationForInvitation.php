<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrRevealQuotationForInvitation
{
    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @return array{quotation: Quotation, created: bool}
     */
    public function handle(Tenant $tenant, RfqInvitation $invitation, ?User $actor = null): array
    {
        return DB::transaction(function () use ($tenant, $invitation, $actor): array {
            $lockedInvitation = RfqInvitation::query()
                ->with(['rfq', 'vendor'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInvitation->rfq === null || $lockedInvitation->vendor === null) {
                throw new ConflictHttpException('The RFQ invitation is missing its RFQ or vendor.');
            }

            $quotation = Quotation::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'rfq_invitation_id' => $lockedInvitation->id,
                ],
                [
                    'rfq_id' => $lockedInvitation->rfq_id,
                    'vendor_id' => $lockedInvitation->vendor_id,
                    'number' => $this->numberFor($lockedInvitation),
                    'status' => QuotationStatus::Draft->value,
                    'file_count' => 0,
                ],
            );
            $created = $quotation->wasRecentlyCreated;

            if ($created) {
                $this->audit->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation.created',
                    subject: $quotation,
                    metadata: [
                        'rfqInvitationId' => (string) $lockedInvitation->id,
                        'rfqId' => (string) $lockedInvitation->rfq_id,
                        'vendorId' => (string) $lockedInvitation->vendor_id,
                    ],
                    subjectDisplay: $quotation->number,
                ));
            }

            return [
                'quotation' => $quotation->refresh()->load(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation']),
                'created' => $created,
            ];
        });
    }

    private function numberFor(RfqInvitation $invitation): string
    {
        return sprintf('QUOTE-%s-%s', $invitation->rfq?->number ?? $invitation->rfq_id, $invitation->vendor_id);
    }
}

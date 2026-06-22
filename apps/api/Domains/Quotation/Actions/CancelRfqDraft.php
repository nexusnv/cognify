<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\RfqStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelRfqDraft
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): Rfq
    {
        Gate::forUser($actor)->authorize('cancel', $rfq);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $data): Rfq {
            $lockedRfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($rfq->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedRfq->isEditable()) {
                throw new ConflictHttpException('This RFQ cannot be cancelled.');
            }

            $lockedRfq->forceFill([
                'status' => RfqStatus::Cancelled->value,
                'cancel_reason' => $data['cancelReason'],
                'cancelled_at' => now(),
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq.draft_cancelled',
                subject: $lockedRfq,
                metadata: [],
                subjectDisplay: $lockedRfq->number,
            ));

            return $this->loadRfq($lockedRfq);
        });
    }

    private function loadRfq(Rfq $rfq): Rfq
    {
        return $rfq->refresh()->load(['sourcingIntakeReview.assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}

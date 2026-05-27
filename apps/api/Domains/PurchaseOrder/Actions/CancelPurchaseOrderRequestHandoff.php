<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelPurchaseOrderRequestHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrderRequestHandoff $handoff, User $actor, int $lockVersion, string $reason): PurchaseOrderRequestHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $reason): PurchaseOrderRequestHandoff {
            $handoff = PurchaseOrderRequestHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($handoff->statusState(), [
                PurchaseOrderRequestHandoffStatus::Draft,
                PurchaseOrderRequestHandoffStatus::Ready,
                PurchaseOrderRequestHandoffStatus::Exported,
            ], true)) {
                throw new ConflictHttpException('This PO handoff cannot be cancelled.');
            }

            $handoff->assertLockVersion($lockVersion);
            $reason = trim($reason);

            if ($reason === '') {
                throw new ConflictHttpException('A cancellation reason is required.');
            }

            $before = $handoff->only(['status', 'cancelled_by_user_id', 'cancelled_at', 'cancelled_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => PurchaseOrderRequestHandoffStatus::Cancelled,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order_handoff.cancelled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'cancelled_by_user_id', 'cancelled_at', 'cancelled_reason', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}

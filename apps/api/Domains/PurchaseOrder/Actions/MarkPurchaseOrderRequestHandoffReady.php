<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkPurchaseOrderRequestHandoffReady
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrderRequestHandoff $handoff, User $actor, int $lockVersion): PurchaseOrderRequestHandoff
    {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion): PurchaseOrderRequestHandoff {
            $handoff = PurchaseOrderRequestHandoff::query()
                ->whereKey($handoff->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($handoff->statusState() !== PurchaseOrderRequestHandoffStatus::Draft) {
                throw new ConflictHttpException('Only draft PO handoffs can be marked ready.');
            }

            $handoff->assertLockVersion($lockVersion);

            if ($handoff->line_snapshot === [] || $handoff->line_snapshot === null) {
                throw new ConflictHttpException('PO handoff must include at least one line before it can be marked ready.');
            }

            if (data_get($handoff->approval_snapshot, 'finalDecision') !== 'approved') {
                throw new ConflictHttpException('PO handoff requires an approved award decision before it can be marked ready.');
            }

            if ($handoff->currency === null || $handoff->total_amount === null) {
                throw new ConflictHttpException('PO handoff requires currency and total amount before it can be marked ready.');
            }

            if (($handoff->readiness_warnings ?? []) !== []) {
                throw new ConflictHttpException('PO handoff readiness warnings must be resolved before it can be marked ready.');
            }

            $before = $handoff->only(['status', 'ready_by_user_id', 'ready_at', 'lock_version']);

            $handoff->forceFill([
                'status' => PurchaseOrderRequestHandoffStatus::Ready,
                'ready_by_user_id' => $actor->id,
                'ready_at' => now(),
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'purchase_order_handoff.ready',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'ready_by_user_id', 'ready_at', 'lock_version']),
            ));

            return $handoff->fresh();
        });
    }
}

<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelPurchaseOrder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, int $lockVersion, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $lockVersion, $reason): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if ($purchaseOrder->statusState() !== PurchaseOrderStatus::Draft) {
                throw new ConflictHttpException('Only draft purchase orders can be cancelled.');
            }

            $purchaseOrder->assertLockVersion($lockVersion);
            $reason = trim($reason);

            if ($reason === '') {
                throw new ConflictHttpException('A cancellation reason is required.');
            }

            $before = $purchaseOrder->only([
                'status',
                'cancelled_by_user_id',
                'cancelled_at',
                'cancelled_reason',
                'lock_version',
            ]);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.cancelled',
                subject: $purchaseOrder,
                before: $before,
                after: $purchaseOrder->only([
                    'status',
                    'cancelled_by_user_id',
                    'cancelled_at',
                    'cancelled_reason',
                    'lock_version',
                ]),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CancelPurchaseOrderChangeOrder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrderChangeOrder $changeOrder, User $actor, int $lockVersion, string $reason): PurchaseOrderChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $actor, $lockVersion, $reason): PurchaseOrderChangeOrder {
            $changeOrder = PurchaseOrderChangeOrder::query()
                ->whereKey($changeOrder->id)
                ->where('tenant_id', $changeOrder->tenant_id)
                ->with(['purchaseOrder.lines', 'lines'])
                ->lockForUpdate()
                ->firstOrFail();

            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($changeOrder->purchase_order_id)
                ->where('tenant_id', $changeOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($changeOrder->statusState(), [PurchaseOrderChangeOrderStatus::Draft, PurchaseOrderChangeOrderStatus::ChangesRequested], true)) {
                throw new ConflictHttpException('Only draft or changes-requested change orders can be cancelled.');
            }

            $changeOrder->assertLockVersion($lockVersion);

            $beforeSnapshot = $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']);

            $changeOrder->forceFill([
                'status' => PurchaseOrderChangeOrderStatus::Cancelled,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancelled_reason' => $reason,
                'lock_version' => $changeOrder->lock_version + 1,
            ])->save();

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::from($changeOrder->from_purchase_order_status),
                'current_change_order_id' => null,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.change_order.cancelled',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'reason' => $reason,
                    'fromStatus' => $changeOrder->from_purchase_order_status,
                    'toStatus' => $changeOrder->from_purchase_order_status,
                ]),
                before: $beforeSnapshot,
                after: $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']),
            ));

            return $changeOrder->fresh(['purchaseOrder.lines', 'lines']);
        });
    }
}

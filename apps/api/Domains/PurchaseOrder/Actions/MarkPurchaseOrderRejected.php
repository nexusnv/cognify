<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Actions\RejectPurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;

class MarkPurchaseOrderRejected
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly RejectPurchaseOrderChangeOrder $rejectChangeOrder,
    ) {}

    public function handle(PurchaseOrder $purchaseOrder, ApprovalInstance $instance, User $actor, string $reason): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $instance, $actor, $reason): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->with('currentChangeOrder')
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->current_change_order_id !== null && $purchaseOrder->currentChangeOrder instanceof PurchaseOrderChangeOrder) {
                return $this->rejectChangeOrder->handle($purchaseOrder->currentChangeOrder, $instance, $actor, $reason);
            }

            $before = $purchaseOrder->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::Rejected,
                'approval_instance_id' => $instance->id,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => now(),
                'rejected_reason' => $reason,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.rejected',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'approvalInstanceId' => (string) $instance->id,
                    'reason' => $reason,
                    'fromStatus' => $before['status'] instanceof PurchaseOrderStatus ? $before['status']->value : (string) $before['status'],
                    'toStatus' => PurchaseOrderStatus::Rejected->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'rejected_by_user_id', 'rejected_at', 'rejected_reason', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

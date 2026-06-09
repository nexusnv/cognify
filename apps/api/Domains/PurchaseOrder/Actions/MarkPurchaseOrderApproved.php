<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;

class MarkPurchaseOrderApproved
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrder $purchaseOrder, ApprovalInstance $instance, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $instance, $actor): PurchaseOrder {
            $purchaseOrder = $this->lockedPurchaseOrder($purchaseOrder);
            $before = $purchaseOrder->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::Approved,
                'approval_instance_id' => $instance->id,
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'rejected_by_user_id' => null,
                'rejected_at' => null,
                'rejected_reason' => null,
                'changes_requested_by_user_id' => null,
                'changes_requested_at' => null,
                'changes_requested_reason' => null,
                'changes_requested_fields' => [],
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.approved',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'approvalInstanceId' => (string) $instance->id,
                    'fromStatus' => $before['status'] instanceof PurchaseOrderStatus ? $before['status']->value : (string) $before['status'],
                    'toStatus' => PurchaseOrderStatus::Approved->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'approved_by_user_id', 'approved_at', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }

    private function lockedPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return PurchaseOrder::query()
            ->whereKey($purchaseOrder->id)
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}

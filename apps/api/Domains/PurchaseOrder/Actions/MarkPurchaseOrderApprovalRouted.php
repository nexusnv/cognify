<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;

class MarkPurchaseOrderApprovalRouted
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrder $purchaseOrder, ApprovalInstance $instance, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $instance, $actor): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $isChangeOrder = $purchaseOrder->current_change_order_id !== null;
            $before = $purchaseOrder->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']);
            $routedStatus = $isChangeOrder
                ? PurchaseOrderStatus::ChangePending
                : PurchaseOrderStatus::InReview;

            $purchaseOrder->forceFill([
                'status' => $routedStatus,
                'approval_instance_id' => $instance->id,
                'approval_submitted_by_user_id' => $actor->id,
                'approval_submitted_at' => now(),
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            if ($isChangeOrder) {
                $changeOrder = PurchaseOrderChangeOrder::query()
                    ->where('tenant_id', $purchaseOrder->tenant_id)
                    ->whereKey($purchaseOrder->current_change_order_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $changeOrder->forceFill([
                    'approval_instance_id' => $instance->id,
                    'lock_version' => $changeOrder->lock_version + 1,
                ])->save();
            }

            $auditAction = $isChangeOrder
                ? 'purchase_order.change_order.approval_routed'
                : 'purchase_order.approval_submitted';

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: $auditAction,
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'approvalInstanceId' => (string) $instance->id,
                    'fromStatus' => $before['status'] instanceof PurchaseOrderStatus ? $before['status']->value : (string) $before['status'],
                    'toStatus' => $routedStatus->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'approval_instance_id', 'approval_submitted_by_user_id', 'approval_submitted_at', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

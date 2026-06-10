<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;

class RequestPurchaseOrderChangeOrderChanges
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(PurchaseOrderChangeOrder $changeOrder, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields = []): PurchaseOrder
    {
        return DB::transaction(function () use ($changeOrder, $instance, $actor, $reason, $requestedFields): PurchaseOrder {
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

            $changeOrder->forceFill([
                'status' => PurchaseOrderChangeOrderStatus::ChangesRequested,
                'approval_instance_id' => $instance->id,
                'changes_requested_by_user_id' => $actor->id,
                'changes_requested_at' => now(),
                'changes_requested_reason' => $reason,
                'lock_version' => $changeOrder->lock_version + 1,
            ])->save();

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::ChangePending,
                'current_change_order_id' => $changeOrder->id,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.change_order.changes_requested',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'approvalInstanceId' => (string) $instance->id,
                    'reason' => $reason,
                    'requestedFields' => array_values($requestedFields),
                    'fromStatus' => $changeOrder->from_purchase_order_status,
                    'toStatus' => PurchaseOrderStatus::ChangePending->value,
                ]),
                before: $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']),
                after: $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

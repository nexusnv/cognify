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

class RequestPurchaseOrderChanges
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<int, string>  $requestedFields
     */
    public function handle(PurchaseOrder $purchaseOrder, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $instance, $actor, $reason, $requestedFields): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $before = $purchaseOrder->only(['status', 'changes_requested_by_user_id', 'changes_requested_at', 'changes_requested_reason', 'changes_requested_fields', 'lock_version']);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::ChangesRequested,
                'approval_instance_id' => $instance->id,
                'changes_requested_by_user_id' => $actor->id,
                'changes_requested_at' => now(),
                'changes_requested_reason' => $reason,
                'changes_requested_fields' => array_values($requestedFields),
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.changes_requested',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'approvalInstanceId' => (string) $instance->id,
                    'reason' => $reason,
                    'requestedFields' => array_values($requestedFields),
                    'fromStatus' => $before['status'] instanceof PurchaseOrderStatus ? $before['status']->value : (string) $before['status'],
                    'toStatus' => PurchaseOrderStatus::ChangesRequested->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'changes_requested_by_user_id', 'changes_requested_at', 'changes_requested_reason', 'changes_requested_fields', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

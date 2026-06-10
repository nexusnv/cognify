<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitPurchaseOrderChangeOrder
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ApplyPurchaseOrderChangeOrder $applyChangeOrder,
        private readonly RouteSubjectForApproval $routeSubjectForApproval,
    ) {}

    public function handle(PurchaseOrderChangeOrder $changeOrder, User $actor, int $lockVersion): PurchaseOrderChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $actor, $lockVersion): PurchaseOrderChangeOrder {
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
                ->with(['lines', 'currentChangeOrder.lines'])
                ->firstOrFail();

            if (! in_array($changeOrder->statusState(), [PurchaseOrderChangeOrderStatus::Draft, PurchaseOrderChangeOrderStatus::ChangesRequested], true)) {
                throw new ConflictHttpException('Only draft or changes-requested change orders can be submitted.');
            }

            $changeOrder->assertLockVersion($lockVersion);

            if (! $changeOrder->material_change) {
                $result = $this->applyChangeOrder->handle($changeOrder, $actor);

                return $result;
            }

            $changeOrder->forceFill([
                'status' => PurchaseOrderChangeOrderStatus::PendingApproval,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
                'lock_version' => $changeOrder->lock_version + 1,
            ])->save();

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::ChangePending,
                'current_change_order_id' => $changeOrder->id,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $instance = $this->routeSubjectForApproval->handle($changeOrder->tenant, $actor, $purchaseOrder);

            $changeOrder->forceFill([
                'approval_instance_id' => $instance->id,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.change_order.submitted',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'approvalInstanceId' => (string) $instance->id,
                    'fromStatus' => PurchaseOrderStatus::Issued->value,
                    'toStatus' => PurchaseOrderStatus::ChangePending->value,
                ]),
                before: $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']),
                after: $purchaseOrder->only(['status', 'current_change_order_id', 'lock_version']),
            ));

            return $changeOrder->fresh(['purchaseOrder.lines', 'lines']);
        });
    }
}

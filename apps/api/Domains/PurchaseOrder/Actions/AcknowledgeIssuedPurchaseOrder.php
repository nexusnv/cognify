<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcknowledgeIssuedPurchaseOrder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseOrder->statusState() !== PurchaseOrderStatus::Issued) {
                throw new ConflictHttpException('Only issued purchase orders can be acknowledged by suppliers.');
            }

            $purchaseOrder->assertLockVersion((int) $payload['lockVersion']);
            $before = $purchaseOrder->only(['status', 'acknowledged_by_user_id', 'acknowledged_at', 'lock_version']);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::Acknowledged,
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
                'acknowledged_contact_name' => $payload['acknowledgedContactName'] ?? null,
                'acknowledgement_reference' => $payload['acknowledgementReference'] ?? null,
                'acknowledgement_note' => $payload['acknowledgementNote'] ?? null,
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.acknowledged',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'acknowledgementReference' => $payload['acknowledgementReference'] ?? null,
                    'fromStatus' => PurchaseOrderStatus::Issued->value,
                    'toStatus' => PurchaseOrderStatus::Acknowledged->value,
                ]),
                before: $before,
                after: $purchaseOrder->only(['status', 'acknowledged_by_user_id', 'acknowledged_at', 'acknowledgement_reference', 'lock_version']),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

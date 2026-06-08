<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkPurchaseOrderReadyForReview
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, int $lockVersion): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $lockVersion): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if ($purchaseOrder->statusState() !== PurchaseOrderStatus::Draft) {
                throw new ConflictHttpException('Only draft purchase orders can be marked ready for review.');
            }

            $purchaseOrder->assertLockVersion($lockVersion);

            $required = [
                'billing_name' => $purchaseOrder->billing_name,
                'billing_address' => $purchaseOrder->billing_address,
                'shipping_name' => $purchaseOrder->shipping_name,
                'shipping_address' => $purchaseOrder->shipping_address,
                'payment_terms' => $purchaseOrder->payment_terms,
            ];

            foreach ($required as $value) {
                if ($value === null || $value === '' || $value === []) {
                    throw new ConflictHttpException('Purchase order requires billing, shipping, and payment terms before review.');
                }
            }

            $before = $purchaseOrder->only([
                'status',
                'ready_for_review_by_user_id',
                'ready_for_review_at',
                'lock_version',
            ]);

            $purchaseOrder->forceFill([
                'status' => PurchaseOrderStatus::ReadyForReview,
                'ready_for_review_by_user_id' => $actor->id,
                'ready_for_review_at' => now(),
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.ready_for_review',
                subject: $purchaseOrder,
                before: $before,
                after: $purchaseOrder->only([
                    'status',
                    'ready_for_review_by_user_id',
                    'ready_for_review_at',
                    'lock_version',
                ]),
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

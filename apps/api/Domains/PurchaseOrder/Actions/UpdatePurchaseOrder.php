<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdatePurchaseOrder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $data): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if ($purchaseOrder->statusState() !== PurchaseOrderStatus::Draft) {
                throw new ConflictHttpException('Only draft purchase orders can be updated.');
            }

            $purchaseOrder->assertLockVersion((int) Arr::get($data, 'lockVersion'));

            $before = $purchaseOrder->only([
                'requested_po_date',
                'expected_delivery_date',
                'billing_name',
                'billing_address',
                'shipping_name',
                'shipping_address',
                'delivery_attention',
                'payment_terms',
                'delivery_terms',
                'buyer_note',
                'finance_note',
                'lock_version',
            ]);

            $attributes = ['lock_version' => $purchaseOrder->lock_version + 1];

            $optionalFields = [
                'requestedPoDate' => 'requested_po_date',
                'expectedDeliveryDate' => 'expected_delivery_date',
                'billingName' => 'billing_name',
                'billingAddress' => 'billing_address',
                'shippingName' => 'shipping_name',
                'shippingAddress' => 'shipping_address',
                'deliveryAttention' => 'delivery_attention',
                'paymentTerms' => 'payment_terms',
                'deliveryTerms' => 'delivery_terms',
                'buyerNote' => 'buyer_note',
                'financeNote' => 'finance_note',
            ];

            foreach ($optionalFields as $inputKey => $column) {
                if (Arr::exists($data, $inputKey)) {
                    $attributes[$column] = $data[$inputKey];
                }
            }

            $purchaseOrder->forceFill($attributes)->save();

            $after = $purchaseOrder->only(array_keys($before));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.updated',
                subject: $purchaseOrder,
                before: $before,
                after: $after,
            ));

            return $purchaseOrder->fresh('lines');
        });
    }
}

<?php

namespace Domains\PurchaseOrder\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderType;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\PurchaseOrder\Support\PurchaseOrderAuditMetadata;
use Domains\PurchaseOrder\Support\PurchaseOrderChangeOrderDelta;
use Domains\PurchaseOrder\Support\PurchaseOrderChangeOrderNumber;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrUpdatePurchaseOrderChangeOrder
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PurchaseOrderChangeOrderDelta $delta,
        private readonly PurchaseOrderChangeOrderNumber $numberGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrderChangeOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): PurchaseOrderChangeOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->with('lines', 'currentChangeOrder.lines')
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($purchaseOrder->statusState(), [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged], true)) {
                throw new ConflictHttpException('Only issued or acknowledged purchase orders can be changed.');
            }

            $purchaseOrder->assertLockVersion((int) Arr::get($payload, 'lockVersion'));

            if ($purchaseOrder->current_change_order_id !== null && $purchaseOrder->currentChangeOrder !== null) {
                if (! in_array($purchaseOrder->currentChangeOrder->statusState(), [PurchaseOrderChangeOrderStatus::Cancelled, PurchaseOrderChangeOrderStatus::Rejected, PurchaseOrderChangeOrderStatus::Approved], true)) {
                    throw new ConflictHttpException('A purchase order already has an active change order.');
                }
            }

            $calculated = $this->delta->calculate($purchaseOrder, $payload);
            $changeType = PurchaseOrderChangeOrderType::from($calculated['changeType']);

            $changeOrder = PurchaseOrderChangeOrder::query()->create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'number' => $this->numberGenerator->nextFor($purchaseOrder),
                'status' => PurchaseOrderChangeOrderStatus::Draft,
                'change_type' => $changeType,
                'from_purchase_order_status' => $purchaseOrder->statusState()->value,
                'to_purchase_order_status' => $changeType === PurchaseOrderChangeOrderType::FullCancellation
                    ? PurchaseOrderStatus::Cancelled->value
                    : $purchaseOrder->statusState()->value,
                'reason' => (string) $payload['reason'],
                'material_change' => (bool) $calculated['materialChange'],
                'requires_approval' => (bool) $calculated['materialChange'],
                'requested_by_user_id' => $actor->id,
                'requested_at' => now(),
                'before_snapshot' => $calculated['before'],
                'after_snapshot' => $calculated['after'],
                'delta_snapshot' => $calculated['delta'],
                'lock_version' => 1,
            ]);

            foreach ($calculated['lineChanges'] as $index => $lineChange) {
                $line = $purchaseOrder->lines->firstWhere('id', $lineChange['lineId']);
                if (! $line instanceof \Domains\PurchaseOrder\Models\PurchaseOrderLine) {
                    continue;
                }

                PurchaseOrderChangeOrderLine::query()->create([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'purchase_order_change_order_id' => $changeOrder->id,
                    'purchase_order_line_id' => $line->id,
                    'line_number' => $line->line_number,
                    'change_action' => $lineChange['action'],
                    'quantity_before' => $lineChange['before']['quantity'] ?? null,
                    'quantity_after' => $lineChange['after']['quantity'] ?? null,
                    'unit_price_before' => $lineChange['before']['unitPrice'] ?? null,
                    'unit_price_after' => $lineChange['after']['unitPrice'] ?? null,
                    'subtotal_amount_before' => $lineChange['before']['subtotalAmount'] ?? null,
                    'subtotal_amount_after' => $lineChange['after']['subtotalAmount'] ?? null,
                    'tax_amount_before' => $lineChange['before']['taxAmount'] ?? null,
                    'tax_amount_after' => $lineChange['after']['taxAmount'] ?? null,
                    'freight_amount_before' => $lineChange['before']['freightAmount'] ?? null,
                    'freight_amount_after' => $lineChange['after']['freightAmount'] ?? null,
                    'discount_amount_before' => $lineChange['before']['discountAmount'] ?? null,
                    'discount_amount_after' => $lineChange['after']['discountAmount'] ?? null,
                    'total_amount_before' => $lineChange['before']['totalAmount'] ?? null,
                    'total_amount_after' => $lineChange['after']['totalAmount'] ?? null,
                    'expected_delivery_date_before' => $lineChange['before']['expectedDeliveryDate'] ?? null,
                    'expected_delivery_date_after' => $lineChange['after']['expectedDeliveryDate'] ?? null,
                    'delivery_location_before' => $lineChange['before']['deliveryLocation'] ?? null,
                    'delivery_location_after' => $lineChange['after']['deliveryLocation'] ?? null,
                    'notes_before' => $lineChange['before']['notes'] ?? null,
                    'notes_after' => $lineChange['after']['notes'] ?? null,
                    'delta_snapshot' => $lineChange,
                ]);
            }

            $purchaseOrder->forceFill([
                'current_change_order_id' => $changeOrder->id,
                'change_order_count' => ((int) $purchaseOrder->change_order_count) + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'purchase_order.change_order.drafted',
                subject: $purchaseOrder,
                metadata: PurchaseOrderAuditMetadata::for($purchaseOrder, extra: [
                    'changeOrderId' => (string) $changeOrder->id,
                    'changeOrderNumber' => $changeOrder->number,
                    'changeType' => $changeType->value,
                    'materialChange' => $changeOrder->material_change,
                    'requiresApproval' => $changeOrder->requires_approval,
                ]),
                before: $purchaseOrder->only(['current_change_order_id', 'change_order_count', 'lock_version']),
                after: $purchaseOrder->only(['current_change_order_id', 'change_order_count', 'lock_version']),
            ));

            return $changeOrder->load(['purchaseOrder.lines', 'lines']);
        });
    }
}

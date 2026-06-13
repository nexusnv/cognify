<?php

namespace Domains\Fulfillment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\Models\ShipmentLine;
use Domains\Fulfillment\States\ShipmentStatus;
use Domains\Fulfillment\Support\FulfillmentNumber;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateShipment
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): Shipment
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): Shipment {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($purchaseOrder->statusState(), [
                PurchaseOrderStatus::Issued,
                PurchaseOrderStatus::Acknowledged,
                PurchaseOrderStatus::ChangePending,
            ], true)) {
                throw new InvalidArgumentException('Shipments can only be recorded for issued, acknowledged, or change-pending purchase orders.');
            }

            $purchaseOrder->assertLockVersion((int) $payload['lockVersion']);

            $lines = $payload['lines'] ?? null;
            if (! is_array($lines) || $lines === []) {
                throw new InvalidArgumentException('At least one shipment line is required.');
            }

            $lineIds = [];
            foreach ($lines as $index => $linePayload) {
                $purchaseOrderLineId = $linePayload['purchaseOrderLineId'] ?? null;

                if (! is_string($purchaseOrderLineId) || $purchaseOrderLineId === '') {
                    throw new InvalidArgumentException("Line at index {$index} is missing purchaseOrderLineId.");
                }

                if (in_array($purchaseOrderLineId, $lineIds, true)) {
                    throw new InvalidArgumentException("Duplicate purchase order line {$purchaseOrderLineId} in shipment lines.");
                }

                $lineIds[] = $purchaseOrderLineId;
            }

            $purchaseOrderLines = PurchaseOrderLine::query()
                ->whereIn('id', $lineIds)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $shipment = Shipment::query()->create([
                'tenant_id' => $purchaseOrder->tenant_id,
                'purchase_order_id' => $purchaseOrder->id,
                'number' => FulfillmentNumber::nextFor($purchaseOrder),
                'status' => ShipmentStatus::Confirmed,
                'carrier_name' => $payload['carrierName'] ?? null,
                'tracking_reference' => $payload['trackingReference'] ?? null,
                'shipment_date' => $payload['shipmentDate'],
                'estimated_arrival_date' => $payload['estimatedArrivalDate'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'lock_version' => 1,
            ]);

            foreach ($lines as $linePayload) {
                $purchaseOrderLine = $purchaseOrderLines->get($linePayload['purchaseOrderLineId']);

                if (! $purchaseOrderLine instanceof PurchaseOrderLine) {
                    throw new InvalidArgumentException('Shipment line does not belong to this purchase order.');
                }

                if (($purchaseOrderLine->status ?? 'open') === 'cancelled') {
                    throw new InvalidArgumentException("Line {$purchaseOrderLine->line_number} is cancelled and cannot be shipped.");
                }

                $quantityShipped = (string) $linePayload['quantityShipped'];
                $backorderQuantity = (string) ($linePayload['backorderQuantity'] ?? '0');

                if (bccomp($quantityShipped, '0', 4) <= 0) {
                    throw new InvalidArgumentException("Line {$purchaseOrderLine->line_number}: quantity shipped must be greater than zero.");
                }

                if (bccomp($backorderQuantity, '0', 4) < 0) {
                    throw new InvalidArgumentException("Line {$purchaseOrderLine->line_number}: backorder quantity cannot be negative.");
                }

                ShipmentLine::query()->create([
                    'tenant_id' => $purchaseOrder->tenant_id,
                    'shipment_id' => $shipment->id,
                    'purchase_order_line_id' => $purchaseOrderLine->id,
                    'line_number' => $purchaseOrderLine->line_number,
                    'quantity_shipped' => $quantityShipped,
                    'quantity_delivered' => '0.0000',
                    'backorder_quantity' => $backorderQuantity,
                    'backorder_expected_at' => $linePayload['backorderExpectedAt'] ?? null,
                    'notes' => $linePayload['notes'] ?? null,
                ]);
            }

            $purchaseOrder->forceFill([
                'lock_version' => $purchaseOrder->lock_version + 1,
            ])->save();

            $shipment->load('lines');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $purchaseOrder->tenant,
                actor: $actor,
                action: 'fulfillment.shipment.recorded',
                subject: $shipment,
                metadata: [
                    'purchaseOrderId' => (string) $purchaseOrder->id,
                    'purchaseOrderNumber' => $purchaseOrder->number,
                    'shipmentNumber' => $shipment->number,
                    'lineCount' => $shipment->lines->count(),
                ],
            ));

            return $shipment;
        });
    }
}

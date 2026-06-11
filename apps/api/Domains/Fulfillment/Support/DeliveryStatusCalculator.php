<?php

namespace Domains\Fulfillment\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;

class DeliveryStatusCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(PurchaseOrder $purchaseOrder): array
    {
        $purchaseOrder->loadMissing(['lines', 'shipments.lines']);

        $activeLines = $purchaseOrder->lines->where('status', '!=', 'cancelled')->values();
        $shipmentLines = $purchaseOrder->shipments->flatMap->lines;
        $lineSummaries = [];
        $lateDeliveryCount = 0;
        $deliveredLineCount = 0;
        $hasBackorder = false;

        foreach ($activeLines as $line) {
            $receivedQuantity = (string) ($line->cumulative_quantity_received ?? '0');
            $orderedQuantity = (string) $line->quantity;
            $lineShipmentRows = $shipmentLines->where('purchase_order_line_id', $line->id);
            $expectedDeliveryDate = $line->expected_delivery_date ?? $purchaseOrder->expected_delivery_date;
            $backorderQuantity = $lineShipmentRows->reduce(
                fn (string $carry, $shipmentLine): string => bcadd($carry, (string) $shipmentLine->backorder_quantity, 4),
                '0.0000',
            );

            $isDelivered = bccomp($receivedQuantity, $orderedQuantity, 4) >= 0;
            $isDelayed = ! $isDelivered
                && $expectedDeliveryDate !== null
                && $expectedDeliveryDate->isPast();

            if ($isDelivered) {
                $deliveredLineCount++;
            }

            if ($isDelayed) {
                $lateDeliveryCount++;
            }

            if (bccomp($backorderQuantity, '0', 4) > 0) {
                $hasBackorder = true;
            }

            $lineStatus = $isDelivered
                ? 'delivered'
                : ($isDelayed
                    ? 'delayed'
                    : (bccomp($backorderQuantity, '0', 4) > 0
                        ? 'backordered'
                        : ($purchaseOrder->shipments->isEmpty() ? 'pending_shipment' : 'awaiting_delivery')));

            $lineSummaries[] = [
                'purchaseOrderLineId' => (string) $line->id,
                'lineNumber' => $line->line_number,
                'orderedQuantity' => $orderedQuantity,
                'receivedQuantity' => $receivedQuantity,
                'backorderQuantity' => $backorderQuantity,
                'expectedDeliveryDate' => $expectedDeliveryDate?->toDateString(),
                'status' => $lineStatus,
                'isDelayed' => $isDelayed,
            ];
        }

        $totalLineCount = count($lineSummaries);
        $shipmentCount = $purchaseOrder->shipments->count();
        $overallStatus = 'pending_shipment';
        $poExpectedDeliveryDelayed = $purchaseOrder->expected_delivery_date !== null
            && $purchaseOrder->expected_delivery_date->isPast()
            && ($totalLineCount === 0 || $deliveredLineCount < $totalLineCount);

        if ($poExpectedDeliveryDelayed && $lateDeliveryCount === 0) {
            $lateDeliveryCount = 1;
        }

        if ($totalLineCount > 0 && $deliveredLineCount === $totalLineCount) {
            $overallStatus = 'delivered';
        } elseif ($lateDeliveryCount > 0 || $poExpectedDeliveryDelayed) {
            $overallStatus = 'delayed';
        } elseif ($hasBackorder) {
            $overallStatus = 'backordered';
        } elseif ($deliveredLineCount > 0) {
            $overallStatus = 'partial';
        } elseif ($shipmentCount > 0) {
            $overallStatus = 'awaiting_delivery';
        }

        return [
            'purchaseOrderId' => (string) $purchaseOrder->id,
            'overallStatus' => $overallStatus,
            'isDelayed' => $lateDeliveryCount > 0,
            'lateDeliveryCount' => $lateDeliveryCount,
            'totalLineCount' => $totalLineCount,
            'deliveredLineCount' => $deliveredLineCount,
            'shipmentCount' => $shipmentCount,
            'lineSummaries' => $lineSummaries,
        ];
    }
}

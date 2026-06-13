<?php

namespace Domains\PurchaseOrder\Http\Resources;

use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderLine
 */
class PurchaseOrderLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PurchaseOrderLine $line */
        $line = $this->resource;

        return [
            'id' => (string) $line->id,
            'lineNumber' => $line->line_number,
            'description' => $line->description,
            'category' => $line->category,
            'sku' => $line->sku,
            'unit' => $line->unit,
            'quantity' => (string) $line->quantity,
            'unitPrice' => (string) $line->unit_price,
            'subtotalAmount' => (string) $line->subtotal_amount,
            'taxAmount' => $line->tax_amount !== null ? (string) $line->tax_amount : null,
            'freightAmount' => $line->freight_amount !== null ? (string) $line->freight_amount : null,
            'discountAmount' => $line->discount_amount !== null ? (string) $line->discount_amount : null,
            'totalAmount' => (string) $line->total_amount,
            'currency' => $line->currency,
            'neededByDate' => $line->needed_by_date?->toDateString(),
            'expectedDeliveryDate' => $line->expected_delivery_date?->toDateString(),
            'deliveryLocation' => $line->delivery_location,
            'notes' => $line->notes,
            'source' => $line->source_snapshot ?? [],
            'status' => $line->status ?? 'open',
            'currentVersionNumber' => $line->current_version_number ?? 1,
            'cancelledByChangeOrderId' => $line->cancelled_by_change_order_id !== null ? (string) $line->cancelled_by_change_order_id : null,
            'cancelledAt' => $line->cancelled_at?->toISOString(),
            'cancelledReason' => $line->cancelled_reason,
            'cumulativeQuantityReceived' => (string) ($line->cumulative_quantity_received ?? '0'),
            'cumulativeQuantityAccepted' => (string) ($line->cumulative_quantity_accepted ?? '0'),
            'overReceiptTolerancePercent' => (string) ($line->over_receipt_tolerance_percent ?? '10.00'),
            'lastReceiptAt' => $line->last_receipt_at?->toISOString(),
        ];
    }
}

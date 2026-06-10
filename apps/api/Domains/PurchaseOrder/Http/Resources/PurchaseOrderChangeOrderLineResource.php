<?php

namespace Domains\PurchaseOrder\Http\Resources;

use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseOrderChangeOrderLine
 */
class PurchaseOrderChangeOrderLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $line = $this->resource;

        return [
            'id' => (string) $line->id,
            'lineId' => (string) $line->purchase_order_line_id,
            'lineNumber' => $line->line_number,
            'changeAction' => $line->change_action,
            'quantityBefore' => $line->quantity_before !== null ? (string) $line->quantity_before : null,
            'quantityAfter' => $line->quantity_after !== null ? (string) $line->quantity_after : null,
            'unitPriceBefore' => $line->unit_price_before !== null ? (string) $line->unit_price_before : null,
            'unitPriceAfter' => $line->unit_price_after !== null ? (string) $line->unit_price_after : null,
            'subtotalAmountBefore' => $line->subtotal_amount_before !== null ? (string) $line->subtotal_amount_before : null,
            'subtotalAmountAfter' => $line->subtotal_amount_after !== null ? (string) $line->subtotal_amount_after : null,
            'totalAmountBefore' => $line->total_amount_before !== null ? (string) $line->total_amount_before : null,
            'totalAmountAfter' => $line->total_amount_after !== null ? (string) $line->total_amount_after : null,
            'expectedDeliveryDateBefore' => $line->expected_delivery_date_before?->toDateString(),
            'expectedDeliveryDateAfter' => $line->expected_delivery_date_after?->toDateString(),
            'deliveryLocationBefore' => $line->delivery_location_before,
            'deliveryLocationAfter' => $line->delivery_location_after,
            'notesBefore' => $line->notes_before,
            'notesAfter' => $line->notes_after,
            'delta' => $line->delta_snapshot ?? [],
        ];
    }
}

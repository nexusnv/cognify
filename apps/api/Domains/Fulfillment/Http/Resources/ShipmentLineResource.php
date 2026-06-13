<?php

namespace Domains\Fulfillment\Http\Resources;

use Domains\Fulfillment\Models\ShipmentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShipmentLine
 */
class ShipmentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderLineId' => (string) $this->purchase_order_line_id,
            'lineNumber' => $this->line_number,
            'quantityShipped' => (string) $this->quantity_shipped,
            'quantityDelivered' => (string) $this->quantity_delivered,
            'backorderQuantity' => (string) $this->backorder_quantity,
            'backorderExpectedAt' => $this->backorder_expected_at?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}

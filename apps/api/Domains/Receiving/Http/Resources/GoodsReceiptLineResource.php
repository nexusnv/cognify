<?php

namespace Domains\Receiving\Http\Resources;

use Domains\Receiving\Models\GoodsReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceiptLine
 */
class GoodsReceiptLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderLineId' => (string) $this->purchase_order_line_id,
            'lineNumber' => $this->line_number,
            'quantityOrdered' => (string) $this->quantity_ordered,
            'quantityReceived' => (string) $this->quantity_received,
            'quantityAccepted' => (string) $this->quantity_accepted,
            'rejectionReason' => $this->rejection_reason,
            'notes' => $this->notes,
        ];
    }
}

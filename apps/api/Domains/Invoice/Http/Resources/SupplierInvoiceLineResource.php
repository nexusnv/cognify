<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierInvoiceLine
 */
class SupplierInvoiceLineResource extends JsonResource
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
            'descriptionSnapshot' => $this->description_snapshot,
            'quantityOrdered' => (string) $this->quantity_ordered,
            'quantityInvoiced' => (string) $this->quantity_invoiced,
            'unitPrice' => (string) $this->unit_price,
            'lineSubtotal' => (string) $this->line_subtotal,
            'notes' => $this->notes,
        ];
    }
}

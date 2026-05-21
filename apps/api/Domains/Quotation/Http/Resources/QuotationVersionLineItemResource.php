<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationVersionLineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationVersionLineItem
 */
class QuotationVersionLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'rfqLineItemId' => $this->rfq_line_item_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->unit_price,
            'subtotalAmount' => $this->subtotal_amount,
            'taxAmount' => $this->tax_amount,
            'totalAmount' => $this->total_amount,
            'leadTimeDays' => $this->lead_time_days,
            'manufacturer' => $this->manufacturer,
            'modelNumber' => $this->model_number,
            'alternateOffered' => (bool) $this->alternate_offered,
            'complianceStatus' => $this->compliance_status,
            'notes' => $this->notes,
        ];
    }
}

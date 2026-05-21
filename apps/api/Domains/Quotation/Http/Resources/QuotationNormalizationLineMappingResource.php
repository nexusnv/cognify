<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationNormalizationLineMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalizationLineMapping
 */
class QuotationNormalizationLineMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'rfqLineItemId' => $this->rfq_line_item_id,
            'quotationVersionLineItemId' => $this->quotation_version_line_item_id === null ? null : (string) $this->quotation_version_line_item_id,
            'mappingType' => $this->mapping_type?->value ?? $this->mapping_type,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->unit_price,
            'lineTotal' => $this->line_total,
            'buyerNote' => $this->buyer_note,
        ];
    }
}

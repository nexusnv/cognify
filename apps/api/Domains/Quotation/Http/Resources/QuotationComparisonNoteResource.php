<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationComparisonNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationComparisonNote
 */
class QuotationComparisonNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'rfqId' => (string) $this->rfq_id,
            'quotationId' => $this->quotation_id !== null ? (string) $this->quotation_id : null,
            'vendorId' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
            'rfqLineItemId' => $this->rfq_line_item_id,
            'section' => $this->section?->value ?? $this->section,
            'note' => $this->note,
            'createdByUserId' => (string) $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id !== null ? (string) $this->updated_by_user_id : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

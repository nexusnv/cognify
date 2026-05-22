<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationNormalizationLineGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalizationLineGroup
 */
class QuotationNormalizationLineGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'groupNumber' => $this->group_number,
            'pricingMode' => $this->pricing_mode?->value ?? $this->pricing_mode,
            'description' => $this->description,
            'currency' => $this->currency,
            'bundleTotalAmount' => $this->bundle_total_amount,
            'notes' => $this->notes,
            'mappings' => QuotationNormalizationLineMappingResource::collection($this->whenLoaded('mappings')),
        ];
    }
}

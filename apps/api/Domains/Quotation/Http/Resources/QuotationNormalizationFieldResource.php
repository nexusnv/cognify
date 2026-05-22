<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationNormalizationField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalizationField
 */
class QuotationNormalizationFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'fieldPath' => $this->field_path,
            'rawValue' => $this->raw_value,
            'normalizedValue' => $this->normalized_value,
            'dataType' => $this->data_type,
            'currency' => $this->currency,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'provenance' => $this->provenance ?? [],
        ];
    }
}

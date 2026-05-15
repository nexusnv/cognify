<?php

namespace Domains\Requisition\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequisitionItemSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'estimatedUnitPrice' => (float) $this->estimated_unit_price,
            'currency' => $this->currency,
        ];
    }
}

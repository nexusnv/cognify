<?php

namespace Domains\Requisition\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Domains\Requisition\Models\RequisitionLineItem
 */
class RequisitionLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit_of_measure,
            'estimatedUnitPrice' => (float) $this->estimated_unit_price,
            'currency' => $this->currency,
            'estimatedLineTotal' => round((float) $this->quantity * (float) $this->estimated_unit_price, 2),
        ];
    }
}

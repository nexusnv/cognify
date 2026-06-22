<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierInvoiceMatchResult */
class SupplierInvoiceMatchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Access pre-loaded relationship to avoid unscoped lookup and N+1 queries
        $lineNumber = $this->supplierInvoiceLine?->line_number;

        return [
            'id' => $this->id,
            'lineNumber' => $lineNumber,
            'matchLevel' => $this->match_level,
            'matchType' => $this->match_type,
            'dimension' => $this->dimension,
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'actualValue' => $this->actual_value !== null ? (string) $this->actual_value : null,
            'tolerancePercentApplied' => $this->tolerance_percent_applied !== null ? (float) $this->tolerance_percent_applied : null,
            'toleranceFloorApplied' => $this->tolerance_floor_applied !== null ? (float) $this->tolerance_floor_applied : null,
            'toleranceCapApplied' => $this->tolerance_cap_applied !== null ? (float) $this->tolerance_cap_applied : null,
            'result' => $this->result,
            'notes' => $this->notes,
        ];
    }
}

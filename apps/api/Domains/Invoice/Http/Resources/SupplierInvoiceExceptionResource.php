<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierInvoiceException
 */
class SupplierInvoiceExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'dimension' => $this->dimension,
            'matchType' => $this->match_type,
            'supplierInvoiceLineId' => $this->supplier_invoice_line_id !== null ? (string) $this->supplier_invoice_line_id : null,
            'purchaseOrderLineId' => $this->purchase_order_line_id !== null ? (string) $this->purchase_order_line_id : null,
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'actualValue' => $this->actual_value !== null ? (string) $this->actual_value : null,
            'status' => $this->status,
            'resolutionType' => $this->resolution_type,
            'resolutionData' => $this->resolution_data,
            'resolvedByUserId' => $this->resolved_by_user_id !== null ? (string) $this->resolved_by_user_id : null,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'escalatedToUserId' => $this->escalated_to_user_id !== null ? (string) $this->escalated_to_user_id : null,
            'escalatedByUserId' => $this->escalated_by_user_id !== null ? (string) $this->escalated_by_user_id : null,
            'escalatedAt' => $this->escalated_at?->toISOString(),
            'escalationNote' => $this->escalation_note,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}

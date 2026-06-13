<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierInvoice
 */
class SupplierInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'vendorId' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
            'number' => $this->number,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->statusState()->value,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'subtotalAmount' => (string) $this->subtotal_amount,
            'taxAmount' => (string) ($this->tax_amount ?? '0.00'),
            'freightAmount' => (string) ($this->freight_amount ?? '0.00'),
            'totalAmount' => (string) $this->total_amount,
            'notes' => $this->notes,
            'capturedByUserId' => $this->captured_by_user_id !== null ? (string) $this->captured_by_user_id : null,
            'capturedAt' => $this->captured_at?->toISOString(),
            'lines' => $this->relationLoaded('lines')
                ? SupplierInvoiceLineResource::collection($this->lines)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
        ];
    }
}

<?php

namespace Domains\Payments\Http\Resources;

use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApPaymentAllocation
 */
class ApPaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'apPaymentHandoffId' => (string) $this->ap_payment_handoff_id,
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'supplierInvoiceNumber' => $this->whenLoaded('supplierInvoice', fn () => $this->supplierInvoice?->invoice_number),
            'allocatedAmount' => (string) $this->allocated_amount,
            'allocationDate' => $this->allocation_date?->toDateString(),
            'paymentReference' => $this->payment_reference,
            'settlementAmount' => $this->settlement_amount !== null ? (string) $this->settlement_amount : null,
            'settlementCurrency' => $this->settlement_currency,
            'voidedAt' => $this->voided_at?->toISOString(),
            'lockVersion' => $this->lock_version,
        ];
    }
}

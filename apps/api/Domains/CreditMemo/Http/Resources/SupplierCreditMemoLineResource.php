<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierCreditMemoLine
 */
class SupplierCreditMemoLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'lineNumber' => (int) $this->line_number,
            'description' => $this->description_snapshot,
            'quantity' => (string) $this->quantity,
            'unitPrice' => (string) $this->unit_price,
            'lineSubtotal' => (string) $this->line_subtotal,
            'taxCode' => $this->tax_code,
            'taxAmount' => (string) $this->tax_amount,
            'purchaseOrderLineId' => $this->purchase_order_line_id !== null ? (string) $this->purchase_order_line_id : null,
            'originalInvoiceLineId' => $this->original_invoice_line_id !== null ? (string) $this->original_invoice_line_id : null,
            'notes' => $this->notes,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

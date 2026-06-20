<?php

namespace Domains\Payments\Http\Resources;

use Domains\Payments\Models\ApPaymentImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApPaymentImport
 */
class ApPaymentImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'batchId' => $this->batch_id,
            'rowIndex' => (int) $this->row_index,
            'handoffNumber' => $this->handoff_number,
            'invoiceNumber' => $this->invoice_number,
            'paymentReference' => $this->payment_reference,
            'allocatedAmount' => $this->allocated_amount !== null ? (string) $this->allocated_amount : null,
            'markFull' => (bool) $this->mark_full,
            'settlementAmount' => $this->settlement_amount !== null ? (string) $this->settlement_amount : null,
            'settlementCurrency' => $this->settlement_currency,
            'paidAt' => $this->paid_at?->toDateString(),
            'settlementMethod' => $this->settlement_method,
            'targetStatus' => $this->target_status,
            'failureCode' => $this->failure_code,
            'failureReason' => $this->failure_reason,
            'voidReason' => $this->void_reason,
            'status' => $this->status,
            'matchError' => $this->match_error,
            'matchedHandoffId' => $this->matched_handoff_id !== null ? (string) $this->matched_handoff_id : null,
            'matchedInvoiceId' => $this->matched_invoice_id !== null ? (string) $this->matched_invoice_id : null,
            'reconciledAt' => $this->reconciled_at?->toISOString(),
            'reconciledByUserId' => $this->reconciled_by_user_id !== null ? (string) $this->reconciled_by_user_id : null,
            'importedByUserId' => (string) $this->imported_by_user_id,
            'importedAt' => $this->imported_at?->toISOString(),
            'lockVersion' => $this->lock_version,
        ];
    }
}

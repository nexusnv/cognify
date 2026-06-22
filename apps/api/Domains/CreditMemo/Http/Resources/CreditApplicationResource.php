<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\CreditApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditApplication
 */
class CreditApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'appliedAmount' => (string) $this->applied_amount,
            'applicationDate' => $this->application_date?->toDateString(),
            'appliedByUserId' => $this->applied_by_user_id !== null ? (string) $this->applied_by_user_id : null,
            'notes' => $this->notes,
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidedByUserId' => $this->voided_by_user_id !== null ? (string) $this->voided_by_user_id : null,
            'voidReason' => $this->void_reason,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

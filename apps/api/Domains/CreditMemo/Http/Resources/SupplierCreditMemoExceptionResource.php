<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierCreditMemoException
 */
class SupplierCreditMemoExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'exceptionType' => $this->exception_type,
            'severity' => $this->severity,
            'description' => $this->description,
            'resolutionType' => $this->resolution_type,
            'resolutionNotes' => $this->resolution_notes,
            'resolvedByUserId' => $this->resolved_by_user_id !== null ? (string) $this->resolved_by_user_id : null,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'acknowledgedByUserId' => $this->acknowledged_by_user_id !== null ? (string) $this->acknowledged_by_user_id : null,
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'escalatedByUserId' => $this->escalated_by_user_id !== null ? (string) $this->escalated_by_user_id : null,
            'escalatedAt' => $this->escalated_at?->toISOString(),
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'adjustedValue' => $this->adjusted_value !== null ? (string) $this->adjusted_value : null,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

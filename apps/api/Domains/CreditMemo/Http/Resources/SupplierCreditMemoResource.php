<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin SupplierCreditMemo
 */
class SupplierCreditMemoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $appliedAmount = app(CreditApplicationSumCalculator::class)->sumForCreditMemo($this->resource);
        $remainingAmount = bcsub((string) $this->total_amount, $appliedAmount, 4);

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'vendorId' => (string) $this->vendor_id,
            'vendorName' => $this->relationLoaded('vendor') ? $this->vendor?->name : null,
            'originalInvoiceId' => $this->original_invoice_id !== null ? (string) $this->original_invoice_id : null,
            'originalInvoiceNumber' => $this->relationLoaded('originalInvoice') ? $this->originalInvoice?->invoice_number : null,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'currency' => $this->currency,
            'subtotalAmount' => (string) $this->subtotal_amount,
            'taxAmount' => (string) $this->tax_amount,
            'freightAmount' => (string) $this->freight_amount,
            'totalAmount' => (string) $this->total_amount,
            'appliedAmount' => $appliedAmount,
            'remainingAmount' => $remainingAmount,
            'creditDate' => $this->credit_date?->toDateString(),
            'notes' => $this->notes,
            'capturedByUserId' => $this->captured_by_user_id !== null ? (string) $this->captured_by_user_id : null,
            'capturedAt' => $this->captured_at?->toISOString(),
            'submittedByUserId' => $this->submitted_by_user_id !== null ? (string) $this->submitted_by_user_id : null,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'approvedByUserId' => $this->approved_by_user_id !== null ? (string) $this->approved_by_user_id : null,
            'approvedAt' => $this->approved_at?->toISOString(),
            'postedByUserId' => $this->posted_by_user_id !== null ? (string) $this->posted_by_user_id : null,
            'postedAt' => $this->posted_at?->toISOString(),
            'voidedByUserId' => $this->voided_by_user_id !== null ? (string) $this->voided_by_user_id : null,
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidReason' => $this->void_reason,
            'approvalInstanceId' => $this->approval_instance_id !== null ? (string) $this->approval_instance_id : null,
            'stpEligible' => $this->stp_eligible,
            'stpProcessedAt' => $this->stp_processed_at?->toISOString(),
            'lockVersion' => $this->lock_version,
            'lines' => $this->relationLoaded('lines')
                ? SupplierCreditMemoLineResource::collection($this->lines)
                : null,
            'applications' => $this->relationLoaded('applications')
                ? CreditApplicationResource::collection($this->applications)
                : null,
            'exceptions' => $this->relationLoaded('exceptions')
                ? SupplierCreditMemoExceptionResource::collection($this->exceptions)
                : null,
            'permissions' => [
                'canEdit' => Gate::allows('update', $this->resource),
                'canSubmit' => Gate::allows('submit', $this->resource),
                'canPost' => Gate::allows('post', $this->resource),
                'canApply' => Gate::allows('apply', $this->resource),
                'canVoidApplication' => Gate::allows('voidApplication', $this->resource),
                'canVoidCreditMemo' => Gate::allows('void', $this->resource),
                'canResolveException' => Gate::allows('view', $this->resource),
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

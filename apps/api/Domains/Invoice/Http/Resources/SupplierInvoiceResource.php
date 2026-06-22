<?php

namespace Domains\Invoice\Http\Resources;

use Domains\CreditMemo\Http\Resources\CreditApplicationResource;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

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
        $paidSum = (string) ApPaymentAllocation::query()
            ->where('supplier_invoice_id', $this->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');
        $creditSum = app(CreditApplicationSumCalculator::class)->sumForInvoice($this->resource);
        $outstanding = bcsub(
            bcsub((string) $this->total_amount, $paidSum, 4),
            $creditSum,
            4
        );

        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'vendorId' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
            'number' => $this->number,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->statusState()->value,
            'paymentStatus' => $this->payment_status?->value,
            'paidAmount' => $paidSum,
            'creditAppliedAmount' => $creditSum,
            'outstandingAmount' => $outstanding,
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
            'purchaseOrder' => [
                'id' => (string) $this->purchase_order_id,
                'number' => $this->purchaseOrder?->number,
            ],
            'vendor' => [
                'id' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
                'name' => $this->vendor?->name,
            ],
            'attachmentCount' => $this->relationLoaded('attachments')
                ? $this->attachments->count()
                : (int) ($this->attachments_count ?? $this->attachments()->count()),
            'reviewStartedByUserId' => $this->review_started_by_user_id !== null ? (string) $this->review_started_by_user_id : null,
            'reviewStartedAt' => $this->review_started_at?->toISOString(),
            'reviewedByUserId' => $this->reviewed_by_user_id !== null ? (string) $this->reviewed_by_user_id : null,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewNotes' => $this->review_notes,
            'reviewChecklist' => $this->review_checklist,
            'reviewChecklistSummary' => SupplierInvoiceReviewChecklistData::summary($this->review_checklist),
            'matchingStatus' => $this->matching_status,
            'matchSummary' => $this->match_summary,
            'exceptionSummary' => $this->exception_summary,
            'reviewBlockers' => $this->review_blockers ?? [],
            'reviewBlockerCount' => count($this->review_blockers ?? []),
            'lines' => $this->relationLoaded('lines')
                ? SupplierInvoiceLineResource::collection($this->lines)->resolve()
                : [],
            'creditApplications' => $this->relationLoaded('creditApplications')
                ? CreditApplicationResource::collection($this->creditApplications)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
            'approvalInstanceId' => $this->approval_instance_id !== null ? (string) $this->approval_instance_id : null,
            'approvalSubmittedByUserId' => $this->approval_submitted_by_user_id !== null ? (string) $this->approval_submitted_by_user_id : null,
            'approvalSubmittedAt' => $this->approval_submitted_at?->toISOString(),
            'approvedByUserId' => $this->approved_by_user_id !== null ? (string) $this->approved_by_user_id : null,
            'approvedAt' => $this->approved_at?->toISOString(),
            'rejectedByUserId' => $this->rejected_by_user_id !== null ? (string) $this->rejected_by_user_id : null,
            'rejectedAt' => $this->rejected_at?->toISOString(),
            'rejectedReason' => $this->rejected_reason,
            'changesRequestedByUserId' => $this->changes_requested_by_user_id !== null ? (string) $this->changes_requested_by_user_id : null,
            'changesRequestedAt' => $this->changes_requested_at?->toISOString(),
            'changesRequestedReason' => $this->changes_requested_reason,
            'changesRequestedFields' => $this->changes_requested_fields,
            'stpEligible' => $this->stp_eligible,
            'stpProcessedAt' => $this->stp_processed_at?->toISOString(),
            'permissions' => [
                'canReview' => Gate::allows('review', $this->resource),
            ],
        ];
    }
}

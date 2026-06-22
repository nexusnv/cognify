<?php

namespace Domains\Invoice\Http\Resources;

use Domains\CreditMemo\Models\CreditApplication;
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
class SupplierInvoiceQueueResource extends JsonResource
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

        $applications = CreditApplication::query()
            ->where('supplier_invoice_id', $this->id)
            ->get();

        $appliedCreditMemos = $applications
            ->groupBy('supplier_credit_memo_id')
            ->map(function ($group) {
                $first = $group->first();
                $nonVoided = $group->whereNull('voided_at');
                return [
                    'id' => (string) $first->supplier_credit_memo_id,
                    'number' => $first->creditMemo?->number,
                    'appliedAmount' => (string) $nonVoided->sum('applied_amount'),
                    'applicationDate' => $first->application_date?->toDateString(),
                    'voidedAt' => $nonVoided->isEmpty() && $group->isNotEmpty()
                        ? $group->last()->voided_at?->toISOString()
                        : null,
                ];
            })
            ->values()
            ->toArray();

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->statusState()->value,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'totalAmount' => (string) $this->total_amount,
            'vendor' => [
                'id' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
                'name' => $this->vendor?->name,
            ],
            'purchaseOrder' => [
                'id' => (string) $this->purchase_order_id,
                'number' => $this->purchaseOrder?->number,
            ],
            'attachmentCount' => (int) ($this->attachments_count ?? $this->attachments()->count()),
            'reviewChecklistSummary' => SupplierInvoiceReviewChecklistData::summary($this->review_checklist),
            'reviewBlockerCount' => count($this->review_blockers ?? []),
            'reviewStartedAt' => $this->review_started_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'matchingStatus' => $this->matching_status,
            'exceptionSummary' => $this->exception_summary,
            'lockVersion' => $this->lock_version,
            'permissions' => [
                'canReview' => Gate::allows('review', $this->resource),
            ],
            'paymentStatus' => $this->payment_status?->value,
            'paymentStatusLabel' => $this->payment_status?->label(),
            'paidAmount' => $paidSum,
            'creditAppliedAmount' => $creditSum,
            'outstandingAmount' => $outstanding,
            'appliedCreditMemos' => $appliedCreditMemos,
            'paymentOnHoldReason' => $this->payment_on_hold_reason,
            'paymentEligibleAt' => $this->payment_eligible_at?->toISOString(),
            'paymentOnHoldAt' => $this->payment_on_hold_at?->toISOString(),
            'paymentOnHoldByUserId' => $this->payment_on_hold_by_user_id ? (string) $this->payment_on_hold_by_user_id : null,
            'activeHandoffId' => $this->relationLoaded('activeHandoff') && $this->activeHandoff->isNotEmpty()
                ? (string) $this->activeHandoff->first()->id : null,
            'activeHandoffNumber' => $this->relationLoaded('activeHandoff') && $this->activeHandoff->isNotEmpty()
                ? $this->activeHandoff->first()->number : null,
            // NOTE: activeHandoff should be loaded with deterministic ordering
            // (e.g. ->orderBy('created_at', 'desc')) in the model relationship or
            // query builder to ensure consistent first() results.
        ];
    }
}

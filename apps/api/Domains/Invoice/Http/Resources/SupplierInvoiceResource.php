<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
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
            'lockVersion' => $this->lock_version,
            'permissions' => [
                'canReview' => Gate::allows('review', $this->resource),
            ],
        ];
    }
}

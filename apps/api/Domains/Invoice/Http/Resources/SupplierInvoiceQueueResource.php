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
class SupplierInvoiceQueueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
        ];
    }
}

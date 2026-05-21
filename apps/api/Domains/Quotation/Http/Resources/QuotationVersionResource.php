<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationVersion
 */
class QuotationVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vendorPortal = (bool) $request->attributes->get('vendor_portal', false);
        $previousVersionId = data_get($this->metadata, 'previousVersionId');

        return [
            'id' => (string) $this->id,
            'quotationId' => (string) $this->quotation_id,
            'versionNumber' => $this->version_number,
            'status' => $this->status?->value ?? $this->status,
            'source' => $this->submission_source?->value ?? $this->submission_source,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'submittedByUser' => $vendorPortal || ! $this->submittedByUser ? null : [
                'id' => (string) $this->submittedByUser->id,
                'name' => $this->submittedByUser->name,
            ],
            'submittedByVendorContact' => $this->submitted_by_vendor_contact,
            'isCurrent' => (bool) $this->is_current,
            'supersededAt' => $this->superseded_at?->toISOString(),
            'previousVersionId' => $previousVersionId === null ? null : (string) $previousVersionId,
            'manualEntry' => [
                'quotationReference' => $this->quotation_reference,
                'quotedAt' => $this->quoted_at?->toDateString(),
                'validUntil' => $this->valid_until?->toDateString(),
                'currency' => $this->currency,
                'subtotalAmount' => $this->subtotal_amount,
                'taxAmount' => $this->tax_amount,
                'freightAmount' => $this->freight_amount,
                'discountAmount' => $this->discount_amount,
                'totalAmount' => $this->total_amount,
                'paymentTerms' => $this->payment_terms,
                'deliveryTerms' => $this->delivery_terms,
                'leadTimeDays' => $this->lead_time_days,
                'warrantyTerms' => $this->warranty_terms,
                'exclusions' => $this->exclusions,
                'complianceNotes' => $this->compliance_notes,
                'buyerNotes' => $vendorPortal ? null : $this->buyer_notes,
                'vendorNotes' => $this->vendor_notes,
            ],
            'lineItems' => $this->relationLoaded('lineItems')
                ? QuotationVersionLineItemResource::collection($this->lineItems)
                : [],
            'attachments' => collect($this->attachment_snapshots ?? [])
                ->map(function (array $attachment) use ($vendorPortal): array {
                    if ($vendorPortal) {
                        $attachment['uploadedBy'] = null;
                    }

                    return $attachment;
                })
                ->values()
                ->all(),
            'attachmentCount' => count($this->attachment_snapshots ?? []),
            'completeness' => [
                'isComplete' => (bool) $this->manual_entry_complete,
                'missingFields' => $this->manual_entry_missing_fields ?? [],
                'lineItemCount' => $this->relationLoaded('lineItems') ? $this->lineItems->count() : 0,
            ],
            'permissions' => [
                'canEdit' => false,
                'canCreateRevision' => $vendorPortal
                    ? (bool) $request->attributes->get('vendor_portal_can_edit_quotation', false)
                    : ($request->user()?->can('view', $this->quotation?->rfq) ?? false),
            ],
        ];
    }
}

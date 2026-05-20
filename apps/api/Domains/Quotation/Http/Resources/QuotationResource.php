<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Attachment\Http\Resources\AttachmentResource;
use Domains\Quotation\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quotation
 */
class QuotationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Quotation $quotation */
        $quotation = $this->resource;

        $vendorPortal = (bool) $request->attributes->get('vendor_portal', false);
        $redactInternalIdentity = $vendorPortal || ! $request->user();
        $canEditManualEntry = $vendorPortal
            ? (bool) $request->attributes->get('vendor_portal_can_edit_quotation', false)
            : ($request->user()?->can('view', $quotation->rfq) ?? false);
        $canUploadAttachment = $vendorPortal
            ? $canEditManualEntry
            : ($request->user()?->can('view', $quotation->rfq) ?? false);
        $canViewAttachments = $vendorPortal
            ? $canEditManualEntry
            : ($request->user()?->can('view', $quotation->rfq) ?? false);

        return [
            'id' => (string) $quotation->id,
            'rfqId' => (string) $quotation->rfq_id,
            'vendorId' => (string) $quotation->vendor_id,
            'rfqInvitationId' => (string) $quotation->rfq_invitation_id,
            'number' => $quotation->number,
            'status' => $quotation->status?->value ?? $quotation->status,
            'submissionSource' => $quotation->submission_source?->value,
            'submittedAt' => $quotation->submitted_at?->toISOString(),
            'latestReceivedAt' => $quotation->latest_received_at?->toISOString(),
            'fileCount' => $quotation->file_count,
            'submittedByUser' => ! $redactInternalIdentity && $quotation->submittedByUser ? [
                'id' => (string) $quotation->submittedByUser->id,
                'name' => $quotation->submittedByUser->name,
            ] : null,
            'submittedByVendorContact' => $quotation->submitted_by_vendor_contact,
            'manualEntry' => [
                'quotationReference' => $quotation->quotation_reference,
                'quotedAt' => $quotation->quoted_at?->toDateString(),
                'validUntil' => $quotation->valid_until?->toDateString(),
                'currency' => $quotation->currency,
                'subtotalAmount' => $quotation->subtotal_amount,
                'taxAmount' => $quotation->tax_amount,
                'freightAmount' => $quotation->freight_amount,
                'discountAmount' => $quotation->discount_amount,
                'totalAmount' => $quotation->total_amount,
                'paymentTerms' => $quotation->payment_terms,
                'deliveryTerms' => $quotation->delivery_terms,
                'leadTimeDays' => $quotation->lead_time_days,
                'warrantyTerms' => $quotation->warranty_terms,
                'exclusions' => $quotation->exclusions,
                'complianceNotes' => $quotation->compliance_notes,
                'buyerNotes' => $vendorPortal ? null : $quotation->buyer_notes,
                'vendorNotes' => $quotation->vendor_notes,
            ],
            'lineItems' => $quotation->relationLoaded('lineItems')
                ? QuotationLineItemResource::collection($quotation->lineItems)
                : [],
            'completeness' => [
                'isComplete' => (bool) $quotation->manual_entry_complete,
                'missingFields' => $quotation->manual_entry_missing_fields ?? [],
                'lineItemCount' => $quotation->relationLoaded('lineItems') ? $quotation->lineItems->count() : 0,
            ],
            'attachments' => $quotation->relationLoaded('attachments')
                ? AttachmentResource::collection($quotation->attachments)
                : [],
            'permissions' => [
                'canUploadAttachment' => $canUploadAttachment,
                'canViewAttachments' => $canViewAttachments,
                'canEditManualEntry' => $canEditManualEntry,
            ],
        ];
    }
}

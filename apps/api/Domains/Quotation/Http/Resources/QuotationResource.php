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

        $redactInternalIdentity = (bool) $request->attributes->get('vendor_portal', false) || ! $request->user();

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
            'attachments' => $quotation->relationLoaded('attachments')
                ? AttachmentResource::collection($quotation->attachments)
                : [],
            'permissions' => [
                'canUploadAttachment' => $request->user()?->can('view', $quotation->rfq) ?? false,
                'canViewAttachments' => $request->user()?->can('view', $quotation->rfq) ?? false,
            ],
        ];
    }
}

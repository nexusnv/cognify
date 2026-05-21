<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationNormalizationAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationNormalizationAttachment
 */
class QuotationNormalizationAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'quotationVersionAttachmentId' => $this->quotation_version_attachment_id,
            'filename' => $this->filename,
            'mimeType' => $this->mime_type,
            'extension' => $this->extension,
            'sizeBytes' => $this->size_bytes,
            'checksumSha256' => $this->checksum_sha256,
            'available' => (bool) $this->available,
            'source' => $this->source,
            'uploadedAt' => $this->uploaded_at?->toISOString(),
            'evidenceRole' => $this->evidence_role,
            'issueSummary' => $this->issue_summary,
        ];
    }
}

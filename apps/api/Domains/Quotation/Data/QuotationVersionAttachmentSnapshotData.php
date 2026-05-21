<?php

namespace Domains\Quotation\Data;

use Domains\Attachment\Models\Attachment;

class QuotationVersionAttachmentSnapshotData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromAttachment(Attachment $attachment): array
    {
        $uploadedBy = $attachment->relationLoaded('uploader') ? $attachment->uploader : null;

        return [
            'id' => (string) $attachment->id,
            'filename' => $attachment->original_filename,
            'mimeType' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'sizeBytes' => $attachment->size_bytes,
            'checksumSha256' => $attachment->checksum_sha256,
            'previewable' => (bool) $attachment->previewable,
            'uploadedBy' => $uploadedBy ? [
                'id' => (string) $uploadedBy->id,
                'name' => $uploadedBy->name,
            ] : null,
            'createdAt' => $attachment->created_at?->toISOString(),
            'available' => true,
        ];
    }
}

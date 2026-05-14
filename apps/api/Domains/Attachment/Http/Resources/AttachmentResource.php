<?php

namespace Domains\Attachment\Http\Resources;

use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attachment
 */
class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Attachment $attachment */
        $attachment = $this->resource;

        return [
            'id' => (string) $attachment->id,
            'parentType' => $attachment->attachable_type === Requisition::class ? 'requisition' : 'unknown',
            'parentId' => (string) $attachment->attachable_id,
            'filename' => $attachment->original_filename,
            'mimeType' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'sizeBytes' => $attachment->size_bytes,
            'previewable' => $attachment->previewable,
            'uploadedBy' => $attachment->uploader ? [
                'id' => (string) $attachment->uploader->id,
                'name' => $attachment->uploader->name,
            ] : null,
            'createdAt' => $attachment->created_at?->toISOString(),
            'permissions' => [
                'canPreview' => $request->user()?->can('preview', $attachment) ?? false,
                'canDownload' => $request->user()?->can('download', $attachment) ?? false,
                'canDelete' => $request->user()?->can('delete', $attachment) ?? false,
            ],
        ];
    }
}

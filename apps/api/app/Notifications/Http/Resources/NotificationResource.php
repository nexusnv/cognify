<?php

namespace App\Notifications\Http\Resources;

use App\Audit\AuditSubject;
use App\Notifications\NotificationRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationRecord
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'href' => $this->href,
            'priority' => $this->priority,
            'readAt' => $this->read_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => $this->actor->name,
            ] : null,
            'subject' => $this->subject_type ? [
                'type' => AuditSubject::typeFor($this->subject_type),
                'id' => (string) $this->subject_id,
                'label' => (string) ($this->metadata['subjectLabel'] ?? $this->subject_id),
            ] : null,
            'metadata' => $this->metadata ?? [],
        ];
    }
}

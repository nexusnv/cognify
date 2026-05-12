<?php

namespace App\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->event_id ?? (string) $this->id,
            'action' => $this->action ?? $this->event_type,
            'message' => $this->message(),
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ] : null,
            'subject' => [
                'type' => AuditSubject::typeFor($this->subject_type),
                'id' => (string) $this->subject_id,
                'display' => $this->subject_display,
            ],
            'metadata' => AuditPayloadSanitizer::sanitize($this->metadata ?? []) ?? [],
            'before' => AuditPayloadSanitizer::sanitize($this->before),
            'after' => AuditPayloadSanitizer::sanitize($this->after),
            'occurredAt' => $this->occurred_at?->toISOString(),
            'requestId' => $this->request_id,
        ];
    }

    private function message(): string
    {
        return match ($this->action ?? $this->event_type) {
            'requisition.created' => 'Draft created',
            'requisition.updated' => 'Draft updated',
            'requisition.submitted' => 'Submitted for review',
            default => $this->action ?? $this->event_type,
        };
    }
}

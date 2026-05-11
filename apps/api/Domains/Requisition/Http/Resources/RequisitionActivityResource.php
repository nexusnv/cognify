<?php

namespace Domains\Requisition\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Audit\AuditEvent
 */
class RequisitionActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->event_type,
            'message' => $this->message(),
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ] : null,
            'metadata' => $this->metadata ?? [],
            'occurredAt' => $this->occurred_at?->toISOString(),
        ];
    }

    private function message(): string
    {
        return match ($this->event_type) {
            'requisition.created' => 'Draft created',
            'requisition.updated' => 'Draft updated',
            'requisition.submitted' => 'Submitted for review',
            default => $this->event_type,
        };
    }
}

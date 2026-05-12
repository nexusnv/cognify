<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AuditRecorder
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        AuditEventData|Tenant $data,
        ?User $actor = null,
        ?string $eventType = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditEvent {
        if (! $data instanceof AuditEventData && ($eventType === null || $subject === null)) {
            throw new InvalidArgumentException('Audit event requires an action and subject.');
        }

        $eventData = $data instanceof AuditEventData
            ? $data
            : new AuditEventData(
                tenant: $data,
                actor: $actor,
                action: (string) $eventType,
                subject: $subject,
                metadata: $metadata,
            );

        $request = request();

        return AuditEvent::query()->create([
            'tenant_id' => $eventData->tenant->id,
            'actor_id' => $eventData->actor?->id,
            // Write both fields while event_type remains a compatibility column.
            'event_type' => $eventData->action,
            'action' => $eventData->action,
            'subject_type' => $eventData->subject::class,
            'subject_id' => $eventData->subject->getKey(),
            'subject_display' => $eventData->subjectDisplay ?? AuditSubject::displayFor($eventData->subject),
            'metadata' => AuditPayloadSanitizer::sanitize($eventData->metadata),
            'before' => AuditPayloadSanitizer::sanitize($eventData->before),
            'after' => AuditPayloadSanitizer::sanitize($eventData->after),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
            'occurred_at' => now(),
        ]);
    }
}

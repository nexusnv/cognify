<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(Tenant $tenant, ?User $actor, string $eventType, Model $subject, array $metadata = []): AuditEvent
    {
        return AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}

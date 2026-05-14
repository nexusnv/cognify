<?php

namespace Database\Seeders\Demo;

use App\Audit\AuditEvent;

class DemoAuditSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $actor = $context->users->get('requester');
        $requisition = $context->requisitions->get('office-refresh');

        AuditEvent::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'action' => 'requisition.submitted',
                'subject_type' => $requisition::class,
                'subject_id' => $requisition->id,
            ],
            [
                'actor_id' => $actor->id,
                'subject_display' => $requisition->number,
                'metadata' => ['demo' => true],
                'after' => ['status' => 'submitted'],
                'occurred_at' => now(),
            ],
        );
    }
}

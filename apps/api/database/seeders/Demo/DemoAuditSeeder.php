<?php

namespace Database\Seeders\Demo;

use App\Audit\AuditEvent;

class DemoAuditSeeder
{
    private const OCCURRED_AT = '2026-05-15 09:05:00';

    public function run(DemoSeedContext $context): void
    {
        $this->seedSubmittedEvent($context, 'acme', 'requester', 'office-refresh');
        $this->seedSubmittedEvent($context, 'northwind', 'vendor_manager', 'warehouse-supplies');
    }

    private function seedSubmittedEvent(
        DemoSeedContext $context,
        string $tenantKey,
        string $actorKey,
        string $requisitionKey,
    ): void {
        $tenant = $context->tenants->get($tenantKey);
        $actor = $context->users->get($actorKey);
        $requisition = $context->requisitions->get($requisitionKey);

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
                'occurred_at' => self::OCCURRED_AT,
            ],
        );
    }
}

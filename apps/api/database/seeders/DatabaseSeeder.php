<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAttachmentSeeder;
use Database\Seeders\Demo\DemoAuditSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoRequisitionSeeder;
use Database\Seeders\Demo\DemoRoadmapPreviewSeeder;
use Database\Seeders\Demo\DemoSeedContext;
use Database\Seeders\Demo\DemoTenantSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Domains\Demo\Models\DemoSeedRun;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const SEEDED_AT = '2026-05-15 09:00:00';

    public function run(): void
    {
        $context = new DemoSeedContext();

        app(DemoTenantSeeder::class)->run($context);
        app(DemoUserSeeder::class)->run($context);
        app(DemoRequisitionSeeder::class)->run($context);
        app(DemoRoadmapPreviewSeeder::class)->run($context);
        app(DemoAttachmentSeeder::class)->run($context);
        app(DemoAuditSeeder::class)->run($context);
        app(DemoNotificationSeeder::class)->run($context);

        DemoSeedRun::query()->updateOrCreate(
            ['name' => 'local-demo'],
            [
                'seeded_at' => self::SEEDED_AT,
                'metadata' => [
                    'tenants' => $context->tenants->count(),
                    'users' => $context->users->count(),
                    'requisitions' => $context->requisitions->count(),
                    'vendors' => $context->vendors->count(),
                    'projects' => $context->projects->count(),
                    'rfqs' => $context->rfqs->count(),
                    'quotations' => $context->quotations->count(),
                    'approval_tasks' => $context->approvalTasks->count(),
                    'awards' => $context->awards->count(),
                ],
            ],
        );
    }
}

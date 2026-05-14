<?php

namespace Database\Seeders\Demo;

use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecord;
use App\Notifications\NotificationRecorder;

class DemoNotificationSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');

        NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('title', 'Local demo data is ready')
            ->delete();

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: collect([
                $context->users->get('admin'),
                $context->users->get('buyer'),
            ]),
            data: new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_SYSTEM_ANNOUNCEMENT,
                title: 'Local demo data is ready',
                body: 'The Cognify demo workspace includes requisitions, vendors, RFQs, quotations, approvals, and awards.',
                href: '/system',
                metadata: ['demo' => true],
            ),
        );
    }
}

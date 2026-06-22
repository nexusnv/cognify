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
        $this->seedAnnouncement($context, 'acme', ['admin', 'buyer']);
        $this->seedAnnouncement($context, 'northwind', ['vendor_manager']);
        $this->seedApprovalNotifications($context);
    }

    /**
     * @param  list<string>  $recipientKeys
     */
    private function seedAnnouncement(DemoSeedContext $context, string $tenantKey, array $recipientKeys): void
    {
        $tenant = $context->tenants->get($tenantKey);

        NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('title', 'Local demo data is ready')
            ->delete();

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: collect($recipientKeys)->map(fn (string $key) => $context->users->get($key)),
            data: new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_SYSTEM_ANNOUNCEMENT,
                title: 'Local demo data is ready',
                body: 'The Cognify demo workspace includes requisitions, vendors, RFQs, quotations, approvals, and awards.',
                href: '/system',
                metadata: ['demo' => true],
            ),
        );
    }

    private function seedApprovalNotifications(DemoSeedContext $context): void
    {
        foreach (['security-audit-approval', 'office-refresh-delegated'] as $taskKey) {
            $task = $context->approvalTasks->get($taskKey);

            if ($task === null || $task->assignee === null || $task->subject === null) {
                continue;
            }

            NotificationRecord::query()
                ->where('tenant_id', $task->tenant_id)
                ->where('title', $task->title)
                ->delete();

            app(NotificationRecorder::class)->record(
                tenant: $task->tenant,
                recipients: collect([$task->assignee]),
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_APPROVAL_TASK_ASSIGNED,
                    title: $task->title,
                    body: $task->subject->title ?? 'Approval task assigned.',
                    href: "/approvals/tasks/{$task->id}",
                    subject: $task->subject,
                    subjectLabel: $task->subject->number ?? null,
                    metadata: ['demo' => true, 'approvalTaskId' => (string) $task->id],
                ),
            );
        }
    }
}

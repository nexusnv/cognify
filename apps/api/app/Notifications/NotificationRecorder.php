<?php

namespace App\Notifications;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Support\Collection;

class NotificationRecorder
{
    /**
     * @param  iterable<User>  $recipients
     */
    public function record(Tenant $tenant, iterable $recipients, NotificationData $data): void
    {
        Collection::make($recipients)
            ->filter(fn (User $recipient): bool => $recipient->tenants()->whereKey($tenant->id)->exists())
            ->unique(fn (User $recipient): int => $recipient->id)
            ->filter(fn (User $recipient): bool => $this->allowsInApp($recipient, $data->type))
            ->each(function (User $recipient) use ($tenant, $data): void {
                NotificationRecord::query()->create([
                    'tenant_id' => $tenant->id,
                    'recipient_id' => $recipient->id,
                    'actor_id' => $data->actor?->id,
                    'type' => $data->type,
                    'title' => $data->title,
                    'body' => $data->body,
                    'href' => $data->href,
                    'subject_type' => $data->subject?->getMorphClass(),
                    'subject_id' => $data->subject?->getKey(),
                    'metadata' => array_filter([
                        ...$data->metadata,
                        'subjectLabel' => $data->subjectLabel,
                    ], fn (mixed $value): bool => $value !== null),
                    'priority' => $data->priority,
                ]);
            });
    }

    private function allowsInApp(User $recipient, string $event): bool
    {
        if (! in_array($event, NotificationPreferenceDefaults::EVENTS, true)) {
            return false;
        }

        $preferences = NotificationPreferenceDefaults::merge($recipient->notification_preferences);

        return $preferences[$event]['inApp'] ?? true;
    }
}

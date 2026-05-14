<?php

namespace App\Notifications;

class NotificationPreferenceDefaults
{
    public const EVENT_REQUISITION_SUBMITTED = 'requisition.submitted';
    public const EVENT_ATTACHMENT_UPLOADED = 'attachment.uploaded';
    public const EVENT_SYSTEM_ANNOUNCEMENT = 'system.announcement';

    public const EVENTS = [
        self::EVENT_REQUISITION_SUBMITTED,
        self::EVENT_ATTACHMENT_UPLOADED,
        self::EVENT_SYSTEM_ANNOUNCEMENT,
    ];

    /**
     * @return array<string, array{inApp: bool}>
     */
    public static function defaults(): array
    {
        return [
            self::EVENT_REQUISITION_SUBMITTED => ['inApp' => true],
            self::EVENT_ATTACHMENT_UPLOADED => ['inApp' => true],
            self::EVENT_SYSTEM_ANNOUNCEMENT => ['inApp' => true],
        ];
    }

    /**
     * @param array<string, mixed>|null $preferences
     * @return array<string, array{inApp: bool}>
     */
    public static function merge(?array $preferences): array
    {
        $merged = self::defaults();

        foreach ($preferences ?? [] as $event => $channels) {
            if (in_array($event, self::EVENTS, true) && is_array($channels) && array_key_exists('inApp', $channels)) {
                $merged[$event]['inApp'] = (bool) $channels['inApp'];
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'notificationPreferences' => [
                'sometimes',
                'array',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (array_keys((array) $value) as $event) {
                        if (! in_array($event, self::EVENTS, true)) {
                            $fail("The {$attribute} field contains an unsupported notification event.");
                        }
                    }
                },
            ],
            'notificationPreferences.*' => ['array:inApp'],
            'notificationPreferences.*.inApp' => ['required', 'boolean'],
        ];
    }
}

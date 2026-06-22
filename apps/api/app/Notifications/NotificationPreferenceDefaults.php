<?php

namespace App\Notifications;

class NotificationPreferenceDefaults
{
    public const EVENT_REQUISITION_SUBMITTED = 'requisition.submitted';

    public const EVENT_REQUISITION_CHANGES_REQUESTED = 'requisition.changes_requested';

    public const EVENT_REQUISITION_RESUBMITTED = 'requisition.resubmitted';

    public const EVENT_REQUISITION_WITHDRAWN = 'requisition.withdrawn';

    public const EVENT_REQUISITION_CANCELLED = 'requisition.cancelled';

    public const EVENT_APPROVAL_TASK_ASSIGNED = 'approval.task_assigned';

    public const EVENT_ATTACHMENT_UPLOADED = 'attachment.uploaded';

    public const EVENT_COLLABORATION_MENTIONED = 'collaboration.mentioned';

    public const EVENT_QUOTATION_NORMALIZATION_FAILED = 'quotation_normalization.failed';

    public const EVENT_QUOTATION_NORMALIZATION_NEEDS_REVIEW = 'quotation_normalization.needs_review';

    public const EVENT_QUOTATION_NORMALIZATION_APPROVED = 'quotation_normalization.approved';

    public const EVENT_SYSTEM_ANNOUNCEMENT = 'system.announcement';

    public const EVENTS = [
        self::EVENT_REQUISITION_SUBMITTED,
        self::EVENT_REQUISITION_CHANGES_REQUESTED,
        self::EVENT_REQUISITION_RESUBMITTED,
        self::EVENT_REQUISITION_WITHDRAWN,
        self::EVENT_REQUISITION_CANCELLED,
        self::EVENT_APPROVAL_TASK_ASSIGNED,
        self::EVENT_ATTACHMENT_UPLOADED,
        self::EVENT_COLLABORATION_MENTIONED,
        self::EVENT_QUOTATION_NORMALIZATION_FAILED,
        self::EVENT_QUOTATION_NORMALIZATION_NEEDS_REVIEW,
        self::EVENT_QUOTATION_NORMALIZATION_APPROVED,
        self::EVENT_SYSTEM_ANNOUNCEMENT,
    ];

    /**
     * @return array<string, array{inApp: bool}>
     */
    public static function defaults(): array
    {
        return [
            self::EVENT_REQUISITION_SUBMITTED => ['inApp' => true],
            self::EVENT_REQUISITION_CHANGES_REQUESTED => ['inApp' => true],
            self::EVENT_REQUISITION_RESUBMITTED => ['inApp' => true],
            self::EVENT_REQUISITION_WITHDRAWN => ['inApp' => true],
            self::EVENT_REQUISITION_CANCELLED => ['inApp' => true],
            self::EVENT_APPROVAL_TASK_ASSIGNED => ['inApp' => true],
            self::EVENT_ATTACHMENT_UPLOADED => ['inApp' => true],
            self::EVENT_COLLABORATION_MENTIONED => ['inApp' => true],
            self::EVENT_QUOTATION_NORMALIZATION_FAILED => ['inApp' => true],
            self::EVENT_QUOTATION_NORMALIZATION_NEEDS_REVIEW => ['inApp' => true],
            self::EVENT_QUOTATION_NORMALIZATION_APPROVED => ['inApp' => true],
            self::EVENT_SYSTEM_ANNOUNCEMENT => ['inApp' => true],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $preferences
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

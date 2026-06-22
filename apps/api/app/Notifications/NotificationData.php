<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

readonly class NotificationData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body = null,
        public ?string $href = null,
        public ?Model $subject = null,
        public ?string $subjectLabel = null,
        public array $metadata = [],
        public string $priority = 'normal',
        public ?User $actor = null,
    ) {}
}

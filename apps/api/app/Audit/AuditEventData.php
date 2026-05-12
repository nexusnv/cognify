<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditEventData
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?User $actor,
        public readonly string $action,
        public readonly Model $subject,
        public readonly array $metadata = [],
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly ?string $subjectDisplay = null,
    ) {
    }
}

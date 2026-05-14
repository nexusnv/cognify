<?php

namespace App\Observability\SystemStatus;

use Illuminate\Support\Carbon;

class SystemStatus
{
    /**
     * @param  array<string, array{status: string, message: ?string, details: array<string, mixed>}>  $checks
     * @param  array{seeded: bool, lastSeededAt: ?string, counts: array<string, int>}  $demo
     */
    public function __construct(
        public readonly string $status,
        public readonly string $environment,
        public readonly string $service,
        public readonly string $version,
        public readonly Carbon $checkedAt,
        public readonly array $checks,
        public readonly array $demo,
    ) {
    }
}


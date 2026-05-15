<?php

namespace App\Observability\SystemStatus;

use App\Observability\SystemStatus\Checks\ApiMetadataCheck;
use App\Observability\SystemStatus\Checks\CacheCheck;
use App\Observability\SystemStatus\Checks\DatabaseCheck;
use App\Observability\SystemStatus\Checks\DemoSeedCheck;
use App\Observability\SystemStatus\Checks\OpenApiCheck;
use App\Observability\SystemStatus\Checks\QueueCheck;
use App\Observability\SystemStatus\Checks\StorageCheck;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemStatusService
{
    /**
     * @param  array<int, SystemStatusCheck>|null  $checks
     */
    public function __construct(private readonly ?array $checks = null) {}

    public function report(Tenant $tenant): SystemStatus
    {
        $checkedAt = now();
        /** @var array<int, SystemStatusCheckResult> $results */
        $results = [];

        foreach ($this->checks() as $check) {
            $results[] = $this->safeRun($check, $tenant);
        }

        $checks = array_map(
            static fn (SystemStatusCheckResult $result): array => $result->toArray(),
            $results,
        );
        $demoCheck = collect($results)->firstWhere('id', 'demo_seed');

        return new SystemStatus(
            status: $this->overallStatus($checks),
            environment: (string) config('app.env'),
            service: 'cognify-api',
            version: (string) config('app.version'),
            checkedAt: $checkedAt,
            checks: $checks,
            demo: $demoCheck instanceof SystemStatusCheckResult
                ? $demoCheck->metadata
                : [
                    'seeded' => false,
                    'lastSeededAt' => null,
                    'counts' => [],
                ],
        );
    }

    /**
     * @return list<SystemStatusCheck>
     */
    private function checks(): array
    {
        if ($this->checks !== null) {
            return $this->checks;
        }

        return [
            new ApiMetadataCheck,
            new DatabaseCheck,
            new CacheCheck,
            new QueueCheck,
            new StorageCheck,
            new OpenApiCheck,
            new DemoSeedCheck,
        ];
    }

    /**
     * @param  array<int, array{id: string, label: string, status: string, message: string, remediation: ?string, metadata: object}>  $checks
     */
    private function overallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('error', $statuses, true)) {
            return 'error';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'ok';
    }

    private function safeRun(SystemStatusCheck $check, Tenant $tenant): SystemStatusCheckResult
    {
        try {
            return $check->run($tenant);
        } catch (Throwable $exception) {
            Log::error('System readiness check failed.', [
                'check' => $check->key(),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'exception' => $exception,
            ]);

            return new SystemStatusCheckResult(
                id: $check->key(),
                label: ucfirst(str_replace('_', ' ', $check->key())),
                status: 'error',
                message: 'Readiness check failed.',
                remediation: 'Review local service configuration and application logs.',
            );
        }
    }
}

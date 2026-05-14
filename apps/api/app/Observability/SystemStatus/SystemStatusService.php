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
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Carbon;
use Throwable;

class SystemStatusService
{
    public function build(Tenant $tenant): SystemStatus
    {
        $checkedAt = now();
        $checks = [];

        foreach ($this->checks() as $check) {
            try {
                $result = $check->run();
                $checks[$check->key()] = $result->toArray();
            } catch (Throwable $exception) {
                $checks[$check->key()] = (new SystemStatusCheckResult(
                    status: 'error',
                    message: $exception->getMessage(),
                ))->toArray();
            }
        }

        return new SystemStatus(
            status: $this->overallStatus($checks),
            environment: (string) config('app.env'),
            service: 'cognify-api',
            version: (string) config('app.version'),
            checkedAt: $checkedAt instanceof Carbon ? $checkedAt : Carbon::parse($checkedAt),
            checks: $checks,
            demo: $this->demoSummary($tenant),
        );
    }

    /**
     * @return list<SystemStatusCheck>
     */
    private function checks(): array
    {
        return [
            new ApiMetadataCheck(),
            new DatabaseCheck(),
            new CacheCheck(),
            new QueueCheck(),
            new StorageCheck(),
            new OpenApiCheck(),
            new DemoSeedCheck(),
        ];
    }

    /**
     * @param  array<string, array{status: string, message: ?string, details: array<string, mixed>}>  $checks
     */
    private function overallStatus(array $checks): string
    {
        foreach ($checks as $check) {
            if (($check['status'] ?? 'error') !== 'ok') {
                return 'degraded';
            }
        }

        return 'ok';
    }

    /**
     * @return array{seeded: bool, lastSeededAt: ?string, counts: array<string, int>}
     */
    private function demoSummary(Tenant $tenant): array
    {
        $latestRun = DemoSeedRun::query()
            ->latest('seeded_at')
            ->latest('id')
            ->first();

        return [
            'seeded' => $latestRun !== null,
            'lastSeededAt' => $latestRun?->seeded_at?->toISOString(),
            'counts' => [
                'tenants' => 1,
                'users' => $tenant->users()->count(),
                'requisitions' => Requisition::query()->where('tenant_id', $tenant->id)->count(),
                'vendors' => Vendor::query()->where('tenant_id', $tenant->id)->count(),
                'rfqs' => Rfq::query()->where('tenant_id', $tenant->id)->count(),
                'quotations' => Quotation::query()->where('tenant_id', $tenant->id)->count(),
                'approvalTasks' => ApprovalTask::query()->where('tenant_id', $tenant->id)->count(),
                'awards' => Award::query()->where('tenant_id', $tenant->id)->count(),
            ],
        ];
    }
}

<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;

class DemoSeedCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'demo_seed';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        $seedRun = DemoSeedRun::query()
            ->latest('seeded_at')
            ->latest('id')
            ->first();

        if ($seedRun === null) {
            return new SystemStatusCheckResult(
                id: 'demo_seed',
                label: 'Demo Seed',
                status: 'error',
                message: 'Demo seed data is unavailable',
                remediation: 'Run the local demo seed.',
                metadata: [
                    'seeded' => false,
                    'lastSeededAt' => null,
                    'counts' => [
                        'tenants' => 1,
                        'users' => $tenant->users()->count(),
                        'requisitions' => 0,
                        'vendors' => 0,
                        'rfqs' => 0,
                        'quotations' => 0,
                        'approvalTasks' => 0,
                        'awards' => 0,
                    ],
                ],
            );
        }

        return new SystemStatusCheckResult(
            id: 'demo_seed',
            label: 'Demo Seed',
            status: 'ok',
            message: 'Demo seed data is available',
            metadata: [
                'seeded' => true,
                'lastSeededAt' => $seedRun->seeded_at?->toISOString(),
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
            ],
        );
    }
}

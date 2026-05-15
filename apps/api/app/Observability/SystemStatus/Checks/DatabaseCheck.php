<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class DatabaseCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'database';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        DB::connection()->getPdo();

        return new SystemStatusCheckResult(
            id: 'database',
            label: 'Database',
            status: 'ok',
            message: 'Connected',
            metadata: [
                'connection' => config('database.default'),
            ],
        );
    }
}

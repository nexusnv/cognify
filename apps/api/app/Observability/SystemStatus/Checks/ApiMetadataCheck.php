<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;

class ApiMetadataCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'api';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        return new SystemStatusCheckResult(
            id: 'api',
            label: 'API',
            status: 'ok',
            message: 'API metadata loaded',
            metadata: [
                'service' => 'cognify-api',
                'environment' => config('app.env'),
                'version' => (string) config('app.version'),
            ],
        );
    }
}

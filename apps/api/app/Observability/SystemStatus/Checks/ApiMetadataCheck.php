<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;

class ApiMetadataCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'apiMetadata';
    }

    public function run(): SystemStatusCheckResult
    {
        return new SystemStatusCheckResult('ok', [
            'service' => 'cognify-api',
            'environment' => config('app.env'),
            'version' => (string) config('app.version'),
        ]);
    }
}


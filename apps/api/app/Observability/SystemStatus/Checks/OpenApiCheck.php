<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;

class OpenApiCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'openApi';
    }

    public function run(): SystemStatusCheckResult
    {
        $path = base_path('storage/openapi/openapi.json');

        if (! is_file($path)) {
            return new SystemStatusCheckResult('error', message: 'OpenAPI spec file is missing.');
        }

        $updatedAt = @filemtime($path);

        return new SystemStatusCheckResult('ok', [
            'path' => 'storage/openapi/openapi.json',
            'updatedAt' => $updatedAt ? now()->setTimestamp($updatedAt)->toISOString() : null,
        ]);
    }
}


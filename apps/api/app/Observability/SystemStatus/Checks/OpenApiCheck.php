<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;

class OpenApiCheck implements SystemStatusCheck
{
    public function __construct(private readonly ?string $path = null) {}

    public function key(): string
    {
        return 'openapi';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        $path = $this->path ?? base_path('storage/openapi/openapi.json');

        if (! is_file($path)) {
            return new SystemStatusCheckResult(
                id: 'openapi',
                label: 'OpenAPI',
                status: 'error',
                message: 'OpenAPI spec file is missing.',
                remediation: 'Regenerate the API contract.',
            );
        }

        if (! is_readable($path)) {
            return new SystemStatusCheckResult(
                id: 'openapi',
                label: 'OpenAPI',
                status: 'error',
                message: 'OpenAPI spec file is not readable.',
                remediation: 'Review file permissions and regenerate the API contract.',
            );
        }

        $updatedAt = filemtime($path);

        if ($updatedAt === false) {
            return new SystemStatusCheckResult(
                id: 'openapi',
                label: 'OpenAPI',
                status: 'error',
                message: 'OpenAPI spec file timestamp is unavailable.',
                remediation: 'Review file permissions and regenerate the API contract.',
            );
        }

        return new SystemStatusCheckResult(
            id: 'openapi',
            label: 'OpenAPI',
            status: 'ok',
            message: 'OpenAPI contract is available',
            metadata: [
                'path' => 'storage/openapi/openapi.json',
                'updatedAt' => now()->setTimestamp($updatedAt)->toISOString(),
            ],
        );
    }
}

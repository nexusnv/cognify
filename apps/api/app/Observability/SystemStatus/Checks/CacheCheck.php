<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Cache;

class CacheCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'cache';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        $probeKey = 'system-status:cache-probe:' . bin2hex(random_bytes(6));
        $value = now()->toISOString();

        Cache::put($probeKey, $value, now()->addMinute());
        $loaded = Cache::get($probeKey);
        Cache::forget($probeKey);

        if ($loaded !== $value) {
            return new SystemStatusCheckResult(
                id: 'cache',
                label: 'Cache',
                status: 'error',
                message: 'Cache read/write mismatch.',
                remediation: 'Review cache configuration.',
            );
        }

        return new SystemStatusCheckResult(
            id: 'cache',
            label: 'Cache',
            status: 'ok',
            message: 'Cache read/write succeeded',
            metadata: [
                'store' => config('cache.default'),
            ],
        );
    }
}

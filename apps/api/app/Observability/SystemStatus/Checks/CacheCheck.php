<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use Illuminate\Support\Facades\Cache;

class CacheCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'cache';
    }

    public function run(): SystemStatusCheckResult
    {
        $probeKey = 'system-status:cache-probe:'.bin2hex(random_bytes(6));
        $value = now()->toISOString();

        Cache::put($probeKey, $value, now()->addMinute());
        $loaded = Cache::get($probeKey);
        Cache::forget($probeKey);

        if ($loaded !== $value) {
            return new SystemStatusCheckResult('error', message: 'Cache probe read/write mismatch.');
        }

        return new SystemStatusCheckResult('ok', [
            'store' => config('cache.default'),
        ]);
    }
}


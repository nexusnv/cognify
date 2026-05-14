<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use Illuminate\Support\Facades\Storage;

class StorageCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'storage';
    }

    public function run(): SystemStatusCheckResult
    {
        $disk = config('filesystems.default');
        $probePath = 'system-status/probe-'.bin2hex(random_bytes(6)).'.txt';
        $value = now()->toISOString();

        Storage::disk($disk)->put($probePath, $value);
        $loaded = Storage::disk($disk)->get($probePath);
        Storage::disk($disk)->delete($probePath);

        if ($loaded !== $value) {
            return new SystemStatusCheckResult('error', message: 'Storage probe read/write mismatch.');
        }

        return new SystemStatusCheckResult('ok', [
            'disk' => $disk,
        ]);
    }
}


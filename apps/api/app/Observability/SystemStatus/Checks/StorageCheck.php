<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'storage';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        $disk = config('filesystems.default');
        $probePath = 'system-status/probe-'.bin2hex(random_bytes(6)).'.txt';
        $value = now()->toISOString();
        $operationException = null;

        try {
            Storage::disk($disk)->put($probePath, $value);
            $loaded = Storage::disk($disk)->get($probePath);
        } catch (Throwable $exception) {
            $operationException = $exception;

            throw $exception;
        } finally {
            try {
                Storage::disk($disk)->delete($probePath);
            } catch (Throwable $deleteException) {
                if (! $operationException instanceof Throwable) {
                    throw $deleteException;
                }
            }
        }

        if ($loaded !== $value) {
            return new SystemStatusCheckResult(
                id: 'storage',
                label: 'Storage',
                status: 'error',
                message: 'Storage read/write mismatch.',
                remediation: 'Review filesystem configuration.',
            );
        }

        return new SystemStatusCheckResult(
            id: 'storage',
            label: 'Storage',
            status: 'ok',
            message: 'Storage read/write succeeded',
            metadata: [
                'disk' => $disk,
            ],
        );
    }
}

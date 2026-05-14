<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Queue;

class QueueCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'queue';
    }

    public function run(Tenant $tenant): SystemStatusCheckResult
    {
        Queue::size();

        return new SystemStatusCheckResult(
            id: 'queue',
            label: 'Queue',
            status: 'ok',
            message: 'Queue storage is available',
            metadata: [
                'connection' => config('queue.default'),
            ],
        );
    }
}

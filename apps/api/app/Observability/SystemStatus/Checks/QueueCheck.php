<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use Illuminate\Support\Facades\Queue;

class QueueCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'queue';
    }

    public function run(): SystemStatusCheckResult
    {
        Queue::size();

        return new SystemStatusCheckResult('ok', [
            'connection' => config('queue.default'),
        ]);
    }
}


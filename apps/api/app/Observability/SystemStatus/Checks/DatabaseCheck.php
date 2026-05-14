<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use Illuminate\Support\Facades\DB;

class DatabaseCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'database';
    }

    public function run(): SystemStatusCheckResult
    {
        DB::connection()->getPdo();

        return new SystemStatusCheckResult('ok', [
            'connection' => config('database.default'),
        ]);
    }
}


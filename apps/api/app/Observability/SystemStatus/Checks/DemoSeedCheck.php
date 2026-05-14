<?php

namespace App\Observability\SystemStatus\Checks;

use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use Domains\Demo\Models\DemoSeedRun;

class DemoSeedCheck implements SystemStatusCheck
{
    public function key(): string
    {
        return 'demoSeed';
    }

    public function run(): SystemStatusCheckResult
    {
        $run = DemoSeedRun::query()
            ->latest('seeded_at')
            ->latest('id')
            ->first();

        if ($run === null) {
            return new SystemStatusCheckResult('error', [
                'seeded' => false,
                'lastSeededAt' => null,
                'runName' => null,
            ], 'No demo seed run found.');
        }

        return new SystemStatusCheckResult('ok', [
            'seeded' => true,
            'lastSeededAt' => $run->seeded_at?->toISOString(),
            'runName' => $run->name,
        ]);
    }
}


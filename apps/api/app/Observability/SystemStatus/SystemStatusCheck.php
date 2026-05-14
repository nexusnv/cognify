<?php

namespace App\Observability\SystemStatus;

use App\Tenancy\Tenant;

interface SystemStatusCheck
{
    public function key(): string;

    public function run(Tenant $tenant): SystemStatusCheckResult;
}

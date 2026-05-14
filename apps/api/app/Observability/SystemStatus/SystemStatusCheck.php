<?php

namespace App\Observability\SystemStatus;

interface SystemStatusCheck
{
    public function key(): string;

    public function run(): SystemStatusCheckResult;
}


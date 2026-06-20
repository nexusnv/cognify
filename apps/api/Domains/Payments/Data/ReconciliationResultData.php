<?php

namespace Domains\Payments\Data;

class ReconciliationResultData
{
    public function __construct(
        public readonly int $reconciled,
        public readonly int $failed,
        public readonly int $skipped,
    ) {}
}

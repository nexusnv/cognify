<?php

namespace Domains\Payments\Support;

use Illuminate\Support\Str;

class PaymentImportBatchIdGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}

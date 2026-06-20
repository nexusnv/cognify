<?php

namespace Tests\Feature;

use Domains\Payments\Models\ApPaymentImport;
use Tests\TestCase;

class ApPaymentImportApiTest extends TestCase
{
    public function test_model_exists(): void
    {
        $this->assertTrue(class_exists(ApPaymentImport::class));
    }
}

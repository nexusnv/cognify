<?php

namespace Tests\Feature;

use Domains\Payments\Models\ApPaymentAllocation;
use Tests\TestCase;

class ApPaymentAllocationApiTest extends TestCase
{
    public function test_model_exists(): void
    {
        $this->assertTrue(class_exists(ApPaymentAllocation::class));
    }
}

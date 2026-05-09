<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_returns_api_status(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'service' => 'cognify-api',
            ]);
    }
}

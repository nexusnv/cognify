<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdentityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_preferences_exist_on_user_model(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $user->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $response = $this->actingAs($user)->getJson('/api/me');

        // This will fail with 404 since routes don't exist yet - that's expected
        $response->assertStatus(200);
    }
}

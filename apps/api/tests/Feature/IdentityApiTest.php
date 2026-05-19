<?php

namespace Tests\Feature;

use App\Auth\Permissions\TenantPermissionResolver;
use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_read_current_identity_context(): void
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

        $me = $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->actingAs($user)
            ->getJson('/api/me');

        $me->assertStatus(200);
        $me->assertJsonPath('data.user.email', 'test@example.com');
        $me->assertJsonPath('data.user.timezone', 'Asia/Kuala_Lumpur');
        $me->assertJsonPath('data.user.locale', 'en');
        $me->assertJsonPath('data.user.theme', 'system');
        $me->assertJsonPath('data.activeTenant.id', (string) $tenant->id);
        $me->assertJsonPath('data.activeRole', 'requester');
        $me->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email', 'avatarUrl', 'timezone', 'locale', 'theme'],
                'tenants',
                'activeTenant' => ['id', 'name'],
                'activeRole',
                'permissions',
            ],
        ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_and_read_current_identity_context(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ])->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $login = $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

        $login->assertNoContent();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', 'test@example.com')
            ->assertJsonPath('data.activeTenant.id', (string) $tenant->id);
    }

    public function test_logout_ends_session(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ])->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_multi_tenant_user_can_validate_current_tenant_without_header(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        $otherTenant = Tenant::create(['name' => 'Northwind Sourcing']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);
        $user->tenants()->attach($otherTenant->id, ['role' => TenantRole::Buyer->value]);

        $this->actingAs($user)
            ->postJson('/api/tenants/current', [
                'tenantId' => (string) $otherTenant->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.activeTenant.id', (string) $otherTenant->id)
            ->assertJsonPath('data.activeRole', TenantRole::Buyer->value);
    }

    public function test_multi_tenant_user_without_tenant_header_receives_memberships_without_active_tenant(): void
    {
        $acme = Tenant::create(['name' => 'Acme Procurement']);
        $northwind = Tenant::create(['name' => 'Northwind Sourcing']);

        $user = User::factory()->create();
        $user->tenants()->attach($acme->id, ['role' => TenantRole::Requester->value]);
        $user->tenants()->attach($northwind->id, ['role' => TenantRole::Buyer->value]);

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data.activeTenant', null);
        $response->assertJsonPath('data.activeRole', null);
        $response->assertJsonCount(2, 'data.tenants');
    }

    public function test_current_tenant_rejects_non_member_tenant(): void
    {
        $acme = Tenant::create(['name' => 'Acme Procurement']);
        $otherTenant = Tenant::create(['name' => 'Other Corp']);

        $user = User::factory()->create();
        $user->tenants()->attach($acme->id, ['role' => TenantRole::Requester->value]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant-Id', (string) $otherTenant->id)
            ->getJson('/api/me');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'forbidden');
        $response->assertJsonPath('error.message', 'Tenant membership is required.');
    }

    public function test_post_tenants_current_rejects_non_member_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        $otherTenant = Tenant::create(['name' => 'Other Corp']);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $response = $this->actingAs($user)
            ->postJson('/api/tenants/current', [
                'tenantId' => (string) $otherTenant->id,
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'forbidden');
        $response->assertJsonPath('error.message', 'Tenant membership is required.');
    }

    public function test_profile_update_validates_and_persists_preferences(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        $user = User::factory()->create([
            'name' => 'Original Name',
            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => 'system',
        ]);
        $user->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson('/api/me/profile', [
                'name' => 'Updated Name',
                'timezone' => 'Asia/Kuala_Lumpur',
                'locale' => 'ms',
                'theme' => 'dark',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.name', 'Updated Name');
        $response->assertJsonPath('data.user.timezone', 'Asia/Kuala_Lumpur');
        $response->assertJsonPath('data.user.locale', 'ms');
        $response->assertJsonPath('data.user.theme', 'dark');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'timezone' => 'Asia/Kuala_Lumpur',
            'locale' => 'ms',
            'theme' => 'dark',
        ]);
    }

    public function test_permissions_are_computed_by_role(): void
    {
        $resolver = app(TenantPermissionResolver::class);

        $rolePermissions = [
            TenantRole::Requester->value => [
                'canCreateRequisition' => true,
                'canViewSubmittedRequisitions' => false,
                'canUpdateOwnDraftRequisition' => true,
                'canSubmitOwnDraftRequisition' => true,
                'canAccessAdmin' => false,
            ],
            TenantRole::Buyer->value => [
                'canCreateRequisition' => false,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => false,
                'canSubmitOwnDraftRequisition' => false,
                'canAccessAdmin' => false,
            ],
            TenantRole::Approver->value => [
                'canCreateRequisition' => false,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => false,
                'canSubmitOwnDraftRequisition' => false,
                'canAccessAdmin' => false,
            ],
            TenantRole::Admin->value => [
                'canCreateRequisition' => true,
                'canViewSubmittedRequisitions' => true,
                'canUpdateOwnDraftRequisition' => true,
                'canSubmitOwnDraftRequisition' => true,
                'canAccessAdmin' => true,
            ],
        ];

        foreach ($rolePermissions as $role => $expected) {
            $this->assertSame(
                $expected,
                $resolver->forRole($role),
                "Permission mismatch for role: {$role}"
            );
        }
    }

    public function test_permissions_returned_with_current_user_context_by_role(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);

        $testCases = [
            ['role' => TenantRole::Requester, 'email' => 'requester@example.com', 'assertions' => [
                'canCreateRequisition' => true,
                'canAccessAdmin' => false,
            ]],
            ['role' => TenantRole::Buyer, 'email' => 'buyer@example.com', 'assertions' => [
                'canViewSubmittedRequisitions' => true,
                'canAccessAdmin' => false,
            ]],
            ['role' => TenantRole::Approver, 'email' => 'approver@example.com', 'assertions' => [
                'canViewSubmittedRequisitions' => true,
                'canAccessAdmin' => false,
            ]],
            ['role' => TenantRole::Admin, 'email' => 'admin@example.com', 'assertions' => [
                'canCreateRequisition' => true,
                'canAccessAdmin' => true,
            ]],
        ];

        foreach ($testCases as $case) {
            $user = User::factory()->create(['email' => $case['email']]);
            $user->tenants()->attach($tenant->id, ['role' => $case['role']->value]);

            $response = $this->actingAs($user)
                ->withHeader('X-Tenant-Id', (string) $tenant->id)
                ->getJson('/api/me');

            $response->assertStatus(200);
            foreach ($case['assertions'] as $key => $expected) {
                $response->assertJsonPath("data.permissions.{$key}", $expected);
            }
        }
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401);

        $this->postJson('/api/auth/logout')
            ->assertStatus(401);
    }

    public function test_profile_update_rejects_invalid_fields(): void
    {
        $tenant = Tenant::create(['name' => 'Acme Procurement']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => TenantRole::Requester->value]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->patchJson('/api/me/profile', [
                'name' => '',
                'timezone' => 'invalid-timezone',
                'locale' => '',
                'theme' => 'neon',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_failed');
        $response->assertJsonPath('error.details.fields', [
            'name' => ['The name field is required.'],
            'timezone' => ['The timezone field must be a valid timezone.'],
            'locale' => ['The locale field is required.'],
            'theme' => ['The selected theme is invalid.'],
        ]);
    }
}

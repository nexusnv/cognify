<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorPickerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_list_active_tenant_vendors_for_picker(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $active = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Supplies',
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Ada Buyer',
                'contactEmail' => 'ada@example.test',
            ],
        ]);

        Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dormant Vendor',
            'status' => 'inactive',
        ]);

        [$otherTenant] = $this->tenantUser('buyer');

        Vendor::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Vendor',
            'status' => 'active',
        ]);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/vendors?status=active');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', (string) $active->id);
        $response->assertJsonPath('data.0.name', 'Acme Supplies');
        $response->assertJsonPath('data.0.defaultContact.name', 'Ada Buyer');
        $response->assertJsonPath('data.0.defaultContact.email', 'ada@example.test');

        $this->assertSame([(string) $active->id], array_column($response->json('data'), 'id'));
        $this->assertSame(['Acme Supplies'], array_column($response->json('data'), 'name'));
    }

    public function test_requester_cannot_use_vendor_picker(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/vendors?status=active')
            ->assertForbidden();
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /**
     * @return array{Tenant, User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant ' . Str::uuid()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }
}

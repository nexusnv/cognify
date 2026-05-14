<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_status_requires_authentication(): void
    {
        $this->getJson('/api/system/status')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_system_status_requires_resolved_tenant_context(): void
    {
        $user = User::factory()->create();
        $first = Tenant::query()->create(['name' => 'Acme Procurement']);
        $second = Tenant::query()->create(['name' => 'Northwind Sourcing']);
        $first->users()->attach($user->id, ['role' => TenantRole::Admin->value]);
        $second->users()->attach($user->id, ['role' => TenantRole::Admin->value]);

        Sanctum::actingAs($user);

        $this->getJson('/api/system/status')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');
    }

    public function test_non_admin_cannot_access_system_status(): void
    {
        [$tenant, $requester] = $this->tenantUser(TenantRole::Requester->value);

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/system/status')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_admin_can_get_system_status_with_expected_shape(): void
    {
        [$tenant, $admin] = $this->tenantUser(TenantRole::Admin->value);
        $secondUser = User::factory()->create();
        $tenant->users()->attach($secondUser->id, ['role' => TenantRole::Buyer->value]);

        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $admin->id,
            'number' => 'REQ-2026-0001',
            'title' => 'Laptop refresh',
            'status' => 'submitted',
            'currency' => 'USD',
            'needed_by_date' => now()->addDays(5)->toDateString(),
            'submitted_at' => now(),
        ]);
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Northstar Office',
            'status' => 'active',
        ]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'requisition_id' => $requisition->id,
            'number' => 'RFQ-2026-0001',
            'title' => 'Laptop shortlist',
            'status' => 'open',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'QUO-2026-0001',
            'status' => 'submitted',
            'total_amount' => '900.00',
            'currency' => 'USD',
        ]);
        ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'approver_id' => $admin->id,
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'title' => 'Finance approval',
            'status' => 'pending',
        ]);
        Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => null,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'number' => 'AWD-2026-0001',
            'status' => 'awarded',
            'total_amount' => '900.00',
            'currency' => 'USD',
            'decided_at' => now(),
        ]);
        DemoSeedRun::query()->create([
            'name' => 'local-demo',
            'seeded_at' => now(),
            'metadata' => [],
        ]);

        $response = $this->actingAsTenant($tenant, $admin)->getJson('/api/system/status');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'environment',
                    'service',
                    'version',
                    'checkedAt',
                    'checks' => [
                        'apiMetadata',
                        'database',
                        'cache',
                        'queue',
                        'storage',
                        'openApi',
                        'demoSeed',
                    ],
                    'demo' => [
                        'seeded',
                        'lastSeededAt',
                        'counts' => [
                            'tenants',
                            'users',
                            'requisitions',
                            'vendors',
                            'rfqs',
                            'quotations',
                            'approvalTasks',
                            'awards',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.service', 'cognify-api')
            ->assertJsonPath('data.version', config('app.version'))
            ->assertJsonPath('data.demo.seeded', true)
            ->assertJsonPath('data.demo.counts.tenants', 1)
            ->assertJsonPath('data.demo.counts.users', 2)
            ->assertJsonPath('data.demo.counts.requisitions', 1)
            ->assertJsonPath('data.demo.counts.vendors', 1)
            ->assertJsonPath('data.demo.counts.rfqs', 1)
            ->assertJsonPath('data.demo.counts.quotations', 1)
            ->assertJsonPath('data.demo.counts.approvalTasks', 1)
            ->assertJsonPath('data.demo.counts.awards', 1);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }
}

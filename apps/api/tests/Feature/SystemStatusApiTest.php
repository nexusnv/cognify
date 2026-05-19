<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Observability\SystemStatus\Checks\OpenApiCheck;
use App\Observability\SystemStatus\Checks\StorageCheck;
use App\Observability\SystemStatus\SystemStatusCheck;
use App\Observability\SystemStatus\SystemStatusCheckResult;
use App\Observability\SystemStatus\SystemStatusService;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\States\ApprovalTaskStatus;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;
use Throwable;

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
            'status' => ApprovalTaskStatus::Active,
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
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
                        ['id', 'label', 'status', 'message', 'remediation', 'metadata'],
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
            ->assertJsonPath('data.checks.0.id', 'api')
            ->assertJsonPath('data.checks.1.id', 'database')
            ->assertJsonPath('data.checks.2.id', 'cache')
            ->assertJsonPath('data.checks.3.id', 'queue')
            ->assertJsonPath('data.checks.4.id', 'storage')
            ->assertJsonPath('data.checks.5.id', 'openapi')
            ->assertJsonPath('data.checks.6.id', 'demo_seed')
            ->assertJsonPath('data.demo.counts.tenants', 1)
            ->assertJsonPath('data.demo.counts.users', 2)
            ->assertJsonPath('data.demo.counts.requisitions', 1)
            ->assertJsonPath('data.demo.counts.vendors', 1)
            ->assertJsonPath('data.demo.counts.rfqs', 1)
            ->assertJsonPath('data.demo.counts.quotations', 1)
            ->assertJsonPath('data.demo.counts.approvalTasks', 1)
            ->assertJsonPath('data.demo.counts.awards', 1);
    }

    public function test_openapi_check_reports_error_when_spec_is_not_readable(): void
    {
        [$tenant] = $this->tenantUser(TenantRole::Admin->value);
        $path = tempnam(sys_get_temp_dir(), 'openapi-');
        $this->assertIsString($path);
        file_put_contents($path, '{}');
        chmod($path, 0000);

        try {
            $result = (new OpenApiCheck($path))->run($tenant);
        } finally {
            chmod($path, 0600);
            unlink($path);
        }

        $this->assertSame('openapi', $result->id);
        $this->assertSame('error', $result->status);
        $this->assertSame('OpenAPI spec file is not readable.', $result->message);
        $this->assertSame([], $result->metadata);
    }

    public function test_storage_check_deletes_probe_when_read_fails(): void
    {
        [$tenant] = $this->tenantUser(TenantRole::Admin->value);
        config(['filesystems.default' => 'local']);
        $disk = Mockery::mock();

        Storage::shouldReceive('disk')
            ->with('local')
            ->andReturn($disk);
        $disk->shouldReceive('put')->once();
        $disk->shouldReceive('get')->once()->andThrow(new \RuntimeException('read failed'));
        $disk->shouldReceive('delete')->once();

        $this->expectException(\RuntimeException::class);

        (new StorageCheck)->run($tenant);
    }

    public function test_system_status_service_logs_failed_checks(): void
    {
        [$tenant] = $this->tenantUser(TenantRole::Admin->value);
        Log::spy();

        $service = new SystemStatusService([
            new class implements SystemStatusCheck
            {
                public function key(): string
                {
                    return 'broken_check';
                }

                public function run(Tenant $tenant): SystemStatusCheckResult
                {
                    throw new \RuntimeException('readiness failed');
                }
            },
        ]);

        $report = $service->report($tenant);

        $this->assertSame('error', $report->status);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn (string $message, array $context): bool => $message === 'System readiness check failed.'
                && $context['check'] === 'broken_check'
                && $context['message'] === 'readiness failed'
                && is_string($context['trace'])
                && $context['exception'] instanceof Throwable,
        );
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

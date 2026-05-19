<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_error_uses_normalized_envelope(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonPath('error.message', 'Authentication is required.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'requestId']]);
    }

    public function test_validation_error_uses_field_detail_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/requisitions', [
                'title' => '',
                'lineItems' => [
                    [
                        'name' => '',
                        'quantity' => -1,
                        'unit' => '',
                        'estimatedUnitPrice' => '-10.00',
                        'currency' => 'USD',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['title', 'lineItems.0.quantity']]]]);
    }

    public function test_not_found_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisitions/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_http_exception_headers_are_preserved(): void
    {
        $response = $this->postJson('/api/me');

        $response->assertStatus(405)
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'requestId']]);

        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    public function test_conflict_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Submitted requisition',
            'business_justification' => 'Already submitted.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'USD',
            'status' => RequisitionStatus::Submitted,
            'lock_version' => 0,
            'submitted_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'title' => 'Changed',
                'lockVersion' => $requisition->lock_version,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'draft_conflict');
    }

    public function test_ambiguous_tenant_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $secondTenant = Tenant::query()->create(['name' => 'Second tenant']);
        $secondTenant->users()->attach($user->id, ['role' => 'requester']);

        Sanctum::actingAs($user);

        $this->getJson('/api/requisitions')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');
    }

    public function test_response_includes_request_id_header_and_error_request_id(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req_test_123')
            ->getJson('/api/me');

        $response->assertHeader('X-Request-Id', 'req_test_123')
            ->assertJsonPath('error.requestId', 'req_test_123');
    }

    public function test_invalid_request_id_header_is_not_echoed(): void
    {
        $response = $this->withHeader('X-Request-Id', "bad\nheader")
            ->getJson('/api/me');

        $response->assertUnauthorized();
        $this->assertNotSame("bad\nheader", $response->headers->get('X-Request-Id'));
        $this->assertStringStartsWith('req_', (string) $response->headers->get('X-Request-Id'));
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

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_search_visible_requisitions_by_requester_name(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);

        $visible = $this->createRequisition($tenant, $requester, [
            'title' => 'Office fit-out procurement',
            'number' => 'REQ-2026-000042',
        ]);

        $this->createRequisition($tenant, $otherRequester, [
            'title' => 'Office furniture refresh',
            'number' => 'REQ-2026-000043',
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=' . urlencode($requester->name));

        $response->assertOk()
            ->assertJsonPath('meta.query', $requester->name)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.type', 'requisition')
            ->assertJsonPath('data.0.id', (string) $visible->id)
            ->assertJsonPath('data.0.title', 'Office fit-out procurement')
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-000042')
            ->assertJsonPath('data.0.status', RequisitionStatus::Draft->value)
            ->assertJsonPath('data.0.href', '/requisitions/' . $visible->id)
            ->assertJsonStructure([
                'data' => [
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                ],
                'meta' => ['query', 'limit', 'returned'],
            ]);
    }

    public function test_requester_can_search_own_requisitions_by_title_and_number(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);

        $titleMatch = $this->createRequisition($tenant, $requester, [
            'title' => 'Office fit-out procurement',
            'number' => 'REQ-2026-000042',
        ]);

        $numberMatch = $this->createRequisition($tenant, $requester, [
            'title' => 'Warehouse supplies',
            'number' => 'REQ-2026-000777',
        ]);

        $this->createRequisition($tenant, $otherRequester, [
            'title' => 'Office furniture refresh',
            'number' => 'REQ-2026-000043',
        ]);

        $titleResponse = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=office&types=requisition&limit=10');

        $titleResponse->assertOk()
            ->assertJsonPath('meta.query', 'office')
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.type', 'requisition')
            ->assertJsonPath('data.0.id', (string) $titleMatch->id)
            ->assertJsonPath('data.0.title', 'Office fit-out procurement')
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-000042')
            ->assertJsonPath('data.0.href', '/requisitions/' . $titleMatch->id);

        $numberResponse = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=000777&types=requisition&limit=10');

        $numberResponse->assertOk()
            ->assertJsonPath('meta.query', '000777')
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $numberMatch->id)
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-000777');
    }

    public function test_buyer_and_approver_search_only_submitted_requisitions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $draft = $this->createRequisition($tenant, $requester, [
            'title' => 'Hidden draft',
            'number' => 'REQ-2026-000100',
            'status' => RequisitionStatus::Draft,
        ]);
        $submitted = $this->createRequisition($tenant, $requester, [
            'title' => 'Visible submitted',
            'number' => 'REQ-2026-000101',
            'status' => RequisitionStatus::Submitted,
        ]);

        $buyerResponse = $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/search?query=' . urlencode('Visible'));

        $buyerResponse->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $submitted->id)
            ->assertJsonMissing(['id' => (string) $draft->id]);

        $approverResponse = $this->actingAsTenant($tenant, $approver)
            ->getJson('/api/search?query=' . urlencode('Visible'));

        $approverResponse->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $submitted->id)
            ->assertJsonMissing(['id' => (string) $draft->id]);
    }

    public function test_search_rejects_unsupported_types(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/search?query=office&types=vendor')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_is_tenant_scoped_and_omits_cross_tenant_results(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');

        $visible = $this->createRequisition($tenant, $requester, [
            'title' => 'Laptop refresh',
            'number' => 'REQ-2026-000201',
        ]);

        $this->createRequisition($otherTenant, $otherRequester, [
            'title' => 'Laptop refresh',
            'number' => 'REQ-2026-000202',
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=laptop');

        $response->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $visible->id)
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-000201')
            ->assertJsonMissing(['subtitle' => 'REQ-2026-000202']);
    }

    public function test_search_rejects_queries_shorter_than_two_characters(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/search?query=a')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_rejects_limit_above_the_server_maximum(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        for ($index = 1; $index <= 30; $index++) {
            $this->createRequisition($tenant, $user, [
                'title' => sprintf('Office item %02d', $index),
                'number' => sprintf('REQ-2026-%06d', $index),
            ]);
        }

        $response = $this->actingAsTenant($tenant, $user)
            ->getJson('/api/search?query=Office&limit=99');

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_returns_empty_success_response_with_returned_meta_zero(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=missing');

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.returned', 0);
    }

    public function test_search_route_is_throttled(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/search', 'GET'),
        );

        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();

        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createRequisition(Tenant $tenant, User $user, array $attributes = []): Requisition
    {
        $status = $attributes['status'] ?? RequisitionStatus::Draft;

        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => $attributes['number'] ?? sprintf(
                'REQ-2026-%06d',
                Requisition::query()->where('tenant_id', $tenant->id)->count() + 1,
            ),
            'title' => $attributes['title'] ?? 'Laptop refresh',
            'business_justification' => $attributes['business_justification'] ?? 'Replace aging laptops.',
            'needed_by_date' => $attributes['needed_by_date'] ?? '2026-07-15',
            'currency' => $attributes['currency'] ?? 'USD',
            'status' => $status,
            'submitted_at' => $status === RequisitionStatus::Submitted ? now() : null,
        ]);

        $requisition->lineItems()->create([
            'name' => 'Developer laptop',
            'quantity' => '2.0000',
            'unit_of_measure' => 'each',
            'estimated_unit_price' => '1800.00',
            'currency' => 'USD',
        ]);

        return $requisition;
    }
}

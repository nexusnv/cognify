<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
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
            ->getJson('/api/search?query='.urlencode($requester->name));

        $response->assertOk()
            ->assertJsonPath('meta.query', $requester->name)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.type', 'requisition')
            ->assertJsonPath('data.0.id', (string) $visible->id)
            ->assertJsonPath('data.0.title', 'Office fit-out procurement')
            ->assertJsonPath('data.0.subtitle', 'REQ-2026-000042')
            ->assertJsonPath('data.0.status', RequisitionStatus::Draft->value)
            ->assertJsonPath('data.0.href', '/requisitions/'.$visible->id)
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
            ->assertJsonPath('data.0.href', '/requisitions/'.$titleMatch->id);

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
            ->getJson('/api/search?query='.urlencode('REQ-2026-0001'));

        $buyerResponse->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $submitted->id)
            ->assertJsonMissing(['id' => (string) $draft->id]);

        $approverResponse = $this->actingAsTenant($tenant, $approver)
            ->getJson('/api/search?query='.urlencode('REQ-2026-0001'));

        $approverResponse->assertOk()
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonPath('data.0.id', (string) $submitted->id)
            ->assertJsonMissing(['id' => (string) $draft->id]);
    }

    public function test_search_rejects_unsupported_types(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/search?query=office&types[]=vendor&types[]=unknown')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_search_returns_roadmap_preview_records_for_all_supported_types(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'title' => 'Alpha workplace refresh',
            'number' => 'REQ-2026-ALPHA',
        ]);
        $vendor = $this->createVendor($tenant, [
            'name' => 'Alpha Office Supplies',
            'status' => 'preferred',
            'category' => 'Office supplies',
            'risk_rating' => 'low',
        ]);
        $project = $this->createProject($tenant, $requester, [
            'number' => 'PRJ-2026-ALPHA',
            'name' => 'Alpha Workplace Refresh',
            'status' => 'active',
        ]);
        $rfq = $this->createRfq($tenant, [
            'number' => 'RFQ-2026-ALPHA',
            'title' => 'Alpha furniture package',
            'status' => 'open',
            'project_id' => $project->id,
            'requisition_id' => $requisition->id,
        ]);
        $quotation = $this->createQuotation($tenant, [
            'number' => 'QUO-2026-ALPHA',
            'status' => 'received',
            'vendor_id' => $vendor->id,
            'rfq_id' => $rfq->id,
        ]);
        $award = $this->createAward($tenant, [
            'number' => 'AWD-2026-ALPHA',
            'status' => 'recommended',
            'vendor_id' => $vendor->id,
            'project_id' => $project->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=alpha&types[]=vendor&types[]=project&types[]=rfq&types[]=quotation&types[]=award&limit=10');

        $response->assertOk()
            ->assertJsonPath('meta.query', 'alpha')
            ->assertJsonPath('meta.returned', 5)
            ->assertJsonPath('data.0.type', 'vendor')
            ->assertJsonPath('data.0.id', (string) $vendor->id)
            ->assertJsonPath('data.0.title', 'Alpha Office Supplies')
            ->assertJsonPath('data.0.subtitle', 'Office supplies')
            ->assertJsonPath('data.0.status', 'preferred')
            ->assertJsonPath('data.0.href', '/system')
            ->assertJsonPath('data.1.type', 'project')
            ->assertJsonPath('data.1.id', (string) $project->id)
            ->assertJsonPath('data.1.title', 'Alpha Workplace Refresh')
            ->assertJsonPath('data.1.subtitle', 'PRJ-2026-ALPHA')
            ->assertJsonPath('data.1.status', 'active')
            ->assertJsonPath('data.1.href', '/projects/'.$project->id)
            ->assertJsonPath('data.2.type', 'rfq')
            ->assertJsonPath('data.2.id', (string) $rfq->id)
            ->assertJsonPath('data.2.title', 'Alpha furniture package')
            ->assertJsonPath('data.2.subtitle', 'RFQ-2026-ALPHA')
            ->assertJsonPath('data.2.status', 'open')
            ->assertJsonPath('data.2.href', '/requisitions/'.$requisition->id)
            ->assertJsonPath('data.3.type', 'quotation')
            ->assertJsonPath('data.3.id', (string) $quotation->id)
            ->assertJsonPath('data.3.title', 'QUO-2026-ALPHA')
            ->assertJsonPath('data.3.subtitle', 'Alpha Office Supplies')
            ->assertJsonPath('data.3.status', 'received')
            ->assertJsonPath('data.3.href', '/requisitions/'.$requisition->id)
            ->assertJsonPath('data.4.type', 'award')
            ->assertJsonPath('data.4.id', (string) $award->id)
            ->assertJsonPath('data.4.title', 'AWD-2026-ALPHA')
            ->assertJsonPath('data.4.subtitle', 'Alpha Office Supplies')
            ->assertJsonPath('data.4.status', 'recommended')
            ->assertJsonPath('data.4.href', '/requisitions/'.$requisition->id)
            ->assertJsonStructure([
                'data' => [
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                    ['type', 'id', 'title', 'subtitle', 'status', 'href', 'updatedAt'],
                ],
                'meta' => ['query', 'limit', 'returned'],
            ]);
    }

    public function test_vendor_search_ranks_status_matches_before_risk_matches(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $statusMatch = $this->createVendor($tenant, [
            'name' => 'Metro Facilities',
            'status' => 'preferred',
            'category' => 'Facilities',
            'risk_rating' => 'low',
        ]);
        $riskMatch = $this->createVendor($tenant, [
            'name' => 'Risk Advisory',
            'status' => 'active',
            'category' => 'Compliance',
            'risk_rating' => 'preferred',
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=preferred&types[]=vendor&limit=10');

        $response->assertOk()
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('data.0.id', (string) $statusMatch->id)
            ->assertJsonPath('data.1.id', (string) $riskMatch->id);
    }

    public function test_search_accepts_repeated_types_parameters_from_client_serialization(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $vendor = $this->createVendor($tenant, [
            'name' => 'Alpha Office Supplies',
            'status' => 'preferred',
            'category' => 'Office supplies',
            'risk_rating' => 'low',
        ]);
        $project = $this->createProject($tenant, $requester, [
            'number' => 'PRJ-2026-ALPHA',
            'name' => 'Alpha Workplace Refresh',
            'status' => 'active',
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=alpha&types=vendor&types=project');

        $response->assertOk()
            ->assertJsonPath('meta.returned', 2)
            ->assertJsonPath('data.0.type', 'vendor')
            ->assertJsonPath('data.0.id', (string) $vendor->id)
            ->assertJsonPath('data.1.type', 'project')
            ->assertJsonPath('data.1.id', (string) $project->id);
    }

    public function test_search_preview_records_fall_back_to_system_when_unlinked(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $rfq = $this->createRfq($tenant, [
            'number' => 'RFQ-2026-FALLBACK',
            'title' => 'Fallback office package',
            'status' => 'open',
        ]);
        $quotation = $this->createQuotation($tenant, [
            'number' => 'QUO-2026-FALLBACK',
            'status' => 'received',
            'rfq_id' => $rfq->id,
        ]);
        $award = $this->createAward($tenant, [
            'number' => 'AWD-2026-FALLBACK',
            'status' => 'recommended',
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=fallback&types[]=rfq&types[]=quotation&types[]=award&limit=10');

        $response->assertOk()
            ->assertJsonPath('meta.returned', 3)
            ->assertJsonPath('data.0.type', 'rfq')
            ->assertJsonPath('data.0.href', '/system')
            ->assertJsonPath('data.1.type', 'quotation')
            ->assertJsonPath('data.1.href', '/system')
            ->assertJsonPath('data.2.type', 'award')
            ->assertJsonPath('data.2.href', '/system');
    }

    public function test_search_is_tenant_scoped_and_omits_cross_tenant_preview_results(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');

        $visible = $this->createVendor($tenant, [
            'name' => 'Alpha Office Supplies',
            'status' => 'preferred',
            'category' => 'Office supplies',
            'risk_rating' => 'low',
        ]);
        $visibleProject = $this->createProject($tenant, $requester, [
            'number' => 'PRJ-2026-ALPHA',
            'name' => 'Alpha Workplace Refresh',
            'status' => 'active',
        ]);
        $visibleRequisition = $this->createRequisition($tenant, $requester, [
            'title' => 'Alpha workplace refresh',
            'number' => 'REQ-2026-ALPHA',
        ]);
        $visibleRfq = $this->createRfq($tenant, [
            'number' => 'RFQ-2026-ALPHA',
            'title' => 'Alpha furniture package',
            'status' => 'open',
            'project_id' => $visibleProject->id,
            'requisition_id' => $visibleRequisition->id,
        ]);
        $visibleQuotation = $this->createQuotation($tenant, [
            'number' => 'QUO-2026-ALPHA',
            'status' => 'received',
            'vendor_id' => $visible->id,
            'rfq_id' => $visibleRfq->id,
        ]);
        $visibleAward = $this->createAward($tenant, [
            'number' => 'AWD-2026-ALPHA',
            'status' => 'recommended',
            'vendor_id' => $visible->id,
            'project_id' => $visibleProject->id,
            'rfq_id' => $visibleRfq->id,
            'quotation_id' => $visibleQuotation->id,
        ]);

        $hidden = $this->createVendor($otherTenant, [
            'name' => 'Alpha Hidden Office Supplies',
            'status' => 'preferred',
            'category' => 'Office supplies',
            'risk_rating' => 'low',
        ]);
        $hiddenProject = $this->createProject($otherTenant, $otherRequester, [
            'number' => 'PRJ-2026-HIDDEN',
            'name' => 'Alpha Hidden Workplace Refresh',
            'status' => 'active',
        ]);
        $hiddenRequisition = $this->createRequisition($otherTenant, $otherRequester, [
            'title' => 'Alpha hidden workplace refresh',
            'number' => 'REQ-2026-HIDDEN',
        ]);
        $hiddenRfq = $this->createRfq($otherTenant, [
            'number' => 'RFQ-2026-HIDDEN',
            'title' => 'Alpha hidden furniture package',
            'status' => 'open',
            'project_id' => $hiddenProject->id,
            'requisition_id' => $hiddenRequisition->id,
        ]);
        $hiddenQuotation = $this->createQuotation($otherTenant, [
            'number' => 'QUO-2026-HIDDEN',
            'status' => 'received',
            'vendor_id' => $hidden->id,
            'rfq_id' => $hiddenRfq->id,
        ]);
        $hiddenAward = $this->createAward($otherTenant, [
            'number' => 'AWD-2026-HIDDEN',
            'status' => 'recommended',
            'vendor_id' => $hidden->id,
            'project_id' => $hiddenProject->id,
            'rfq_id' => $hiddenRfq->id,
            'quotation_id' => $hiddenQuotation->id,
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=alpha&types[]=vendor&types[]=project&types[]=rfq&types[]=quotation&types[]=award');

        $response->assertOk()
            ->assertJsonPath('meta.returned', 5)
            ->assertJsonPath('data.0.id', (string) $visible->id)
            ->assertJsonPath('data.1.id', (string) $visibleProject->id)
            ->assertJsonPath('data.2.id', (string) $visibleRfq->id)
            ->assertJsonPath('data.3.id', (string) $visibleQuotation->id)
            ->assertJsonPath('data.4.id', (string) $visibleAward->id)
            ->assertJsonMissing(['title' => 'Alpha Hidden Office Supplies'])
            ->assertJsonMissing(['title' => 'Alpha Hidden Workplace Refresh'])
            ->assertJsonMissing(['title' => 'Alpha hidden workplace refresh'])
            ->assertJsonMissing(['title' => 'Alpha hidden furniture package'])
            ->assertJsonMissing(['title' => 'QUO-2026-HIDDEN'])
            ->assertJsonMissing(['title' => 'AWD-2026-HIDDEN']);
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
     * @param  array<string, mixed>  $attributes
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createVendor(Tenant $tenant, array $attributes = []): Vendor
    {
        return Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['name'] ?? 'Alpha Office Supplies',
            'status' => $attributes['status'] ?? 'preferred',
            'category' => $attributes['category'] ?? 'Office supplies',
            'risk_rating' => $attributes['risk_rating'] ?? 'low',
            'metadata' => $attributes['metadata'] ?? ['demo' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createProject(Tenant $tenant, User $owner, array $attributes = []): ProcurementProject
    {
        return ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'number' => $attributes['number'] ?? 'PRJ-2026-ALPHA',
            'name' => $attributes['name'] ?? 'Alpha Workplace Refresh',
            'status' => $attributes['status'] ?? 'active',
            'budget_amount' => $attributes['budget_amount'] ?? '120000.00',
            'currency' => $attributes['currency'] ?? 'USD',
            'metadata' => $attributes['metadata'] ?? ['demo' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createRfq(Tenant $tenant, array $attributes = []): Rfq
    {
        return Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $attributes['project_id'] ?? null,
            'requisition_id' => $attributes['requisition_id'] ?? null,
            'number' => $attributes['number'] ?? 'RFQ-2026-ALPHA',
            'title' => $attributes['title'] ?? 'Alpha furniture package',
            'status' => $attributes['status'] ?? 'open',
            'due_at' => $attributes['due_at'] ?? now()->addDays(14),
            'metadata' => $attributes['metadata'] ?? ['demo' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createQuotation(Tenant $tenant, array $attributes = []): Quotation
    {
        return Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $attributes['rfq_id'] ?? null,
            'vendor_id' => $attributes['vendor_id'] ?? null,
            'number' => $attributes['number'] ?? 'QUO-2026-ALPHA',
            'status' => $attributes['status'] ?? 'received',
            'total_amount' => $attributes['total_amount'] ?? '84500.00',
            'currency' => $attributes['currency'] ?? 'USD',
            'metadata' => $attributes['metadata'] ?? ['demo' => true],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAward(Tenant $tenant, array $attributes = []): Award
    {
        return Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $attributes['project_id'] ?? null,
            'rfq_id' => $attributes['rfq_id'] ?? null,
            'quotation_id' => $attributes['quotation_id'] ?? null,
            'vendor_id' => $attributes['vendor_id'] ?? null,
            'number' => $attributes['number'] ?? 'AWD-2026-ALPHA',
            'status' => $attributes['status'] ?? 'recommended',
            'total_amount' => $attributes['total_amount'] ?? '84500.00',
            'currency' => $attributes['currency'] ?? 'USD',
            'decided_at' => $attributes['decided_at'] ?? now(),
            'metadata' => $attributes['metadata'] ?? ['demo' => true],
        ]);
    }
}

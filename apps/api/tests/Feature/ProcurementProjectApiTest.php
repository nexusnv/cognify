<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProcurementProjectApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_project(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/projects', [
                'name' => 'Office refresh',
                'charter' => 'Refresh workstations for the Kuala Lumpur office.',
                'ownerId' => (string) $buyer->id,
                'budgetAmount' => '25000.00',
                'currency' => 'MYR',
                'department' => 'Operations',
                'costCenter' => 'OPS-100',
                'targetStartDate' => '2026-06-01',
                'targetCompletionDate' => '2026-09-30',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Office refresh')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.owner.id', (string) $buyer->id)
            ->assertJsonPath('data.permissions.canActivate', true)
            ->assertJsonPath('data.summary.linkedRequisitionCount', 0);

        $this->assertDatabaseHas('procurement_projects', [
            'tenant_id' => $tenant->id,
            'owner_id' => $buyer->id,
            'name' => 'Office refresh',
            'status' => 'draft',
            'currency' => 'MYR',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'project.created',
        ]);
    }

    public function test_requester_cannot_create_project(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->postJson('/api/projects', [
                'name' => 'Unauthorized project',
                'ownerId' => (string) $requester->id,
                'budgetAmount' => '1000.00',
                'currency' => 'MYR',
            ])
            ->assertForbidden();
    }

    public function test_project_owner_must_belong_to_current_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $otherTenantUser] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/projects', [
                'name' => 'Cross tenant owner',
                'ownerId' => (string) $otherTenantUser->id,
                'budgetAmount' => '1000.00',
                'currency' => 'MYR',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_buyer_can_update_non_terminal_project(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/projects/{$project->id}", [
                'name' => 'Updated office refresh',
                'charter' => 'Updated charter.',
                'ownerId' => (string) $buyer->id,
                'budgetAmount' => '30000.00',
                'currency' => 'MYR',
                'department' => 'Operations',
                'costCenter' => 'OPS-200',
                'targetStartDate' => '2026-06-15',
                'targetCompletionDate' => '2026-10-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated office refresh')
            ->assertJsonPath('data.costCenter', 'OPS-200');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'project.updated',
        ]);
    }

    public function test_project_list_is_tenant_scoped(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $visibleProject = $this->createProject($tenant, $buyer, ['name' => 'Visible project']);
        $this->createProject($otherTenant, $otherBuyer, ['name' => 'Hidden project']);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/projects?search=project')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $visibleProject->id)
            ->assertJsonMissing(['name' => 'Hidden project']);
    }

    public function test_project_detail_includes_summary_permissions_and_owner(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer);
        $this->createRequisition($tenant, $buyer, ['project_id' => $project->id, 'status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.linkedRequisitionCount', 1)
            ->assertJsonPath('data.summary.submittedRequisitionCount', 1)
            ->assertJsonPath('data.owner.id', (string) $buyer->id)
            ->assertJsonPath('data.permissions.canLinkRequisitions', true);
    }

    public function test_completed_project_cannot_be_updated_by_buyer(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'completed']);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/projects/{$project->id}", [
                'name' => 'Cannot update completed project',
                'ownerId' => (string) $buyer->id,
                'budgetAmount' => '30000.00',
                'currency' => 'MYR',
            ])
            ->assertStatus(409);
    }

    public function test_project_activity_endpoint_returns_project_events(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer);
        AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'project.created',
            'action' => 'project.created',
            'subject_type' => ProcurementProject::class,
            'subject_id' => $project->id,
            'metadata' => ['name' => $project->name],
            'occurred_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/projects/{$project->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'project.created');
    }

    public function test_buyer_can_link_and_unlink_visible_requisition_to_project(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $buyer, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/projects/{$project->id}/requisitions", [
                'requisitionId' => (string) $requisition->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', (string) $requisition->id)
            ->assertJsonPath('data.projectId', (string) $project->id);

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'project_id' => $project->id,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/projects/{$project->id}/requisitions/{$requisition->id}")
            ->assertOk()
            ->assertJsonPath('data.projectId', null);

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'project_id' => null,
        ]);
    }

    public function test_project_cannot_link_cross_tenant_requisition(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $otherRequisition = $this->createRequisition($otherTenant, $otherBuyer);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/projects/{$project->id}/requisitions", [
                'requisitionId' => (string) $otherRequisition->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_project_requisition_list_returns_linked_requisitions(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $buyer, [
            'project_id' => $project->id,
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/projects/{$project->id}/requisitions")
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $requisition->id)
            ->assertJsonPath('data.0.status', 'submitted');
    }

    public function test_project_search_results_link_to_project_workspace(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, [
            'number' => 'PRJ-2026-000777',
            'name' => 'Warehouse launch',
            'status' => 'active',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/search?query=Warehouse&types[]=project')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'project')
            ->assertJsonPath('data.0.id', (string) $project->id)
            ->assertJsonPath('data.0.href', "/projects/{$project->id}");
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
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => $role]);

        return [$tenant, $user];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createProject(Tenant $tenant, User $owner, array $overrides = []): ProcurementProject
    {
        return ProcurementProject::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'number' => 'PRJ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => 'Office refresh',
            'charter' => 'Refresh workstations.',
            'status' => 'draft',
            'budget_amount' => '25000.00',
            'currency' => 'MYR',
            'department' => 'Operations',
            'cost_center' => 'OPS-100',
            'target_start_date' => '2026-06-01',
            'target_completion_date' => '2026-09-30',
        ], $overrides));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRequisition(Tenant $tenant, User $requester, array $overrides = []): Requisition
    {
        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Linked requisition',
            'business_justification' => 'Needed for the project.',
            'needed_by_date' => '2026-07-01',
            'status' => RequisitionStatus::Draft,
            'currency' => 'MYR',
        ], $overrides));
    }
}

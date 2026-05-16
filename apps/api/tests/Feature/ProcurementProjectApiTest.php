<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

    public function test_requester_index_only_returns_owned_or_visible_projects(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');
        [, $anotherBuyer] = $this->tenantUser('buyer', $tenant);

        $ownedProject = $this->createProject($tenant, $requester, ['name' => 'Requester owned project']);
        $visibleProject = $this->createProject($tenant, $buyer, ['name' => 'Visible linked project']);
        $hiddenProject = $this->createProject($tenant, $anotherBuyer, ['name' => 'Hidden project']);
        $this->createRequisition($tenant, $requester, [
            'project_id' => $visibleProject->id,
            'status' => RequisitionStatus::Draft,
        ]);
        $this->createProject($otherTenant, $otherBuyer, ['name' => 'Cross tenant project']);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/projects');

        $response->assertOk();

        $names = array_column($response->json('data'), 'name');

        $this->assertContains('Requester owned project', $names);
        $this->assertContains('Visible linked project', $names);
        $this->assertNotContains('Hidden project', $names);

        $this->assertDatabaseHas('procurement_projects', [
            'id' => $ownedProject->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('procurement_projects', [
            'id' => $visibleProject->id,
            'tenant_id' => $tenant->id,
        ]);
        $this->assertDatabaseHas('procurement_projects', [
            'id' => $hiddenProject->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_requester_cannot_view_project_without_visible_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $project = $this->createProject($tenant, $otherRequester, ['name' => 'Hidden workspace']);
        $this->createRequisition($tenant, $otherRequester, [
            'project_id' => $project->id,
            'status' => RequisitionStatus::Draft,
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/projects/{$project->id}")
            ->assertForbidden();
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

    public function test_terminal_project_permissions_expose_linking_as_disabled(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'cancelled']);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.canLinkRequisitions', false)
            ->assertJsonPath('data.permissions.canUnlinkRequisitions', false);
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

    public function test_login_allows_access_to_project_endpoints(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $buyer->forceFill([
            'email' => 'project-buyer@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $project = $this->createProject($tenant, $buyer);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'project-buyer@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $project->id);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $project->id);
    }

    public function test_logout_revokes_access_to_project_endpoints(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $buyer->forceFill([
            'email' => 'project-logout@example.com',
            'password' => Hash::make('secret123'),
        ])->save();
        $project = $this->createProject($tenant, $buyer);

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/login', [
                'email' => 'project-logout@example.com',
                'password' => 'secret123',
            ])
            ->assertNoContent();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/projects/{$project->id}")
            ->assertOk();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        Auth::forgetGuards();

        $this->withHeader('Origin', 'http://localhost:8880')
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/projects/{$project->id}")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_requests_are_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer);

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/projects')
            ->assertUnauthorized();

        $this->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson("/api/projects/{$project->id}")
            ->assertUnauthorized();
    }

    public function test_buyer_can_link_and_unlink_visible_requisition_to_project(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        [, $requester] = $this->tenantUser('requester', $tenant);
        $project = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

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

    public function test_requester_can_link_and_unlink_own_draft_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $project = $this->createProject($tenant, $requester, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $requester, ['status' => RequisitionStatus::Draft]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/projects/{$project->id}/requisitions", [
                'requisitionId' => (string) $requisition->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.projectId', (string) $project->id);

        $this->actingAsTenant($tenant, $requester)
            ->deleteJson("/api/projects/{$project->id}/requisitions/{$requisition->id}")
            ->assertOk()
            ->assertJsonPath('data.projectId', null);
    }

    public function test_requester_cannot_link_hidden_same_tenant_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $project = $this->createProject($tenant, $requester, ['status' => 'active']);
        $hiddenRequisition = $this->createRequisition($tenant, $otherRequester, ['status' => RequisitionStatus::Draft]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/projects/{$project->id}/requisitions", [
                'requisitionId' => (string) $hiddenRequisition->id,
            ])
            ->assertForbidden();
    }

    public function test_requester_cannot_link_or_unlink_own_submitted_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $project = $this->createProject($tenant, $requester, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/projects/{$project->id}/requisitions", [
                'requisitionId' => (string) $requisition->id,
            ])
            ->assertForbidden();

        $requisition->forceFill(['project_id' => $project->id])->save();

        $this->actingAsTenant($tenant, $requester)
            ->deleteJson("/api/projects/{$project->id}/requisitions/{$requisition->id}")
            ->assertForbidden();
    }

    public function test_resuming_on_hold_project_records_reactivated_audit_event(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, ['status' => 'active']);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/projects/{$project->id}/hold")
            ->assertOk();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/projects/{$project->id}/resume")
            ->assertOk();

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'project.reactivated',
        ]);
    }

    public function test_project_completion_date_validation_uses_stored_start_date(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $project = $this->createProject($tenant, $buyer, [
            'status' => 'draft',
            'target_start_date' => '2026-06-01',
            'target_completion_date' => '2026-09-30',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/projects/{$project->id}", [
                'targetCompletionDate' => '2026-05-31',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_project_list_can_sort_by_name_ascending(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $this->createProject($tenant, $buyer, ['name' => 'Zulu project']);
        $this->createProject($tenant, $buyer, ['name' => 'Alpha project']);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/projects?sort=name_asc');

        $response->assertOk();

        $names = array_column($response->json('data'), 'name');

        $this->assertSame(['Alpha project', 'Zulu project'], array_slice($names, 0, 2));
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

    public function test_project_cannot_relink_requisition_from_another_project(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $sourceProject = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $targetProject = $this->createProject($tenant, $buyer, ['status' => 'active']);
        $requisition = $this->createRequisition($tenant, $buyer, [
            'project_id' => $sourceProject->id,
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/projects/{$targetProject->id}/requisitions", [
                'requisitionId' => (string) $requisition->id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.message', 'Requisition is linked to another project; unlink first.');

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'project_id' => $sourceProject->id,
        ]);
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

    public function test_project_search_respects_project_visibility(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $visibleProject = $this->createProject($tenant, $requester, [
            'name' => 'Requester visible warehouse launch',
            'status' => 'active',
        ]);
        $hiddenProject = $this->createProject($tenant, $otherRequester, [
            'name' => 'Requester hidden warehouse launch',
            'status' => 'active',
        ]);
        $this->createRequisition($tenant, $requester, [
            'project_id' => $visibleProject->id,
            'status' => RequisitionStatus::Draft,
        ]);
        $this->createRequisition($tenant, $otherRequester, [
            'project_id' => $hiddenProject->id,
            'status' => RequisitionStatus::Draft,
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/search?query=warehouse&types[]=project');

        $response->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains((string) $visibleProject->id, $ids);
        $this->assertNotContains((string) $hiddenProject->id, $ids);
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

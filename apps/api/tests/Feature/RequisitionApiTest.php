<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionDepartment;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequisitionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_member_can_create_requisition_draft(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        RequisitionDepartment::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Engineering',
            'active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson('/api/requisitions', [
                'title' => 'Laptop refresh',
                'businessJustification' => 'Replace aging engineering laptops.',
                'neededByDate' => '2026-07-15',
                'department' => 'Engineering',
                'lineItems' => [
                    [
                        'name' => 'Developer laptop',
                        'description' => '14 inch laptop',
                        'quantity' => 2,
                        'unit' => 'each',
                        'estimatedUnitPrice' => '1800.00',
                        'currency' => 'USD',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Laptop refresh')
            ->assertJsonPath('data.status', RequisitionStatus::Draft->value)
            ->assertJsonPath('data.requester.id', (string) $user->id)
            ->assertJsonCount(1, 'data.lineItems');

        $this->assertDatabaseHas('requisitions', [
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'title' => 'Laptop refresh',
            'status' => RequisitionStatus::Draft->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'requisition.created',
        ]);
    }

    public function test_requester_can_update_own_draft(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $response = $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Updated laptop refresh',
                'businessJustification' => 'Updated business justification.',
                'lineItems' => [
                    [
                        'name' => 'Developer laptop',
                        'quantity' => 3,
                        'unit' => 'each',
                        'estimatedUnitPrice' => '1750.00',
                        'currency' => 'USD',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated laptop refresh')
            ->assertJsonPath('data.lineItems.0.quantity', 3);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'requisition.updated',
        ]);
    }

    public function test_requester_cannot_create_requisition_on_hidden_same_tenant_project(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $hiddenProject = $this->createProject($tenant, $otherRequester);

        $this->actingAsTenant($tenant, $requester)
            ->postJson('/api/requisitions', [
                'title' => 'Hidden project guess',
                'projectId' => (string) $hiddenProject->id,
                'lineItems' => [
                    [
                        'name' => 'Developer laptop',
                        'quantity' => 1,
                        'unit' => 'each',
                        'estimatedUnitPrice' => '1800.00',
                        'currency' => 'USD',
                    ],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('requisitions', [
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'title' => 'Hidden project guess',
            'project_id' => $hiddenProject->id,
        ]);
    }

    public function test_requester_cannot_update_requisition_onto_hidden_same_tenant_project(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $requisition = $this->createDraft($tenant, $requester);
        $hiddenProject = $this->createProject($tenant, $otherRequester);

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'projectId' => (string) $hiddenProject->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'project_id' => null,
            'lock_version' => 0,
        ]);
    }

    public function test_requester_must_send_current_lock_version_when_updating_draft(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'title' => 'Updated without lock version',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_stale_draft_update_returns_conflict_without_overwriting_current_values(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Newer saved title',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Newer saved title')
            ->assertJsonPath('data.lockVersion', 1);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Stale overwritten title',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'draft_conflict');

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'title' => 'Newer saved title',
            'lock_version' => 1,
        ]);
    }

    public function test_update_response_includes_lock_version(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Lock version response',
            ])
            ->assertOk()
            ->assertJsonPath('data.lockVersion', 1);
    }

    public function test_requisition_line_items_reject_negative_quantity_and_price(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/requisitions', [
                'title' => 'Invalid line items',
                'lineItems' => [
                    [
                        'name' => 'Credit line',
                        'quantity' => -1,
                        'unit' => 'each',
                        'estimatedUnitPrice' => '-10.00',
                        'currency' => 'USD',
                    ],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_submitted_requisition_cannot_be_updated(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user, ['status' => RequisitionStatus::Submitted]);

        $response = $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Should not update',
            ]);

        $response->assertStatus(409);
    }

    public function test_requester_can_submit_valid_draft(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::Submitted->value)
            ->assertJsonPath('data.permissions.canUpdate', false)
            ->assertJsonPath('data.permissions.canSubmit', false);

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => RequisitionStatus::Submitted->value,
        ]);

        $this->assertNotNull($requisition->fresh()->submitted_at);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'requisition.submitted',
        ]);
    }

    public function test_submitting_requisition_notifies_buyer_and_admin_but_not_requester(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->createDraft($tenant, $requester);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/submit")
            ->assertOk();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'actor_id' => $requester->id,
            'type' => 'requisition.submitted',
            'href' => "/requisitions/{$requisition->id}",
        ]);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $admin->id,
            'actor_id' => $requester->id,
            'type' => 'requisition.submitted',
            'href' => "/requisitions/{$requisition->id}",
        ]);
        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'type' => 'requisition.submitted',
        ]);
    }

    public function test_invalid_draft_cannot_be_submitted(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Incomplete requisition',
            'status' => RequisitionStatus::Draft,
        ]);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/submit");

        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_requisition_is_not_accessible_from_another_tenant(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant, $otherUser] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $response = $this->actingAsTenant($otherTenant, $otherUser)
            ->getJson("/api/requisitions/{$requisition->id}");

        $response->assertNotFound();
    }

    public function test_multi_tenant_user_must_send_tenant_header(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $otherTenant = Tenant::query()->create(['name' => 'Second tenant']);
        $otherTenant->users()->attach($user->id, ['role' => 'requester']);
        $this->createDraft($tenant, $user);

        Sanctum::actingAs($user);

        $this->getJson('/api/requisitions')
            ->assertStatus(400)
            ->assertJsonPath('error.message', 'X-Tenant-Id header is required for users with multiple tenants.')
            ->assertJsonPath('error.code', 'ambiguous_tenant');
    }

    public function test_requisition_list_clamps_per_page(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisitions?perPage=500')
            ->assertOk()
            ->assertJsonPath('meta.perPage', 100);
    }

    public function test_requisition_list_filters_by_estimated_amount_range(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $low = $this->createDraft($tenant, $user, ['title' => 'Low value']);
        $high = $this->createDraft($tenant, $user, ['title' => 'High value']);
        $low->lineItems()->update(['estimated_unit_price' => '10.00']);
        $high->lineItems()->update(['estimated_unit_price' => '2500.00']);

        $response = $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisitions?amountMin=1000&amountMax=6000')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertSame([(string) $high->id], $ids);
    }

    public function test_buyer_and_approver_can_view_submitted_requisitions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);

        $draft = $this->createDraft($tenant, $requester, ['title' => 'Hidden draft']);
        $submitted = $this->createDraft($tenant, $requester, [
            'title' => 'Visible submitted',
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/requisitions')
            ->assertOk()
            ->assertJsonMissing(['id' => $draft->id])
            ->assertJsonFragment(['id' => (string) $submitted->id]);

        $this->actingAsTenant($tenant, $approver)
            ->getJson("/api/requisitions/{$submitted->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $submitted->id);
    }

    public function test_buyer_can_request_changes_on_submitted_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/request-changes", [
                'reason' => 'Please clarify the delivery location and line item quantity.',
                'requestedFields' => ['deliveryLocation', 'lineItems', 'lineItems'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::ChangesRequested->value)
            ->assertJsonPath('data.changeRequestReason', 'Please clarify the delivery location and line item quantity.')
            ->assertJsonPath('data.changeRequestFields.0', 'deliveryLocation')
            ->assertJsonPath('data.changeRequestFields.1', 'lineItems')
            ->assertJsonPath('data.permissions.canRequestChanges', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'requisition.changes_requested',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'actor_id' => $buyer->id,
            'type' => 'requisition.changes_requested',
            'href' => "/requisitions/{$requisition->id}",
        ]);
    }

    public function test_admin_cannot_request_changes_on_draft_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->createDraft($tenant, $requester);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/requisitions/{$requisition->id}/request-changes", [
                'reason' => 'Please update this draft.',
            ])
            ->assertForbidden();
    }

    public function test_requester_can_edit_change_requested_requisition_and_resubmit(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createDraft($tenant, $requester, [
            'status' => RequisitionStatus::ChangesRequested,
            'change_request_reason' => 'Clarify line items.',
            'change_request_fields' => ['lineItems'],
            'changes_requested_by_id' => $buyer->id,
            'changes_requested_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/requisitions/{$requisition->id}", [
                'lockVersion' => 0,
                'title' => 'Updated after change request',
                'businessJustification' => 'Updated justification for resubmission.',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated after change request');

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/resubmit")
            ->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::Submitted->value)
            ->assertJsonPath('data.changeRequestReason', null)
            ->assertJsonPath('data.changeRequestFields', []);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $requester->id,
            'event_type' => 'requisition.resubmitted',
        ]);
    }

    public function test_requester_cannot_resubmit_invalid_change_requested_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createDraft($tenant, $requester, [
            'status' => RequisitionStatus::ChangesRequested,
            'business_justification' => '',
            'change_request_reason' => 'Restore the missing justification.',
            'change_request_fields' => ['businessJustification'],
            'changes_requested_by_id' => $buyer->id,
            'changes_requested_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/resubmit")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['businessJustification']]]]);

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'status' => RequisitionStatus::ChangesRequested->value,
        ]);
    }

    public function test_requester_can_withdraw_submitted_requisition_with_reason(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/withdraw", [
                'reason' => 'Budget moved to a different project.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::Withdrawn->value)
            ->assertJsonPath('data.withdrawalReason', 'Budget moved to a different project.')
            ->assertJsonPath('data.permissions.canSubmit', false)
            ->assertJsonPath('data.permissions.canWithdraw', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $requester->id,
            'event_type' => 'requisition.withdrawn',
        ]);
    }

    public function test_admin_withdrawal_notifies_original_requester(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/requisitions/{$requisition->id}/withdraw", [
                'reason' => 'Admin withdrawal on behalf of requester.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::Withdrawn->value);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'actor_id' => $admin->id,
            'type' => 'requisition.withdrawn',
            'href' => "/requisitions/{$requisition->id}",
        ]);
    }

    public function test_admin_can_cancel_submitted_requisition_with_reason(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/requisitions/{$requisition->id}/cancel", [
                'reason' => 'Duplicate request already approved outside this record.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', RequisitionStatus::Cancelled->value)
            ->assertJsonPath('data.cancellationReason', 'Duplicate request already approved outside this record.');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'event_type' => 'requisition.cancelled',
        ]);
    }

    public function test_terminal_requisitions_reject_workflow_actions_and_updates(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $admin] = $this->tenantUser('admin', $tenant);
        $withdrawn = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Withdrawn]);
        $cancelled = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Cancelled]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$withdrawn->id}/submit")
            ->assertStatus(409);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$withdrawn->id}/resubmit")
            ->assertStatus(409);

        $this->actingAsTenant($tenant, $requester)
            ->patchJson("/api/requisitions/{$withdrawn->id}", [
                'lockVersion' => 0,
                'title' => 'Should not change',
            ])
            ->assertStatus(409);

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/requisitions/{$cancelled->id}/cancel", [
                'reason' => 'Already cancelled.',
            ])
            ->assertStatus(409);
    }

    public function test_activity_endpoint_returns_audit_events_for_requisition(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'requisition.updated',
            'subject_type' => Requisition::class,
            'subject_id' => $requisition->id,
            'metadata' => ['status' => 'draft'],
            'occurred_at' => now(),
        ]);

        $response = $this->actingAsTenant($tenant, $user)
            ->getJson("/api/requisitions/{$requisition->id}/activity");

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'requisition.updated')
            ->assertJsonPath('data.0.actor.id', (string) $user->id);
    }

    public function test_requisition_response_includes_project_summary(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $project = ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $buyer->id,
            'number' => 'PRJ-2026-000001',
            'name' => 'Office refresh',
            'status' => 'active',
            'budget_amount' => '25000.00',
            'currency' => 'MYR',
        ]);
        $requisition = $this->createDraft($tenant, $requester, ['project_id' => $project->id]);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}")
            ->assertOk()
            ->assertJsonPath('data.projectId', (string) $project->id)
            ->assertJsonPath('data.projectSummary.name', 'Office refresh')
            ->assertJsonPath('data.projectSummary.number', 'PRJ-2026-000001');
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

    private function createProject(Tenant $tenant, User $owner): ProcurementProject
    {
        return ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'number' => sprintf('PRJ-2026-%06d', ProcurementProject::query()->where('tenant_id', $tenant->id)->count() + 1),
            'name' => 'Hidden workspace',
            'status' => 'active',
            'budget_amount' => '25000.00',
            'currency' => 'MYR',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDraft(Tenant $tenant, User $user, array $attributes = []): Requisition
    {
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
            'project_id' => $attributes['project_id'] ?? null,
            'currency' => $attributes['currency'] ?? 'USD',
            'status' => $attributes['status'] ?? RequisitionStatus::Draft,
            'lock_version' => $attributes['lock_version'] ?? 0,
            'submitted_at' => in_array(($attributes['status'] ?? null), [
                RequisitionStatus::Submitted,
                RequisitionStatus::ChangesRequested,
            ], true) ? now() : null,
            'changes_requested_at' => $attributes['changes_requested_at'] ?? null,
            'changes_requested_by_id' => $attributes['changes_requested_by_id'] ?? null,
            'change_request_reason' => $attributes['change_request_reason'] ?? null,
            'change_request_fields' => $attributes['change_request_fields'] ?? null,
            'withdrawn_at' => $attributes['withdrawn_at'] ?? null,
            'withdrawn_by_id' => $attributes['withdrawn_by_id'] ?? null,
            'withdrawal_reason' => $attributes['withdrawal_reason'] ?? null,
            'cancelled_at' => $attributes['cancelled_at'] ?? null,
            'cancelled_by_id' => $attributes['cancelled_by_id'] ?? null,
            'cancellation_reason' => $attributes['cancellation_reason'] ?? null,
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

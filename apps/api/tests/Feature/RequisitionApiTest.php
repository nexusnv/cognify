<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
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

    public function test_submitted_requisition_cannot_be_updated(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user, ['status' => RequisitionStatus::Submitted]);

        $response = $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", [
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
            ->assertJsonValidationErrors([
                'businessJustification',
                'neededByDate',
                'lineItems',
            ]);
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
            ->assertJsonPath('message', 'X-Tenant-Id header is required for users with multiple tenants.');
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
            ->assertJsonPath('data.0.type', 'requisition.updated')
            ->assertJsonPath('data.0.actor.id', (string) $user->id);
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
            'currency' => $attributes['currency'] ?? 'USD',
            'status' => $attributes['status'] ?? RequisitionStatus::Draft,
            'submitted_at' => ($attributes['status'] ?? null) === RequisitionStatus::Submitted ? now() : null,
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

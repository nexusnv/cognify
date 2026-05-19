<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SourcingIntakeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_or_reveal_intake_review_for_submitted_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Submitted,
        ]);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake");

        $response->assertCreated()
            ->assertJsonPath('data.requisition.id', (string) $requisition->id)
            ->assertJsonPath('data.status', SourcingIntakeStatus::Open->value)
            ->assertJsonPath('data.permissions.canClaim', true)
            ->assertJsonPath('data.permissions.canUpdate', true)
            ->assertJsonPath('data.permissions.canRecordDecision', false);

        $this->assertDatabaseHas('sourcing_intake_reviews', [
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'status' => SourcingIntakeStatus::Open->value,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'sourcing_intake.created',
        ]);
    }

    public function test_create_or_reveal_is_idempotent_for_active_review(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Approved,
        ]);

        $first = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, SourcingIntakeReview::query()->where('requisition_id', $requisition->id)->count());
    }

    public function test_requester_cannot_manage_sourcing_intake(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Submitted,
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/sourcing-intake")
            ->assertForbidden();
    }

    public function test_intake_reviews_are_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherBuyer] = $this->tenantUser('buyer', $otherTenant);

        $visible = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));
        $hidden = $this->createReview($otherTenant, $otherBuyer, $this->createRequisition($otherTenant, $otherRequester));

        $response = $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/sourcing/intake-reviews');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $visible->id);
        $this->assertNotContains((string) $hidden->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_buyer_can_claim_update_and_record_rfq_ready_decision(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, null, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::InReview->value)
            ->assertJsonPath('data.assignedBuyer.id', (string) $buyer->id);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/sourcing/intake-reviews/{$review->id}", [
                'category' => 'IT Hardware',
                'subcategory' => 'Laptops',
                'urgency' => 'standard',
                'complexity' => 'medium',
                'targetDecisionDate' => '2026-06-15',
                'checklist' => [
                    ['key' => 'specification_complete', 'label' => 'Specification complete', 'complete' => true],
                    ['key' => 'budget_clear', 'label' => 'Budget clear', 'complete' => true],
                ],
                'internalNotes' => 'Ready for competitive sourcing.',
            ])
            ->assertOk()
            ->assertJsonPath('data.category', 'IT Hardware')
            ->assertJsonPath('data.checklist.0.complete', true);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/decision", [
                'sourcingPath' => SourcingPath::NeedsRfq->value,
                'decisionReason' => 'Competitive quotes required for value and delivery comparison.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::ReadyForRfq->value)
            ->assertJsonPath('data.sourcingPath', SourcingPath::NeedsRfq->value)
            ->assertJsonPath('data.permissions.canCreateRfq', false);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'sourcing_intake.ready_for_rfq',
        ]);
    }

    public function test_clarification_decision_updates_requisition_correction_flow(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/decision", [
                'sourcingPath' => SourcingPath::NeedsClarification->value,
                'decisionReason' => 'Missing technical specifications.',
                'clarificationMessage' => 'Please add device specifications and warranty requirements.',
                'clarificationFields' => ['lineItems', 'businessJustification'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::ClarificationRequested->value)
            ->assertJsonPath('data.requisition.status', RequisitionStatus::ChangesRequested->value);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'actor_id' => $buyer->id,
            'type' => 'requisition.changes_requested',
        ]);
    }

    public function test_invalid_transition_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester), [
            'status' => SourcingIntakeStatus::ReadyForRfq,
            'sourcing_path' => SourcingPath::NeedsRfq,
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/sourcing/intake-reviews/{$review->id}", [
                'category' => 'Changed after decision',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_assigned_buyer_must_belong_to_current_tenant(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $otherTenantBuyer] = $this->tenantUser('buyer');
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/reassign", [
                'buyerId' => (string) $otherTenantBuyer->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_close_requires_reason_and_records_no_sourcing_path(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $review = $this->createReview($tenant, $buyer, $this->createRequisition($tenant, $requester));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/close")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/sourcing/intake-reviews/{$review->id}/close", [
                'sourcingPath' => SourcingPath::NoSourcingRequired->value,
                'decisionReason' => 'Request was consolidated into an existing sourcing package.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', SourcingIntakeStatus::Closed->value)
            ->assertJsonPath('data.sourcingPath', SourcingPath::NoSourcingRequired->value);
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
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createRequisition(Tenant $tenant, User $requester, array $overrides = []): Requisition
    {
        $projectId = $overrides['project_id'] ?? null;

        if ($projectId === 'make-project') {
            $project = ProcurementProject::query()->create([
                'tenant_id' => $tenant->id,
                'owner_id' => $requester->id,
                'number' => 'PRJ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
                'name' => 'Office refresh',
                'status' => 'active',
                'budget_amount' => '25000.00',
                'currency' => 'MYR',
            ]);
            $projectId = $project->id;
        }

        $requisition = Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'project_id' => $projectId,
            'number' => 'REQ-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'status' => RequisitionStatus::Submitted,
            'currency' => 'MYR',
            'submitted_at' => now(),
        ], array_diff_key($overrides, ['project_id' => true])));

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
     * @param array<string, mixed> $overrides
     */
    private function createReview(Tenant $tenant, ?User $buyer, Requisition $requisition, array $overrides = []): SourcingIntakeReview
    {
        return SourcingIntakeReview::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requisition_id' => $requisition->id,
            'project_id' => $requisition->project_id,
            'assigned_buyer_id' => $buyer?->id,
            'status' => $buyer === null ? SourcingIntakeStatus::Open : SourcingIntakeStatus::InReview,
            'sourcing_path' => null,
            'checklist' => [],
        ], $overrides));
    }
}

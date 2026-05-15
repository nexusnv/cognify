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

class CollaborationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_user_can_create_a_comment_with_visible_mentions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->requisition($tenant, $requester, RequisitionStatus::Submitted);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'Please review the attached notes.',
                'mentionedUserIds' => [(string) $requester->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.body', 'Please review the attached notes.')
            ->assertJsonPath('data.subjectType', 'requisition')
            ->assertJsonPath('data.subjectId', (string) $requisition->id)
            ->assertJsonPath('data.author.id', (string) $buyer->id)
            ->assertJsonPath('data.mentions.0.mentionedUser.id', (string) $requester->id);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'collaboration.comment_created',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $requester->id,
            'actor_id' => $buyer->id,
            'type' => 'collaboration.mentioned',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'type' => 'collaboration.mentioned',
        ]);
    }

    public function test_comment_creation_rejects_mentions_for_users_who_cannot_view_the_requisition(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->requisition($tenant, $requester, RequisitionStatus::Draft);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'Looking good.',
                'mentionedUserIds' => [(string) $buyer->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseMissing('collaboration_comments', [
            'tenant_id' => $tenant->id,
            'subject_id' => $requisition->id,
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'collaboration.comment_created',
        ]);
    }

    public function test_mention_candidates_are_limited_to_visible_users(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->requisition($tenant, $requester, RequisitionStatus::Draft);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/mention-candidates")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/mention-candidates")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertEqualsCanonicalizing([
            (string) $requester->id,
            (string) $admin->id,
        ], $ids);
        $this->assertNotContains((string) $buyer->id, $ids);
    }

    public function test_cross_tenant_comment_routes_are_blocked(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        $requisition = $this->requisition($otherTenant, $otherRequester, RequisitionStatus::Submitted);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/comments")
            ->assertNotFound();

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'Not allowed.',
            ])
            ->assertNotFound();
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

    private function requisition(Tenant $tenant, User $requester, RequisitionStatus $status): Requisition
    {
        return Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'USD',
            'status' => $status,
        ]);
    }
}

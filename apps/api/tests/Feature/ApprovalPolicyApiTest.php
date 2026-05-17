<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_policy_draft(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');

        $response = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/approval-policies', $this->policyPayload());

        $response->assertCreated()
            ->assertJsonPath('data.tenantId', (string) $tenant->id)
            ->assertJsonPath('data.name', 'Standard requisition approval')
            ->assertJsonPath('data.subjectType', 'requisition')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenantId',
                    'name',
                    'subjectType',
                    'status',
                    'versions' => [
                        [
                            'id',
                            'versionNumber',
                            'status',
                            'rules',
                            'routeTemplate',
                            'slaRules',
                            'createdAt',
                            'updatedAt',
                        ],
                    ],
                    'createdAt',
                    'updatedAt',
                ],
            ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'event_type' => 'approval_policy.created',
        ]);
    }

    public function test_non_admin_cannot_create_policy_draft(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson('/api/approval-policies', $this->policyPayload())
            ->assertForbidden();
    }

    public function test_admin_can_publish_immutable_policy_version(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        $policy = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/approval-policies', $this->policyPayload())
            ->assertCreated()
            ->json('data');
        $versionId = $policy['versions'][0]['id'];

        $publishResponse = $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/approval-policy-versions/{$versionId}/publish");

        $publishResponse->assertOk()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.versionNumber', 1)
            ->assertJsonPath('data.routeTemplate.stages.0.completionRule', 'all')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'versionNumber',
                    'status',
                    'rules',
                    'routeTemplate',
                    'slaRules',
                    'createdAt',
                    'updatedAt',
                ],
            ]);

        $this->actingAsTenant($tenant, $admin)
            ->patchJson("/api/approval-policies/{$policy['id']}", [
                'name' => 'Updated policy name',
                'routeTemplate' => [
                    'stages' => [
                        [
                            'name' => 'Finance',
                            'completionRule' => 'any',
                            'approvers' => [['type' => 'role', 'role' => 'admin']],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $this->actingAsTenant($tenant, $admin)
            ->getJson("/api/approval-policies/{$policy['id']}")
            ->assertOk()
            ->assertJsonPath('data.versions.1.status', 'published')
            ->assertJsonPath('data.versions.1.routeTemplate.stages.0.completionRule', 'all');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $admin->id,
            'event_type' => 'approval_policy.published',
        ]);
    }

    public function test_policy_version_rejects_invalid_completion_rule(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        $policy = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/approval-policies', $this->policyPayload())
            ->assertCreated()
            ->json('data');

        $this->actingAsTenant($tenant, $admin)
            ->postJson("/api/approval-policies/{$policy['id']}/versions", [
                'rules' => [],
                'routeTemplate' => [
                    'stages' => [
                        [
                            'name' => 'Invalid',
                            'completionRule' => 'majority',
                            'approvers' => [['type' => 'role', 'role' => 'admin']],
                        ],
                    ],
                ],
                'slaRules' => [['stage' => 'Invalid', 'dueInHours' => 24]],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_cross_tenant_policy_is_not_visible(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [$otherTenant, $otherAdmin] = $this->tenantUser('admin');
        $policy = $this->actingAsTenant($tenant, $admin)
            ->postJson('/api/approval-policies', $this->policyPayload())
            ->assertCreated()
            ->json('data');

        $this->actingAsTenant($otherTenant, $otherAdmin)
            ->getJson("/api/approval-policies/{$policy['id']}")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherAdmin)
            ->getJson('/api/approval-policies')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /**
     * @return array<string, mixed>
     */
    private function policyPayload(): array
    {
        return [
            'name' => 'Standard requisition approval',
            'description' => 'Default requisition approval route.',
            'subjectType' => 'requisition',
            'rules' => [
                [
                    'field' => 'amount',
                    'operator' => 'gte',
                    'value' => 1000,
                ],
            ],
            'routeTemplate' => [
                'stages' => [
                    [
                        'name' => 'Manager review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'approver'],
                        ],
                    ],
                ],
            ],
            'slaRules' => [
                [
                    'stage' => 'Manager review',
                    'dueInHours' => 48,
                    'escalateAfterHours' => 72,
                ],
            ],
        ];
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
}

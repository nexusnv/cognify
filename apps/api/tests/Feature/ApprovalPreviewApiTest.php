<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionLineItem;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalPreviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_preview_approval_path_for_own_draft(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 100,
            'rules' => [
                ['field' => 'amount', 'operator' => 'gte', 'value' => 1000],
            ],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Manager review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'approver', 'label' => 'Approver'],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Manager review', 'dueInHours' => 48, 'escalateAfterHours' => 72],
            ],
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview");

        $response->assertOk()
            ->assertJsonPath('data.createsTasks', false)
            ->assertJsonPath('data.matchedPolicy.subjectType', 'requisition')
            ->assertJsonPath('data.matchedVersion.versionNumber', 1)
            ->assertJsonPath('data.matchedConditions.0.field', 'amount')
            ->assertJsonCount(0, 'data.warnings')
            ->assertJsonStructure([
                'data' => [
                    'matchedPolicy' => [
                        'id',
                        'name',
                        'subjectType',
                        'status',
                    ],
                    'matchedVersion' => [
                        'id',
                        'versionNumber',
                        'status',
                    ],
                    'matchedConditions',
                    'stages' => [
                        [
                            'name',
                            'completionRule',
                            'approvers',
                            'fallbackApprovers',
                            'dueAt',
                            'warnings',
                        ],
                    ],
                    'warnings',
                    'estimatedDueAt',
                    'createsTasks',
                ],
            ]);
    }

    public function test_buyer_can_preview_submitted_requisition_path(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, [
            'status' => RequisitionStatus::Submitted,
            'submitted_at' => now(),
        ]);
        $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 100,
            'rules' => [
                ['field' => 'department', 'operator' => 'equals', 'value' => 'Operations'],
            ],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Buyer review',
                        'completionRule' => 'any',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Buyer review', 'dueInHours' => 24],
            ],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview")
            ->assertOk()
            ->assertJsonPath('data.matchedConditions.0.field', 'department')
            ->assertJsonPath('data.stages.0.name', 'Buyer review')
            ->assertJsonPath('data.createsTasks', false);
    }

    public function test_preview_does_not_create_approval_tasks(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester);
        $this->createPublishedPolicyVersion($tenant, $requester);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview")
            ->assertOk()
            ->assertJsonPath('data.createsTasks', false);

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_preview_explains_missing_required_context(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'department' => null,
            'cost_center' => null,
        ]);
        $this->createPublishedPolicyVersion($tenant, $requester, [
            'rules' => [
                ['field' => 'riskClassification', 'operator' => 'equals', 'value' => 'high'],
                ['field' => 'vendorId', 'operator' => 'equals', 'value' => 'vendor-42'],
            ],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Risk review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'approver', 'label' => 'Approver'],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Risk review', 'dueInHours' => 24],
            ],
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview");

        $response->assertOk()
            ->assertJsonPath('data.createsTasks', false)
            ->assertJsonPath('data.warnings.0.code', 'fallback_policy')
            ->assertJsonPath('data.warnings.1.code', 'missing_context')
            ->assertJsonPath('data.warnings.1.message', 'Missing required approval context: riskClassification, vendorId');
    }

    public function test_preview_reports_missing_context_when_fallback_policy_is_selected(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'department' => 'Operations',
            'cost_center' => 'OPS-220',
        ]);

        $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 100,
            'rules' => [
                ['field' => 'riskClassification', 'operator' => 'equals', 'value' => 'high'],
                ['field' => 'vendorId', 'operator' => 'equals', 'value' => 'vendor-42'],
            ],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Risk review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'approver', 'label' => 'Approver'],
                        ],
                        'fallbackApprovers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => [
                ['stage' => 'Risk review', 'dueInHours' => 24],
            ],
        ]);
        $fallbackVersion = $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 1,
            'rules' => [],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Fallback buyer review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview");

        $response->assertOk()
            ->assertJsonPath('data.matchedVersion.id', (string) $fallbackVersion->id)
            ->assertJsonPath('data.warnings.0.code', 'fallback_policy')
            ->assertJsonPath('data.warnings.1.code', 'missing_context')
            ->assertJsonPath(
                'data.warnings.1.message',
                'Missing required approval context affected policy matching: riskClassification, vendorId',
            );
    }

    public function test_preview_uses_fallback_policy_when_no_rule_matches(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, [
            'department' => 'Operations',
            'cost_center' => 'OPS-220',
        ]);
        $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 100,
            'rules' => [
                ['field' => 'amount', 'operator' => 'gte', 'value' => 50000],
            ],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Executive review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'admin', 'label' => 'Executive approver'],
                        ],
                    ],
                ],
            ],
        ]);
        $fallbackVersion = $this->createPublishedPolicyVersion($tenant, $requester, [
            'priority' => 1,
            'rules' => [],
            'route_template' => [
                'stages' => [
                    [
                        'name' => 'Fallback buyer review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'buyer', 'label' => 'Buyer fallback'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/approval-preview")
            ->assertOk()
            ->assertJsonPath('data.matchedVersion.id', (string) $fallbackVersion->id)
            ->assertJsonPath('data.stages.0.name', 'Fallback buyer review');
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
    private function createRequisition(Tenant $tenant, User $requester, array $attributes = []): Requisition
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => $attributes['number'] ?? sprintf(
                'REQ-2026-%06d',
                Requisition::query()->where('tenant_id', $tenant->id)->count() + 1,
            ),
            'title' => $attributes['title'] ?? 'Laptop refresh',
            'business_justification' => $attributes['business_justification'] ?? 'Replace aging laptops.',
            'needed_by_date' => $attributes['needed_by_date'] ?? '2026-07-15',
            'department' => array_key_exists('department', $attributes) ? $attributes['department'] : 'Operations',
            'project_id' => $attributes['project_id'] ?? null,
            'cost_center' => array_key_exists('cost_center', $attributes) ? $attributes['cost_center'] : 'OPS-220',
            'delivery_location' => $attributes['delivery_location'] ?? 'Shah Alam warehouse',
            'currency' => $attributes['currency'] ?? 'MYR',
            'status' => $attributes['status'] ?? RequisitionStatus::Draft,
            'lock_version' => $attributes['lock_version'] ?? 0,
            'submitted_at' => $attributes['submitted_at'] ?? null,
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
            'description' => 'Standard laptop',
            'quantity' => '2.0000',
            'unit_of_measure' => 'each',
            'estimated_unit_price' => '1800.00',
            'currency' => 'MYR',
        ]);

        return $requisition;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPublishedPolicyVersion(Tenant $tenant, User $actor, array $attributes = []): ApprovalPolicyVersion
    {
        $policy = ApprovalPolicy::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $attributes['policy_name'] ?? 'Standard requisition approval',
            'description' => $attributes['description'] ?? 'Default requisition approval route.',
            'subject_type' => 'requisition',
            'status' => ApprovalPolicyStatus::Active,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);

        return ApprovalPolicyVersion::query()->create([
            'approval_policy_id' => $policy->id,
            'tenant_id' => $tenant->id,
            'subject_type' => 'requisition',
            'version_number' => 1,
            'status' => ApprovalPolicyVersionStatus::Published,
            'priority' => $attributes['priority'] ?? 100,
            'rules' => $attributes['rules'] ?? [
                ['field' => 'amount', 'operator' => 'gte', 'value' => 1000],
            ],
            'route_template' => $attributes['route_template'] ?? [
                'stages' => [
                    [
                        'name' => 'Manager review',
                        'completionRule' => 'all',
                        'approvers' => [
                            ['type' => 'role', 'role' => 'approver', 'label' => 'Approver'],
                        ],
                    ],
                ],
            ],
            'sla_rules' => $attributes['sla_rules'] ?? [
                ['stage' => 'Manager review', 'dueInHours' => 48, 'escalateAfterHours' => 72],
            ],
            'published_by' => $actor->id,
            'published_at' => now(),
        ]);
    }
}

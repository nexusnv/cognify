<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;
use Domains\Requisition\Models\RequisitionItemSuggestion;
use Domains\Requisition\Models\RequisitionTemplate;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequisitionAuthoringApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_options_are_tenant_scoped(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant] = $this->tenantUser('requester');

        RequisitionDepartment::query()->create(['tenant_id' => $tenant->id, 'name' => 'Procurement', 'active' => true, 'sort_order' => 1]);
        RequisitionDepartment::query()->create(['tenant_id' => $otherTenant->id, 'name' => 'Hidden department', 'active' => true, 'sort_order' => 1]);
        RequisitionCostCenter::query()->create(['tenant_id' => $tenant->id, 'code' => 'OPS-110', 'name' => 'Operations', 'active' => true, 'sort_order' => 1]);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisition-intake-options')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Procurement'])
            ->assertJsonFragment(['code' => 'OPS-110'])
            ->assertJsonMissing(['name' => 'Hidden department']);
    }

    public function test_invalid_department_and_cost_center_are_rejected_when_present(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        RequisitionDepartment::query()->create(['tenant_id' => $tenant->id, 'name' => 'Procurement', 'active' => true]);
        RequisitionCostCenter::query()->create(['tenant_id' => $tenant->id, 'code' => 'OPS-110', 'name' => 'Operations', 'active' => true]);

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/requisitions', [
                'title' => 'Invalid org metadata',
                'department' => 'Finance',
                'costCenter' => 'FIN-999',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_template_list_and_apply_are_tenant_scoped_and_audited(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant] = $this->tenantUser('requester');

        $template = RequisitionTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'IT equipment',
            'description' => 'Laptop and accessories',
            'category' => 'it_equipment',
            'defaults' => [
                'businessJustification' => 'Replace or provision business equipment.',
                'lineItems' => [[
                    'name' => 'Laptop',
                    'quantity' => 1,
                    'unit' => 'each',
                    'estimatedUnitPrice' => 1800,
                    'currency' => 'MYR',
                ]],
            ],
            'active' => true,
            'sort_order' => 1,
        ]);

        RequisitionTemplate::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Hidden template',
            'category' => 'hidden',
            'defaults' => [],
            'active' => true,
        ]);

        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisition-templates')
            ->assertOk()
            ->assertJsonFragment(['name' => 'IT equipment'])
            ->assertJsonMissing(['name' => 'Hidden template']);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/apply-template", [
                'templateId' => (string) $template->id,
                'mode' => 'replace',
                'lockVersion' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.businessJustification', 'Replace or provision business equipment.')
            ->assertJsonPath('data.lineItems.0.name', 'Laptop')
            ->assertJsonPath('data.lockVersion', 1);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'requisition.template_applied',
        ]);
    }

    public function test_template_fill_empty_mode_preserves_existing_values(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $template = RequisitionTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Facilities request',
            'category' => 'facilities',
            'defaults' => [
                'businessJustification' => 'Facilities support request.',
                'deliveryLocation' => 'Kuala Lumpur HQ',
            ],
            'active' => true,
            'sort_order' => 1,
        ]);

        $requisition = $this->createDraft($tenant, $user, [
            'business_justification' => 'Existing justification',
            'lock_version' => 2,
        ]);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/apply-template", [
                'templateId' => (string) $template->id,
                'mode' => 'fill-empty',
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.businessJustification', 'Existing justification')
            ->assertJsonPath('data.deliveryLocation', 'Kuala Lumpur HQ')
            ->assertJsonPath('data.lockVersion', 3);
    }

    public function test_template_fill_empty_mode_populates_blank_placeholder_line_items(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $template = RequisitionTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'IT equipment',
            'category' => 'it_equipment',
            'defaults' => [
                'lineItems' => [[
                    'name' => 'Laptop',
                    'quantity' => 1,
                    'unit' => 'each',
                    'estimatedUnitPrice' => 1800,
                    'currency' => 'MYR',
                ]],
            ],
            'active' => true,
            'sort_order' => 1,
        ]);

        $requisition = $this->createDraft($tenant, $user, ['lock_version' => 2]);
        $requisition->lineItems()->create([
            'name' => '',
            'description' => null,
            'quantity' => 1,
            'unit_of_measure' => 'each',
            'estimated_unit_price' => 0,
            'currency' => 'MYR',
        ]);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/apply-template", [
                'templateId' => (string) $template->id,
                'mode' => 'fill-empty',
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.lineItems.0.name', 'Laptop')
            ->assertJsonPath('data.lineItems.0.quantity', 1)
            ->assertJsonPath('data.lockVersion', 3);
    }

    public function test_non_draft_requisition_cannot_receive_template_application(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $template = RequisitionTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Office supplies',
            'category' => 'office_supplies',
            'defaults' => [],
            'active' => true,
        ]);
        $requisition = $this->createDraft($tenant, $user, ['status' => RequisitionStatus::Submitted]);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/apply-template", [
                'templateId' => (string) $template->id,
                'mode' => 'replace',
                'lockVersion' => 0,
            ])
            ->assertStatus(409);
    }

    public function test_template_application_skips_invalid_line_items_without_a_name(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $template = RequisitionTemplate::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Broken template',
            'category' => 'broken',
            'defaults' => [
                'lineItems' => [[
                    'quantity' => 1,
                    'unit' => 'each',
                    'estimatedUnitPrice' => 1800,
                    'currency' => 'MYR',
                ]],
            ],
            'active' => true,
            'sort_order' => 1,
        ]);

        $requisition = $this->createDraft($tenant, $user, ['lock_version' => 1]);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/apply-template", [
                'templateId' => (string) $template->id,
                'mode' => 'replace',
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.lineItems')
            ->assertJsonPath('data.lockVersion', 2);
    }

    public function test_suggestions_are_tenant_scoped_and_searchable(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant] = $this->tenantUser('requester');

        RequisitionItemSuggestion::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Laptop',
            'category' => 'it_equipment',
            'unit' => 'each',
            'estimated_unit_price' => '1800.00',
            'currency' => 'MYR',
            'aliases' => ['notebook'],
            'active' => true,
        ]);

        RequisitionItemSuggestion::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Hidden laptop',
            'category' => 'hidden',
            'unit' => 'each',
            'estimated_unit_price' => '1.00',
            'currency' => 'MYR',
            'active' => true,
        ]);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisition-line-item-suggestions?search=lap')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Laptop'])
            ->assertJsonMissing(['name' => 'Hidden laptop']);
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
    private function createDraft(Tenant $tenant, User $user, array $attributes = []): Requisition
    {
        return Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => 'REQ-2026-000001',
            'title' => $attributes['title'] ?? 'Laptop refresh',
            'business_justification' => $attributes['business_justification'] ?? 'Replace aging laptops.',
            'needed_by_date' => $attributes['needed_by_date'] ?? '2026-07-15',
            'currency' => $attributes['currency'] ?? 'MYR',
            'status' => $attributes['status'] ?? RequisitionStatus::Draft,
            'lock_version' => $attributes['lock_version'] ?? 0,
            'submitted_at' => ($attributes['status'] ?? null) === RequisitionStatus::Submitted ? now() : null,
        ]);
    }
}

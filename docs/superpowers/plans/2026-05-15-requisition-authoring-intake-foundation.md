# Requisition Authoring And Intake Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the P1 Epic 1 requester authoring workflow so requesters can create, save, reopen, autosave, template, and enrich requisition drafts without data loss.

**Architecture:** Extend the existing requisition slice instead of replacing it. Laravel domain behavior stays in `apps/api/Domains/Requisition`, OpenAPI remains the contract source, `@cognify/api-client` is regenerated for frontend usage, and Cognify-specific UI remains under `apps/web/features/requisitions`.

**Tech Stack:** Laravel, Sanctum, Eloquent migrations, OpenAPI/Orval, Next.js App Router, React 19, TanStack Query, Vitest, MSW, Playwright.

---

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-15-requisition-authoring-intake-foundation-design.md`
- Runbook: `docs/05-runbooks/feature-development.md`
- Greenfield design: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Epic source: `docs/02-release-management/2026-05-15-P1-Epics.md`

## Locked Implementation Decisions

- Use `lock_version` integer concurrency instead of deriving version from `updated_at`.
- Store seeded organization metadata in Requisition-domain tables: `requisition_departments` and `requisition_cost_centers`.
- Emit a distinct `requisition.template_applied` audit event when template application changes a draft.
- Use existing Playwright harness: `pnpm --filter @cognify/web test:e2e`.
- Keep submission behavior compatible but do not add new Epic 2 submission features.

## File Map

### Backend

- Modify: `apps/api/database/migrations/2026_05_10_000002_create_requisitions_table.php` to add `lock_version` for fresh databases.
- Create: `apps/api/database/migrations/2026_05_15_020000_create_requisition_authoring_support_tables.php` for templates, suggestions, departments, and cost centers.
- Create: `apps/api/database/seeders/Demo/DemoRequisitionAuthoringSeeder.php` for demo authoring data.
- Modify: `apps/api/database/seeders/DatabaseSeeder.php` to call the authoring seeder from the demo seed flow.
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php` for `lock_version` fill/cast.
- Create: `apps/api/Domains/Requisition/Models/RequisitionTemplate.php`.
- Create: `apps/api/Domains/Requisition/Models/RequisitionItemSuggestion.php`.
- Create: `apps/api/Domains/Requisition/Models/RequisitionDepartment.php`.
- Create: `apps/api/Domains/Requisition/Models/RequisitionCostCenter.php`.
- Modify: `apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php` to require `lockVersion` on updates.
- Modify: `apps/api/app/Http/Requests/Requisition/CreateRequisitionRequest.php` for org metadata validation when present.
- Create: `apps/api/Domains/Requisition/Http/Requests/ApplyRequisitionTemplateRequest.php`.
- Modify: `apps/api/Domains/Requisition/Actions/CreateRequisitionDraft.php` for org metadata validation helpers if not in FormRequest.
- Modify: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php` for concurrency, org metadata validation, and lock increment.
- Create: `apps/api/Domains/Requisition/Actions/ApplyRequisitionTemplate.php`.
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionTemplateController.php`.
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionItemSuggestionController.php`.
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionIntakeOptionsController.php`.
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php` for template route action and lock-aware update behavior.
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php` to expose `lockVersion`.
- Create: `apps/api/Domains/Requisition/Http/Resources/RequisitionTemplateResource.php`.
- Create: `apps/api/Domains/Requisition/Http/Resources/RequisitionItemSuggestionResource.php`.
- Modify: `apps/api/routes/api.php` to add authoring support routes.
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php` for lock/version regression tests.
- Create: `apps/api/tests/Feature/RequisitionAuthoringApiTest.php` for templates, suggestions, and intake options.

### Contract And Client

- Modify: `apps/api/storage/openapi/openapi.json`.
- Regenerate: `packages/api-client/src/generated/*`.
- Modify: `packages/api-client/src/index.ts` only if new generated exports are not already surfaced.

### Frontend

- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts` for `lockVersion`, templates, suggestions, and intake options.
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts` for generated template/suggestion/intake endpoints and lock-aware draft updates.
- Modify: `apps/web/features/requisitions/hooks/use-save-requisition-draft.ts` or replace with focused hooks below.
- Create: `apps/web/features/requisitions/hooks/use-requisition-draft-save-controller.ts`.
- Create: `apps/web/features/requisitions/hooks/use-requisition-intake-options.ts`.
- Create: `apps/web/features/requisitions/hooks/use-requisition-templates.ts`.
- Create: `apps/web/features/requisitions/hooks/use-requisition-line-item-suggestions.ts`.
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx` to delegate save orchestration and render templates/suggestions.
- Create: `apps/web/features/requisitions/components/requisition-template-picker.tsx`.
- Create: `apps/web/features/requisitions/components/requisition-line-item-suggestion-combobox.tsx`.
- Create: `apps/web/features/requisitions/components/requisition-save-conflict-panel.tsx`.
- Modify: `apps/web/features/requisitions/schemas/requisition-form-schema.ts` for zero estimated price draft compatibility and lock-aware values if needed.
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`.
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`.
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`.
- Create: `apps/web/features/requisitions/tests/requisition-draft-save-controller.test.tsx`.
- Create: `apps/web/tests/e2e/requisition-authoring.spec.ts`.

---

## Task 1: Baseline And Worktree Safety

**Files:**
- Read: `AGENTS.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `docs/superpowers/specs/2026-05-15-requisition-authoring-intake-foundation-design.md`

- [ ] **Step 1: Check current branch and uncommitted files**

Run:

```bash
git status --short --branch
```

Expected: The branch and any uncommitted files are visible. Do not overwrite unrelated user changes.

- [ ] **Step 2: Confirm baseline focused tests before changing behavior**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx
cd apps/api && php artisan test --filter=RequisitionApiTest
```

Expected: Existing requisition tests pass. If a test fails before edits, capture the failure and apply `superpowers:systematic-debugging` before modifying production code.

- [ ] **Step 3: Commit nothing in this task unless baseline docs were changed intentionally**

Run:

```bash
git status --short
```

Expected: No implementation files changed by this task.

---

## Task 2: Backend Concurrency Contract Tests

**Files:**
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php`

- [ ] **Step 1: Write failing tests for lock-aware update behavior**

Add these tests near the existing update tests in `apps/api/tests/Feature/RequisitionApiTest.php`:

```php
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
```

Update the helper `createDraft()` in the same file to include the expected initial value:

```php
'lock_version' => $attributes['lock_version'] ?? 0,
```

- [ ] **Step 2: Run tests to verify they fail for missing implementation**

Run:

```bash
cd apps/api && php artisan test --filter='requester_must_send_current_lock_version|stale_draft_update|update_response_includes_lock_version'
```

Expected: FAIL because `lock_version` is not migrated/exposed/enforced yet.

- [ ] **Step 3: Commit failing tests only**

Run:

```bash
git add apps/api/tests/Feature/RequisitionApiTest.php
git commit -m "test: cover requisition draft save conflicts"
```

Expected: Commit created with failing regression tests.

---

## Task 3: Backend Lock Version Implementation

**Files:**
- Modify: `apps/api/database/migrations/2026_05_10_000002_create_requisitions_table.php`
- Create: `apps/api/database/migrations/2026_05_15_020001_add_lock_version_to_requisitions_table.php` if the existing migration has already run in local databases.
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify: `apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php`
- Modify: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`

- [ ] **Step 1: Add lock version to migrations**

In `apps/api/database/migrations/2026_05_10_000002_create_requisitions_table.php`, add this column after `status`:

```php
$table->unsignedInteger('lock_version')->default(0);
```

Create `apps/api/database/migrations/2026_05_15_020001_add_lock_version_to_requisitions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            if (! Schema::hasColumn('requisitions', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(0)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            if (Schema::hasColumn('requisitions', 'lock_version')) {
                $table->dropColumn('lock_version');
            }
        });
    }
};
```

- [ ] **Step 2: Add model fill and cast**

In `apps/api/Domains/Requisition/Models/Requisition.php`, add `lock_version` to `$fillable`:

```php
'lock_version',
```

Add the cast:

```php
'lock_version' => 'integer',
```

- [ ] **Step 3: Require lockVersion on update requests**

In `apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php`, add this rule:

```php
'lockVersion' => ['required', 'integer', 'min:0'],
```

- [ ] **Step 4: Enforce stale-save detection and increment**

In `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`, before the transaction, add:

```php
if ((int) $data['lockVersion'] !== (int) $requisition->lock_version) {
    throw new ConflictHttpException('The draft has changed since it was loaded.', null, 0);
}
```

Inside the `fill([...])` array, add:

```php
'lock_version' => $requisition->lock_version + 1,
```

In the audit `before` payload, add:

```php
'lockVersion' => $requisition->lock_version,
```

In the audit `after` payload, add:

```php
'lockVersion' => $requisition->lock_version,
```

If the existing exception handler maps every `ConflictHttpException` to `invalid_state`, update the API error mapping so this message/code returns:

```json
{"error":{"code":"draft_conflict","message":"The draft has changed since it was loaded."}}
```

Do not change non-draft update conflicts away from their existing invalid-state behavior.

- [ ] **Step 5: Expose lockVersion in resource**

In `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`, add after `status`:

```php
'lockVersion' => $this->lock_version,
```

- [ ] **Step 6: Run focused backend tests**

Run:

```bash
cd apps/api && php artisan test --filter='requester_must_send_current_lock_version|stale_draft_update|update_response_includes_lock_version|requester_can_update_own_draft|submitted_requisition_cannot_be_updated'
```

Expected: PASS.

- [ ] **Step 7: Commit implementation**

Run:

```bash
git add apps/api/database/migrations apps/api/Domains/Requisition/Models/Requisition.php apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php apps/api/tests/Feature/RequisitionApiTest.php
git commit -m "feat: add requisition draft conflict detection"
```

Expected: Commit created.

---

## Task 4: Authoring Support Backend Tests

**Files:**
- Create: `apps/api/tests/Feature/RequisitionAuthoringApiTest.php`

- [ ] **Step 1: Add failing feature tests for org metadata, templates, and suggestions**

Create `apps/api/tests/Feature/RequisitionAuthoringApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
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
     * @param array<string, mixed> $attributes
     */
    private function createDraft(Tenant $tenant, User $user, array $attributes = []): Requisition
    {
        $requisition = Requisition::query()->create([
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

        return $requisition;
    }
}
```

- [ ] **Step 2: Run tests to verify they fail for missing models/routes**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionAuthoringApiTest
```

Expected: FAIL because support models, tables, routes, and actions do not exist.

- [ ] **Step 3: Commit failing tests**

Run:

```bash
git add apps/api/tests/Feature/RequisitionAuthoringApiTest.php
git commit -m "test: cover requisition authoring support APIs"
```

Expected: Commit created.

---

## Task 5: Authoring Support Tables And Models

**Files:**
- Create: `apps/api/database/migrations/2026_05_15_020000_create_requisition_authoring_support_tables.php`
- Create: `apps/api/Domains/Requisition/Models/RequisitionTemplate.php`
- Create: `apps/api/Domains/Requisition/Models/RequisitionItemSuggestion.php`
- Create: `apps/api/Domains/Requisition/Models/RequisitionDepartment.php`
- Create: `apps/api/Domains/Requisition/Models/RequisitionCostCenter.php`

- [ ] **Step 1: Create migration for support tables**

Create `apps/api/database/migrations/2026_05_15_020000_create_requisition_authoring_support_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisition_departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'active', 'sort_order']);
        });

        Schema::create('requisition_cost_centers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'active', 'sort_order']);
        });

        Schema::create('requisition_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category');
            $table->json('defaults');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'active', 'sort_order']);
            $table->index(['category', 'active']);
        });

        Schema::create('requisition_item_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('unit');
            $table->decimal('estimated_unit_price', 14, 2)->default(0);
            $table->char('currency', 3)->default('MYR');
            $table->json('aliases')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'active', 'sort_order']);
            $table->index(['category', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_item_suggestions');
        Schema::dropIfExists('requisition_templates');
        Schema::dropIfExists('requisition_cost_centers');
        Schema::dropIfExists('requisition_departments');
    }
};
```

- [ ] **Step 2: Create model classes**

Create `apps/api/Domains/Requisition/Models/RequisitionTemplate.php`:

```php
<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'defaults',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'defaults' => 'array',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Create `apps/api/Domains/Requisition/Models/RequisitionItemSuggestion.php`:

```php
<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItemSuggestion extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'unit',
        'estimated_unit_price',
        'currency',
        'aliases',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'estimated_unit_price' => 'decimal:2',
            'aliases' => 'array',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Create `apps/api/Domains/Requisition/Models/RequisitionDepartment.php`:

```php
<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionDepartment extends Model
{
    protected $fillable = ['tenant_id', 'name', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Create `apps/api/Domains/Requisition/Models/RequisitionCostCenter.php`:

```php
<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionCostCenter extends Model
{
    protected $fillable = ['tenant_id', 'code', 'name', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

- [ ] **Step 3: Run model/migration tests again**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionAuthoringApiTest
```

Expected: Tests still fail because routes/resources/actions are not implemented, but model/table class errors are gone.

- [ ] **Step 4: Commit tables and models**

Run:

```bash
git add apps/api/database/migrations apps/api/Domains/Requisition/Models
git commit -m "feat: add requisition authoring support models"
```

Expected: Commit created.

---

## Task 6: Backend Authoring Resources, Actions, And Routes

**Files:**
- Create: `apps/api/Domains/Requisition/Http/Resources/RequisitionTemplateResource.php`
- Create: `apps/api/Domains/Requisition/Http/Resources/RequisitionItemSuggestionResource.php`
- Create: `apps/api/Domains/Requisition/Http/Requests/ApplyRequisitionTemplateRequest.php`
- Create: `apps/api/Domains/Requisition/Actions/ApplyRequisitionTemplate.php`
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionTemplateController.php`
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionItemSuggestionController.php`
- Create: `apps/api/Domains/Requisition/Http/Controllers/RequisitionIntakeOptionsController.php`
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Create resources**

Create `apps/api/Domains/Requisition/Http/Resources/RequisitionTemplateResource.php`:

```php
<?php

namespace Domains\Requisition\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequisitionTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'defaults' => $this->defaults,
        ];
    }
}
```

Create `apps/api/Domains/Requisition/Http/Resources/RequisitionItemSuggestionResource.php`:

```php
<?php

namespace Domains\Requisition\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequisitionItemSuggestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'unit' => $this->unit,
            'estimatedUnitPrice' => (float) $this->estimated_unit_price,
            'currency' => $this->currency,
        ];
    }
}
```

- [ ] **Step 2: Create template request**

Create `apps/api/Domains/Requisition/Http/Requests/ApplyRequisitionTemplateRequest.php`:

```php
<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyRequisitionTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'templateId' => ['required', 'string'],
            'mode' => ['required', 'string', 'in:fill-empty,replace'],
            'lockVersion' => ['required', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 3: Create apply action**

Create `apps/api/Domains/Requisition/Actions/ApplyRequisitionTemplate.php`:

```php
<?php

namespace Domains\Requisition\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Models\RequisitionTemplate;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApplyRequisitionTemplate
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition, RequisitionTemplate $template, string $mode, int $lockVersion): Requisition
    {
        if ($requisition->status !== RequisitionStatus::Draft) {
            throw new ConflictHttpException('Only draft requisitions can receive templates.');
        }

        if ($lockVersion !== (int) $requisition->lock_version) {
            throw new ConflictHttpException('The draft has changed since it was loaded.');
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $template, $mode): Requisition {
            $before = [
                'title' => $requisition->title,
                'businessJustification' => $requisition->business_justification,
                'lineItemCount' => $requisition->lineItems()->count(),
                'lockVersion' => $requisition->lock_version,
            ];
            $defaults = $template->defaults ?? [];

            $attributes = [
                'business_justification' => $this->valueFor($mode, $requisition->business_justification, Arr::get($defaults, 'businessJustification')),
                'department' => $this->valueFor($mode, $requisition->department, Arr::get($defaults, 'department')),
                'cost_center' => $this->valueFor($mode, $requisition->cost_center, Arr::get($defaults, 'costCenter')),
                'delivery_location' => $this->valueFor($mode, $requisition->delivery_location, Arr::get($defaults, 'deliveryLocation')),
                'currency' => strtoupper($this->valueFor($mode, $requisition->currency, Arr::get($defaults, 'currency', $requisition->currency)) ?? 'MYR'),
                'lock_version' => $requisition->lock_version + 1,
            ];

            if ($mode === 'replace' && Arr::get($defaults, 'title')) {
                $attributes['title'] = Arr::get($defaults, 'title');
            }

            $requisition->forceFill($attributes)->save();

            if ($mode === 'replace' && is_array(Arr::get($defaults, 'lineItems'))) {
                $requisition->lineItems()->delete();
                foreach (Arr::get($defaults, 'lineItems') as $lineItem) {
                    $requisition->lineItems()->create([
                        'name' => $lineItem['name'],
                        'description' => $lineItem['description'] ?? null,
                        'quantity' => $lineItem['quantity'] ?? 1,
                        'unit_of_measure' => $lineItem['unit'] ?? 'each',
                        'estimated_unit_price' => $lineItem['estimatedUnitPrice'] ?? 0,
                        'currency' => strtoupper($lineItem['currency'] ?? $requisition->currency),
                    ]);
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'requisition.template_applied',
                subject: $requisition,
                metadata: ['templateId' => (string) $template->id, 'mode' => $mode],
                before: $before,
                after: [
                    'businessJustification' => $requisition->business_justification,
                    'lineItemCount' => $requisition->lineItems()->count(),
                    'lockVersion' => $requisition->lock_version,
                ],
                subjectDisplay: $requisition->number,
            ));

            return $requisition->refresh()->load(['requester', 'lineItems']);
        });
    }

    private function valueFor(string $mode, mixed $current, mixed $incoming): mixed
    {
        if ($mode === 'replace') {
            return $incoming ?? $current;
        }

        return blank($current) ? $incoming : $current;
    }
}
```

- [ ] **Step 4: Create controllers**

Create `apps/api/Domains/Requisition/Http/Controllers/RequisitionTemplateController.php`:

```php
<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Http\Resources\RequisitionTemplateResource;
use Domains\Requisition\Models\RequisitionTemplate;

class RequisitionTemplateController extends Controller
{
    public function index(CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();

        $templates = RequisitionTemplate::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return RequisitionTemplateResource::collection($templates);
    }
}
```

Create `apps/api/Domains/Requisition/Http/Controllers/RequisitionItemSuggestionController.php`:

```php
<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Http\Resources\RequisitionItemSuggestionResource;
use Domains\Requisition\Models\RequisitionItemSuggestion;
use Illuminate\Http\Request;

class RequisitionItemSuggestionController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();
        $search = strtolower((string) $request->query('search', ''));

        $suggestions = RequisitionItemSuggestion::query()
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->when($request->query('category'), fn ($query, string $category) => $query->where('category', $category))
            ->when($request->query('currency'), fn ($query, string $currency) => $query->where('currency', strtoupper($currency)))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(category) LIKE ?', ["%{$search}%"]);
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(10)
            ->get();

        return RequisitionItemSuggestionResource::collection($suggestions);
    }
}
```

Create `apps/api/Domains/Requisition/Http/Controllers/RequisitionIntakeOptionsController.php`:

```php
<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;

class RequisitionIntakeOptionsController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();

        return response()->json([
            'data' => [
                'departments' => RequisitionDepartment::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['name'])
                    ->map(fn (RequisitionDepartment $department): array => ['name' => $department->name])
                    ->values(),
                'costCenters' => RequisitionCostCenter::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['code', 'name'])
                    ->map(fn (RequisitionCostCenter $costCenter): array => ['code' => $costCenter->code, 'name' => $costCenter->name])
                    ->values(),
                'currencies' => ['MYR', 'USD'],
                'units' => ['each', 'bundle', 'month', 'hour', 'day'],
            ],
        ]);
    }
}
```

- [ ] **Step 5: Wire apply-template controller action**

In `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`, import:

```php
use Domains\Requisition\Actions\ApplyRequisitionTemplate;
use Domains\Requisition\Http\Requests\ApplyRequisitionTemplateRequest;
use Domains\Requisition\Models\RequisitionTemplate;
```

Add this method before `findTenantRequisition()`:

```php
public function applyTemplate(
    ApplyRequisitionTemplateRequest $request,
    CurrentTenant $currentTenant,
    ApplyRequisitionTemplate $applyRequisitionTemplate,
    int $requisition,
): RequisitionResource {
    $tenant = $currentTenant->get();
    $requisition = $this->findTenantRequisition($currentTenant, $requisition);
    $this->authorize('update', $requisition);

    $template = RequisitionTemplate::query()
        ->where('active', true)
        ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
        ->findOrFail((int) $request->validated('templateId'));

    $requisition = $applyRequisitionTemplate->handle(
        $tenant,
        $request->user(),
        $requisition,
        $template,
        $request->validated('mode'),
        (int) $request->validated('lockVersion'),
    );

    return new RequisitionResource($requisition);
}
```

- [ ] **Step 6: Add routes**

In `apps/api/routes/api.php`, import the controllers and add inside the authenticated API group with existing requisition routes:

```php
Route::get('/requisition-templates', [RequisitionTemplateController::class, 'index']);
Route::get('/requisition-line-item-suggestions', [RequisitionItemSuggestionController::class, 'index']);
Route::get('/requisition-intake-options', RequisitionIntakeOptionsController::class);
Route::post('/requisitions/{requisition}/apply-template', [RequisitionController::class, 'applyTemplate']);
```

- [ ] **Step 7: Run authoring API tests**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionAuthoringApiTest
```

Expected: PASS or only org validation tests fail until Task 7.

- [ ] **Step 8: Commit resources/actions/routes**

Run:

```bash
git add apps/api/Domains/Requisition/Http apps/api/Domains/Requisition/Actions/ApplyRequisitionTemplate.php apps/api/routes/api.php apps/api/tests/Feature/RequisitionAuthoringApiTest.php
git commit -m "feat: add requisition authoring support APIs"
```

Expected: Commit created.

---

## Task 7: Server-Side Org Metadata Validation

**Files:**
- Modify: `apps/api/app/Http/Requests/Requisition/CreateRequisitionRequest.php`
- Modify: `apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php`

- [ ] **Step 1: Add validation helper to create request**

In `CreateRequisitionRequest`, import:

```php
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;
use Illuminate\Validation\Rule;
```

At the start of `rules()`, resolve the tenant ID:

```php
$tenantId = app(\App\Tenancy\CurrentTenant::class)->get()->id;
```

Replace the `department` and `costCenter` rules with:

```php
'department' => [
    'nullable',
    'string',
    'max:255',
    Rule::exists(RequisitionDepartment::class, 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
],
'costCenter' => [
    'nullable',
    'string',
    'max:255',
    Rule::exists(RequisitionCostCenter::class, 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
],
```

- [ ] **Step 2: Add the same validation to update request**

In `UpdateRequisitionRequest`, import the same classes and replace department/costCenter rules with `sometimes`, preserving update semantics:

```php
$tenantId = app(\App\Tenancy\CurrentTenant::class)->get()->id;
```

```php
'department' => [
    'sometimes',
    'nullable',
    'string',
    'max:255',
    Rule::exists(RequisitionDepartment::class, 'name')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
],
'costCenter' => [
    'sometimes',
    'nullable',
    'string',
    'max:255',
    Rule::exists(RequisitionCostCenter::class, 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('active', true)),
],
```

- [ ] **Step 3: Run focused backend tests**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionAuthoringApiTest|RequisitionApiTest'
```

Expected: PASS. If existing `RequisitionApiTest` creates departments/cost centers without seed data, adjust those tests to seed valid org metadata where department/costCenter are sent.

- [ ] **Step 4: Commit validation**

Run:

```bash
git add apps/api/app/Http/Requests/Requisition apps/api/tests/Feature
git commit -m "feat: validate requisition authoring org metadata"
```

Expected: Commit created.

---

## Task 8: Demo Seed Data

**Files:**
- Create: `apps/api/database/seeders/Demo/DemoRequisitionAuthoringSeeder.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php` or `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`

- [ ] **Step 1: Add demo authoring seeder**

Create `apps/api/database/seeders/Demo/DemoRequisitionAuthoringSeeder.php`:

```php
<?php

namespace Database\Seeders\Demo;

use App\Tenancy\Tenant;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;
use Domains\Requisition\Models\RequisitionItemSuggestion;
use Domains\Requisition\Models\RequisitionTemplate;
use Illuminate\Database\Seeder;

class DemoRequisitionAuthoringSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            foreach ([['Procurement', 1], ['Operations', 2], ['IT', 3], ['Finance', 4]] as [$name, $sort]) {
                RequisitionDepartment::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    ['active' => true, 'sort_order' => $sort],
                );
            }

            foreach ([['OPS-110', 'Operations', 1], ['IT-210', 'Information Technology', 2], ['FIN-310', 'Finance', 3]] as [$code, $name, $sort]) {
                RequisitionCostCenter::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    ['name' => $name, 'active' => true, 'sort_order' => $sort],
                );
            }

            RequisitionTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'IT equipment'],
                [
                    'description' => 'Laptop, monitor, and accessory purchases.',
                    'category' => 'it_equipment',
                    'defaults' => [
                        'department' => 'IT',
                        'costCenter' => 'IT-210',
                        'businessJustification' => 'Provision or replace equipment required for business operations.',
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
                ],
            );

            RequisitionTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'SaaS subscription'],
                [
                    'description' => 'Software subscription or renewal request.',
                    'category' => 'saas_subscription',
                    'defaults' => [
                        'department' => 'IT',
                        'costCenter' => 'IT-210',
                        'businessJustification' => 'Maintain software access required for business continuity.',
                        'lineItems' => [[
                            'name' => 'SaaS subscription',
                            'quantity' => 12,
                            'unit' => 'month',
                            'estimatedUnitPrice' => 250,
                            'currency' => 'MYR',
                        ]],
                    ],
                    'active' => true,
                    'sort_order' => 2,
                ],
            );

            foreach ([
                ['Laptop', 'it_equipment', 'each', 1800, ['notebook', 'computer'], 1],
                ['Monitor', 'it_equipment', 'each', 700, ['display', 'screen'], 2],
                ['SaaS subscription', 'saas_subscription', 'month', 250, ['software', 'license'], 3],
                ['Packing box bundle', 'office_supplies', 'bundle', 170, ['boxes', 'packaging'], 4],
            ] as [$name, $category, $unit, $price, $aliases, $sort]) {
                RequisitionItemSuggestion::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'category' => $category,
                        'unit' => $unit,
                        'estimated_unit_price' => $price,
                        'currency' => 'MYR',
                        'aliases' => $aliases,
                        'active' => true,
                        'sort_order' => $sort,
                    ],
                );
            }
        });
    }
}
```

- [ ] **Step 2: Call seeder from existing demo seed flow**

Add this call where demo seeders are orchestrated:

```php
$this->call(\Database\Seeders\Demo\DemoRequisitionAuthoringSeeder::class);
```

- [ ] **Step 3: Run seeder smoke test**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionAuthoringApiTest
```

Expected: PASS.

- [ ] **Step 4: Commit seeding**

Run:

```bash
git add apps/api/database/seeders
git commit -m "feat: seed requisition authoring options"
```

Expected: Commit created.

---

## Task 9: OpenAPI And Generated Client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Generate: `packages/api-client/src/generated/*`

- [ ] **Step 1: Update OpenAPI schemas**

In `apps/api/storage/openapi/openapi.json`, update `Requisition` schema with:

```json
"lockVersion": { "type": "integer", "minimum": 0 }
```

Update `UpdateRequisitionRequest` schema with required `lockVersion`:

```json
"lockVersion": { "type": "integer", "minimum": 0 }
```

Add schemas equivalent to:

```json
"RequisitionTemplate": {
  "type": "object",
  "required": ["id", "name", "category", "defaults"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" },
    "description": { "type": ["string", "null"] },
    "category": { "type": "string" },
    "defaults": { "type": "object", "additionalProperties": true }
  }
},
"ApplyRequisitionTemplateRequest": {
  "type": "object",
  "required": ["templateId", "mode", "lockVersion"],
  "properties": {
    "templateId": { "type": "string" },
    "mode": { "type": "string", "enum": ["fill-empty", "replace"] },
    "lockVersion": { "type": "integer", "minimum": 0 }
  }
},
"RequisitionItemSuggestion": {
  "type": "object",
  "required": ["id", "name", "unit", "estimatedUnitPrice", "currency"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" },
    "category": { "type": ["string", "null"] },
    "unit": { "type": "string" },
    "estimatedUnitPrice": { "type": "number" },
    "currency": { "type": "string", "minLength": 3, "maxLength": 3 }
  }
},
"RequisitionIntakeOptions": {
  "type": "object",
  "required": ["departments", "costCenters", "currencies", "units"],
  "properties": {
    "departments": { "type": "array", "items": { "type": "object", "required": ["name"], "properties": { "name": { "type": "string" } } } },
    "costCenters": { "type": "array", "items": { "type": "object", "required": ["code", "name"], "properties": { "code": { "type": "string" }, "name": { "type": "string" } } } },
    "currencies": { "type": "array", "items": { "type": "string" } },
    "units": { "type": "array", "items": { "type": "string" } }
  }
}
```

Add paths:

```json
"/api/requisition-templates": { "get": { "operationId": "listRequisitionTemplates" } },
"/api/requisitions/{requisitionId}/apply-template": { "post": { "operationId": "applyRequisitionTemplate" } },
"/api/requisition-line-item-suggestions": { "get": { "operationId": "listRequisitionLineItemSuggestions" } },
"/api/requisition-intake-options": { "get": { "operationId": "getRequisitionIntakeOptions" } }
```

Use the existing file's response/error schema style. Do not introduce a second error contract.

- [ ] **Step 2: Regenerate client and verify contract**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: PASS and generated endpoint/schema files changed.

- [ ] **Step 3: Typecheck generated client**

Run:

```bash
pnpm --filter @cognify/api-client typecheck
```

Expected: PASS.

- [ ] **Step 4: Commit contract/client**

Run:

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated packages/api-client/src/index.ts packages/api-client/src/client.ts
git commit -m "feat: add requisition authoring API contract"
```

Expected: Commit created. If `packages/api-client/src/index.ts` or `client.ts` did not change, omit them from `git add`.

---

## Task 10: Frontend API Types And MSW Contract

**Files:**
- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`

- [ ] **Step 1: Extend view model types**

Add to `apps/web/features/requisitions/types/requisition-view-model.ts`:

```ts
export type RequisitionTemplate = {
  id: string;
  name: string;
  description?: string | null;
  category: string;
  defaults: Partial<RequisitionFormValues>;
};

export type RequisitionTemplateMode = "fill-empty" | "replace";

export type RequisitionItemSuggestion = {
  id: string;
  name: string;
  category?: string | null;
  unit: string;
  estimatedUnitPrice: number;
  currency: string;
};

export type RequisitionIntakeOptions = {
  departments: Array<{ name: string }>;
  costCenters: Array<{ code: string; name: string }>;
  currencies: string[];
  units: string[];
};
```

Add `lockVersion` to `Requisition`:

```ts
lockVersion: number;
```

Add `lockVersion` to `RequisitionFormValues` only if the save API uses the form value directly. Prefer passing it separately from the current `Requisition` object.

- [ ] **Step 2: Extend API wrapper**

In `apps/web/features/requisitions/api/requisitions-api.ts`, import generated endpoints:

```ts
import {
  applyRequisitionTemplate as applyRequisitionTemplateEndpoint,
  getRequisitionIntakeOptions as getRequisitionIntakeOptionsEndpoint,
  listRequisitionLineItemSuggestions as listRequisitionLineItemSuggestionsEndpoint,
  listRequisitionTemplates as listRequisitionTemplatesEndpoint,
} from "@cognify/api-client/endpoints";
```

Update `updateRequisitionDraft` signature:

```ts
export async function updateRequisitionDraft(requisitionId: string, values: RequisitionFormValues, lockVersion: number) {
  const response = await updateRequisition(requisitionId, { ...values, lockVersion }, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as Requisition;
}
```

Add wrappers:

```ts
export async function listRequisitionTemplates() {
  const response = await listRequisitionTemplatesEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as RequisitionTemplate[];
}

export async function applyRequisitionTemplate(requisitionId: string, templateId: string, mode: RequisitionTemplateMode, lockVersion: number) {
  const response = await applyRequisitionTemplateEndpoint(
    requisitionId,
    { templateId, mode, lockVersion },
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return response.data.data as Requisition;
}

export async function listRequisitionLineItemSuggestions(query: { search?: string; category?: string; currency?: string }) {
  const response = await listRequisitionLineItemSuggestionsEndpoint(query, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as RequisitionItemSuggestion[];
}

export async function getRequisitionIntakeOptions() {
  const response = await getRequisitionIntakeOptionsEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as RequisitionIntakeOptions;
}
```

- [ ] **Step 3: Update fixtures**

In each requisition fixture, add:

```ts
lockVersion: 0,
```

Add exports:

```ts
export const requisitionTemplateFixtures: RequisitionTemplate[] = [
  {
    id: "template-it-equipment",
    name: "IT equipment",
    description: "Laptop and accessory purchases.",
    category: "it_equipment",
    defaults: {
      department: "IT",
      costCenter: "IT-210",
      businessJustification: "Provision or replace equipment required for business operations.",
      lineItems: [{ name: "Laptop", quantity: 1, unit: "each", estimatedUnitPrice: 1800, currency: "MYR" }],
    },
  },
];

export const requisitionItemSuggestionFixtures: RequisitionItemSuggestion[] = [
  { id: "suggestion-laptop", name: "Laptop", category: "it_equipment", unit: "each", estimatedUnitPrice: 1800, currency: "MYR" },
  { id: "suggestion-monitor", name: "Monitor", category: "it_equipment", unit: "each", estimatedUnitPrice: 700, currency: "MYR" },
];

export const requisitionIntakeOptionsFixture: RequisitionIntakeOptions = {
  departments: [{ name: "Procurement" }, { name: "IT" }, { name: "Operations" }],
  costCenters: [{ code: "OPS-110", name: "Operations" }, { code: "IT-210", name: "Information Technology" }],
  currencies: ["MYR", "USD"],
  units: ["each", "bundle", "month", "hour", "day"],
};
```

- [ ] **Step 4: Update MSW handlers**

In `requisitions-handlers.ts`, add `lockVersion` handling on patch:

```ts
const requestLockVersion = "lockVersion" in values ? Number(values.lockVersion) : undefined;
if (requestLockVersion === undefined) {
  return HttpResponse.json({ error: { code: "validation_failed", message: "Validation failed" } }, { status: 422 });
}
if (requestLockVersion !== existing.lockVersion) {
  return HttpResponse.json({ error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } }, { status: 409 });
}
```

When building updated requisitions, increment:

```ts
lockVersion: (existing.lockVersion ?? 0) + 1,
```

Add handlers:

```ts
http.get("/api/requisition-templates", () => HttpResponse.json({ data: requisitionTemplateFixtures })),
http.get("/api/requisition-intake-options", () => HttpResponse.json({ data: requisitionIntakeOptionsFixture })),
http.get("/api/requisition-line-item-suggestions", ({ request }) => {
  const search = new URL(request.url).searchParams.get("search")?.toLowerCase() ?? "";
  return HttpResponse.json({
    data: requisitionItemSuggestionFixtures.filter((item) => item.name.toLowerCase().includes(search)),
  });
}),
http.post("/api/requisitions/:requisitionId/apply-template", async ({ params, request }) => {
  const body = (await request.json()) as { templateId: string; mode: "fill-empty" | "replace"; lockVersion: number };
  const existing = requisitions.find((item) => item.id === params.requisitionId);
  const template = requisitionTemplateFixtures.find((item) => item.id === body.templateId);
  if (!existing || !template) return HttpResponse.json({ error: { code: "not_found", message: "Not found" } }, { status: 404 });
  if (existing.status !== "draft") return HttpResponse.json({ error: { code: "invalid_state", message: "Only draft requisitions can receive templates." } }, { status: 409 });
  if (body.lockVersion !== existing.lockVersion) return HttpResponse.json({ error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } }, { status: 409 });
  const mergedValues = body.mode === "replace" ? { ...toFormValues(existing), ...template.defaults } : fillEmptyValues(toFormValues(existing), template.defaults);
  const updated = { ...buildRequisition(mergedValues, existing.id, "draft", existing.number, existing.createdAt), lockVersion: existing.lockVersion + 1 };
  requisitions = requisitions.map((item) => (item.id === existing.id ? updated : item));
  return HttpResponse.json({ data: updated });
}),
```

Add helper functions in the same file:

```ts
function toFormValues(requisition: Requisition): RequisitionFormValues {
  return {
    title: requisition.title,
    businessJustification: requisition.businessJustification,
    neededByDate: requisition.neededByDate,
    department: requisition.department ?? "",
    costCenter: requisition.costCenter ?? "",
    deliveryLocation: requisition.deliveryLocation ?? "",
    currency: requisition.currency,
    lineItems: requisition.lineItems,
  };
}

function fillEmptyValues(current: RequisitionFormValues, incoming: Partial<RequisitionFormValues>): RequisitionFormValues {
  return {
    ...current,
    businessJustification: current.businessJustification || incoming.businessJustification || "",
    department: current.department || incoming.department || "",
    costCenter: current.costCenter || incoming.costCenter || "",
    deliveryLocation: current.deliveryLocation || incoming.deliveryLocation || "",
    currency: current.currency || incoming.currency || "MYR",
    lineItems: current.lineItems.some((item) => item.name.trim()) ? current.lineItems : incoming.lineItems ?? current.lineItems,
  };
}
```

- [ ] **Step 5: Run frontend typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS or generated endpoint signature mismatches are fixed in the wrapper.

- [ ] **Step 6: Commit API/MSW updates**

Run:

```bash
git add apps/web/features/requisitions/types apps/web/features/requisitions/api apps/web/features/requisitions/mocks
git commit -m "feat: wire requisition authoring frontend contracts"
```

Expected: Commit created.

---

## Task 11: Frontend Save Controller Tests

**Files:**
- Create: `apps/web/features/requisitions/tests/requisition-draft-save-controller.test.tsx`

- [ ] **Step 1: Write failing tests for save controller behavior**

Create `apps/web/features/requisitions/tests/requisition-draft-save-controller.test.tsx`:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { act, renderHook, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { useRequisitionDraftSaveController } from "../hooks/use-requisition-draft-save-controller";
import type { RequisitionFormValues } from "../types/requisition-view-model";

const baseValues: RequisitionFormValues = {
  title: "Laptop refresh",
  businessJustification: "Replace unsupported laptops.",
  neededByDate: "2026-06-15",
  department: "IT",
  costCenter: "IT-210",
  deliveryLocation: "Kuala Lumpur",
  currency: "MYR",
  lineItems: [{ name: "Laptop", quantity: 1, unit: "each", estimatedUnitPrice: 1800, currency: "MYR" }],
};

function wrapper({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("useRequisitionDraftSaveController", () => {
  it("creates the first draft with manual save and tracks returned lock version", async () => {
    const createDraft = vi.fn().mockResolvedValue({ id: "req-1", lockVersion: 0, status: "draft" });
    const updateDraft = vi.fn();
    const { result } = renderHook(
      () => useRequisitionDraftSaveController({ initialRequisition: undefined, createDraft, updateDraft, autosaveDelayMs: 10 }),
      { wrapper },
    );

    await act(async () => {
      await result.current.saveNow(baseValues);
    });

    expect(createDraft).toHaveBeenCalledWith(baseValues);
    expect(updateDraft).not.toHaveBeenCalled();
    expect(result.current.requisitionId).toBe("req-1");
    expect(result.current.lockVersion).toBe(0);
    expect(result.current.saveState).toBe("saved");
  });

  it("autosaves existing drafts with the latest lock version", async () => {
    const createDraft = vi.fn();
    const updateDraft = vi.fn().mockResolvedValue({ id: "req-1", lockVersion: 2, status: "draft" });
    const { result } = renderHook(
      () => useRequisitionDraftSaveController({ initialRequisition: { id: "req-1", lockVersion: 1 }, createDraft, updateDraft, autosaveDelayMs: 10 }),
      { wrapper },
    );

    act(() => {
      result.current.scheduleAutosave(baseValues);
    });

    await waitFor(() => expect(updateDraft).toHaveBeenCalledWith("req-1", baseValues, 1));
    expect(result.current.lockVersion).toBe(2);
    expect(result.current.saveState).toBe("saved");
  });

  it("exposes conflict state without clearing local values", async () => {
    const conflict = { error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } };
    const updateDraft = vi.fn().mockRejectedValue(conflict);
    const { result } = renderHook(
      () => useRequisitionDraftSaveController({ initialRequisition: { id: "req-1", lockVersion: 1 }, createDraft: vi.fn(), updateDraft, autosaveDelayMs: 10 }),
      { wrapper },
    );

    await act(async () => {
      await result.current.saveNow(baseValues);
    });

    expect(result.current.saveState).toBe("conflict");
    expect(result.current.lastFailedValues).toEqual(baseValues);
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
pnpm --filter @cognify/web test -- requisition-draft-save-controller.test.tsx
```

Expected: FAIL because the hook does not exist.

- [ ] **Step 3: Commit failing tests**

Run:

```bash
git add apps/web/features/requisitions/tests/requisition-draft-save-controller.test.tsx
git commit -m "test: cover requisition draft save controller"
```

Expected: Commit created.

---

## Task 12: Frontend Save Controller Implementation

**Files:**
- Create: `apps/web/features/requisitions/hooks/use-requisition-draft-save-controller.ts`
- Modify: `apps/web/features/requisitions/hooks/use-save-requisition-draft.ts`

- [ ] **Step 1: Create save controller hook**

Create `apps/web/features/requisitions/hooks/use-requisition-draft-save-controller.ts`:

```ts
"use client";

import { useCallback, useRef, useState } from "react";
import type { Requisition, RequisitionFormValues } from "../types/requisition-view-model";

type SaveState = "idle" | "unsaved" | "saving" | "saved" | "failed" | "conflict";

type MinimalRequisition = Pick<Requisition, "id" | "lockVersion">;

type SaveControllerOptions = {
  initialRequisition?: MinimalRequisition;
  createDraft: (values: RequisitionFormValues) => Promise<MinimalRequisition>;
  updateDraft: (requisitionId: string, values: RequisitionFormValues, lockVersion: number) => Promise<MinimalRequisition>;
  autosaveDelayMs?: number;
};

function isConflict(error: unknown) {
  if (typeof error !== "object" || error === null) return false;
  const candidate = error as { error?: { code?: string }; code?: string };
  return candidate.error?.code === "draft_conflict" || candidate.code === "draft_conflict";
}

export function useRequisitionDraftSaveController({
  initialRequisition,
  createDraft,
  updateDraft,
  autosaveDelayMs = 1200,
}: SaveControllerOptions) {
  const [requisitionId, setRequisitionId] = useState(initialRequisition?.id);
  const [lockVersion, setLockVersion] = useState(initialRequisition?.lockVersion ?? 0);
  const [saveState, setSaveState] = useState<SaveState>("idle");
  const [lastFailedValues, setLastFailedValues] = useState<RequisitionFormValues | null>(null);
  const timerRef = useRef<ReturnType<typeof window.setTimeout> | null>(null);
  const idRef = useRef(initialRequisition?.id);
  const versionRef = useRef(initialRequisition?.lockVersion ?? 0);

  const applySaved = useCallback((requisition: MinimalRequisition) => {
    idRef.current = requisition.id;
    versionRef.current = requisition.lockVersion;
    setRequisitionId(requisition.id);
    setLockVersion(requisition.lockVersion);
    setSaveState("saved");
    setLastFailedValues(null);
  }, []);

  const saveNow = useCallback(async (values: RequisitionFormValues) => {
    if (timerRef.current) {
      window.clearTimeout(timerRef.current);
      timerRef.current = null;
    }

    setSaveState("saving");
    try {
      const saved = idRef.current
        ? await updateDraft(idRef.current, values, versionRef.current)
        : await createDraft(values);
      applySaved(saved);
      return saved;
    } catch (error) {
      setLastFailedValues(values);
      setSaveState(isConflict(error) ? "conflict" : "failed");
      return undefined;
    }
  }, [applySaved, createDraft, updateDraft]);

  const scheduleAutosave = useCallback((values: RequisitionFormValues) => {
    setSaveState("unsaved");
    if (!idRef.current) return;
    if (timerRef.current) window.clearTimeout(timerRef.current);
    timerRef.current = window.setTimeout(() => {
      void saveNow(values);
    }, autosaveDelayMs);
  }, [autosaveDelayMs, saveNow]);

  return {
    requisitionId,
    lockVersion,
    saveState,
    lastFailedValues,
    saveNow,
    scheduleAutosave,
  };
}
```

- [ ] **Step 2: Keep old hook as thin mutation wrapper**

Update `apps/web/features/requisitions/hooks/use-save-requisition-draft.ts` so mutation accepts lock version:

```ts
mutationFn: ({ requisitionId, values, lockVersion }: { requisitionId?: string; values: RequisitionFormValues; lockVersion?: number }) =>
  requisitionId ? updateRequisitionDraft(requisitionId, values, lockVersion ?? 0) : createRequisitionDraft(values),
```

- [ ] **Step 3: Run save controller tests**

Run:

```bash
pnpm --filter @cognify/web test -- requisition-draft-save-controller.test.tsx
```

Expected: PASS.

- [ ] **Step 4: Commit hook implementation**

Run:

```bash
git add apps/web/features/requisitions/hooks apps/web/features/requisitions/tests/requisition-draft-save-controller.test.tsx
git commit -m "feat: add requisition draft save controller"
```

Expected: Commit created.

---

## Task 13: Frontend Authoring Hooks And Components

**Files:**
- Create: `apps/web/features/requisitions/hooks/use-requisition-intake-options.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-templates.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-line-item-suggestions.ts`
- Create: `apps/web/features/requisitions/components/requisition-template-picker.tsx`
- Create: `apps/web/features/requisitions/components/requisition-line-item-suggestion-combobox.tsx`
- Create: `apps/web/features/requisitions/components/requisition-save-conflict-panel.tsx`

- [ ] **Step 1: Create query hooks**

Create `use-requisition-intake-options.ts`:

```ts
"use client";

import { useQuery } from "@tanstack/react-query";
import { getRequisitionIntakeOptions } from "../api/requisitions-api";

export function useRequisitionIntakeOptions() {
  return useQuery({ queryKey: ["requisition-intake-options"], queryFn: getRequisitionIntakeOptions });
}
```

Create `use-requisition-templates.ts`:

```ts
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { applyRequisitionTemplate, listRequisitionTemplates } from "../api/requisitions-api";
import type { RequisitionTemplateMode } from "../types/requisition-view-model";

export function useRequisitionTemplates() {
  return useQuery({ queryKey: ["requisition-templates"], queryFn: listRequisitionTemplates });
}

export function useApplyRequisitionTemplate() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ requisitionId, templateId, mode, lockVersion }: { requisitionId: string; templateId: string; mode: RequisitionTemplateMode; lockVersion: number }) =>
      applyRequisitionTemplate(requisitionId, templateId, mode, lockVersion),
    onSuccess: async (requisition) => {
      await queryClient.invalidateQueries({ queryKey: ["requisitions"] });
      queryClient.setQueryData(["requisition", requisition.id], requisition);
    },
  });
}
```

Create `use-requisition-line-item-suggestions.ts`:

```ts
"use client";

import { useQuery } from "@tanstack/react-query";
import { listRequisitionLineItemSuggestions } from "../api/requisitions-api";

export function useRequisitionLineItemSuggestions(search: string, currency: string) {
  return useQuery({
    queryKey: ["requisition-line-item-suggestions", search, currency],
    queryFn: () => listRequisitionLineItemSuggestions({ search, currency }),
    enabled: search.trim().length >= 2,
  });
}
```

- [ ] **Step 2: Create template picker**

Create `requisition-template-picker.tsx`:

```tsx
"use client";

import type { RequisitionTemplate, RequisitionTemplateMode } from "../types/requisition-view-model";

export function RequisitionTemplatePicker({
  templates,
  disabled,
  onApply,
}: {
  templates: RequisitionTemplate[];
  disabled?: boolean;
  onApply: (template: RequisitionTemplate, mode: RequisitionTemplateMode) => void;
}) {
  return (
    <section className="space-y-3 rounded-md border p-4">
      <h2 className="text-base font-semibold">Start from a template</h2>
      <div className="grid gap-2">
        {templates.map((template) => (
          <div key={template.id} className="rounded-md border p-3">
            <p className="font-medium">{template.name}</p>
            {template.description ? <p className="mt-1 text-sm text-muted-foreground">{template.description}</p> : null}
            <div className="mt-3 flex flex-wrap gap-2">
              <button type="button" className="min-h-10 rounded-md border px-3 text-sm font-medium" disabled={disabled} onClick={() => onApply(template, "fill-empty")}>
                Fill empty fields
              </button>
              <button type="button" className="min-h-10 rounded-md border px-3 text-sm font-medium" disabled={disabled} onClick={() => onApply(template, "replace")}>
                Replace draft fields
              </button>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
```

- [ ] **Step 3: Create suggestion selector**

Create `requisition-line-item-suggestion-combobox.tsx`:

```tsx
"use client";

import { useRequisitionLineItemSuggestions } from "../hooks/use-requisition-line-item-suggestions";
import type { RequisitionItemSuggestion } from "../types/requisition-view-model";

export function RequisitionLineItemSuggestionCombobox({
  search,
  currency,
  onSelect,
}: {
  search: string;
  currency: string;
  onSelect: (suggestion: RequisitionItemSuggestion) => void;
}) {
  const suggestions = useRequisitionLineItemSuggestions(search, currency);

  if (search.trim().length < 2 || suggestions.isError || !suggestions.data?.length) return null;

  return (
    <div className="rounded-md border bg-background p-2" aria-label="Line item suggestions">
      {suggestions.data.map((suggestion) => (
        <button key={suggestion.id} type="button" className="block w-full rounded px-2 py-2 text-left text-sm hover:bg-muted" onClick={() => onSelect(suggestion)}>
          <span className="font-medium">{suggestion.name}</span>
          <span className="ml-2 text-muted-foreground">{suggestion.unit} · {suggestion.currency} {suggestion.estimatedUnitPrice}</span>
        </button>
      ))}
    </div>
  );
}
```

- [ ] **Step 4: Create conflict panel**

Create `requisition-save-conflict-panel.tsx`:

```tsx
"use client";

export function RequisitionSaveConflictPanel({ onReload }: { onReload: () => void }) {
  return (
    <div role="alert" className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
      <p className="font-semibold">This draft changed elsewhere.</p>
      <p className="mt-1">Your local edits are still on screen. Reload the latest server copy before deciding what to reapply.</p>
      <button type="button" className="mt-3 min-h-10 rounded-md border border-amber-400 px-3 font-medium" onClick={onReload}>
        Reload latest draft
      </button>
    </div>
  );
}
```

- [ ] **Step 5: Run typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

- [ ] **Step 6: Commit hooks/components**

Run:

```bash
git add apps/web/features/requisitions/hooks apps/web/features/requisitions/components
git commit -m "feat: add requisition authoring UI helpers"
```

Expected: Commit created.

---

## Task 14: Integrate Authoring UI Into Requisition Form

**Files:**
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx`
- Modify: `apps/web/features/requisitions/schemas/requisition-form-schema.ts`
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Update draft schema to allow zero estimated unit price for drafts**

In `requisition-form-schema.ts`, keep submit schema strict if desired, but ensure draft schema uses:

```ts
estimatedUnitPrice: z.coerce.number().min(0).default(0),
```

- [ ] **Step 2: Add workflow tests for templates and suggestions**

Append to `requisitions-workflow.test.tsx`:

```tsx
it("applies a template and selected line item suggestion before saving", async () => {
  const user = userEvent.setup();
  renderWithQuery(<RequisitionCreatePage />);

  await user.type(screen.getByLabelText("Title"), "New laptop request");
  await user.click(await screen.findByRole("button", { name: "Fill empty fields" }));
  expect(await screen.findByDisplayValue("Provision or replace equipment required for business operations.")).toBeInTheDocument();

  await user.clear(screen.getByLabelText("Item name 1"));
  await user.type(screen.getByLabelText("Item name 1"), "Lap");
  await user.click(await screen.findByRole("button", { name: /Laptop/ }));
  expect(screen.getByLabelText("Unit 1")).toHaveValue("each");
  expect(screen.getByLabelText("Estimated unit price 1")).toHaveValue(1800);

  await user.click(screen.getByRole("button", { name: "Save draft" }));
  expect(await screen.findByText("Saved")).toBeInTheDocument();
});

it("shows conflict recovery when a stale save is rejected", async () => {
  const user = userEvent.setup();
  server.use(
    http.patch("/api/requisitions/req-1", () => HttpResponse.json({ error: { code: "draft_conflict", message: "The draft has changed since it was loaded." } }, { status: 409 })),
  );

  renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);
  await user.click(await screen.findByRole("link", { name: "Edit draft" }));
  renderWithQuery(<RequisitionCreatePage />);
  await user.type(screen.getByLabelText("Title"), " changed");
  await user.click(screen.getByRole("button", { name: "Save draft" }));

  expect(await screen.findByRole("alert")).toHaveTextContent("This draft changed elsewhere.");
});
```

Adjust the second test to render the actual edit page/workflow if one exists. Do not assert conflict only through a toast; assert an accessible recovery surface.

- [ ] **Step 3: Wire form to hooks/components**

In `requisition-form.tsx`:

- import `RequisitionTemplatePicker`, `RequisitionLineItemSuggestionCombobox`, `RequisitionSaveConflictPanel`.
- import `useRequisitionTemplates`, `useApplyRequisitionTemplate`, `useRequisitionIntakeOptions`.
- use `useRequisitionDraftSaveController` with `createRequisitionDraft` and `updateRequisitionDraft` wrapper functions.
- replace direct `useSaveRequisitionDraft` mutation state with controller `saveState`, `saveNow`, and `scheduleAutosave`.
- call `scheduleAutosave(nextValues)` inside field update handlers only after server ID exists.
- render conflict panel when `saveState === "conflict"`.
- render template picker near the top of the form.
- render suggestion combobox under each line item name input.
- use intake options to render department/cost center selects when options load; fallback to current text inputs if the query errors.

Use this value update shape:

```ts
function setNextValues(updater: (current: RequisitionFormValues) => RequisitionFormValues) {
  setValues((current) => {
    const next = updater(current);
    saveController.scheduleAutosave(next);
    return next;
  });
}
```

Use this suggestion selection shape:

```ts
function applySuggestion(index: number, suggestion: RequisitionItemSuggestion) {
  setNextValues((current) => ({
    ...current,
    lineItems: current.lineItems.map((item, itemIndex) =>
      itemIndex === index
        ? { ...item, name: suggestion.name, unit: suggestion.unit, estimatedUnitPrice: suggestion.estimatedUnitPrice, currency: suggestion.currency }
        : item,
    ),
  }));
}
```

Use this template apply shape for existing drafts:

```ts
async function handleApplyTemplate(template: RequisitionTemplate, mode: RequisitionTemplateMode) {
  if (!saveController.requisitionId) {
    setValues((current) => mergeTemplateDefaults(current, template.defaults, mode));
    return;
  }

  const updated = await applyTemplate.mutateAsync({
    requisitionId: saveController.requisitionId,
    templateId: template.id,
    mode,
    lockVersion: saveController.lockVersion,
  });
  setValues(toFormValues(updated));
}
```

Define local helpers `mergeTemplateDefaults()` and `toFormValues()` in the form file or a small `utils` file if the form becomes too large.

- [ ] **Step 4: Run focused frontend tests**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-draft-save-controller.test.tsx
```

Expected: PASS.

- [ ] **Step 5: Commit form integration**

Run:

```bash
git add apps/web/features/requisitions/forms apps/web/features/requisitions/schemas apps/web/features/requisitions/tests
git commit -m "feat: integrate requisition authoring workflow"
```

Expected: Commit created.

---

## Task 15: Playwright Critical-Path E2E Tests

**Files:**
- Create: `apps/web/tests/e2e/requisition-authoring.spec.ts`
- Modify: `apps/web/playwright.config.ts` only if web server startup is missing and needed.

- [ ] **Step 1: Add E2E tests for critical authoring path**

Create `apps/web/tests/e2e/requisition-authoring.spec.ts`:

```ts
import { expect, test } from "@playwright/test";

test.describe("requisition authoring", () => {
  test("creates, saves, reopens, and continues a draft", async ({ page }) => {
    await page.goto("/requisitions/new");
    await page.getByLabel("Title").fill("E2E laptop refresh");
    await page.getByLabel("Business justification").fill("Replace unsupported field laptops.");
    await page.getByLabel("Needed by").fill("2026-06-15");
    await page.getByLabel("Item name 1").fill("Laptop");
    await page.getByLabel("Quantity 1").fill("2");
    await page.getByLabel("Estimated unit price 1").fill("1800");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Saved")).toBeVisible();

    await page.goto("/requisitions");
    await page.getByText("E2E laptop refresh").click();
    await page.getByRole("link", { name: "Edit draft" }).click();
    await expect(page.getByLabel("Title")).toHaveValue("E2E laptop refresh");
    await page.getByLabel("Business justification").fill("Replace unsupported field laptops before warranty expiry.");
    await expect(page.getByText("Saved")).toBeVisible({ timeout: 5000 });
    await page.reload();
    await expect(page.getByLabel("Business justification")).toHaveValue("Replace unsupported field laptops before warranty expiry.");
  });

  test("applies template and uses a line item suggestion", async ({ page }) => {
    await page.goto("/requisitions/new");
    await page.getByLabel("Title").fill("E2E template request");
    await page.getByRole("button", { name: "Fill empty fields" }).first().click();
    await expect(page.getByLabel("Business justification")).not.toHaveValue("");
    await page.getByLabel("Item name 1").fill("Lap");
    await page.getByRole("button", { name: /Laptop/ }).click();
    await expect(page.getByLabel("Unit 1")).toHaveValue("each");
    await expect(page.getByLabel("Estimated unit price 1")).toHaveValue("1800");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Saved")).toBeVisible();
  });

  test("saves incomplete drafts without adding submission scope", async ({ page }) => {
    await page.goto("/requisitions/new");
    await page.getByLabel("Title").fill("E2E incomplete draft");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Saved")).toBeVisible();
    await page.getByRole("button", { name: "Submit" }).click();
    await expect(page.getByRole("alert")).toContainText("Complete the highlighted fields before submitting.");
  });
});
```

If the E2E app requires authentication seed/login, add a setup helper in the same file that logs in through the existing login page using seeded demo credentials. Do not bypass server validation by writing directly to browser storage unless the existing E2E convention already does that.

- [ ] **Step 2: Add web server config if absent**

If `pnpm --filter @cognify/web test:e2e` cannot reach a running app, update `apps/web/playwright.config.ts` with:

```ts
webServer: {
  command: "pnpm dev",
  url: "http://127.0.0.1:3000",
  reuseExistingServer: !process.env.CI,
  timeout: 120_000,
},
```

Only add this if local E2E convention expects Playwright to start Next.js itself.

- [ ] **Step 3: Run E2E tests**

Run:

```bash
pnpm --filter @cognify/web test:e2e -- requisition-authoring.spec.ts
```

Expected: PASS. If auth or backend service startup blocks E2E, document the exact blocker and add equivalent Vitest/MSW + API tests for the blocked assertion before claiming completion.

- [ ] **Step 4: Commit E2E tests**

Run:

```bash
git add apps/web/tests/e2e apps/web/playwright.config.ts
git commit -m "test: add requisition authoring e2e coverage"
```

Expected: Commit created. If Playwright config did not change, omit it.

---

## Task 16: Final Verification And Documentation Check

**Files:**
- Modify only if needed: `docs/superpowers/specs/2026-05-15-requisition-authoring-intake-foundation-design.md`
- Modify only if needed: `docs/superpowers/plans/2026-05-15-requisition-authoring-intake-foundation.md`

- [ ] **Step 1: Run backend focused checks**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionApiTest|RequisitionAuthoringApiTest'
cd apps/api && php artisan route:list --path=api
```

Expected: Tests PASS and routes include requisition authoring endpoints.

- [ ] **Step 2: Run frontend focused checks**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-draft-save-controller.test.tsx
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: PASS.

- [ ] **Step 3: Run contract checks**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS.

- [ ] **Step 4: Run E2E checks**

Run:

```bash
pnpm --filter @cognify/web test:e2e -- requisition-authoring.spec.ts
```

Expected: PASS or documented environment blocker with equivalent coverage already passing.

- [ ] **Step 5: Inspect git diff for boundary violations**

Run:

```bash
git diff --stat origin/main...HEAD
git diff --name-only origin/main...HEAD
```

Expected: Cognify-specific UI is under `apps/web`, Laravel business behavior is under `apps/api/Domains/Requisition`, generated client changes are under `packages/api-client`, and no requisition-specific business meaning appears in `packages/ui`.

- [ ] **Step 6: Final commit for any verification-only doc corrections**

Run only if docs were updated:

```bash
git add docs/superpowers/specs/2026-05-15-requisition-authoring-intake-foundation-design.md docs/superpowers/plans/2026-05-15-requisition-authoring-intake-foundation.md
git commit -m "docs: refine requisition authoring implementation notes"
```

Expected: Commit created only if documentation changed.

---

## Self-Review Notes

Spec coverage:

- Draft creation/editing: Tasks 2, 3, 10, 12, 14, 15.
- Autosave/manual save: Tasks 11, 12, 14, 15.
- Conflict handling: Tasks 2, 3, 10, 11, 12, 14, 15.
- Templates: Tasks 4, 5, 6, 8, 9, 10, 13, 14, 15.
- Suggestions: Tasks 4, 5, 6, 8, 9, 10, 13, 14, 15.
- Department/cost center validation: Tasks 4, 5, 7, 8, 15.
- API contract/generated client: Task 9.
- Critical-path E2E: Task 15.
- Boundary compliance and final verification: Task 16.

Plan scan:

- Red-flag planning language was checked and removed where it appeared outside executable code examples.
- E2E auth setup is conditional because the active harness has no current E2E specs. The task requires either implementing the existing convention or documenting a blocker plus equivalent coverage before completion.

Type consistency:

- API field is `lockVersion` in JSON and TypeScript.
- Database field is `lock_version` in Laravel.
- Template mode is `fill-empty | replace` in OpenAPI, PHP validation, and TypeScript.
- Conflict code is `draft_conflict`.

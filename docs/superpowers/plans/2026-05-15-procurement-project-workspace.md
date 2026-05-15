# Procurement Project Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the P1 Procurement Project Workspace so buyers can create tenant-scoped projects, link requisitions, and manage a project workspace with budget, pipeline, ownership, and activity context.

**Architecture:** Implement this as two vertical slices from `docs/superpowers/specs/2026-05-15-procurement-project-workspace-design.md`: project record foundation, then project workspace. Backend project workflow lives in `apps/api/Domains/Project`; requisition lifecycle remains in `apps/api/Domains/Requisition`; frontend project workflows live in `apps/web/features/projects`; OpenAPI and `@cognify/api-client` remain the contract boundary.

**Tech Stack:** Laravel 12, Sanctum, Eloquent, PHPUnit/Pest-compatible Laravel feature tests, Next.js App Router, React 19, TanStack Query, MSW, Vitest, shadcn/Radix primitives exported through `packages/ui`, Orval-generated `@cognify/api-client`.

---

## Source Documents

- Design spec: `docs/superpowers/specs/2026-05-15-procurement-project-workspace-design.md`
- P1 epic plan: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Architecture: `ARCHITECTURE.md`
- Feature runbook: `docs/05-runbooks/feature-development.md`
- Agent guidance: `AGENTS.md`

## Execution Guardrails

- The current branch already contains uncommitted requisition/collaboration work. Do not revert it. Before editing, run `git status --short --branch` and work with the current tree.
- Preserve existing P1 requisition behavior. Project work can touch requisition create/update/resource/form files only where project selection or project summary is required.
- UI work must prioritize shadcn/Radix primitive exports from `packages/ui` (`Button`, `Badge`, `NativeSelect`, `Textarea`) and existing app primitives (`DataTable`, form helpers, `RecordWorkspaceLayout`, activity timeline) before adding custom components.
- Do not add project comments, approval tasks, RFQs, quotations, award actions, risk scoring, PO handoff, portfolios, milestones, Gantt views, or budget enforcement.
- Every API contract change must be reflected in `apps/api/storage/openapi/openapi.json`, followed by `pnpm generate:api` and `pnpm check:api-contract`.
- Keep controllers thin. Domain rules go in actions, policies, status enums, and request classes.

## File Map

### Backend Project Domain

- Create: `apps/api/Domains/Project/States/ProjectStatus.php`  
  Owns project lifecycle values and terminal-state helpers.
- Create: `apps/api/Domains/Project/Services/ProcurementProjectNumberGenerator.php`  
  Generates tenant-scoped project numbers such as `PRJ-2026-000001`.
- Create: `apps/api/Domains/Project/Actions/CreateProcurementProject.php`  
  Creates projects, validates tenant ownership, assigns number, writes audit.
- Create: `apps/api/Domains/Project/Actions/UpdateProcurementProject.php`  
  Updates non-terminal project metadata, writes audit.
- Create: `apps/api/Domains/Project/Actions/TransitionProcurementProjectStatus.php`  
  Handles activate, hold, resume, complete, cancel transitions and audit.
- Create: `apps/api/Domains/Project/Actions/LinkRequisitionToProject.php`  
  Links visible same-tenant requisitions to projects.
- Create: `apps/api/Domains/Project/Actions/UnlinkRequisitionFromProject.php`  
  Removes a requisition project link when policy and state allow it.
- Create: `apps/api/Domains/Project/Policies/ProcurementProjectPolicy.php`  
  Enforces view, create, update, status, and link permissions.
- Create: `apps/api/Domains/Project/Http/Controllers/ProcurementProjectController.php`  
  Exposes list, create, show, update, and status actions.
- Create: `apps/api/Domains/Project/Http/Controllers/ProjectRequisitionController.php`  
  Exposes linked requisition list, link, and unlink actions.
- Create: `apps/api/Domains/Project/Http/Controllers/ProjectActivityController.php`  
  Lists project audit events.
- Create: `apps/api/Domains/Project/Http/Requests/StoreProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Requests/UpdateProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Requests/TransitionProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Requests/LinkProjectRequisitionRequest.php`
- Create: `apps/api/Domains/Project/Http/Resources/ProcurementProjectResource.php`
- Create: `apps/api/Domains/Project/Http/Resources/ProjectRequisitionResource.php`
- Modify: `apps/api/Domains/Project/Models/ProcurementProject.php`  
  Add casts, relationships, helpers, and fillable fields.
- Create: `apps/api/database/migrations/2026_05_15_050000_expand_procurement_projects_for_workspace.php`  
  Adds durable project columns.
- Modify: `apps/api/routes/api.php`  
  Registers project endpoints inside `auth:sanctum` and `ResolveCurrentTenant`.
- Test: `apps/api/tests/Feature/ProcurementProjectApiTest.php`

### Backend Requisition/Search/Seed Integration

- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`  
  Add `project()` relationship.
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`  
  Add `projectSummary`.
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`  
  Eager-load `project.owner`.
- Modify: `apps/api/Domains/Requisition/Actions/CreateRequisitionDraft.php` and `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`  
  Validate same-tenant project IDs before save.
- Modify: `apps/api/app/Http/Requests/Requisition/CreateRequisitionRequest.php` and `apps/api/app/Http/Requests/Requisition/UpdateRequisitionRequest.php`  
  Keep `projectId` contract and add validation messages if needed.
- Modify: `apps/api/Domains/Search/Providers/ProcurementProjectSearchProvider.php`  
  Link to `/projects/{id}` and include durable project fields.
- Modify: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`  
  Seed real project fields.
- Test: existing `apps/api/tests/Feature/RequisitionApiTest.php`
- Test: existing search feature test if present; otherwise extend `ProcurementProjectApiTest.php` with search coverage.

### API Contract

- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/**`
- Modify if generated exports require it: `packages/api-client/src/index.ts`, `packages/api-client/src/client.ts`

### Frontend Project Feature

- Create: `apps/web/features/projects/api/projects-api.ts`
- Create: `apps/web/features/projects/types/project-view-model.ts`
- Create: `apps/web/features/projects/schemas/project-form-schema.ts`
- Create: `apps/web/features/projects/hooks/use-projects.ts`
- Create: `apps/web/features/projects/hooks/use-project.ts`
- Create: `apps/web/features/projects/hooks/use-project-actions.ts`
- Create: `apps/web/features/projects/hooks/use-project-requisitions.ts`
- Create: `apps/web/features/projects/components/project-status-badge.tsx`
- Create: `apps/web/features/projects/components/project-action-dialog.tsx`
- Create: `apps/web/features/projects/components/project-budget-summary.tsx`
- Create: `apps/web/features/projects/components/project-requisition-pipeline.tsx`
- Create: `apps/web/features/projects/components/project-activity-timeline.tsx`
- Create: `apps/web/features/projects/forms/project-form.tsx`
- Create: `apps/web/features/projects/tables/projects-table.tsx`
- Create: `apps/web/features/projects/workflows/project-list-page.tsx`
- Create: `apps/web/features/projects/workflows/project-create-page.tsx`
- Create: `apps/web/features/projects/workflows/project-detail-page.tsx`
- Create: `apps/web/features/projects/mocks/project-fixtures.ts`
- Create: `apps/web/features/projects/mocks/project-handlers.ts`
- Create: `apps/web/features/projects/tests/project-form-schema.test.ts`
- Create: `apps/web/features/projects/tests/projects-workflow.test.tsx`
- Create: `apps/web/features/projects/tests/project-api-mappers.test.ts`
- Create: `apps/web/app/(workspace)/projects/page.tsx`
- Create: `apps/web/app/(workspace)/projects/new/page.tsx`
- Create: `apps/web/app/(workspace)/projects/[projectId]/page.tsx`

### Frontend Requisition/Search/Shell Integration

- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-list-page.tsx`
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/features/search/hooks/use-recent-records.ts` if project recent records require type widening.
- Modify: `apps/web/tests/msw/handlers.ts` or equivalent MSW aggregation file if feature handlers are centralized.

---

## Task 0: Baseline And Worktree Safety

**Files:**
- Read: `docs/superpowers/specs/2026-05-15-procurement-project-workspace-design.md`
- Read: `ARCHITECTURE.md`
- Read: `docs/05-runbooks/feature-development.md`
- Inspect: `git status --short --branch`

- [ ] **Step 1: Confirm branch state**

Run:

```bash
git status --short --branch
```

Expected: current branch is shown, with existing uncommitted requisition/collaboration changes and the project design spec. Treat those files as user/work-in-progress unless this plan explicitly touches them.

- [ ] **Step 2: Confirm project domain baseline**

Run:

```bash
find apps/api/Domains/Project -maxdepth 4 -type f -print | sort
```

Expected before implementation: `apps/api/Domains/Project/Models/ProcurementProject.php` exists and most project domain folders do not.

- [ ] **Step 3: Confirm frontend primitive availability**

Run:

```bash
find packages/ui/src apps/web/components -maxdepth 3 -type f | sort
```

Expected: `packages/ui` exports `Button`, `Badge`, `NativeSelect`, and `Textarea`; app-level `DataTable`, form helpers, activity timeline, status badge, and record workspace layout exist.

---

## Task 1: Project API Foundation Tests

**Files:**
- Create: `apps/api/tests/Feature/ProcurementProjectApiTest.php`
- Later tasks satisfy these tests.

- [ ] **Step 1: Write failing project API tests**

Create `apps/api/tests/Feature/ProcurementProjectApiTest.php` with this test class:

```php
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
            'auditable_type' => ProcurementProject::class,
            'auditable_id' => $project->id,
            'metadata' => ['name' => $project->name],
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/projects/{$project->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.type', 'project.created');
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
```

- [ ] **Step 2: Run the tests to confirm missing implementation**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
```

Expected: FAIL with errors for missing project routes, missing columns such as `charter`, or missing project resource behavior.

---

## Task 2: Project Data Model, Status, Policy, And Actions

**Files:**
- Create: `apps/api/database/migrations/2026_05_15_050000_expand_procurement_projects_for_workspace.php`
- Modify: `apps/api/Domains/Project/Models/ProcurementProject.php`
- Create: `apps/api/Domains/Project/States/ProjectStatus.php`
- Create: `apps/api/Domains/Project/Services/ProcurementProjectNumberGenerator.php`
- Create: `apps/api/Domains/Project/Policies/ProcurementProjectPolicy.php`
- Create: `apps/api/Domains/Project/Actions/CreateProcurementProject.php`
- Create: `apps/api/Domains/Project/Actions/UpdateProcurementProject.php`
- Create: `apps/api/Domains/Project/Actions/TransitionProcurementProjectStatus.php`

- [ ] **Step 1: Add the additive project migration**

Create the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_projects', function (Blueprint $table): void {
            $table->text('charter')->nullable()->after('name');
            $table->string('department')->nullable()->after('currency');
            $table->string('cost_center')->nullable()->after('department');
            $table->date('target_start_date')->nullable()->after('cost_center');
            $table->date('target_completion_date')->nullable()->after('target_start_date');
            $table->timestamp('cancelled_at')->nullable()->after('target_completion_date');
            $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_id');
            $table->timestamp('completed_at')->nullable()->after('cancellation_reason');
            $table->foreignId('completed_by_id')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'owner_id']);
            $table->index(['tenant_id', 'department']);
            $table->index(['tenant_id', 'cost_center']);
        });
    }

    public function down(): void
    {
        Schema::table('procurement_projects', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'owner_id']);
            $table->dropIndex(['tenant_id', 'department']);
            $table->dropIndex(['tenant_id', 'cost_center']);
            $table->dropConstrainedForeignId('completed_by_id');
            $table->dropColumn('completed_at');
            $table->dropColumn('cancellation_reason');
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropColumn('cancelled_at');
            $table->dropColumn('target_completion_date');
            $table->dropColumn('target_start_date');
            $table->dropColumn('cost_center');
            $table->dropColumn('department');
            $table->dropColumn('charter');
        });
    }
};
```

- [ ] **Step 2: Add `ProjectStatus`**

Create `apps/api/Domains/Project/States/ProjectStatus.php`:

```php
<?php

namespace Domains\Project\States;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Active, self::Cancelled], true),
            self::Active => in_array($next, [self::OnHold, self::Completed, self::Cancelled], true),
            self::OnHold => in_array($next, [self::Active, self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
```

- [ ] **Step 3: Update `ProcurementProject` model**

Modify `apps/api/Domains/Project/Models/ProcurementProject.php` so the fillable, casts, and relationships include:

```php
use Domains\Project\States\ProjectStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Relations\HasMany;

protected $fillable = [
    'tenant_id',
    'owner_id',
    'number',
    'name',
    'charter',
    'status',
    'budget_amount',
    'currency',
    'department',
    'cost_center',
    'target_start_date',
    'target_completion_date',
    'cancelled_at',
    'cancelled_by_id',
    'cancellation_reason',
    'completed_at',
    'completed_by_id',
    'metadata',
];

protected function casts(): array
{
    return [
        'budget_amount' => 'decimal:2',
        'target_start_date' => 'date',
        'target_completion_date' => 'date',
        'cancelled_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => ProjectStatus::class,
        'metadata' => 'array',
    ];
}

/**
 * @return HasMany<Requisition, $this>
 */
public function requisitions(): HasMany
{
    return $this->hasMany(Requisition::class, 'project_id');
}

public function cancelledBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'cancelled_by_id');
}

public function completedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'completed_by_id');
}
```

Keep the existing owner tenant guard. Update it so `owner_id === null` remains valid but a non-null owner must belong to the same tenant.

- [ ] **Step 4: Add the project number generator**

Create `apps/api/Domains/Project/Services/ProcurementProjectNumberGenerator.php`:

```php
<?php

namespace Domains\Project\Services;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Support\Facades\DB;

class ProcurementProjectNumberGenerator
{
    public function nextForTenant(Tenant $tenant): string
    {
        return DB::transaction(function () use ($tenant): string {
            $year = now()->year;
            $prefix = "PRJ-{$year}-";
            $latest = ProcurementProject::query()
                ->where('tenant_id', $tenant->id)
                ->where('number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $latest ? ((int) substr((string) $latest, -6)) + 1 : 1;

            return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
```

- [ ] **Step 5: Add the project policy**

Create `apps/api/Domains/Project/Policies/ProcurementProjectPolicy.php`:

```php
<?php

namespace Domains\Project\Policies;

use App\Models\User;
use Domains\Project\Models\ProcurementProject;

class ProcurementProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProcurementProject $project): bool
    {
        return $user->tenants()->whereKey($project->tenant_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->hasRole($user, ['buyer', 'admin']);
    }

    public function update(User $user, ProcurementProject $project): bool
    {
        if ($project->status->isTerminal()) {
            return false;
        }

        return $this->hasRole($user, ['buyer', 'admin']) || (int) $project->owner_id === (int) $user->id;
    }

    public function transition(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function cancel(User $user, ProcurementProject $project): bool
    {
        return ! $project->status->isTerminal() && $this->hasRole($user, ['buyer', 'admin']);
    }

    public function linkRequisitions(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    public function unlinkRequisitions(User $user, ProcurementProject $project): bool
    {
        return $this->update($user, $project);
    }

    private function hasRole(User $user, array $roles): bool
    {
        return $user->tenants()->wherePivotIn('role', $roles)->exists();
    }
}
```

If existing role lookup helpers are available in `CurrentTenant`, prefer them in controllers/resources, but keep the policy independent enough for Laravel authorization calls.

- [ ] **Step 6: Add project actions**

Create the three action classes. Use this shared behavior:

```php
AuditEvent::query()->create([
    'tenant_id' => $tenant->id,
    'actor_id' => $actor->id,
    'event_type' => 'project.created',
    'auditable_type' => ProcurementProject::class,
    'auditable_id' => $project->id,
    'metadata' => ['name' => $project->name, 'number' => $project->number],
]);
```

`CreateProcurementProject::handle(Tenant $tenant, User $actor, array $data): ProcurementProject` should set `status` to `ProjectStatus::Draft`, assign `number` from `ProcurementProjectNumberGenerator`, and load `owner`, `requisitions`, `cancelledBy`, and `completedBy`.

`UpdateProcurementProject::handle(Tenant $tenant, User $actor, ProcurementProject $project, array $data): ProcurementProject` should abort with `HttpException(409, 'Terminal projects cannot be updated.')` when `$project->status->isTerminal()` is true, update metadata fields, write `project.updated`, and reload relationships.

`TransitionProcurementProjectStatus::handle(Tenant $tenant, User $actor, ProcurementProject $project, ProjectStatus $next, ?string $reason = null): ProcurementProject` should verify `$project->status->canTransitionTo($next)`, set cancellation or completion metadata for terminal transitions, require a reason for `ProjectStatus::Cancelled`, write the matching event type, and reload relationships.

- [ ] **Step 7: Run focused API tests**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
```

Expected: tests still fail because controllers, requests, resources, and routes are not created yet. Model-level column errors should be gone.

---

## Task 3: Project Controllers, Requests, Resources, Routes, And Activity

**Files:**
- Create: `apps/api/Domains/Project/Http/Controllers/ProcurementProjectController.php`
- Create: `apps/api/Domains/Project/Http/Controllers/ProjectActivityController.php`
- Create: `apps/api/Domains/Project/Http/Requests/StoreProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Requests/UpdateProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Requests/TransitionProcurementProjectRequest.php`
- Create: `apps/api/Domains/Project/Http/Resources/ProcurementProjectResource.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/ProcurementProjectApiTest.php`

- [ ] **Step 1: Add project form requests**

Create `StoreProcurementProjectRequest` with:

```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'charter' => ['nullable', 'string', 'max:5000'],
        'ownerId' => ['required', 'integer', 'exists:users,id'],
        'budgetAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        'currency' => ['required', 'string', 'size:3'],
        'department' => ['nullable', 'string', 'max:255'],
        'costCenter' => ['nullable', 'string', 'max:255'],
        'targetStartDate' => ['nullable', 'date'],
        'targetCompletionDate' => ['nullable', 'date', 'after_or_equal:targetStartDate'],
    ];
}
```

Create `UpdateProcurementProjectRequest` with the same keys, using `sometimes` for each field. Create `TransitionProcurementProjectRequest` with:

```php
public function rules(): array
{
    return [
        'reason' => ['nullable', 'string', 'max:2000'],
    ];
}
```

Each request should validate `ownerId` after the base rules:

```php
$validator->after(function ($validator): void {
    $tenant = app(\App\Tenancy\CurrentTenant::class)->get();
    $ownerId = $this->input('ownerId');

    if ($ownerId === null || $tenant === null) {
        return;
    }

    $belongsToTenant = \App\Models\User::query()
        ->whereKey($ownerId)
        ->whereHas('tenants', fn ($query) => $query->whereKey($tenant->id))
        ->exists();

    if (! $belongsToTenant) {
        $validator->errors()->add('ownerId', 'The selected owner must belong to the current tenant.');
    }
});
```

- [ ] **Step 2: Add `ProcurementProjectResource`**

Create the resource with these response keys:

```php
return [
    'id' => (string) $this->id,
    'tenantId' => (string) $this->tenant_id,
    'number' => $this->number,
    'name' => $this->name,
    'charter' => $this->charter,
    'status' => $this->status->value,
    'owner' => $this->userSummary($this->whenLoaded('owner')),
    'budgetAmount' => $this->budget_amount !== null ? (float) $this->budget_amount : null,
    'currency' => $this->currency,
    'department' => $this->department,
    'costCenter' => $this->cost_center,
    'targetStartDate' => $this->target_start_date?->toDateString(),
    'targetCompletionDate' => $this->target_completion_date?->toDateString(),
    'cancelledAt' => $this->cancelled_at?->toISOString(),
    'cancelledBy' => $this->userSummary($this->whenLoaded('cancelledBy')),
    'cancellationReason' => $this->cancellation_reason,
    'completedAt' => $this->completed_at?->toISOString(),
    'completedBy' => $this->userSummary($this->whenLoaded('completedBy')),
    'summary' => $this->summary(),
    'permissions' => [
        'canUpdate' => $request->user()?->can('update', $this->resource) ?? false,
        'canActivate' => $this->status === ProjectStatus::Draft && ($request->user()?->can('transition', $this->resource) ?? false),
        'canHold' => $this->status === ProjectStatus::Active && ($request->user()?->can('transition', $this->resource) ?? false),
        'canResume' => $this->status === ProjectStatus::OnHold && ($request->user()?->can('transition', $this->resource) ?? false),
        'canComplete' => in_array($this->status, [ProjectStatus::Active, ProjectStatus::OnHold], true) && ($request->user()?->can('transition', $this->resource) ?? false),
        'canCancel' => $request->user()?->can('cancel', $this->resource) ?? false,
        'canLinkRequisitions' => $request->user()?->can('linkRequisitions', $this->resource) ?? false,
        'canUnlinkRequisitions' => $request->user()?->can('unlinkRequisitions', $this->resource) ?? false,
        'canViewActivity' => $request->user()?->can('view', $this->resource) ?? false,
    ],
    'createdAt' => $this->created_at?->toISOString(),
    'updatedAt' => $this->updated_at?->toISOString(),
];
```

Implement `summary()` by querying loaded requisitions when available, otherwise by tenant/project scoped aggregate queries. It must return:

```php
[
    'estimatedRequisitionTotal' => 0.0,
    'linkedRequisitionCount' => 0,
    'draftRequisitionCount' => 0,
    'submittedRequisitionCount' => 0,
    'changesRequestedRequisitionCount' => 0,
    'stoppedRequisitionCount' => 0,
    'approvalPlaceholderCount' => 0,
    'awardPlaceholderCount' => 0,
]
```

- [ ] **Step 3: Add controller**

Create `ProcurementProjectController` with `index`, `store`, `show`, `update`, `activate`, `hold`, `resume`, `complete`, and `cancel`. The `index` query should:

```php
$query = ProcurementProject::query()
    ->with(['owner', 'cancelledBy', 'completedBy'])
    ->where('tenant_id', $tenant->id)
    ->latest('updated_at');

$query->when($request->query('search'), function ($query, string $search): void {
    $query->where(function ($query) use ($search): void {
        $query->where('name', 'like', "%{$search}%")
            ->orWhere('number', 'like', "%{$search}%")
            ->orWhere('department', 'like', "%{$search}%")
            ->orWhere('cost_center', 'like', "%{$search}%");
    });
});
$query->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status));
$query->when($request->query('ownerId'), fn ($query, string $ownerId) => $query->where('owner_id', $ownerId));
$query->when($request->query('department'), fn ($query, string $department) => $query->where('department', $department));
$query->when($request->query('costCenter'), fn ($query, string $costCenter) => $query->where('cost_center', $costCenter));
$query->when($request->query('updatedFrom'), fn ($query, string $date) => $query->whereDate('updated_at', '>=', $date));
$query->when($request->query('updatedTo'), fn ($query, string $date) => $query->whereDate('updated_at', '<=', $date));
```

Use the same pagination meta shape as requisitions:

```php
return response()->json([
    'data' => ProcurementProjectResource::collection($paginator->getCollection())->resolve(),
    'meta' => [
        'currentPage' => $paginator->currentPage(),
        'perPage' => $paginator->perPage(),
        'total' => $paginator->total(),
        'lastPage' => $paginator->lastPage(),
    ],
]);
```

- [ ] **Step 4: Add project activity controller**

Create `ProjectActivityController::index(CurrentTenant $currentTenant, int $project)` that tenant-loads the project, authorizes `view`, and returns project audit events in the same shape used by requisition activity. Reuse `AuditEventController` resource shape if there is an existing resource; otherwise return `id`, `type`, `actor`, `metadata`, and `occurredAt`.

- [ ] **Step 5: Register project routes**

Modify `apps/api/routes/api.php` imports:

```php
use Domains\Project\Http\Controllers\ProcurementProjectController;
use Domains\Project\Http\Controllers\ProjectActivityController;
```

Inside the tenant middleware group, add before requisition routes:

```php
Route::get('/projects', [ProcurementProjectController::class, 'index']);
Route::post('/projects', [ProcurementProjectController::class, 'store']);
Route::get('/projects/{project}', [ProcurementProjectController::class, 'show']);
Route::patch('/projects/{project}', [ProcurementProjectController::class, 'update']);
Route::post('/projects/{project}/activate', [ProcurementProjectController::class, 'activate']);
Route::post('/projects/{project}/hold', [ProcurementProjectController::class, 'hold']);
Route::post('/projects/{project}/resume', [ProcurementProjectController::class, 'resume']);
Route::post('/projects/{project}/complete', [ProcurementProjectController::class, 'complete']);
Route::post('/projects/{project}/cancel', [ProcurementProjectController::class, 'cancel']);
Route::get('/projects/{project}/activity', [ProjectActivityController::class, 'index']);
```

- [ ] **Step 6: Run focused tests and route list**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
php artisan route:list --path=api/projects
```

Expected: project create/update/list/detail/activity tests pass. Link/unlink tests are not present yet. Route list includes all project routes above.

---

## Task 4: Project-Requisition Link/Unlink And Requisition Summary

**Files:**
- Create: `apps/api/Domains/Project/Actions/LinkRequisitionToProject.php`
- Create: `apps/api/Domains/Project/Actions/UnlinkRequisitionFromProject.php`
- Create: `apps/api/Domains/Project/Http/Controllers/ProjectRequisitionController.php`
- Create: `apps/api/Domains/Project/Http/Requests/LinkProjectRequisitionRequest.php`
- Create: `apps/api/Domains/Project/Http/Resources/ProjectRequisitionResource.php`
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`
- Modify: `apps/api/Domains/Requisition/Actions/CreateRequisitionDraft.php`
- Modify: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/ProcurementProjectApiTest.php`
- Test: `apps/api/tests/Feature/RequisitionApiTest.php`

- [ ] **Step 1: Add failing link/unlink and requisition summary tests**

Append these tests to `ProcurementProjectApiTest`:

```php
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
```

Add this to `RequisitionApiTest`:

```php
public function test_requisition_response_includes_project_summary(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $project = \Domains\Project\Models\ProcurementProject::query()->create([
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
```

- [ ] **Step 2: Run tests to verify failures**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
php artisan test --filter=RequisitionApiTest
```

Expected: link/unlink routes and `projectSummary` assertions fail.

- [ ] **Step 3: Add requisition project relationship and summary**

In `Requisition.php`, add:

```php
use Domains\Project\Models\ProcurementProject;

public function project(): BelongsTo
{
    return $this->belongsTo(ProcurementProject::class, 'project_id');
}
```

In `RequisitionResource`, add:

```php
'projectSummary' => $this->projectSummary($this->whenLoaded('project')),
```

and:

```php
private function projectSummary(ProcurementProject|MissingValue|null $project): ?array
{
    if (! $project instanceof ProcurementProject) {
        return null;
    }

    return [
        'id' => (string) $project->id,
        'number' => $project->number,
        'name' => $project->name,
        'status' => $project->status->value,
        'owner' => $this->userSummary($project->relationLoaded('owner') ? $project->owner : null),
    ];
}
```

Update requisition controller eager loads from `with(['requester', 'lineItems', ...])` to include `project.owner` for index/show/update responses where `RequisitionResource` is returned.

- [ ] **Step 4: Add link/unlink actions and routes**

`LinkRequisitionToProject::handle()` should:

```php
$requisition = Requisition::query()
    ->where('tenant_id', $tenant->id)
    ->whereKey($requisitionId)
    ->firstOrFail();

if (in_array($requisition->status, [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled], true)) {
    throw new HttpException(409, 'Terminal requisitions cannot be linked to projects.');
}

$requisition->forceFill(['project_id' => $project->id])->save();
```

It should create `project.requisition_linked` and `requisition.project_linked` audit events.

`UnlinkRequisitionFromProject::handle()` should tenant-load the requisition where `project_id` equals the project ID, reject terminal requisitions with 409, set `project_id` to null, and create `project.requisition_unlinked` and `requisition.project_unlinked` audit events.

Add `ProjectRequisitionController` methods:

- `index(CurrentTenant $currentTenant, int $project)`
- `store(LinkProjectRequisitionRequest $request, CurrentTenant $currentTenant, LinkRequisitionToProject $action, int $project)`
- `destroy(CurrentTenant $currentTenant, UnlinkRequisitionFromProject $action, int $project, int $requisition)`

Register routes:

```php
Route::get('/projects/{project}/requisitions', [ProjectRequisitionController::class, 'index']);
Route::post('/projects/{project}/requisitions', [ProjectRequisitionController::class, 'store']);
Route::delete('/projects/{project}/requisitions/{requisition}', [ProjectRequisitionController::class, 'destroy']);
```

- [ ] **Step 5: Validate requisition `projectId` on create/update**

In `CreateRequisitionDraft` and `UpdateRequisitionDraft`, before saving a non-empty `projectId`, verify:

```php
$projectId = $data['projectId'] ?? null;

if ($projectId !== null && $projectId !== '') {
    ProcurementProject::query()
        ->where('tenant_id', $tenant->id)
        ->whereKey($projectId)
        ->whereNotIn('status', [ProjectStatus::Completed, ProjectStatus::Cancelled])
        ->firstOrFail();
}
```

If the current API error style maps `ModelNotFoundException` to 404, replace `firstOrFail()` with validator-level validation in the form requests so invalid project IDs return 422. The expected test status for cross-tenant project selection should be 422.

- [ ] **Step 6: Run linked focused tests**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api/projects
```

Expected: project and requisition project-summary tests pass.

---

## Task 5: OpenAPI Contract And Generated Client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/**`
- Modify if needed: `packages/api-client/src/index.ts`

- [ ] **Step 1: Add OpenAPI schemas**

In `apps/api/storage/openapi/openapi.json`, add schemas with these exact names:

- `ProcurementProject`
- `ProcurementProjectSummary`
- `ProcurementProjectPermissions`
- `ProcurementProjectListResponse`
- `StoreProcurementProjectRequest`
- `UpdateProcurementProjectRequest`
- `TransitionProcurementProjectRequest`
- `LinkProjectRequisitionRequest`
- `ProjectRequisition`
- `ProjectRequisitionListResponse`
- `ProjectActivityListResponse`

`ProcurementProject.status` enum must be:

```json
["draft", "active", "on_hold", "completed", "cancelled"]
```

`ProcurementProjectPermissions` must include the boolean keys from the spec:

```json
[
  "canUpdate",
  "canActivate",
  "canHold",
  "canResume",
  "canComplete",
  "canCancel",
  "canLinkRequisitions",
  "canUnlinkRequisitions",
  "canViewActivity"
]
```

- [ ] **Step 2: Add OpenAPI paths**

Add paths for:

```txt
GET /api/projects
POST /api/projects
GET /api/projects/{projectId}
PATCH /api/projects/{projectId}
POST /api/projects/{projectId}/activate
POST /api/projects/{projectId}/hold
POST /api/projects/{projectId}/resume
POST /api/projects/{projectId}/complete
POST /api/projects/{projectId}/cancel
GET /api/projects/{projectId}/requisitions
POST /api/projects/{projectId}/requisitions
DELETE /api/projects/{projectId}/requisitions/{requisitionId}
GET /api/projects/{projectId}/activity
```

Use operation IDs that produce readable generated endpoint names:

```txt
listProjects
createProject
getProject
updateProject
activateProject
holdProject
resumeProject
completeProject
cancelProject
listProjectRequisitions
linkProjectRequisition
unlinkProjectRequisition
listProjectActivity
```

- [ ] **Step 3: Update requisition schema**

Add `projectSummary` to the generated `Requisition` schema:

```json
"projectSummary": {
  "nullable": true,
  "$ref": "#/components/schemas/RequisitionProjectSummary"
}
```

Add `RequisitionProjectSummary` with `id`, `number`, `name`, `status`, and nullable `owner`.

- [ ] **Step 4: Regenerate and check API client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoint functions and schema types for projects appear under `packages/api-client/src/generated`. `pnpm check:api-contract` exits 0 after generated files are current.

- [ ] **Step 5: Review generated names**

Run:

```bash
rg -n "listProjects|createProject|getProject|updateProject|linkProjectRequisition|projectSummary" packages/api-client/src
```

Expected: all operation IDs and schemas are present. If Orval generated different names, adjust frontend imports in later tasks to the generated names instead of hand-writing fetch calls.

---

## Task 6: Frontend Project API, Types, Hooks, And MSW

**Files:**
- Create: `apps/web/features/projects/types/project-view-model.ts`
- Create: `apps/web/features/projects/api/projects-api.ts`
- Create: `apps/web/features/projects/hooks/use-projects.ts`
- Create: `apps/web/features/projects/hooks/use-project.ts`
- Create: `apps/web/features/projects/hooks/use-project-actions.ts`
- Create: `apps/web/features/projects/hooks/use-project-requisitions.ts`
- Create: `apps/web/features/projects/mocks/project-fixtures.ts`
- Create: `apps/web/features/projects/mocks/project-handlers.ts`
- Create: `apps/web/features/projects/tests/project-api-mappers.test.ts`
- Modify: `apps/web/tests/msw/handlers.ts` or the current MSW handler barrel.

- [ ] **Step 1: Write failing API mapper tests**

Create `apps/web/features/projects/tests/project-api-mappers.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import { mapProject, mapProjectListResponse } from "../api/projects-api";
import { projectListResponseFixture, projectResponseFixture } from "../mocks/project-fixtures";

describe("project API mappers", () => {
  it("maps project detail responses into the view model", () => {
    const project = mapProject(projectResponseFixture.data);

    expect(project.id).toBe("501");
    expect(project.status).toBe("active");
    expect(project.owner.name).toBe("Priya Buyer");
    expect(project.summary.linkedRequisitionCount).toBe(2);
    expect(project.permissions.canLinkRequisitions).toBe(true);
  });

  it("maps project list responses with pagination metadata", () => {
    const response = mapProjectListResponse(projectListResponseFixture);

    expect(response.data).toHaveLength(1);
    expect(response.meta.total).toBe(1);
    expect(response.data[0]?.number).toBe("PRJ-2026-000501");
  });
});
```

Run:

```bash
pnpm --filter @cognify/web test -- projects/tests/project-api-mappers.test.ts
```

Expected: FAIL because project feature files do not exist.

- [ ] **Step 2: Add project view model types**

Create `project-view-model.ts` with:

```ts
export type ProjectStatus = "draft" | "active" | "on_hold" | "completed" | "cancelled";

export type UserSummary = {
  id: string;
  name: string;
  email: string;
};

export type ProjectPermissions = {
  canUpdate: boolean;
  canActivate: boolean;
  canHold: boolean;
  canResume: boolean;
  canComplete: boolean;
  canCancel: boolean;
  canLinkRequisitions: boolean;
  canUnlinkRequisitions: boolean;
  canViewActivity: boolean;
};

export type ProjectSummary = {
  estimatedRequisitionTotal: number;
  linkedRequisitionCount: number;
  draftRequisitionCount: number;
  submittedRequisitionCount: number;
  changesRequestedRequisitionCount: number;
  stoppedRequisitionCount: number;
  approvalPlaceholderCount: number;
  awardPlaceholderCount: number;
};

export type ProcurementProject = {
  id: string;
  tenantId: string;
  number: string;
  name: string;
  charter?: string;
  status: ProjectStatus;
  owner: UserSummary;
  budgetAmount?: number | null;
  currency: string;
  department?: string;
  costCenter?: string;
  targetStartDate?: string;
  targetCompletionDate?: string;
  cancelledAt?: string | null;
  cancellationReason?: string | null;
  completedAt?: string | null;
  summary: ProjectSummary;
  permissions: ProjectPermissions;
  createdAt: string;
  updatedAt: string;
};

export type ProjectListResponse = {
  data: ProcurementProject[];
  meta: {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
  };
};

export type ProjectFormValues = {
  name: string;
  charter: string;
  ownerId: string;
  budgetAmount: string;
  currency: string;
  department: string;
  costCenter: string;
  targetStartDate: string;
  targetCompletionDate: string;
};
```

- [ ] **Step 3: Add project fixtures**

Create fixtures with IDs and values used by tests:

```ts
export const projectResponseFixture = {
  data: {
    id: "501",
    tenantId: "1",
    number: "PRJ-2026-000501",
    name: "Office refresh",
    charter: "Refresh the Kuala Lumpur office.",
    status: "active",
    owner: { id: "12", name: "Priya Buyer", email: "priya@example.test" },
    budgetAmount: 25000,
    currency: "MYR",
    department: "Operations",
    costCenter: "OPS-100",
    targetStartDate: "2026-06-01",
    targetCompletionDate: "2026-09-30",
    cancelledAt: null,
    cancellationReason: null,
    completedAt: null,
    summary: {
      estimatedRequisitionTotal: 12000,
      linkedRequisitionCount: 2,
      draftRequisitionCount: 1,
      submittedRequisitionCount: 1,
      changesRequestedRequisitionCount: 0,
      stoppedRequisitionCount: 0,
      approvalPlaceholderCount: 0,
      awardPlaceholderCount: 0,
    },
    permissions: {
      canUpdate: true,
      canActivate: false,
      canHold: true,
      canResume: false,
      canComplete: true,
      canCancel: true,
      canLinkRequisitions: true,
      canUnlinkRequisitions: true,
      canViewActivity: true,
    },
    createdAt: "2026-05-15T00:00:00.000000Z",
    updatedAt: "2026-05-15T01:00:00.000000Z",
  },
} as const;

export const projectListResponseFixture = {
  data: [projectResponseFixture.data],
  meta: { currentPage: 1, perPage: 15, total: 1, lastPage: 1 },
} as const;
```

- [ ] **Step 4: Add generated-client API wrappers**

Create `projects-api.ts`. Import generated endpoints from `@cognify/api-client/endpoints` and generated schemas from `@cognify/api-client/schemas`. Follow the requisitions API pattern:

```ts
import {
  activateProject,
  cancelProject,
  completeProject,
  createProject,
  getProject,
  holdProject,
  linkProjectRequisition,
  listProjectActivity,
  listProjectRequisitions,
  listProjects as listProjectsEndpoint,
  resumeProject,
  unlinkProjectRequisition,
  updateProject,
} from "@cognify/api-client/endpoints";
import type { ProcurementProject as ApiProject, ProcurementProjectListResponse as ApiProjectListResponse } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";
import type { ProcurementProject, ProjectFormValues, ProjectListResponse } from "../types/project-view-model";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;
  return { headers: { "X-Tenant-Id": tenantId } };
}

export function mapProject(project: ApiProject): ProcurementProject {
  return {
    id: project.id,
    tenantId: project.tenantId,
    number: project.number,
    name: project.name,
    charter: project.charter ?? "",
    status: project.status,
    owner: {
      id: project.owner.id,
      name: project.owner.name,
      email: project.owner.email ?? "",
    },
    budgetAmount: project.budgetAmount ?? null,
    currency: project.currency,
    department: project.department ?? "",
    costCenter: project.costCenter ?? "",
    targetStartDate: project.targetStartDate ?? "",
    targetCompletionDate: project.targetCompletionDate ?? "",
    cancelledAt: project.cancelledAt ?? null,
    cancellationReason: project.cancellationReason ?? null,
    completedAt: project.completedAt ?? null,
    summary: project.summary,
    permissions: project.permissions,
    createdAt: project.createdAt,
    updatedAt: project.updatedAt,
  };
}

export function mapProjectListResponse(response: ApiProjectListResponse): ProjectListResponse {
  return {
    data: response.data.map(mapProject),
    meta: response.meta,
  };
}
```

Add exported functions for list/get/create/update/status/link/unlink/activity. Each should call generated endpoints with `withActiveTenantHeader()`, check the expected status code, and throw `response.data` otherwise.

- [ ] **Step 5: Add TanStack Query hooks**

Create hooks with stable query keys:

```ts
export const projectKeys = {
  all: ["projects"] as const,
  lists: () => [...projectKeys.all, "list"] as const,
  list: (query: ProjectQuery) => [...projectKeys.lists(), query] as const,
  detail: (projectId: string) => [...projectKeys.all, "detail", projectId] as const,
  requisitions: (projectId: string) => [...projectKeys.all, "requisitions", projectId] as const,
  activity: (projectId: string) => [...projectKeys.all, "activity", projectId] as const,
};
```

`useProjectActions` should invalidate `projectKeys.detail(projectId)`, `projectKeys.lists()`, and `projectKeys.requisitions(projectId)` after successful mutations.

- [ ] **Step 6: Add MSW project handlers**

Create `project-handlers.ts` using `http.get`, `http.post`, `http.patch`, and `HttpResponse.json`. Handlers must cover:

- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/:projectId`
- `PATCH /api/projects/:projectId`
- status action posts
- `GET /api/projects/:projectId/requisitions`
- `POST /api/projects/:projectId/requisitions`
- `DELETE /api/projects/:projectId/requisitions/:requisitionId`
- `GET /api/projects/:projectId/activity`

Use the fixture response shape from generated API contract. Add the new handlers to the central MSW handler list.

- [ ] **Step 7: Run frontend project mapper tests**

Run:

```bash
pnpm --filter @cognify/web test -- projects/tests/project-api-mappers.test.ts
```

Expected: PASS.

---

## Task 7: Project List, Create/Edit Form, And Routes

**Files:**
- Create: `apps/web/features/projects/schemas/project-form-schema.ts`
- Create: `apps/web/features/projects/forms/project-form.tsx`
- Create: `apps/web/features/projects/components/project-status-badge.tsx`
- Create: `apps/web/features/projects/tables/projects-table.tsx`
- Create: `apps/web/features/projects/workflows/project-list-page.tsx`
- Create: `apps/web/features/projects/workflows/project-create-page.tsx`
- Create: `apps/web/app/(workspace)/projects/page.tsx`
- Create: `apps/web/app/(workspace)/projects/new/page.tsx`
- Create: `apps/web/features/projects/tests/project-form-schema.test.ts`
- Create: `apps/web/features/projects/tests/projects-workflow.test.tsx`

- [ ] **Step 1: Write failing form schema tests**

Create `project-form-schema.test.ts`:

```ts
import { describe, expect, it } from "vitest";
import { projectFormSchema } from "../schemas/project-form-schema";

describe("projectFormSchema", () => {
  it("accepts a valid project form", () => {
    const parsed = projectFormSchema.safeParse({
      name: "Office refresh",
      charter: "Refresh workstations.",
      ownerId: "12",
      budgetAmount: "25000.00",
      currency: "MYR",
      department: "Operations",
      costCenter: "OPS-100",
      targetStartDate: "2026-06-01",
      targetCompletionDate: "2026-09-30",
    });

    expect(parsed.success).toBe(true);
  });

  it("rejects completion dates before start dates", () => {
    const parsed = projectFormSchema.safeParse({
      name: "Office refresh",
      charter: "",
      ownerId: "12",
      budgetAmount: "25000.00",
      currency: "MYR",
      department: "",
      costCenter: "",
      targetStartDate: "2026-09-30",
      targetCompletionDate: "2026-06-01",
    });

    expect(parsed.success).toBe(false);
  });
});
```

- [ ] **Step 2: Add project schema**

Create `project-form-schema.ts`:

```ts
import { z } from "zod";

export const projectFormSchema = z.object({
  name: z.string().min(1, "Project name is required").max(255),
  charter: z.string().max(5000).optional().default(""),
  ownerId: z.string().min(1, "Owner is required"),
  budgetAmount: z.string().regex(/^\d+(\.\d{1,2})?$/, "Budget must be a valid amount"),
  currency: z.string().length(3, "Currency must be a 3-letter code"),
  department: z.string().max(255).optional().default(""),
  costCenter: z.string().max(255).optional().default(""),
  targetStartDate: z.string().optional().default(""),
  targetCompletionDate: z.string().optional().default(""),
}).refine((value) => {
  if (!value.targetStartDate || !value.targetCompletionDate) return true;
  return value.targetCompletionDate >= value.targetStartDate;
}, {
  message: "Target completion date cannot be before target start date",
  path: ["targetCompletionDate"],
});
```

- [ ] **Step 3: Add status badge with shadcn primitive**

Create `project-status-badge.tsx` using `Badge` from `@cognify/ui`:

```tsx
import { Badge } from "@cognify/ui";
import type { ProjectStatus } from "../types/project-view-model";

const labels: Record<ProjectStatus, string> = {
  draft: "Draft",
  active: "Active",
  on_hold: "On hold",
  completed: "Completed",
  cancelled: "Cancelled",
};

export function ProjectStatusBadge({ status }: { status: ProjectStatus }) {
  return <Badge variant={status === "cancelled" ? "destructive" : "secondary"}>{labels[status]}</Badge>;
}
```

- [ ] **Step 4: Add form and workflow**

Build `ProjectForm` with `react-hook-form`, `FormField`, `FormErrorSummary`, `Button`, `NativeSelect`, and `Textarea`. Use plain inputs only where `packages/ui` has no primitive yet. The submit payload must be `ProjectFormValues` and the create workflow should redirect to `/projects/{id}` after success.

Owner options can use the current `/api/me` membership user as an initial single option for the first pass if a tenant-user directory endpoint does not exist. Do not create a broad user administration endpoint in this epic.

- [ ] **Step 5: Add project table and list workflow**

Use the existing app `DataTable` instead of hand-rolling table behavior. Columns:

- project number
- name
- status
- owner
- budget
- linked requisitions
- updated date
- actions

The list workflow must include loading, empty, populated, and error states. Use `Button`, `Badge`, and `NativeSelect` from `@cognify/ui` where applicable.

- [ ] **Step 6: Add App Router pages**

Create:

```tsx
// apps/web/app/(workspace)/projects/page.tsx
import { ProjectListPage } from "@/features/projects/workflows/project-list-page";

export default function ProjectsPage() {
  return <ProjectListPage />;
}
```

```tsx
// apps/web/app/(workspace)/projects/new/page.tsx
import { ProjectCreatePage } from "@/features/projects/workflows/project-create-page";

export default function NewProjectPage() {
  return <ProjectCreatePage />;
}
```

- [ ] **Step 7: Add workflow tests**

In `projects-workflow.test.tsx`, render `ProjectListPage` and `ProjectCreatePage` inside the existing test providers. Assert:

- list displays `Office refresh`
- empty state appears when MSW returns no projects
- create form validates required name
- successful create calls the generated-shaped handler

- [ ] **Step 8: Run project web tests**

Run:

```bash
pnpm --filter @cognify/web test -- projects
pnpm --filter @cognify/web typecheck
```

Expected: project feature tests pass; typecheck may still fail until generated client imports and later project detail files are complete.

---

## Task 8: Project Detail Workspace, Pipeline, Actions, And Activity

**Files:**
- Create: `apps/web/features/projects/components/project-action-dialog.tsx`
- Create: `apps/web/features/projects/components/project-budget-summary.tsx`
- Create: `apps/web/features/projects/components/project-requisition-pipeline.tsx`
- Create: `apps/web/features/projects/components/project-activity-timeline.tsx`
- Create: `apps/web/features/projects/workflows/project-detail-page.tsx`
- Create: `apps/web/app/(workspace)/projects/[projectId]/page.tsx`
- Extend: `apps/web/features/projects/tests/projects-workflow.test.tsx`

- [ ] **Step 1: Add failing detail workflow tests**

Extend `projects-workflow.test.tsx` with:

```tsx
it("renders project workspace summary and placeholders", async () => {
  render(<ProjectDetailPage projectId="501" />, { wrapper: TestAppProviders });

  expect(await screen.findByRole("heading", { name: "Office refresh" })).toBeInTheDocument();
  expect(screen.getByText("PRJ-2026-000501")).toBeInTheDocument();
  expect(screen.getByText("Budget summary")).toBeInTheDocument();
  expect(screen.getByText("Requisition pipeline")).toBeInTheDocument();
  expect(screen.getByText("Approval routing is not active for projects yet.")).toBeInTheDocument();
  expect(screen.getByText("Project risks are reserved for a later governance slice.")).toBeInTheDocument();
  expect(screen.getByText("Award records will appear here after award workflows are implemented.")).toBeInTheDocument();
});
```

- [ ] **Step 2: Build budget summary component**

`ProjectBudgetSummary` should accept `budgetAmount`, `currency`, and `summary`. It must display:

- budget amount
- linked requisition estimated total
- remaining budget
- non-enforcing over-budget warning when linked totals exceed budget

Use compact bordered sections, not nested cards.

- [ ] **Step 3: Build requisition pipeline**

`ProjectRequisitionPipeline` should group linked requisitions by:

- draft
- submitted
- changes requested
- stopped

Each row links to `/requisitions/{id}` and displays requisition number, title, requester, estimated total, and status.

- [ ] **Step 4: Build action dialog**

`ProjectActionDialog` should mirror the requisition action dialog pattern. It needs actions:

- activate
- hold
- resume
- complete
- cancel with required reason

Use `Button` from `@cognify/ui` for triggers and confirm buttons. Use `Textarea` for cancellation reason.

- [ ] **Step 5: Build detail page**

Use `RecordWorkspaceLayout`:

```tsx
<RecordWorkspaceLayout
  backHref="/projects"
  backLabel="Back to projects"
  eyebrow={project.number}
  title={project.name}
  status={<ProjectStatusBadge status={project.status} />}
  metadata={[
    { id: "owner", label: "Owner", value: project.owner.name },
    { id: "budget", label: "Budget", value: formatMoney(project.budgetAmount ?? 0, project.currency) },
    { id: "target", label: "Target completion", value: project.targetCompletionDate || "No target" },
  ]}
  sections={[
    { id: "overview", label: "Overview" },
    { id: "budget", label: "Budget" },
    { id: "pipeline", label: "Pipeline" },
    { id: "activity", label: "Activity" },
  ]}
  primaryActions={actions}
>
  ...
</RecordWorkspaceLayout>
```

Remember the project as a recent record with `type: "project"`, `href: /projects/{id}`, and status.

- [ ] **Step 6: Add dynamic route**

Create:

```tsx
import { ProjectDetailPage } from "@/features/projects/workflows/project-detail-page";

export default async function ProjectWorkspacePage({
  params,
}: {
  params: Promise<{ projectId: string }>;
}) {
  const { projectId } = await params;
  return <ProjectDetailPage projectId={projectId} />;
}
```

- [ ] **Step 7: Run detail tests**

Run:

```bash
pnpm --filter @cognify/web test -- projects
pnpm --filter @cognify/web typecheck
```

Expected: project workflow tests pass. Typecheck passes unless requisition integration remains incomplete.

---

## Task 9: Requisition Project Selector And Project Summary Links

**Files:**
- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`
- Modify: `apps/web/features/requisitions/forms/requisition-form.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Modify: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`
- Extend: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Add failing requisition UI tests**

Extend requisition workflow tests with:

```tsx
it("shows project summary link on requisition detail when project is present", async () => {
  render(<RequisitionDetailPage requisitionId="1001" />, { wrapper: TestAppProviders });

  expect(await screen.findByRole("link", { name: /Office refresh/ })).toHaveAttribute("href", "/projects/501");
});

it("lets requesters select an active project in the requisition form", async () => {
  render(<RequisitionCreatePage />, { wrapper: TestAppProviders });

  const projectSelect = await screen.findByLabelText("Project");
  await userEvent.selectOptions(projectSelect, "501");

  expect(projectSelect).toHaveValue("501");
});
```

- [ ] **Step 2: Add requisition project summary type**

In `requisition-view-model.ts`, add:

```ts
export type RequisitionProjectSummary = {
  id: string;
  number: string;
  name: string;
  status: "draft" | "active" | "on_hold" | "completed" | "cancelled";
  owner?: UserSummary | null;
};
```

Add `projectSummary?: RequisitionProjectSummary | null` to `Requisition`.

- [ ] **Step 3: Map project summary from generated client**

In `requisitions-api.ts`, update `mapRequisition`:

```ts
projectSummary: requisition.projectSummary
  ? {
      id: requisition.projectSummary.id,
      number: requisition.projectSummary.number,
      name: requisition.projectSummary.name,
      status: requisition.projectSummary.status,
      owner: requisition.projectSummary.owner ? mapUserSummary(requisition.projectSummary.owner) : null,
    }
  : null,
```

- [ ] **Step 4: Add project selector to requisition form**

Use `useProjects({ status: "active", perPage: 100 })` in `RequisitionForm`. Render with `NativeSelect` from `@cognify/ui` if the existing form can accept it; otherwise use the existing form field wrapper with a native `select`.

Behavior:

- first option is empty: `No project`
- options show `{number} - {name}`
- terminal projects are excluded unless the requisition is already linked to one
- API lookup failure shows a non-blocking inline message and preserves the rest of the form
- form submission sends only `projectId`

- [ ] **Step 5: Add project summary links**

In requisition detail metadata or overview section, render:

```tsx
{requisition.projectSummary ? (
  <Link href={`/projects/${requisition.projectSummary.id}`} className="font-medium underline-offset-4 hover:underline">
    {requisition.projectSummary.number} - {requisition.projectSummary.name}
  </Link>
) : (
  "No project"
)}
```

In requisitions table, add a compact Project column if it does not break mobile fallback. If the table is already dense, add project under the title/subtitle area on mobile and as a column on desktop.

- [ ] **Step 6: Update requisition fixtures and handlers**

Add `projectSummary` to at least one requisition fixture:

```ts
projectSummary: {
  id: "501",
  number: "PRJ-2026-000501",
  name: "Office refresh",
  status: "active",
  owner: { id: "12", name: "Priya Buyer", email: "priya@example.test" },
},
```

- [ ] **Step 7: Run requisition-focused web tests**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions
pnpm --filter @cognify/web typecheck
```

Expected: requisition tests and typecheck pass.

---

## Task 10: Search, Navigation, Demo Data, And Shell Integration

**Files:**
- Modify: `apps/api/Domains/Search/Providers/ProcurementProjectSearchProvider.php`
- Modify: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/features/search/hooks/use-recent-records.ts` if needed.
- Test: project search coverage in `apps/api/tests/Feature/ProcurementProjectApiTest.php` or existing search tests.
- Test: shell route config test if it exists.

- [ ] **Step 1: Add failing project search assertion**

Add to `ProcurementProjectApiTest`:

```php
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
```

- [ ] **Step 2: Update project search provider**

In `ProcurementProjectSearchProvider`, change `href`:

```php
href: "/projects/{$project->id}",
```

Keep tenant scoping and owner eager loading.

- [ ] **Step 3: Update demo data**

In `DemoRoadmapPreviewSeeder`, ensure seeded `ProcurementProject::updateOrCreate()` calls include:

```php
'charter' => 'Coordinate related requisitions for the workspace refresh.',
'department' => 'Operations',
'cost_center' => 'OPS-100',
'target_start_date' => '2026-06-01',
'target_completion_date' => '2026-09-30',
'status' => 'active',
```

Use deterministic dates and values. Do not seed RFQ/award workflow records beyond existing preview data.

- [ ] **Step 4: Add shell route**

In `shell-route-config.ts`, add a Projects navigation item only if the current route config already includes workspace navigation for requisitions/system. Use an existing icon from `lucide-react` and keep permission behavior consistent with requisitions. Do not show Create Project before permissions/session context is loaded.

- [ ] **Step 5: Run search and shell tests**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
cd ../..
pnpm --filter @cognify/web test -- shell
```

Expected: project search href points to `/projects/{id}` and shell tests pass.

---

## Task 11: Contract, API, Web, And Regression Verification

**Files:**
- No new source files. This task verifies the integrated feature.

- [ ] **Step 1: Run API project checks**

Run:

```bash
cd apps/api
php artisan test --filter=ProcurementProjectApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api/projects
```

Expected: all selected API tests pass and project routes list all endpoint methods.

- [ ] **Step 2: Run contract checks**

Run from repo root:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generation completes and contract check exits 0. Review generated diffs in `packages/api-client/src/generated/**`.

- [ ] **Step 3: Run web checks**

Run:

```bash
pnpm --filter @cognify/web test -- projects
pnpm --filter @cognify/web test -- requisitions
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: project tests, requisition tests, typecheck, and lint pass.

- [ ] **Step 4: Run broader checks if generated clients or shared config changed**

Run:

```bash
pnpm typecheck
pnpm test
```

Expected: root typecheck and test suites pass. If failures are unrelated to project work, capture the exact failing test and decide whether it blocks this feature.

- [ ] **Step 5: Review scope boundaries**

Run:

```bash
rg -n "rfq|quotation|award|risk score|gantt|portfolio|purchase order" apps/web/features/projects apps/api/Domains/Project
```

Expected: matches are limited to quiet placeholder labels or absent. There should be no RFQ, quotation, award, risk scoring, Gantt, portfolio, or PO handoff implementation in the project feature.

- [ ] **Step 6: Final git review**

Run:

```bash
git status --short
git diff --stat
```

Expected: diffs are limited to project workspace implementation, generated API client files, and the required requisition/search/shell integration files.

---

## Suggested Commit Checkpoints

Use these only during implementation, after each checkpoint passes its focused tests:

```bash
git add apps/api/Domains/Project apps/api/database/migrations apps/api/tests/Feature/ProcurementProjectApiTest.php apps/api/routes/api.php
git commit -m "feat(api): add procurement project foundation"
```

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src
git commit -m "feat(contract): add procurement project API client"
```

```bash
git add apps/web/features/projects apps/web/app/'(workspace)'/projects
git commit -m "feat(web): add procurement project workspace"
```

```bash
git add apps/api/Domains/Requisition apps/api/Domains/Search apps/web/features/requisitions apps/web/components/shell
git commit -m "feat: link requisitions to procurement projects"
```

Do not create these commits during planning. They are execution checkpoints for the implementation phase.

## Self-Review

- Spec coverage: The plan covers project records, project workspace, lifecycle statuses, tenant permissions, requisition linking, activity, search, OpenAPI, generated client consumption, MSW, web routes, shadcn-first UI composition, and verification.
- Scope boundary: Approval orchestration, RFQ, quotation, award, PO handoff, real risk management, budget enforcement, programs, portfolios, milestones, and Gantt views are excluded.
- Type consistency: Backend status values are `draft`, `active`, `on_hold`, `completed`, and `cancelled`; frontend `ProjectStatus` uses the same values; endpoint operation names match the plan's generated-client imports.
- Open risk: If the generated Orval names differ from the requested operation IDs, frontend API wrapper imports must use the actual generated names found by `rg` after `pnpm generate:api`.

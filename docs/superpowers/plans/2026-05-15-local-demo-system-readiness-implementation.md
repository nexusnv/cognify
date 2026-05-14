# Local Demo System Readiness Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build deterministic local demo data and an authenticated system readiness surface for the final P0 Cognify epic.

**Architecture:** Add lightweight roadmap-preview models in their future domain folders, orchestrated by idempotent demo seeders under `database/seeders/Demo`. Add infrastructure readiness checks under `app/Observability/SystemStatus`, expose them through `GET /api/system/status`, regenerate the API client, and consume the generated contract from a new web `system-readiness` feature, shell footer indicator, and `/system` admin route.

**Tech Stack:** Laravel 12, Eloquent, PHPUnit, OpenAPI JSON, Orval-generated `@cognify/api-client`, Next.js App Router, TanStack Query, MSW, Vitest, Testing Library.

---

## Decisions Locked For This Plan

- Detailed web route: `/system` under `apps/web/app/(workspace)/system/page.tsx`.
- Demo refresh: idempotent seeders using stable keys and `updateOrCreate`, so `php artisan db:seed` can refresh local demo data without duplicate records.
- Attachments: create real tiny files on the Laravel `local` disk and matching attachment records.
- API version: source from `config('app.version')`, backed by `APP_VERSION` with fallback to `0.1.0`.
- Demo counts: return tenant-scoped counts for the active tenant plus a global non-sensitive `seeded` marker and `lastSeededAt`.
- Detailed readiness authorization: require authenticated user, resolved tenant, and `canAccessAdmin` equivalent by checking the active tenant role is `admin`.

## File Map

### Backend Migrations And Models

- Create: `apps/api/database/migrations/2026_05_15_000000_create_demo_readiness_tables.php`
  - Creates `vendors`, `procurement_projects`, `rfqs`, `quotations`, `approval_tasks`, `awards`, and `demo_seed_runs`.
- Create: `apps/api/Domains/Vendor/Models/Vendor.php`
  - Tenant-scoped vendor preview record.
- Create: `apps/api/Domains/Project/Models/ProcurementProject.php`
  - Tenant-scoped project preview record.
- Create: `apps/api/Domains/Quotation/Models/Rfq.php`
  - Tenant-scoped RFQ preview record.
- Create: `apps/api/Domains/Quotation/Models/Quotation.php`
  - Tenant-scoped quotation preview record.
- Create: `apps/api/Domains/Approval/Models/ApprovalTask.php`
  - Tenant-scoped approval task preview record.
- Create: `apps/api/Domains/Award/Models/Award.php`
  - Tenant-scoped award preview record.
- Create: `apps/api/Domains/Demo/Models/DemoSeedRun.php`
  - Records demo seed metadata for readiness.

### Backend Demo Seeders

- Create: `apps/api/database/seeders/Demo/DemoSeedContext.php`
  - Carries seeded tenants, users, requisitions, vendors, projects, RFQs, quotations, approval tasks, and awards between seeders.
- Create: `apps/api/database/seeders/Demo/DemoTenantSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoUserSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoAttachmentSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoAuditSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoNotificationSeeder.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
  - Orchestrates focused demo seeders.

### Backend Search

- Create: `apps/api/Domains/Search/Providers/VendorSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/ProcurementProjectSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/RfqSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/QuotationSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/AwardSearchProvider.php`
- Modify: `apps/api/Domains/Search/Services/SearchService.php`
  - Registers preview providers.
- Modify: `apps/api/Domains/Search/Data/SearchResultData.php`
  - Keep `href` non-null and point preview records to linked requisitions or `/system`.
- Modify: `apps/api/storage/openapi/openapi.json`
  - Adds preview search result enum values if the current schema enumerates result types.

### Backend Readiness API

- Create: `apps/api/app/Observability/SystemStatus/SystemStatus.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusCheckResult.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusService.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/ApiMetadataCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/DatabaseCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/CacheCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/QueueCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/StorageCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/OpenApiCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/DemoSeedCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Http/Controllers/SystemStatusController.php`
- Create: `apps/api/app/Observability/SystemStatus/Http/Resources/SystemStatusResource.php`
- Modify: `apps/api/config/app.php`
  - Adds `version`.
- Modify: `apps/api/.env.example`
  - Adds `APP_VERSION=0.1.0`.
- Modify: `apps/api/routes/api.php`
  - Adds `GET /system/status`.
- Modify: `apps/api/storage/openapi/openapi.json`
  - Adds system status contract and operation.

### Backend Tests

- Create: `apps/api/tests/Feature/DemoSeederTest.php`
- Create: `apps/api/tests/Feature/SystemStatusApiTest.php`
- Modify: `apps/api/tests/Feature/SearchApiTest.php`
  - Adds preview search isolation coverage.

### API Client

- Modify generated files under `packages/api-client/src/generated/`
- Modify: `packages/api-client/src/generated/endpoints.ts` or equivalent generated endpoint exports
- Modify: `packages/api-client/src/generated/schemas/index.ts`
- Modify: `packages/api-client/src/index.ts`
  - Export the generated status endpoint and schemas when the generated barrel file requires an explicit export.

### Frontend System Readiness

- Create: `apps/web/features/system-readiness/api/system-readiness-api.ts`
- Create: `apps/web/features/system-readiness/hooks/use-system-status.ts`
- Create: `apps/web/features/system-readiness/components/system-status-badge.tsx`
- Create: `apps/web/features/system-readiness/components/system-status-summary.tsx`
- Create: `apps/web/features/system-readiness/components/system-check-list.tsx`
- Create: `apps/web/features/system-readiness/components/demo-dataset-summary.tsx`
- Create: `apps/web/features/system-readiness/workflows/system-status-page.tsx`
- Create: `apps/web/features/system-readiness/mocks/system-readiness-fixtures.ts`
- Create: `apps/web/features/system-readiness/mocks/system-readiness-handlers.ts`
- Create: `apps/web/features/system-readiness/tests/system-status-page.test.tsx`
- Create: `apps/web/features/system-readiness/tests/system-status-footer.test.tsx`
- Create: `apps/web/app/(workspace)/system/page.tsx`
- Modify: `apps/web/tests/msw/handlers.ts`
  - Registers system readiness handlers.

### Frontend Shell And Search

- Modify: `apps/web/components/shell/shell-footer.tsx`
  - Adds compact readiness indicator.
- Modify: `apps/web/components/shell/app-shell.tsx`
  - Passes admin capability to footer and avoids querying readiness for non-admin users.
- Modify: `apps/web/components/shell/shell-route-config.ts`
  - Adds `System` nav item visible to admin-capable users.
- Modify: `apps/web/features/search/components/command-palette.tsx`
  - Handles preview result types and `/system` preview destinations.
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
  - Adds preview search records.
- Modify: `apps/web/features/search/tests/command-palette.test.tsx`
  - Covers preview records.

---

## Task 1: Add Lightweight Preview Tables And Models

**Files:**
- Create: `apps/api/database/migrations/2026_05_15_000000_create_demo_readiness_tables.php`
- Create: `apps/api/Domains/Vendor/Models/Vendor.php`
- Create: `apps/api/Domains/Project/Models/ProcurementProject.php`
- Create: `apps/api/Domains/Quotation/Models/Rfq.php`
- Create: `apps/api/Domains/Quotation/Models/Quotation.php`
- Create: `apps/api/Domains/Approval/Models/ApprovalTask.php`
- Create: `apps/api/Domains/Award/Models/Award.php`
- Create: `apps/api/Domains/Demo/Models/DemoSeedRun.php`
- Test: `apps/api/tests/Feature/DemoSeederTest.php`

- [ ] **Step 1: Write the failing schema/model test**

Add this test file:

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_models_are_tenant_scoped_and_cast_metadata(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme Procurement']);
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $tenant->users()->attach($user->id, ['role' => TenantRole::Admin->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Atlas Office Supplies',
            'status' => 'preferred',
            'category' => 'Office supplies',
            'risk_rating' => 'low',
            'metadata' => ['region' => 'APAC'],
        ]);

        $project = ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'PRJ-2026-0001',
            'name' => 'HQ Workplace Refresh',
            'status' => 'active',
            'owner_id' => $user->id,
            'budget_amount' => 120000,
            'currency' => 'USD',
            'metadata' => ['department' => 'Operations'],
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'number' => 'RFQ-2026-0001',
            'title' => 'Office furniture package',
            'status' => 'open',
            'due_at' => '2026-05-29 12:00:00',
            'metadata' => ['invited_vendors' => 3],
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'QUO-2026-0001',
            'status' => 'received',
            'total_amount' => 84500,
            'currency' => 'USD',
            'metadata' => ['lead_time_days' => 21],
        ]);

        $approvalTask = ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'approver_id' => $user->id,
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'title' => 'Finance approval for office furniture package',
            'status' => 'pending',
            'due_at' => '2026-05-22 12:00:00',
            'metadata' => ['stage' => 'finance'],
        ]);

        $award = Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'number' => 'AWD-2026-0001',
            'status' => 'recommended',
            'total_amount' => 84500,
            'currency' => 'USD',
            'decided_at' => '2026-05-20 12:00:00',
            'metadata' => ['rationale' => 'Best delivery confidence'],
        ]);

        $run = DemoSeedRun::query()->create([
            'name' => 'local-demo',
            'seeded_at' => '2026-05-15 00:00:00',
            'metadata' => ['version' => 1],
        ]);

        $this->assertSame($tenant->id, $vendor->tenant_id);
        $this->assertSame('APAC', $vendor->metadata['region']);
        $this->assertSame($tenant->id, $project->tenant_id);
        $this->assertSame(3, $rfq->metadata['invited_vendors']);
        $this->assertSame(21, $quotation->metadata['lead_time_days']);
        $this->assertSame('finance', $approvalTask->metadata['stage']);
        $this->assertSame('Best delivery confidence', $award->metadata['rationale']);
        $this->assertSame('local-demo', $run->name);
    }
}
```

- [ ] **Step 2: Run the failing model test**

Run:

```bash
cd apps/api
php artisan test --filter=DemoSeederTest
```

Expected: FAIL because preview model classes and tables do not exist.

- [ ] **Step 3: Add the migration**

Create `apps/api/database/migrations/2026_05_15_000000_create_demo_readiness_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status');
            $table->string('category')->nullable();
            $table->string('risk_rating')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('procurement_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number');
            $table->string('name');
            $table->string('status');
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('rfqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->string('number');
            $table->string('title');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('approval_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->string('status');
            $table->timestamp('due_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('rfq_id')->nullable()->constrained('rfqs')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('demo_seed_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('seeded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_seed_runs');
        Schema::dropIfExists('awards');
        Schema::dropIfExists('approval_tasks');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('rfqs');
        Schema::dropIfExists('procurement_projects');
        Schema::dropIfExists('vendors');
    }
};
```

- [ ] **Step 4: Add focused Eloquent models**

Create `apps/api/Domains/Vendor/Models/Vendor.php`:

```php
<?php

namespace Domains\Vendor\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vendor extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'status',
        'category',
        'risk_rating',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

Create `apps/api/Domains/Project/Models/ProcurementProject.php`:

```php
<?php

namespace Domains\Project\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementProject extends Model
{
    protected $fillable = [
        'tenant_id',
        'owner_id',
        'number',
        'name',
        'status',
        'budget_amount',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
```

Create `apps/api/Domains/Quotation/Models/Rfq.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rfq extends Model
{
    protected $table = 'rfqs';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'requisition_id',
        'number',
        'title',
        'status',
        'due_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }
}
```

Create `apps/api/Domains/Quotation/Models/Quotation.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'vendor_id',
        'number',
        'status',
        'total_amount',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
```

Create `apps/api/Domains/Approval/Models/ApprovalTask.php`:

```php
<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalTask extends Model
{
    protected $fillable = [
        'tenant_id',
        'approver_id',
        'subject_type',
        'subject_id',
        'title',
        'status',
        'due_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Create `apps/api/Domains/Award/Models/Award.php`:

```php
<?php

namespace Domains\Award\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'rfq_id',
        'quotation_id',
        'vendor_id',
        'number',
        'status',
        'total_amount',
        'currency',
        'decided_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
```

Create `apps/api/Domains/Demo/Models/DemoSeedRun.php`:

```php
<?php

namespace Domains\Demo\Models;

use Illuminate\Database\Eloquent\Model;

class DemoSeedRun extends Model
{
    protected $fillable = [
        'name',
        'seeded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'seeded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
```

- [ ] **Step 5: Run the model test**

Run:

```bash
cd apps/api
php artisan test --filter=DemoSeederTest
```

Expected: PASS.

- [ ] **Step 6: Commit preview schema and models**

Run:

```bash
git add apps/api/database/migrations/2026_05_15_000000_create_demo_readiness_tables.php apps/api/Domains/Vendor/Models/Vendor.php apps/api/Domains/Project/Models/ProcurementProject.php apps/api/Domains/Quotation/Models/Rfq.php apps/api/Domains/Quotation/Models/Quotation.php apps/api/Domains/Approval/Models/ApprovalTask.php apps/api/Domains/Award/Models/Award.php apps/api/Domains/Demo/Models/DemoSeedRun.php apps/api/tests/Feature/DemoSeederTest.php
git commit -m "feat(api): add demo preview records"
```

---

## Task 2: Build Idempotent Demo Seeders

**Files:**
- Create: `apps/api/database/seeders/Demo/DemoSeedContext.php`
- Create: `apps/api/database/seeders/Demo/DemoTenantSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoUserSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoAttachmentSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoAuditSeeder.php`
- Create: `apps/api/database/seeders/Demo/DemoNotificationSeeder.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
- Test: `apps/api/tests/Feature/DemoSeederTest.php`

- [ ] **Step 1: Add failing seed idempotency test**

Append this test to `apps/api/tests/Feature/DemoSeederTest.php`:

```php
public function test_demo_seeders_create_repeatable_full_demo_dataset(): void
{
    $this->seed();
    $this->seed();

    $this->assertDatabaseHas('tenants', ['name' => 'Acme Procurement']);
    $this->assertDatabaseHas('tenants', ['name' => 'Northwind Sourcing']);
    $this->assertDatabaseHas('tenants', ['name' => 'Beta Corp Sandbox']);
    $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
    $this->assertDatabaseHas('users', ['email' => 'finance@example.com']);
    $this->assertDatabaseHas('vendors', ['name' => 'Atlas Office Supplies', 'status' => 'preferred']);
    $this->assertDatabaseHas('procurement_projects', ['number' => 'PRJ-2026-0001']);
    $this->assertDatabaseHas('rfqs', ['number' => 'RFQ-2026-0001']);
    $this->assertDatabaseHas('quotations', ['number' => 'QUO-2026-0001']);
    $this->assertDatabaseHas('approval_tasks', ['title' => 'Finance approval for office furniture package']);
    $this->assertDatabaseHas('awards', ['number' => 'AWD-2026-0001']);
    $this->assertDatabaseHas('demo_seed_runs', ['name' => 'local-demo']);

    $this->assertSame(3, \App\Tenancy\Tenant::query()->count());
    $this->assertSame(7, \App\Models\User::query()->count());
    $this->assertSame(6, \Domains\Vendor\Models\Vendor::query()->count());
}
```

- [ ] **Step 2: Run the failing seed test**

Run:

```bash
cd apps/api
php artisan test --filter=DemoSeederTest
```

Expected: FAIL because the focused demo seeders do not exist and the current seed shape is incomplete.

- [ ] **Step 3: Create shared seed context**

Create `apps/api/database/seeders/Demo/DemoSeedContext.php`:

```php
<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Collection;

class DemoSeedContext
{
    /** @var Collection<string, Tenant> */
    public Collection $tenants;

    /** @var Collection<string, User> */
    public Collection $users;

    /** @var Collection<string, Requisition> */
    public Collection $requisitions;

    /** @var Collection<string, Vendor> */
    public Collection $vendors;

    /** @var Collection<string, ProcurementProject> */
    public Collection $projects;

    /** @var Collection<string, Rfq> */
    public Collection $rfqs;

    /** @var Collection<string, Quotation> */
    public Collection $quotations;

    /** @var Collection<string, ApprovalTask> */
    public Collection $approvalTasks;

    /** @var Collection<string, Award> */
    public Collection $awards;

    public function __construct()
    {
        $this->tenants = collect();
        $this->users = collect();
        $this->requisitions = collect();
        $this->vendors = collect();
        $this->projects = collect();
        $this->rfqs = collect();
        $this->quotations = collect();
        $this->approvalTasks = collect();
        $this->awards = collect();
    }
}
```

- [ ] **Step 4: Create tenant and user seeders**

Create `apps/api/database/seeders/Demo/DemoTenantSeeder.php`:

```php
<?php

namespace Database\Seeders\Demo;

use App\Tenancy\Tenant;

class DemoTenantSeeder
{
    public function run(DemoSeedContext $context): void
    {
        foreach ([
            'acme' => 'Acme Procurement',
            'northwind' => 'Northwind Sourcing',
            'beta' => 'Beta Corp Sandbox',
        ] as $key => $name) {
            $context->tenants->put($key, Tenant::query()->updateOrCreate(['name' => $name], ['name' => $name]));
        }
    }
}
```

Create `apps/api/database/seeders/Demo/DemoUserSeeder.php`:

```php
<?php

namespace Database\Seeders\Demo;

use App\Auth\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $users = [
            'requester' => ['Test User', 'test@example.com', TenantRole::Requester->value, ['acme']],
            'buyer' => ['Buyer User', 'buyer@example.com', TenantRole::Buyer->value, ['acme']],
            'approver' => ['Approver User', 'approver@example.com', TenantRole::Approver->value, ['acme']],
            'finance' => ['Finance User', 'finance@example.com', TenantRole::Approver->value, ['acme']],
            'auditor' => ['Audit User', 'auditor@example.com', TenantRole::Admin->value, ['acme']],
            'vendor_manager' => ['Vendor Manager', 'vendor.manager@example.com', TenantRole::Buyer->value, ['northwind']],
            'admin' => ['Admin User', 'admin@example.com', TenantRole::Admin->value, ['acme', 'beta']],
        ];

        foreach ($users as $key => [$name, $email, $role, $tenantKeys]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'locale' => 'en',
                    'theme' => 'system',
                ],
            );

            foreach ($tenantKeys as $tenantKey) {
                $user->tenants()->syncWithoutDetaching([
                    $context->tenants->get($tenantKey)->id => ['role' => $role],
                ]);
            }

            $context->users->put($key, $user);
        }
    }
}
```

- [ ] **Step 5: Create requisition seeder**

Create `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`:

```php
<?php

namespace Database\Seeders\Demo;

use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class DemoRequisitionSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $requester = $context->users->get('requester');

        $records = [
            'office-refresh' => [
                'REQ-2026-0001',
                'HQ workplace refresh',
                RequisitionStatus::Submitted,
                'Operations',
                'CC-OPS-100',
                [['Ergonomic chair', 45, 420], ['Adjustable desk', 45, 880]],
            ],
            'laptops' => [
                'REQ-2026-0002',
                'Engineering laptop refresh',
                RequisitionStatus::Draft,
                'Engineering',
                'CC-ENG-220',
                [['Developer laptop', 18, 2450], ['Docking station', 18, 240]],
            ],
            'security-audit' => [
                'REQ-2026-0003',
                'Security audit services',
                RequisitionStatus::Submitted,
                'Security',
                'CC-SEC-310',
                [['SOC 2 readiness assessment', 1, 38000]],
            ],
        ];

        foreach ($records as $key => [$number, $title, $status, $department, $costCenter, $items]) {
            $requisition = Requisition::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $number],
                [
                    'requester_id' => $requester->id,
                    'title' => $title,
                    'business_justification' => "Demo justification for {$title}.",
                    'needed_by_date' => '2026-06-30',
                    'department' => $department,
                    'cost_center' => $costCenter,
                    'delivery_location' => 'Kuala Lumpur HQ',
                    'currency' => 'USD',
                    'status' => $status,
                    'submitted_at' => $status === RequisitionStatus::Submitted ? now() : null,
                ],
            );

            $requisition->lineItems()->delete();
            foreach ($items as [$name, $quantity, $unitPrice]) {
                $requisition->lineItems()->create([
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_of_measure' => 'each',
                    'estimated_unit_price' => $unitPrice,
                    'currency' => 'USD',
                ]);
            }

            $context->requisitions->put($key, $requisition->refresh());
        }
    }
}
```

- [ ] **Step 6: Create roadmap preview seeder**

Create `apps/api/database/seeders/Demo/DemoRoadmapPreviewSeeder.php` using the models from Task 1. Include these exact stable records:

```php
<?php

namespace Database\Seeders\Demo;

use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;

class DemoRoadmapPreviewSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $admin = $context->users->get('admin');
        $approver = $context->users->get('finance');

        $vendorRows = [
            'atlas' => ['Atlas Office Supplies', 'preferred', 'Office supplies', 'low'],
            'northstar' => ['Northstar Furniture Co', 'evaluation', 'Furniture', 'medium'],
            'secureworks' => ['SecureWorks Advisory', 'preferred', 'Professional services', 'low'],
            'papertrail' => ['Papertrail Logistics', 'restricted', 'Logistics', 'high'],
            'byteforge' => ['ByteForge Systems', 'evaluation', 'IT hardware', 'medium'],
            'greenline' => ['Greenline Facilities', 'preferred', 'Facilities', 'low'],
        ];

        foreach ($vendorRows as $key => [$name, $status, $category, $risk]) {
            $context->vendors->put($key, Vendor::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['status' => $status, 'category' => $category, 'risk_rating' => $risk, 'metadata' => ['demo' => true]],
            ));
        }

        $project = ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-0001'],
            [
                'owner_id' => $admin->id,
                'name' => 'HQ Workplace Refresh',
                'status' => 'active',
                'budget_amount' => 120000,
                'currency' => 'USD',
                'metadata' => ['department' => 'Operations'],
            ],
        );
        $context->projects->put('workplace-refresh', $project);

        $rfq = Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'RFQ-2026-0001'],
            [
                'project_id' => $project->id,
                'requisition_id' => $context->requisitions->get('office-refresh')->id,
                'title' => 'Office furniture package',
                'status' => 'open',
                'due_at' => now()->addDays(14),
                'metadata' => ['invited_vendors' => 3],
            ],
        );
        $context->rfqs->put('office-furniture', $rfq);

        $quotation = Quotation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'QUO-2026-0001'],
            [
                'rfq_id' => $rfq->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'received',
                'total_amount' => 84500,
                'currency' => 'USD',
                'metadata' => ['lead_time_days' => 21],
            ],
        );
        $context->quotations->put('northstar-office', $quotation);

        $approvalTask = ApprovalTask::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'subject_type' => Quotation::class,
                'subject_id' => $quotation->id,
                'title' => 'Finance approval for office furniture package',
            ],
            [
                'approver_id' => $approver->id,
                'status' => 'pending',
                'due_at' => now()->addDays(7),
                'metadata' => ['stage' => 'finance'],
            ],
        );
        $context->approvalTasks->put('office-finance', $approvalTask);

        $award = Award::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'AWD-2026-0001'],
            [
                'project_id' => $project->id,
                'rfq_id' => $rfq->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'recommended',
                'total_amount' => 84500,
                'currency' => 'USD',
                'decided_at' => now(),
                'metadata' => ['rationale' => 'Best delivery confidence'],
            ],
        );
        $context->awards->put('office-award', $award);
    }
}
```

- [ ] **Step 7: Create attachment, audit, and notification seeders**

Create these files with focused seed logic:

`apps/api/database/seeders/Demo/DemoAttachmentSeeder.php`

```php
<?php

namespace Database\Seeders\Demo;

use Domains\Attachment\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DemoAttachmentSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $user = $context->users->get('requester');
        $requisition = $context->requisitions->get('office-refresh');
        $path = "tenants/{$tenant->id}/demo/office-refresh-brief.txt";
        $contents = "Cognify local demo attachment for HQ workplace refresh.\n";

        Storage::disk('local')->put($path, $contents);

        Attachment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'storage_path' => $path],
            [
                'attachable_type' => $requisition::class,
                'attachable_id' => $requisition->id,
                'uploaded_by' => $user->id,
                'original_filename' => 'office-refresh-brief.txt',
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size_bytes' => strlen($contents),
                'storage_disk' => 'local',
                'checksum_sha256' => hash('sha256', $contents),
                'previewable' => true,
            ],
        );
    }
}
```

`apps/api/database/seeders/Demo/DemoAuditSeeder.php`

```php
<?php

namespace Database\Seeders\Demo;

use App\Audit\AuditEvent;

class DemoAuditSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $actor = $context->users->get('requester');
        $requisition = $context->requisitions->get('office-refresh');

        AuditEvent::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'action' => 'requisition.submitted',
                'subject_type' => $requisition::class,
                'subject_id' => (string) $requisition->id,
            ],
            [
                'actor_id' => $actor->id,
                'subject_display' => $requisition->number,
                'metadata' => ['demo' => true],
                'after' => ['status' => 'submitted'],
                'occurred_at' => now(),
            ],
        );
    }
}
```

`apps/api/database/seeders/Demo/DemoNotificationSeeder.php`

```php
<?php

namespace Database\Seeders\Demo;

use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;

class DemoNotificationSeeder
{
    public function run(DemoSeedContext $context): void
    {
        app(NotificationRecorder::class)->record(
            tenant: $context->tenants->get('acme'),
            recipients: collect([$context->users->get('admin'), $context->users->get('buyer')]),
            data: new NotificationData(
                type: NotificationPreferenceDefaults::EVENT_SYSTEM_ANNOUNCEMENT,
                title: 'Local demo data is ready',
                body: 'The Cognify demo workspace includes requisitions, vendors, RFQs, quotations, approvals, and awards.',
                href: '/system',
                metadata: ['demo' => true],
            ),
        );
    }
}
```

- [ ] **Step 8: Update DatabaseSeeder**

Replace `apps/api/database/seeders/DatabaseSeeder.php` with orchestration that calls each demo seeder and records the run:

```php
<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAttachmentSeeder;
use Database\Seeders\Demo\DemoAuditSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoRequisitionSeeder;
use Database\Seeders\Demo\DemoRoadmapPreviewSeeder;
use Database\Seeders\Demo\DemoSeedContext;
use Database\Seeders\Demo\DemoTenantSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Domains\Demo\Models\DemoSeedRun;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $context = new DemoSeedContext();

        app(DemoTenantSeeder::class)->run($context);
        app(DemoUserSeeder::class)->run($context);
        app(DemoRequisitionSeeder::class)->run($context);
        app(DemoRoadmapPreviewSeeder::class)->run($context);
        app(DemoAttachmentSeeder::class)->run($context);
        app(DemoAuditSeeder::class)->run($context);
        app(DemoNotificationSeeder::class)->run($context);

        DemoSeedRun::query()->updateOrCreate(
            ['name' => 'local-demo'],
            [
                'seeded_at' => now(),
                'metadata' => [
                    'tenants' => $context->tenants->count(),
                    'users' => $context->users->count(),
                    'vendors' => $context->vendors->count(),
                ],
            ],
        );
    }
}
```

- [ ] **Step 9: Make notification seeding idempotent and rerun the seed test**

Run:

```bash
cd apps/api
php artisan test --filter=DemoSeederTest
```

Before recording the demo notification, update `DemoNotificationSeeder` to delete prior demo system announcement notifications for the same tenant and title:

```php
\App\Notifications\NotificationRecord::query()
    ->where('tenant_id', $context->tenants->get('acme')->id)
    ->where('title', 'Local demo data is ready')
    ->delete();
```

Expected: PASS.

- [ ] **Step 10: Commit demo seeders**

Run:

```bash
git add apps/api/database/seeders apps/api/tests/Feature/DemoSeederTest.php
git commit -m "feat(api): seed local demo dataset"
```

---

## Task 3: Add Tenant-Scoped Preview Search

**Files:**
- Create: `apps/api/Domains/Search/Providers/VendorSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/ProcurementProjectSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/RfqSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/QuotationSearchProvider.php`
- Create: `apps/api/Domains/Search/Providers/AwardSearchProvider.php`
- Modify: `apps/api/Domains/Search/Services/SearchService.php`
- Test: `apps/api/tests/Feature/SearchApiTest.php`

- [ ] **Step 1: Add failing preview search test**

Append this test to `apps/api/tests/Feature/SearchApiTest.php`:

```php
public function test_search_returns_preview_records_for_active_tenant_only(): void
{
    $this->seed();

    $admin = \App\Models\User::query()->where('email', 'admin@example.com')->firstOrFail();
    $tenant = \App\Tenancy\Tenant::query()->where('name', 'Acme Procurement')->firstOrFail();
    $otherTenant = \App\Tenancy\Tenant::query()->where('name', 'Northwind Sourcing')->firstOrFail();

    \Domains\Vendor\Models\Vendor::query()->create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Atlas Office Supplies',
        'status' => 'restricted',
        'category' => 'Office supplies',
        'risk_rating' => 'high',
    ]);

    $response = $this
        ->actingAs($admin)
        ->withHeader('X-Tenant-Id', (string) $tenant->id)
        ->getJson('/api/search?query=Atlas&types[]=vendor&limit=10');

    $response->assertOk();
    $response->assertJsonPath('data.0.type', 'vendor');
    $response->assertJsonPath('data.0.title', 'Atlas Office Supplies');
    $this->assertCount(1, $response->json('data'));
}
```

- [ ] **Step 2: Run the failing search test**

Run:

```bash
cd apps/api
php artisan test --filter='search_returns_preview_records_for_active_tenant_only'
```

Expected: FAIL because vendor search provider is not registered.

- [ ] **Step 3: Add provider pattern for vendors**

Create `apps/api/Domains/Search/Providers/VendorSearchProvider.php`:

```php
<?php

namespace Domains\Search\Providers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Search\Contracts\SearchProvider;
use Domains\Search\Data\SearchResultData;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Collection;

class VendorSearchProvider implements SearchProvider
{
    public function type(): string
    {
        return 'vendor';
    }

    public function search(Tenant $tenant, User $user, string $query, int $limit): Collection
    {
        $normalizedQuery = mb_strtolower(trim($query));

        return Vendor::query()
            ->where('tenant_id', $tenant->id)
            ->where(function ($builder) use ($normalizedQuery): void {
                $builder->whereRaw('lower(name) like ?', ['%' . $normalizedQuery . '%'])
                    ->orWhereRaw('lower(category) like ?', ['%' . $normalizedQuery . '%']);
            })
            ->orderByRaw('CASE WHEN lower(name) LIKE ? THEN 0 ELSE 1 END', [$normalizedQuery . '%'])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Vendor $vendor): SearchResultData => new SearchResultData(
                type: $this->type(),
                id: (string) $vendor->id,
                title: $vendor->name,
                subtitle: $vendor->category,
                status: $vendor->status,
                href: '/system',
                updatedAt: $vendor->updated_at?->toISOString(),
            ));
    }
}
```

- [ ] **Step 4: Add remaining preview providers with the same contract**

Create providers for projects, RFQs, quotations, and awards. Use these result mappings:

```php
// ProcurementProjectSearchProvider
type: 'project'
title: $project->name
subtitle: $project->number
status: $project->status
href: '/system'

// RfqSearchProvider
type: 'rfq'
title: $rfq->title
subtitle: $rfq->number
status: $rfq->status
href: $rfq->requisition_id ? "/requisitions/{$rfq->requisition_id}" : '/system'

// QuotationSearchProvider
type: 'quotation'
title: $quotation->number
subtitle: $quotation->vendor?->name
status: $quotation->status
href: $quotation->rfq?->requisition_id ? "/requisitions/{$quotation->rfq->requisition_id}" : '/system'

// AwardSearchProvider
type: 'award'
title: $award->number
subtitle: $award->vendor?->name
status: $award->status
href: $award->rfq?->requisition_id ? "/requisitions/{$award->rfq->requisition_id}" : '/system'
```

Each provider must filter by `tenant_id`, search stable display fields, order exact/prefix matches before substring matches, and return `SearchResultData`.

- [ ] **Step 5: Register providers**

Modify `apps/api/Domains/Search/Services/SearchService.php`:

```php
private function providers(): array
{
    return [
        app(RequisitionSearchProvider::class),
        app(VendorSearchProvider::class),
        app(ProcurementProjectSearchProvider::class),
        app(RfqSearchProvider::class),
        app(QuotationSearchProvider::class),
        app(AwardSearchProvider::class),
    ];
}
```

Add imports for each new provider.

- [ ] **Step 6: Run search tests**

Run:

```bash
cd apps/api
php artisan test --filter=SearchApiTest
```

Expected: PASS.

- [ ] **Step 7: Commit preview search**

Run:

```bash
git add apps/api/Domains/Search apps/api/tests/Feature/SearchApiTest.php
git commit -m "feat(api): search demo preview records"
```

---

## Task 4: Add Backend System Readiness API

**Files:**
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusCheckResult.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatus.php`
- Create: `apps/api/app/Observability/SystemStatus/SystemStatusService.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/ApiMetadataCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/DatabaseCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/CacheCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/QueueCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/StorageCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/OpenApiCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Checks/DemoSeedCheck.php`
- Create: `apps/api/app/Observability/SystemStatus/Http/Controllers/SystemStatusController.php`
- Create: `apps/api/app/Observability/SystemStatus/Http/Resources/SystemStatusResource.php`
- Modify: `apps/api/config/app.php`
- Modify: `apps/api/.env.example`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/SystemStatusApiTest.php`

- [ ] **Step 1: Write failing status API tests**

Create `apps/api/tests/Feature/SystemStatusApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_system_status(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $tenant = Tenant::query()->where('name', 'Acme Procurement')->firstOrFail();

        $response = $this
            ->actingAs($admin)
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/system/status');

        $response->assertOk();
        $response->assertJsonPath('data.service', 'cognify-api');
        $response->assertJsonPath('data.demo.seeded', true);
        $this->assertContains($response->json('data.status'), ['ok', 'warning', 'error']);
        $this->assertGreaterThan(0, $response->json('data.demo.counts.requisitions'));
        $this->assertGreaterThan(0, count($response->json('data.checks')));
    }

    public function test_non_admin_cannot_view_system_status(): void
    {
        $this->seed();

        $requester = User::query()->where('email', 'test@example.com')->firstOrFail();
        $tenant = Tenant::query()->where('name', 'Acme Procurement')->firstOrFail();

        $this
            ->actingAs($requester)
            ->withHeader('X-Tenant-Id', (string) $tenant->id)
            ->getJson('/api/system/status')
            ->assertForbidden();
    }

    public function test_system_status_requires_current_tenant(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this
            ->actingAs($admin)
            ->getJson('/api/system/status')
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run failing status tests**

Run:

```bash
cd apps/api
php artisan test --filter=SystemStatusApiTest
```

Expected: FAIL because `/api/system/status` does not exist.

- [ ] **Step 3: Add status value objects and check contract**

Create `apps/api/app/Observability/SystemStatus/SystemStatusCheck.php`:

```php
<?php

namespace App\Observability\SystemStatus;

use App\Tenancy\Tenant;

interface SystemStatusCheck
{
    public function run(Tenant $tenant): SystemStatusCheckResult;
}
```

Create `apps/api/app/Observability/SystemStatus/SystemStatusCheckResult.php`:

```php
<?php

namespace App\Observability\SystemStatus;

class SystemStatusCheckResult
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $remediation = null,
        public readonly array $metadata = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'status' => $this->status,
            'message' => $this->message,
            'remediation' => $this->remediation,
            'metadata' => (object) $this->metadata,
        ];
    }
}
```

Create `apps/api/app/Observability/SystemStatus/SystemStatus.php`:

```php
<?php

namespace App\Observability\SystemStatus;

class SystemStatus
{
    public function __construct(
        public readonly string $status,
        public readonly string $environment,
        public readonly string $service,
        public readonly string $version,
        public readonly string $checkedAt,
        public readonly array $checks,
        public readonly array $demo,
    ) {
    }
}
```

- [ ] **Step 4: Add checks and aggregator**

Create `SystemStatusService` and check classes. Use this exact aggregation logic in `apps/api/app/Observability/SystemStatus/SystemStatusService.php`:

```php
<?php

namespace App\Observability\SystemStatus;

use App\Observability\SystemStatus\Checks\ApiMetadataCheck;
use App\Observability\SystemStatus\Checks\CacheCheck;
use App\Observability\SystemStatus\Checks\DatabaseCheck;
use App\Observability\SystemStatus\Checks\DemoSeedCheck;
use App\Observability\SystemStatus\Checks\OpenApiCheck;
use App\Observability\SystemStatus\Checks\QueueCheck;
use App\Observability\SystemStatus\Checks\StorageCheck;
use App\Tenancy\Tenant;
use Throwable;

class SystemStatusService
{
    public function report(Tenant $tenant): SystemStatus
    {
        $checks = collect($this->checks())
            ->map(fn (SystemStatusCheck $check): SystemStatusCheckResult => $this->safeRun($check, $tenant))
            ->values();

        $overall = $checks->contains(fn (SystemStatusCheckResult $check): bool => $check->status === 'error')
            ? 'error'
            : ($checks->contains(fn (SystemStatusCheckResult $check): bool => $check->status === 'warning') ? 'warning' : 'ok');

        $demoCheck = $checks->firstWhere('id', 'demo_seed');

        return new SystemStatus(
            status: $overall,
            environment: app()->environment(),
            service: 'cognify-api',
            version: (string) config('app.version', '0.1.0'),
            checkedAt: now()->toISOString(),
            checks: $checks->map->toArray()->all(),
            demo: $demoCheck?->metadata ?? ['seeded' => false, 'counts' => []],
        );
    }

    private function safeRun(SystemStatusCheck $check, Tenant $tenant): SystemStatusCheckResult
    {
        try {
            return $check->run($tenant);
        } catch (Throwable $exception) {
            return new SystemStatusCheckResult(
                id: class_basename($check),
                label: class_basename($check),
                status: 'error',
                message: 'Readiness check failed.',
                remediation: 'Review local service configuration and application logs.',
            );
        }
    }

    private function checks(): array
    {
        return [
            app(ApiMetadataCheck::class),
            app(DatabaseCheck::class),
            app(CacheCheck::class),
            app(QueueCheck::class),
            app(StorageCheck::class),
            app(OpenApiCheck::class),
            app(DemoSeedCheck::class),
        ];
    }
}
```

Implement each check with a concrete `SystemStatusCheckResult`:

```php
return new SystemStatusCheckResult(
    id: 'database',
    label: 'Database',
    status: 'ok',
    message: 'Connected',
    remediation: null,
);
```

Use these IDs and messages:

- `api`: `API metadata loaded`
- `database`: `Connected`
- `cache`: `Cache read/write succeeded`
- `queue`: `Queue storage is available`
- `storage`: `Storage read/write succeeded`
- `openapi`: `OpenAPI contract is available`
- `demo_seed`: `Demo seed data is available`

For `DemoSeedCheck`, metadata must include:

```php
[
    'seeded' => true,
    'lastSeededAt' => $seedRun->seeded_at?->toISOString(),
    'counts' => [
        'tenants' => 1,
        'users' => $tenant->users()->count(),
        'requisitions' => Requisition::query()->where('tenant_id', $tenant->id)->count(),
        'vendors' => Vendor::query()->where('tenant_id', $tenant->id)->count(),
        'rfqs' => Rfq::query()->where('tenant_id', $tenant->id)->count(),
        'quotations' => Quotation::query()->where('tenant_id', $tenant->id)->count(),
        'approvalTasks' => ApprovalTask::query()->where('tenant_id', $tenant->id)->count(),
        'awards' => Award::query()->where('tenant_id', $tenant->id)->count(),
    ],
]
```

- [ ] **Step 5: Add controller and resource**

Create `apps/api/app/Observability/SystemStatus/Http/Controllers/SystemStatusController.php`:

```php
<?php

namespace App\Observability\SystemStatus\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Observability\SystemStatus\Http\Resources\SystemStatusResource;
use App\Observability\SystemStatus\SystemStatusService;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;

class SystemStatusController extends Controller
{
    public function show(Request $request, CurrentTenant $currentTenant, SystemStatusService $service): SystemStatusResource
    {
        abort_unless($currentTenant->roleFor($request->user()) === TenantRole::Admin->value, 403);

        return new SystemStatusResource($service->report($currentTenant->get()));
    }
}
```

Create `apps/api/app/Observability/SystemStatus/Http/Resources/SystemStatusResource.php`:

```php
<?php

namespace App\Observability\SystemStatus\Http\Resources;

use App\Observability\SystemStatus\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var SystemStatus $status */
        $status = $this->resource;

        return [
            'status' => $status->status,
            'environment' => $status->environment,
            'service' => $status->service,
            'version' => $status->version,
            'checkedAt' => $status->checkedAt,
            'checks' => $status->checks,
            'demo' => $status->demo,
        ];
    }
}
```

- [ ] **Step 6: Register route and version config**

In `apps/api/routes/api.php`, import the controller:

```php
use App\Observability\SystemStatus\Http\Controllers\SystemStatusController;
```

Inside the `ResolveCurrentTenant` protected group, add:

```php
Route::get('/system/status', [SystemStatusController::class, 'show']);
```

In `apps/api/config/app.php`, add:

```php
'version' => env('APP_VERSION', '0.1.0'),
```

In `apps/api/.env.example`, add:

```env
APP_VERSION=0.1.0
```

- [ ] **Step 7: Run status API tests**

Run:

```bash
cd apps/api
php artisan test --filter=SystemStatusApiTest
```

Expected: PASS.

- [ ] **Step 8: Commit backend readiness API**

Run:

```bash
git add apps/api/app/Observability/SystemStatus apps/api/config/app.php apps/api/.env.example apps/api/routes/api.php apps/api/tests/Feature/SystemStatusApiTest.php
git commit -m "feat(api): expose system readiness status"
```

---

## Task 5: Update OpenAPI And Regenerate API Client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files in `packages/api-client/src/generated/`
- Modify: `packages/api-client/src/index.ts`
- Test: contract checks

- [ ] **Step 1: Add system status schemas to OpenAPI**

Edit `apps/api/storage/openapi/openapi.json` and add schemas named:

```json
"SystemStatusResponse"
"SystemStatus"
"SystemStatusCheck"
"SystemStatusDemoSummary"
"SystemStatusDemoCounts"
```

Use this response shape:

```json
{
  "data": {
    "status": "ok",
    "environment": "local",
    "service": "cognify-api",
    "version": "0.1.0",
    "checkedAt": "2026-05-15T00:00:00Z",
    "checks": [
      {
        "id": "database",
        "label": "Database",
        "status": "ok",
        "message": "Connected",
        "remediation": null,
        "metadata": {}
      }
    ],
    "demo": {
      "seeded": true,
      "lastSeededAt": "2026-05-15T00:00:00Z",
      "counts": {
        "tenants": 1,
        "users": 4,
        "requisitions": 3,
        "vendors": 6,
        "rfqs": 1,
        "quotations": 1,
        "approvalTasks": 1,
        "awards": 1
      }
    }
  }
}
```

Add path:

```json
"/api/system/status": {
  "get": {
    "operationId": "getSystemStatus",
    "summary": "Get system readiness status",
    "tags": ["System"],
    "responses": {
      "200": {
        "description": "System readiness status",
        "content": {
          "application/json": {
            "schema": {
              "$ref": "#/components/schemas/SystemStatusResponse"
            }
          }
        }
      },
      "401": {
        "$ref": "#/components/responses/Unauthenticated"
      },
      "403": {
        "$ref": "#/components/responses/Unauthorized"
      }
    }
  }
}
```

- [ ] **Step 2: Regenerate API client**

Run:

```bash
pnpm generate:api
```

Expected: generated schema files include system status types and endpoint exports include `getSystemStatus`.

- [ ] **Step 3: Run contract check**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS.

- [ ] **Step 4: Commit contract and generated client**

Run:

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src
git commit -m "feat(contract): add system status endpoint"
```

---

## Task 6: Add Web System Readiness Feature

**Files:**
- Create: `apps/web/features/system-readiness/api/system-readiness-api.ts`
- Create: `apps/web/features/system-readiness/hooks/use-system-status.ts`
- Create: `apps/web/features/system-readiness/components/system-status-badge.tsx`
- Create: `apps/web/features/system-readiness/components/system-status-summary.tsx`
- Create: `apps/web/features/system-readiness/components/system-check-list.tsx`
- Create: `apps/web/features/system-readiness/components/demo-dataset-summary.tsx`
- Create: `apps/web/features/system-readiness/workflows/system-status-page.tsx`
- Create: `apps/web/features/system-readiness/mocks/system-readiness-fixtures.ts`
- Create: `apps/web/features/system-readiness/mocks/system-readiness-handlers.ts`
- Create: `apps/web/features/system-readiness/tests/system-status-page.test.tsx`
- Create: `apps/web/app/(workspace)/system/page.tsx`
- Modify: `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Write failing system page test**

Create `apps/web/features/system-readiness/tests/system-status-page.test.tsx`:

```tsx
import { render, screen } from "@testing-library/react";
import { AppProviders } from "@/components/providers/app-providers";
import { SystemStatusPage } from "../workflows/system-status-page";

describe("SystemStatusPage", () => {
  it("renders readiness checks and demo counts", async () => {
    render(
      <AppProviders>
        <SystemStatusPage />
      </AppProviders>,
    );

    expect(await screen.findByRole("heading", { name: "System Status" })).toBeInTheDocument();
    expect(await screen.findByText("Database")).toBeInTheDocument();
    expect(await screen.findByText("Demo dataset")).toBeInTheDocument();
    expect(await screen.findByText("Requisitions")).toBeInTheDocument();
    expect(await screen.findByText("Vendors")).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the failing web test**

Run:

```bash
pnpm --filter @cognify/web test -- system-status-page.test.tsx
```

Expected: FAIL because the feature files do not exist.

- [ ] **Step 3: Add API hook**

Create `apps/web/features/system-readiness/api/system-readiness-api.ts`:

```ts
"use client";

import { getSystemStatus } from "@cognify/api-client";
import type { SystemStatusResponse } from "@cognify/api-client";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export async function fetchSystemStatus(): Promise<SystemStatusResponse> {
  const tenantId = getStoredActiveTenantId();
  const response = await getSystemStatus({
    headers: tenantId ? { "X-Tenant-Id": tenantId } : undefined,
  });

  if (response.status !== 200) {
    throw response.data;
  }

  return response.data;
}
```

Create `apps/web/features/system-readiness/hooks/use-system-status.ts`:

```ts
"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchSystemStatus } from "../api/system-readiness-api";

export function useSystemStatus(enabled = true) {
  return useQuery({
    queryKey: ["system-status"],
    queryFn: fetchSystemStatus,
    enabled,
    staleTime: 30_000,
    retry: 1,
  });
}
```

- [ ] **Step 4: Add fixtures and MSW handler**

Create `apps/web/features/system-readiness/mocks/system-readiness-fixtures.ts`:

```ts
import type { SystemStatusResponse } from "@cognify/api-client";

export const healthySystemStatus: SystemStatusResponse = {
  data: {
    status: "ok",
    environment: "local",
    service: "cognify-api",
    version: "0.1.0",
    checkedAt: "2026-05-15T00:00:00Z",
    checks: [
      {
        id: "database",
        label: "Database",
        status: "ok",
        message: "Connected",
        remediation: null,
        metadata: {},
      },
      {
        id: "storage",
        label: "Storage",
        status: "ok",
        message: "Storage read/write succeeded",
        remediation: null,
        metadata: {},
      },
    ],
    demo: {
      seeded: true,
      lastSeededAt: "2026-05-15T00:00:00Z",
      counts: {
        tenants: 1,
        users: 4,
        requisitions: 3,
        vendors: 6,
        rfqs: 1,
        quotations: 1,
        approvalTasks: 1,
        awards: 1,
      },
    },
  },
};
```

Create `apps/web/features/system-readiness/mocks/system-readiness-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import { healthySystemStatus } from "./system-readiness-fixtures";

export const systemReadinessHandlers = [
  http.get("/api/system/status", () => HttpResponse.json(healthySystemStatus)),
];
```

Modify `apps/web/tests/msw/handlers.ts` to include:

```ts
import { systemReadinessHandlers } from "@/features/system-readiness/mocks/system-readiness-handlers";

export const handlers = [
  // existing handlers
  ...systemReadinessHandlers,
];
```

Preserve existing handler imports and array entries.

- [ ] **Step 5: Add components**

Create `apps/web/features/system-readiness/components/system-status-badge.tsx`:

```tsx
export function SystemStatusBadge({ status }: { status: "ok" | "warning" | "error" }) {
  const label = status === "ok" ? "Healthy" : status === "warning" ? "Warning" : "Needs attention";
  const className =
    status === "ok"
      ? "border-emerald-700 text-emerald-700"
      : status === "warning"
        ? "border-amber-700 text-amber-700"
        : "border-red-700 text-red-700";

  return <span className={`rounded-md border px-2 py-1 text-xs font-medium ${className}`}>{label}</span>;
}
```

Create `apps/web/features/system-readiness/components/system-check-list.tsx`:

```tsx
import type { SystemStatusCheck } from "@cognify/api-client";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemCheckList({ checks }: { checks: SystemStatusCheck[] }) {
  return (
    <section aria-labelledby="system-checks-heading" className="space-y-3">
      <h2 id="system-checks-heading" className="text-base font-semibold">
        Checks
      </h2>
      <div className="divide-y rounded-md border">
        {checks.map((check) => (
          <div key={check.id} className="grid gap-2 p-4 md:grid-cols-[12rem_1fr_auto] md:items-center">
            <div className="font-medium">{check.label}</div>
            <div className="text-sm text-muted-foreground">
              <div>{check.message}</div>
              {check.remediation ? <div className="mt-1">{check.remediation}</div> : null}
            </div>
            <SystemStatusBadge status={check.status} />
          </div>
        ))}
      </div>
    </section>
  );
}
```

Create `apps/web/features/system-readiness/components/demo-dataset-summary.tsx`:

```tsx
import type { SystemStatusDemoSummary } from "@cognify/api-client";

const labels: Array<[keyof SystemStatusDemoSummary["counts"], string]> = [
  ["users", "Users"],
  ["requisitions", "Requisitions"],
  ["vendors", "Vendors"],
  ["rfqs", "RFQs"],
  ["quotations", "Quotations"],
  ["approvalTasks", "Approval tasks"],
  ["awards", "Awards"],
];

export function DemoDatasetSummary({ demo }: { demo: SystemStatusDemoSummary }) {
  return (
    <section aria-labelledby="demo-dataset-heading" className="space-y-3">
      <h2 id="demo-dataset-heading" className="text-base font-semibold">
        Demo dataset
      </h2>
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {labels.map(([key, label]) => (
          <div key={key} className="rounded-md border p-4">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-2xl font-semibold">{demo.counts[key]}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
```

Create `system-status-summary.tsx` and `system-status-page.tsx` with this page behavior:

```tsx
"use client";

import { DemoDatasetSummary } from "../components/demo-dataset-summary";
import { SystemCheckList } from "../components/system-check-list";
import { SystemStatusBadge } from "../components/system-status-badge";
import { useSystemStatus } from "../hooks/use-system-status";

export function SystemStatusPage() {
  const query = useSystemStatus();
  const status = query.data?.data;

  if (query.isLoading) {
    return <div role="status">Loading system status...</div>;
  }

  if (query.isError || !status) {
    return <div role="alert">System status could not be loaded.</div>;
  }

  return (
    <div className="mx-auto max-w-6xl space-y-6">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-normal">System Status</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {status.service} · {status.environment} · v{status.version}
          </p>
        </div>
        <SystemStatusBadge status={status.status} />
      </div>
      <SystemCheckList checks={status.checks} />
      <DemoDatasetSummary demo={status.demo} />
    </div>
  );
}
```

Create route `apps/web/app/(workspace)/system/page.tsx`:

```tsx
import { SystemStatusPage } from "@/features/system-readiness/workflows/system-status-page";

export default function Page() {
  return <SystemStatusPage />;
}
```

- [ ] **Step 6: Run web page test**

Run:

```bash
pnpm --filter @cognify/web test -- system-status-page.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit web readiness page**

Run:

```bash
git add apps/web/features/system-readiness apps/web/app/'(workspace)'/system/page.tsx apps/web/tests/msw/handlers.ts
git commit -m "feat(web): add system readiness page"
```

---

## Task 7: Add Footer Indicator And Admin Navigation

**Files:**
- Modify: `apps/web/components/shell/shell-footer.tsx`
- Modify: `apps/web/components/shell/app-shell.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Create: `apps/web/features/system-readiness/tests/system-status-footer.test.tsx`

- [ ] **Step 1: Write failing footer test**

Create `apps/web/features/system-readiness/tests/system-status-footer.test.tsx`:

```tsx
import { render, screen } from "@testing-library/react";
import { ShellFooter } from "@/components/shell/shell-footer";

describe("ShellFooter readiness indicator", () => {
  it("shows local demo readiness for admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus readinessStatus="ok" />);

    expect(screen.getByText("Cognify · Local demo · Healthy")).toBeInTheDocument();
    expect(screen.getByText("Workspace: Acme Procurement")).toBeInTheDocument();
  });

  it("hides detailed readiness for non-admin users", () => {
    render(<ShellFooter tenantName="Acme Procurement" canViewSystemStatus={false} readinessStatus="error" />);

    expect(screen.getByText("Cognify")).toBeInTheDocument();
    expect(screen.queryByText(/Needs attention/)).not.toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run failing footer test**

Run:

```bash
pnpm --filter @cognify/web test -- system-status-footer.test.tsx
```

Expected: FAIL because footer props do not exist.

- [ ] **Step 3: Update ShellFooter**

Replace `apps/web/components/shell/shell-footer.tsx` with:

```tsx
export interface ShellFooterProps {
  tenantName: string;
  canViewSystemStatus?: boolean;
  readinessStatus?: "ok" | "warning" | "error";
}

export function ShellFooter({ tenantName, canViewSystemStatus = false, readinessStatus }: ShellFooterProps) {
  const workspaceLabel = tenantName.trim() || "Operational workspace";
  const readinessLabel =
    readinessStatus === "ok" ? "Healthy" : readinessStatus === "warning" ? "Warning" : "Needs attention";
  const productLabel = canViewSystemStatus && readinessStatus ? `Cognify · Local demo · ${readinessLabel}` : "Cognify";

  return (
    <footer className="border-t px-4 py-3 text-xs text-muted-foreground md:px-6">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span>{productLabel}</span>
        <span className="truncate" title={`Workspace: ${workspaceLabel}`}>
          Workspace: {workspaceLabel}
        </span>
      </div>
    </footer>
  );
}
```

- [ ] **Step 4: Update AppShell to fetch status only for admins**

Modify `apps/web/components/shell/app-shell.tsx`:

```tsx
import { useSystemStatus } from "@/features/system-readiness/hooks/use-system-status";
```

Inside `AppShell`, add:

```tsx
const canViewSystemStatus = Boolean(permissions?.canAccessAdmin);
const systemStatusQuery = useSystemStatus(canViewSystemStatus);
```

Replace footer usage:

```tsx
<ShellFooter
  tenantName={tenantName}
  canViewSystemStatus={canViewSystemStatus}
  readinessStatus={systemStatusQuery.data?.data.status}
/>
```

- [ ] **Step 5: Add system nav item**

Modify `apps/web/components/shell/shell-route-config.ts`:

```tsx
import { Activity } from "lucide-react";
```

In the `manage` group, add before Account:

```tsx
{
  label: "System",
  href: "/system",
  icon: Activity,
  implemented: true,
  permission: canUseAudit,
},
```

In `getBreadcrumbs`, add:

```tsx
if (normalizedPathname === "/system") {
  return [{ label: "System" }];
}
```

- [ ] **Step 6: Run shell tests**

Run:

```bash
pnpm --filter @cognify/web test -- system-status-footer.test.tsx app-shell.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit footer and navigation**

Run:

```bash
git add apps/web/components/shell apps/web/features/system-readiness/tests/system-status-footer.test.tsx
git commit -m "feat(web): show system readiness in shell"
```

---

## Task 8: Update Frontend Search For Preview Result Types

**Files:**
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
- Modify: `apps/web/features/search/components/command-palette.tsx`
- Modify: `apps/web/features/search/tests/command-palette.test.tsx`

- [ ] **Step 1: Add failing command palette preview result test**

Append to `apps/web/features/search/tests/command-palette.test.tsx`:

```tsx
it("renders roadmap preview search results", async () => {
  window.localStorage.setItem("cognify.activeTenantId", "tenant-1");

  render(<CommandPalette />);

  await userEvent.keyboard("{Meta>}k{/Meta}");
  await userEvent.type(screen.getByRole("combobox"), "Atlas");

  expect(await screen.findByText("Atlas Office Supplies")).toBeInTheDocument();
  expect(await screen.findByText("Office supplies")).toBeInTheDocument();
});
```

- [ ] **Step 2: Run failing search UI test**

Run:

```bash
pnpm --filter @cognify/web test -- command-palette.test.tsx
```

Expected: FAIL if fixtures or icon/type mapping do not cover preview records.

- [ ] **Step 3: Add preview fixture**

Modify `apps/web/features/search/mocks/search-fixtures.ts` and add:

```ts
{
  type: "vendor",
  id: "vendor-1",
  title: "Atlas Office Supplies",
  subtitle: "Office supplies",
  status: "preferred",
  href: "/system",
  updatedAt: "2026-05-15T00:00:00Z",
}
```

Ensure the handler returns this result for query `Atlas`.

- [ ] **Step 4: Map preview icons in CommandPalette**

In `apps/web/features/search/components/command-palette.tsx`, map preview result types to existing lucide icons:

```tsx
const resultIcons = {
  requisition: FileText,
  vendor: Building2,
  project: FolderKanban,
  rfq: ReceiptText,
  quotation: ReceiptText,
  award: CheckCircle2,
};
```

Use `resultIcons[result.type as keyof typeof resultIcons] ?? FileSearch` where the component currently chooses an icon for API results.

- [ ] **Step 5: Run command palette tests**

Run:

```bash
pnpm --filter @cognify/web test -- command-palette.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Commit preview search UI**

Run:

```bash
git add apps/web/features/search
git commit -m "feat(web): show demo preview search results"
```

---

## Task 9: Final Verification And Documentation Notes

**Files:**
- Modify: `docs/05-runbooks/local-development.md` if it exists and lacks demo seed instructions.
- Modify: `docs/CURRENT_STATE.md` if present and used for current state tracking.

- [ ] **Step 1: Run API tests**

Run:

```bash
cd apps/api
php artisan test
```

Expected: PASS.

- [ ] **Step 2: Run API route listing**

Run:

```bash
cd apps/api
php artisan route:list --path=api
```

Expected: output includes:

```txt
GET|HEAD api/system/status
```

- [ ] **Step 3: Run API contract checks**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: PASS and no unreviewed generated drift after generation.

- [ ] **Step 4: Run web checks**

Run:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Expected: PASS.

- [ ] **Step 5: Run root checks**

Run:

```bash
pnpm typecheck
pnpm test
```

Expected: PASS.

- [ ] **Step 6: Add local development documentation**

Modify `docs/05-runbooks/local-development.md` and add this section:

````md
## Local Demo Data

Run the local demo seed after migrations:

```bash
cd apps/api
php artisan migrate:fresh --seed
```

The seed creates deterministic Cognify demo tenants, users, requisitions, vendors, projects, RFQs, quotations, approvals, awards, audit events, notifications, and sample attachment files. Admin users can inspect readiness at `/system`.
````

- [ ] **Step 7: Commit docs and final verification fixes**

Run:

```bash
git add docs apps/api apps/web packages/api-client
git commit -m "docs: document local demo readiness"
```

---

## Plan Self-Review

Spec coverage:

- Demo snapshot data is covered by Tasks 1 and 2.
- Lightweight roadmap-preview records are covered by Task 1.
- Realistic seeded relationships, attachments, audit, and notifications are covered by Task 2.
- Tenant-scoped preview search is covered by Task 3 and Task 8.
- Detailed readiness API is covered by Task 4.
- OpenAPI/generated client flow is covered by Task 5.
- Frontend system page is covered by Task 6.
- Footer indicator and admin navigation are covered by Task 7.
- Verification and local documentation are covered by Task 9.

Placeholder scan:

- This plan intentionally contains no `TBD`, `TODO`, incomplete section, or open implementation choice.
- Future P1/P2 workflow behavior remains out of scope by design.

Type consistency:

- Backend readiness status values use `ok`, `warning`, and `error` across API, OpenAPI, and frontend.
- Demo count keys use `approvalTasks` consistently in API response and frontend summary.
- Route paths use `/api/system/status` for backend and `/system` for the web page.

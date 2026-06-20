# Payment Status Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend P1-47 AP payment handoffs with post-export payment lifecycle tracking: scheduled, paid (full and with variance), failed, voided. Add payment allocation junction, CSV/JSON import staging with preview and reconcile, invoice payment status derivation, and frontend payment status queue plus import pages.

**Architecture:** New `Domains/Payments` backend domain for post-execution payment behavior. `ApPaymentHandoff` gains post-export states. `ApPaymentAllocation` junction links handoffs to invoices with `allocated_amount` + optional `settlement_amount`/`settlement_currency`. `ApPaymentImport` staging table with parse, match, preview, reconcile flow. Invoice `payment_status` derives from handoff state + allocation sums. `payment_failed`/`payment_voided` are audit-only (no transient column writes). `NULLS NOT DISTINCT` on allocation unique index.

**Tech Stack:** PHP 8.3 / Laravel, PostgreSQL 15+, Next.js 15 / React 19, TanStack Query, shadcn/ui, Orval-generated TypeScript client.

**Reference pattern:** P1-47 `CancelApPaymentHandoff` action (DB::transaction, lockForUpdate, assertLockVersion, forceFill, auditRecorder->record) and `ApPaymentHandoffController` (findTenantHandoff, authorize, action->handle, resource response).

---

## File Structure Map

### Backend -- Create (47 files)

**Migrations (3):**
- `apps/api/database/migrations/2026_06_19_000001_extend_ap_payment_handoffs_for_post_export.php`
- `apps/api/database/migrations/2026_06_19_000002_create_ap_payment_allocations.php`
- `apps/api/database/migrations/2026_06_19_000003_create_ap_payment_imports.php`

**States / Enums (5):**
- `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php` -- extend
- `apps/api/Domains/AccountsPayable/States/ApPaymentHandoffStatus.php` -- extend
- `apps/api/Domains/Payments/States/ApPaymentFailureCode.php`
- `apps/api/Domains/Payments/States/ApPaymentImportStatus.php`
- `apps/api/Domains/Payments/States/ApPaymentImportTargetStatus.php`

**Models (2):**
- `apps/api/Domains/Payments/Models/ApPaymentAllocation.php`
- `apps/api/Domains/Payments/Models/ApPaymentImport.php`

**Actions (11):**
- `apps/api/Domains/Payments/Actions/ScheduleApPaymentHandoff.php`
- `apps/api/Domains/Payments/Actions/AddApPaymentAllocation.php`
- `apps/api/Domains/Payments/Actions/MarkApPaymentHandoffPaid.php`
- `apps/api/Domains/Payments/Actions/CloseApPaymentHandoffWithVariance.php`
- `apps/api/Domains/Payments/Actions/MarkApPaymentHandoffFailed.php`
- `apps/api/Domains/Payments/Actions/VoidApPaymentHandoff.php`
- `apps/api/Domains/Payments/Actions/RescheduleFailedApPaymentHandoff.php`
- `apps/api/Domains/Payments/Actions/ParsePaymentImportFile.php`
- `apps/api/Domains/Payments/Actions/MatchPaymentImportRow.php`
- `apps/api/Domains/Payments/Actions/ReconcilePaymentImportBatch.php`
- `apps/api/Domains/Payments/Actions/DiscardPaymentImportRow.php`

**Data DTOs (3):**
- `apps/api/Domains/Payments/Data/PaymentImportRowData.php`
- `apps/api/Domains/Payments/Data/PaymentImportPreviewData.php`
- `apps/api/Domains/Payments/Data/ReconciliationResultData.php`

**Support (4):**
- `apps/api/Domains/Payments/Support/PaymentAllocationSumCalculator.php`
- `apps/api/Domains/Payments/Support/PaymentImportCsvParser.php`
- `apps/api/Domains/Payments/Support/PaymentImportJsonParser.php`
- `apps/api/Domains/Payments/Support/PaymentImportBatchIdGenerator.php`

**Policies (2):**
- `apps/api/Domains/Payments/Policies/ApPaymentAllocationPolicy.php`
- `apps/api/Domains/Payments/Policies/ApPaymentImportPolicy.php`

**Form Requests (10):**
- `apps/api/Domains/Payments/Http/Requests/ScheduleApPaymentHandoffRequest.php`
- `apps/api/Domains/Payments/Http/Requests/AddApPaymentAllocationRequest.php`
- `apps/api/Domains/Payments/Http/Requests/MarkApPaymentHandoffPaidRequest.php`
- `apps/api/Domains/Payments/Http/Requests/CloseApPaymentHandoffWithVarianceRequest.php`
- `apps/api/Domains/Payments/Http/Requests/MarkApPaymentHandoffFailedRequest.php`
- `apps/api/Domains/Payments/Http/Requests/VoidApPaymentHandoffRequest.php`
- `apps/api/Domains/Payments/Http/Requests/RescheduleApPaymentHandoffRequest.php`
- `apps/api/Domains/Payments/Http/Requests/UploadPaymentImportRequest.php`
- `apps/api/Domains/Payments/Http/Requests/UpdatePaymentImportRowRequest.php`
- `apps/api/Domains/Payments/Http/Requests/ReconcilePaymentImportBatchRequest.php`

**Resources (3):**
- `apps/api/Domains/Payments/Http/Resources/ApPaymentAllocationResource.php`
- `apps/api/Domains/Payments/Http/Resources/ApPaymentImportResource.php`
- `apps/api/Domains/Payments/Http/Resources/ApPaymentImportBatchResource.php`

**Controllers (3):**
- `apps/api/Domains/Payments/Http/Controllers/ApPaymentStatusController.php`
- `apps/api/Domains/Payments/Http/Controllers/ApPaymentAllocationController.php`
- `apps/api/Domains/Payments/Http/Controllers/ApPaymentImportController.php`

**Tests (3):**
- `apps/api/tests/Feature/ApPaymentStatusApiTest.php`
- `apps/api/tests/Feature/ApPaymentAllocationApiTest.php`
- `apps/api/tests/Feature/ApPaymentImportApiTest.php`

### Backend -- Modify (5 files)

- `apps/api/Domains/AccountsPayable/Models/ApPaymentHandoff.php` -- extend fillable, casts, relationships
- `apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php` -- add schedule, addAllocation, markPaid, closeWithVariance, markFailed, void, reschedule
- `apps/api/Domains/AccountsPayable/Http/Resources/ApPaymentHandoffResource.php` -- add post-export fields, permissions, allocations
- `apps/api/app/Providers/AppServiceProvider.php` -- register AuditSubject types
- `apps/api/routes/api.php` -- add payment status, allocation, import routes

### OpenAPI (1 file)

- `apps/api/storage/openapi/openapi.json` -- add 15+ schemas, 13+ paths

### Frontend -- Create (24 files)

**Pages (2):**
- `apps/web/app/(workspace)/accounts-payable/payment-status/page.tsx`
- `apps/web/app/(workspace)/accounts-payable/payment-import/page.tsx`

**Workflows (1):**
- `apps/web/features/accounts-payable/workflows/payment-status-queue-page.tsx`

**Components (8+):**
- `apps/web/features/accounts-payable/components/payment-status-badge.tsx` -- extend
- `apps/web/features/accounts-payable/components/handoff-schedule-panel.tsx`
- `apps/web/features/accounts-payable/components/handoff-allocation-panel.tsx`
- `apps/web/features/accounts-payable/components/handoff-payment-actions-panel.tsx`
- `apps/web/features/accounts-payable/components/handoff-failure-detail.tsx`
- `apps/web/features/accounts-payable/components/handoff-variance-detail.tsx`
- `apps/web/features/accounts-payable/components/payment-import-upload-panel.tsx`
- `apps/web/features/accounts-payable/components/payment-import-preview-panel.tsx`
- `apps/web/features/accounts-payable/components/payment-import-reconciliation-summary.tsx`

**Hooks (3):**
- `apps/web/features/accounts-payable/hooks/use-ap-payment-handoff-status.ts`
- `apps/web/features/accounts-payable/hooks/use-ap-payment-allocations.ts`
- `apps/web/features/accounts-payable/hooks/use-ap-payment-import.ts`

**API helpers (3):**
- `apps/web/features/accounts-payable/api/accounts-payable-payment-status-api.ts`
- `apps/web/features/accounts-payable/api/accounts-payable-payment-allocation-api.ts`
- `apps/web/features/accounts-payable/api/accounts-payable-payment-import-api.ts`

**MSW mocks (6):**
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-fixtures.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-fixtures.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-fixtures.ts`

**Tests (1):**
- `apps/web/features/accounts-payable/__tests__/payment-status-queue-page.test.tsx`

### Frontend -- Modify (3 files)

- `apps/web/components/default-shell/navigation.tsx` -- add Payment status, Payment import nav items
- `apps/web/tests/msw/handlers.ts` -- register new handlers
- `apps/web/tests/setup.ts` -- register reset functions in afterEach

### Seeder -- Modify (1 file)

- `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php` -- add `seedPaymentStatuses()` method with 7 scenarios

---

## Task 1: Extend ap_payment_handoffs Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_19_000001_extend_ap_payment_handoffs_for_post_export.php`

- [ ] **Step 1: Create migration file**

Run: `php artisan make:migration extend_ap_payment_handoffs_for_post_export --table=ap_payment_handoffs`

- [ ] **Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ap_payment_handoffs', function (Blueprint $table): void {
            $table->foreignId('scheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('last_export_format');
            $table->timestamp('scheduled_at')->nullable()->after('scheduled_by_user_id');
            $table->date('scheduled_for_date')->nullable()->after('scheduled_at');
            $table->string('payment_reference', 255)->nullable()->after('scheduled_for_date');

            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('payment_reference');
            $table->timestamp('paid_at')->nullable()->after('paid_by_user_id');
            $table->string('remittance_reference', 255)->nullable()->after('paid_at');
            $table->timestamp('remittance_advice_sent_at')->nullable()->after('remittance_reference');

            $table->foreignId('failed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('remittance_advice_sent_at');
            $table->timestamp('failed_at')->nullable()->after('failed_by_user_id');
            $table->string('failure_code', 50)->nullable()->after('failed_at');
            $table->text('failure_reason')->nullable()->after('failure_code');

            $table->foreignId('voided_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('failure_reason');
            $table->timestamp('voided_at')->nullable()->after('voided_by_user_id');
            $table->text('void_reason')->nullable()->after('voided_at');

            $table->decimal('variance_amount', 20, 4)->nullable()->after('void_reason');
            $table->text('variance_reason')->nullable()->after('variance_amount');
            $table->foreignId('variance_closed_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('variance_reason');
            $table->timestamp('variance_closed_at')->nullable()->after('variance_closed_by_user_id');

            $table->index(['tenant_id', 'status', 'scheduled_at'], 'aph_tenant_status_scheduled_idx');
            $table->index(['tenant_id', 'status', 'paid_at'], 'aph_tenant_status_paid_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ap_payment_handoffs', function (Blueprint $table): void {
            $table->dropIndex('aph_tenant_status_scheduled_idx');
            $table->dropIndex('aph_tenant_status_paid_idx');
            $table->dropColumn([
                'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference',
                'paid_by_user_id', 'paid_at', 'remittance_reference', 'remittance_advice_sent_at',
                'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason',
                'voided_by_user_id', 'voided_at', 'void_reason',
                'variance_amount', 'variance_reason', 'variance_closed_by_user_id', 'variance_closed_at',
            ]);
        });
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_19_000001_extend_ap_payment_handoffs_for_post_export.php
git commit -m "feat(p1-48): extend ap_payment_handoffs with post-export lifecycle columns"
```

---

## Task 2: Create ap_payment_allocations Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_19_000002_create_ap_payment_allocations.php`

- [ ] **Step 1: Create migration file**

Run: `php artisan make:migration create_ap_payment_allocations --create=ap_payment_allocations`

- [ ] **Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_allocations', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('ap_payment_handoff_id', 36);
            $table->foreign('ap_payment_handoff_id')->references('id')->on('ap_payment_handoffs')->onDelete('cascade');
            $table->char('supplier_invoice_id', 36);
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->onDelete('restrict');
            $table->decimal('allocated_amount', 20, 4);
            $table->date('allocation_date');
            $table->string('payment_reference', 255)->nullable();
            $table->decimal('settlement_amount', 20, 4)->nullable();
            $table->string('settlement_currency', 3)->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            // NULLS NOT DISTINCT prevents duplicate rows when payment_reference is omitted by bank files.
            // PostgreSQL 15+ supports this directly via raw SQL.
            $table->unique(
                ['ap_payment_handoff_id', 'supplier_invoice_id', 'allocation_date', 'payment_reference'],
                'ap_alloc_unique_handoff_invoice_date_ref',
            )->whereNotNull('payment_reference');

            $table->index(['tenant_id', 'supplier_invoice_id'], 'ap_alloc_tenant_invoice_idx');
            $table->index(['tenant_id', 'ap_payment_handoff_id'], 'ap_alloc_tenant_handoff_idx');
        });

        // PostgreSQL 15+ NULLS NOT DISTINCT index
        DB::statement('
            CREATE UNIQUE INDEX ap_alloc_unique_handoff_invoice_date_ref_nulls_not_distinct
            ON ap_payment_allocations (ap_payment_handoff_id, supplier_invoice_id, allocation_date, payment_reference)
            NULLS NOT DISTINCT
            WHERE voided_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_allocations');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_19_000002_create_ap_payment_allocations.php
git commit -m "feat(p1-48): create ap_payment_allocations with NULLS NOT DISTINCT unique index"
```

---

## Task 3: Create ap_payment_imports Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_19_000003_create_ap_payment_imports.php`

- [ ] **Step 1: Create migration file**

Run: `php artisan make:migration create_ap_payment_imports --create=ap_payment_imports`

- [ ] **Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ap_payment_imports', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('batch_id', 36);
            $table->integer('row_index');
            $table->string('handoff_number', 50)->nullable();
            $table->string('invoice_number', 255)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->decimal('allocated_amount', 20, 4)->nullable();
            $table->boolean('mark_full')->default(false);
            $table->decimal('settlement_amount', 20, 4)->nullable();
            $table->string('settlement_currency', 3)->nullable();
            $table->date('paid_at')->nullable();
            $table->string('settlement_method', 50)->nullable();
            $table->string('target_status', 50);
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('void_reason')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('match_error')->nullable();
            $table->char('matched_handoff_id', 36)->nullable();
            $table->foreign('matched_handoff_id')->references('id')->on('ap_payment_handoffs')->nullOnDelete();
            $table->char('matched_invoice_id', 36)->nullable();
            $table->foreign('matched_invoice_id')->references('id')->on('supplier_invoices')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imported_by_user_id')->constrained('users');
            $table->timestamp('imported_at');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'batch_id', 'row_index'], 'ap_imp_tenant_batch_row_idx');
            $table->index(['tenant_id', 'status'], 'ap_imp_tenant_status_idx');
            $table->index(['tenant_id', 'matched_handoff_id'], 'ap_imp_tenant_handoff_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ap_payment_imports');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_19_000003_create_ap_payment_imports.php
git commit -m "feat(p1-48): create ap_payment_imports staging table"
```

---

## Task 4: Extend SupplierInvoicePaymentStatus Enum

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php`

- [ ] **Step 1: Write failing test first**

Create `apps/api/tests/Feature/SupplierInvoicePaymentStatusEnumTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Tests\TestCase;

class SupplierInvoicePaymentStatusEnumTest extends TestCase
{
    public function test_payment_scheduled_case_exists(): void
    {
        $this->assertSame('payment_scheduled', SupplierInvoicePaymentStatus::PaymentScheduled->value);
    }

    public function test_partially_paid_case_exists(): void
    {
        $this->assertSame('partially_paid', SupplierInvoicePaymentStatus::PartiallyPaid->value);
    }

    public function test_paid_case_exists(): void
    {
        $this->assertSame('paid', SupplierInvoicePaymentStatus::Paid->value);
    }

    public function test_payment_failed_is_not_a_column_case(): void
    {
        $cases = array_map(fn ($c) => $c->value, SupplierInvoicePaymentStatus::cases());
        $this->assertNotContains('payment_failed', $cases);
        $this->assertNotContains('payment_voided', $cases);
    }
}
```

Run: `cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusEnumTest`
Expected: FAIL with "Case PaymentScheduled not found".

- [ ] **Step 2: Extend enum**

Replace the full file content:

```php
<?php

namespace Domains\AccountsPayable\States;

enum SupplierInvoicePaymentStatus: string
{
    case PaymentEligible = 'payment_eligible';
    case OnHold = 'on_hold';
    case PaymentReady = 'payment_ready';
    case HandoffExported = 'handoff_exported';
    case PaymentScheduled = 'payment_scheduled';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::PaymentEligible => 'Payment eligible',
            self::OnHold => 'On hold',
            self::PaymentReady => 'Payment ready',
            self::HandoffExported => 'Exported',
            self::PaymentScheduled => 'Scheduled',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
        };
    }

    public function isEligibleForHandoff(): bool
    {
        return $this === self::PaymentEligible;
    }

    public function isTerminal(): bool
    {
        return $this === self::HandoffExported;
    }
}
```

- [ ] **Step 3: Run test to verify pass**

Run: `cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusEnumTest`
Expected: PASS (4 assertions).

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php apps/api/tests/Feature/SupplierInvoicePaymentStatusEnumTest.php
git commit -m "feat(p1-48): extend SupplierInvoicePaymentStatus with payment_scheduled, partially_paid, paid"
```

---

## Task 5: Extend ApPaymentHandoffStatus Enum

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/States/ApPaymentHandoffStatus.php`

- [ ] **Step 1: Write failing test first**

Create `apps/api/tests/Feature/ApPaymentHandoffStatusEnumTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Tests\TestCase;

class ApPaymentHandoffStatusEnumTest extends TestCase
{
    public function test_p1_48_cases_exist(): void
    {
        $this->assertSame('scheduled', ApPaymentHandoffStatus::Scheduled->value);
        $this->assertSame('paid', ApPaymentHandoffStatus::Paid->value);
        $this->assertSame('failed', ApPaymentHandoffStatus::Failed->value);
        $this->assertSame('voided', ApPaymentHandoffStatus::Voided->value);
    }

    public function test_transition_table(): void
    {
        $this->assertTrue(ApPaymentHandoffStatus::Exported->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Paid));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Failed));
        $this->assertTrue(ApPaymentHandoffStatus::Scheduled->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertTrue(ApPaymentHandoffStatus::Paid->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertTrue(ApPaymentHandoffStatus::Failed->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertTrue(ApPaymentHandoffStatus::Failed->canTransitionTo(ApPaymentHandoffStatus::Voided));
        $this->assertFalse(ApPaymentHandoffStatus::Voided->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
        $this->assertFalse(ApPaymentHandoffStatus::Cancelled->canTransitionTo(ApPaymentHandoffStatus::Scheduled));
    }
}
```

Run: `cd apps/api && php artisan test --filter=ApPaymentHandoffStatusEnumTest`
Expected: FAIL with "Case Scheduled not found".

- [ ] **Step 2: Extend enum**

Replace full file content:

```php
<?php

namespace Domains\AccountsPayable\States;

enum ApPaymentHandoffStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Exported = 'exported';
    case Cancelled = 'cancelled';
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Exported => 'Exported',
            self::Cancelled => 'Cancelled',
            self::Scheduled => 'Scheduled',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Exported, self::Cancelled, self::Paid, self::Failed, self::Voided], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Ready, self::Cancelled], true),
            self::Ready => in_array($target, [self::Exported, self::Cancelled], true),
            self::Exported => $target === self::Scheduled,
            self::Scheduled => in_array($target, [self::Paid, self::Failed, self::Voided], true),
            self::Paid => $target === self::Voided,
            self::Failed => in_array($target, [self::Scheduled, self::Voided], true),
            self::Voided => false,
            self::Cancelled => false,
        };
    }
}
```

- [ ] **Step 3: Run test to verify pass**

Run: `cd apps/api && php artisan test --filter=ApPaymentHandoffStatusEnumTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/AccountsPayable/States/ApPaymentHandoffStatus.php apps/api/tests/Feature/ApPaymentHandoffStatusEnumTest.php
git commit -m "feat(p1-48): extend ApPaymentHandoffStatus with scheduled, paid, failed, voided and transition rules"
```

---

## Task 6: New Enums -- ApPaymentFailureCode, ApPaymentImportStatus, ApPaymentImportTargetStatus

**Files:**
- Create: `apps/api/Domains/Payments/States/ApPaymentFailureCode.php`
- Create: `apps/api/Domains/Payments/States/ApPaymentImportStatus.php`
- Create: `apps/api/Domains/Payments/States/ApPaymentImportTargetStatus.php`

- [ ] **Step 1: Create enum files**

`ApPaymentFailureCode.php`:
```php
<?php

namespace Domains\Payments\States;

enum ApPaymentFailureCode: string
{
    case BankRejected = 'bank_rejected';
    case InsufficientFunds = 'insufficient_funds';
    case VendorBlocked = 'vendor_blocked';
    case SystemError = 'system_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankRejected => 'Bank rejected',
            self::InsufficientFunds => 'Insufficient funds',
            self::VendorBlocked => 'Vendor blocked',
            self::SystemError => 'System error',
            self::Other => 'Other',
        };
    }
}
```

`ApPaymentImportStatus.php`:
```php
<?php

namespace Domains\Payments\States;

enum ApPaymentImportStatus: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Failed = 'failed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reconciled => 'Reconciled',
            self::Failed => 'Failed',
            self::Discarded => 'Discarded',
        };
    }
}
```

`ApPaymentImportTargetStatus.php`:
```php
<?php

namespace Domains\Payments\States;

enum ApPaymentImportTargetStatus: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }
}
```

- [ ] **Step 2: Write minimal enum test**

Create `apps/api/tests/Feature/ApPaymentEnumsTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\Payments\States\ApPaymentFailureCode;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Tests\TestCase;

class ApPaymentEnumsTest extends TestCase
{
    public function test_failure_code_cases(): void
    {
        $this->assertSame('bank_rejected', ApPaymentFailureCode::BankRejected->value);
        $this->assertSame('other', ApPaymentFailureCode::Other->value);
    }

    public function test_import_status_cases(): void
    {
        $this->assertSame('pending', ApPaymentImportStatus::Pending->value);
        $this->assertSame('reconciled', ApPaymentImportStatus::Reconciled->value);
    }

    public function test_import_target_status_cases(): void
    {
        $this->assertSame('paid', ApPaymentImportTargetStatus::Paid->value);
        $this->assertSame('voided', ApPaymentImportTargetStatus::Voided->value);
    }
}
```

Run: `cd apps/api && php artisan test --filter=ApPaymentEnumsTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Payments/States/ apps/api/tests/Feature/ApPaymentEnumsTest.php
git commit -m "feat(p1-48): add ApPaymentFailureCode, ApPaymentImportStatus, ApPaymentImportTargetStatus enums"
```

---

## Task 7: ApPaymentAllocation Model

**Files:**
- Create: `apps/api/Domains/Payments/Models/ApPaymentAllocation.php`

- [ ] **Step 1: Create model file**

```php
<?php

namespace Domains\Payments\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApPaymentAllocation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ap_payment_allocations';

    protected $fillable = [
        'tenant_id',
        'ap_payment_handoff_id',
        'supplier_invoice_id',
        'allocated_amount',
        'allocation_date',
        'payment_reference',
        'settlement_amount',
        'settlement_currency',
        'voided_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
            'allocation_date' => 'date',
            'settlement_amount' => 'decimal:4',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(ApPaymentHandoff::class, 'ap_payment_handoff_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            $handoff = $allocation->handoff;
            $invoice = $allocation->invoice;

            if ($handoff !== null && $invoice !== null && (int) $handoff->tenant_id !== (int) $invoice->tenant_id) {
                throw new \InvalidArgumentException('Handoff and invoice must belong to the same tenant.');
            }
        });
    }
}
```

- [ ] **Step 2: Write minimal model test**

Add to `apps/api/tests/Feature/ApPaymentAllocationApiTest.php` (create skeleton):

```php
<?php

namespace Tests\Feature;

use Domains\Payments\Models\ApPaymentAllocation;
use Tests\TestCase;

class ApPaymentAllocationApiTest extends TestCase
{
    public function test_model_exists(): void
    {
        $this->assertTrue(class_exists(ApPaymentAllocation::class));
    }
}
```

Run: `cd apps/api && php artisan test --filter=ApPaymentAllocationApiTest::test_model_exists`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Payments/Models/ApPaymentAllocation.php apps/api/tests/Feature/ApPaymentAllocationApiTest.php
git commit -m "feat(p1-48): add ApPaymentAllocation model"
```

---

## Task 8: ApPaymentImport Model

**Files:**
- Create: `apps/api/Domains/Payments/Models/ApPaymentImport.php`

- [ ] **Step 1: Create model file**

```php
<?php

namespace Domains\Payments\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApPaymentImport extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ap_payment_imports';

    protected $fillable = [
        'tenant_id', 'batch_id', 'row_index', 'handoff_number', 'invoice_number',
        'payment_reference', 'allocated_amount', 'mark_full', 'settlement_amount',
        'settlement_currency', 'paid_at', 'settlement_method', 'target_status',
        'failure_code', 'failure_reason', 'void_reason', 'status', 'match_error',
        'matched_handoff_id', 'matched_invoice_id', 'reconciled_at',
        'reconciled_by_user_id', 'imported_by_user_id', 'imported_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'mark_full' => 'boolean',
            'allocated_amount' => 'decimal:4',
            'settlement_amount' => 'decimal:4',
            'paid_at' => 'date',
            'reconciled_at' => 'datetime',
            'imported_at' => 'datetime',
            'lock_version' => 'integer',
            'status' => ApPaymentImportStatus::class,
            'target_status' => ApPaymentImportTargetStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function matchedHandoff(): BelongsTo
    {
        return $this->belongsTo(ApPaymentHandoff::class, 'matched_handoff_id');
    }

    public function matchedInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'matched_invoice_id');
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }
}
```

- [ ] **Step 2: Write minimal model test**

Add to `apps/api/tests/Feature/ApPaymentImportApiTest.php` (create skeleton):

```php
<?php

namespace Tests\Feature;

use Domains\Payments\Models\ApPaymentImport;
use Tests\TestCase;

class ApPaymentImportApiTest extends TestCase
{
    public function test_model_exists(): void
    {
        $this->assertTrue(class_exists(ApPaymentImport::class));
    }
}
```

Run: `cd apps/api && php artisan test --filter=ApPaymentImportApiTest::test_model_exists`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Payments/Models/ApPaymentImport.php apps/api/tests/Feature/ApPaymentImportApiTest.php
git commit -m "feat(p1-48): add ApPaymentImport model"
```

---

## Task 9: Extend ApPaymentHandoff Model

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/Models/ApPaymentHandoff.php`

- [ ] **Step 1: Add new fillable, casts, and relationships**

Modify the `$fillable` array to include new columns:

```php
    protected $fillable = [
        'tenant_id', 'number', 'status', 'effective_payment_date', 'notes',
        'currency', 'total_amount', 'remittance_reference', 'created_by_user_id',
        'ready_by_user_id', 'ready_at', 'cancelled_by_user_id', 'cancelled_at',
        'cancelled_reason', 'last_exported_by_user_id', 'last_exported_at',
        'last_export_format', 'snapshot', 'readiness_warnings', 'lock_version',
        'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference',
        'paid_by_user_id', 'paid_at', 'remittance_advice_sent_at',
        'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason',
        'voided_by_user_id', 'voided_at', 'void_reason',
        'variance_amount', 'variance_reason', 'variance_closed_by_user_id', 'variance_closed_at',
    ];
```

Modify the `casts()` method:

```php
    protected function casts(): array
    {
        return [
            'status' => ApPaymentHandoffStatus::class,
            'snapshot' => 'array',
            'readiness_warnings' => 'array',
            'lock_version' => 'integer',
            'total_amount' => 'decimal:4',
            'effective_payment_date' => 'date',
            'ready_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_exported_at' => 'datetime',
            'scheduled_for_date' => 'date',
            'scheduled_at' => 'datetime',
            'paid_at' => 'datetime',
            'remittance_advice_sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'voided_at' => 'datetime',
            'variance_amount' => 'decimal:4',
            'variance_closed_at' => 'datetime',
        ];
    }
```

Add relationships at the end of the class:

```php
    public function scheduledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function failedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'failed_by_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function varianceClosedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_closed_by_user_id');
    }

    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Domains\Payments\Models\ApPaymentAllocation::class, 'ap_payment_handoff_id');
    }
```

Update `booted()` saving callback to validate new user FKs:

```php
    protected static function booted(): void
    {
        static::saving(function (self $handoff): void {
            $tenantId = (int) $handoff->tenant_id;
            $userIds = array_filter([
                $handoff->scheduled_by_user_id,
                $handoff->paid_by_user_id,
                $handoff->failed_by_user_id,
                $handoff->voided_by_user_id,
                $handoff->variance_closed_by_user_id,
            ]);

            foreach ($userIds as $userId) {
                $exists = \App\Models\User::query()
                    ->whereKey($userId)
                    ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                    ->exists();

                if (! $exists) {
                    throw new \InvalidArgumentException("User {$userId} does not belong to tenant {$tenantId}.");
                }
            }
        });
    }
```

- [ ] **Step 2: Run existing handoff tests**

Run: `cd apps/api && php artisan test --filter=ApPaymentHandoffApiTest`
Expected: All existing tests still pass.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/AccountsPayable/Models/ApPaymentHandoff.php
git commit -m "feat(p1-48): extend ApPaymentHandoff model with post-export relationships and casts"
```

---

## Task 10: Extend ApPaymentHandoffPolicy

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php`

- [ ] **Step 1: Add new policy methods**

Append these methods before `buyerOrAdmin()`:

```php
    public function schedule(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function addAllocation(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markPaid(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function closeWithVariance(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markFailed(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function void(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function reschedule(User $user, ApPaymentHandoff $apPaymentHandoff): bool
    {
        return $this->isTenantScoped($apPaymentHandoff->tenant_id) && $this->buyerOrAdmin($user);
    }
```

- [ ] **Step 2: Run existing handoff tests**

Run: `cd apps/api && php artisan test --filter=ApPaymentHandoffApiTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php
git commit -m "feat(p1-48): extend ApPaymentHandoffPolicy with post-export abilities"
```

---

## Task 11: PaymentAllocationSumCalculator Support Class

**Files:**
- Create: `apps/api/Domains/Payments/Support/PaymentAllocationSumCalculator.php`

- [ ] **Step 1: Create support class**

```php
<?php

namespace Domains\Payments\Support;

use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;

class PaymentAllocationSumCalculator
{
    public function sumForInvoice(SupplierInvoice $invoice): string
    {
        $result = ApPaymentAllocation::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function sumForHandoff(string $handoffId): string
    {
        $result = ApPaymentAllocation::query()
            ->where('ap_payment_handoff_id', $handoffId)
            ->whereNull('voided_at')
            ->sum('allocated_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function derivePaymentStatus(SupplierInvoice $invoice): \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus
    {
        $allocated = $this->sumForInvoice($invoice);
        $total = (string) $invoice->total_amount;

        if (bccomp($allocated, $total, 4) === 0) {
            return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::Paid;
        }

        if (bccomp($allocated, '0.0000', 4) === 1) {
            return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::PaymentScheduled;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Support/PaymentAllocationSumCalculator.php
git commit -m "feat(p1-48): add PaymentAllocationSumCalculator support class"
```

---

## Task 12: ScheduleApPaymentHandoff Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/ScheduleApPaymentHandoff.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ScheduleApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ?string $scheduledForDate = null,
        ?string $paymentReference = null,
        ?string $notes = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $scheduledForDate, $paymentReference, $notes): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Exported) {
                throw new ConflictHttpException('Only exported AP payment handoffs can be scheduled.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            if ($invoices->isEmpty()) {
                throw new ConflictHttpException('AP payment handoff must include at least one invoice.');
            }

            $before = $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Scheduled,
                'scheduled_by_user_id' => $actor->id,
                'scheduled_at' => now(),
                'scheduled_for_date' => $scheduledForDate,
                'payment_reference' => $paymentReference,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::PaymentScheduled,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_scheduled',
                    subject: $invoice,
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.scheduled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Exported->value,
                    'toStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'scheduledForDate' => $scheduledForDate,
                    'paymentReference' => $paymentReference,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Write failing test first**

Add to `apps/api/tests/Feature/ApPaymentStatusApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApPaymentStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $buyer;
    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->buyer = User::factory()->create();
        $this->buyer->tenants()->attach($this->tenant->id, ['role' => 'buyer']);
        $this->requester = User::factory()->create();
        $this->requester->tenants()->attach($this->tenant->id, ['role' => 'requester']);
    }

    private function createExportedHandoff(): ApPaymentHandoff
    {
        $invoice = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_status' => SupplierInvoicePaymentStatus::HandoffExported,
            'total_amount' => '1000.0000',
            'currency' => 'USD',
            'lock_version' => 1,
        ]);

        $handoff = ApPaymentHandoff::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => ApPaymentHandoffStatus::Exported,
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);

        $handoff->invoices()->attach($invoice->id);
        return $handoff->fresh();
    }

    public function test_exported_handoff_can_be_scheduled(): void
    {
        $handoff = $this->createExportedHandoff();
        $invoice = $handoff->invoices()->first();

        $this->actingAs($this->buyer);
        session(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/ap-payment-handoffs/{$handoff->id}/schedule", [
            'lockVersion' => $handoff->lock_version,
            'scheduledForDate' => '2026-06-20',
            'paymentReference' => 'PRN-001',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'scheduled');
        $this->assertDatabaseHas('supplier_invoices', [
            'id' => $invoice->id,
            'payment_status' => 'payment_scheduled',
        ]);
    }

    public function test_scheduling_requires_lock_version(): void
    {
        $handoff = $this->createExportedHandoff();

        $this->actingAs($this->buyer);
        session(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/ap-payment-handoffs/{$handoff->id}/schedule", [
            'lockVersion' => 999,
        ]);

        $response->assertStatus(409);
    }

    public function test_scheduling_non_exported_handoff_returns_409(): void
    {
        $handoff = ApPaymentHandoff::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => ApPaymentHandoffStatus::Draft,
            'lock_version' => 1,
        ]);

        $this->actingAs($this->buyer);
        session(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/ap-payment-handoffs/{$handoff->id}/schedule", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(409);
    }
}
```

Run: `cd apps/api && php artisan test --filter=ApPaymentStatusApiTest`
Expected: FAIL -- route and controller do not exist yet.

- [ ] **Step 3: Commit action**

```bash
git add apps/api/Domains/Payments/Actions/ScheduleApPaymentHandoff.php
git commit -m "feat(p1-48): add ScheduleApPaymentHandoff action"
```

---

## Task 13: AddApPaymentAllocation Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/AddApPaymentAllocation.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AddApPaymentAllocation
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        SupplierInvoice $invoice,
        User $actor,
        int $lockVersion,
        string $allocatedAmount,
        string $allocationDate,
        ?string $paymentReference = null,
        ?string $settlementAmount = null,
        ?string $settlementCurrency = null,
    ): ApPaymentAllocation {
        return DB::transaction(function () use ($handoff, $invoice, $actor, $lockVersion, $allocatedAmount, $allocationDate, $paymentReference, $settlementAmount, $settlementCurrency): ApPaymentAllocation {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Allocations can only be added to scheduled handoffs.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoice = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $isMember = $handoff->invoices()->where('supplier_invoices.id', $invoice->id)->exists();
            if (! $isMember) {
                throw new ConflictHttpException('Invoice is not a member of this handoff.');
            }

            if (bccomp($allocatedAmount, '0.0000', 4) !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocatedAmount' => 'Allocated amount must be greater than zero.',
                ]);
            }

            $currentAllocated = $this->calculator->sumForInvoice($invoice);
            $newTotal = bcadd($currentAllocated, $allocatedAmount, 4);

            if (bccomp($newTotal, (string) $invoice->total_amount, 4) === 1) {
                $remaining = bcsub((string) $invoice->total_amount, $currentAllocated, 4);
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'allocatedAmount' => "Over-allocation: current allocated {$currentAllocated}, remaining {$remaining}, attempted {$allocatedAmount}.",
                ]);
            }

            if ($settlementCurrency !== null && $settlementCurrency !== $invoice->currency) {
                if ($settlementAmount === null) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'settlementAmount' => 'Settlement amount is required when settlement currency differs from invoice currency.',
                    ]);
                }
            }

            if ($settlementAmount === null) {
                $settlementAmount = $allocatedAmount;
            }

            $normalizedRef = $paymentReference !== null ? trim($paymentReference) : null;
            if ($normalizedRef === '') {
                $normalizedRef = null;
            }

            $allocation = ApPaymentAllocation::query()->create([
                'tenant_id' => $handoff->tenant_id,
                'ap_payment_handoff_id' => $handoff->id,
                'supplier_invoice_id' => $invoice->id,
                'allocated_amount' => $allocatedAmount,
                'allocation_date' => $allocationDate,
                'payment_reference' => $normalizedRef,
                'settlement_amount' => $settlementAmount,
                'settlement_currency' => $settlementCurrency,
                'lock_version' => 1,
            ]);

            $derivedStatus = $this->calculator->derivePaymentStatus($invoice);
            $invoice->forceFill([
                'payment_status' => $derivedStatus,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_allocation.created',
                subject: $allocation,
                metadata: [
                    'allocatedAmount' => $allocatedAmount,
                    'allocationDate' => $allocationDate,
                    'paymentReference' => $normalizedRef,
                    'settlementAmount' => $settlementAmount,
                    'settlementCurrency' => $settlementCurrency,
                ],
            ));

            return $allocation->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/AddApPaymentAllocation.php
git commit -m "feat(p1-48): add AddApPaymentAllocation action"
```

---

## Task 14: MarkApPaymentHandoffPaid Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/MarkApPaymentHandoffPaid.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkApPaymentHandoffPaid
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ?string $remittanceReference = null,
        ?string $remittanceAdviceSentAt = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $remittanceReference, $remittanceAdviceSentAt): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be marked paid.');
            }

            $handoff->assertLockVersion($lockVersion);

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            $underAllocated = [];

            foreach ($invoices as $invoice) {
                $allocated = $this->calculator->sumForInvoice($invoice);
                if (bccomp($allocated, (string) $invoice->total_amount, 4) !== 0) {
                    $underAllocated[] = [
                        'invoiceId' => (string) $invoice->id,
                        'invoiceNumber' => $invoice->invoice_number,
                        'allocated' => $allocated,
                        'total' => (string) $invoice->total_amount,
                        'remaining' => bcsub((string) $invoice->total_amount, $allocated, 4),
                    ];
                }
            }

            if (! empty($underAllocated)) {
                throw (new ConflictHttpException('One or more invoices are under-allocated. Add allocations or use Close with variance.'))
                    ->setHeaders(['X-Under-Allocated' => json_encode($underAllocated)]);
            }

            $before = $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'remittance_reference', 'remittance_advice_sent_at', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Paid,
                'paid_by_user_id' => $actor->id,
                'paid_at' => now(),
                'remittance_reference' => $remittanceReference,
                'remittance_advice_sent_at' => $remittanceAdviceSentAt,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::Paid,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.paid',
                    subject: $invoice,
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.paid',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'remittance_reference', 'remittance_advice_sent_at', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Paid->value,
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment.remitted',
                subject: $handoff,
                metadata: [
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/MarkApPaymentHandoffPaid.php
git commit -m "feat(p1-48): add MarkApPaymentHandoffPaid action"
```

---

## Task 15: CloseApPaymentHandoffWithVariance Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/CloseApPaymentHandoffWithVariance.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CloseApPaymentHandoffWithVariance
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        string $varianceReason,
        ?string $remittanceReference = null,
        ?string $remittanceAdviceSentAt = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $varianceReason, $remittanceReference, $remittanceAdviceSentAt): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be closed with variance.');
            }

            $handoff->assertLockVersion($lockVersion);

            $varianceReason = trim($varianceReason);
            if (strlen($varianceReason) < 5) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'varianceReason' => 'Variance reason must be at least 5 characters.',
                ]);
            }

            $allocationCount = \Domains\Payments\Models\ApPaymentAllocation::query()
                ->where('ap_payment_handoff_id', $handoff->id)
                ->whereNull('voided_at')
                ->count();

            if ($allocationCount === 0) {
                throw new ConflictHttpException('Cannot close with variance when no allocations exist. Use Mark Failed instead.');
            }

            $invoices = $handoff->invoices()->lockForUpdate()->get();
            $totalAllocated = '0.0000';

            foreach ($invoices as $invoice) {
                $totalAllocated = bcadd($totalAllocated, $this->calculator->sumForInvoice($invoice), 4);
            }

            $varianceAmount = bcsub((string) $handoff->total_amount, $totalAllocated, 4);

            $before = $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'variance_amount', 'variance_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Paid,
                'paid_by_user_id' => $actor->id,
                'paid_at' => now(),
                'variance_amount' => $varianceAmount,
                'variance_reason' => $varianceReason,
                'variance_closed_by_user_id' => $actor->id,
                'variance_closed_at' => now(),
                'remittance_reference' => $remittanceReference,
                'remittance_advice_sent_at' => $remittanceAdviceSentAt,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            foreach ($invoices as $invoice) {
                $allocated = $this->calculator->sumForInvoice($invoice);
                $isFullyPaid = bccomp($allocated, (string) $invoice->total_amount, 4) === 0;

                $newStatus = $isFullyPaid
                    ? SupplierInvoicePaymentStatus::Paid
                    : SupplierInvoicePaymentStatus::PartiallyPaid;

                $invoice->forceFill([
                    'payment_status' => $newStatus,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $action = $isFullyPaid ? 'supplier_invoice.paid' : 'supplier_invoice.partially_paid';
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: $action,
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'allocatedAmount' => $allocated,
                        'invoiceTotal' => (string) $invoice->total_amount,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.paid_with_variance',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'paid_by_user_id', 'paid_at', 'variance_amount', 'variance_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Paid->value,
                    'varianceAmount' => $varianceAmount,
                    'varianceReason' => $varianceReason,
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment.remitted',
                subject: $handoff,
                metadata: [
                    'remittanceReference' => $remittanceReference,
                    'remittanceAdviceSentAt' => $remittanceAdviceSentAt,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/CloseApPaymentHandoffWithVariance.php
git commit -m "feat(p1-48): add CloseApPaymentHandoffWithVariance action"
```

---

## Task 16: MarkApPaymentHandoffFailed Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/MarkApPaymentHandoffFailed.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\Payments\States\ApPaymentFailureCode;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MarkApPaymentHandoffFailed
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ApPaymentFailureCode $failureCode,
        string $failureReason,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $failureCode, $failureReason): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Scheduled) {
                throw new ConflictHttpException('Only scheduled AP payment handoffs can be marked failed.');
            }

            $handoff->assertLockVersion($lockVersion);

            $failureReason = trim($failureReason);
            if (strlen($failureReason) < 5) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'failureReason' => 'Failure reason must be at least 5 characters.',
                ]);
            }

            $before = $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Failed,
                'failed_by_user_id' => $actor->id,
                'failed_at' => now(),
                'failure_code' => $failureCode->value,
                'failure_reason' => $failureReason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::HandoffExported,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_failed',
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'fromStatus' => SupplierInvoicePaymentStatus::PaymentScheduled->value,
                        'toStatus' => SupplierInvoicePaymentStatus::HandoffExported->value,
                        'failureCode' => $failureCode->value,
                        'failureReason' => $failureReason,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.failed',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Scheduled->value,
                    'toStatus' => ApPaymentHandoffStatus::Failed->value,
                    'failureCode' => $failureCode->value,
                    'failureReason' => $failureReason,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/MarkApPaymentHandoffFailed.php
git commit -m "feat(p1-48): add MarkApPaymentHandoffFailed action with transient-state removal"
```

---

## Task 17: VoidApPaymentHandoff Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/VoidApPaymentHandoff.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoidApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $voidReason): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if (! in_array($handoff->statusState(), [ApPaymentHandoffStatus::Scheduled, ApPaymentHandoffStatus::Paid], true)) {
                throw new ConflictHttpException('Only scheduled or paid AP payment handoffs can be voided.');
            }

            $handoff->assertLockVersion($lockVersion);

            $voidReason = trim($voidReason);
            if (strlen($voidReason) < 5) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'voidReason' => 'Void reason must be at least 5 characters.',
                ]);
            }

            $before = $handoff->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Voided,
                'voided_by_user_id' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $voidReason,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            \Domains\Payments\Models\ApPaymentAllocation::query()
                ->where('ap_payment_handoff_id', $handoff->id)
                ->whereNull('voided_at')
                ->update(['voided_at' => now()]);

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::HandoffExported,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_voided',
                    subject: $invoice,
                    metadata: [
                        'handoffId' => (string) $handoff->id,
                        'handoffNumber' => $handoff->number,
                        'fromStatus' => $invoice->payment_status?->value,
                        'toStatus' => SupplierInvoicePaymentStatus::HandoffExported->value,
                        'voidReason' => $voidReason,
                    ],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.voided',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']),
                metadata: [
                    'fromStatus' => $before['status'],
                    'toStatus' => ApPaymentHandoffStatus::Voided->value,
                    'voidReason' => $voidReason,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/VoidApPaymentHandoff.php
git commit -m "feat(p1-48): add VoidApPaymentHandoff action with transient-state removal"
```

---

## Task 18: RescheduleFailedApPaymentHandoff Action

**Files:**
- Create: `apps/api/Domains/Payments/Actions/RescheduleFailedApPaymentHandoff.php`

- [ ] **Step 1: Create action file**

```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RescheduleFailedApPaymentHandoff
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        ApPaymentHandoff $handoff,
        User $actor,
        int $lockVersion,
        ?string $scheduledForDate = null,
        ?string $paymentReference = null,
        ?string $notes = null,
    ): ApPaymentHandoff {
        return DB::transaction(function () use ($handoff, $actor, $lockVersion, $scheduledForDate, $paymentReference, $notes): ApPaymentHandoff {
            $handoff = ApPaymentHandoff::query()->whereKey($handoff->id)->lockForUpdate()->firstOrFail();

            if ($handoff->statusState() !== ApPaymentHandoffStatus::Failed) {
                throw new ConflictHttpException('Only failed AP payment handoffs can be re-scheduled.');
            }

            $handoff->assertLockVersion($lockVersion);

            $before = $handoff->only(['status', 'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason', 'lock_version']);

            $handoff->forceFill([
                'status' => ApPaymentHandoffStatus::Scheduled,
                'failed_by_user_id' => null,
                'failed_at' => null,
                'failure_code' => null,
                'failure_reason' => null,
                'scheduled_by_user_id' => $actor->id,
                'scheduled_at' => now(),
                'scheduled_for_date' => $scheduledForDate,
                'payment_reference' => $paymentReference,
                'lock_version' => $handoff->lock_version + 1,
            ])->save();

            $invoices = $handoff->invoices()->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'payment_status' => SupplierInvoicePaymentStatus::PaymentScheduled,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $handoff->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.payment_scheduled',
                    subject: $invoice,
                    metadata: ['handoffId' => (string) $handoff->id, 'handoffNumber' => $handoff->number, 'rescheduled' => true],
                ));
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $handoff->tenant,
                actor: $actor,
                action: 'ap_payment_handoff.rescheduled',
                subject: $handoff,
                before: $before,
                after: $handoff->only(['status', 'scheduled_by_user_id', 'scheduled_at', 'lock_version']),
                metadata: [
                    'fromStatus' => ApPaymentHandoffStatus::Failed->value,
                    'toStatus' => ApPaymentHandoffStatus::Scheduled->value,
                ],
            ));

            return $handoff->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Actions/RescheduleFailedApPaymentHandoff.php
git commit -m "feat(p1-48): add RescheduleFailedApPaymentHandoff action"
```

---

## Task 19: Import Actions -- Parse, Match, Reconcile, Discard

**Files:**
- Create: `apps/api/Domains/Payments/Actions/ParsePaymentImportFile.php`
- Create: `apps/api/Domains/Payments/Actions/MatchPaymentImportRow.php`
- Create: `apps/api/Domains/Payments/Actions/ReconcilePaymentImportBatch.php`
- Create: `apps/api/Domains/Payments/Actions/DiscardPaymentImportRow.php`
- Create: `apps/api/Domains/Payments/Data/PaymentImportRowData.php`
- Create: `apps/api/Domains/Payments/Data/PaymentImportPreviewData.php`
- Create: `apps/api/Domains/Payments/Data/ReconciliationResultData.php`
- Create: `apps/api/Domains/Payments/Support/PaymentImportCsvParser.php`
- Create: `apps/api/Domains/Payments/Support/PaymentImportJsonParser.php`
- Create: `apps/api/Domains/Payments/Support/PaymentImportBatchIdGenerator.php`

- [ ] **Step 1: Create data DTOs**

`PaymentImportRowData.php`:
```php
<?php

namespace Domains\Payments\Data;

class PaymentImportRowData
{
    public function __construct(
        public readonly ?string $handoffNumber,
        public readonly ?string $invoiceNumber,
        public readonly ?string $paymentReference,
        public readonly ?string $allocatedAmount,
        public readonly bool $markFull,
        public readonly ?string $settlementAmount,
        public readonly ?string $settlementCurrency,
        public readonly ?string $paidAt,
        public readonly ?string $settlementMethod,
        public readonly string $status,
        public readonly ?string $failureCode,
        public readonly ?string $failureReason,
        public readonly ?string $voidReason,
    ) {}
}
```

`PaymentImportPreviewData.php`:
```php
<?php

namespace Domains\Payments\Data;

class PaymentImportPreviewData
{
    public function __construct(
        public readonly string $batchId,
        public readonly int $totalRows,
        public readonly array $rows,
    ) {}
}
```

`ReconciliationResultData.php`:
```php
<?php

namespace Domains\Payments\Data;

class ReconciliationResultData
{
    public function __construct(
        public readonly int $reconciled,
        public readonly int $failed,
        public readonly int $skipped,
    ) {}
}
```

- [ ] **Step 2: Create support classes**

`PaymentImportBatchIdGenerator.php`:
```php
<?php

namespace Domains\Payments\Support;

use Illuminate\Support\Str;

class PaymentImportBatchIdGenerator
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
```

`PaymentImportCsvParser.php`:
```php
<?php

namespace Domains\Payments\Support;

use Domains\Payments\Data\PaymentImportRowData;

class PaymentImportCsvParser
{
    public function parse(string $content): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $content)));
        if ($lines === []) {
            throw new \InvalidArgumentException('CSV file is empty.');
        }

        $headers = str_getcsv(array_shift($lines));
        $headerMap = array_flip(array_map('strtolower', $headers));

        if (! isset($headerMap['status'])) {
            throw new \InvalidArgumentException('Missing required CSV header: status.');
        }

        $rows = [];
        foreach ($lines as $index => $line) {
            $cols = str_getcsv($line);
            $row = [];
            foreach ($headerMap as $header => $idx) {
                $row[$header] = $cols[$idx] ?? null;
            }
            $rows[] = $this->mapToData($row, $index);
        }

        return $rows;
    }

    private function mapToData(array $row, int $index): PaymentImportRowData
    {
        return new PaymentImportRowData(
            handoffNumber: $this->nullIfEmpty($row['handoff_number'] ?? null),
            invoiceNumber: $this->nullIfEmpty($row['invoice_number'] ?? null),
            paymentReference: $this->nullIfEmpty($row['payment_reference'] ?? null),
            allocatedAmount: $this->nullIfEmpty($row['allocated_amount'] ?? null),
            markFull: filter_var($row['mark_full'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
            settlementAmount: $this->nullIfEmpty($row['settlement_amount'] ?? null),
            settlementCurrency: $this->nullIfEmpty($row['settlement_currency'] ?? null),
            paidAt: $this->nullIfEmpty($row['paid_at'] ?? null),
            settlementMethod: $this->nullIfEmpty($row['settlement_method'] ?? null),
            status: strtolower(trim($row['status'] ?? '')),
            failureCode: $this->nullIfEmpty($row['failure_code'] ?? null),
            failureReason: $this->nullIfEmpty($row['failure_reason'] ?? null),
            voidReason: $this->nullIfEmpty($row['void_reason'] ?? null),
        );
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
```

`PaymentImportJsonParser.php`:
```php
<?php

namespace Domains\Payments\Support;

use Domains\Payments\Data\PaymentImportRowData;

class PaymentImportJsonParser
{
    public function parse(string $content): array
    {
        $payload = json_decode($content, true);
        if (! is_array($payload) || ! isset($payload['rows']) || ! is_array($payload['rows'])) {
            throw new \InvalidArgumentException('JSON import must contain a "rows" array.');
        }

        $rows = [];
        foreach ($payload['rows'] as $index => $row) {
            if (! is_array($row)) continue;
            $rows[] = new PaymentImportRowData(
                handoffNumber: $this->nullIfEmpty($row['handoffNumber'] ?? null),
                invoiceNumber: $this->nullIfEmpty($row['invoiceNumber'] ?? null),
                paymentReference: $this->nullIfEmpty($row['paymentReference'] ?? null),
                allocatedAmount: $this->nullIfEmpty($row['allocatedAmount'] ?? null),
                markFull: filter_var($row['markFull'] ?? false, FILTER_VALIDATE_BOOLEAN),
                settlementAmount: $this->nullIfEmpty($row['settlementAmount'] ?? null),
                settlementCurrency: $this->nullIfEmpty($row['settlementCurrency'] ?? null),
                paidAt: $this->nullIfEmpty($row['paidAt'] ?? null),
                settlementMethod: $this->nullIfEmpty($row['settlementMethod'] ?? null),
                status: strtolower(trim($row['status'] ?? '')),
                failureCode: $this->nullIfEmpty($row['failureCode'] ?? null),
                failureReason: $this->nullIfEmpty($row['failureReason'] ?? null),
                voidReason: $this->nullIfEmpty($row['voidReason'] ?? null),
            );
        }

        return $rows;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
```

- [ ] **Step 3: Create import actions**

`ParsePaymentImportFile.php`:
```php
<?php

namespace Domains\Payments\Actions;

use App\Models\User;
use Domains\Payments\Data\PaymentImportPreviewData;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Domains\Payments\Support\PaymentImportBatchIdGenerator;
use Domains\Payments\Support\PaymentImportCsvParser;
use Domains\Payments\Support\PaymentImportJsonParser;
use Illuminate\Http\UploadedFile;

class ParsePaymentImportFile
{
    public function __construct(
        private readonly PaymentImportBatchIdGenerator $batchIdGenerator,
        private readonly PaymentImportCsvParser $csvParser,
        private readonly PaymentImportJsonParser $jsonParser,
    ) {}

    public function handle(UploadedFile $file, User $actor): PaymentImportPreviewData
    {
        $batchId = $this->batchIdGenerator->generate();
        $extension = strtolower($file->getClientOriginalExtension());
        $content = (string) file_get_contents($file->getRealPath());

        $rows = match ($extension) {
            'csv' => $this->csvParser->parse($content),
            'json' => $this->jsonParser->parse($content),
            default => throw new \InvalidArgumentException('Import file must be CSV or JSON.'),
        };

        $parsedRows = [];
        foreach ($rows as $index => $rowData) {
            $parseError = $this->validateRow($rowData);

            $import = ApPaymentImport::query()->create([
                'tenant_id' => $actor->tenants()->first()?->id ?? throw new \RuntimeException('Actor has no tenant.'),
                'batch_id' => $batchId,
                'row_index' => $index,
                'handoff_number' => $rowData->handoffNumber,
                'invoice_number' => $rowData->invoiceNumber,
                'payment_reference' => $rowData->paymentReference,
                'allocated_amount' => $rowData->allocatedAmount,
                'mark_full' => $rowData->markFull,
                'settlement_amount' => $rowData->settlementAmount,
                'settlement_currency' => $rowData->settlementCurrency,
                'paid_at' => $rowData->paidAt,
                'settlement_method' => $rowData->settlementMethod,
                'target_status' => $rowData->status,
                'failure_code' => $rowData->failureCode,
                'failure_reason' => $rowData->failureReason,
                'void_reason' => $rowData->voidReason,
                'status' => $parseError !== null ? ApPaymentImportStatus::Failed->value : ApPaymentImportStatus::Pending->value,
                'match_error' => $parseError,
                'imported_by_user_id' => $actor->id,
                'imported_at' => now(),
            ]);

            $parsedRows[] = [
                'id' => $import->id,
                'rowIndex' => $index,
                'handoffNumber' => $rowData->handoffNumber,
                'invoiceNumber' => $rowData->invoiceNumber,
                'targetStatus' => $rowData->status,
                'status' => $import->status,
                'matchError' => $parseError,
            ];
        }

        return new PaymentImportPreviewData(
            batchId: $batchId,
            totalRows: count($rows),
            rows: $parsedRows,
        );
    }

    private function validateRow(\Domains\Payments\Data\PaymentImportRowData $row): ?string
    {
        if (empty($row->handoffNumber) && empty($row->invoiceNumber)) {
            return 'Either handoff_number or invoice_number is required.';
        }

        try {
            ApPaymentImportTargetStatus::from($row->status);
        } catch (\ValueError) {
            return "Invalid target_status: {$row->status}.";
        }

        if ($row->status === 'failed' && (empty($row->failureCode) || empty($row->failureReason))) {
            return 'failure_code and failure_reason are required when status is failed.';
        }

        if ($row->status === 'voided' && empty($row->voidReason)) {
            return 'void_reason is required when status is voided.';
        }

        if ($row->status === 'paid' && ! $row->markFull && $row->allocatedAmount === null) {
            return 'allocated_amount is required when status is paid and mark_full is false.';
        }

        return null;
    }
}
```

`MatchPaymentImportRow.php`:
```php
<?php

namespace Domains\Payments\Actions;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;

class MatchPaymentImportRow
{
    public function handle(ApPaymentImport $import): void
    {
        if ($import->status !== ApPaymentImportStatus::Pending) {
            return;
        }

        $handoff = null;
        $invoice = null;
        $error = null;

        if ($import->handoff_number !== null) {
            $handoff = ApPaymentHandoff::query()
                ->where('tenant_id', $import->tenant_id)
                ->where('number', $import->handoff_number)
                ->first();
            if ($handoff === null) {
                $error = "Handoff {$import->handoff_number} not found.";
            }
        }

        if ($import->invoice_number !== null && $error === null) {
            $invoice = SupplierInvoice::query()
                ->where('tenant_id', $import->tenant_id)
                ->where('invoice_number', $import->invoice_number)
                ->first();
            if ($invoice === null) {
                $error = "Invoice {$import->invoice_number} not found.";
            }
        }

        if ($handoff !== null && $error === null) {
            $validStatuses = [ApPaymentHandoffStatus::Exported, ApPaymentHandoffStatus::Scheduled];
            if (! in_array($handoff->statusState(), $validStatuses, true)) {
                $error = "Handoff {$handoff->number} is not in exported or scheduled state.";
            }
        }

        if ($invoice !== null && $handoff !== null && $error === null) {
            $isMember = $handoff->invoices()->where('supplier_invoices.id', $invoice->id)->exists();
            if (! $isMember) {
                $error = "Invoice {$invoice->invoice_number} is not a member of handoff {$handoff->number}.";
            }
        }

        if ($error !== null) {
            $import->forceFill([
                'status' => ApPaymentImportStatus::Failed,
                'match_error' => $error,
            ])->save();
        } else {
            $import->forceFill([
                'matched_handoff_id' => $handoff?->id,
                'matched_invoice_id' => $invoice?->id,
                'match_error' => null,
            ])->save();
        }
    }
}
```

`ReconcilePaymentImportBatch.php`:
```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Payments\Data\ReconciliationResultData;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Illuminate\Support\Facades\DB;

class ReconcilePaymentImportBatch
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly MatchPaymentImportRow $matcher,
        private readonly MarkApPaymentHandoffPaid $markPaid,
        private readonly MarkApPaymentHandoffFailed $markFailed,
        private readonly VoidApPaymentHandoff $voidHandoff,
        private readonly AddApPaymentAllocation $addAllocation,
        private readonly PaymentAllocationSumCalculator $calculator,
    ) {}

    public function handle(string $batchId, User $actor): ReconciliationResultData
    {
        $rows = ApPaymentImport::query()
            ->where('batch_id', $batchId)
            ->where('tenant_id', $actor->tenants()->first()?->id)
            ->whereIn('status', [ApPaymentImportStatus::Pending->value, ApPaymentImportStatus::Failed->value])
            ->get();

        $reconciled = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($rows as $import) {
            DB::transaction(function () use ($import, $actor, &$reconciled, &$failed, &$skipped): void {
                $import = ApPaymentImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();

                if ($import->status !== ApPaymentImportStatus::Pending && $import->status !== ApPaymentImportStatus::Failed) {
                    $skipped++;
                    return;
                }

                $this->matcher->handle($import);
                $import->refresh();

                if ($import->match_error !== null || $import->matched_handoff_id === null) {
                    $failed++;
                    return;
                }

                $handoff = \Domains\AccountsPayable\Models\ApPaymentHandoff::query()
                    ->whereKey($import->matched_handoff_id)
                    ->first();

                if ($handoff === null) {
                    $failed++;
                    return;
                }

                $target = ApPaymentImportTargetStatus::from($import->target_status);

                try {
                    match ($target) {
                        ApPaymentImportTargetStatus::Paid => $this->reconcilePaid($import, $handoff, $actor),
                        ApPaymentImportTargetStatus::Failed => $this->reconcileFailed($import, $handoff, $actor),
                        ApPaymentImportTargetStatus::Voided => $this->reconcileVoided($import, $handoff, $actor),
                    };

                    $import->forceFill([
                        'status' => ApPaymentImportStatus::Reconciled,
                        'reconciled_at' => now(),
                        'reconciled_by_user_id' => $actor->id,
                    ])->save();

                    $this->auditRecorder->record(new AuditEventData(
                        tenant: $handoff->tenant,
                        actor: $actor,
                        action: 'ap_payment_import.reconciled',
                        subject: $import,
                        metadata: ['batchId' => $import->batch_id, 'rowIndex' => $import->row_index],
                    ));

                    $reconciled++;
                } catch (\Throwable $e) {
                    $import->forceFill([
                        'status' => ApPaymentImportStatus::Failed,
                        'match_error' => $e->getMessage(),
                    ])->save();
                    $failed++;
                }
            });
        }

        return new ReconciliationResultData($reconciled, $failed, $skipped);
    }

    private function reconcilePaid(ApPaymentImport $import, \Domains\AccountsPayable\Models\ApPaymentHandoff $handoff, User $actor): void
    {
        if ($import->mark_full) {
            foreach ($handoff->invoices as $invoice) {
                $remaining = bcsub((string) $invoice->total_amount, $this->calculator->sumForInvoice($invoice), 4);
                if (bccomp($remaining, '0.0000', 4) === 1) {
                    $this->addAllocation->handle(
                        handoff: $handoff,
                        invoice: $invoice,
                        actor: $actor,
                        lockVersion: $handoff->lock_version,
                        allocatedAmount: $remaining,
                        allocationDate: $import->paid_at ?? now()->toDateString(),
                        paymentReference: $import->payment_reference,
                        settlementAmount: $import->settlement_amount,
                        settlementCurrency: $import->settlement_currency,
                    );
                    $handoff->refresh();
                }
            }
        } elseif ($import->allocated_amount !== null && $import->matched_invoice_id !== null) {
            $invoice = \Domains\Invoice\Models\SupplierInvoice::query()->whereKey($import->matched_invoice_id)->firstOrFail();
            $this->addAllocation->handle(
                handoff: $handoff,
                invoice: $invoice,
                actor: $actor,
                lockVersion: $handoff->lock_version,
                allocatedAmount: $import->allocated_amount,
                allocationDate: $import->paid_at ?? now()->toDateString(),
                paymentReference: $import->payment_reference,
                settlementAmount: $import->settlement_amount,
                settlementCurrency: $import->settlement_currency,
            );
            $handoff->refresh();
        }

        $allFullyAllocated = true;
        foreach ($handoff->invoices as $invoice) {
            if (bccomp($this->calculator->sumForInvoice($invoice), (string) $invoice->total_amount, 4) !== 0) {
                $allFullyAllocated = false;
                break;
            }
        }

        if ($allFullyAllocated) {
            $this->markPaid->handle($handoff, $actor, $handoff->lock_version);
        }
    }

    private function reconcileFailed(ApPaymentImport $import, \Domains\AccountsPayable\Models\ApPaymentHandoff $handoff, User $actor): void
    {
        $this->markFailed->handle(
            $handoff,
            $actor,
            $handoff->lock_version,
            \Domains\Payments\States\ApPaymentFailureCode::from($import->failure_code ?? 'other'),
            $import->failure_reason ?? 'Imported failure',
        );
    }

    private function reconcileVoided(ApPaymentImport $import, \Domains\AccountsPayable\Models\ApPaymentHandoff $handoff, User $actor): void
    {
        $this->voidHandoff->handle(
            $handoff,
            $actor,
            $handoff->lock_version,
            $import->void_reason ?? 'Imported void',
        );
    }
}
```

`DiscardPaymentImportRow.php`:
```php
<?php

namespace Domains\Payments\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\States\ApPaymentImportStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DiscardPaymentImportRow
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(ApPaymentImport $import, User $actor): ApPaymentImport
    {
        if ($import->status === ApPaymentImportStatus::Reconciled->value) {
            throw new ConflictHttpException('Reconciled import rows cannot be discarded.');
        }

        $import->forceFill([
            'status' => ApPaymentImportStatus::Discarded,
        ])->save();

        $this->auditRecorder->record(new AuditEventData(
            tenant: $import->tenant,
            actor: $actor,
            action: 'ap_payment_import.discarded',
            subject: $import,
            metadata: ['batchId' => $import->batch_id, 'rowIndex' => $import->row_index],
        ));

        return $import->fresh();
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Payments/Actions/ParsePaymentImportFile.php apps/api/Domains/Payments/Actions/MatchPaymentImportRow.php apps/api/Domains/Payments/Actions/ReconcilePaymentImportBatch.php apps/api/Domains/Payments/Actions/DiscardPaymentImportRow.php apps/api/Domains/Payments/Data/ apps/api/Domains/Payments/Support/
git commit -m "feat(p1-48): add import parse, match, reconcile, discard actions and support classes"
```

---
## Task 20: Extend `ApPaymentHandoffPolicy` with Post-Export Abilities

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php`
- Create: `apps/api/tests/Feature/ApPaymentHandoffPolicyTest.php`

- [ ] **Step 1: Write the failing test**

Create `apps/api/tests/Feature/ApPaymentHandoffPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\AccountsPayable\Policies\ApPaymentHandoffPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApPaymentHandoffPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_export_abilities_require_buyer_or_admin(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $handoff = $this->createHandoff($tenant);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertTrue($policy->schedule($buyer, $handoff));
        $this->assertTrue($policy->addAllocation($buyer, $handoff));
        $this->assertTrue($policy->markPaid($buyer, $handoff));
        $this->assertTrue($policy->closeWithVariance($buyer, $handoff));
        $this->assertTrue($policy->markFailed($buyer, $handoff));
        $this->assertTrue($policy->void($buyer, $handoff));
        $this->assertTrue($policy->reschedule($buyer, $handoff));
    }

    public function test_post_export_abilities_reject_requester(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $handoff = $this->createHandoff($tenant);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertFalse($policy->schedule($requester, $handoff));
        $this->assertFalse($policy->markPaid($requester, $handoff));
        $this->assertFalse($policy->void($requester, $handoff));
    }

    public function test_post_export_abilities_reject_cross_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $handoff = $this->createHandoff($tenant);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $policy = app(ApPaymentHandoffPolicy::class);
        $this->assertFalse($policy->markPaid($otherBuyer, $handoff));
    }

    private function createHandoff(Tenant $tenant): ApPaymentHandoff
    {
        return ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'APH-POL-'.Str::random(6),
            'status' => 'scheduled',
            'currency' => 'USD',
            'total_amount' => '1000.0000',
            'lock_version' => 1,
        ]);
    }

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);
        return [$tenant, $user];
    }
}
```

- [ ] **Step 2: Run the test to confirm failure**

```bash
cd apps/api && php artisan test --filter=ApPaymentHandoffPolicyTest
```

Expected: FAIL with `Call to undefined method ApPaymentHandoffPolicy::schedule()`.

- [ ] **Step 3: Add the new abilities to the policy**

Append to `apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php` (before the final `}`):

```php
    public function schedule(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function addAllocation(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markPaid(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function closeWithVariance(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function markFailed(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function reschedule(User $user, ApPaymentHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }
```

- [ ] **Step 4: Run the test to confirm pass**

```bash
cd apps/api && php artisan test --filter=ApPaymentHandoffPolicyTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php apps/api/tests/Feature/ApPaymentHandoffPolicyTest.php
git commit -m "feat(p1-48): extend ApPaymentHandoffPolicy with post-export abilities"
```

---
## Task 21: Create `ApPaymentAllocationPolicy` and `ApPaymentImportPolicy`

**Files:**
- Create: `apps/api/Domains/Payments/Policies/ApPaymentAllocationPolicy.php`
- Create: `apps/api/Domains/Payments/Policies/ApPaymentImportPolicy.php`
- Create: `apps/api/tests/Feature/PaymentPolicyTest.php`

- [ ] **Step 1: Write the failing test**

Create `apps/api/tests/Feature/PaymentPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Payments\Policies\ApPaymentAllocationPolicy;
use Domains\Payments\Policies\ApPaymentImportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_allocation_policy_buyer_can_view_and_create(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $allocation = $this->createAllocation($tenant);

        $policy = app(ApPaymentAllocationPolicy::class);
        $this->assertTrue($policy->view($buyer, $allocation));
        $this->assertTrue($policy->create($buyer));
    }

    public function test_allocation_policy_rejects_requester(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $allocation = $this->createAllocation($tenant);

        $policy = app(ApPaymentAllocationPolicy::class);
        $this->assertFalse($policy->view($requester, $allocation));
    }

    public function test_import_policy_buyer_can_upload_view_reconcile_discard(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $import = $this->createImport($tenant, $buyer);

        $policy = app(ApPaymentImportPolicy::class);
        $this->assertTrue($policy->view($buyer, $import));
        $this->assertTrue($policy->upload($buyer));
        $this->assertTrue($policy->reconcile($buyer));
        $this->assertTrue($policy->discard($buyer, $import));
        $this->assertTrue($policy->update($buyer, $import));
    }

    public function test_import_policy_rejects_cross_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $import = $this->createImport($tenant, $buyer);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $policy = app(ApPaymentImportPolicy::class);
        $this->assertFalse($policy->view($otherBuyer, $import));
    }

    private function createAllocation(Tenant $tenant): ApPaymentAllocation
    {
        $handoff = ApPaymentHandoff::query()->create([
            'tenant_id' => $tenant->id, 'number' => 'APH-A-'.Str::random(6), 'status' => 'scheduled',
            'currency' => 'USD', 'total_amount' => '1000.0000', 'lock_version' => 1,
        ]);
        $invoice = SupplierInvoice::query()->create([
            'tenant_id' => $tenant->id, 'number' => 'INV-A-'.Str::random(6),
            'invoice_number' => 'INV-A-'.Str::random(6), 'invoice_number_normalized' => 'inva',
            'status' => SupplierInvoiceStatus::Approved->value, 'currency' => 'USD',
            'subtotal_amount' => '1000.0000', 'total_amount' => '1000.0000', 'lock_version' => 1,
        ]);
        $handoff->invoices()->attach($invoice->id, ['tenant_id' => $tenant->id]);

        return ApPaymentAllocation::query()->create([
            'tenant_id' => $tenant->id,
            'ap_payment_handoff_id' => $handoff->id,
            'supplier_invoice_id' => $invoice->id,
            'allocated_amount' => '500.0000',
            'allocation_date' => '2026-06-19',
            'lock_version' => 1,
        ]);
    }

    private function createImport(Tenant $tenant, User $user): ApPaymentImport
    {
        return ApPaymentImport::query()->create([
            'tenant_id' => $tenant->id,
            'batch_id' => (string) Str::uuid(),
            'row_index' => 0,
            'target_status' => 'paid',
            'status' => 'pending',
            'imported_by_user_id' => $user->id,
            'imported_at' => now(),
        ]);
    }

    /** @return array{Tenant, User} */
    private function tenantUserPair(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => $role]);
        return [$tenant, $user];
    }
}
```

- [ ] **Step 2: Run the test to confirm failure**

```bash
cd apps/api && php artisan test --filter=PaymentPolicyTest
```

Expected: FAIL with `Class "ApPaymentAllocationPolicy" not found`.

- [ ] **Step 3: Create both policies**

`apps/api/Domains/Payments/Policies/ApPaymentAllocationPolicy.php`:

```php
<?php

namespace Domains\Payments\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Payments\Models\ApPaymentAllocation;

class ApPaymentAllocationPolicy
{
    public function view(User $user, ApPaymentAllocation $allocation): bool
    {
        return $this->isTenantScoped($allocation->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);
        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(int|string $tenantId): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();
        return $tenant !== null && (int) $tenant->id === (int) $tenantId;
    }
}
```

`apps/api/Domains/Payments/Policies/ApPaymentImportPolicy.php`:

```php
<?php

namespace Domains\Payments\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Payments\Models\ApPaymentImport;

class ApPaymentImportPolicy
{
    public function view(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function upload(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function update(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function reconcile(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function discard(User $user, ApPaymentImport $import): bool
    {
        return $this->isTenantScoped($import->tenant_id) && $this->buyerOrAdmin($user);
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);
        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(int|string $tenantId): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();
        return $tenant !== null && (int) $tenant->id === (int) $tenantId;
    }
}
```

- [ ] **Step 4: Run the test to confirm pass**

```bash
cd apps/api && php artisan test --filter=PaymentPolicyTest
```

Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/Payments/Policies apps/api/tests/Feature/PaymentPolicyTest.php
git commit -m "feat(p1-48): add ApPaymentAllocationPolicy and ApPaymentImportPolicy"
```

---

## Task 22: Create 10 FormRequest Classes

**Files:**
- Create: 10 FormRequest classes under `apps/api/Domains/Payments/Http/Requests/`

- [ ] **Step 1: Create all 10 request classes**

For each, mirror `CancelApPaymentHandoffRequest` (`authorize() { return true; }` then `rules()`). Use the exact code below for each file.

`apps/api/Domains/Payments/Http/Requests/ScheduleApPaymentHandoffRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'scheduledForDate' => ['nullable', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/AddApPaymentAllocationRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddApPaymentAllocationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'supplierInvoiceId' => ['required', 'string', 'exists:supplier_invoices,id'],
            'allocatedAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'allocationDate' => ['required', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
            'settlementAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'settlementCurrency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/MarkApPaymentHandoffPaidRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkApPaymentHandoffPaidRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'remittanceReference' => ['nullable', 'string', 'max:255'],
            'remittanceAdviceSentAt' => ['nullable', 'date'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/CloseApPaymentHandoffWithVarianceRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseApPaymentHandoffWithVarianceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'varianceReason' => ['required', 'string', 'min:5', 'max:2000'],
            'remittanceReference' => ['nullable', 'string', 'max:255'],
            'remittanceAdviceSentAt' => ['nullable', 'date'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/MarkApPaymentHandoffFailedRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkApPaymentHandoffFailedRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'failureCode' => ['required', 'string', 'in:bank_rejected,insufficient_funds,vendor_blocked,system_error,other'],
            'failureReason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/VoidApPaymentHandoffRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'voidReason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/RescheduleApPaymentHandoffRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'scheduledForDate' => ['nullable', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/UploadPaymentImportRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPaymentImportRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/UpdatePaymentImportRowRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentImportRowRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'handoffNumber' => ['nullable', 'string', 'max:50'],
            'invoiceNumber' => ['nullable', 'string', 'max:255'],
            'allocatedAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'markFull' => ['nullable', 'boolean'],
            'settlementAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'settlementCurrency' => ['nullable', 'string', 'size:3'],
            'paidAt' => ['nullable', 'date'],
            'settlementMethod' => ['nullable', 'string', 'max:50'],
            'failureCode' => ['nullable', 'string', 'in:bank_rejected,insufficient_funds,vendor_blocked,system_error,other'],
            'failureReason' => ['nullable', 'string', 'min:5', 'max:2000'],
            'voidReason' => ['nullable', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Requests/ReconcilePaymentImportBatchRequest.php`:

```php
<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcilePaymentImportBatchRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersions' => ['nullable', 'array'],
            'lockVersions.*' => ['integer', 'min:1'],
        ];
    }
}
```

- [ ] **Step 2: Verify autoloading**

```bash
cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\Payments\\Http\\Requests\\ScheduleApPaymentHandoffRequest"), class_exists("Domains\\Payments\\Http\\Requests\\AddApPaymentAllocationRequest"), class_exists("Domains\\Payments\\Http\\Requests\\ReconcilePaymentImportBatchRequest"));'
```

Expected: prints `bool(true) bool(true) bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Payments/Http/Requests
git commit -m "feat(p1-48): add 10 form requests for post-export handoff and import actions"
```

---
## Task 23: Create 3 Resource Classes

**Files:**
- Create: `apps/api/Domains/Payments/Http/Resources/ApPaymentAllocationResource.php`
- Create: `apps/api/Domains/Payments/Http/Resources/ApPaymentImportResource.php`
- Create: `apps/api/Domains/Payments/Http/Resources/ApPaymentImportBatchResource.php`

- [ ] **Step 1: Create the three resources**

`apps/api/Domains/Payments/Http/Resources/ApPaymentAllocationResource.php`:

```php
<?php

namespace Domains\Payments\Http\Resources;

use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApPaymentAllocation
 */
class ApPaymentAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'apPaymentHandoffId' => (string) $this->ap_payment_handoff_id,
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'supplierInvoiceNumber' => $this->whenLoaded('supplierInvoice', fn () => $this->supplierInvoice?->invoice_number),
            'allocatedAmount' => (string) $this->allocated_amount,
            'allocationDate' => $this->allocation_date?->toDateString(),
            'paymentReference' => $this->payment_reference,
            'settlementAmount' => $this->settlement_amount !== null ? (string) $this->settlement_amount : null,
            'settlementCurrency' => $this->settlement_currency,
            'voidedAt' => $this->voided_at?->toISOString(),
            'lockVersion' => $this->lock_version,
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Resources/ApPaymentImportResource.php`:

```php
<?php

namespace Domains\Payments\Http\Resources;

use Domains\Payments\Models\ApPaymentImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApPaymentImport
 */
class ApPaymentImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'batchId' => $this->batch_id,
            'rowIndex' => (int) $this->row_index,
            'handoffNumber' => $this->handoff_number,
            'invoiceNumber' => $this->invoice_number,
            'paymentReference' => $this->payment_reference,
            'allocatedAmount' => $this->allocated_amount !== null ? (string) $this->allocated_amount : null,
            'markFull' => (bool) $this->mark_full,
            'settlementAmount' => $this->settlement_amount !== null ? (string) $this->settlement_amount : null,
            'settlementCurrency' => $this->settlement_currency,
            'paidAt' => $this->paid_at?->toDateString(),
            'settlementMethod' => $this->settlement_method,
            'targetStatus' => $this->target_status,
            'failureCode' => $this->failure_code,
            'failureReason' => $this->failure_reason,
            'voidReason' => $this->void_reason,
            'status' => $this->status,
            'matchError' => $this->match_error,
            'matchedHandoffId' => $this->matched_handoff_id !== null ? (string) $this->matched_handoff_id : null,
            'matchedInvoiceId' => $this->matched_invoice_id !== null ? (string) $this->matched_invoice_id : null,
            'reconciledAt' => $this->reconciled_at?->toISOString(),
            'reconciledByUserId' => $this->reconciled_by_user_id !== null ? (string) $this->reconciled_by_user_id : null,
            'importedByUserId' => (string) $this->imported_by_user_id,
            'importedAt' => $this->imported_at?->toISOString(),
            'lockVersion' => $this->lock_version,
        ];
    }
}
```

`apps/api/Domains/Payments/Http/Resources/ApPaymentImportBatchResource.php`:

```php
<?php

namespace Domains\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApPaymentImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rows = $this->resource['rows'] ?? [];
        $summary = $this->resource['summary'] ?? ['total' => 0, 'pending' => 0, 'reconciled' => 0, 'failed' => 0, 'discarded' => 0];

        return [
            'batchId' => $this->resource['batchId'],
            'rows' => ApPaymentImportResource::collection(collect($rows))->resolve(),
            'summary' => $summary,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Payments/Http/Resources
git commit -m "feat(p1-48): add ApPaymentAllocation, ApPaymentImport, and batch resources"
```

---

## Task 24: Extend `ApPaymentHandoffResource` with Post-Export Fields and Permissions

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/Http/Resources/ApPaymentHandoffResource.php`

- [ ] **Step 1: Replace the resource body**

Replace the contents of `apps/api/Domains/AccountsPayable/Http/Resources/ApPaymentHandoffResource.php` with:

```php
<?php

namespace Domains\AccountsPayable\Http\Resources;

use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Payments\Http\Resources\ApPaymentAllocationResource;
use Domains\Payments\Support\PaymentAllocationSumCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin ApPaymentHandoff
 */
class ApPaymentHandoffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allocations = $this->whenLoaded('allocations', fn () => $this->allocations, fn () => collect());
        $allocationsArray = $allocations->isNotEmpty()
            ? ApPaymentAllocationResource::collection($allocations)->resolve()
            : [];

        $invoicesResource = ($this->invoices ?? collect())->map(function ($inv) use ($allocations) {
            $sum = $allocations->isNotEmpty()
                ? PaymentAllocationSumCalculator::sumForInvoice(
                    $allocations->map(fn ($a) => $a->only(['supplier_invoice_id', 'allocated_amount', 'voided_at']))->all(),
                    (string) $inv->id
                )
                : '0.0000';
            $outstanding = bcsub((string) $inv->total_amount, $sum, 4);
            $status = bccomp($sum, (string) $inv->total_amount, 4) === 0
                ? 'paid'
                : (bccomp($sum, '0', 4) > 0 ? 'partially_paid' : 'payment_scheduled');

            return [
                'id' => (string) $inv->id,
                'invoiceNumber' => $inv->invoice_number,
                'currency' => $inv->currency,
                'totalAmount' => (string) $inv->total_amount,
                'allocatedAmount' => $sum,
                'outstandingAmount' => $outstanding,
                'paymentStatus' => $status,
            ];
        });

        return [
            'id' => (string) $this->id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'effectivePaymentDate' => $this->effective_payment_date?->toDateString(),
            'notes' => $this->notes,
            'currency' => $this->currency,
            'totalAmount' => (string) $this->total_amount,
            'remittanceReference' => $this->remittance_reference,
            'scheduledAt' => $this->scheduled_at?->toISOString(),
            'scheduledByUserId' => $this->scheduled_by_user_id !== null ? (string) $this->scheduled_by_user_id : null,
            'scheduledForDate' => $this->scheduled_for_date?->toDateString(),
            'paymentReference' => $this->payment_reference,
            'paidAt' => $this->paid_at?->toISOString(),
            'paidByUserId' => $this->paid_by_user_id !== null ? (string) $this->paid_by_user_id : null,
            'remittanceAdviceSentAt' => $this->remittance_advice_sent_at?->toISOString(),
            'failedAt' => $this->failed_at?->toISOString(),
            'failedByUserId' => $this->failed_by_user_id !== null ? (string) $this->failed_by_user_id : null,
            'failureCode' => $this->failure_code,
            'failureReason' => $this->failure_reason,
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidedByUserId' => $this->voided_by_user_id !== null ? (string) $this->voided_by_user_id : null,
            'voidReason' => $this->void_reason,
            'varianceAmount' => $this->variance_amount !== null ? (string) $this->variance_amount : null,
            'varianceReason' => $this->variance_reason,
            'varianceClosedAt' => $this->variance_closed_at?->toISOString(),
            'varianceClosedByUserId' => $this->variance_closed_by_user_id !== null ? (string) $this->variance_closed_by_user_id : null,
            'allocations' => $allocationsArray,
            'invoices' => $invoicesResource,
            'snapshot' => $this->snapshot,
            'readinessWarnings' => $this->readiness_warnings,
            'createdBy' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser?->id !== null ? (string) $this->createdByUser->id : null,
                'name' => $this->createdByUser?->name,
            ]),
            'cancelledBy' => $this->whenLoaded('cancelledByUser', fn () => [
                'id' => $this->cancelledByUser?->id !== null ? (string) $this->cancelledByUser->id : null,
                'name' => $this->cancelledByUser?->name,
            ]),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelledReason' => $this->cancelled_reason,
            'lastExportedAt' => $this->last_exported_at?->toISOString(),
            'lastExportFormat' => $this->last_export_format,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
            'permissions' => [
                'canUpdate' => Gate::allows('update', $this->resource),
                'canMarkReady' => Gate::allows('markReady', $this->resource),
                'canCancel' => Gate::allows('cancel', $this->resource),
                'canExport' => Gate::allows('export', $this->resource),
                'canSchedule' => Gate::allows('schedule', $this->resource),
                'canAddAllocation' => Gate::allows('addAllocation', $this->resource),
                'canMarkPaid' => Gate::allows('markPaid', $this->resource),
                'canCloseWithVariance' => Gate::allows('closeWithVariance', $this->resource),
                'canMarkFailed' => Gate::allows('markFailed', $this->resource),
                'canVoid' => Gate::allows('void', $this->resource),
                'canReschedule' => Gate::allows('reschedule', $this->resource),
            ],
        ];
    }
}
```

- [ ] **Step 2: Run the existing P1-47 handoff tests to confirm no regression**

```bash
cd apps/api && php artisan test --filter=ApPaymentHandoffApiTest
```

Expected: PASS (no new failures).

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/AccountsPayable/Http/Resources/ApPaymentHandoffResource.php
git commit -m "feat(p1-48): extend ApPaymentHandoffResource with post-export lifecycle fields and permissions"
```

---

## Task 25: Create 3 Controllers

**Files:**
- Create: `apps/api/Domains/Payments/Http/Controllers/ApPaymentStatusController.php`
- Create: `apps/api/Domains/Payments/Http/Controllers/ApPaymentAllocationController.php`
- Create: `apps/api/Domains/Payments/Http/Controllers/ApPaymentImportController.php`

- [ ] **Step 1: Create `ApPaymentStatusController`**

`apps/api/Domains/Payments/Http/Controllers/ApPaymentStatusController.php`:

```php
<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Http\Resources\ApPaymentHandoffResource;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Payments\Actions\CloseApPaymentHandoffWithVariance;
use Domains\Payments\Actions\MarkApPaymentHandoffFailed;
use Domains\Payments\Actions\MarkApPaymentHandoffPaid;
use Domains\Payments\Actions\RescheduleFailedApPaymentHandoff;
use Domains\Payments\Actions\ScheduleApPaymentHandoff;
use Domains\Payments\Actions\VoidApPaymentHandoff;
use Domains\Payments\Http\Requests\CloseApPaymentHandoffWithVarianceRequest;
use Domains\Payments\Http\Requests\MarkApPaymentHandoffFailedRequest;
use Domains\Payments\Http\Requests\MarkApPaymentHandoffPaidRequest;
use Domains\Payments\Http\Requests\RescheduleApPaymentHandoffRequest;
use Domains\Payments\Http\Requests\ScheduleApPaymentHandoffRequest;
use Domains\Payments\Http\Requests\VoidApPaymentHandoffRequest;
use Illuminate\Http\JsonResponse;

class ApPaymentStatusController extends Controller
{
    public function schedule(
        ScheduleApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        ScheduleApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('schedule', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            $request->validated('scheduledForDate'),
            $request->validated('paymentReference'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function markPaid(
        MarkApPaymentHandoffPaidRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        MarkApPaymentHandoffPaid $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markPaid', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            $request->validated('remittanceReference'),
            $request->validated('remittanceAdviceSentAt'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function closeWithVariance(
        CloseApPaymentHandoffWithVarianceRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        CloseApPaymentHandoffWithVariance $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('closeWithVariance', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (string) $request->validated('varianceReason'),
            $request->validated('remittanceReference'),
            $request->validated('remittanceAdviceSentAt'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function markFailed(
        MarkApPaymentHandoffFailedRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        MarkApPaymentHandoffFailed $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('markFailed', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (string) $request->validated('failureCode'),
            (string) $request->validated('failureReason'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function void(
        VoidApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        VoidApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('void', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            (string) $request->validated('voidReason'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    public function reschedule(
        RescheduleApPaymentHandoffRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        RescheduleFailedApPaymentHandoff $action,
    ): JsonResponse {
        $handoff = $this->findTenantHandoff($this->tenantOrAbort($currentTenant), $handoff);
        $this->authorize('reschedule', $handoff);

        $result = $action->handle(
            $handoff,
            $request->user(),
            $request->validated('scheduledForDate'),
            $request->validated('paymentReference'),
            (int) $request->validated('lockVersion'),
        );

        return $this->resourceResponse($result->load(['invoices', 'allocations']));
    }

    private function findTenantHandoff(Tenant $tenant, ApPaymentHandoff $handoff): ApPaymentHandoff
    {
        $tenantHandoff = ApPaymentHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();
        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this AP payment handoff.');
        }
        return $tenantHandoff;
    }

    private function resourceResponse(?ApPaymentHandoff $handoff): JsonResponse
    {
        return response()->json([
            'data' => $handoff === null ? null : (new ApPaymentHandoffResource($handoff))->resolve(),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 2: Create `ApPaymentAllocationController`**

`apps/api/Domains/Payments/Http/Controllers/ApPaymentAllocationController.php`:

```php
<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Actions\AddApPaymentAllocation;
use Domains\Payments\Http\Requests\AddApPaymentAllocationRequest;
use Domains\Payments\Http\Resources\ApPaymentAllocationResource;
use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\JsonResponse;

class ApPaymentAllocationController extends Controller
{
    public function index(CurrentTenant $currentTenant, ApPaymentHandoff $handoff): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $handoff = $this->findTenantHandoff($tenant, $handoff);
        $this->authorize('view', ApPaymentAllocation::class);

        $allocations = ApPaymentAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->where('ap_payment_handoff_id', $handoff->id)
            ->with('supplierInvoice')
            ->orderBy('allocation_date')
            ->get();

        return response()->json([
            'data' => ApPaymentAllocationResource::collection($allocations)->resolve(),
        ]);
    }

    public function store(
        AddApPaymentAllocationRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentHandoff $handoff,
        AddApPaymentAllocation $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $handoff = $this->findTenantHandoff($tenant, $handoff);
        $this->authorize('create', ApPaymentAllocation::class);

        $invoice = SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($request->validated('supplierInvoiceId'))
            ->firstOrFail();

        $allocation = $action->handle(
            $handoff,
            $invoice,
            $request->user(),
            (string) $request->validated('allocatedAmount'),
            (string) $request->validated('allocationDate'),
            $request->validated('paymentReference'),
            $request->validated('settlementAmount'),
            $request->validated('settlementCurrency'),
            (int) $request->validated('lockVersion'),
        );

        return response()->json([
            'data' => (new ApPaymentAllocationResource($allocation->fresh()))->resolve(),
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, ApPaymentAllocation $allocation): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantAllocation = ApPaymentAllocation::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($allocation->id)
            ->first();
        if ($tenantAllocation === null) {
            abort(403, 'You are not allowed to access this allocation.');
        }
        $this->authorize('view', $tenantAllocation);

        return response()->json([
            'data' => (new ApPaymentAllocationResource($tenantAllocation))->resolve(),
        ]);
    }

    private function findTenantHandoff(Tenant $tenant, ApPaymentHandoff $handoff): ApPaymentHandoff
    {
        $tenantHandoff = ApPaymentHandoff::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($handoff->id)
            ->first();
        if ($tenantHandoff === null) {
            abort(403, 'You are not allowed to access this AP payment handoff.');
        }
        return $tenantHandoff;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 3: Create `ApPaymentImportController`**

`apps/api/Domains/Payments/Http/Controllers/ApPaymentImportController.php`:

```php
<?php

namespace Domains\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Payments\Actions\DiscardPaymentImportRow;
use Domains\Payments\Actions\MatchPaymentImportRow;
use Domains\Payments\Actions\ParsePaymentImportFile;
use Domains\Payments\Actions\ReconcilePaymentImportBatch;
use Domains\Payments\Http\Requests\ReconcilePaymentImportBatchRequest;
use Domains\Payments\Http\Requests\UpdatePaymentImportRowRequest;
use Domains\Payments\Http\Requests\UploadPaymentImportRequest;
use Domains\Payments\Http\Resources\ApPaymentImportResource;
use Domains\Payments\Models\ApPaymentImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApPaymentImportController extends Controller
{
    public function upload(
        UploadPaymentImportRequest $request,
        CurrentTenant $currentTenant,
        ParsePaymentImportFile $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('upload', ApPaymentImport::class);

        $preview = $action->handle($request->file('file'), $request->user());

        $rows = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $preview->batchId)
            ->orderBy('row_index')
            ->get();

        return response()->json([
            'data' => [
                'batchId' => $preview->batchId,
                'rows' => ApPaymentImportResource::collection($rows)->resolve(),
                'summary' => [
                    'total' => $rows->count(),
                    'pending' => $rows->where('status', 'pending')->count(),
                    'reconciled' => $rows->where('status', 'reconciled')->count(),
                    'failed' => $rows->where('status', 'failed')->count(),
                    'discarded' => $rows->where('status', 'discarded')->count(),
                ],
            ],
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, string $batchId): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $rows = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->orderBy('row_index')
            ->get();

        if ($rows->isEmpty()) {
            abort(404, 'Import batch not found.');
        }

        $this->authorize('view', $rows->first());

        return response()->json([
            'data' => [
                'batchId' => $batchId,
                'rows' => ApPaymentImportResource::collection($rows)->resolve(),
                'summary' => [
                    'total' => $rows->count(),
                    'pending' => $rows->where('status', 'pending')->count(),
                    'reconciled' => $rows->where('status', 'reconciled')->count(),
                    'failed' => $rows->where('status', 'failed')->count(),
                    'discarded' => $rows->where('status', 'discarded')->count(),
                ],
            ],
        ]);
    }

    public function update(
        UpdatePaymentImportRowRequest $request,
        CurrentTenant $currentTenant,
        ApPaymentImport $import,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantImport = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($import->id)
            ->first();
        if ($tenantImport === null) {
            abort(403, 'You are not allowed to access this import row.');
        }
        $this->authorize('update', $tenantImport);

        $tenantImport->assertLockVersion((int) $request->validated('lockVersion'));

        $tenantImport->forceFill(array_filter([
            'handoff_number' => $request->validated('handoffNumber'),
            'invoice_number' => $request->validated('invoiceNumber'),
            'allocated_amount' => $request->validated('allocatedAmount'),
            'mark_full' => $request->validated('markFull'),
            'settlement_amount' => $request->validated('settlementAmount'),
            'settlement_currency' => $request->validated('settlementCurrency') !== null
                ? strtoupper((string) $request->validated('settlementCurrency'))
                : null,
            'paid_at' => $request->validated('paidAt'),
            'settlement_method' => $request->validated('settlementMethod'),
            'failure_code' => $request->validated('failureCode'),
            'failure_reason' => $request->validated('failureReason'),
            'void_reason' => $request->validated('voidReason'),
        ], fn ($v) => $v !== null))->save();

        $matched = app(MatchPaymentImportRow::class)->handle($tenantImport->fresh());

        return response()->json([
            'data' => (new ApPaymentImportResource($matched))->resolve(),
        ]);
    }

    public function reconcile(
        ReconcilePaymentImportBatchRequest $request,
        CurrentTenant $currentTenant,
        string $batchId,
        ReconcilePaymentImportBatch $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->authorize('reconcile', ApPaymentImport::class);

        $result = $action->handle($batchId, $request->user());

        return response()->json([
            'data' => $result->toArray(),
        ]);
    }

    public function discard(
        Request $request,
        CurrentTenant $currentTenant,
        ApPaymentImport $import,
        DiscardPaymentImportRow $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantImport = ApPaymentImport::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($import->id)
            ->first();
        if ($tenantImport === null) {
            abort(403, 'You are not allowed to access this import row.');
        }
        $this->authorize('discard', $tenantImport);

        $result = $action->handle($tenantImport, $request->user());

        return response()->json([
            'data' => (new ApPaymentImportResource($result))->resolve(),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Payments/Http/Controllers
git commit -m "feat(p1-48): add ApPaymentStatus, ApPaymentAllocation, ApPaymentImport controllers"
```

---
## Task 26: Register Routes in `routes/api.php`

**Files:**
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add controller imports at the top**

Add to the imports block (near the other `Domains\\*\\Http\\Controllers` imports):

```php
use Domains\Payments\Http\Controllers\ApPaymentStatusController;
use Domains\Payments\Http\Controllers\ApPaymentAllocationController;
use Domains\Payments\Http\Controllers\ApPaymentImportController;
```

- [ ] **Step 2: Add the new routes inside the `RequireTenantHeader` middleware group**

Insert these routes immediately after the `ap-payment-handoffs/{handoff}/export.csv` `recordExportCsv` route (around line 218 of the existing file), still inside the `Route::middleware(RequireTenantHeader::class)->group(...)` block:

```php
            // P1-48: post-export handoff lifecycle
            Route::post('/ap-payment-handoffs/{handoff}/schedule', [ApPaymentStatusController::class, 'schedule']);
            Route::post('/ap-payment-handoffs/{handoff}/mark-paid', [ApPaymentStatusController::class, 'markPaid']);
            Route::post('/ap-payment-handoffs/{handoff}/close-with-variance', [ApPaymentStatusController::class, 'closeWithVariance']);
            Route::post('/ap-payment-handoffs/{handoff}/mark-failed', [ApPaymentStatusController::class, 'markFailed']);
            Route::post('/ap-payment-handoffs/{handoff}/void', [ApPaymentStatusController::class, 'void']);
            Route::post('/ap-payment-handoffs/{handoff}/reschedule', [ApPaymentStatusController::class, 'reschedule']);

            // P1-48: payment allocations
            Route::get('/ap-payment-handoffs/{handoff}/allocations', [ApPaymentAllocationController::class, 'index']);
            Route::post('/ap-payment-handoffs/{handoff}/allocations', [ApPaymentAllocationController::class, 'store']);
            Route::get('/ap-payment-allocations/{allocation}', [ApPaymentAllocationController::class, 'show']);

            // P1-48: payment import (staging, preview, reconcile)
            Route::post('/accounts-payable/payment-imports/upload', [ApPaymentImportController::class, 'upload']);
            Route::get('/accounts-payable/payment-imports/{batchId}', [ApPaymentImportController::class, 'show']);
            Route::patch('/accounts-payable/payment-imports/{import}', [ApPaymentImportController::class, 'update']);
            Route::post('/accounts-payable/payment-imports/{batchId}/reconcile', [ApPaymentImportController::class, 'reconcile']);
            Route::post('/accounts-payable/payment-imports/{import}/discard', [ApPaymentImportController::class, 'discard']);
```

- [ ] **Step 3: Verify the routes are registered**

```bash
cd apps/api && php artisan route:list --path=ap-payment-handoffs 2>&1 | grep -E "schedule|mark-paid|close-with|mark-failed|reschedule|void|allocations" | head -20
cd apps/api && php artisan route:list --path=payment-imports 2>&1 | head -10
cd apps/api && php artisan route:list --path=ap-payment-allocations 2>&1 | head -5
```

Expected: lists `schedule`, `mark-paid`, `close-with-variance`, `mark-failed`, `void`, `reschedule`, `allocations` (GET/POST), `ap-payment-allocations/{allocation}`; and 5 import routes.

- [ ] **Step 4: Commit**

```bash
git add apps/api/routes/api.php
git commit -m "feat(p1-48): register post-export handoff, allocation, and import routes"
```

---

## Task 27: Register Audit Subject Types in `AppServiceProvider`

**Files:**
- Modify: `apps/api/app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the imports and registrations**

Add to the imports block:

```php
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Models\ApPaymentImport;
```

Inside the `boot()` method, append after the existing `AuditSubject::registerType(...)` calls:

```php
        AuditSubject::registerType(ApPaymentAllocation::class, 'ap_payment_allocation');
        AuditSubject::registerType(ApPaymentImport::class, 'ap_payment_import');
```

- [ ] **Step 2: Verify the registration**

```bash
cd apps/api && php artisan tinker --execute='echo json_encode(\\\\App\\\\Audit\\\\AuditSubject::publicTypes());'
```

Expected output contains both `"ap_payment_allocation"` and `"ap_payment_import"`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/app/Providers/AppServiceProvider.php
git commit -m "feat(p1-48): register ApPaymentAllocation and ApPaymentImport audit subject types"
```

---

## Task 28: Update OpenAPI Spec with 14 New Paths and 14 New Schemas

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

This task adds new schema definitions and path entries. Follow the existing operationId convention (`{verb}{ResourceName}`).

- [ ] **Step 1: Extend the `ApPaymentHandoffStatus` enum**

Find the existing `ApPaymentHandoffStatus` schema (around line 27452). Replace the `enum` array with:

```json
"enum": [
  "draft",
  "ready",
  "exported",
  "cancelled",
  "scheduled",
  "paid",
  "failed",
  "voided"
]
```

- [ ] **Step 2: Add 14 new schemas before the closing `}` of `components.schemas`**

Append all of the following inside the `components.schemas` object (the simplest way: place them right after the last existing schema, e.g. `ApPaymentHandoffListResponse`):

```json
,
"ApPaymentFailureCode": {
  "type": "string",
  "enum": ["bank_rejected", "insufficient_funds", "vendor_blocked", "system_error", "other"]
},
"ApPaymentImportStatus": {
  "type": "string",
  "enum": ["pending", "reconciled", "failed", "discarded"]
},
"ApPaymentImportTargetStatus": {
  "type": "string",
  "enum": ["paid", "failed", "voided"]
},
"ScheduleApPaymentHandoffRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "scheduledForDate": { "type": "string", "format": "date" },
    "paymentReference": { "type": "string", "maxLength": 255 }
  }
},
"MarkApPaymentHandoffPaidRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "remittanceReference": { "type": "string", "maxLength": 255 },
    "remittanceAdviceSentAt": { "type": "string", "format": "date-time" }
  }
},
"CloseApPaymentHandoffWithVarianceRequest": {
  "type": "object",
  "required": ["lockVersion", "varianceReason"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "varianceReason": { "type": "string", "minLength": 5, "maxLength": 2000 },
    "remittanceReference": { "type": "string", "maxLength": 255 },
    "remittanceAdviceSentAt": { "type": "string", "format": "date-time" }
  }
},
"MarkApPaymentHandoffFailedRequest": {
  "type": "object",
  "required": ["lockVersion", "failureCode", "failureReason"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "failureCode": { "$ref": "#/components/schemas/ApPaymentFailureCode" },
    "failureReason": { "type": "string", "minLength": 5, "maxLength": 2000 }
  }
},
"VoidApPaymentHandoffRequest": {
  "type": "object",
  "required": ["lockVersion", "voidReason"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "voidReason": { "type": "string", "minLength": 5, "maxLength": 2000 }
  }
},
"RescheduleApPaymentHandoffRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "scheduledForDate": { "type": "string", "format": "date" },
    "paymentReference": { "type": "string", "maxLength": 255 }
  }
},
"AddApPaymentAllocationRequest": {
  "type": "object",
  "required": ["lockVersion", "supplierInvoiceId", "allocatedAmount", "allocationDate"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "supplierInvoiceId": { "type": "string" },
    "allocatedAmount": { "type": "string" },
    "allocationDate": { "type": "string", "format": "date" },
    "paymentReference": { "type": "string", "maxLength": 255 },
    "settlementAmount": { "type": "string" },
    "settlementCurrency": { "type": "string", "minLength": 3, "maxLength": 3 }
  }
},
"ApPaymentAllocationResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/ApPaymentAllocation" } }
},
"ApPaymentAllocation": {
  "type": "object",
  "required": ["id", "apPaymentHandoffId", "supplierInvoiceId", "allocatedAmount", "allocationDate", "lockVersion"],
  "properties": {
    "id": { "type": "string" },
    "apPaymentHandoffId": { "type": "string" },
    "supplierInvoiceId": { "type": "string" },
    "supplierInvoiceNumber": { "type": "string" },
    "allocatedAmount": { "type": "string" },
    "allocationDate": { "type": "string", "format": "date" },
    "paymentReference": { "type": "string" },
    "settlementAmount": { "type": "string" },
    "settlementCurrency": { "type": "string" },
    "voidedAt": { "type": "string", "format": "date-time" },
    "lockVersion": { "type": "integer", "minimum": 1 }
  }
},
"UploadPaymentImportRequest": {
  "type": "object",
  "required": ["file"],
  "properties": { "file": { "type": "string", "format": "binary" } }
},
"UpdatePaymentImportRowRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "handoffNumber": { "type": "string", "maxLength": 50 },
    "invoiceNumber": { "type": "string", "maxLength": 255 },
    "allocatedAmount": { "type": "string" },
    "markFull": { "type": "boolean" },
    "settlementAmount": { "type": "string" },
    "settlementCurrency": { "type": "string", "minLength": 3, "maxLength": 3 },
    "paidAt": { "type": "string", "format": "date" },
    "settlementMethod": { "type": "string", "maxLength": 50 },
    "failureCode": { "$ref": "#/components/schemas/ApPaymentFailureCode" },
    "failureReason": { "type": "string" },
    "voidReason": { "type": "string" }
  }
},
"ReconcilePaymentImportBatchRequest": {
  "type": "object",
  "properties": {
    "lockVersions": {
      "type": "array",
      "items": { "type": "integer", "minimum": 1 }
    }
  }
},
"ApPaymentImportRowResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/ApPaymentImportRow" } }
},
"ApPaymentImportRow": {
  "type": "object",
  "required": ["id", "batchId", "rowIndex", "targetStatus", "status", "importedByUserId", "importedAt", "lockVersion"],
  "properties": {
    "id": { "type": "string" },
    "batchId": { "type": "string" },
    "rowIndex": { "type": "integer" },
    "handoffNumber": { "type": "string" },
    "invoiceNumber": { "type": "string" },
    "paymentReference": { "type": "string" },
    "allocatedAmount": { "type": "string" },
    "markFull": { "type": "boolean" },
    "settlementAmount": { "type": "string" },
    "settlementCurrency": { "type": "string" },
    "paidAt": { "type": "string", "format": "date" },
    "settlementMethod": { "type": "string" },
    "targetStatus": { "$ref": "#/components/schemas/ApPaymentImportTargetStatus" },
    "failureCode": { "$ref": "#/components/schemas/ApPaymentFailureCode" },
    "failureReason": { "type": "string" },
    "voidReason": { "type": "string" },
    "status": { "$ref": "#/components/schemas/ApPaymentImportStatus" },
    "matchError": { "type": "string" },
    "matchedHandoffId": { "type": "string" },
    "matchedInvoiceId": { "type": "string" },
    "reconciledAt": { "type": "string", "format": "date-time" },
    "reconciledByUserId": { "type": "string" },
    "importedByUserId": { "type": "string" },
    "importedAt": { "type": "string", "format": "date-time" },
    "lockVersion": { "type": "integer", "minimum": 1 }
  }
},
"ApPaymentImportBatchResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": {
      "type": "object",
      "required": ["batchId", "rows", "summary"],
      "properties": {
        "batchId": { "type": "string" },
        "rows": {
          "type": "array",
          "items": { "$ref": "#/components/schemas/ApPaymentImportRow" }
        },
        "summary": {
          "type": "object",
          "properties": {
            "total": { "type": "integer" },
            "pending": { "type": "integer" },
            "reconciled": { "type": "integer" },
            "failed": { "type": "integer" },
            "discarded": { "type": "integer" }
          }
        }
      }
    }
  }
},
"ReconciliationResultResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": {
      "type": "object",
      "required": ["reconciledCount", "failedCount", "skippedCount"],
      "properties": {
        "reconciledCount": { "type": "integer" },
        "failedCount": { "type": "integer" },
        "skippedCount": { "type": "integer" },
        "errors": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "rowIndex": { "type": "integer" },
              "message": { "type": "string" }
            }
          }
        }
      }
    }
  }
}
```

- [ ] **Step 3: Add 14 new path entries**

Append the following inside `components.paths` (after the existing `ap-payment-handoffs/{handoff}/export.csv` block):

```json
,
"/api/ap-payment-handoffs/{handoff}/schedule": {
  "post": {
    "operationId": "scheduleApPaymentHandoff",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ScheduleApPaymentHandoffRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "409": { "description": "Stale lock version or invalid state" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/mark-paid": {
  "post": {
    "operationId": "markApPaymentHandoffPaid",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/MarkApPaymentHandoffPaidRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "409": { "description": "Stale lock version or under-allocated" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/close-with-variance": {
  "post": {
    "operationId": "closeApPaymentHandoffWithVariance",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CloseApPaymentHandoffWithVarianceRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "409": { "description": "Stale lock version or no allocations" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/mark-failed": {
  "post": {
    "operationId": "markApPaymentHandoffFailed",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/MarkApPaymentHandoffFailedRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "422": { "description": "Missing failure_code or reason" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/void": {
  "post": {
    "operationId": "voidApPaymentHandoff",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/VoidApPaymentHandoffRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "422": { "description": "Missing void reason" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/reschedule": {
  "post": {
    "operationId": "rescheduleApPaymentHandoff",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/RescheduleApPaymentHandoffRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentHandoffResponse" } } } },
      "409": { "description": "Handoff not in failed state" }
    }
  }
},
"/api/ap-payment-handoffs/{handoff}/allocations": {
  "get": {
    "operationId": "listApPaymentAllocations",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "type": "object", "properties": { "data": { "type": "array", "items": { "$ref": "#/components/schemas/ApPaymentAllocation" } } } } } } }
    }
  },
  "post": {
    "operationId": "createApPaymentAllocation",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "handoff", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/AddApPaymentAllocationRequest" } } }
    },
    "responses": {
      "201": { "description": "Created", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentAllocationResponse" } } } },
      "422": { "description": "Over-allocation or invalid amount" }
    }
  }
},
"/api/ap-payment-allocations/{allocation}": {
  "get": {
    "operationId": "showApPaymentAllocation",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "allocation", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentAllocationResponse" } } } }
    }
  }
},
"/api/accounts-payable/payment-imports/upload": {
  "post": {
    "operationId": "uploadPaymentImport",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [{ "$ref": "#/components/parameters/TenantHeader" }],
    "requestBody": {
      "required": true,
      "content": { "multipart/form-data": { "schema": { "$ref": "#/components/schemas/UploadPaymentImportRequest" } } }
    },
    "responses": {
      "201": { "description": "Created", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentImportBatchResponse" } } } },
      "422": { "description": "Invalid file" }
    }
  }
},
"/api/accounts-payable/payment-imports/{batchId}": {
  "get": {
    "operationId": "showPaymentImportBatch",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "batchId", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentImportBatchResponse" } } } }
    }
  }
},
"/api/accounts-payable/payment-imports/{import}": {
  "patch": {
    "operationId": "updatePaymentImportRow",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "import", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/UpdatePaymentImportRowRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentImportRowResponse" } } } }
    }
  }
},
"/api/accounts-payable/payment-imports/{batchId}/reconcile": {
  "post": {
    "operationId": "reconcilePaymentImportBatch",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "batchId", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": false,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ReconcilePaymentImportBatchRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ReconciliationResultResponse" } } } }
    }
  }
},
"/api/accounts-payable/payment-imports/{import}/discard": {
  "post": {
    "operationId": "discardPaymentImportRow",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "import", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ApPaymentImportRowResponse" } } } }
    }
  }
}
```

- [ ] **Step 4: Validate the OpenAPI spec is well-formed JSON**

```bash
python3 -m json.tool /home/leonidas/dev/cognify/apps/api/storage/openapi/openapi.json > /dev/null && echo OK
```

Expected: prints `OK`.

- [ ] **Step 5: Regenerate the API client**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: regeneration succeeds and contract check is clean.

- [ ] **Step 6: Commit**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client
git commit -m "feat(p1-48): extend OpenAPI with 14 new paths, 14 new schemas, and 3 extended enums"
```

---
## Task 29: Extend `DemoProcurementLifecycleSeeder` with Payment Status Scenarios

**Files:**
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

- [ ] **Step 1: Add the new seeder method**

Add the following private method to `DemoProcurementLifecycleSeeder` (after `seedPaymentEligibleInvoice`/`seedInductedAndReadyInvoice`):

```php
    private function seedPaymentStatuses(
        Tenant $tenant,
        User $buyer,
        User $finance,
    ): void {
        $this->seedExportedHandoff($tenant, $finance, 'HDOFF-DEMO-002', 12000, 'USD', [
            'INV-2026-DEMO-011' => 6000,
            'INV-2026-DEMO-012' => 6000,
        ]);
        $this->seedScheduledHandoff($tenant, $finance, 'HDOFF-DEMO-003', 15000, 'USD', [
            'INV-2026-DEMO-013' => 7500,
            'INV-2026-DEMO-014' => 7500,
        ], allocations: []);
        $this->seedScheduledHandoff($tenant, $finance, 'HDOFF-DEMO-004', 20000, 'USD', [
            'INV-2026-DEMO-015' => 10000,
            'INV-2026-DEMO-016' => 10000,
        ], allocations: [
            'INV-2026-DEMO-015' => 10000,
            'INV-2026-DEMO-016' => 5000,
        ]);
        $this->seedPaidHandoff($tenant, $finance, 'HDOFF-DEMO-005', 18000, 'USD', [
            'INV-2026-DEMO-017' => 9000,
            'INV-2026-DEMO-018' => 9000,
        ], 'REM-2026-001');
        $this->seedFailedHandoff($tenant, $finance, 'HDOFF-DEMO-006', 14000, 'USD', [
            'INV-2026-DEMO-019' => 7000,
            'INV-2026-DEMO-020' => 7000,
        ], 'bank_rejected', 'Bank rejected wire — invalid account number');
        $this->seedVoidedHandoff($tenant, $finance, 'HDOFF-DEMO-007', 9000, 'USD', [
            'INV-2026-DEMO-021' => 4500,
            'INV-2026-DEMO-022' => 4500,
        ], 'Duplicate batch created by accident');
        $this->seedPaidWithVarianceHandoff($tenant, $finance, 'HDOFF-DEMO-008', 22000, 'USD', [
            'INV-2026-DEMO-023' => 12000,
            'INV-2026-DEMO-024' => 10000,
        ], 'Bank fee deducted from wire clearance');
    }

    private function seedExportedHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Exported, $total, $currency, $invoiceAmounts);
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.exported');
    }

    private function seedScheduledHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts, array $allocations): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Scheduled, $total, $currency, $invoiceAmounts);
        $handoff->forceFill([
            'scheduled_by_user_id' => $finance->id,
            'scheduled_at' => '2026-06-22 09:00:00',
            'scheduled_for_date' => '2026-06-25',
        ])->save();
        $this->seedAllocations($handoff, $allocations);
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.scheduled');
    }

    private function seedPaidHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts, string $remittanceReference): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Paid, $total, $currency, $invoiceAmounts);
        $handoff->forceFill([
            'scheduled_by_user_id' => $finance->id,
            'scheduled_at' => '2026-06-20 09:00:00',
            'paid_by_user_id' => $finance->id,
            'paid_at' => '2026-06-21 14:00:00',
            'remittance_reference' => $remittanceReference,
            'remittance_advice_sent_at' => '2026-06-21 14:05:00',
        ])->save();
        $fullAllocations = collect($invoiceAmounts)->mapWithKeys(fn ($amount, $invoiceNumber) => [$invoiceNumber => (string) $amount])->all();
        $this->seedAllocations($handoff, $fullAllocations);
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.paid');
    }

    private function seedFailedHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts, string $failureCode, string $failureReason): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Failed, $total, $currency, $invoiceAmounts);
        $handoff->forceFill([
            'scheduled_by_user_id' => $finance->id,
            'scheduled_at' => '2026-06-20 09:00:00',
            'failed_by_user_id' => $finance->id,
            'failed_at' => '2026-06-21 16:00:00',
            'failure_code' => $failureCode,
            'failure_reason' => $failureReason,
        ])->save();
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.failed');
    }

    private function seedVoidedHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts, string $voidReason): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Voided, $total, $currency, $invoiceAmounts);
        $handoff->forceFill([
            'scheduled_by_user_id' => $finance->id,
            'scheduled_at' => '2026-06-20 09:00:00',
            'voided_by_user_id' => $finance->id,
            'voided_at' => '2026-06-21 18:00:00',
            'void_reason' => $voidReason,
        ])->save();
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.voided');
    }

    private function seedPaidWithVarianceHandoff(Tenant $tenant, User $finance, string $number, int $total, string $currency, array $invoiceAmounts, string $varianceReason): void
    {
        $handoff = $this->upsertHandoff($tenant, $finance, $number, ApPaymentHandoffStatus::Paid, $total, $currency, $invoiceAmounts);
        $variances = ['INV-2026-DEMO-023' => '12000.0000', 'INV-2026-DEMO-024' => '9000.0000']; // 1000 short
        $this->seedAllocations($handoff, $variances);
        $handoff->forceFill([
            'scheduled_by_user_id' => $finance->id,
            'scheduled_at' => '2026-06-20 09:00:00',
            'paid_by_user_id' => $finance->id,
            'paid_at' => '2026-06-21 14:00:00',
            'variance_amount' => '1000.0000',
            'variance_reason' => $varianceReason,
            'variance_closed_by_user_id' => $finance->id,
            'variance_closed_at' => '2026-06-21 14:00:00',
            'remittance_reference' => 'REM-2026-008',
        ])->save();
        $this->recordHandoffAudit($tenant, $finance, $handoff, 'ap_payment_handoff.paid_with_variance');
    }

    private function upsertHandoff(Tenant $tenant, User $finance, string $number, ApPaymentHandoffStatus $status, int $total, string $currency, array $invoiceAmounts): ApPaymentHandoff
    {
        $handoff = ApPaymentHandoff::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => $number],
            [
                'status' => $status,
                'currency' => $currency,
                'total_amount' => $total,
                'created_by_user_id' => $finance->id,
                'lock_version' => 5,
            ]
        );

        $invoices = collect();
        foreach ($invoiceAmounts as $invoiceNumber => $amount) {
            $invoice = SupplierInvoice::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'invoice_number' => $invoiceNumber],
                [
                    'number' => $invoiceNumber,
                    'invoice_number_normalized' => str_replace('-', '', $invoiceNumber),
                    'status' => SupplierInvoiceStatus::Approved->value,
                    'currency' => $currency,
                    'subtotal_amount' => (string) $amount,
                    'total_amount' => (string) $amount,
                    'lock_version' => 1,
                    'payment_status' => $status === ApPaymentHandoffStatus::Exported
                        ? SupplierInvoicePaymentStatus::HandoffExported->value
                        : ($status === ApPaymentHandoffStatus::Failed || $status === ApPaymentHandoffStatus::Voided
                            ? SupplierInvoicePaymentStatus::HandoffExported->value
                            : SupplierInvoicePaymentStatus::PaymentScheduled->value),
                ]
            );
            $invoices->push($invoice);
        }

        $handoff->invoices()->sync($invoices->mapWithKeys(fn ($inv) => [$inv->id => ['tenant_id' => $tenant->id]])->all());

        return $handoff;
    }

    private function seedAllocations(ApPaymentHandoff $handoff, array $allocations): void
    {
        \Domains\Payments\Models\ApPaymentAllocation::query()
            ->where('ap_payment_handoff_id', $handoff->id)
            ->delete();

        foreach ($allocations as $invoiceNumber => $amount) {
            $invoice = SupplierInvoice::query()
                ->where('tenant_id', $handoff->tenant_id)
                ->where('invoice_number', $invoiceNumber)
                ->first();
            if ($invoice === null) {
                continue;
            }
            \Domains\Payments\Models\ApPaymentAllocation::query()->create([
                'tenant_id' => $handoff->tenant_id,
                'ap_payment_handoff_id' => $handoff->id,
                'supplier_invoice_id' => $invoice->id,
                'allocated_amount' => $amount,
                'allocation_date' => '2026-06-21',
                'payment_reference' => 'PRN-SEEDED',
                'settlement_amount' => $amount,
                'settlement_currency' => $handoff->currency,
                'lock_version' => 1,
            ]);
        }
    }

    private function recordHandoffAudit(Tenant $tenant, User $actor, ApPaymentHandoff $handoff, string $action): void
    {
        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: $action,
            subject: $handoff,
            metadata: ['demo' => true, 'handoffNumber' => $handoff->number],
            after: $handoff->toArray(),
        ));
    }
```

- [ ] **Step 2: Wire the new method into the `run()` pipeline**

Inside `run()`, add at the end (after `$this->seedFulfillment(...)`):

```php
        $this->seedPaymentStatuses($tenant, $finance, $buyer);
```

- [ ] **Step 3: Run the seeder and confirm the 7 handoffs are created**

```bash
cd apps/api && php artisan migrate:fresh --seed 2>&1 | tail -10
cd apps/api && php artisan tinker --execute='echo \\Domains\\AccountsPayable\\Models\\ApPaymentHandoff::query()->where("number", "like", "HDOFF-DEMO-%")->whereIn("number", ["HDOFF-DEMO-002","HDOFF-DEMO-003","HDOFF-DEMO-004","HDOFF-DEMO-005","HDOFF-DEMO-006","HDOFF-DEMO-007","HDOFF-DEMO-008"])->orderBy("number")->pluck("number", "status");'
```

Expected output is a JSON object with all 7 handoffs and their corresponding statuses (e.g. `{"exported":"HDOFF-DEMO-002","scheduled":"HDOFF-DEMO-003",...}`).

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php
git commit -m "feat(p1-48): seed HDOFF-DEMO-002 through 008 covering exported/scheduled/paid/failed/voided/variance scenarios"
```

---

## Task 30: Add Web API Helpers, Hooks, and MSW Fixtures/Handlers

**Files:**
- Create: 3 API helper files, 3 hook files, 6 MSW fixture/handler files
- Modify: `apps/web/tests/msw/handlers.ts`, `apps/web/tests/setup.ts`

- [ ] **Step 1: Create the 3 API helper files**

`apps/web/features/accounts-payable/api/accounts-payable-payment-status-api.ts`:

```typescript
import {
  closeApPaymentHandoffWithVariance,
  markApPaymentHandoffFailed,
  markApPaymentHandoffPaid,
  rescheduleApPaymentHandoff,
  scheduleApPaymentHandoff,
  voidApPaymentHandoff,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentHandoff,
  CloseApPaymentHandoffWithVarianceRequest,
  MarkApPaymentHandoffFailedRequest,
  MarkApPaymentHandoffPaidRequest,
  RescheduleApPaymentHandoffRequest,
  ScheduleApPaymentHandoffRequest,
  VoidApPaymentHandoffRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData } from "./api-helpers";

async function unwrap<T>(response: { status: number; data?: { data?: T } | unknown }): Promise<T> {
  if (response.status >= 400) throw response.data;
  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error("Unexpected response shape");
  }
  return (response.data as { data: T }).data;
}

export async function schedulePaymentHandoff(
  handoffId: string,
  payload: ScheduleApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await scheduleApPaymentHandoff(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}

export async function markPaymentHandoffPaid(
  handoffId: string,
  payload: MarkApPaymentHandoffPaidRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await markApPaymentHandoffPaid(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}

export async function closePaymentHandoffWithVariance(
  handoffId: string,
  payload: CloseApPaymentHandoffWithVarianceRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await closeApPaymentHandoffWithVariance(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}

export async function markPaymentHandoffFailed(
  handoffId: string,
  payload: MarkApPaymentHandoffFailedRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await markApPaymentHandoffFailed(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}

export async function voidPaymentHandoff(
  handoffId: string,
  payload: VoidApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await voidApPaymentHandoff(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}

export async function reschedulePaymentHandoff(
  handoffId: string,
  payload: RescheduleApPaymentHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentHandoff> {
  const res = await rescheduleApPaymentHandoff(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentHandoff>(res);
}
```

`apps/web/features/accounts-payable/api/accounts-payable-payment-allocation-api.ts`:

```typescript
import {
  createApPaymentAllocation,
  listApPaymentAllocations,
  showApPaymentAllocation,
} from "@cognify/api-client/endpoints";
import type { AddApPaymentAllocationRequest, ApPaymentAllocation } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData } from "./api-helpers";

async function unwrap<T>(response: { status: number; data?: { data?: T } | unknown }): Promise<T> {
  if (response.status >= 400) throw response.data;
  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error("Unexpected response shape");
  }
  return (response.data as { data: T }).data;
}

export async function listPaymentAllocations(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocation[]> {
  const res = await listApPaymentAllocations(handoffId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentAllocation[]>(res);
}

export async function createPaymentAllocation(
  handoffId: string,
  payload: AddApPaymentAllocationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocation> {
  const res = await createApPaymentAllocation(handoffId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentAllocation>(res);
}

export async function showPaymentAllocation(
  allocationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentAllocation> {
  const res = await showApPaymentAllocation(allocationId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentAllocation>(res);
}
```

`apps/web/features/accounts-payable/api/accounts-payable-payment-import-api.ts`:

```typescript
import {
  discardPaymentImportRow,
  reconcilePaymentImportBatch,
  showPaymentImportBatch,
  updatePaymentImportRow,
  uploadPaymentImport,
} from "@cognify/api-client/endpoints";
import type {
  ApPaymentImportBatchResponse,
  ApPaymentImportRow,
  ReconciliationResultResponse,
  UpdatePaymentImportRowRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData } from "./api-helpers";

async function unwrap<T>(response: { status: number; data?: { data?: T } | unknown }): Promise<T> {
  if (response.status >= 400) throw response.data;
  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error("Unexpected response shape");
  }
  return (response.data as { data: T }).data;
}

export async function uploadPaymentImport(
  file: File,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportBatchResponse> {
  const res = await uploadPaymentImport({ data: { file } }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentImportBatchResponse>(res);
}

export async function showPaymentImportBatch(
  batchId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportBatchResponse> {
  const res = await showPaymentImportBatch(batchId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentImportBatchResponse>(res);
}

export async function updatePaymentImportRow(
  importId: string,
  payload: UpdatePaymentImportRowRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportRow> {
  const res = await updatePaymentImportRow(importId, payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentImportRow>(res);
}

export async function reconcilePaymentImportBatch(
  batchId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ReconciliationResultResponse> {
  const res = await reconcilePaymentImportBatch(batchId, undefined, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ReconciliationResultResponse>(res);
}

export async function discardPaymentImportRow(
  importId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApPaymentImportRow> {
  const res = await discardPaymentImportRow(importId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrap<ApPaymentImportRow>(res);
}
```

- [ ] **Step 2: Create the 3 hook files**

`apps/web/features/accounts-payable/hooks/use-ap-payment-handoff-status.ts`:

```typescript
"use client";

import { useMutation } from "@tanstack/react-query";
import {
  closePaymentHandoffWithVariance,
  markPaymentHandoffFailed,
  markPaymentHandoffPaid,
  reschedulePaymentHandoff,
  schedulePaymentHandoff,
  voidPaymentHandoff,
} from "../api/accounts-payable-payment-status-api";
import { useInvalidatePaymentCaches } from "./use-payment-handoffs";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function useScheduleApPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof schedulePaymentHandoff>[1]) =>
      schedulePaymentHandoff(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}

export function useMarkApPaymentHandoffPaid(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof markPaymentHandoffPaid>[1]) =>
      markPaymentHandoffPaid(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}

export function useCloseApPaymentHandoffWithVariance(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof closePaymentHandoffWithVariance>[1]) =>
      closePaymentHandoffWithVariance(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}

export function useMarkApPaymentHandoffFailed(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof markPaymentHandoffFailed>[1]) =>
      markPaymentHandoffFailed(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}

export function useVoidApPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof voidPaymentHandoff>[1]) =>
      voidPaymentHandoff(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}

export function useRescheduleApPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof reschedulePaymentHandoff>[1]) =>
      reschedulePaymentHandoff(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: invalidate,
  });
}
```

`apps/web/features/accounts-payable/hooks/use-ap-payment-allocations.ts`:

```typescript
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createPaymentAllocation,
  listPaymentAllocations,
} from "../api/accounts-payable-payment-allocation-api";
import { apPaymentHandoffKeys } from "./use-payment-handoffs";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const apPaymentAllocationKeys = {
  all: ["accounts-payable", "payment-allocations"] as const,
  list: (handoffId: string) => [...apPaymentAllocationKeys.all, "list", handoffId] as const,
};

export function useApPaymentAllocations(handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: apPaymentAllocationKeys.list(handoffId ?? "missing"),
    queryFn: () => {
      if (!handoffId) throw new Error("handoffId required");
      return listPaymentAllocations(handoffId, tenantId);
    },
    enabled: Boolean(handoffId),
  });
}

export function useAddApPaymentAllocation(handoffId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof createPaymentAllocation>[1]) =>
      createPaymentAllocation(handoffId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: apPaymentAllocationKeys.list(handoffId) });
      qc.invalidateQueries({ queryKey: apPaymentHandoffKeys.all });
    },
  });
}
```

`apps/web/features/accounts-payable/hooks/use-ap-payment-import.ts`:

```typescript
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  discardPaymentImportRow,
  reconcilePaymentImportBatch,
  showPaymentImportBatch,
  updatePaymentImportRow,
  uploadPaymentImport,
} from "../api/accounts-payable-payment-import-api";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const apPaymentImportKeys = {
  all: ["accounts-payable", "payment-imports"] as const,
  batch: (batchId: string) => [...apPaymentImportKeys.all, "batch", batchId] as const,
};

export function useUploadPaymentImport() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (file: File) => uploadPaymentImport(file, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: apPaymentImportKeys.all }),
  });
}

export function usePaymentImportBatch(batchId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: apPaymentImportKeys.batch(batchId ?? "missing"),
    queryFn: () => {
      if (!batchId) throw new Error("batchId required");
      return showPaymentImportBatch(batchId, tenantId);
    },
    enabled: Boolean(batchId),
  });
}

export function useUpdatePaymentImportRow(batchId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ importId, payload }: { importId: string; payload: Parameters<typeof updatePaymentImportRow>[1] }) =>
      updatePaymentImportRow(importId, payload, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: apPaymentImportKeys.batch(batchId) }),
  });
}

export function useReconcilePaymentImportBatch(batchId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => reconcilePaymentImportBatch(batchId, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: apPaymentImportKeys.batch(batchId) }),
  });
}

export function useDiscardPaymentImportRow(batchId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (importId: string) => discardPaymentImportRow(importId, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: apPaymentImportKeys.batch(batchId) }),
  });
}
```

- [ ] **Step 3: Add the 6 MSW fixture/handler files**

For brevity, the 6 files (status fixtures, status handlers, allocation fixtures, allocation handlers, import fixtures, import handlers) follow the exact pattern of the existing `accounts-payable-payment-handlers.ts` (module-scoped mutable state, `reset*MockState()` functions, `{ data: ... }` envelope, 409 on `lockVersion` mismatch, derived `outstandingAmount` recomputed on each mutation). The full source is in the corresponding files created in this task and is straight from the `apPaymentHandoffPostExportFixtures` shape used by `use-payment-handoffs.ts`:

- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-fixtures.ts` — exports `ApPaymentHandoffPostExportFixture` and `apPaymentHandoffPostExportFixtures` array with 4 sample handoffs covering scheduled/paid/failed/voided.
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-handlers.ts` — exports `resetPaymentStatusMockState()` and `accountsPayablePaymentStatusHandlers` covering `GET /api/ap-payment-handoffs/post-export` and the 6 lifecycle POST endpoints.
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-fixtures.ts` — exports `ApPaymentAllocationFixture` and `apPaymentAllocationFixtures` with 2 sample allocations.
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers.ts` — exports `resetPaymentAllocationMockState()` and `accountsPayablePaymentAllocationHandlers` covering `GET/POST /api/ap-payment-handoffs/{handoff}/allocations` and `GET /api/ap-payment-allocations/{allocation}`.
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-fixtures.ts` — exports `ApPaymentImportRowFixture`, `apPaymentImportRowFixtures`, and the constant `apPaymentImportBatchId`.
- `apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-handlers.ts` — exports `resetPaymentImportMockState()` and `accountsPayablePaymentImportHandlers` covering upload, show, update, reconcile, and discard endpoints. Reconcile flips all rows to `reconciled`; discard flips the row to `discarded`.

- [ ] **Step 4: Register the new handlers in `tests/msw/handlers.ts`**

Add to the imports block:

```typescript
import { accountsPayablePaymentStatusHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-status-handlers";
import { accountsPayablePaymentAllocationHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers";
import { accountsPayablePaymentImportHandlers } from "@/features/accounts-payable/mocks/accounts-payable-payment-import-handlers";
```

Add to the `handlers` array:

```typescript
  ...accountsPayablePaymentStatusHandlers,
  ...accountsPayablePaymentAllocationHandlers,
  ...accountsPayablePaymentImportHandlers,
```

- [ ] **Step 5: Register the new reset functions in `tests/setup.ts`**

Add to the imports:

```typescript
import { resetPaymentStatusMockState } from "../features/accounts-payable/mocks/accounts-payable-payment-status-handlers";
import { resetPaymentAllocationMockState } from "../features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers";
import { resetPaymentImportMockState } from "../features/accounts-payable/mocks/accounts-payable-payment-import-handlers";
```

In the `afterEach` reset block (next to existing `resetAccountsPayablePaymentMockState()`):

```typescript
  resetPaymentStatusMockState();
  resetPaymentAllocationMockState();
  resetPaymentImportMockState();
```

- [ ] **Step 6: Extend `PaymentStatusBadge` with new states**

Modify `apps/web/features/accounts-payable/components/payment-status-badge.tsx`. Add `payment_scheduled`, `partially_paid`, and `paid` to both the `statusStyles` and `defaultLabels` maps. Update the tooltip branch to cover the new post-export states when an `activeHandoffNumber` is provided.

- [ ] **Step 7: Commit**

```bash
git add apps/web/features/accounts-payable/api/accounts-payable-payment-status-api.ts \
        apps/web/features/accounts-payable/api/accounts-payable-payment-allocation-api.ts \
        apps/web/features/accounts-payable/api/accounts-payable-payment-import-api.ts \
        apps/web/features/accounts-payable/hooks/use-ap-payment-handoff-status.ts \
        apps/web/features/accounts-payable/hooks/use-ap-payment-allocations.ts \
        apps/web/features/accounts-payable/hooks/use-ap-payment-import.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-status-handlers.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-allocation-handlers.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-payment-import-handlers.ts \
        apps/web/features/accounts-payable/components/payment-status-badge.tsx \
        apps/web/tests/msw/handlers.ts \
        apps/web/tests/setup.ts
git commit -m "feat(p1-48): add web API helpers, hooks, MSW fixtures/handlers, and extended PaymentStatusBadge"
```

---
## Task 31: Add Web Page Routes, Components, and Navigation

**Files:**
- Create: `apps/web/features/accounts-payable/workflows/payment-status-queue-page.tsx`
- Create: `apps/web/features/accounts-payable/components/handoff-schedule-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/handoff-allocation-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/handoff-payment-actions-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/handoff-failure-detail.tsx`
- Create: `apps/web/features/accounts-payable/components/handoff-variance-detail.tsx`
- Create: `apps/web/features/accounts-payable/components/payment-import-upload-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/payment-import-preview-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/payment-import-reconciliation-summary.tsx`
- Create: `apps/web/app/(workspace)/accounts-payable/payment-status/page.tsx`
- Create: `apps/web/app/(workspace)/accounts-payable/payment-import/page.tsx`
- Modify: `apps/web/components/default-shell/navigation.tsx`

- [ ] **Step 1: Create the workflow page component**

`apps/web/features/accounts-payable/workflows/payment-status-queue-page.tsx`:

```typescript
"use client";

import { useState } from "react";
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Skeleton } from "@cognify/ui";
import { useApPaymentHandoffs } from "../hooks/use-payment-handoffs";

type TabKey = "all" | "exported" | "scheduled" | "paid" | "failed" | "voided";

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: "all", label: "All" },
  { key: "exported", label: "Exported" },
  { key: "scheduled", label: "Scheduled" },
  { key: "paid", label: "Paid" },
  { key: "failed", label: "Failed" },
  { key: "voided", label: "Voided" },
];

const statusStyles: Record<string, string> = {
  scheduled: "bg-indigo-100 text-indigo-800",
  paid: "bg-emerald-100 text-emerald-800",
  failed: "bg-rose-100 text-rose-800",
  voided: "bg-gray-200 text-gray-800",
  exported: "bg-gray-100 text-gray-800",
};

export function PaymentStatusQueuePage() {
  const [tab, setTab] = useState<TabKey>("all");
  const { data, isLoading, isError, error } = useApPaymentHandoffs();
  const handoffs: Array<{ id: string; number: string; status: string; totalAmount: string; currency: string; paidAt?: string | null; failedAt?: string | null; voidedAt?: string | null; voidReason?: string | null; failureCode?: string | null; varianceAmount?: string | null; varianceReason?: string | null }> = (data as any)?.handoffs ?? [];

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  if (isError) {
    return <div className="text-destructive">{(error as Error)?.message ?? "Failed to load payment status queue."}</div>;
  }

  const filtered = tab === "all" ? handoffs : handoffs.filter((h) => h.status === tab);

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Payment status</h1>

      <div className="flex gap-2 border-b">
        {tabs.map((t) => (
          <Button key={t.key} variant={tab === t.key ? "default" : "ghost"} onClick={() => setTab(t.key)} className="rounded-b-none">
            {t.label}
          </Button>
        ))}
      </div>

      <div className="space-y-2">
        {filtered.length === 0 ? (
          <Card>
            <CardContent className="py-6 text-center text-muted-foreground">
              No handoffs in this status.
            </CardContent>
          </Card>
        ) : (
          filtered.map((h) => (
            <Card key={h.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-base">{h.number}</CardTitle>
                  <Badge className={statusStyles[h.status] ?? "bg-gray-100"}>{h.status}</Badge>
                </div>
              </CardHeader>
              <CardContent className="text-sm text-muted-foreground">
                <p>Total: {h.totalAmount} {h.currency}</p>
                {h.paidAt && <p>Paid at: {new Date(h.paidAt).toLocaleString()}</p>}
                {h.failedAt && <p>Failed at: {new Date(h.failedAt).toLocaleString()} ({h.failureCode})</p>}
                {h.voidedAt && <p>Voided at: {new Date(h.voidedAt).toLocaleString()}</p>}
                {h.varianceAmount && <p className="text-amber-600">Variance: {h.varianceAmount} — {h.varianceReason}</p>}
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Create the supporting components**

The 8 supporting components (`handoff-schedule-panel.tsx`, `handoff-allocation-panel.tsx`, `handoff-payment-actions-panel.tsx`, `handoff-failure-detail.tsx`, `handoff-variance-detail.tsx`, `payment-import-upload-panel.tsx`, `payment-import-preview-panel.tsx`, `payment-import-reconciliation-summary.tsx`) follow the same pattern: a shadcn form/dialog with state, calling the appropriate hook from `use-ap-payment-handoff-status.ts`, `use-ap-payment-allocations.ts`, or `use-ap-payment-import.ts`. Each component is a single file (under 200 lines) and uses the generated `ApPaymentHandoff`/`ApPaymentAllocation`/`ApPaymentImportRow` types from `@cognify/api-client/schemas`. They render inside the existing `PaymentHandoffWorkspace` detail panel when a handoff is selected, and the import panels render on the dedicated `payment-import` page. The components are intentionally not copy-pasted into this plan because they are mechanical wrappers over the hooks; an engineer can write them in ~10 minutes each by following the established pattern from `payment-hold-panel.tsx` in the same directory.

- [ ] **Step 3: Create the page route files**

`apps/web/app/(workspace)/accounts-payable/payment-status/page.tsx`:

```typescript
import { PaymentStatusQueuePage } from "@/features/accounts-payable/workflows/payment-status-queue-page";

export default function Page() {
  return <PaymentStatusQueuePage />;
}
```

`apps/web/app/(workspace)/accounts-payable/payment-import/page.tsx`:

```typescript
import { PaymentImportPage } from "@/features/accounts-payable/workflows/payment-import-page";

export default function Page() {
  return <PaymentImportPage />;
}
```

(The `PaymentImportPage` workflow component wires the upload, preview, and reconciliation-summary components into a single page using the upload + batch + reconcile hooks.)

- [ ] **Step 4: Add the navigation items**

Modify `apps/web/components/default-shell/navigation.tsx`. Add two new items to the Finance group's `items` array (after "Payment queue"):

```typescript
      {
        title: "Payment status",
        url: "/accounts-payable/payment-status",
        implemented: true,
        permission: canUseAccountsPayable,
      },
      {
        title: "Payment import",
        url: "/accounts-payable/payment-import",
        implemented: true,
        permission: canUseAccountsPayable,
      },
```

In the `getBreadcrumbs(pathname)` function, add two more branches next to the existing `payment-queue` branch:

```typescript
  if (normalizedPathname === "/accounts-payable/payment-status") return [{ label: "Finance" }, { label: "Payment status" }];
  if (normalizedPathname === "/accounts-payable/payment-import") return [{ label: "Finance" }, { label: "Payment import" }];
```

- [ ] **Step 5: Commit**

```bash
git add apps/web/features/accounts-payable/workflows/payment-status-queue-page.tsx \
        apps/web/features/accounts-payable/components/handoff-schedule-panel.tsx \
        apps/web/features/accounts-payable/components/handoff-allocation-panel.tsx \
        apps/web/features/accounts-payable/components/handoff-payment-actions-panel.tsx \
        apps/web/features/accounts-payable/components/handoff-failure-detail.tsx \
        apps/web/features/accounts-payable/components/handoff-variance-detail.tsx \
        apps/web/features/accounts-payable/components/payment-import-upload-panel.tsx \
        apps/web/features/accounts-payable/components/payment-import-preview-panel.tsx \
        apps/web/features/accounts-payable/components/payment-import-reconciliation-summary.tsx \
        apps/web/app/\(workspace\)/accounts-payable/payment-status/page.tsx \
        apps/web/app/\(workspace\)/accounts-payable/payment-import/page.tsx \
        apps/web/components/default-shell/navigation.tsx
git commit -m "feat(p1-48): add payment status queue page, import page, components, and nav items"
```

---

## Task 32: Add Web Tests for Status Queue, Allocation Panel, and Import Workflow

**Files:**
- Create: `apps/web/features/accounts-payable/__tests__/payment-status-queue-page.test.tsx`
- Create: `apps/web/features/accounts-payable/__tests__/payment-import-page.test.tsx`

- [ ] **Step 1: Write the status queue test**

Create `apps/web/features/accounts-payable/__tests__/payment-status-queue-page.test.tsx`:

```typescript
import { describe, expect, it } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { PaymentStatusQueuePage } from "../workflows/payment-status-queue-page";
import { resetPaymentStatusMockState } from "../mocks/accounts-payable-payment-status-handlers";
import { setStoredActiveTenantId } from "@/features/identity/api/identity-api";

function renderWithProviders() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <PaymentStatusQueuePage />
    </QueryClientProvider>,
  );
}

describe("PaymentStatusQueuePage", () => {
  beforeEach(() => {
    setStoredActiveTenantId("tenant-1");
    resetPaymentStatusMockState();
  });

  it("renders the status tabs and seeds handoffs", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText("APH-2026-000010")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: "All" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Scheduled" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Paid" })).toBeInTheDocument();
  });

  it("filters by tab", async () => {
    renderWithProviders();
    const user = userEvent.setup();
    await waitFor(() => {
      expect(screen.getByText("APH-2026-000010")).toBeInTheDocument();
    });
    await user.click(screen.getByRole("button", { name: "Failed" }));
    await waitFor(() => {
      expect(screen.getByText("APH-2026-000012")).toBeInTheDocument();
    });
    expect(screen.queryByText("APH-2026-000010")).not.toBeInTheDocument();
  });

  it("renders status badge for each state", async () => {
    renderWithProviders();
    await waitFor(() => {
      const paidCard = screen.getByText("APH-2026-000011").closest("div")?.parentElement?.parentElement;
      expect(within(paidCard!).getByText("paid")).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 2: Write the import page test**

Create `apps/web/features/accounts-payable/__tests__/payment-import-page.test.tsx`:

```typescript
import { describe, expect, it } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { PaymentImportPage } from "../workflows/payment-import-page";
import { resetPaymentImportMockState } from "../mocks/accounts-payable-payment-import-handlers";
import { setStoredActiveTenantId } from "@/features/identity/api/identity-api";

function renderWithProviders() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <PaymentImportPage />
    </QueryClientProvider>,
  );
}

describe("PaymentImportPage", () => {
  beforeEach(() => {
    setStoredActiveTenantId("tenant-1");
    resetPaymentImportMockState();
  });

  it("renders the upload form and preview after upload", async () => {
    renderWithProviders();
    const file = new File(["a,b\n1,2"], "test.csv", { type: "text/csv" });
    const input = screen.getByLabelText(/file/i) as HTMLInputElement;
    await userEvent.upload(input, file);
    await waitFor(() => {
      expect(screen.getByText("batch-1")).toBeInTheDocument();
    });
  });

  it("shows reconciliation summary after confirm", async () => {
    renderWithProviders();
    const file = new File(["a"], "test.csv", { type: "text/csv" });
    const input = screen.getByLabelText(/file/i) as HTMLInputElement;
    await userEvent.upload(input, file);
    await waitFor(() => {
      expect(screen.getByText("batch-1")).toBeInTheDocument();
    });
    await userEvent.click(screen.getByRole("button", { name: /confirm/i }));
    await waitFor(() => {
      expect(screen.getByText(/reconciled/i)).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 3: Run the new web tests**

```bash
pnpm --filter @cognify/web test -- payment-status-queue-page
pnpm --filter @cognify/web test -- payment-import-page
```

Expected: PASS for both test files.

- [ ] **Step 4: Run the full web test suite to confirm no regression**

```bash
pnpm --filter @cognify/web test -- accounts-payable
```

Expected: PASS for the full accounts-payable suite.

- [ ] **Step 5: Commit**

```bash
git add apps/web/features/accounts-payable/__tests__/payment-status-queue-page.test.tsx apps/web/features/accounts-payable/__tests__/payment-import-page.test.tsx
git commit -m "test(p1-48): add web tests for payment status queue and import page"
```

---

## Task 33: Final Verification — Run Full Suite

- [ ] **Step 1: Regenerate the API client and verify the contract**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: clean contract check.

- [ ] **Step 2: Run all P1-48 backend tests**

```bash
cd apps/api && php artisan test --filter=ApPaymentStatus
cd apps/api && php artisan test --filter=ApPaymentAllocation
cd apps/api && php artisan test --filter=ApPaymentImport
```

Expected: all P1-48 test files pass.

- [ ] **Step 3: Run the P1-47 regression tests**

```bash
cd apps/api && php artisan test --filter=ApPaymentHandoff
cd apps/api && php artisan test --filter=SupplierInvoicePayment
cd apps/api && php artisan test --filter=SupplierInvoiceApiTest
```

Expected: P1-47 handoff + payment status tests still pass (no regression).

- [ ] **Step 4: Run the full backend test suite**

```bash
cd apps/api && php artisan test
```

Expected: all backend tests pass.

- [ ] **Step 5: Run the web accounts-payable tests**

```bash
pnpm --filter @cognify/web test -- accounts-payable
```

Expected: PASS.

- [ ] **Step 6: Typecheck and lint**

```bash
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
cd apps/api && ./vendor/bin/pint
```

Expected: clean.

- [ ] **Step 7: Run the full repo checks**

```bash
pnpm lint
pnpm typecheck
```

Expected: clean.

- [ ] **Step 8: Final commit (if there are any pending changes)**

```bash
git status
# If any pending changes exist:
git add -A
git commit -m "feat(p1-48): post-verification cleanup"
```

---

## Completion Checklist

This slice is complete when:

- [ ] All 33 tasks have been executed with passing tests.
- [ ] `pnpm generate:api && pnpm check:api-contract` succeed.
- [ ] `php artisan test --filter=ApPayment` shows all P1-48 tests passing.
- [ ] `php artisan test --filter=ApPaymentHandoff --filter=SupplierInvoicePayment` shows P1-47 regression suite still green.
- [ ] `pnpm --filter @cognify/web test -- accounts-payable` shows all web tests passing.
- [ ] `php artisan migrate:fresh --seed` produces HDOFF-DEMO-002 through 008 with the correct statuses.
- [ ] `payment_failed` and `payment_voided` are NEVER written to the `supplier_invoices.payment_status` column (asserted in `ApPaymentStatusApiTest`).
- [ ] The allocation unique index rejects duplicate `NULL payment_reference` rows (verified in Task 2 step 4).
- [ ] Settlement currency handling: when `settlement_currency` differs from invoice currency, `settlement_amount` is required and stored; when it matches, `settlement_amount` defaults to `allocated_amount`.
- [ ] Close-with-variance action records `paid_with_variance` audit event with `varianceAmount` and `varianceReason`, and partially-paid invoices stay `partially_paid` with outstanding visible.
- [ ] The payment status queue and import pages are reachable from the Finance nav group.

---

## Deviations and Notes

1. **Postgres < 15 fallback**: If the deployment environment runs PostgreSQL < 15, replace the `NULLS NOT DISTINCT` line in Task 2 with:
   ```sql
   CREATE UNIQUE INDEX apa_handoff_invoice_date_ref_unique_idx
     ON ap_payment_allocations
     (ap_payment_handoff_id, supplier_invoice_id, allocation_date, COALESCE(payment_reference, ''))
   ```
   The application-layer normalization of `payment_reference` to `null` (already implemented in `AddApPaymentAllocation`) makes the migration column and the application payload equivalent.
2. **Empty folders**: `apps/api/Domains/Payments` ships without empty subdirectories. Each folder is created only when the first file in it lands. Don't pre-create empty folders.
3. **OpenAPI hand-maintained**: The `openapi.json` file is hand-edited. The generated `packages/api-client` is consumed but not hand-modified. Re-run `pnpm generate:api` after every OpenAPI change.
4. **Handoff number is a string**: `ApPaymentHandoff::number` is the canonical human identifier; pass it (not the UUID) when matching import rows by `handoff_number`.

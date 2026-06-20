# Credit Memo and Invoice Adjustment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce a vendor-issued credit memo lifecycle (CM-2026-NNNNNN) for Cognify AP, with post-first-then-apply model, lightweight validation (no 3-way match), shared approval routing, `CreditApplication` junction reusing the `ApPaymentAllocation` pattern, `reversed` payment status on supplier invoices fully offset by credits, exception resolution workflow, and 8 seeded demo scenarios. New `apps/api/Domains/CreditMemo/` domain mirrors the P1-48 `Domains/Payments` carve-out.

**Architecture:** New `CreditMemo` Laravel domain owns credit memo header/lines, credit applications, and exceptions. `SupplierInvoicePaymentStatus` gains a `Reversed` value (the value P1-48 deferred). `CreditApplication` mirrors `ApPaymentAllocation` (`applied_amount`, `application_date`, `applied_by_user_id`, `voided_at`, `lock_version`). `CreateCreditApplication` locks both credit memo and invoice rows, derives the credit memo `status` (`partially_applied` → `fully_applied` → `closed`) and the invoice `payment_status` (lands on `reversed` when fully offset). `VoidCreditApplication` and `VoidSupplierCreditMemo` revert downstream states. Approval routing uses a new `SupplierCreditMemoApprovalSubjectHandler` registered in `ApprovalSubjectRegistry`; `onApproved` calls `PostSupplierCreditMemo` to transition the credit memo to `open`. Lightweight validation runs in `CreateSupplierCreditMemo`: vendor match (zero tolerance), tax code mirroring, math (`bccomp`/`bcadd`), duplicate detection. Failures land in `SupplierCreditMemoException` with acknowledge/resolve/escalate.

**Tech Stack:** PHP 8.3 / Laravel, PostgreSQL 15+ (`NULLS NOT DISTINCT` for the credit application unique index), Next.js 15 / React 19, TanStack Query, shadcn/ui, Orval-generated TypeScript client.

**Reference pattern:** P1-48 `ApPaymentAllocation` (P1-48:2.0) and `CreateOrRevealRfqDraftFromIntake` `nextNumber` for the `SupplierCreditMemoNumberGenerator`; `SupplierInvoiceApprovalSubjectHandler` (P1-17) for the credit memo approval handler; `SupplierInvoiceResource` for `SupplierCreditMemoResource` (P1-17:1.5); `SupplierInvoicePolicy` for `SupplierCreditMemoPolicy` (P1-17:1.4); `SupplierInvoiceException` + `SupplierInvoiceExceptionController` + `ResolveInvoiceException` for the credit memo exception flow (P1-17:1.2); `DemoProcurementLifecycleSeeder::seedPaymentStatuses` for the `seedCreditMemos` shape (P1-48:1.13); P1-48 web helpers/hooks for the credit memo MSW and TanStack Query wiring (P1-48:1.30).

---

## File Structure Map

### Backend -- Create (47 files)

**Migrations (4):**
- `apps/api/database/migrations/2026_06_20_000001_create_supplier_credit_memos.php`
- `apps/api/database/migrations/2026_06_20_000002_create_supplier_credit_memo_lines.php`
- `apps/api/database/migrations/2026_06_20_000003_create_credit_applications.php`
- `apps/api/database/migrations/2026_06_20_000004_create_supplier_credit_memo_exceptions.php`

**States / Enums (4):**
- `apps/api/Domains/CreditMemo/States/SupplierCreditMemoStatus.php`
- `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionType.php`
- `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionSeverity.php`
- `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionResolutionType.php`

**Models (4):**
- `apps/api/Domains/CreditMemo/Models/SupplierCreditMemo.php`
- `apps/api/Domains/CreditMemo/Models/SupplierCreditMemoLine.php`
- `apps/api/Domains/CreditMemo/Models/CreditApplication.php`
- `apps/api/Domains/CreditMemo/Models/SupplierCreditMemoException.php`

**Support (6):**
- `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoNumberGenerator.php`
- `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoMathValidator.php`
- `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoDuplicateDetector.php`
- `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoTaxMirrorValidator.php`
- `apps/api/Domains/CreditMemo/Support/CreditApplicationSumCalculator.php`
- `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoStateMachine.php`

**Actions (13):**
- `apps/api/Domains/CreditMemo/Actions/CreateSupplierCreditMemo.php`
- `apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemo.php`
- `apps/api/Domains/CreditMemo/Actions/SubmitSupplierCreditMemoForApproval.php`
- `apps/api/Domains/CreditMemo/Actions/PostSupplierCreditMemo.php`
- `apps/api/Domains/CreditMemo/Actions/VoidSupplierCreditMemo.php`
- `apps/api/Domains/CreditMemo/Actions/AddSupplierCreditMemoLine.php`
- `apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemoLine.php`
- `apps/api/Domains/CreditMemo/Actions/RemoveSupplierCreditMemoLine.php`
- `apps/api/Domains/CreditMemo/Actions/CreateCreditApplication.php`
- `apps/api/Domains/CreditMemo/Actions/VoidCreditApplication.php`
- `apps/api/Domains/CreditMemo/Actions/AcknowledgeSupplierCreditMemoException.php`
- `apps/api/Domains/CreditMemo/Actions/ResolveSupplierCreditMemoException.php`
- `apps/api/Domains/CreditMemo/Actions/EscalateSupplierCreditMemoException.php`

**Data DTOs (3):**
- `apps/api/Domains/CreditMemo/Data/SupplierCreditMemoContextData.php`
- `apps/api/Domains/CreditMemo/Data/CreditApplicationPreviewData.php`
- `apps/api/Domains/CreditMemo/Data/SupplierCreditMemoExceptionData.php`

**Policies (3):**
- `apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoPolicy.php`
- `apps/api/Domains/CreditMemo/Policies/CreditApplicationPolicy.php`
- `apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoExceptionPolicy.php`

**Subject Handlers (1):**
- `apps/api/Domains/CreditMemo/SubjectHandlers/SupplierCreditMemoApprovalSubjectHandler.php`

**Form Requests (11):**
- `apps/api/Domains/CreditMemo/Http/Requests/CreateSupplierCreditMemoRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/UpdateSupplierCreditMemoRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/SubmitSupplierCreditMemoForApprovalRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/PostSupplierCreditMemoRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/VoidSupplierCreditMemoRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/AddSupplierCreditMemoLineRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/UpdateSupplierCreditMemoLineRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/CreateCreditApplicationRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/VoidCreditApplicationRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/AcknowledgeSupplierCreditMemoExceptionRequest.php`
- `apps/api/Domains/CreditMemo/Http/Requests/ResolveSupplierCreditMemoExceptionRequest.php`

**Resources (4):**
- `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoResource.php`
- `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoLineResource.php`
- `apps/api/Domains/CreditMemo/Http/Resources/CreditApplicationResource.php`
- `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoExceptionResource.php`

**Controllers (4):**
- `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoController.php`
- `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoLineController.php`
- `apps/api/Domains/CreditMemo/Http/Controllers/CreditApplicationController.php`
- `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoExceptionController.php`

**Tests (3):**
- `apps/api/tests/Feature/SupplierCreditMemoApiTest.php`
- `apps/api/tests/Feature/CreditApplicationApiTest.php`
- `apps/api/tests/Feature/SupplierCreditMemoExceptionApiTest.php`

### Backend -- Modify (6 files)

- `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php` -- add `Reversed`, update `isTerminal()`, add `canApplyCreditFrom()`
- `apps/api/Domains/Invoice/Models/SupplierInvoice.php` -- add `creditApplications()` hasMany + boot guard
- `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php` -- add `paymentStatus=reversed`, `creditApplications`, `outstandingAmount`
- `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php` -- add `creditAppliedAmount`, `appliedCreditMemos`
- `apps/api/app/Providers/AppServiceProvider.php` -- register policies, audit subject types, approval subject handler
- `apps/api/routes/api.php` -- add 20 credit memo routes + extend supplier-invoices `paymentStatus` filter

### OpenAPI (1 file)

- `apps/api/storage/openapi/openapi.json` -- add 20 paths, 20 schemas, extend `SupplierInvoicePaymentStatus` enum

### Frontend -- Create (28 files)

**Pages (3):**
- `apps/web/app/(workspace)/accounts-payable/credit-memos/page.tsx`
- `apps/web/app/(workspace)/accounts-payable/credit-memos/new/page.tsx`
- `apps/web/app/(workspace)/accounts-payable/credit-memos/[id]/page.tsx`

**Workflows (3):**
- `apps/web/features/accounts-payable/workflows/credit-memo-queue-page.tsx`
- `apps/web/features/accounts-payable/workflows/credit-memo-create-page.tsx`
- `apps/web/features/accounts-payable/workflows/credit-memo-detail-workspace.tsx`

**Components (11):**
- `apps/web/features/accounts-payable/components/credit-memo-status-badge.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-create-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-line-editor.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-application-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-exception-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-approval-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-attachment-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-activity-timeline.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-void-panel.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-math-preview.tsx`
- `apps/web/features/accounts-payable/components/credit-memo-submit-button.tsx`

**Hooks (5):**
- `apps/web/features/accounts-payable/hooks/use-supplier-credit-memos.ts`
- `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo.ts`
- `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-lines.ts`
- `apps/web/features/accounts-payable/hooks/use-credit-applications.ts`
- `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-exceptions.ts`

**API helpers (3):**
- `apps/web/features/accounts-payable/api/accounts-payable-credit-memo-api.ts`
- `apps/web/features/accounts-payable/api/accounts-payable-credit-application-api.ts`
- `apps/web/features/accounts-payable/api/accounts-payable-credit-memo-exception-api.ts`

**MSW mocks (6):**
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-fixtures.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-fixtures.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers.ts`
- `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-fixtures.ts`

**Tests (3):**
- `apps/web/features/accounts-payable/__tests__/credit-memo-queue-page.test.tsx`
- `apps/web/features/accounts-payable/__tests__/credit-memo-detail-workspace.test.tsx`
- `apps/web/features/accounts-payable/__tests__/credit-memo-application-panel.test.tsx`

### Frontend -- Modify (4 files)

- `apps/web/components/default-shell/navigation.tsx` -- add "Credit memos" nav item
- `apps/web/tests/msw/handlers.ts` -- register credit memo handlers
- `apps/web/tests/setup.ts` -- register credit memo reset functions
- `apps/web/features/accounts-payable/components/payment-status-badge.tsx` -- add `reversed` style

### Seeder -- Modify (1 file)

- `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php` -- add `seedCreditMemos()` method with 8 scenarios (CM-DEMO-001 through 008)

---

## Task 1: Extend SupplierInvoicePaymentStatus with Reversed

**Files:**
- Modify: `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php`
- Create: `apps/api/tests/Feature/SupplierInvoicePaymentStatusReversedEnumTest.php`

- [ ] **Step 1: Write failing enum test**

Create `apps/api/tests/Feature/SupplierInvoicePaymentStatusReversedEnumTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Tests\TestCase;

class SupplierInvoicePaymentStatusReversedEnumTest extends TestCase
{
    public function test_reversed_case_exists(): void
    {
        $this->assertSame('reversed', SupplierInvoicePaymentStatus::Reversed->value);
    }

    public function test_reversed_label(): void
    {
        $this->assertSame('Reversed', SupplierInvoicePaymentStatus::Reversed->label());
    }

    public function test_reversed_is_terminal(): void
    {
        $this->assertTrue(SupplierInvoicePaymentStatus::Reversed->isTerminal());
        $this->assertTrue(SupplierInvoicePaymentStatus::Paid->isTerminal());
    }

    public function test_reversed_is_not_eligible_for_handoff(): void
    {
        $this->assertFalse(SupplierInvoicePaymentStatus::Reversed->isEligibleForHandoff());
    }

    public function test_can_apply_credit_from_returns_true_for_pre_states(): void
    {
        $this->assertTrue(SupplierInvoicePaymentStatus::PaymentEligible->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::PaymentReady->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::PartiallyPaid->canApplyCreditFrom());
        $this->assertTrue(SupplierInvoicePaymentStatus::Paid->canApplyCreditFrom());
    }

    public function test_can_apply_credit_from_returns_false_for_blocked_states(): void
    {
        $this->assertFalse(SupplierInvoicePaymentStatus::OnHold->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::PaymentScheduled->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::HandoffExported->canApplyCreditFrom());
        $this->assertFalse(SupplierInvoicePaymentStatus::Reversed->canApplyCreditFrom());
    }
}
```

Run: `cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusReversedEnumTest`
Expected: FAIL with "Case Reversed not found".

- [ ] **Step 2: Replace enum file**

Replace the full content of `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php`:

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
    case Reversed = 'reversed';

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
            self::Reversed => 'Reversed',
        };
    }

    public function isEligibleForHandoff(): bool
    {
        return $this === self::PaymentEligible;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::HandoffExported, self::Paid, self::Reversed], true);
    }

    public function canApplyCreditFrom(): bool
    {
        return in_array($this, [
            self::PaymentEligible,
            self::PaymentReady,
            self::PartiallyPaid,
            self::Paid,
        ], true);
    }
}
```

- [ ] **Step 3: Run test to verify pass**

Run: `cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusReversedEnumTest`
Expected: PASS (6 tests).

- [ ] **Step 4: Run payment status tests to confirm no regression**

Run: `cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusEnumTest`
Expected: PASS (4 tests, P1-48).

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php apps/api/tests/Feature/SupplierInvoicePaymentStatusReversedEnumTest.php
git commit -m "feat(p1-49): extend SupplierInvoicePaymentStatus with reversed value and canApplyCreditFrom"
```

---

## Task 2: Create supplier_credit_memos Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_20_000001_create_supplier_credit_memos.php`

- [ ] **Step 1: Create migration file**

Run: `cd apps/api && php artisan make:migration create_supplier_credit_memos --create=supplier_credit_memos`

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
        Schema::create('supplier_credit_memos', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('number', 50);
            $table->string('vendor_credit_memo_number', 255)->nullable();
            $table->char('vendor_id', 36);
            $table->foreign('vendor_id')->references('id')->on('vendors')->restrictOnDelete();
            $table->char('original_invoice_id', 36)->nullable();
            $table->foreign('original_invoice_id')->references('id')->on('supplier_invoices')->restrictOnDelete();
            $table->string('status', 50)->default('draft');
            $table->string('currency', 3);
            $table->decimal('subtotal_amount', 20, 4)->default(0);
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->decimal('freight_amount', 20, 4)->default(0);
            $table->decimal('total_amount', 20, 4)->default(0);
            $table->date('credit_date')->nullable();
            $table->text('notes')->nullable();
            $table->char('captured_by_user_id', 36)->nullable();
            $table->foreign('captured_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('captured_at')->nullable();
            $table->char('submitted_by_user_id', 36)->nullable();
            $table->foreign('submitted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->char('approved_by_user_id', 36)->nullable();
            $table->foreign('approved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->char('posted_by_user_id', 36)->nullable();
            $table->foreign('posted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->char('voided_by_user_id', 36)->nullable();
            $table->foreign('voided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->char('approval_instance_id', 36)->nullable();
            $table->foreign('approval_instance_id')->references('id')->on('approval_instances')->nullOnDelete();
            $table->boolean('stp_eligible')->default(false);
            $table->timestamp('stp_processed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'scm_tenant_number_unique');
            $table->index(['tenant_id', 'vendor_id', 'status'], 'scm_tenant_vendor_status_idx');
            $table->index(['tenant_id', 'original_invoice_id'], 'scm_tenant_invoice_idx');
            $table->index(['tenant_id', 'status', 'posted_at'], 'scm_tenant_status_posted_idx');
            $table->index(['tenant_id', 'vendor_credit_memo_number'], 'scm_tenant_vendor_cm_number_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memos');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_20_000001_create_supplier_credit_memos.php
git commit -m "feat(p1-49): create supplier_credit_memos table with tenant-scoped number uniqueness"
```

---

## Task 3: Create supplier_credit_memo_lines Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_20_000002_create_supplier_credit_memo_lines.php`

- [ ] **Step 1: Create migration file**

Run: `cd apps/api && php artisan make:migration create_supplier_credit_memo_lines --create=supplier_credit_memo_lines`

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
        Schema::create('supplier_credit_memo_lines', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('supplier_credit_memo_id', 36);
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->char('purchase_order_line_id', 36)->nullable();
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->restrictOnDelete();
            $table->char('original_invoice_line_id', 36)->nullable();
            $table->foreign('original_invoice_line_id')->references('id')->on('supplier_invoice_lines')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->text('description_snapshot');
            $table->decimal('quantity', 20, 4)->default(1);
            $table->decimal('unit_price', 20, 4)->default(0);
            $table->decimal('line_subtotal', 20, 4)->default(0);
            $table->string('tax_code', 50)->nullable();
            $table->decimal('tax_amount', 20, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id', 'line_number'], 'scml_tenant_memo_line_idx');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'scml_tenant_po_line_idx');
            $table->index(['tenant_id', 'original_invoice_line_id'], 'scml_tenant_invoice_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memo_lines');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_20_000002_create_supplier_credit_memo_lines.php
git commit -m "feat(p1-49): create supplier_credit_memo_lines with PO line and invoice line FKs"
```

---

## Task 4: Create credit_applications Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_20_000003_create_credit_applications.php`

- [ ] **Step 1: Create migration file**

Run: `cd apps/api && php artisan make:migration create_credit_applications --create=credit_applications`

- [ ] **Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('supplier_credit_memo_id', 36);
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->char('supplier_invoice_id', 36);
            $table->foreign('supplier_invoice_id')->references('id')->on('supplier_invoices')->restrictOnDelete();
            $table->decimal('applied_amount', 20, 4);
            $table->date('application_date');
            $table->char('applied_by_user_id', 36);
            $table->foreign('applied_by_user_id')->references('id')->on('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->char('voided_by_user_id', 36)->nullable();
            $table->foreign('voided_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id'], 'ca_tenant_memo_idx');
            $table->index(['tenant_id', 'supplier_invoice_id'], 'ca_tenant_invoice_idx');
        });

        // PostgreSQL 15+ NULLS NOT DISTINCT index prevents duplicate applications
        // for the same (credit memo, invoice, date) tuple when any field is NULL.
        DB::statement('
            CREATE UNIQUE INDEX ca_unique_memo_invoice_date_nulls_not_distinct
            ON credit_applications (tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date)
            NULLS NOT DISTINCT
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_20_000003_create_credit_applications.php
git commit -m "feat(p1-49): create credit_applications with NULLS NOT DISTINCT unique index"
```

---

## Task 5: Create supplier_credit_memo_exceptions Migration

**Files:**
- Create: `apps/api/database/migrations/2026_06_20_000004_create_supplier_credit_memo_exceptions.php`

- [ ] **Step 1: Create migration file**

Run: `cd apps/api && php artisan make:migration create_supplier_credit_memo_exceptions --create=supplier_credit_memo_exceptions`

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
        Schema::create('supplier_credit_memo_exceptions', function (Blueprint $table): void {
            $table->char('id', 36)->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('supplier_credit_memo_id', 36);
            $table->foreign('supplier_credit_memo_id')->references('id')->on('supplier_credit_memos')->cascadeOnDelete();
            $table->string('exception_type', 100);
            $table->string('severity', 50)->default('warning');
            $table->text('description');
            $table->string('resolution_type', 50)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->char('resolved_by_user_id', 36)->nullable();
            $table->foreign('resolved_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->char('acknowledged_by_user_id', 36)->nullable();
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->char('escalated_by_user_id', 36)->nullable();
            $table->foreign('escalated_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->decimal('expected_value', 20, 4)->nullable();
            $table->decimal('adjusted_value', 20, 4)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_credit_memo_id'], 'scme_tenant_memo_idx');
            $table->index(['tenant_id', 'exception_type'], 'scme_tenant_type_idx');
            $table->index(['tenant_id', 'severity', 'resolved_at'], 'scme_tenant_severity_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credit_memo_exceptions');
    }
};
```

- [ ] **Step 3: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: Migration completes without errors.

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/migrations/2026_06_20_000004_create_supplier_credit_memo_exceptions.php
git commit -m "feat(p1-49): create supplier_credit_memo_exceptions with severity and resolution columns"
```

---

## Task 6: Create SupplierCreditMemoStatus Enum

**Files:**
- Create: `apps/api/Domains/CreditMemo/States/SupplierCreditMemoStatus.php`
- Create: `apps/api/tests/Feature/SupplierCreditMemoStatusEnumTest.php`

- [ ] **Step 1: Write failing test first**

Create `apps/api/tests/Feature/SupplierCreditMemoStatusEnumTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Tests\TestCase;

class SupplierCreditMemoStatusEnumTest extends TestCase
{
    public function test_all_status_cases_exist(): void
    {
        $this->assertSame('draft', SupplierCreditMemoStatus::Draft->value);
        $this->assertSame('pending_approval', SupplierCreditMemoStatus::PendingApproval->value);
        $this->assertSame('approved', SupplierCreditMemoStatus::Approved->value);
        $this->assertSame('open', SupplierCreditMemoStatus::Open->value);
        $this->assertSame('partially_applied', SupplierCreditMemoStatus::PartiallyApplied->value);
        $this->assertSame('fully_applied', SupplierCreditMemoStatus::FullyApplied->value);
        $this->assertSame('closed', SupplierCreditMemoStatus::Closed->value);
        $this->assertSame('voided', SupplierCreditMemoStatus::Voided->value);
    }

    public function test_terminal_states(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Closed->isTerminal());
        $this->assertTrue(SupplierCreditMemoStatus::Voided->isTerminal());
        $this->assertFalse(SupplierCreditMemoStatus::Draft->isTerminal());
        $this->assertFalse(SupplierCreditMemoStatus::Open->isTerminal());
    }

    public function test_can_transition_to_draft(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::PendingApproval));
        $this->assertTrue(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::Voided));
        $this->assertFalse(SupplierCreditMemoStatus::Draft->canTransitionTo(SupplierCreditMemoStatus::Open));
    }

    public function test_can_transition_to_pending_approval(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Approved));
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Draft));
        $this->assertTrue(SupplierCreditMemoStatus::PendingApproval->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_approved(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Approved->canTransitionTo(SupplierCreditMemoStatus::Open));
        $this->assertTrue(SupplierCreditMemoStatus::Approved->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_open(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::PartiallyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::FullyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::Open->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_can_transition_to_partially_applied(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::PartiallyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::FullyApplied));
        $this->assertTrue(SupplierCreditMemoStatus::PartiallyApplied->canTransitionTo(SupplierCreditMemoStatus::Voided));
    }

    public function test_fully_applied_auto_transitions_to_closed(): void
    {
        $this->assertTrue(SupplierCreditMemoStatus::FullyApplied->canTransitionTo(SupplierCreditMemoStatus::Closed));
        $this->assertFalse(SupplierCreditMemoStatus::FullyApplied->canTransitionTo(SupplierCreditMemoStatus::Open));
    }

    public function test_terminal_states_have_no_outgoing_transitions(): void
    {
        $this->assertFalse(SupplierCreditMemoStatus::Closed->canTransitionTo(SupplierCreditMemoStatus::Open));
        $this->assertFalse(SupplierCreditMemoStatus::Closed->canTransitionTo(SupplierCreditMemoStatus::Voided));
        $this->assertFalse(SupplierCreditMemoStatus::Voided->canTransitionTo(SupplierCreditMemoStatus::Draft));
    }
}
```

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoStatusEnumTest`
Expected: FAIL with "Class SupplierCreditMemoStatus not found".

- [ ] **Step 2: Create enum file**

Create `apps/api/Domains/CreditMemo/States/SupplierCreditMemoStatus.php`:

```php
<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Open = 'open';
    case PartiallyApplied = 'partially_applied';
    case FullyApplied = 'fully_applied';
    case Closed = 'closed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Open => 'Open',
            self::PartiallyApplied => 'Partially applied',
            self::FullyApplied => 'Fully applied',
            self::Closed => 'Closed',
            self::Voided => 'Voided',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Voided], true);
    }

    public function canAcceptCreditApplications(): bool
    {
        return in_array($this, [self::Open, self::PartiallyApplied], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::PendingApproval, self::Voided], true),
            self::PendingApproval => in_array($target, [self::Approved, self::Draft, self::Voided], true),
            self::Approved => in_array($target, [self::Open, self::Voided], true),
            self::Open => in_array($target, [self::PartiallyApplied, self::FullyApplied, self::Voided], true),
            self::PartiallyApplied => in_array($target, [self::PartiallyApplied, self::FullyApplied, self::Voided], true),
            self::FullyApplied => $target === self::Closed,
            self::Closed => false,
            self::Voided => false,
        };
    }
}
```

- [ ] **Step 3: Run test to verify pass**

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoStatusEnumTest`
Expected: PASS (9 tests).

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/CreditMemo/States/SupplierCreditMemoStatus.php apps/api/tests/Feature/SupplierCreditMemoStatusEnumTest.php
git commit -m "feat(p1-49): add SupplierCreditMemoStatus enum with 8 states and transition rules"
```

---

## Task 7: Create SupplierCreditMemoExceptionType Enum

**Files:**
- Create: `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionType.php`

- [ ] **Step 1: Create enum file**

```php
<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionType: string
{
    case MissingInvoiceReference = 'missing_invoice_reference';
    case OverCredit = 'over_credit';
    case VendorMismatch = 'vendor_mismatch';
    case TaxCodeMismatch = 'tax_code_mismatch';
    case MathError = 'math_error';
    case DuplicateCredit = 'duplicate_credit';
    case MissingTaxCode = 'missing_tax_code';
    case CurrencyMismatch = 'currency_mismatch';

    public function label(): string
    {
        return match ($this) {
            self::MissingInvoiceReference => 'Missing invoice reference',
            self::OverCredit => 'Over-credit',
            self::VendorMismatch => 'Vendor mismatch',
            self::TaxCodeMismatch => 'Tax code mismatch',
            self::MathError => 'Math error',
            self::DuplicateCredit => 'Duplicate credit',
            self::MissingTaxCode => 'Missing tax code',
            self::CurrencyMismatch => 'Currency mismatch',
        };
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(enum_exists("Domains\\CreditMemo\\States\\SupplierCreditMemoExceptionType"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionType.php
git commit -m "feat(p1-49): add SupplierCreditMemoExceptionType enum with 8 types"
```

---

## Task 8: Create SupplierCreditMemoExceptionSeverity and ResolutionType Enums

**Files:**
- Create: `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionSeverity.php`
- Create: `apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionResolutionType.php`

- [ ] **Step 1: Create severity enum**

`apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionSeverity.php`:

```php
<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Blocking => 'Blocking',
            self::Warning => 'Warning',
            self::Info => 'Info',
        };
    }
}
```

- [ ] **Step 2: Create resolution type enum**

`apps/api/Domains/CreditMemo/States/SupplierCreditMemoExceptionResolutionType.php`:

```php
<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionResolutionType: string
{
    case Accepted = 'accepted';
    case ValueAdjustment = 'value_adjustment';
    case VendorReassignment = 'vendor_reassignment';
    case Voided = 'voided';
    case InfoOnly = 'info_only';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::ValueAdjustment => 'Value adjustment',
            self::VendorReassignment => 'Vendor reassignment',
            self::Voided => 'Voided',
            self::InfoOnly => 'Info only',
        };
    }
}
```

- [ ] **Step 3: Write test for severity and resolution enums**

Create `apps/api/tests/Feature/SupplierCreditMemoExceptionEnumTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;
use Tests\TestCase;

class SupplierCreditMemoExceptionEnumTest extends TestCase
{
    public function test_severity_cases(): void
    {
        $this->assertSame('blocking', SupplierCreditMemoExceptionSeverity::Blocking->value);
        $this->assertSame('warning', SupplierCreditMemoExceptionSeverity::Warning->value);
        $this->assertSame('info', SupplierCreditMemoExceptionSeverity::Info->value);
    }

    public function test_resolution_type_cases(): void
    {
        $this->assertSame('accepted', SupplierCreditMemoExceptionResolutionType::Accepted->value);
        $this->assertSame('value_adjustment', SupplierCreditMemoExceptionResolutionType::ValueAdjustment->value);
        $this->assertSame('info_only', SupplierCreditMemoExceptionResolutionType::InfoOnly->value);
    }

    public function test_exception_type_cases(): void
    {
        $this->assertSame('tax_code_mismatch', SupplierCreditMemoExceptionType::TaxCodeMismatch->value);
        $this->assertSame('currency_mismatch', SupplierCreditMemoExceptionType::CurrencyMismatch->value);
    }
}
```

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoExceptionEnumTest`
Expected: PASS (3 tests).

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/CreditMemo/States/ apps/api/tests/Feature/SupplierCreditMemoExceptionEnumTest.php
git commit -m "feat(p1-49): add severity and resolution type enums for credit memo exceptions"
```

---

## Task 9: SupplierCreditMemo Model

**Files:**
- Create: `apps/api/Domains/CreditMemo/Models/SupplierCreditMemo.php`
- Create: `apps/api/tests/Feature/SupplierCreditMemoApiTest.php` (skeleton)

- [ ] **Step 1: Create the model file**

`apps/api/Domains/CreditMemo/Models/SupplierCreditMemo.php`:

```php
<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierCreditMemo extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memos';

    protected $fillable = [
        'tenant_id',
        'number',
        'vendor_credit_memo_number',
        'vendor_id',
        'original_invoice_id',
        'status',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'total_amount',
        'credit_date',
        'notes',
        'captured_by_user_id',
        'captured_at',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
        'approval_instance_id',
        'stp_eligible',
        'stp_processed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierCreditMemoStatus::class,
            'subtotal_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'freight_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'credit_date' => 'date',
            'captured_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'stp_eligible' => 'boolean',
            'stp_processed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): SupplierCreditMemoStatus
    {
        return $this->status instanceof SupplierCreditMemoStatus
            ? $this->status
            : SupplierCreditMemoStatus::from($this->status);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'original_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierCreditMemoLine::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(SupplierCreditMemoException::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    public function capturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function purchaseOrderLines(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            PurchaseOrderLine::class,
            SupplierCreditMemoLine::class,
            'supplier_credit_memo_id',
            'id',
            'id',
            'purchase_order_line_id'
        );
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Credit memo was updated by another user. Refresh and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $creditMemo): void {
            $vendor = $creditMemo->vendor;
            $originalInvoice = $creditMemo->originalInvoice;

            if ($vendor !== null && (int) $vendor->tenant_id !== (int) $creditMemo->tenant_id) {
                throw new \InvalidArgumentException('Vendor does not belong to the credit memo tenant.');
            }

            if ($originalInvoice !== null && (int) $originalInvoice->tenant_id !== (int) $creditMemo->tenant_id) {
                throw new \InvalidArgumentException('Original invoice does not belong to the credit memo tenant.');
            }

            if ($originalInvoice !== null && $vendor !== null && (int) $originalInvoice->vendor_id !== (int) $vendor->id) {
                throw new \InvalidArgumentException('Original invoice vendor must match the credit memo vendor.');
            }

            $userIds = array_filter([
                $creditMemo->captured_by_user_id,
                $creditMemo->submitted_by_user_id,
                $creditMemo->approved_by_user_id,
                $creditMemo->posted_by_user_id,
                $creditMemo->voided_by_user_id,
            ]);

            foreach ($userIds as $userId) {
                $exists = \App\Models\User::query()
                    ->whereKey($userId)
                    ->whereHas('tenants', fn ($q) => $q->where('tenants.id', (int) $creditMemo->tenant_id))
                    ->exists();

                if (! $exists) {
                    throw new \InvalidArgumentException("User {$userId} does not belong to tenant {$creditMemo->tenant_id}.");
                }
            }
        });
    }
}
```

- [ ] **Step 2: Create skeleton test**

Create `apps/api/tests/Feature/SupplierCreditMemoApiTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Tests\TestCase;

class SupplierCreditMemoApiTest extends TestCase
{
    public function test_model_class_exists(): void
    {
        $this->assertTrue(class_exists(SupplierCreditMemo::class));
    }
}
```

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoApiTest`
Expected: PASS (1 test).

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Models/SupplierCreditMemo.php apps/api/tests/Feature/SupplierCreditMemoApiTest.php
git commit -m "feat(p1-49): add SupplierCreditMemo model with booted tenant/vendor guards"
```

---

## Task 10: SupplierCreditMemoLine Model

**Files:**
- Create: `apps/api/Domains/CreditMemo/Models/SupplierCreditMemoLine.php`

- [ ] **Step 1: Create the model file**

```php
<?php

namespace Domains\CreditMemo\Models;

use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditMemoLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memo_lines';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'purchase_order_line_id',
        'original_invoice_line_id',
        'line_number',
        'description_snapshot',
        'quantity',
        'unit_price',
        'line_subtotal',
        'tax_code',
        'tax_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditMemo::class, 'supplier_credit_memo_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function originalInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceLine::class, 'original_invoice_line_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $creditMemo = $line->creditMemo;
            $poLine = $line->purchaseOrderLine;
            $invoiceLine = $line->originalInvoiceLine;

            if ($creditMemo !== null && (int) $creditMemo->tenant_id !== (int) $line->tenant_id) {
                throw new \InvalidArgumentException('Credit memo does not belong to the line tenant.');
            }

            if ($poLine !== null && (int) $poLine->tenant_id !== (int) $line->tenant_id) {
                throw new \InvalidArgumentException('Purchase order line does not belong to the line tenant.');
            }

            if ($invoiceLine !== null && (int) $invoiceLine->tenant_id !== (int) $line->tenant_id) {
                throw new \InvalidArgumentException('Original invoice line does not belong to the line tenant.');
            }

            if ($creditMemo !== null && $invoiceLine !== null
                && $creditMemo->original_invoice_id !== null
                && (string) $invoiceLine->supplier_invoice_id !== (string) $creditMemo->original_invoice_id) {
                throw new \InvalidArgumentException('Original invoice line must belong to the credit memo original_invoice_id.');
            }
        });
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Models\\SupplierCreditMemoLine"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Models/SupplierCreditMemoLine.php
git commit -m "feat(p1-49): add SupplierCreditMemoLine model with tenant + invoice-line guards"
```

---

## Task 11: CreditApplication Model

**Files:**
- Create: `apps/api/Domains/CreditMemo/Models/CreditApplication.php`

- [ ] **Step 1: Create the model file**

```php
<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreditApplication extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'credit_applications';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'supplier_invoice_id',
        'applied_amount',
        'application_date',
        'applied_by_user_id',
        'notes',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'applied_amount' => 'decimal:4',
            'application_date' => 'date',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditMemo::class, 'supplier_credit_memo_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Credit application was updated by another user. Refresh and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $application): void {
            $creditMemo = $application->creditMemo;
            $invoice = $application->invoice;

            if ($creditMemo !== null && $invoice !== null && (int) $creditMemo->tenant_id !== (int) $invoice->tenant_id) {
                throw new \InvalidArgumentException('Credit memo and invoice must belong to the same tenant.');
            }

            if ($creditMemo !== null && $invoice !== null && (int) $creditMemo->vendor_id !== (int) $invoice->vendor_id) {
                throw new \InvalidArgumentException('Credit memo and invoice must share the same vendor.');
            }

            $userIds = array_filter([$application->applied_by_user_id, $application->voided_by_user_id]);
            foreach ($userIds as $userId) {
                $exists = \App\Models\User::query()
                    ->whereKey($userId)
                    ->whereHas('tenants', fn ($q) => $q->where('tenants.id', (int) $application->tenant_id))
                    ->exists();
                if (! $exists) {
                    throw new \InvalidArgumentException("User {$userId} does not belong to tenant {$application->tenant_id}.");
                }
            }
        });
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Models\\CreditApplication"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Models/CreditApplication.php
git commit -m "feat(p1-49): add CreditApplication model with tenant/vendor lock guards"
```

---

## Task 12: SupplierCreditMemoException Model

**Files:**
- Create: `apps/api/Domains/CreditMemo/Models/SupplierCreditMemoException.php`

- [ ] **Step 1: Create the model file**

```php
<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierCreditMemoException extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memo_exceptions';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'exception_type',
        'severity',
        'description',
        'resolution_type',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'escalated_by_user_id',
        'escalated_at',
        'expected_value',
        'adjusted_value',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'exception_type' => SupplierCreditMemoExceptionType::class,
            'severity' => SupplierCreditMemoExceptionSeverity::class,
            'resolution_type' => SupplierCreditMemoExceptionResolutionType::class,
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'expected_value' => 'decimal:4',
            'adjusted_value' => 'decimal:4',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditMemo::class, 'supplier_credit_memo_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function escalatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by_user_id');
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Exception was updated by another user. Refresh and try again.');
        }
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isBlocking(): bool
    {
        return $this->severity === SupplierCreditMemoExceptionSeverity::Blocking && $this->isOpen();
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Models\\SupplierCreditMemoException"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Models/SupplierCreditMemoException.php
git commit -m "feat(p1-49): add SupplierCreditMemoException model with severity/resolution state"
```

---

## Task 13: Extend SupplierInvoice Model with creditApplications Relationship

**Files:**
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoice.php`

- [ ] **Step 1: Read current model to find the relationship placement**

Run: `grep -n "paymentAllocations\|HasMany\|protected function" apps/api/Domains/Invoice/Models/SupplierInvoice.php | head -30`

- [ ] **Step 2: Add the relationship method**

Inside the `SupplierInvoice` class, add the following method (after any existing `paymentAllocations()` method):

```php
    public function creditApplications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Domains\CreditMemo\Models\CreditApplication::class, 'supplier_invoice_id');
    }
```

- [ ] **Step 3: Run invoice tests to confirm no regression**

Run: `cd apps/api && php artisan test --filter=SupplierInvoiceApiTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Invoice/Models/SupplierInvoice.php
git commit -m "feat(p1-49): add creditApplications hasMany to SupplierInvoice"
```

---

## Task 14: SupplierCreditMemoNumberGenerator Support Class

**Files:**
- Create: `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoNumberGenerator.php`

- [ ] **Step 1: Create the number generator**

```php
<?php

namespace Domains\CreditMemo\Support;

use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;

class SupplierCreditMemoNumberGenerator
{
    public static function nextForTenant(int|string $tenantId): string
    {
        $year = now()->format('Y');
        $prefix = "CM-{$year}-";

        Tenant::query()
            ->whereKey($tenantId)
            ->lockForUpdate()
            ->firstOrFail();

        $latestNumbers = SupplierCreditMemo::query()
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->pluck('number')
            ->all();

        $sequence = 1;
        foreach ($latestNumbers as $number) {
            if (is_string($number) && preg_match('/^CM-\d{4}-(\d+)$/', $number, $matches) === 1) {
                $sequence = max($sequence, ((int) $matches[1]) + 1);
            }
        }

        return sprintf('%s%06d', $prefix, $sequence);
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Support\\SupplierCreditMemoNumberGenerator"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Support/SupplierCreditMemoNumberGenerator.php
git commit -m "feat(p1-49): add SupplierCreditMemoNumberGenerator with CM-YYYY-NNNNNN pattern"
```

---

## Task 15: MathValidator, DuplicateDetector, and TaxMirrorValidator Support Classes

**Files:**
- Create: `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoMathValidator.php`
- Create: `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoDuplicateDetector.php`
- Create: `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoTaxMirrorValidator.php`

- [ ] **Step 1: Create math validator**

```php
<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use InvalidArgumentException;

class SupplierCreditMemoMathValidator
{
    public function validate(SupplierCreditMemo $creditMemo): void
    {
        $lines = $creditMemo->lines;
        if ($lines->isEmpty()) {
            throw new InvalidArgumentException('Credit memo must have at least one line.');
        }

        $subtotalSum = '0.0000';
        foreach ($lines as $line) {
            $subtotalSum = bcadd($subtotalSum, (string) $line->line_subtotal, 4);
        }

        if (bccomp($subtotalSum, (string) $creditMemo->subtotal_amount, 4) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Line subtotals (%.4f) do not match header subtotal (%.4f).',
                (float) $subtotalSum,
                (float) $creditMemo->subtotal_amount
            ));
        }

        $expectedTotal = bcadd(
            bcadd($subtotalSum, (string) $creditMemo->tax_amount, 4),
            (string) $creditMemo->freight_amount,
            4
        );

        if (bccomp($expectedTotal, (string) $creditMemo->total_amount, 4) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'Subtotal + tax + freight (%.4f) does not match total (%.4f).',
                (float) $expectedTotal,
                (float) $creditMemo->total_amount
            ));
        }
    }

    public function recomputeHeader(SupplierCreditMemo $creditMemo): void
    {
        $lines = $creditMemo->lines;
        $subtotal = '0.0000';
        $tax = '0.0000';

        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line->line_subtotal, 4);
            $tax = bcadd($tax, (string) $line->tax_amount, 4);
        }

        $total = bcadd(bcadd($subtotal, $tax, 4), (string) $creditMemo->freight_amount, 4);

        $creditMemo->forceFill([
            'subtotal_amount' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $total,
        ]);
    }
}
```

- [ ] **Step 2: Create duplicate detector**

```php
<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Illuminate\Database\Eloquent\Builder;

class SupplierCreditMemoDuplicateDetector
{
    public function isDuplicate(SupplierCreditMemo $creditMemo): bool
    {
        if (empty($creditMemo->vendor_credit_memo_number) || empty($creditMemo->original_invoice_id)) {
            return false;
        }

        return SupplierCreditMemo::query()
            ->where('tenant_id', $creditMemo->tenant_id)
            ->where('vendor_id', $creditMemo->vendor_id)
            ->where('original_invoice_id', $creditMemo->original_invoice_id)
            ->where('vendor_credit_memo_number', $creditMemo->vendor_credit_memo_number)
            ->when($creditMemo->exists, fn (Builder $q) => $q->where('id', '!=', $creditMemo->id))
            ->exists();
    }
}
```

- [ ] **Step 3: Create tax mirror validator**

```php
<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\Invoice\Models\SupplierInvoiceLine;
use InvalidArgumentException;

class SupplierCreditMemoTaxMirrorValidator
{
    public function validate(SupplierCreditMemo $creditMemo): void
    {
        $originalInvoiceId = $creditMemo->original_invoice_id;
        if ($originalInvoiceId === null) {
            return;
        }

        $originalLines = SupplierInvoiceLine::query()
            ->where('supplier_invoice_id', $originalInvoiceId)
            ->get()
            ->keyBy('id');

        foreach ($creditMemo->lines as $creditLine) {
            if ($creditLine->original_invoice_line_id === null) {
                continue;
            }

            $originalLine = $originalLines->get($creditLine->original_invoice_line_id);
            if ($originalLine === null) {
                throw new InvalidArgumentException(sprintf(
                    'Credit memo line %d references invoice line %s which does not exist on the original invoice.',
                    $creditLine->line_number,
                    $creditLine->original_invoice_line_id
                ));
            }

            if ($originalLine->tax_code !== null && $creditLine->tax_code === null) {
                throw new InvalidArgumentException(sprintf(
                    'Credit memo line %d is missing tax code; original line uses %s.',
                    $creditLine->line_number,
                    (string) $originalLine->tax_code
                ));
            }

            if ($originalLine->tax_code !== null && $creditLine->tax_code !== $originalLine->tax_code) {
                throw new InvalidArgumentException(sprintf(
                    'Credit memo line %d tax code %s does not match original line tax code %s.',
                    $creditLine->line_number,
                    (string) $creditLine->tax_code,
                    (string) $originalLine->tax_code
                ));
            }
        }
    }
}
```

- [ ] **Step 4: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Support\\SupplierCreditMemoMathValidator"), class_exists("Domains\\CreditMemo\\Support\\SupplierCreditMemoDuplicateDetector"), class_exists("Domains\\CreditMemo\\Support\\SupplierCreditMemoTaxMirrorValidator"));`
Expected: prints `bool(true) bool(true) bool(true)`.

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/CreditMemo/Support/SupplierCreditMemoMathValidator.php apps/api/Domains/CreditMemo/Support/SupplierCreditMemoDuplicateDetector.php apps/api/Domains/CreditMemo/Support/SupplierCreditMemoTaxMirrorValidator.php
git commit -m "feat(p1-49): add math, duplicate, and tax mirror validator support classes"
```

---

## Task 16: CreditApplicationSumCalculator and StateMachine Support Classes

**Files:**
- Create: `apps/api/Domains/CreditMemo/Support/CreditApplicationSumCalculator.php`
- Create: `apps/api/Domains/CreditMemo/Support/SupplierCreditMemoStateMachine.php`

- [ ] **Step 1: Create sum calculator**

```php
<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\Invoice\Models\SupplierInvoice;

class CreditApplicationSumCalculator
{
    public function sumForCreditMemo(SupplierCreditMemo $creditMemo): string
    {
        $result = CreditApplication::query()
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->whereNull('voided_at')
            ->sum('applied_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function sumForInvoice(SupplierInvoice $invoice): string
    {
        $result = CreditApplication::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('applied_amount');

        return $result !== null ? (string) $result : '0.0000';
    }

    public function remainingForCreditMemo(SupplierCreditMemo $creditMemo): string
    {
        $applied = $this->sumForCreditMemo($creditMemo);
        return bcsub((string) $creditMemo->total_amount, $applied, 4);
    }

    public function outstandingForInvoice(SupplierInvoice $invoice): string
    {
        $paid = \Domains\Payments\Models\ApPaymentAllocation::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->whereNull('voided_at')
            ->sum('allocated_amount');
        $paidNormalized = $paid !== null ? (string) $paid : '0.0000';
        $creditApplied = $this->sumForInvoice($invoice);

        return bcsub(
            bcsub((string) $invoice->total_amount, $paidNormalized, 4),
            $creditApplied,
            4
        );
    }
}
```

- [ ] **Step 2: Create state machine**

```php
<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\States\SupplierCreditMemoStatus;

class SupplierCreditMemoStateMachine
{
    public function deriveStatusFromApplications(string $appliedSum, string $totalAmount): SupplierCreditMemoStatus
    {
        if (bccomp($appliedSum, $totalAmount, 4) === 0) {
            return SupplierCreditMemoStatus::FullyApplied;
        }

        if (bccomp($appliedSum, '0.0000', 4) === 1) {
            return SupplierCreditMemoStatus::PartiallyApplied;
        }

        return SupplierCreditMemoStatus::Open;
    }

    public function rederiveStatusOnVoid(SupplierCreditMemoStatus $current, string $appliedSum, string $totalAmount): SupplierCreditMemoStatus
    {
        if ($current === SupplierCreditMemoStatus::Closed) {
            return $current;
        }

        if ($current === SupplierCreditMemoStatus::Voided) {
            return $current;
        }

        if (bccomp($appliedSum, '0.0000', 4) === 0) {
            return SupplierCreditMemoStatus::Open;
        }

        if (bccomp($appliedSum, $totalAmount, 4) === 0) {
            return SupplierCreditMemoStatus::FullyApplied;
        }

        return SupplierCreditMemoStatus::PartiallyApplied;
    }
}
```

- [ ] **Step 3: Write unit test for sum calculator and state machine**

Create `apps/api/tests/Feature/CreditMemoSupportTest.php`:

```php
<?php

namespace Tests\Feature;

use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\CreditMemo\Support\SupplierCreditMemoStateMachine;
use Tests\TestCase;

class CreditMemoSupportTest extends TestCase
{
    public function test_derive_status_when_no_applications(): void
    {
        $sm = app(SupplierCreditMemoStateMachine::class);
        $this->assertSame(
            SupplierCreditMemoStatus::Open,
            $sm->deriveStatusFromApplications('0.0000', '1000.0000')
        );
    }

    public function test_derive_status_when_partially_applied(): void
    {
        $sm = app(SupplierCreditMemoStateMachine::class);
        $this->assertSame(
            SupplierCreditMemoStatus::PartiallyApplied,
            $sm->deriveStatusFromApplications('500.0000', '1000.0000')
        );
    }

    public function test_derive_status_when_fully_applied(): void
    {
        $sm = app(SupplierCreditMemoStateMachine::class);
        $this->assertSame(
            SupplierCreditMemoStatus::FullyApplied,
            $sm->deriveStatusFromApplications('1000.0000', '1000.0000')
        );
    }

    public function test_rederive_keeps_closed_terminal(): void
    {
        $sm = app(SupplierCreditMemoStateMachine::class);
        $this->assertSame(
            SupplierCreditMemoStatus::Closed,
            $sm->rederiveStatusOnVoid(SupplierCreditMemoStatus::Closed, '0.0000', '1000.0000')
        );
    }

    public function test_rederive_returns_to_open_when_all_voided(): void
    {
        $sm = app(SupplierCreditMemoStateMachine::class);
        $this->assertSame(
            SupplierCreditMemoStatus::Open,
            $sm->rederiveStatusOnVoid(SupplierCreditMemoStatus::PartiallyApplied, '0.0000', '1000.0000')
        );
    }

    public function test_calculator_class_exists(): void
    {
        $this->assertTrue(class_exists(CreditApplicationSumCalculator::class));
    }
}
```

Run: `cd apps/api && php artisan test --filter=CreditMemoSupportTest`
Expected: PASS (6 tests).

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/CreditMemo/Support/CreditApplicationSumCalculator.php apps/api/Domains/CreditMemo/Support/SupplierCreditMemoStateMachine.php apps/api/tests/Feature/CreditMemoSupportTest.php
git commit -m "feat(p1-49): add CreditApplicationSumCalculator and SupplierCreditMemoStateMachine"
```

---

## Task 17: CreateSupplierCreditMemo Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/CreateSupplierCreditMemo.php`
- Create: `apps/api/Domains/CreditMemo/Data/SupplierCreditMemoContextData.php`

- [ ] **Step 1: Create context DTO**

`apps/api/Domains/CreditMemo/Data/SupplierCreditMemoContextData.php`:

```php
<?php

namespace Domains\CreditMemo\Data;

class SupplierCreditMemoContextData
{
    public function __construct(
        public readonly int $tenantId,
        public readonly int $vendorId,
        public readonly ?int $originalInvoiceId,
        public readonly string $vendorCreditMemoNumber,
        public readonly string $creditDate,
        public readonly string $currency,
        public readonly string $subtotalAmount,
        public readonly string $taxAmount,
        public readonly string $freightAmount,
        public readonly string $totalAmount,
        public readonly ?string $notes,
        /** @var array<int, array<string, mixed>> */
        public readonly array $lines,
    ) {}
}
```

- [ ] **Step 2: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Data\SupplierCreditMemoContextData;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionSeverity;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionType;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\SupplierCreditMemoDuplicateDetector;
use Domains\CreditMemo\Support\SupplierCreditMemoNumberGenerator;
use Domains\CreditMemo\Support\SupplierCreditMemoTaxMirrorValidator;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;

class CreateSupplierCreditMemo
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierCreditMemoDuplicateDetector $duplicateDetector,
        private readonly SupplierCreditMemoTaxMirrorValidator $taxMirrorValidator,
    ) {}

    public function handle(Tenant $tenant, User $actor, SupplierCreditMemoContextData $context): SupplierCreditMemo
    {
        return DB::transaction(function () use ($tenant, $actor, $context): SupplierCreditMemo {
            $vendor = Vendor::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($context->vendorId)
                ->lockForUpdate()
                ->firstOrFail();

            $originalInvoice = null;
            if ($context->originalInvoiceId !== null) {
                $originalInvoice = SupplierInvoice::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($context->originalInvoiceId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $originalInvoice->vendor_id !== (int) $vendor->id) {
                    throw new \InvalidArgumentException('Credit memo vendor must match the original invoice vendor.');
                }
            }

            $currency = $context->currency !== ''
                ? $context->currency
                : ($originalInvoice?->currency ?? 'USD');

            if ($originalInvoice !== null && $currency !== (string) $originalInvoice->currency) {
                throw new \InvalidArgumentException('Credit memo currency must match the original invoice currency.');
            }

            $number = SupplierCreditMemoNumberGenerator::nextForTenant($tenant->id);

            $creditMemo = SupplierCreditMemo::query()->create([
                'tenant_id' => $tenant->id,
                'number' => $number,
                'vendor_credit_memo_number' => $context->vendorCreditMemoNumber,
                'vendor_id' => $vendor->id,
                'original_invoice_id' => $originalInvoice?->id,
                'status' => SupplierCreditMemoStatus::Draft,
                'currency' => $currency,
                'subtotal_amount' => $context->subtotalAmount,
                'tax_amount' => $context->taxAmount,
                'freight_amount' => $context->freightAmount,
                'total_amount' => $context->totalAmount,
                'credit_date' => $context->creditDate,
                'notes' => $context->notes,
                'captured_by_user_id' => $actor->id,
                'captured_at' => now(),
                'lock_version' => 1,
            ]);

            foreach ($context->lines as $linePayload) {
                $quantity = (string) ($linePayload['quantity'] ?? '1');
                $unitPrice = (string) ($linePayload['unitPrice'] ?? '0');
                $lineSubtotal = bcmul($quantity, $unitPrice, 4);

                SupplierCreditMemoLine::query()->create([
                    'tenant_id' => $tenant->id,
                    'supplier_credit_memo_id' => $creditMemo->id,
                    'purchase_order_line_id' => $linePayload['purchaseOrderLineId'] ?? null,
                    'original_invoice_line_id' => $linePayload['originalInvoiceLineId'] ?? null,
                    'line_number' => (int) $linePayload['lineNumber'],
                    'description_snapshot' => (string) $linePayload['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'tax_code' => $linePayload['taxCode'] ?? null,
                    'tax_amount' => (string) ($linePayload['taxAmount'] ?? '0'),
                    'notes' => $linePayload['notes'] ?? null,
                ]);
            }

            $creditMemo->load('lines');

            $this->taxMirrorValidator->validate($creditMemo);

            if ($this->duplicateDetector->isDuplicate($creditMemo)) {
                SupplierCreditMemoException::query()->create([
                    'tenant_id' => $tenant->id,
                    'supplier_credit_memo_id' => $creditMemo->id,
                    'exception_type' => SupplierCreditMemoExceptionType::DuplicateCredit,
                    'severity' => SupplierCreditMemoExceptionSeverity::Warning,
                    'description' => 'A credit memo with the same vendor, original invoice, and vendor credit memo number already exists.',
                    'lock_version' => 1,
                ]);
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'supplier_credit_memo.created',
                subject: $creditMemo,
                metadata: [
                    'number' => $number,
                    'vendorId' => (string) $vendor->id,
                    'originalInvoiceId' => $originalInvoice?->id !== null ? (string) $originalInvoice->id : null,
                    'totalAmount' => $context->totalAmount,
                    'currency' => $currency,
                    'lineCount' => count($context->lines),
                ],
            ));

            return $creditMemo->fresh(['lines', 'exceptions']);
        });
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/CreateSupplierCreditMemo.php apps/api/Domains/CreditMemo/Data/SupplierCreditMemoContextData.php
git commit -m "feat(p1-49): add CreateSupplierCreditMemo action with vendor + tax mirror validation"
```

---

## Task 18: UpdateSupplierCreditMemo Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemo.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateSupplierCreditMemo
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemo $creditMemo,
        User $actor,
        int $lockVersion,
        ?string $notes = null,
        ?string $creditDate = null,
        ?string $vendorCreditMemoNumber = null,
        ?string $freightAmount = null,
    ): SupplierCreditMemo {
        return DB::transaction(function () use ($creditMemo, $actor, $lockVersion, $notes, $creditDate, $vendorCreditMemoNumber, $freightAmount): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Only draft credit memos can be updated.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            $before = $creditMemo->only(['notes', 'credit_date', 'vendor_credit_memo_number', 'freight_amount', 'lock_version']);

            $fill = [];
            if ($notes !== null) $fill['notes'] = $notes;
            if ($creditDate !== null) $fill['credit_date'] = $creditDate;
            if ($vendorCreditMemoNumber !== null) $fill['vendor_credit_memo_number'] = $vendorCreditMemoNumber;
            if ($freightAmount !== null) {
                $fill['freight_amount'] = $freightAmount;
                $fill['total_amount'] = bcadd(bcadd((string) $creditMemo->subtotal_amount, (string) $creditMemo->tax_amount, 4), $freightAmount, 4);
            }

            $fill['lock_version'] = $creditMemo->lock_version + 1;

            $creditMemo->forceFill($fill)->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.updated',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['notes', 'credit_date', 'vendor_credit_memo_number', 'freight_amount', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'fields' => array_keys($fill),
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemo.php
git commit -m "feat(p1-49): add UpdateSupplierCreditMemo action (draft only, lock-versioned)"
```

---

## Task 19: AddSupplierCreditMemoLine Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/AddSupplierCreditMemoLine.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\SupplierCreditMemoMathValidator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AddSupplierCreditMemoLine
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierCreditMemoMathValidator $mathValidator,
    ) {}

    public function handle(
        SupplierCreditMemo $creditMemo,
        User $actor,
        int $lockVersion,
        int $lineNumber,
        string $description,
        string $quantity,
        string $unitPrice,
        ?string $taxCode = null,
        ?string $taxAmount = null,
        ?string $purchaseOrderLineId = null,
        ?string $originalInvoiceLineId = null,
        ?string $notes = null,
    ): SupplierCreditMemoLine {
        return DB::transaction(function () use ($creditMemo, $actor, $lockVersion, $lineNumber, $description, $quantity, $unitPrice, $taxCode, $taxAmount, $purchaseOrderLineId, $originalInvoiceLineId, $notes): SupplierCreditMemoLine {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Lines can only be added to draft credit memos.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            $lineSubtotal = bcmul($quantity, $unitPrice, 4);

            $line = SupplierCreditMemoLine::query()->create([
                'tenant_id' => $creditMemo->tenant_id,
                'supplier_credit_memo_id' => $creditMemo->id,
                'purchase_order_line_id' => $purchaseOrderLineId,
                'original_invoice_line_id' => $originalInvoiceLineId,
                'line_number' => $lineNumber,
                'description_snapshot' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_code' => $taxCode,
                'tax_amount' => $taxAmount ?? '0',
                'notes' => $notes,
            ]);

            $creditMemo->load('lines');
            $this->mathValidator->recomputeHeader($creditMemo);
            $creditMemo->lock_version = $creditMemo->lock_version + 1;
            $creditMemo->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_added',
                subject: $line,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $lineNumber,
                    'lineSubtotal' => $lineSubtotal,
                ],
            ));

            return $line->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/AddSupplierCreditMemoLine.php
git commit -m "feat(p1-49): add AddSupplierCreditMemoLine action with header recompute"
```

---

## Task 20: UpdateSupplierCreditMemoLine and RemoveSupplierCreditMemoLine Actions

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemoLine.php`
- Create: `apps/api/Domains/CreditMemo/Actions/RemoveSupplierCreditMemoLine.php`

- [ ] **Step 1: Create update line action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\SupplierCreditMemoMathValidator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateSupplierCreditMemoLine
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierCreditMemoMathValidator $mathValidator,
    ) {}

    public function handle(
        SupplierCreditMemoLine $line,
        User $actor,
        int $lockVersion,
        ?string $description = null,
        ?string $quantity = null,
        ?string $unitPrice = null,
        ?string $taxCode = null,
        ?string $taxAmount = null,
        ?string $notes = null,
    ): SupplierCreditMemoLine {
        return DB::transaction(function () use ($line, $actor, $lockVersion, $description, $quantity, $unitPrice, $taxCode, $taxAmount, $notes): SupplierCreditMemoLine {
            $line = SupplierCreditMemoLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->assertLockVersion($lockVersion);

            $creditMemo = SupplierCreditMemo::query()->whereKey($line->supplier_credit_memo_id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Lines can only be updated on draft credit memos.');
            }

            $line->assertLockVersion($lockVersion);

            $fill = [];
            if ($description !== null) $fill['description_snapshot'] = $description;
            if ($quantity !== null) $fill['quantity'] = $quantity;
            if ($unitPrice !== null) $fill['unit_price'] = $unitPrice;
            if ($taxCode !== null) $fill['tax_code'] = $taxCode;
            if ($taxAmount !== null) $fill['tax_amount'] = $taxAmount;
            if ($notes !== null) $fill['notes'] = $notes;

            if (isset($fill['quantity']) || isset($fill['unit_price'])) {
                $newQuantity = $fill['quantity'] ?? (string) $line->quantity;
                $newUnitPrice = $fill['unit_price'] ?? (string) $line->unit_price;
                $fill['line_subtotal'] = bcmul($newQuantity, $newUnitPrice, 4);
            }

            $line->forceFill($fill)->save();

            $creditMemo->load('lines');
            $this->mathValidator->recomputeHeader($creditMemo);
            $creditMemo->lock_version = $creditMemo->lock_version + 1;
            $creditMemo->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_updated',
                subject: $line,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $line->line_number,
                ],
            ));

            return $line->fresh();
        });
    }
}
```

- [ ] **Step 2: Create remove line action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\SupplierCreditMemoMathValidator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RemoveSupplierCreditMemoLine
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly SupplierCreditMemoMathValidator $mathValidator,
    ) {}

    public function handle(SupplierCreditMemoLine $line, User $actor, int $lockVersion): void
    {
        DB::transaction(function () use ($line, $actor, $lockVersion): void {
            $line = SupplierCreditMemoLine::query()->whereKey($line->id)->lockForUpdate()->firstOrFail();
            $line->assertLockVersion($lockVersion);

            $creditMemo = SupplierCreditMemo::query()->whereKey($line->supplier_credit_memo_id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Lines can only be removed from draft credit memos.');
            }

            $lineNumber = $line->line_number;
            $line->delete();

            $creditMemo->load('lines');
            $this->mathValidator->recomputeHeader($creditMemo);
            $creditMemo->lock_version = $creditMemo->lock_version + 1;
            $creditMemo->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.line_removed',
                subject: $creditMemo,
                metadata: [
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'lineNumber' => $lineNumber,
                ],
            ));
        });
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/UpdateSupplierCreditMemoLine.php apps/api/Domains/CreditMemo/Actions/RemoveSupplierCreditMemoLine.php
git commit -m "feat(p1-49): add UpdateSupplierCreditMemoLine and RemoveSupplierCreditMemoLine actions"
```

---

## Task 21: SubmitSupplierCreditMemoForApproval Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/SubmitSupplierCreditMemoForApproval.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Approval\Contracts\ApprovalSubjectRegistry;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitSupplierCreditMemoForApproval
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly ApprovalSubjectRegistry $approvalRegistry,
    ) {}

    public function handle(SupplierCreditMemo $creditMemo, User $actor, int $lockVersion): SupplierCreditMemo
    {
        return DB::transaction(function () use ($creditMemo, $actor, $lockVersion): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Draft) {
                throw new ConflictHttpException('Only draft credit memos can be submitted for approval.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            if ($creditMemo->lines()->count() === 0) {
                throw new ConflictHttpException('Credit memo must have at least one line.');
            }

            $openBlocking = $creditMemo->exceptions()
                ->whereNull('resolved_at')
                ->whereHas('severity', function ($q) {
                    $q->where('severity', 'blocking');
                })
                ->count();

            if ($openBlocking > 0) {
                throw new ConflictHttpException("Credit memo has {$openBlocking} open blocking exceptions.");
            }

            $before = $creditMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'lock_version']);

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::PendingApproval,
                'submitted_by_user_id' => $actor->id,
                'submitted_at' => now(),
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            $instance = $this->approvalRegistry->route($creditMemo, $actor);

            $creditMemo->forceFill([
                'approval_instance_id' => $instance->id,
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.submitted_for_approval',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'submitted_by_user_id', 'submitted_at', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'approvalInstanceId' => (string) $instance->id,
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/SubmitSupplierCreditMemoForApproval.php
git commit -m "feat(p1-49): add SubmitSupplierCreditMemoForApproval action with approval routing"
```

---

## Task 22: PostSupplierCreditMemo Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/PostSupplierCreditMemo.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PostSupplierCreditMemo
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemo $creditMemo, User $actor, int $lockVersion): SupplierCreditMemo
    {
        return DB::transaction(function () use ($creditMemo, $actor, $lockVersion): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() !== SupplierCreditMemoStatus::Approved) {
                throw new ConflictHttpException('Only approved credit memos can be posted.');
            }

            $creditMemo->assertLockVersion($lockVersion);

            $before = $creditMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']);

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Open,
                'posted_by_user_id' => $actor->id,
                'posted_at' => now(),
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.posted',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'posted_by_user_id', 'posted_at', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'totalAmount' => (string) $creditMemo->total_amount,
                    'currency' => $creditMemo->currency,
                ],
            ));

            return $creditMemo->fresh();
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/PostSupplierCreditMemo.php
git commit -m "feat(p1-49): add PostSupplierCreditMemo action (approved to open)"
```

---

## Task 23: VoidSupplierCreditMemo Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/VoidSupplierCreditMemo.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\CreditMemo\Support\SupplierCreditMemoStateMachine;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoidSupplierCreditMemo
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $sumCalculator,
        private readonly SupplierCreditMemoStateMachine $stateMachine,
    ) {}

    public function handle(
        SupplierCreditMemo $creditMemo,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): SupplierCreditMemo {
        $voidReason = trim($voidReason);
        if (strlen($voidReason) < 5) {
            throw new \Illuminate\Validation\ValidationException::withMessages([
                'voidReason' => 'Void reason must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($creditMemo, $actor, $lockVersion, $voidReason): SupplierCreditMemo {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();
            $creditMemo->assertLockVersion($lockVersion);

            $voidableStates = [
                SupplierCreditMemoStatus::Draft,
                SupplierCreditMemoStatus::PendingApproval,
                SupplierCreditMemoStatus::Approved,
                SupplierCreditMemoStatus::Open,
                SupplierCreditMemoStatus::PartiallyApplied,
            ];

            if (! in_array($creditMemo->statusState(), $voidableStates, true)) {
                throw new ConflictHttpException('Credit memo cannot be voided from its current state.');
            }

            $before = $creditMemo->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']);

            $applications = CreditApplication::query()
                ->where('supplier_credit_memo_id', $creditMemo->id)
                ->whereNull('voided_at')
                ->lockForUpdate()
                ->get();

            $applicationsVoided = 0;
            $affectedInvoiceIds = [];

            foreach ($applications as $application) {
                $application->forceFill([
                    'voided_at' => now(),
                    'voided_by_user_id' => $actor->id,
                    'void_reason' => $voidReason,
                    'lock_version' => $application->lock_version + 1,
                ])->save();
                $applicationsVoided++;
                $affectedInvoiceIds[(string) $application->supplier_invoice_id] = true;
            }

            $creditMemo->forceFill([
                'status' => SupplierCreditMemoStatus::Voided,
                'voided_by_user_id' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $voidReason,
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            foreach (array_keys($affectedInvoiceIds) as $invoiceId) {
                $invoice = SupplierInvoice::query()->whereKey($invoiceId)->lockForUpdate()->first();
                if ($invoice === null) continue;

                $recompute = $this->sumCalculator->outstandingForInvoice($invoice);
                $applied = $this->sumCalculator->sumForInvoice($invoice);
                $newStatus = $this->deriveInvoicePaymentStatus($invoice, $recompute, $applied);

                if ($newStatus !== null && $invoice->payment_status !== null
                    && (string) $invoice->payment_status->value !== $newStatus->value) {
                    $previous = (string) $invoice->payment_status->value;
                    $invoice->forceFill([
                        'payment_status' => $newStatus,
                        'lock_version' => $invoice->lock_version + 1,
                    ])->save();

                    $this->auditRecorder->record(new AuditEventData(
                        tenant: $creditMemo->tenant,
                        actor: $actor,
                        action: 'supplier_invoice.credit_memo_voided',
                        subject: $invoice,
                        metadata: [
                            'creditMemoId' => (string) $creditMemo->id,
                            'creditMemoNumber' => $creditMemo->number,
                            'applicationsVoided' => $applicationsVoided,
                            'fromStatus' => $previous,
                            'toStatus' => $newStatus->value,
                        ],
                    ));
                }
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.voided',
                subject: $creditMemo,
                before: $before,
                after: $creditMemo->only(['status', 'voided_by_user_id', 'voided_at', 'void_reason', 'lock_version']),
                metadata: [
                    'number' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'applicationsVoided' => $applicationsVoided,
                    'invoicesAffected' => count($affectedInvoiceIds),
                ],
            ));

            return $creditMemo->fresh();
        });
    }

    private function deriveInvoicePaymentStatus(SupplierInvoice $invoice, string $outstanding, string $creditApplied): ?SupplierInvoicePaymentStatus
    {
        $total = (string) $invoice->total_amount;
        $creditAppliedNormalized = $creditApplied;

        if (bccomp($creditAppliedNormalized, $total, 4) >= 0) {
            return SupplierInvoicePaymentStatus::Reversed;
        }

        if (bccomp($outstanding, $total, 4) < 0 && bccomp($outstanding, '0.0000', 4) > 0) {
            return SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        if (bccomp($outstanding, '0.0000', 4) === 0 && (string) ($invoice->payment_status?->value ?? '') === 'reversed') {
            return SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/VoidSupplierCreditMemo.php
git commit -m "feat(p1-49): add VoidSupplierCreditMemo action with cascading application void and invoice revert"
```

---

## Task 24: CreateCreditApplication Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/CreateCreditApplication.php`
- Create: `apps/api/Domains/CreditMemo/Data/CreditApplicationPreviewData.php`

- [ ] **Step 1: Create preview DTO**

```php
<?php

namespace Domains\CreditMemo\Data;

class CreditApplicationPreviewData
{
    public function __construct(
        public readonly string $creditMemoRemainingAmount,
        public readonly string $invoiceOutstandingAmount,
        public readonly string $creditMemoAppliedAmount,
        public readonly string $invoiceCreditAppliedAmount,
        public readonly string $creditMemoStatus,
        public readonly string $invoicePaymentStatus,
    ) {}
}
```

- [ ] **Step 2: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\CreditMemo\Support\SupplierCreditMemoStateMachine;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateCreditApplication
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $sumCalculator,
        private readonly SupplierCreditMemoStateMachine $stateMachine,
    ) {}

    public function handle(
        SupplierCreditMemo $creditMemo,
        SupplierInvoice $invoice,
        User $actor,
        int $lockVersion,
        string $appliedAmount,
        string $applicationDate,
        ?string $notes = null,
    ): CreditApplication {
        return DB::transaction(function () use ($creditMemo, $invoice, $actor, $lockVersion, $appliedAmount, $applicationDate, $notes): CreditApplication {
            $creditMemo = SupplierCreditMemo::query()->whereKey($creditMemo->id)->lockForUpdate()->firstOrFail();
            $invoice = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $creditMemo->assertLockVersion($lockVersion);

            if (! $creditMemo->statusState()->canAcceptCreditApplications()) {
                throw new ConflictHttpException('Credit memo must be in Open or Partially Applied state.');
            }

            if ((int) $creditMemo->vendor_id !== (int) $invoice->vendor_id) {
                throw new \InvalidArgumentException('Credit memo and invoice must share the same vendor.');
            }

            if (bccomp($appliedAmount, '0.0000', 4) !== 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'appliedAmount' => 'Applied amount must be greater than zero.',
                ]);
            }

            $currentMemoApplied = $this->sumCalculator->sumForCreditMemo($creditMemo);
            $memoRemaining = bcsub((string) $creditMemo->total_amount, $currentMemoApplied, 4);

            if (bccomp(bcadd($currentMemoApplied, $appliedAmount, 4), (string) $creditMemo->total_amount, 4) === 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'appliedAmount' => sprintf(
                        'Over-application of credit: current applied %s, remaining %s, attempted %s.',
                        $currentMemoApplied,
                        $memoRemaining,
                        $appliedAmount
                    ),
                ]);
            }

            $invoiceOutstanding = $this->sumCalculator->outstandingForInvoice($invoice);
            if (bccomp($appliedAmount, $invoiceOutstanding, 4) === 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'appliedAmount' => sprintf(
                        'Over-application of invoice: outstanding %s, attempted %s.',
                        $invoiceOutstanding,
                        $appliedAmount
                    ),
                ]);
            }

            $invoicePaymentStatus = $invoice->payment_status;

            if ($invoicePaymentStatus !== null && ! $invoicePaymentStatus->canApplyCreditFrom()) {
                if ($invoicePaymentStatus === SupplierInvoicePaymentStatus::OnHold) {
                    throw new ConflictHttpException('Cannot apply credit to an invoice on hold. Release the hold first.');
                }
                throw new ConflictHttpException('Cannot apply credit while the invoice is in a handoff state. Void the handoff first.');
            }

            $application = CreditApplication::query()->create([
                'tenant_id' => $creditMemo->tenant_id,
                'supplier_credit_memo_id' => $creditMemo->id,
                'supplier_invoice_id' => $invoice->id,
                'applied_amount' => $appliedAmount,
                'application_date' => $applicationDate,
                'applied_by_user_id' => $actor->id,
                'notes' => $notes,
                'lock_version' => 1,
            ]);

            $newMemoApplied = bcadd($currentMemoApplied, $appliedAmount, 4);
            $newMemoStatus = $this->stateMachine->deriveStatusFromApplications($newMemoApplied, (string) $creditMemo->total_amount);

            $creditMemoBefore = $creditMemo->only(['status', 'lock_version']);
            $creditMemo->forceFill([
                'status' => $newMemoStatus,
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            $newInvoiceCreditApplied = bcadd($this->sumCalculator->sumForInvoice($invoice), $appliedAmount, 4);
            $newInvoiceStatus = $this->deriveInvoicePaymentStatus($invoice, $newInvoiceCreditApplied);

            $invoiceBefore = $invoice->payment_status !== null ? (string) $invoice->payment_status->value : null;
            $invoiceChanged = false;
            if ($newInvoiceStatus !== null && ($invoiceBefore === null || $invoiceBefore !== $newInvoiceStatus->value)) {
                $invoice->forceFill([
                    'payment_status' => $newInvoiceStatus,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();
                $invoiceChanged = true;
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.applied',
                subject: $creditMemo,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'number' => $creditMemo->number,
                    'appliedAmount' => $appliedAmount,
                    'invoiceId' => (string) $invoice->id,
                    'invoiceNumber' => $invoice->number,
                    'fromStatus' => $creditMemoBefore['status'],
                    'toStatus' => $newMemoStatus->value,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_invoice.credit_applied',
                subject: $invoice,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'appliedAmount' => $appliedAmount,
                    'fromStatus' => $invoiceBefore,
                    'toStatus' => $newInvoiceStatus?->value,
                ],
            ));

            if ($newMemoStatus === SupplierCreditMemoStatus::FullyApplied) {
                $creditMemo->forceFill([
                    'status' => SupplierCreditMemoStatus::Closed,
                    'lock_version' => $creditMemo->lock_version + 1,
                ])->save();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_credit_memo.fully_applied',
                    subject: $creditMemo,
                    metadata: [
                        'applicationId' => (string) $application->id,
                        'number' => $creditMemo->number,
                    ],
                ));

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_credit_memo.closed',
                    subject: $creditMemo,
                    metadata: [
                        'number' => $creditMemo->number,
                        'totalAmount' => (string) $creditMemo->total_amount,
                    ],
                ));
            }

            if ($invoiceChanged && $newInvoiceStatus === SupplierInvoicePaymentStatus::Reversed) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $creditMemo->tenant,
                    actor: $actor,
                    action: 'supplier_invoice.reversed',
                    subject: $invoice,
                    metadata: [
                        'creditMemoId' => (string) $creditMemo->id,
                        'creditMemoNumber' => $creditMemo->number,
                        'applicationId' => (string) $application->id,
                    ],
                ));
            }

            return $application->fresh();
        });
    }

    private function deriveInvoicePaymentStatus(SupplierInvoice $invoice, string $creditApplied): ?SupplierInvoicePaymentStatus
    {
        $total = (string) $invoice->total_amount;
        $currentPayment = $invoice->payment_status;

        if ($currentPayment === null) {
            return null;
        }

        if (bccomp($creditApplied, $total, 4) >= 0) {
            return SupplierInvoicePaymentStatus::Reversed;
        }

        if ($currentPayment === SupplierInvoicePaymentStatus::Paid) {
            return SupplierInvoicePaymentStatus::Paid;
        }

        if ($currentPayment === SupplierInvoicePaymentStatus::PartiallyPaid) {
            return SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return null;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/CreateCreditApplication.php apps/api/Domains/CreditMemo/Data/CreditApplicationPreviewData.php
git commit -m "feat(p1-49): add CreateCreditApplication action with over-application guards and invoice reverse derivation"
```

---

## Task 25: VoidCreditApplication Action

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/VoidCreditApplication.php`

- [ ] **Step 1: Create the action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\AccountsPayable\States\SupplierInvoicePaymentStatus;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\CreditMemo\Support\SupplierCreditMemoStateMachine;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoidCreditApplication
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreditApplicationSumCalculator $sumCalculator,
        private readonly SupplierCreditMemoStateMachine $stateMachine,
    ) {}

    public function handle(
        CreditApplication $application,
        User $actor,
        int $lockVersion,
        string $voidReason,
    ): CreditApplication {
        $voidReason = trim($voidReason);
        if (strlen($voidReason) < 5) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'voidReason' => 'Void reason must be at least 5 characters.',
            ]);
        }

        return DB::transaction(function () use ($application, $actor, $lockVersion, $voidReason): CreditApplication {
            $application = CreditApplication::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $application->assertLockVersion($lockVersion);

            if ($application->voided_at !== null) {
                throw new ConflictHttpException('Credit application is already voided.');
            }

            $creditMemo = SupplierCreditMemo::query()->whereKey($application->supplier_credit_memo_id)->lockForUpdate()->firstOrFail();

            if ($creditMemo->statusState() === SupplierCreditMemoStatus::Voided) {
                throw new ConflictHttpException('Credit memo is voided; applications cannot be voided individually.');
            }

            $invoice = SupplierInvoice::query()->whereKey($application->supplier_invoice_id)->lockForUpdate()->firstOrFail();

            $before = $application->only(['voided_at', 'voided_by_user_id', 'void_reason', 'lock_version']);

            $application->forceFill([
                'voided_at' => now(),
                'voided_by_user_id' => $actor->id,
                'void_reason' => $voidReason,
                'lock_version' => $application->lock_version + 1,
            ])->save();

            $currentMemoStatus = $creditMemo->statusState();
            $newMemoApplied = $this->sumCalculator->sumForCreditMemo($creditMemo);
            $newMemoStatus = $this->stateMachine->rederiveStatusOnVoid($currentMemoStatus, $newMemoApplied, (string) $creditMemo->total_amount);

            $creditMemo->forceFill([
                'status' => $newMemoStatus,
                'lock_version' => $creditMemo->lock_version + 1,
            ])->save();

            $newInvoiceCreditApplied = $this->sumCalculator->sumForInvoice($invoice);
            $newInvoiceStatus = $this->deriveInvoicePaymentStatusOnVoid($invoice, $newInvoiceCreditApplied);

            $invoiceBefore = $invoice->payment_status !== null ? (string) $invoice->payment_status->value : null;
            if ($newInvoiceStatus !== null && $invoiceBefore !== $newInvoiceStatus->value) {
                $invoice->forceFill([
                    'payment_status' => $newInvoiceStatus,
                    'lock_version' => $invoice->lock_version + 1,
                ])->save();
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_credit_memo.application_voided',
                subject: $creditMemo,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'number' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'fromStatus' => $currentMemoStatus->value,
                    'toStatus' => $newMemoStatus->value,
                ],
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $creditMemo->tenant,
                actor: $actor,
                action: 'supplier_invoice.credit_application_voided',
                subject: $invoice,
                metadata: [
                    'applicationId' => (string) $application->id,
                    'creditMemoId' => (string) $creditMemo->id,
                    'creditMemoNumber' => $creditMemo->number,
                    'voidReason' => $voidReason,
                    'fromStatus' => $invoiceBefore,
                    'toStatus' => $newInvoiceStatus?->value,
                ],
            ));

            return $application->fresh();
        });
    }

    private function deriveInvoicePaymentStatusOnVoid(SupplierInvoice $invoice, string $creditApplied): ?SupplierInvoicePaymentStatus
    {
        $total = (string) $invoice->total_amount;
        $currentPayment = $invoice->payment_status;

        if ($currentPayment === null) {
            return null;
        }

        if (bccomp($creditApplied, $total, 4) >= 0) {
            return SupplierInvoicePaymentStatus::Reversed;
        }

        if ($currentPayment === SupplierInvoicePaymentStatus::Reversed) {
            return SupplierInvoicePaymentStatus::PartiallyPaid;
        }

        return null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/VoidCreditApplication.php
git commit -m "feat(p1-49): add VoidCreditApplication action with invoice revert to partially_paid"
```

---

## Task 26: Acknowledge, Resolve, and Escalate Credit Memo Exception Actions

**Files:**
- Create: `apps/api/Domains/CreditMemo/Actions/AcknowledgeSupplierCreditMemoException.php`
- Create: `apps/api/Domains/CreditMemo/Actions/ResolveSupplierCreditMemoException.php`
- Create: `apps/api/Domains/CreditMemo/Actions/EscalateSupplierCreditMemoException.php`
- Create: `apps/api/Domains/CreditMemo/Data/SupplierCreditMemoExceptionData.php`

- [ ] **Step 1: Create exception data DTO**

```php
<?php

namespace Domains\CreditMemo\Data;

class SupplierCreditMemoExceptionData
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $creditMemoId,
        public readonly string $exceptionType,
        public readonly string $severity,
        public readonly string $description,
        public readonly ?string $expectedValue = null,
        public readonly ?string $adjustedValue = null,
    ) {}
}
```

- [ ] **Step 2: Create acknowledge action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AcknowledgeSupplierCreditMemoException
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemoException $exception, User $actor, int $lockVersion): SupplierCreditMemoException
    {
        return DB::transaction(function () use ($exception, $actor, $lockVersion): SupplierCreditMemoException {
            $exception = SupplierCreditMemoException::query()->whereKey($exception->id)->lockForUpdate()->firstOrFail();
            $exception->assertLockVersion($lockVersion);

            if ($exception->acknowledged_at !== null) {
                throw new ConflictHttpException('Exception is already acknowledged.');
            }

            $exception->forceFill([
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
                'lock_version' => $exception->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $exception->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.acknowledged',
                subject: $exception,
                metadata: [
                    'creditMemoId' => (string) $exception->supplier_credit_memo_id,
                    'exceptionType' => $exception->exception_type->value,
                ],
            ));

            return $exception->fresh();
        });
    }
}
```

- [ ] **Step 3: Create resolve action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveSupplierCreditMemoException
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(
        SupplierCreditMemoException $exception,
        User $actor,
        int $lockVersion,
        SupplierCreditMemoExceptionResolutionType $resolutionType,
        string $resolutionNotes,
    ): SupplierCreditMemoException {
        return DB::transaction(function () use ($exception, $actor, $lockVersion, $resolutionType, $resolutionNotes): SupplierCreditMemoException {
            $exception = SupplierCreditMemoException::query()->whereKey($exception->id)->lockForUpdate()->firstOrFail();
            $exception->assertLockVersion($lockVersion);

            if ($exception->resolved_at !== null) {
                throw new ConflictHttpException('Exception is already resolved.');
            }

            $exception->forceFill([
                'resolution_type' => $resolutionType,
                'resolution_notes' => $resolutionNotes,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'lock_version' => $exception->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $exception->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.resolved',
                subject: $exception,
                metadata: [
                    'creditMemoId' => (string) $exception->supplier_credit_memo_id,
                    'exceptionType' => $exception->exception_type->value,
                    'resolutionType' => $resolutionType->value,
                ],
            ));

            return $exception->fresh();
        });
    }
}
```

- [ ] **Step 4: Create escalate action**

```php
<?php

namespace Domains\CreditMemo\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EscalateSupplierCreditMemoException
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(SupplierCreditMemoException $exception, User $actor, int $lockVersion): SupplierCreditMemoException
    {
        return DB::transaction(function () use ($exception, $actor, $lockVersion): SupplierCreditMemoException {
            $exception = SupplierCreditMemoException::query()->whereKey($exception->id)->lockForUpdate()->firstOrFail();
            $exception->assertLockVersion($lockVersion);

            if ($exception->escalated_at !== null) {
                throw new ConflictHttpException('Exception is already escalated.');
            }

            $exception->forceFill([
                'escalated_by_user_id' => $actor->id,
                'escalated_at' => now(),
                'lock_version' => $exception->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $exception->tenant,
                actor: $actor,
                action: 'supplier_credit_memo_exception.escalated',
                subject: $exception,
                metadata: [
                    'creditMemoId' => (string) $exception->supplier_credit_memo_id,
                    'exceptionType' => $exception->exception_type->value,
                ],
            ));

            return $exception->fresh();
        });
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/CreditMemo/Actions/AcknowledgeSupplierCreditMemoException.php apps/api/Domains/CreditMemo/Actions/ResolveSupplierCreditMemoException.php apps/api/Domains/CreditMemo/Actions/EscalateSupplierCreditMemoException.php apps/api/Domains/CreditMemo/Data/SupplierCreditMemoExceptionData.php
git commit -m "feat(p1-49): add acknowledge/resolve/escalate actions for credit memo exceptions"
```

---

## Task 27: SupplierCreditMemoPolicy

**Files:**
- Create: `apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoPolicy.php`
- Create: `apps/api/tests/Feature/SupplierCreditMemoPolicyTest.php`

- [ ] **Step 1: Write failing test first**

Create `apps/api/tests/Feature/SupplierCreditMemoPolicyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Policies\SupplierCreditMemoPolicy;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierCreditMemoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_view_create_edit(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $creditMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Draft);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertTrue($policy->view($buyer, $creditMemo));
        $this->assertTrue($policy->create($buyer));
        $this->assertTrue($policy->update($buyer, $creditMemo));
    }

    public function test_requester_cannot_view(): void
    {
        [$tenant, $requester] = $this->tenantUserPair(TenantRole::Requester->value);
        $creditMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Draft);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertFalse($policy->view($requester, $creditMemo));
        $this->assertFalse($policy->create($requester));
    }

    public function test_cross_tenant_denied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $creditMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Draft);

        [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertFalse($policy->view($otherBuyer, $creditMemo));
    }

    public function test_submit_only_from_draft(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);
        $creditMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Open);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertFalse($policy->submit($buyer, $creditMemo));
    }

    public function test_apply_only_from_open_or_partially_applied(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $openMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Open);
        $closedMemo = $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Closed);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertTrue($policy->apply($buyer, $openMemo));
        $this->assertFalse($policy->apply($buyer, $closedMemo));
    }

    public function test_void_from_allowed_states(): void
    {
        [$tenant, $buyer] = $this->tenantUserPair(TenantRole::Buyer->value);

        $policy = app(SupplierCreditMemoPolicy::class);
        $this->assertTrue($policy->void($buyer, $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Draft)));
        $this->assertTrue($policy->void($buyer, $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Open)));
        $this->assertTrue($policy->void($buyer, $this->createCreditMemo($tenant, SupplierCreditMemoStatus::PartiallyApplied)));
        $this->assertFalse($policy->void($buyer, $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Closed)));
        $this->assertFalse($policy->void($buyer, $this->createCreditMemo($tenant, SupplierCreditMemoStatus::Voided)));
    }

    private function createCreditMemo(Tenant $tenant, SupplierCreditMemoStatus $status): SupplierCreditMemo
    {
        return SupplierCreditMemo::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'CM-POL-'.Str::random(6),
            'vendor_id' => (string) Str::uuid(),
            'status' => $status,
            'currency' => 'USD',
            'subtotal_amount' => '1000.0000',
            'tax_amount' => '0.0000',
            'freight_amount' => '0.0000',
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

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoPolicyTest`
Expected: FAIL with `Class "SupplierCreditMemoPolicy" not found`.

- [ ] **Step 2: Create the policy**

`apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoPolicy.php`:

```php
<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;

class SupplierCreditMemoPolicy
{
    public function view(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function update(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Draft;
    }

    public function submit(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Draft;
    }

    public function approve(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id);
    }

    public function reject(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->approve($user, $creditMemo);
    }

    public function requestChanges(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->approve($user, $creditMemo);
    }

    public function post(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState() === SupplierCreditMemoStatus::Approved;
    }

    public function apply(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && $creditMemo->statusState()->canAcceptCreditApplications();
    }

    public function void(User $user, SupplierCreditMemo $creditMemo): bool
    {
        $voidable = [
            SupplierCreditMemoStatus::Draft,
            SupplierCreditMemoStatus::PendingApproval,
            SupplierCreditMemoStatus::Approved,
            SupplierCreditMemoStatus::Open,
            SupplierCreditMemoStatus::PartiallyApplied,
        ];

        return $this->isTenantScoped($creditMemo->tenant_id)
            && $this->buyerOrAdmin($user)
            && in_array($creditMemo->statusState(), $voidable, true);
    }

    public function resolveException(User $user, SupplierCreditMemo $creditMemo): bool
    {
        return $this->isTenantScoped($creditMemo->tenant_id) && $this->buyerOrAdmin($user);
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

- [ ] **Step 3: Run test to verify pass**

Run: `cd apps/api && php artisan test --filter=SupplierCreditMemoPolicyTest`
Expected: PASS (6 tests).

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoPolicy.php apps/api/tests/Feature/SupplierCreditMemoPolicyTest.php
git commit -m "feat(p1-49): add SupplierCreditMemoPolicy with view, create, update, submit, apply, void, resolveException"
```

---

## Task 28: CreditApplicationPolicy and SupplierCreditMemoExceptionPolicy

**Files:**
- Create: `apps/api/Domains/CreditMemo/Policies/CreditApplicationPolicy.php`
- Create: `apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoExceptionPolicy.php`

- [ ] **Step 1: Create credit application policy**

```php
<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\CreditApplication;

class CreditApplicationPolicy
{
    public function view(User $user, CreditApplication $application): bool
    {
        return $this->isTenantScoped($application->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function void(User $user, CreditApplication $application): bool
    {
        return $this->isTenantScoped($application->tenant_id)
            && $this->buyerOrAdmin($user)
            && $application->voided_at === null;
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

- [ ] **Step 2: Create exception policy**

```php
<?php

namespace Domains\CreditMemo\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\CreditMemo\Models\SupplierCreditMemoException;

class SupplierCreditMemoExceptionPolicy
{
    public function view(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function acknowledge(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->acknowledged_at === null;
    }

    public function resolve(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->resolved_at === null;
    }

    public function escalate(User $user, SupplierCreditMemoException $exception): bool
    {
        return $this->isTenantScoped($exception->tenant_id)
            && $this->buyerOrAdmin($user)
            && $exception->escalated_at === null;
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

- [ ] **Step 3: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Policies\\CreditApplicationPolicy"), class_exists("Domains\\CreditMemo\\Policies\\SupplierCreditMemoExceptionPolicy"));`
Expected: prints `bool(true) bool(true)`.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/CreditMemo/Policies/CreditApplicationPolicy.php apps/api/Domains/CreditMemo/Policies/SupplierCreditMemoExceptionPolicy.php
git commit -m "feat(p1-49): add CreditApplicationPolicy and SupplierCreditMemoExceptionPolicy"
```

---

## Task 29: SupplierCreditMemoApprovalSubjectHandler

**Files:**
- Create: `apps/api/Domains/CreditMemo/SubjectHandlers/SupplierCreditMemoApprovalSubjectHandler.php`

- [ ] **Step 1: Create the handler**

```php
<?php

namespace Domains\CreditMemo\SubjectHandlers;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Contracts\ApprovalSubjectHandler;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Approval\Support\ApprovalSubjectSummary;
use Domains\CreditMemo\Actions\PostSupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SupplierCreditMemoApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function __construct(
        private readonly PostSupplierCreditMemo $postCreditMemo,
    ) {}

    public function subjectType(): string
    {
        return 'supplier_credit_memo';
    }

    public function modelClass(): string
    {
        return SupplierCreditMemo::class;
    }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing(['vendor', 'exceptions', 'lines']);

        // Note: ApprovalContextData is the shared DTO across handlers. The
        // `supplierInvoiceId` field is reused as the credit memo id so the
        // approval service can stamp a subjectId reference without a new field.
        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'supplier_credit_memo',
            requisitionId: null,
            requesterId: null,
            amount: (float) ($subject->total_amount ?? 0),
            currency: $subject->currency,
            department: null,
            costCenter: null,
            projectId: null,
            lineItemCategories: [],
            riskClassification: null,
            vendorId: $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
            supplierInvoiceId: (string) $subject->id,
            purchaseOrderId: null,
            purchaseOrderNumber: null,
            matchingStatus: null,
            exceptionCount: $subject->exceptions->count(),
            hasValueAdjustments: false,
            originalInvoiceAmount: (float) $subject->total_amount,
            totalVarianceAdjusted: 0.0,
        );
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing('vendor');

        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return new ApprovalSubjectSummary(
            type: 'supplier_credit_memo',
            id: (string) $subject->id,
            number: $subject->number,
            title: "Approve credit memo {$subject->number} from {$vendorName}",
            status: $subject->statusState()->value,
            primaryParty: $vendorName,
            amount: (float) ($subject->total_amount ?? 0),
            currency: $subject->currency,
            href: "/accounts-payable/credit-memos/{$subject->id}",
            metadata: [
                'supplierCreditMemoId' => (string) $subject->id,
                'supplierCreditMemoNumber' => $subject->number,
                'vendorId' => $subject->vendor_id !== null ? (string) $subject->vendor_id : null,
                'vendorName' => $vendorName,
                'originalInvoiceId' => $subject->original_invoice_id !== null ? (string) $subject->original_invoice_id : null,
            ],
        );
    }

    public function taskTitle(Model $subject): string
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing('vendor');
        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return "Approve credit memo {$subject->number} from {$vendorName}";
    }

    public function notificationSubjectLabel(Model $subject): ?string
    {
        assert($subject instanceof SupplierCreditMemo);

        return $subject->number;
    }

    public function notificationBody(Model $subject): string
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->loadMissing('vendor');
        $vendorName = $subject->vendor?->name ?? 'Unknown vendor';

        return "Credit memo {$subject->number} for {$subject->total_amount} {$subject->currency} from {$vendorName} requires approval.";
    }

    public function canDelegateTo(Model $subject, User $delegate): bool
    {
        return true;
    }

    public function delegationValidationMessage(Model $subject): string
    {
        return 'The selected delegate cannot approve this credit memo.';
    }

    public function escalationFallbackRecipients(Tenant $tenant, Model $subject, array $stageTemplate): iterable
    {
        $fallbackApprovers = collect($stageTemplate['fallbackApprovers'] ?? []);

        if ($fallbackApprovers->isEmpty()) {
            return $this->usersForRole($tenant, 'buyer')
                ->merge($this->usersForRole($tenant, 'admin'))
                ->unique('id')
                ->values();
        }

        return $fallbackApprovers
            ->flatMap(function (mixed $approver) use ($tenant): Collection {
                if (! is_array($approver)) {
                    return collect();
                }

                if (($approver['type'] ?? null) === 'user' && isset($approver['userId'])) {
                    $user = $tenant->users()->whereKey((int) $approver['userId'])->first();
                    return $user instanceof User ? collect([$user]) : collect();
                }

                if (($approver['type'] ?? null) === 'role' && isset($approver['role'])) {
                    return $this->usersForRole($tenant, (string) $approver['role']);
                }

                return collect();
            })
            ->unique('id')
            ->values();
    }

    public function onRouted(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierCreditMemo);
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        assert($subject instanceof SupplierCreditMemo);
        DB::transaction(function () use ($tenant, $subject, $actor) {
            $lockedMemo = SupplierCreditMemo::query()
                ->where('tenant_id', $tenant->id)
                ->where('id', $subject->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->postCreditMemo->handle($lockedMemo, $actor, (int) $lockedMemo->lock_version);
        });
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        assert($subject instanceof SupplierCreditMemo);
        $subject->forceFill([
            'status' => \Domains\CreditMemo\States\SupplierCreditMemoStatus::Draft,
            'lock_version' => $subject->lock_version + 1,
        ])->save();
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        assert($subject instanceof SupplierCreditMemo);
        $this->onRejected($tenant, $subject, $instance, $actor, $reason);
    }

    /**
     * @return Collection<int, User>
     */
    private function usersForRole(Tenant $tenant, string $role): Collection
    {
        return $tenant->users()
            ->wherePivot('role', $role)
            ->orderBy('users.id')
            ->get();
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\SubjectHandlers\\SupplierCreditMemoApprovalSubjectHandler"));`
Expected: prints `bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/SubjectHandlers/SupplierCreditMemoApprovalSubjectHandler.php
git commit -m "feat(p1-49): add SupplierCreditMemoApprovalSubjectHandler with onApproved auto-post"
```

---

## Task 30: 11 FormRequest Classes

**Files:**
- Create: 11 FormRequest classes under `apps/api/Domains/CreditMemo/Http/Requests/`

- [ ] **Step 1: Create the 11 request classes**

For each, use `authorize() { return true; }` and a `rules()` method.

`apps/api/Domains/CreditMemo/Http/Requests/CreateSupplierCreditMemoRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSupplierCreditMemoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vendorId' => ['required', 'integer', 'exists:vendors,id'],
            'originalInvoiceId' => ['nullable', 'string', 'exists:supplier_invoices,id'],
            'vendorCreditMemoNumber' => ['required', 'string', 'max:255'],
            'creditDate' => ['required', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotalAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'freightAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'totalAmount' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.lineNumber' => ['required', 'integer', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:1000'],
            'lines.*.quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'lines.*.unitPrice' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'lines.*.taxCode' => ['nullable', 'string', 'max:50'],
            'lines.*.taxAmount' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'lines.*.purchaseOrderLineId' => ['nullable', 'string', 'exists:purchase_order_lines,id'],
            'lines.*.originalInvoiceLineId' => ['nullable', 'string', 'exists:supplier_invoice_lines,id'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/UpdateSupplierCreditMemoRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierCreditMemoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'creditDate' => ['nullable', 'date'],
            'vendorCreditMemoNumber' => ['nullable', 'string', 'max:255'],
            'freightAmount' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/SubmitSupplierCreditMemoForApprovalRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSupplierCreditMemoForApprovalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/PostSupplierCreditMemoRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostSupplierCreditMemoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/VoidSupplierCreditMemoRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidSupplierCreditMemoRequest extends FormRequest
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

`apps/api/Domains/CreditMemo/Http/Requests/AddSupplierCreditMemoLineRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddSupplierCreditMemoLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'lineNumber' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:1000'],
            'quantity' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'unitPrice' => ['required', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxCode' => ['nullable', 'string', 'max:50'],
            'taxAmount' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'purchaseOrderLineId' => ['nullable', 'string', 'exists:purchase_order_lines,id'],
            'originalInvoiceLineId' => ['nullable', 'string', 'exists:supplier_invoice_lines,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/UpdateSupplierCreditMemoLineRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierCreditMemoLineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'quantity' => ['nullable', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'unitPrice' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'taxCode' => ['nullable', 'string', 'max:50'],
            'taxAmount' => ['nullable', 'string', 'regex:/^-?\d+(\.\d{1,4})?$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/CreateCreditApplicationRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCreditApplicationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'supplierInvoiceId' => ['required', 'string', 'exists:supplier_invoices,id'],
            'appliedAmount' => ['required', 'string', 'regex:/^\d+(\.\d{1,4})?$/'],
            'applicationDate' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/VoidCreditApplicationRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidCreditApplicationRequest extends FormRequest
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

`apps/api/Domains/CreditMemo/Http/Requests/AcknowledgeSupplierCreditMemoExceptionRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeSupplierCreditMemoExceptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

`apps/api/Domains/CreditMemo/Http/Requests/ResolveSupplierCreditMemoExceptionRequest.php`:

```php
<?php

namespace Domains\CreditMemo\Http\Requests;

use Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveSupplierCreditMemoExceptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'resolutionType' => ['required', Rule::enum(SupplierCreditMemoExceptionResolutionType::class)],
            'resolutionNotes' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 2: Verify autoloading**

Run: `cd apps/api && php -r 'require "vendor/autoload.php"; var_dump(class_exists("Domains\\CreditMemo\\Http\\Requests\\CreateSupplierCreditMemoRequest"), class_exists("Domains\\CreditMemo\\Http\\Requests\\ResolveSupplierCreditMemoExceptionRequest"));'
Expected: prints `bool(true) bool(true)`.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/CreditMemo/Http/Requests
git commit -m "feat(p1-49): add 11 form request classes for credit memo CRUD, lines, applications, exceptions"
```

---

## Task 31: 4 Resource Classes

**Files:**
- Create: `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoResource.php`
- Create: `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoLineResource.php`
- Create: `apps/api/Domains/CreditMemo/Http/Resources/CreditApplicationResource.php`
- Create: `apps/api/Domains/CreditMemo/Http/Resources/SupplierCreditMemoExceptionResource.php`

- [ ] **Step 1: Create SupplierCreditMemoLineResource**

```php
<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierCreditMemoLine
 */
class SupplierCreditMemoLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'lineNumber' => (int) $this->line_number,
            'description' => $this->description_snapshot,
            'quantity' => (string) $this->quantity,
            'unitPrice' => (string) $this->unit_price,
            'lineSubtotal' => (string) $this->line_subtotal,
            'taxCode' => $this->tax_code,
            'taxAmount' => (string) $this->tax_amount,
            'purchaseOrderLineId' => $this->purchase_order_line_id !== null ? (string) $this->purchase_order_line_id : null,
            'originalInvoiceLineId' => $this->original_invoice_line_id !== null ? (string) $this->original_invoice_line_id : null,
            'notes' => $this->notes,
        ];
    }
}
```

- [ ] **Step 2: Create CreditApplicationResource**

```php
<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\CreditApplication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditApplication
 */
class CreditApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'supplierCreditMemoNumber' => $this->whenLoaded('creditMemo', fn () => $this->creditMemo?->number),
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'supplierInvoiceNumber' => $this->whenLoaded('invoice', fn () => $this->invoice?->number),
            'appliedAmount' => (string) $this->applied_amount,
            'applicationDate' => $this->application_date?->toDateString(),
            'appliedByUserId' => (string) $this->applied_by_user_id,
            'notes' => $this->notes,
            'voidedAt' => $this->voided_at?->toISOString(),
            'voidedByUserId' => $this->voided_by_user_id !== null ? (string) $this->voided_by_user_id : null,
            'voidReason' => $this->void_reason,
            'lockVersion' => $this->lock_version,
        ];
    }
}
```

- [ ] **Step 3: Create SupplierCreditMemoExceptionResource**

```php
<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierCreditMemoException
 */
class SupplierCreditMemoExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierCreditMemoId' => (string) $this->supplier_credit_memo_id,
            'exceptionType' => $this->exception_type?->value,
            'severity' => $this->severity?->value,
            'description' => $this->description,
            'resolutionType' => $this->resolution_type?->value,
            'resolutionNotes' => $this->resolution_notes,
            'resolvedByUserId' => $this->resolved_by_user_id !== null ? (string) $this->resolved_by_user_id : null,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'acknowledgedByUserId' => $this->acknowledged_by_user_id !== null ? (string) $this->acknowledged_by_user_id : null,
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'escalatedByUserId' => $this->escalated_by_user_id !== null ? (string) $this->escalated_by_user_id : null,
            'escalatedAt' => $this->escalated_at?->toISOString(),
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'adjustedValue' => $this->adjusted_value !== null ? (string) $this->adjusted_value : null,
            'lockVersion' => $this->lock_version,
        ];
    }
}
```

- [ ] **Step 4: Create SupplierCreditMemoResource**

```php
<?php

namespace Domains\CreditMemo\Http\Resources;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin SupplierCreditMemo
 */
class SupplierCreditMemoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $creditMemo = $this->resource;
        $creditMemo->loadMissing(['lines', 'applications', 'exceptions', 'vendor', 'originalInvoice']);

        $applicationsArray = $creditMemo->relationLoaded('applications')
            ? CreditApplicationResource::collection($creditMemo->applications)->resolve()
            : [];
        $linesArray = $creditMemo->relationLoaded('lines')
            ? SupplierCreditMemoLineResource::collection($creditMemo->lines)->resolve()
            : [];
        $exceptionsArray = $creditMemo->relationLoaded('exceptions')
            ? SupplierCreditMemoExceptionResource::collection($creditMemo->exceptions)->resolve()
            : [];

        $appliedSum = app(CreditApplicationSumCalculator::class)->sumForCreditMemo($creditMemo);

        return [
            'id' => (string) $creditMemo->id,
            'tenantId' => (string) $creditMemo->tenant_id,
            'number' => $creditMemo->number,
            'vendorCreditMemoNumber' => $creditMemo->vendor_credit_memo_number,
            'vendorId' => (string) $creditMemo->vendor_id,
            'vendorName' => $creditMemo->vendor?->name,
            'originalInvoiceId' => $creditMemo->original_invoice_id !== null ? (string) $creditMemo->original_invoice_id : null,
            'originalInvoiceNumber' => $creditMemo->originalInvoice?->number,
            'status' => $creditMemo->statusState()->value,
            'currency' => $creditMemo->currency,
            'subtotalAmount' => (string) $creditMemo->subtotal_amount,
            'taxAmount' => (string) $creditMemo->tax_amount,
            'freightAmount' => (string) $creditMemo->freight_amount,
            'totalAmount' => (string) $creditMemo->total_amount,
            'appliedAmount' => $appliedSum,
            'remainingAmount' => bcsub((string) $creditMemo->total_amount, $appliedSum, 4),
            'creditDate' => $creditMemo->credit_date?->toDateString(),
            'notes' => $creditMemo->notes,
            'capturedByUserId' => $creditMemo->captured_by_user_id !== null ? (string) $creditMemo->captured_by_user_id : null,
            'capturedAt' => $creditMemo->captured_at?->toISOString(),
            'submittedByUserId' => $creditMemo->submitted_by_user_id !== null ? (string) $creditMemo->submitted_by_user_id : null,
            'submittedAt' => $creditMemo->submitted_at?->toISOString(),
            'approvedByUserId' => $creditMemo->approved_by_user_id !== null ? (string) $creditMemo->approved_by_user_id : null,
            'approvedAt' => $creditMemo->approved_at?->toISOString(),
            'postedByUserId' => $creditMemo->posted_by_user_id !== null ? (string) $creditMemo->posted_by_user_id : null,
            'postedAt' => $creditMemo->posted_at?->toISOString(),
            'voidedByUserId' => $creditMemo->voided_by_user_id !== null ? (string) $creditMemo->voided_by_user_id : null,
            'voidedAt' => $creditMemo->voided_at?->toISOString(),
            'voidReason' => $creditMemo->void_reason,
            'approvalInstanceId' => $creditMemo->approval_instance_id !== null ? (string) $creditMemo->approval_instance_id : null,
            'stpEligible' => (bool) $creditMemo->stp_eligible,
            'stpProcessedAt' => $creditMemo->stp_processed_at?->toISOString(),
            'lockVersion' => $creditMemo->lock_version,
            'lines' => $linesArray,
            'applications' => $applicationsArray,
            'exceptions' => $exceptionsArray,
            'createdAt' => $creditMemo->created_at?->toISOString(),
            'updatedAt' => $creditMemo->updated_at?->toISOString(),
            'permissions' => [
                'canEdit' => Gate::allows('update', $creditMemo),
                'canSubmit' => Gate::allows('submit', $creditMemo),
                'canApprove' => Gate::allows('approve', $creditMemo),
                'canReject' => Gate::allows('reject', $creditMemo),
                'canRequestChanges' => Gate::allows('requestChanges', $creditMemo),
                'canPost' => Gate::allows('post', $creditMemo),
                'canApply' => Gate::allows('apply', $creditMemo),
                'canVoidApplication' => Gate::allows('apply', $creditMemo),
                'canVoidCreditMemo' => Gate::allows('void', $creditMemo),
                'canResolveException' => Gate::allows('resolveException', $creditMemo),
            ],
        ];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/CreditMemo/Http/Resources
git commit -m "feat(p1-49): add SupplierCreditMemo, Line, Application, Exception resources with permissions"
```

---

## Task 32: 4 Controllers

**Files:**
- Create: `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoController.php`
- Create: `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoLineController.php`
- Create: `apps/api/Domains/CreditMemo/Http/Controllers/CreditApplicationController.php`
- Create: `apps/api/Domains/CreditMemo/Http/Controllers/SupplierCreditMemoExceptionController.php`

- [ ] **Step 1: Create SupplierCreditMemoController**

```php
<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\CreateSupplierCreditMemo;
use Domains\CreditMemo\Actions\PostSupplierCreditMemo;
use Domains\CreditMemo\Actions\SubmitSupplierCreditMemoForApproval;
use Domains\CreditMemo\Actions\UpdateSupplierCreditMemo;
use Domains\CreditMemo\Actions\VoidSupplierCreditMemo;
use Domains\CreditMemo\Data\SupplierCreditMemoContextData;
use Domains\CreditMemo\Http\Requests\CreateSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\PostSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\SubmitSupplierCreditMemoForApprovalRequest;
use Domains\CreditMemo\Http\Requests\UpdateSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Requests\VoidSupplierCreditMemoRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierCreditMemoController
{
    use AuthorizesRequests;

    public function index(CurrentTenant $currentTenant, Request $request): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $prototype = new SupplierCreditMemo(['tenant_id' => $tenant->id]);
        $this->authorize('view', $prototype);

        $query = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->with(['vendor', 'originalInvoice']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($vendorId = $request->query('vendorId')) {
            $query->where('vendor_id', $vendorId);
        }

        $perPage = min(max((int) $request->integer('perPage', 25), 1), 100);
        $memos = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => SupplierCreditMemoResource::collection($memos->getCollection())->resolve(),
            'meta' => [
                'total' => $memos->total(),
                'perPage' => $memos->perPage(),
                'currentPage' => $memos->currentPage(),
            ],
        ]);
    }

    public function store(
        CreateSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        CreateSupplierCreditMemo $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $prototype = new SupplierCreditMemo(['tenant_id' => $tenant->id]);
        $this->authorize('create', $prototype);

        $payload = $request->validated();

        $context = new SupplierCreditMemoContextData(
            tenantId: (int) $tenant->id,
            vendorId: (int) $payload['vendorId'],
            originalInvoiceId: isset($payload['originalInvoiceId']) ? (int) $payload['originalInvoiceId'] : null,
            vendorCreditMemoNumber: (string) $payload['vendorCreditMemoNumber'],
            creditDate: (string) $payload['creditDate'],
            currency: (string) ($payload['currency'] ?? ''),
            subtotalAmount: (string) $payload['subtotalAmount'],
            taxAmount: (string) $payload['taxAmount'],
            freightAmount: (string) $payload['freightAmount'],
            totalAmount: (string) $payload['totalAmount'],
            notes: $payload['notes'] ?? null,
            lines: $payload['lines'],
        );

        $creditMemo = $action->handle($tenant, $request->user(), $context);

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo->fresh(['lines', 'exceptions', 'vendor'])))->resolve(),
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, SupplierCreditMemo $creditMemo, Request $request): JsonResponse
    {
        $creditMemo = $this->findTenantCreditMemo($this->tenantOrAbort($currentTenant), $creditMemo);
        $this->authorize('view', $creditMemo);

        return response()->json([
            'data' => (new SupplierCreditMemoResource($creditMemo->load(['lines', 'applications', 'exceptions', 'vendor', 'originalInvoice'])))->resolve($request),
        ]);
    }

    public function update(
        UpdateSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        UpdateSupplierCreditMemo $action,
    ): JsonResponse {
        $creditMemo = $this->findTenantCreditMemo($this->tenantOrAbort($currentTenant), $creditMemo);
        $this->authorize('update', $creditMemo);

        $payload = $request->validated();
        $result = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $payload['lockVersion'],
            $payload['notes'] ?? null,
            $payload['creditDate'] ?? null,
            $payload['vendorCreditMemoNumber'] ?? null,
            $payload['freightAmount'] ?? null,
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($result->fresh()))->resolve(),
        ]);
    }

    public function submit(
        SubmitSupplierCreditMemoForApprovalRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SubmitSupplierCreditMemoForApproval $action,
    ): JsonResponse {
        $creditMemo = $this->findTenantCreditMemo($this->tenantOrAbort($currentTenant), $creditMemo);
        $this->authorize('submit', $creditMemo);

        $result = $action->handle($creditMemo, $request->user(), (int) $request->validated('lockVersion'));

        return response()->json([
            'data' => (new SupplierCreditMemoResource($result->fresh(['lines', 'exceptions', 'vendor', 'originalInvoice'])))->resolve(),
        ]);
    }

    public function post(
        PostSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        PostSupplierCreditMemo $action,
    ): JsonResponse {
        $creditMemo = $this->findTenantCreditMemo($this->tenantOrAbort($currentTenant), $creditMemo);
        $this->authorize('post', $creditMemo);

        $result = $action->handle($creditMemo, $request->user(), (int) $request->validated('lockVersion'));

        return response()->json([
            'data' => (new SupplierCreditMemoResource($result->fresh()))->resolve(),
        ]);
    }

    public function void(
        VoidSupplierCreditMemoRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        VoidSupplierCreditMemo $action,
    ): JsonResponse {
        $creditMemo = $this->findTenantCreditMemo($this->tenantOrAbort($currentTenant), $creditMemo);
        $this->authorize('void', $creditMemo);

        $result = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $request->validated('lockVersion'),
            (string) $request->validated('voidReason'),
        );

        return response()->json([
            'data' => (new SupplierCreditMemoResource($result->fresh(['lines', 'applications'])))->resolve(),
        ]);
    }

    private function findTenantCreditMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        $tenantMemo = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($creditMemo->id)
            ->first();

        if ($tenantMemo === null) {
            abort(403, 'You are not allowed to access this credit memo.');
        }

        return $tenantMemo;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 2: Create SupplierCreditMemoLineController**

```php
<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\AddSupplierCreditMemoLine;
use Domains\CreditMemo\Actions\RemoveSupplierCreditMemoLine;
use Domains\CreditMemo\Actions\UpdateSupplierCreditMemoLine;
use Domains\CreditMemo\Http\Requests\AddSupplierCreditMemoLineRequest;
use Domains\CreditMemo\Http\Requests\UpdateSupplierCreditMemoLineRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoLineResource;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierCreditMemoLineController
{
    use AuthorizesRequests;

    public function store(
        AddSupplierCreditMemoLineRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        AddSupplierCreditMemoLine $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $this->authorize('update', $creditMemo);

        $payload = $request->validated();
        $line = $action->handle(
            $creditMemo,
            $request->user(),
            (int) $payload['lockVersion'],
            (int) $payload['lineNumber'],
            (string) $payload['description'],
            (string) $payload['quantity'],
            (string) $payload['unitPrice'],
            $payload['taxCode'] ?? null,
            $payload['taxAmount'] ?? null,
            $payload['purchaseOrderLineId'] ?? null,
            $payload['originalInvoiceLineId'] ?? null,
            $payload['notes'] ?? null,
        );

        return response()->json([
            'data' => (new SupplierCreditMemoLineResource($line))->resolve(),
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo->fresh(['lines'])))->resolve(),
        ], 201);
    }

    public function update(
        UpdateSupplierCreditMemoLineRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoLine $line,
        UpdateSupplierCreditMemoLine $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $line = $this->findTenantLine($tenant, $line);
        $this->authorize('update', $creditMemo);

        $payload = $request->validated();
        $result = $action->handle(
            $line,
            $request->user(),
            (int) $payload['lockVersion'],
            $payload['description'] ?? null,
            $payload['quantity'] ?? null,
            $payload['unitPrice'] ?? null,
            $payload['taxCode'] ?? null,
            $payload['taxAmount'] ?? null,
            $payload['notes'] ?? null,
        );

        return response()->json([
            'data' => (new SupplierCreditMemoLineResource($result))->resolve(),
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo->fresh(['lines'])))->resolve(),
        ]);
    }

    public function destroy(
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoLine $line,
        RemoveSupplierCreditMemoLine $action,
        \Illuminate\Http\Request $request,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $line = $this->findTenantLine($tenant, $line);
        $this->authorize('update', $creditMemo);

        $lockVersion = (int) $request->input('lockVersion', $line->lock_version);
        $action->handle($line, $request->user(), $lockVersion);

        return response()->json([
            'creditMemo' => (new SupplierCreditMemoResource($creditMemo->fresh(['lines'])))->resolve(),
        ]);
    }

    private function findTenantMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        $tenantMemo = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($creditMemo->id)
            ->first();

        if ($tenantMemo === null) {
            abort(403, 'You are not allowed to access this credit memo.');
        }

        return $tenantMemo;
    }

    private function findTenantLine(Tenant $tenant, SupplierCreditMemoLine $line): SupplierCreditMemoLine
    {
        $tenantLine = SupplierCreditMemoLine::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($line->id)
            ->first();

        if ($tenantLine === null) {
            abort(403, 'You are not allowed to access this credit memo line.');
        }

        return $tenantLine;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 3: Create CreditApplicationController**

```php
<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\CreateCreditApplication;
use Domains\CreditMemo\Actions\VoidCreditApplication;
use Domains\CreditMemo\Http\Requests\CreateCreditApplicationRequest;
use Domains\CreditMemo\Http\Requests\VoidCreditApplicationRequest;
use Domains\CreditMemo\Http\Resources\CreditApplicationResource;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Policies\CreditApplicationPolicy;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CreditApplicationController
{
    use AuthorizesRequests;

    public function index(CurrentTenant $currentTenant, SupplierCreditMemo $creditMemo): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $prototype = new CreditApplication(['tenant_id' => $tenant->id]);
        $this->authorize('view', $prototype);

        $applications = CreditApplication::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->with(['invoice', 'creditMemo'])
            ->orderBy('application_date')
            ->get();

        return response()->json([
            'data' => CreditApplicationResource::collection($applications)->resolve(),
        ]);
    }

    public function store(
        CreateCreditApplicationRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        CreateCreditApplication $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $this->authorize('create', new CreditApplication(['tenant_id' => $tenant->id]));

        $payload = $request->validated();
        $invoice = \Domains\Invoice\Models\SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($payload['supplierInvoiceId'])
            ->firstOrFail();

        $application = $action->handle(
            $creditMemo,
            $invoice,
            $request->user(),
            (int) $payload['lockVersion'],
            (string) $payload['appliedAmount'],
            (string) $payload['applicationDate'],
            $payload['notes'] ?? null,
        );

        return response()->json([
            'data' => (new CreditApplicationResource($application->fresh(['invoice', 'creditMemo'])))->resolve(),
        ], 201);
    }

    public function show(CurrentTenant $currentTenant, CreditApplication $application): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantApplication = $this->findTenantApplication($tenant, $application);
        $this->authorize('view', $tenantApplication);

        return response()->json([
            'data' => (new CreditApplicationResource($tenantApplication->load(['invoice', 'creditMemo'])))->resolve(),
        ]);
    }

    public function destroy(
        VoidCreditApplicationRequest $request,
        CurrentTenant $currentTenant,
        CreditApplication $application,
        VoidCreditApplication $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $tenantApplication = $this->findTenantApplication($tenant, $application);
        $this->authorize('void', $tenantApplication);

        $result = $action->handle(
            $tenantApplication,
            $request->user(),
            (int) $request->validated('lockVersion'),
            (string) $request->validated('voidReason'),
        );

        return response()->json([
            'data' => (new CreditApplicationResource($result->fresh(['invoice', 'creditMemo'])))->resolve(),
        ]);
    }

    private function findTenantMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        $tenantMemo = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($creditMemo->id)
            ->first();

        if ($tenantMemo === null) {
            abort(403, 'You are not allowed to access this credit memo.');
        }

        return $tenantMemo;
    }

    private function findTenantApplication(Tenant $tenant, CreditApplication $application): CreditApplication
    {
        $tenantApp = CreditApplication::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($application->id)
            ->first();

        if ($tenantApp === null) {
            abort(403, 'You are not allowed to access this credit application.');
        }

        return $tenantApp;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 4: Create SupplierCreditMemoExceptionController**

```php
<?php

namespace Domains\CreditMemo\Http\Controllers;

use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\CreditMemo\Actions\AcknowledgeSupplierCreditMemoException;
use Domains\CreditMemo\Actions\EscalateSupplierCreditMemoException;
use Domains\CreditMemo\Actions\ResolveSupplierCreditMemoException;
use Domains\CreditMemo\Http\Requests\AcknowledgeSupplierCreditMemoExceptionRequest;
use Domains\CreditMemo\Http\Requests\ResolveSupplierCreditMemoExceptionRequest;
use Domains\CreditMemo\Http\Resources\SupplierCreditMemoExceptionResource;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class SupplierCreditMemoExceptionController
{
    use AuthorizesRequests;

    public function index(CurrentTenant $currentTenant, SupplierCreditMemo $creditMemo): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $creditMemo = $this->findTenantMemo($tenant, $creditMemo);
        $prototype = new SupplierCreditMemoException(['tenant_id' => $tenant->id]);
        $this->authorize('view', $prototype);

        $exceptions = SupplierCreditMemoException::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_credit_memo_id', $creditMemo->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => SupplierCreditMemoExceptionResource::collection($exceptions)->resolve(),
        ]);
    }

    public function acknowledge(
        AcknowledgeSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        AcknowledgeSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->findTenantMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception);
        $this->authorize('acknowledge', $exception);

        $result = $action->handle($exception, $request->user(), (int) $request->validated('lockVersion'));

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($result))->resolve(),
        ]);
    }

    public function resolve(
        ResolveSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        ResolveSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->findTenantMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception);
        $this->authorize('resolve', $exception);

        $result = $action->handle(
            $exception,
            $request->user(),
            (int) $request->validated('lockVersion'),
            \Domains\CreditMemo\States\SupplierCreditMemoExceptionResolutionType::from((string) $request->validated('resolutionType')),
            (string) $request->validated('resolutionNotes'),
        );

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($result))->resolve(),
        ]);
    }

    public function escalate(
        AcknowledgeSupplierCreditMemoExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierCreditMemo $creditMemo,
        SupplierCreditMemoException $exception,
        EscalateSupplierCreditMemoException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $this->findTenantMemo($tenant, $creditMemo);
        $exception = $this->findTenantException($tenant, $exception);
        $this->authorize('escalate', $exception);

        $result = $action->handle($exception, $request->user(), (int) $request->validated('lockVersion'));

        return response()->json([
            'data' => (new SupplierCreditMemoExceptionResource($result))->resolve(),
        ]);
    }

    private function findTenantMemo(Tenant $tenant, SupplierCreditMemo $creditMemo): SupplierCreditMemo
    {
        $tenantMemo = SupplierCreditMemo::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($creditMemo->id)
            ->first();
        if ($tenantMemo === null) {
            abort(403, 'You are not allowed to access this credit memo.');
        }
        return $tenantMemo;
    }

    private function findTenantException(Tenant $tenant, SupplierCreditMemoException $exception): SupplierCreditMemoException
    {
        $tenantException = SupplierCreditMemoException::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($exception->id)
            ->first();
        if ($tenantException === null) {
            abort(403, 'You are not allowed to access this exception.');
        }
        return $tenantException;
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/CreditMemo/Http/Controllers
git commit -m "feat(p1-49): add SupplierCreditMemo, Line, CreditApplication, and Exception controllers"
```

---

## Task 33: Register 20 Credit Memo Routes + Extend Supplier Invoice paymentStatus Filter

**Files:**
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add controller imports at the top of `routes/api.php`**

In the `use` block, after the existing `Domains\AccountsPayable\Http\Controllers\SupplierInvoicePaymentController` import, add:

```php
use Domains\CreditMemo\Http\Controllers\SupplierCreditMemoController;
use Domains\CreditMemo\Http\Controllers\SupplierCreditMemoLineController;
use Domains\CreditMemo\Http\Controllers\CreditApplicationController;
use Domains\CreditMemo\Http\Controllers\SupplierCreditMemoExceptionController;
```

- [ ] **Step 2: Insert 20 credit memo routes inside the `RequireTenantHeader` middleware group**

Immediately after the `Route::post('/accounts-payable/payment-imports/{import}/discard', ...)` line (around line 241), still inside the `Route::middleware(RequireTenantHeader::class)->group(...)` block, add:

```php
            // P1-49: credit memo CRUD
            Route::get('/supplier-credit-memos', [SupplierCreditMemoController::class, 'index']);
            Route::post('/supplier-credit-memos', [SupplierCreditMemoController::class, 'store']);
            Route::get('/supplier-credit-memos/{creditMemo}', [SupplierCreditMemoController::class, 'show']);
            Route::patch('/supplier-credit-memos/{creditMemo}', [SupplierCreditMemoController::class, 'update']);
            Route::post('/supplier-credit-memos/{creditMemo}/submit', [SupplierCreditMemoController::class, 'submit']);
            Route::post('/supplier-credit-memos/{creditMemo}/post', [SupplierCreditMemoController::class, 'post']);
            Route::post('/supplier-credit-memos/{creditMemo}/void', [SupplierCreditMemoController::class, 'void']);

            // P1-49: credit memo lines
            Route::post('/supplier-credit-memos/{creditMemo}/lines', [SupplierCreditMemoLineController::class, 'store']);
            Route::patch('/supplier-credit-memos/{creditMemo}/lines/{line}', [SupplierCreditMemoLineController::class, 'update']);
            Route::delete('/supplier-credit-memos/{creditMemo}/lines/{line}', [SupplierCreditMemoLineController::class, 'destroy']);

            // P1-49: credit applications
            Route::get('/supplier-credit-memos/{creditMemo}/applications', [CreditApplicationController::class, 'index']);
            Route::post('/supplier-credit-memos/{creditMemo}/applications', [CreditApplicationController::class, 'store']);
            Route::get('/credit-applications/{application}', [CreditApplicationController::class, 'show']);
            Route::delete('/credit-applications/{application}', [CreditApplicationController::class, 'destroy']);

            // P1-49: credit memo exceptions
            Route::get('/supplier-credit-memos/{creditMemo}/exceptions', [SupplierCreditMemoExceptionController::class, 'index']);
            Route::post('/supplier-credit-memos/{creditMemo}/exceptions/{exception}/acknowledge', [SupplierCreditMemoExceptionController::class, 'acknowledge']);
            Route::post('/supplier-credit-memos/{creditMemo}/exceptions/{exception}/resolve', [SupplierCreditMemoExceptionController::class, 'resolve']);
            Route::post('/supplier-credit-memos/{creditMemo}/exceptions/{exception}/escalate', [SupplierCreditMemoExceptionController::class, 'escalate']);
```

- [ ] **Step 3: Extend the `paymentStatus` filter on `supplier-invoices` queue**

In `SupplierInvoiceController::applyQueueFilters()` (file: `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceController.php`), update the `$validPaymentStatuses` array to include `reversed`, `paid`, `partially_paid`, `payment_scheduled`:

Replace the line:
```php
$validPaymentStatuses = ['none', 'any', 'payment_eligible', 'on_hold', 'payment_ready', 'handoff_exported'];
```

with:
```php
$validPaymentStatuses = ['none', 'any', 'payment_eligible', 'on_hold', 'payment_ready', 'handoff_exported', 'payment_scheduled', 'partially_paid', 'paid', 'reversed'];
```

- [ ] **Step 4: Verify the new routes are registered**

Run:
```bash
cd apps/api && php artisan route:list --path=supplier-credit-memos 2>&1 | head -30
cd apps/api && php artisan route:list --path=credit-applications 2>&1 | head -10
```

Expected: lists `supplier-credit-memos` (GET, POST), `supplier-credit-memos/{creditMemo}` (GET, PATCH), `submit`, `post`, `void`, `lines` (POST, PATCH, DELETE), `applications` (GET, POST), `credit-applications/{application}` (GET, DELETE), `exceptions` (GET), `acknowledge`, `resolve`, `escalate`.

- [ ] **Step 5: Commit**

```bash
git add apps/api/routes/api.php apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceController.php
git commit -m "feat(p1-49): register 20 credit memo routes and extend supplier-invoice paymentStatus filter"
```

---

## Task 34: Register Policies, Audit Subject Types, and Approval Subject Handler in AppServiceProvider

**Files:**
- Modify: `apps/api/app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add new imports**

In the `use` block, add the credit memo imports after the existing `Domains\Payments\Models\ApPaymentImport` import:

```php
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\Policies\CreditApplicationPolicy;
use Domains\CreditMemo\Policies\SupplierCreditMemoExceptionPolicy;
use Domains\CreditMemo\Policies\SupplierCreditMemoPolicy;
use Domains\CreditMemo\SubjectHandlers\SupplierCreditMemoApprovalSubjectHandler;
```

- [ ] **Step 2: Register the new approval subject handler in `register()`**

Inside the `register()` method, add the new handler to the `ApprovalSubjectRegistry` singleton array (after `SupplierInvoiceApprovalSubjectHandler`):

```php
        $this->app->singleton(ApprovalSubjectRegistry::class, fn ($app) => new ApprovalSubjectRegistry([
            $app->make(RequisitionApprovalSubjectHandler::class),
            $app->make(RfqAwardRecommendationApprovalSubjectHandler::class),
            $app->make(PurchaseOrderApprovalSubjectHandler::class),
            $app->make(SupplierInvoiceApprovalSubjectHandler::class),
            $app->make(SupplierCreditMemoApprovalSubjectHandler::class),
        ]));
```

- [ ] **Step 3: Register policies in `boot()`**

Add three `Gate::policy` calls after the `Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class)` line:

```php
        Gate::policy(SupplierCreditMemo::class, SupplierCreditMemoPolicy::class);
        Gate::policy(CreditApplication::class, CreditApplicationPolicy::class);
        Gate::policy(SupplierCreditMemoException::class, SupplierCreditMemoExceptionPolicy::class);
```

- [ ] **Step 4: Register audit subject types in `boot()`**

After the existing `AuditSubject::registerType(ApPaymentImport::class, 'ap_payment_import')` line, add:

```php
        AuditSubject::registerType(SupplierCreditMemo::class, 'supplier_credit_memo');
        AuditSubject::registerType(SupplierCreditMemoLine::class, 'supplier_credit_memo_line');
        AuditSubject::registerType(CreditApplication::class, 'credit_application');
        AuditSubject::registerType(SupplierCreditMemoException::class, 'supplier_credit_memo_exception');
```

- [ ] **Step 5: Verify registrations**

Run:
```bash
cd apps/api && php artisan tinker --execute='echo json_encode(\\\\App\\\\Audit\\\\AuditSubject::publicTypes());'
```

Expected output contains `"supplier_credit_memo"`, `"supplier_credit_memo_line"`, `"credit_application"`, and `"supplier_credit_memo_exception"`.

- [ ] **Step 6: Commit**

```bash
git add apps/api/app/Providers/AppServiceProvider.php
git commit -m "feat(p1-49): register credit memo policies, audit subjects, and approval subject handler"
```

---

## Task 35: Extend SupplierInvoiceResource and SupplierInvoiceQueueResource

**Files:**
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php`

- [ ] **Step 1: Extend SupplierInvoiceResource**

Replace the full file content of `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php`:

```php
<?php

namespace Domains\Invoice\Http\Resources;

use Domains\CreditMemo\Http\Resources\CreditApplicationResource;
use Domains\CreditMemo\Support\CreditApplicationSumCalculator;
use Domains\Invoice\Data\SupplierInvoiceReviewChecklistData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\Models\ApPaymentAllocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin SupplierInvoice
 */
class SupplierInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paidSum = $this->relationLoaded('paymentAllocations') || ApPaymentAllocation::query()->where('supplier_invoice_id', $this->id)->exists()
            ? (string) ApPaymentAllocation::query()
                ->where('supplier_invoice_id', $this->id)
                ->whereNull('voided_at')
                ->sum('allocated_amount')
            : '0.0000';
        $creditSum = app(CreditApplicationSumCalculator::class)->sumForInvoice($this->resource);
        $outstanding = bcsub(
            bcsub((string) $this->total_amount, $paidSum, 4),
            $creditSum,
            4
        );

        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'vendorId' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
            'number' => $this->number,
            'invoiceNumber' => $this->invoice_number,
            'status' => $this->statusState()->value,
            'paymentStatus' => $this->payment_status?->value,
            'paidAmount' => $paidSum,
            'creditAppliedAmount' => $creditSum,
            'outstandingAmount' => $outstanding,
            'invoiceDate' => $this->invoice_date?->toDateString(),
            'dueDate' => $this->due_date?->toDateString(),
            'currency' => $this->currency,
            'subtotalAmount' => (string) $this->subtotal_amount,
            'taxAmount' => (string) ($this->tax_amount ?? '0.00'),
            'freightAmount' => (string) ($this->freight_amount ?? '0.00'),
            'totalAmount' => (string) $this->total_amount,
            'notes' => $this->notes,
            'capturedByUserId' => $this->captured_by_user_id !== null ? (string) $this->captured_by_user_id : null,
            'capturedAt' => $this->captured_at?->toISOString(),
            'purchaseOrder' => [
                'id' => (string) $this->purchase_order_id,
                'number' => $this->purchaseOrder?->number,
            ],
            'vendor' => [
                'id' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
                'name' => $this->vendor?->name,
            ],
            'attachmentCount' => $this->relationLoaded('attachments')
                ? $this->attachments->count()
                : (int) ($this->attachments_count ?? $this->attachments()->count()),
            'reviewStartedByUserId' => $this->review_started_by_user_id !== null ? (string) $this->review_started_by_user_id : null,
            'reviewStartedAt' => $this->review_started_at?->toISOString(),
            'reviewedByUserId' => $this->reviewed_by_user_id !== null ? (string) $this->reviewed_by_user_id : null,
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewNotes' => $this->review_notes,
            'reviewChecklist' => $this->review_checklist,
            'reviewChecklistSummary' => SupplierInvoiceReviewChecklistData::summary($this->review_checklist),
            'matchingStatus' => $this->matching_status,
            'matchSummary' => $this->match_summary,
            'exceptionSummary' => $this->exception_summary,
            'reviewBlockers' => $this->review_blockers ?? [],
            'reviewBlockerCount' => count($this->review_blockers ?? []),
            'lines' => $this->relationLoaded('lines')
                ? SupplierInvoiceLineResource::collection($this->lines)->resolve()
                : [],
            'creditApplications' => $this->relationLoaded('creditApplications')
                ? CreditApplicationResource::collection($this->creditApplications)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
            'approvalInstanceId' => $this->approval_instance_id !== null ? (string) $this->approval_instance_id : null,
            'approvalSubmittedByUserId' => $this->approval_submitted_by_user_id !== null ? (string) $this->approval_submitted_by_user_id : null,
            'approvalSubmittedAt' => $this->approval_submitted_at?->toISOString(),
            'approvedByUserId' => $this->approved_by_user_id !== null ? (string) $this->approved_by_user_id : null,
            'approvedAt' => $this->approved_at?->toISOString(),
            'rejectedByUserId' => $this->rejected_by_user_id !== null ? (string) $this->rejected_by_user_id : null,
            'rejectedAt' => $this->rejected_at?->toISOString(),
            'rejectedReason' => $this->rejected_reason,
            'changesRequestedByUserId' => $this->changes_requested_by_user_id !== null ? (string) $this->changes_requested_by_user_id : null,
            'changesRequestedAt' => $this->changes_requested_at?->toISOString(),
            'changesRequestedReason' => $this->changes_requested_reason,
            'changesRequestedFields' => $this->changes_requested_fields,
            'stpEligible' => $this->stp_eligible,
            'stpProcessedAt' => $this->stp_processed_at?->toISOString(),
            'permissions' => [
                'canReview' => Gate::allows('review', $this->resource),
            ],
        ];
    }
}
```

- [ ] **Step 2: Extend SupplierInvoiceQueueResource with credit memo fields**

Read the current file (`apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php`), then add `paymentStatus`, `outstandingAmount`, `creditAppliedAmount`, and `appliedCreditMemos` to its `toArray()` output. Use the same `CreditApplicationSumCalculator` and `ApPaymentAllocation` aggregations as in `SupplierInvoiceResource`. The queue resource should resolve the per-invoice `appliedCreditMemos` array via `CreditApplicationResource::collection($applications->where('supplier_invoice_id', $this->id))` to avoid N+1.

- [ ] **Step 3: Run invoice tests to confirm no regression**

Run: `cd apps/api && php artisan test --filter=SupplierInvoiceApiTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php
git commit -m "feat(p1-49): extend SupplierInvoice resources with paymentStatus, outstanding, and credit applications"
```

---

## Task 36: Update OpenAPI Spec with 20 Paths and 20 Schemas

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

- [ ] **Step 1: Extend the `SupplierInvoicePaymentStatus` enum**

Find the existing `SupplierInvoicePaymentStatus` schema (around line 27,000 in the OpenAPI JSON) and replace its `enum` array with:

```json
"enum": [
  "payment_eligible",
  "on_hold",
  "payment_ready",
  "handoff_exported",
  "payment_scheduled",
  "partially_paid",
  "paid",
  "reversed"
]
```

- [ ] **Step 2: Add 20 new schemas inside `components.schemas`**

Append the following before the closing `}` of `components.schemas`:

```json
,
"SupplierCreditMemoStatus": {
  "type": "string",
  "enum": ["draft", "pending_approval", "approved", "open", "partially_applied", "fully_applied", "closed", "voided"]
},
"SupplierCreditMemoExceptionType": {
  "type": "string",
  "enum": ["missing_invoice_reference", "over_credit", "vendor_mismatch", "tax_code_mismatch", "math_error", "duplicate_credit", "missing_tax_code", "currency_mismatch"]
},
"SupplierCreditMemoExceptionSeverity": {
  "type": "string",
  "enum": ["blocking", "warning", "info"]
},
"SupplierCreditMemoExceptionResolutionType": {
  "type": "string",
  "enum": ["accepted", "value_adjustment", "vendor_reassignment", "voided", "info_only"]
},
"CreateSupplierCreditMemoRequest": {
  "type": "object",
  "required": ["vendorId", "vendorCreditMemoNumber", "creditDate", "subtotalAmount", "taxAmount", "freightAmount", "totalAmount", "lines"],
  "properties": {
    "vendorId": { "type": "integer" },
    "originalInvoiceId": { "type": "string" },
    "vendorCreditMemoNumber": { "type": "string", "maxLength": 255 },
    "creditDate": { "type": "string", "format": "date" },
    "currency": { "type": "string", "minLength": 3, "maxLength": 3 },
    "subtotalAmount": { "type": "string" },
    "taxAmount": { "type": "string" },
    "freightAmount": { "type": "string" },
    "totalAmount": { "type": "string" },
    "notes": { "type": "string" },
    "lines": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/SupplierCreditMemoLineInput" }
    }
  }
},
"SupplierCreditMemoLineInput": {
  "type": "object",
  "required": ["lineNumber", "description", "quantity", "unitPrice"],
  "properties": {
    "lineNumber": { "type": "integer" },
    "description": { "type": "string" },
    "quantity": { "type": "string" },
    "unitPrice": { "type": "string" },
    "taxCode": { "type": "string" },
    "taxAmount": { "type": "string" },
    "purchaseOrderLineId": { "type": "string" },
    "originalInvoiceLineId": { "type": "string" },
    "notes": { "type": "string" }
  }
},
"UpdateSupplierCreditMemoRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "notes": { "type": "string" },
    "creditDate": { "type": "string", "format": "date" },
    "vendorCreditMemoNumber": { "type": "string" },
    "freightAmount": { "type": "string" }
  }
},
"SubmitSupplierCreditMemoForApprovalRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": { "lockVersion": { "type": "integer" } }
},
"PostSupplierCreditMemoRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": { "lockVersion": { "type": "integer" } }
},
"VoidSupplierCreditMemoRequest": {
  "type": "object",
  "required": ["lockVersion", "voidReason"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "voidReason": { "type": "string", "minLength": 5 }
  }
},
"AddSupplierCreditMemoLineRequest": {
  "type": "object",
  "required": ["lockVersion", "lineNumber", "description", "quantity", "unitPrice"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "lineNumber": { "type": "integer" },
    "description": { "type": "string" },
    "quantity": { "type": "string" },
    "unitPrice": { "type": "string" },
    "taxCode": { "type": "string" },
    "taxAmount": { "type": "string" },
    "purchaseOrderLineId": { "type": "string" },
    "originalInvoiceLineId": { "type": "string" },
    "notes": { "type": "string" }
  }
},
"UpdateSupplierCreditMemoLineRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "description": { "type": "string" },
    "quantity": { "type": "string" },
    "unitPrice": { "type": "string" },
    "taxCode": { "type": "string" },
    "taxAmount": { "type": "string" },
    "notes": { "type": "string" }
  }
},
"CreateCreditApplicationRequest": {
  "type": "object",
  "required": ["lockVersion", "supplierInvoiceId", "appliedAmount", "applicationDate"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "supplierInvoiceId": { "type": "string" },
    "appliedAmount": { "type": "string" },
    "applicationDate": { "type": "string", "format": "date" },
    "notes": { "type": "string" }
  }
},
"VoidCreditApplicationRequest": {
  "type": "object",
  "required": ["lockVersion", "voidReason"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "voidReason": { "type": "string", "minLength": 5 }
  }
},
"AcknowledgeSupplierCreditMemoExceptionRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": { "lockVersion": { "type": "integer" } }
},
"ResolveSupplierCreditMemoExceptionRequest": {
  "type": "object",
  "required": ["lockVersion", "resolutionType", "resolutionNotes"],
  "properties": {
    "lockVersion": { "type": "integer" },
    "resolutionType": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionResolutionType" },
    "resolutionNotes": { "type": "string", "minLength": 5 }
  }
},
"SupplierCreditMemoResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/SupplierCreditMemo" } }
},
"SupplierCreditMemo": {
  "type": "object",
  "required": ["id", "tenantId", "number", "vendorId", "status", "currency", "totalAmount", "lockVersion"],
  "properties": {
    "id": { "type": "string" },
    "tenantId": { "type": "string" },
    "number": { "type": "string" },
    "vendorCreditMemoNumber": { "type": "string" },
    "vendorId": { "type": "string" },
    "vendorName": { "type": "string" },
    "originalInvoiceId": { "type": "string" },
    "originalInvoiceNumber": { "type": "string" },
    "status": { "$ref": "#/components/schemas/SupplierCreditMemoStatus" },
    "currency": { "type": "string" },
    "subtotalAmount": { "type": "string" },
    "taxAmount": { "type": "string" },
    "freightAmount": { "type": "string" },
    "totalAmount": { "type": "string" },
    "appliedAmount": { "type": "string" },
    "remainingAmount": { "type": "string" },
    "creditDate": { "type": "string", "format": "date" },
    "notes": { "type": "string" },
    "lockVersion": { "type": "integer" },
    "lines": { "type": "array", "items": { "$ref": "#/components/schemas/SupplierCreditMemoLine" } },
    "applications": { "type": "array", "items": { "$ref": "#/components/schemas/CreditApplication" } },
    "exceptions": { "type": "array", "items": { "$ref": "#/components/schemas/SupplierCreditMemoException" } },
    "permissions": { "$ref": "#/components/schemas/SupplierCreditMemoPermissions" }
  }
},
"SupplierCreditMemoLine": {
  "type": "object",
  "required": ["id", "supplierCreditMemoId", "lineNumber", "description", "quantity", "unitPrice", "lineSubtotal"],
  "properties": {
    "id": { "type": "string" },
    "supplierCreditMemoId": { "type": "string" },
    "lineNumber": { "type": "integer" },
    "description": { "type": "string" },
    "quantity": { "type": "string" },
    "unitPrice": { "type": "string" },
    "lineSubtotal": { "type": "string" },
    "taxCode": { "type": "string" },
    "taxAmount": { "type": "string" },
    "purchaseOrderLineId": { "type": "string" },
    "originalInvoiceLineId": { "type": "string" },
    "notes": { "type": "string" }
  }
},
"SupplierCreditMemoLineResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/SupplierCreditMemoLine" } }
},
"CreditApplication": {
  "type": "object",
  "required": ["id", "supplierCreditMemoId", "supplierInvoiceId", "appliedAmount", "applicationDate", "appliedByUserId", "lockVersion"],
  "properties": {
    "id": { "type": "string" },
    "supplierCreditMemoId": { "type": "string" },
    "supplierCreditMemoNumber": { "type": "string" },
    "supplierInvoiceId": { "type": "string" },
    "supplierInvoiceNumber": { "type": "string" },
    "appliedAmount": { "type": "string" },
    "applicationDate": { "type": "string", "format": "date" },
    "appliedByUserId": { "type": "string" },
    "notes": { "type": "string" },
    "voidedAt": { "type": "string", "format": "date-time" },
    "voidedByUserId": { "type": "string" },
    "voidReason": { "type": "string" },
    "lockVersion": { "type": "integer" }
  }
},
"CreditApplicationResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/CreditApplication" } }
},
"SupplierCreditMemoException": {
  "type": "object",
  "required": ["id", "supplierCreditMemoId", "exceptionType", "severity", "description", "lockVersion"],
  "properties": {
    "id": { "type": "string" },
    "supplierCreditMemoId": { "type": "string" },
    "exceptionType": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionType" },
    "severity": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionSeverity" },
    "description": { "type": "string" },
    "resolutionType": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionResolutionType" },
    "resolutionNotes": { "type": "string" },
    "resolvedAt": { "type": "string", "format": "date-time" },
    "acknowledgedAt": { "type": "string", "format": "date-time" },
    "escalatedAt": { "type": "string", "format": "date-time" },
    "expectedValue": { "type": "string" },
    "adjustedValue": { "type": "string" },
    "lockVersion": { "type": "integer" }
  }
},
"SupplierCreditMemoExceptionResponse": {
  "type": "object",
  "required": ["data"],
  "properties": { "data": { "$ref": "#/components/schemas/SupplierCreditMemoException" } }
},
"SupplierCreditMemoPermissions": {
  "type": "object",
  "properties": {
    "canEdit": { "type": "boolean" },
    "canSubmit": { "type": "boolean" },
    "canApprove": { "type": "boolean" },
    "canReject": { "type": "boolean" },
    "canRequestChanges": { "type": "boolean" },
    "canPost": { "type": "boolean" },
    "canApply": { "type": "boolean" },
    "canVoidApplication": { "type": "boolean" },
    "canVoidCreditMemo": { "type": "boolean" },
    "canResolveException": { "type": "boolean" }
  }
},
"SupplierCreditMemoListResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "type": "array", "items": { "$ref": "#/components/schemas/SupplierCreditMemo" } },
    "meta": {
      "type": "object",
      "properties": {
        "total": { "type": "integer" },
        "perPage": { "type": "integer" },
        "currentPage": { "type": "integer" }
      }
    }
  }
}
```

- [ ] **Step 3: Add 20 path entries**

Append the following inside `components.paths` (after the existing `accounts-payable/payment-imports/{import}/discard` block):

```json
,
"/api/supplier-credit-memos": {
  "get": {
    "operationId": "listSupplierCreditMemos",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "query", "name": "status", "schema": { "$ref": "#/components/schemas/SupplierCreditMemoStatus" } },
      { "in": "query", "name": "vendorId", "schema": { "type": "string" } },
      { "in": "query", "name": "perPage", "schema": { "type": "integer" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoListResponse" } } } }
    }
  },
  "post": {
    "operationId": "createSupplierCreditMemo",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [{ "$ref": "#/components/parameters/TenantHeader" }],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CreateSupplierCreditMemoRequest" } } }
    },
    "responses": {
      "201": { "description": "Created", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } },
      "422": { "description": "Validation error" }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}": {
  "get": {
    "operationId": "showSupplierCreditMemo",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } }
    }
  },
  "patch": {
    "operationId": "updateSupplierCreditMemo",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/UpdateSupplierCreditMemoRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } },
      "409": { "description": "Stale lock version or non-draft state" }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/submit": {
  "post": {
    "operationId": "submitSupplierCreditMemoForApproval",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SubmitSupplierCreditMemoForApprovalRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } },
      "409": { "description": "Not in draft state" }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/post": {
  "post": {
    "operationId": "postSupplierCreditMemo",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/PostSupplierCreditMemoRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/void": {
  "post": {
    "operationId": "voidSupplierCreditMemo",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/VoidSupplierCreditMemoRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/lines": {
  "post": {
    "operationId": "addSupplierCreditMemoLine",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/AddSupplierCreditMemoLineRequest" } } }
    },
    "responses": {
      "201": { "description": "Created", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoLineResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/lines/{line}": {
  "patch": {
    "operationId": "updateSupplierCreditMemoLine",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } },
      { "in": "path", "name": "line", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/UpdateSupplierCreditMemoLineRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoLineResponse" } } } }
    }
  },
  "delete": {
    "operationId": "removeSupplierCreditMemoLine",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } },
      { "in": "path", "name": "line", "required": true, "schema": { "type": "string" } }
    ],
    "responses": { "204": { "description": "No content" } }
  }
},
"/api/supplier-credit-memos/{creditMemo}/applications": {
  "get": {
    "operationId": "listCreditApplications",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "type": "object", "properties": { "data": { "type": "array", "items": { "$ref": "#/components/schemas/CreditApplication" } } } } } } }
    }
  },
  "post": {
    "operationId": "createCreditApplication",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CreateCreditApplicationRequest" } } }
    },
    "responses": {
      "201": { "description": "Created", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CreditApplicationResponse" } } } },
      "409": { "description": "Invalid invoice state" },
      "422": { "description": "Over-application" }
    }
  }
},
"/api/credit-applications/{application}": {
  "get": {
    "operationId": "showCreditApplication",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "application", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CreditApplicationResponse" } } } }
    }
  },
  "delete": {
    "operationId": "voidCreditApplication",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "application", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/VoidCreditApplicationRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/CreditApplicationResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/exceptions": {
  "get": {
    "operationId": "listSupplierCreditMemoExceptions",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "type": "object", "properties": { "data": { "type": "array", "items": { "$ref": "#/components/schemas/SupplierCreditMemoException" } } } } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/acknowledge": {
  "post": {
    "operationId": "acknowledgeSupplierCreditMemoException",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } },
      { "in": "path", "name": "exception", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/AcknowledgeSupplierCreditMemoExceptionRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/resolve": {
  "post": {
    "operationId": "resolveSupplierCreditMemoException",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } },
      { "in": "path", "name": "exception", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ResolveSupplierCreditMemoExceptionRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionResponse" } } } }
    }
  }
},
"/api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/escalate": {
  "post": {
    "operationId": "escalateSupplierCreditMemoException",
    "tags": ["accounts-payable"],
    "security": [{ "sanctum": [] }],
    "parameters": [
      { "$ref": "#/components/parameters/TenantHeader" },
      { "in": "path", "name": "creditMemo", "required": true, "schema": { "type": "string" } },
      { "in": "path", "name": "exception", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/AcknowledgeSupplierCreditMemoExceptionRequest" } } }
    },
    "responses": {
      "200": { "description": "OK", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierCreditMemoExceptionResponse" } } } }
    }
  }
}
```

- [ ] **Step 4: Validate the OpenAPI spec is well-formed JSON**

Run:
```bash
python3 -m json.tool /home/leonidas/dev/cognify/apps/api/storage/openapi/openapi.json > /dev/null && echo OK
```

Expected: prints `OK`.

- [ ] **Step 5: Regenerate the API client and verify the contract**

Run:
```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: regeneration succeeds and contract check is clean.

- [ ] **Step 6: Commit**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client
git commit -m "feat(p1-49): extend OpenAPI with 20 new paths, 20 new schemas, and reversed payment status"
```

---

## Task 37: Extend DemoProcurementLifecycleSeeder with 8 Credit Memo Scenarios

**Files:**
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

- [ ] **Step 1: Read the seeder and find the right insertion point**

Run: `grep -n "seedPaymentStatuses\|public function run\|private function seedExportedHandoff" apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php | head -10`

- [ ] **Step 2: Add `seedCreditMemos` method and its helpers**

Append the following method to the `DemoProcurementLifecycleSeeder` class (after the existing `seedPaymentStatuses` helpers, before the final closing brace):

```php
    public function seedCreditMemos(Tenant $tenant, User $finance): void
    {
        $this->seedDraftCreditMemo($tenant, $finance, 'CM-DEMO-001', 'VCM-DEMO-001', 'INV-2026-DEMO-031', 'paid');
        $this->seedPendingApprovalCreditMemo($tenant, $finance, 'CM-DEMO-002', 'VCM-DEMO-002', 'INV-2026-DEMO-032', 'payment_eligible');
        $this->seedOpenCreditMemo($tenant, $finance, 'CM-DEMO-003', 'VCM-DEMO-003', 'INV-2026-DEMO-033', 'payment_ready');
        $this->seedPartiallyAppliedCreditMemo($tenant, $finance, 'CM-DEMO-004', 'VCM-DEMO-004', 'INV-2026-DEMO-034', 'partially_paid', '600.0000');
        $this->seedFullyAppliedCreditMemo($tenant, $finance, 'CM-DEMO-005', 'VCM-DEMO-005', 'INV-2026-DEMO-035', 'paid', '200.0000');
        $this->seedVoidedCreditMemo($tenant, $finance, 'CM-DEMO-006', 'VCM-DEMO-006', 'INV-2026-DEMO-036', 'paid');
        $this->seedPartiallyAppliedCreditMemo($tenant, $finance, 'CM-DEMO-007', 'VCM-DEMO-007', 'INV-2026-DEMO-037', 'partially_paid', '200.0000');
        $this->seedReversedInvoiceCreditMemo($tenant, $finance, 'CM-DEMO-008', 'VCM-DEMO-008', 'INV-2026-DEMO-038', '1000.0000');
    }

    private function seedDraftCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::Draft, '500.0000');
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Widget A return', '5.0000', '100.0000', '0.0000', 'TX_STD');
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.created');
    }

    private function seedPendingApprovalCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::PendingApproval, '500.0000');
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Pricing dispute line', '5.0000', '100.0000', '0.0000', 'TX_ZERO');
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.submitted_for_approval');
    }

    private function seedOpenCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::Open, '300.0000');
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Return line A', '3.0000', '50.0000', '0.0000', 'TX_STD');
        $this->addCreditMemoLine($tenant, $creditMemo, 2, 'Return line B', '3.0000', '50.0000', '0.0000', 'TX_STD');
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.posted');
    }

    private function seedPartiallyAppliedCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus, string $appliedAmount): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::PartiallyApplied, $appliedAmount);
        $total = '1000.0000';
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Bulk return', '10.0000', '100.0000', '0.0000', 'TX_STD');
        $invoice = \Domains\Invoice\Models\SupplierInvoice::query()->where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->first();
        if ($invoice !== null) {
            \Domains\CreditMemo\Models\CreditApplication::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'supplier_credit_memo_id' => $creditMemo->id, 'supplier_invoice_id' => $invoice->id, 'application_date' => '2026-06-21'],
                ['applied_amount' => $appliedAmount, 'applied_by_user_id' => $finance->id, 'lock_version' => 1]
            );
        }
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.applied');
    }

    private function seedFullyAppliedCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus, string $appliedAmount): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::Closed, $appliedAmount);
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Freight credit', '2.0000', '100.0000', '0.0000', 'TX_STD');
        $invoice = \Domains\Invoice\Models\SupplierInvoice::query()->where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->first();
        if ($invoice !== null) {
            \Domains\CreditMemo\Models\CreditApplication::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'supplier_credit_memo_id' => $creditMemo->id, 'supplier_invoice_id' => $invoice->id, 'application_date' => '2026-06-22'],
                ['applied_amount' => $appliedAmount, 'applied_by_user_id' => $finance->id, 'lock_version' => 1]
            );
        }
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.closed');
    }

    private function seedVoidedCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $paymentStatus): void
    {
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, $paymentStatus, \Domains\CreditMemo\States\SupplierCreditMemoStatus::Voided, '400.0000');
        $creditMemo->forceFill([
            'voided_by_user_id' => $finance->id,
            'voided_at' => '2026-06-23 10:00:00',
            'void_reason' => 'Duplicate credit memo; vendor sent a corrected version',
        ])->save();
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Voided return', '4.0000', '100.0000', '0.0000', 'TX_STD');
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.voided');
    }

    private function seedReversedInvoiceCreditMemo(Tenant $tenant, User $finance, string $number, string $vendorNumber, string $invoiceNumber, string $appliedAmount): void
    {
        $invoice = \Domains\Invoice\Models\SupplierInvoice::query()->where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->first();
        $creditMemo = $this->upsertCreditMemo($tenant, $finance, $number, $vendorNumber, $invoiceNumber, 'paid', \Domains\CreditMemo\States\SupplierCreditMemoStatus::Closed, $appliedAmount);
        $this->addCreditMemoLine($tenant, $creditMemo, 1, 'Full invoice offset', '10.0000', '100.0000', '0.0000', 'TX_STD');
        if ($invoice !== null) {
            \Domains\CreditMemo\Models\CreditApplication::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'supplier_credit_memo_id' => $creditMemo->id, 'supplier_invoice_id' => $invoice->id, 'application_date' => '2026-06-24'],
                ['applied_amount' => $appliedAmount, 'applied_by_user_id' => $finance->id, 'lock_version' => 1]
            );
            $invoice->forceFill(['payment_status' => \Domains\AccountsPayable\States\SupplierInvoicePaymentStatus::Reversed, 'lock_version' => $invoice->lock_version + 1])->save();
        }
        $this->recordCreditMemoAudit($tenant, $finance, $creditMemo, 'supplier_credit_memo.closed');
    }

    private function upsertCreditMemo(
        Tenant $tenant,
        User $finance,
        string $number,
        string $vendorNumber,
        string $invoiceNumber,
        string $paymentStatus,
        \Domains\CreditMemo\States\SupplierCreditMemoStatus $status,
        string $totalAmount,
    ): \Domains\CreditMemo\Models\SupplierCreditMemo {
        $invoice = \Domains\Invoice\Models\SupplierInvoice::query()->where('tenant_id', $tenant->id)->where('invoice_number', $invoiceNumber)->first();

        if ($invoice === null) {
            throw new \RuntimeException("Seed invoice {$invoiceNumber} not found; ensure seedInvoices runs before seedCreditMemos.");
        }

        $vendorId = $invoice->vendor_id;

        $creditMemo = \Domains\CreditMemo\Models\SupplierCreditMemo::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => $number],
            [
                'vendor_credit_memo_number' => $vendorNumber,
                'vendor_id' => $vendorId,
                'original_invoice_id' => $invoice->id,
                'status' => $status,
                'currency' => $invoice->currency,
                'subtotal_amount' => $totalAmount,
                'tax_amount' => '0.0000',
                'freight_amount' => '0.0000',
                'total_amount' => $totalAmount,
                'credit_date' => '2026-06-20',
                'captured_by_user_id' => $finance->id,
                'captured_at' => '2026-06-20 09:00:00',
                'lock_version' => 5,
            ]
        );

        if ($status === \Domains\CreditMemo\States\SupplierCreditMemoStatus::Open
            || $status === \Domains\CreditMemo\States\SupplierCreditMemoStatus::PartiallyApplied
            || $status === \Domains\CreditMemo\States\SupplierCreditMemoStatus::Closed) {
            $creditMemo->forceFill([
                'approved_by_user_id' => $finance->id,
                'approved_at' => '2026-06-20 10:00:00',
                'posted_by_user_id' => $finance->id,
                'posted_at' => '2026-06-20 10:30:00',
            ])->save();
        }

        return $creditMemo->fresh();
    }

    private function addCreditMemoLine(Tenant $tenant, \Domains\CreditMemo\Models\SupplierCreditMemo $creditMemo, int $lineNumber, string $description, string $quantity, string $unitPrice, string $taxAmount, string $taxCode): void
    {
        $lineSubtotal = bcmul($quantity, $unitPrice, 4);
        \Domains\CreditMemo\Models\SupplierCreditMemoLine::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'supplier_credit_memo_id' => $creditMemo->id, 'line_number' => $lineNumber],
            [
                'description_snapshot' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => $lineSubtotal,
                'tax_code' => $taxCode,
                'tax_amount' => $taxAmount,
            ]
        );
    }

    private function recordCreditMemoAudit(Tenant $tenant, User $actor, \Domains\CreditMemo\Models\SupplierCreditMemo $creditMemo, string $action): void
    {
        $this->auditRecorder->record(new \App\Audit\AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: $action,
            subject: $creditMemo,
            metadata: ['demo' => true, 'creditMemoNumber' => $creditMemo->number],
            after: $creditMemo->toArray(),
        ));
    }
```

- [ ] **Step 3: Wire `seedCreditMemos` into the `run()` pipeline**

Inside `run()`, after the `seedPaymentStatuses` call (or after the other seed methods), add:

```php
        $this->seedCreditMemos($tenant, $finance);
```

- [ ] **Step 4: Run the seeder and confirm 8 credit memos are created**

Run:
```bash
cd apps/api && php artisan migrate:fresh --seed 2>&1 | tail -10
cd apps/api && php artisan tinker --execute='echo \\Domains\\CreditMemo\\Models\\SupplierCreditMemo::query()->where("number", "like", "CM-DEMO-%")->orderBy("number")->pluck("number", "status");'
```

Expected output is a JSON object with all 8 credit memos and their corresponding statuses (`{"draft":"CM-DEMO-001","pending_approval":"CM-DEMO-002","open":"CM-DEMO-003","partially_applied":"CM-DEMO-004","closed":"CM-DEMO-005","voided":"CM-DEMO-006","partially_applied":"CM-DEMO-007","closed":"CM-DEMO-008"}`).

- [ ] **Step 5: Verify the `reversed` invoice was created**

Run: `cd apps/api && php artisan tinker --execute='echo \\Domains\\Invoice\\Models\\SupplierInvoice::query()->where("payment_status", "reversed")->pluck("invoice_number");'`

Expected: prints `["INV-2026-DEMO-038"]`.

- [ ] **Step 6: Commit**

```bash
git add apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php
git commit -m "feat(p1-49): seed CM-DEMO-001 through 008 covering draft, pending_approval, open, partially_applied, closed, voided, and reversed invoice scenarios"
```

---

## Task 38: Web API Helpers (3 files)

**Files:**
- Create: `apps/web/features/accounts-payable/api/accounts-payable-credit-memo-api.ts`
- Create: `apps/web/features/accounts-payable/api/accounts-payable-credit-application-api.ts`
- Create: `apps/web/features/accounts-payable/api/accounts-payable-credit-memo-exception-api.ts`

- [ ] **Step 1: Create `accounts-payable-credit-memo-api.ts`**

```typescript
import {
  listSupplierCreditMemos,
  createSupplierCreditMemo,
  showSupplierCreditMemo,
  updateSupplierCreditMemo,
  submitSupplierCreditMemoForApproval,
  postSupplierCreditMemo,
  voidSupplierCreditMemo,
  addSupplierCreditMemoLine,
  updateSupplierCreditMemoLine,
  removeSupplierCreditMemoLine,
} from "@cognify/api-client/endpoints";
import type {
  SupplierCreditMemo,
  CreateSupplierCreditMemoRequest,
  UpdateSupplierCreditMemoRequest,
  SubmitSupplierCreditMemoForApprovalRequest,
  PostSupplierCreditMemoRequest,
  VoidSupplierCreditMemoRequest,
  AddSupplierCreditMemoLineRequest,
  UpdateSupplierCreditMemoLineRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

export async function listCreditMemos(
  filters: { status?: string; vendorId?: string; perPage?: number } = {},
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<{ data: SupplierCreditMemo[]; total: number }> {
  const response = await listSupplierCreditMemos(filters, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  const data = unwrapData<SupplierCreditMemo[]>(response);
  return {
    data,
    total: (response.data as { meta?: { total?: number } })?.meta?.total ?? data.length,
  };
}

export async function createCreditMemo(
  payload: CreateSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await createSupplierCreditMemo({ data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response, 201);
}

export async function showCreditMemo(
  id: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await showSupplierCreditMemo(id, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function updateCreditMemo(
  id: string,
  payload: UpdateSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await updateSupplierCreditMemo(id, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function submitCreditMemoForApproval(
  id: string,
  payload: SubmitSupplierCreditMemoForApprovalRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await submitSupplierCreditMemoForApproval(id, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function postCreditMemo(
  id: string,
  payload: PostSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await postSupplierCreditMemo(id, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function voidCreditMemo(
  id: string,
  payload: VoidSupplierCreditMemoRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await voidSupplierCreditMemo(id, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function addCreditMemoLine(
  creditMemoId: string,
  payload: AddSupplierCreditMemoLineRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await addSupplierCreditMemoLine(creditMemoId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response, 201);
}

export async function updateCreditMemoLine(
  creditMemoId: string,
  lineId: string,
  payload: UpdateSupplierCreditMemoLineRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await updateSupplierCreditMemoLine(creditMemoId, lineId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}

export async function removeCreditMemoLine(
  creditMemoId: string,
  lineId: string,
  lockVersion: number,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemo> {
  const response = await removeSupplierCreditMemoLine(creditMemoId, lineId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemo>(response);
}
```

- [ ] **Step 2: Create `accounts-payable-credit-application-api.ts`**

```typescript
import {
  listCreditApplications,
  createCreditApplication,
  showCreditApplication,
  voidCreditApplication,
} from "@cognify/api-client/endpoints";
import type {
  CreditApplication,
  CreateCreditApplicationRequest,
  VoidCreditApplicationRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

export async function listCreditApplicationsForMemo(
  creditMemoId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication[]> {
  const response = await listCreditApplications(creditMemoId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication[]>(response);
}

export async function createCreditApplicationApi(
  creditMemoId: string,
  payload: CreateCreditApplicationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await createCreditApplication(creditMemoId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication>(response, 201);
}

export async function showCreditApplicationApi(
  applicationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await showCreditApplication(applicationId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication>(response);
}

export async function voidCreditApplicationApi(
  applicationId: string,
  payload: VoidCreditApplicationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<CreditApplication> {
  const response = await voidCreditApplication(applicationId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<CreditApplication>(response);
}
```

- [ ] **Step 3: Create `accounts-payable-credit-memo-exception-api.ts`**

```typescript
import {
  listSupplierCreditMemoExceptions,
  acknowledgeSupplierCreditMemoException,
  resolveSupplierCreditMemoException,
  escalateSupplierCreditMemoException,
} from "@cognify/api-client/endpoints";
import type {
  SupplierCreditMemoException,
  AcknowledgeSupplierCreditMemoExceptionRequest,
  ResolveSupplierCreditMemoExceptionRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { withActiveTenantHeader, throwResponseData, unwrapData } from "./api-helpers";

export async function listCreditMemoExceptions(
  creditMemoId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException[]> {
  const response = await listSupplierCreditMemoExceptions(creditMemoId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException[]>(response);
}

export async function acknowledgeCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: AcknowledgeSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await acknowledgeSupplierCreditMemoException(creditMemoId, exceptionId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}

export async function resolveCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: ResolveSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await resolveSupplierCreditMemoException(creditMemoId, exceptionId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}

export async function escalateCreditMemoException(
  creditMemoId: string,
  exceptionId: string,
  payload: AcknowledgeSupplierCreditMemoExceptionRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<SupplierCreditMemoException> {
  const response = await escalateSupplierCreditMemoException(creditMemoId, exceptionId, { data: payload }, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapData<SupplierCreditMemoException>(response);
}
```

- [ ] **Step 4: Commit**

```bash
git add apps/web/features/accounts-payable/api/accounts-payable-credit-memo-api.ts \
        apps/web/features/accounts-payable/api/accounts-payable-credit-application-api.ts \
        apps/web/features/accounts-payable/api/accounts-payable-credit-memo-exception-api.ts
git commit -m "feat(p1-49): add web API helpers for credit memo CRUD, applications, and exceptions"
```

---

## Task 39: Web Hooks (5 files)

**Files:**
- Create: `apps/web/features/accounts-payable/hooks/use-supplier-credit-memos.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-lines.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-credit-applications.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-exceptions.ts`

- [ ] **Step 1: Create `use-supplier-credit-memos.ts`**

```typescript
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  listCreditMemos,
  createCreditMemo,
  updateCreditMemo,
  submitCreditMemoForApproval,
  postCreditMemo,
  voidCreditMemo,
} from "../api/accounts-payable-credit-memo-api";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const supplierCreditMemoKeys = {
  all: ["accounts-payable", "credit-memos"] as const,
  list: (filters: object) => [...supplierCreditMemoKeys.all, "list", filters] as const,
  detail: (id: string) => [...supplierCreditMemoKeys.all, "detail", id] as const,
};

export function useSupplierCreditMemos(filters: { status?: string; vendorId?: string; perPage?: number } = {}) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: supplierCreditMemoKeys.list(filters),
    queryFn: () => listCreditMemos(filters, tenantId),
  });
}

function useInvalidateCreditMemoCaches() {
  const qc = useQueryClient();
  return async () => {
    await qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
    await qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
  };
}

export function useCreateSupplierCreditMemo() {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof createCreditMemo>[0]) => createCreditMemo(payload),
    onSuccess: invalidate,
  });
}

export function useUpdateSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof updateCreditMemo>[1]) => updateCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}

export function useSubmitSupplierCreditMemoForApproval(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof submitCreditMemoForApproval>[1]) => submitCreditMemoForApproval(id, payload),
    onSuccess: invalidate,
  });
}

export function usePostSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof postCreditMemo>[1]) => postCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}

export function useVoidSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: Parameters<typeof voidCreditMemo>[1]) => voidCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}
```

- [ ] **Step 2: Create `use-supplier-credit-memo.ts`**

```typescript
"use client";

import { useQuery } from "@tanstack/react-query";
import { showCreditMemo } from "../api/accounts-payable-credit-memo-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function useSupplierCreditMemo(id: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: supplierCreditMemoKeys.detail(id ?? "missing"),
    queryFn: () => {
      if (!id) throw new Error("creditMemoId required");
      return showCreditMemo(id, tenantId);
    },
    enabled: Boolean(id),
  });
}
```

- [ ] **Step 3: Create `use-supplier-credit-memo-lines.ts`**

```typescript
"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  addCreditMemoLine,
  updateCreditMemoLine,
  removeCreditMemoLine,
} from "../api/accounts-payable-credit-memo-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function useAddSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof addCreditMemoLine>[1]) => addCreditMemoLine(creditMemoId, payload, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}

export function useUpdateSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ lineId, payload }: { lineId: string; payload: Parameters<typeof updateCreditMemoLine>[2] }) =>
      updateCreditMemoLine(creditMemoId, lineId, payload, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}

export function useRemoveSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ lineId, lockVersion }: { lineId: string; lockVersion: number }) =>
      removeCreditMemoLine(creditMemoId, lineId, lockVersion, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}
```

- [ ] **Step 4: Create `use-credit-applications.ts`**

```typescript
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  listCreditApplicationsForMemo,
  createCreditApplicationApi,
  voidCreditApplicationApi,
} from "../api/accounts-payable-credit-application-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const creditApplicationKeys = {
  all: ["accounts-payable", "credit-applications"] as const,
  list: (creditMemoId: string) => [...creditApplicationKeys.all, "list", creditMemoId] as const,
};

export function useCreditApplications(creditMemoId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: creditApplicationKeys.list(creditMemoId ?? "missing"),
    queryFn: () => {
      if (!creditMemoId) throw new Error("creditMemoId required");
      return listCreditApplicationsForMemo(creditMemoId, tenantId);
    },
    enabled: Boolean(creditMemoId),
  });
}

export function useCreateCreditApplication(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: Parameters<typeof createCreditApplicationApi>[1]) =>
      createCreditApplicationApi(creditMemoId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditApplicationKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
      qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
    },
  });
}

export function useVoidCreditApplication(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ applicationId, payload }: { applicationId: string; payload: Parameters<typeof voidCreditApplicationApi>[1] }) =>
      voidCreditApplicationApi(applicationId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditApplicationKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
      qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
    },
  });
}
```

- [ ] **Step 5: Create `use-supplier-credit-memo-exceptions.ts`**

```typescript
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  listCreditMemoExceptions,
  acknowledgeCreditMemoException,
  resolveCreditMemoException,
  escalateCreditMemoException,
} from "../api/accounts-payable-credit-memo-exception-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const creditMemoExceptionKeys = {
  all: ["accounts-payable", "credit-memo-exceptions"] as const,
  list: (creditMemoId: string) => [...creditMemoExceptionKeys.all, "list", creditMemoId] as const,
};

export function useSupplierCreditMemoExceptions(creditMemoId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: creditMemoExceptionKeys.list(creditMemoId ?? "missing"),
    queryFn: () => {
      if (!creditMemoId) throw new Error("creditMemoId required");
      return listCreditMemoExceptions(creditMemoId, tenantId);
    },
    enabled: Boolean(creditMemoId),
  });
}

export function useAcknowledgeCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ exceptionId, payload }: { exceptionId: string; payload: Parameters<typeof acknowledgeCreditMemoException>[2] }) =>
      acknowledgeCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}

export function useResolveCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ exceptionId, payload }: { exceptionId: string; payload: Parameters<typeof resolveCreditMemoException>[2] }) =>
      resolveCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}

export function useEscalateCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ exceptionId, payload }: { exceptionId: string; payload: Parameters<typeof escalateCreditMemoException>[2] }) =>
      escalateCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}
```

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/accounts-payable/hooks/use-supplier-credit-memos.ts \
        apps/web/features/accounts-payable/hooks/use-supplier-credit-memo.ts \
        apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-lines.ts \
        apps/web/features/accounts-payable/hooks/use-credit-applications.ts \
        apps/web/features/accounts-payable/hooks/use-supplier-credit-memo-exceptions.ts
git commit -m "feat(p1-49): add web TanStack Query hooks for credit memo list, detail, lines, applications, exceptions"
```

---

## Task 40: Web MSW Mocks (6 files)

**Files:**
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-handlers.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/tests/setup.ts`

- [ ] **Step 1: Create the credit memo fixtures**

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-fixtures.ts`:

```typescript
import type { SupplierCreditMemo } from "@cognify/api-client/schemas";

const creditMemoSeeds: SupplierCreditMemo[] = [
  {
    id: "cm-1",
    tenantId: "tenant-1",
    number: "CM-2026-000001",
    vendorCreditMemoNumber: "VCM-001",
    vendorId: "vendor-1",
    vendorName: "Acme Supplies",
    originalInvoiceId: "inv-1",
    originalInvoiceNumber: "INV-2026-000042",
    status: "draft",
    currency: "USD",
    subtotalAmount: "1000.00",
    taxAmount: "80.00",
    freightAmount: "0.00",
    totalAmount: "1080.00",
    appliedAmount: "0.00",
    remainingAmount: "1080.00",
    creditDate: "2026-06-15",
    lockVersion: 1,
    lines: [
      {
        id: "cml-1",
        supplierCreditMemoId: "cm-1",
        lineNumber: 1,
        description: "Widget A return",
        quantity: "10.0000",
        unitPrice: "100.0000",
        lineSubtotal: "1000.0000",
        taxCode: "TX_STD",
        taxAmount: "80.0000",
        purchaseOrderLineId: null,
        originalInvoiceLineId: null,
        notes: null,
      },
    ],
    applications: [],
    exceptions: [],
    permissions: {
      canEdit: true,
      canSubmit: true,
      canApprove: false,
      canReject: false,
      canRequestChanges: false,
      canPost: false,
      canApply: false,
      canVoidApplication: false,
      canVoidCreditMemo: true,
      canResolveException: true,
    },
  },
  {
    id: "cm-2",
    tenantId: "tenant-1",
    number: "CM-2026-000002",
    vendorCreditMemoNumber: "VCM-002",
    vendorId: "vendor-1",
    vendorName: "Acme Supplies",
    originalInvoiceId: "inv-2",
    originalInvoiceNumber: "INV-2026-000043",
    status: "open",
    currency: "USD",
    subtotalAmount: "500.00",
    taxAmount: "40.00",
    freightAmount: "0.00",
    totalAmount: "540.00",
    appliedAmount: "0.00",
    remainingAmount: "540.00",
    creditDate: "2026-06-16",
    lockVersion: 1,
    lines: [],
    applications: [],
    exceptions: [],
    permissions: {
      canEdit: false,
      canSubmit: false,
      canApprove: false,
      canReject: false,
      canRequestChanges: false,
      canPost: false,
      canApply: true,
      canVoidApplication: true,
      canVoidCreditMemo: true,
      canResolveException: true,
    },
  },
  {
    id: "cm-3",
    tenantId: "tenant-1",
    number: "CM-2026-000003",
    vendorCreditMemoNumber: "VCM-003",
    vendorId: "vendor-2",
    vendorName: "Beta Logistics",
    originalInvoiceId: "inv-3",
    originalInvoiceNumber: "INV-2026-000044",
    status: "partially_applied",
    currency: "USD",
    subtotalAmount: "1000.00",
    taxAmount: "0.00",
    freightAmount: "0.00",
    totalAmount: "1000.00",
    appliedAmount: "500.00",
    remainingAmount: "500.00",
    creditDate: "2026-06-18",
    lockVersion: 3,
    lines: [],
    applications: [
      {
        id: "ca-1",
        supplierCreditMemoId: "cm-3",
        supplierCreditMemoNumber: "CM-2026-000003",
        supplierInvoiceId: "inv-3",
        supplierInvoiceNumber: "INV-2026-000044",
        appliedAmount: "500.00",
        applicationDate: "2026-06-19",
        appliedByUserId: "user-1",
        notes: null,
        voidedAt: null,
        voidedByUserId: null,
        voidReason: null,
        lockVersion: 1,
      },
    ],
    exceptions: [],
    permissions: {
      canEdit: false,
      canSubmit: false,
      canApprove: false,
      canReject: false,
      canRequestChanges: false,
      canPost: false,
      canApply: true,
      canVoidApplication: true,
      canVoidCreditMemo: true,
      canResolveException: true,
    },
  },
];

let _memos = [...creditMemoSeeds];

export const creditMemoFixtures = {
  all: () => _memos,
  findById: (id: string) => _memos.find((m) => m.id === id) ?? null,
  setMemos: (next: SupplierCreditMemo[]) => {
    _memos = next;
  },
};
```

- [ ] **Step 2: Create the credit memo handlers**

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers.ts`:

```typescript
import { http, HttpResponse } from "msw";
import { creditMemoFixtures } from "./accounts-payable-credit-memo-fixtures";

let _nextNumber = 4;

export function resetCreditMemoMockState() {
  _nextNumber = 4;
  creditMemoFixtures.setMemos(creditMemoFixtures.all().slice(0, 3));
}

export const accountsPayableCreditMemoHandlers = [
  http.get("/api/supplier-credit-memos", ({ request }) => {
    const url = new URL(request.url);
    const status = url.searchParams.get("status");
    const all = creditMemoFixtures.all().filter((m) => (status ? m.status === status : true));
    return HttpResponse.json({ data: all, meta: { total: all.length, perPage: 25, currentPage: 1 } });
  }),

  http.get("/api/supplier-credit-memos/:id", ({ params }) => {
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: memo });
  }),

  http.post("/api/supplier-credit-memos", async ({ request }) => {
    const body = (await request.json()) as { data: { totalAmount: string; vendorId: string } };
    const newMemo = {
      id: `cm-${_nextNumber}`,
      tenantId: "tenant-1",
      number: `CM-2026-00000${_nextNumber}`,
      vendorCreditMemoNumber: null,
      vendorId: body.data.vendorId,
      vendorName: "Mock Vendor",
      originalInvoiceId: null,
      originalInvoiceNumber: null,
      status: "draft",
      currency: "USD",
      subtotalAmount: body.data.totalAmount,
      taxAmount: "0.00",
      freightAmount: "0.00",
      totalAmount: body.data.totalAmount,
      appliedAmount: "0.00",
      remainingAmount: body.data.totalAmount,
      creditDate: new Date().toISOString().split("T")[0],
      lockVersion: 1,
      lines: [],
      applications: [],
      exceptions: [],
      permissions: {
        canEdit: true,
        canSubmit: true,
        canApprove: false,
        canReject: false,
        canRequestChanges: false,
        canPost: false,
        canApply: false,
        canVoidApplication: false,
        canVoidCreditMemo: true,
        canResolveException: true,
      },
    };
    _nextNumber += 1;
    creditMemoFixtures.setMemos([...creditMemoFixtures.all(), newMemo]);
    return HttpResponse.json({ data: newMemo }, { status: 201 });
  }),

  http.patch("/api/supplier-credit-memos/:id", async ({ params, request }) => {
    const body = (await request.json()) as { data: { lockVersion: number } };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.lockVersion !== body.data.lockVersion) {
      return new HttpResponse(JSON.stringify({ message: "Stale lock version" }), { status: 409 });
    }
    return HttpResponse.json({ data: { ...memo, lockVersion: memo.lockVersion + 1 } });
  }),

  http.post("/api/supplier-credit-memos/:id/submit", async ({ params, request }) => {
    const body = (await request.json()) as { data: { lockVersion: number } };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status !== "draft") {
      return new HttpResponse(JSON.stringify({ message: "Not in draft state" }), { status: 409 });
    }
    if (memo.lockVersion !== body.data.lockVersion) {
      return new HttpResponse(JSON.stringify({ message: "Stale lock version" }), { status: 409 });
    }
    return HttpResponse.json({ data: { ...memo, status: "pending_approval", lockVersion: memo.lockVersion + 1 } });
  }),

  http.post("/api/supplier-credit-memos/:id/post", async ({ params, request }) => {
    const body = (await request.json()) as { data: { lockVersion: number } };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status !== "approved") {
      return new HttpResponse(JSON.stringify({ message: "Not in approved state" }), { status: 409 });
    }
    return HttpResponse.json({ data: { ...memo, status: "open", lockVersion: memo.lockVersion + 1 } });
  }),

  http.post("/api/supplier-credit-memos/:id/void", async ({ params, request }) => {
    const body = (await request.json()) as { data: { lockVersion: number; voidReason: string } };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status === "closed" || memo.status === "voided") {
      return new HttpResponse(JSON.stringify({ message: "Not voidable" }), { status: 409 });
    }
    return HttpResponse.json({ data: { ...memo, status: "voided", lockVersion: memo.lockVersion + 1 } });
  }),
];
```

- [ ] **Step 3: Create the credit application fixtures and handlers**

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-fixtures.ts`:

```typescript
import type { CreditApplication } from "@cognify/api-client/schemas";

const _applications: CreditApplication[] = [
  {
    id: "ca-1",
    supplierCreditMemoId: "cm-3",
    supplierCreditMemoNumber: "CM-2026-000003",
    supplierInvoiceId: "inv-3",
    supplierInvoiceNumber: "INV-2026-000044",
    appliedAmount: "500.00",
    applicationDate: "2026-06-19",
    appliedByUserId: "user-1",
    notes: "First application",
    voidedAt: null,
    voidedByUserId: null,
    voidReason: null,
    lockVersion: 1,
  },
];

export const creditApplicationFixtures = {
  all: () => _applications,
  setApplications: (next: CreditApplication[]) => {
    _applications.length = 0;
    _applications.push(...next);
  },
  addApplication: (application: CreditApplication) => {
    _applications.push(application);
  },
};
```

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-handlers.ts`:

```typescript
import { http, HttpResponse } from "msw";
import { creditApplicationFixtures } from "./accounts-payable-credit-application-fixtures";

export function resetCreditApplicationMockState() {
  creditApplicationFixtures.setApplications([]);
}

export const accountsPayableCreditApplicationHandlers = [
  http.get("/api/supplier-credit-memos/:creditMemo/applications", () => {
    return HttpResponse.json({ data: creditApplicationFixtures.all() });
  }),

  http.get("/api/credit-applications/:id", ({ params }) => {
    const application = creditApplicationFixtures.all().find((a) => a.id === params.id);
    if (!application) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: application });
  }),

  http.post("/api/supplier-credit-memos/:creditMemo/applications", async ({ request }) => {
    const body = (await request.json()) as { data: { appliedAmount: string; supplierInvoiceId: string; applicationDate: string } };
    const newApplication = {
      id: `ca-${Date.now()}`,
      supplierCreditMemoId: "cm-3",
      supplierCreditMemoNumber: "CM-2026-000003",
      supplierInvoiceId: body.data.supplierInvoiceId,
      supplierInvoiceNumber: "INV-2026-000044",
      appliedAmount: body.data.appliedAmount,
      applicationDate: body.data.applicationDate,
      appliedByUserId: "user-1",
      notes: null,
      voidedAt: null,
      voidedByUserId: null,
      voidReason: null,
      lockVersion: 1,
    };
    creditApplicationFixtures.addApplication(newApplication);
    return HttpResponse.json({ data: newApplication }, { status: 201 });
  }),

  http.delete("/api/credit-applications/:id", async ({ params, request }) => {
    const body = (await request.json().catch(() => ({}))) as { data: { lockVersion: number; voidReason: string } };
    const application = creditApplicationFixtures.all().find((a) => a.id === params.id);
    if (!application) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: { ...application, voidedAt: new Date().toISOString(), voidReason: body.data?.voidReason ?? "Test void" } });
  }),
];
```

- [ ] **Step 4: Create the exception fixtures and handlers**

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-fixtures.ts`:

```typescript
import type { SupplierCreditMemoException } from "@cognify/api-client/schemas";

const _exceptions: SupplierCreditMemoException[] = [];

export const creditMemoExceptionFixtures = {
  all: () => _exceptions,
  setExceptions: (next: SupplierCreditMemoException[]) => {
    _exceptions.length = 0;
    _exceptions.push(...next);
  },
};
```

`apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers.ts`:

```typescript
import { http, HttpResponse } from "msw";
import { creditMemoExceptionFixtures } from "./accounts-payable-credit-memo-exception-fixtures";

export function resetCreditMemoExceptionMockState() {
  creditMemoExceptionFixtures.setExceptions([]);
}

export const accountsPayableCreditMemoExceptionHandlers = [
  http.get("/api/supplier-credit-memos/:creditMemo/exceptions", () => {
    return HttpResponse.json({ data: creditMemoExceptionFixtures.all() });
  }),

  http.post("/api/supplier-credit-memos/:creditMemo/exceptions/:exception/acknowledge", async ({ params }) => {
    const exception = creditMemoExceptionFixtures.all().find((e) => e.id === params.exception);
    if (!exception) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: { ...exception, acknowledgedAt: new Date().toISOString() } });
  }),

  http.post("/api/supplier-credit-memos/:creditMemo/exceptions/:exception/resolve", async ({ params }) => {
    const exception = creditMemoExceptionFixtures.all().find((e) => e.id === params.exception);
    if (!exception) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: { ...exception, resolvedAt: new Date().toISOString() } });
  }),

  http.post("/api/supplier-credit-memos/:creditMemo/exceptions/:exception/escalate", async ({ params }) => {
    const exception = creditMemoExceptionFixtures.all().find((e) => e.id === params.exception);
    if (!exception) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: { ...exception, escalatedAt: new Date().toISOString() } });
  }),
];
```

- [ ] **Step 5: Register the new handlers in `tests/msw/handlers.ts`**

Add to the imports:

```typescript
import { accountsPayableCreditMemoHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers";
import { accountsPayableCreditApplicationHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-application-handlers";
import { accountsPayableCreditMemoExceptionHandlers } from "@/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers";
```

Add to the `handlers` array (alongside the existing AP handlers):

```typescript
  ...accountsPayableCreditMemoHandlers,
  ...accountsPayableCreditApplicationHandlers,
  ...accountsPayableCreditMemoExceptionHandlers,
```

- [ ] **Step 6: Register the new reset functions in `tests/setup.ts`**

Add to the imports:

```typescript
import { resetCreditMemoMockState } from "../features/accounts-payable/mocks/accounts-payable-credit-memo-handlers";
import { resetCreditApplicationMockState } from "../features/accounts-payable/mocks/accounts-payable-credit-application-handlers";
import { resetCreditMemoExceptionMockState } from "../features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers";
```

In the `afterEach` reset block (next to the existing `resetAccountsPayablePaymentMockState()`):

```typescript
  resetCreditMemoMockState();
  resetCreditApplicationMockState();
  resetCreditMemoExceptionMockState();
```

- [ ] **Step 7: Commit**

```bash
git add apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-handlers.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-credit-application-handlers.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-fixtures.ts \
        apps/web/features/accounts-payable/mocks/accounts-payable-credit-memo-exception-handlers.ts \
        apps/web/tests/msw/handlers.ts \
        apps/web/tests/setup.ts
git commit -m "feat(p1-49): add MSW fixtures and handlers for credit memo CRUD, applications, and exceptions"
```

---

## Task 41: Web Pages, Components, and Navigation

**Files:**
- Create: 3 page files, 11 component files, 3 workflow files
- Modify: `apps/web/components/default-shell/navigation.tsx`
- Modify: `apps/web/features/accounts-payable/components/payment-status-badge.tsx`

- [ ] **Step 1: Create the credit memo status badge component**

`apps/web/features/accounts-payable/components/credit-memo-status-badge.tsx`:

```typescript
import { Badge } from "@cognify/ui";
import type { SupplierCreditMemoStatus } from "@cognify/api-client/schemas";

const statusStyles: Record<SupplierCreditMemoStatus, string> = {
  draft: "bg-slate-100 text-slate-800",
  pending_approval: "bg-amber-100 text-amber-800",
  approved: "bg-indigo-100 text-indigo-800",
  open: "bg-emerald-100 text-emerald-800",
  partially_applied: "bg-cyan-100 text-cyan-800",
  fully_applied: "bg-blue-100 text-blue-800",
  closed: "bg-gray-200 text-gray-700",
  voided: "bg-rose-100 text-rose-800",
};

const statusLabels: Record<SupplierCreditMemoStatus, string> = {
  draft: "Draft",
  pending_approval: "Pending approval",
  approved: "Approved",
  open: "Open",
  partially_applied: "Partially applied",
  fully_applied: "Fully applied",
  closed: "Closed",
  voided: "Voided",
};

export function CreditMemoStatusBadge({ status }: { status: SupplierCreditMemoStatus }) {
  return <Badge className={statusStyles[status] ?? "bg-gray-100"}>{statusLabels[status] ?? status}</Badge>;
}
```

- [ ] **Step 2: Create the math preview component**

`apps/web/features/accounts-payable/components/credit-memo-math-preview.tsx`:

```typescript
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import type { SupplierCreditMemoLine } from "@cognify/api-client/schemas";

export function CreditMemoMathPreview({ lines }: { lines: SupplierCreditMemoLine[] }) {
  const subtotalSum = lines.reduce(
    (acc, line) => acc + Number(line.lineSubtotal),
    0,
  );
  const taxSum = lines.reduce((acc, line) => acc + Number(line.taxAmount ?? 0), 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Math preview</CardTitle>
      </CardHeader>
      <CardContent className="text-sm space-y-1">
        <div className="flex justify-between">
          <span>Lines subtotal</span>
          <span>{subtotalSum.toFixed(4)}</span>
        </div>
        <div className="flex justify-between">
          <span>Lines tax</span>
          <span>{taxSum.toFixed(4)}</span>
        </div>
        <div className="flex justify-between font-semibold">
          <span>Lines total</span>
          <span>{(subtotalSum + taxSum).toFixed(4)}</span>
        </div>
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 3: Create the remaining 9 component files (one-file stubs following existing pattern)**

The 9 remaining components follow the same single-file shadcn form/dialog pattern. Each is a thin wrapper over a hook from `use-supplier-credit-memos`, `use-supplier-credit-memo-lines`, `use-credit-applications`, or `use-supplier-credit-memo-exceptions`. Each is under 200 lines.

Create these files following the pattern of the existing `payment-import-upload-panel.tsx` and `payment-import-reconciliation-summary.tsx` files in `apps/web/features/accounts-payable/components/`:

- `apps/web/features/accounts-payable/components/credit-memo-create-panel.tsx` — wraps `useCreateSupplierCreditMemo` + `useAddSupplierCreditMemoLine`. Renders a form with vendor picker, original invoice picker, line editor rows, and math preview.
- `apps/web/features/accounts-payable/components/credit-memo-line-editor.tsx` — wraps `useAddSupplierCreditMemoLine`, `useUpdateSupplierCreditMemoLine`, `useRemoveSupplierCreditMemoLine`. Renders a line list with edit/remove controls.
- `apps/web/features/accounts-payable/components/credit-memo-application-panel.tsx` — wraps `useCreditApplications`, `useCreateCreditApplication`, `useVoidCreditApplication`. Renders the application list, the apply form, and void buttons.
- `apps/web/features/accounts-payable/components/credit-memo-exception-panel.tsx` — wraps `useSupplierCreditMemoExceptions`, `useAcknowledgeCreditMemoException`, `useResolveCreditMemoException`, `useEscalateCreditMemoException`. Renders the exception list with acknowledge/resolve/escalate actions.
- `apps/web/features/accounts-payable/components/credit-memo-approval-panel.tsx` — read-only panel showing approval task summary, using `creditMemo.approvalInstanceId` for navigation.
- `apps/web/features/accounts-payable/components/credit-memo-attachment-panel.tsx` — uses the existing `Attachment` morph (no new wiring needed; just delegates to the generic attachment component).
- `apps/web/features/accounts-payable/components/credit-memo-activity-timeline.tsx` — renders audit events fetched via the existing audit API.
- `apps/web/features/accounts-payable/components/credit-memo-void-panel.tsx` — wraps `useVoidSupplierCreditMemo`. Renders a void form with reason field.
- `apps/web/features/accounts-payable/components/credit-memo-submit-button.tsx` — wraps `useSubmitSupplierCreditMemoForApproval` and shows a submit button gated by the credit memo's `permissions.canSubmit`.

- [ ] **Step 4: Create the 3 workflow page components**

`apps/web/features/accounts-payable/workflows/credit-memo-queue-page.tsx`:

```typescript
"use client";

import { useState } from "react";
import { Badge, Button, Card, CardContent, CardHeader, CardTitle, Skeleton } from "@cognify/ui";
import type { SupplierCreditMemoStatus } from "@cognify/api-client/schemas";
import { useSupplierCreditMemos } from "../hooks/use-supplier-credit-memos";
import { CreditMemoStatusBadge } from "../components/credit-memo-status-badge";

type TabKey = "all" | SupplierCreditMemoStatus;

const tabs: Array<{ key: TabKey; label: string }> = [
  { key: "all", label: "All" },
  { key: "draft", label: "Draft" },
  { key: "pending_approval", label: "Pending approval" },
  { key: "open", label: "Open" },
  { key: "partially_applied", label: "Partially applied" },
  { key: "fully_applied", label: "Fully applied" },
  { key: "closed", label: "Closed" },
  { key: "voided", label: "Voided" },
];

export function CreditMemoQueuePage() {
  const [tab, setTab] = useState<TabKey>("all");
  const filters = tab === "all" ? {} : { status: tab };
  const { data, isLoading, isError, error } = useSupplierCreditMemos(filters);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
      </div>
    );
  }

  if (isError) {
    return <div className="text-destructive">{(error as Error)?.message ?? "Failed to load credit memo queue."}</div>;
  }

  const memos = data?.data ?? [];

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">Credit memos</h1>

      <div className="flex gap-2 border-b overflow-x-auto">
        {tabs.map((t) => (
          <Button key={t.key} variant={tab === t.key ? "default" : "ghost"} onClick={() => setTab(t.key)} className="rounded-b-none">
            {t.label}
          </Button>
        ))}
      </div>

      <div className="space-y-2">
        {memos.length === 0 ? (
          <Card>
            <CardContent className="py-6 text-center text-muted-foreground">No credit memos in this status.</CardContent>
          </Card>
        ) : (
          memos.map((memo) => (
            <Card key={memo.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="text-base">{memo.number}</CardTitle>
                  <CreditMemoStatusBadge status={memo.status} />
                </div>
              </CardHeader>
              <CardContent className="text-sm text-muted-foreground">
                <p>Vendor: {memo.vendorName ?? memo.vendorId}</p>
                <p>Original invoice: {memo.originalInvoiceNumber ?? memo.originalInvoiceId}</p>
                <p>
                  {memo.appliedAmount} / {memo.totalAmount} {memo.currency} applied
                </p>
              </CardContent>
            </Card>
          ))
        )}
      </div>
    </div>
  );
}
```

`apps/web/features/accounts-payable/workflows/credit-memo-create-page.tsx`:

```typescript
"use client";

import { useRouter } from "next/navigation";
import { CreditMemoCreatePanel } from "../components/credit-memo-create-panel";

export function CreditMemoCreatePage() {
  const router = useRouter();
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">New credit memo</h1>
      <CreditMemoCreatePanel
        onSuccess={(id) => router.push(`/accounts-payable/credit-memos/${id}`)}
        onCancel={() => router.push("/accounts-payable/credit-memos")}
      />
    </div>
  );
}
```

`apps/web/features/accounts-payable/workflows/credit-memo-detail-workspace.tsx`:

```typescript
"use client";

import { useParams } from "next/navigation";
import { useSupplierCreditMemo } from "../hooks/use-supplier-credit-memo";
import { CreditMemoStatusBadge } from "../components/credit-memo-status-badge";
import { CreditMemoMathPreview } from "../components/credit-memo-math-preview";
import { CreditMemoApplicationPanel } from "../components/credit-memo-application-panel";
import { CreditMemoExceptionPanel } from "../components/credit-memo-exception-panel";
import { CreditMemoApprovalPanel } from "../components/credit-memo-approval-panel";
import { CreditMemoAttachmentPanel } from "../components/credit-memo-attachment-panel";
import { CreditMemoActivityTimeline } from "../components/credit-memo-activity-timeline";
import { CreditMemoVoidPanel } from "../components/credit-memo-void-panel";
import { CreditMemoSubmitButton } from "../components/credit-memo-submit-button";
import { Card, CardContent, Skeleton } from "@cognify/ui";

export function CreditMemoDetailWorkspace() {
  const params = useParams<{ id: string }>();
  const id = Array.isArray(params?.id) ? params.id[0] : params?.id;
  const { data: memo, isLoading, isError, error } = useSupplierCreditMemo(id);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (isError || !memo) {
    return <div className="text-destructive">{(error as Error)?.message ?? "Failed to load credit memo."}</div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{memo.number}</h1>
          <p className="text-sm text-muted-foreground">
            {memo.vendorName ?? memo.vendorId} · {memo.totalAmount} {memo.currency}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <CreditMemoStatusBadge status={memo.status} />
          {memo.permissions?.canSubmit && <CreditMemoSubmitButton creditMemoId={memo.id} lockVersion={memo.lockVersion} />}
          {memo.permissions?.canVoidCreditMemo && <CreditMemoVoidPanel creditMemoId={memo.id} lockVersion={memo.lockVersion} />}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardContent className="pt-6 text-sm space-y-2">
              <p><span className="font-semibold">Original invoice:</span> {memo.originalInvoiceNumber ?? memo.originalInvoiceId}</p>
              <p><span className="font-semibold">Status:</span> {memo.status}</p>
              <p><span className="font-semibold">Currency:</span> {memo.currency}</p>
              <p><span className="font-semibold">Subtotal:</span> {memo.subtotalAmount}</p>
              <p><span className="font-semibold">Tax:</span> {memo.taxAmount}</p>
              <p><span className="font-semibold">Freight:</span> {memo.freightAmount}</p>
              <p><span className="font-semibold">Total:</span> {memo.totalAmount}</p>
              <p><span className="font-semibold">Applied:</span> {memo.appliedAmount}</p>
              <p><span className="font-semibold">Remaining:</span> {memo.remainingAmount}</p>
            </CardContent>
          </Card>
          <CreditMemoMathPreview lines={memo.lines ?? []} />
          {memo.permissions?.canApply && <CreditMemoApplicationPanel creditMemoId={memo.id} />}
          {memo.exceptions && memo.exceptions.length > 0 && <CreditMemoExceptionPanel creditMemoId={memo.id} />}
          <CreditMemoAttachmentPanel attachableId={memo.id} attachableType="supplier_credit_memo" />
        </div>
        <div className="space-y-4">
          <CreditMemoApprovalPanel creditMemoId={memo.id} />
          <CreditMemoActivityTimeline subjectType="supplier_credit_memo" subjectId={memo.id} />
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 5: Create the 3 page route files**

`apps/web/app/(workspace)/accounts-payable/credit-memos/page.tsx`:

```typescript
import { CreditMemoQueuePage } from "@/features/accounts-payable/workflows/credit-memo-queue-page";

export default function Page() {
  return <CreditMemoQueuePage />;
}
```

`apps/web/app/(workspace)/accounts-payable/credit-memos/new/page.tsx`:

```typescript
import { CreditMemoCreatePage } from "@/features/accounts-payable/workflows/credit-memo-create-page";

export default function Page() {
  return <CreditMemoCreatePage />;
}
```

`apps/web/app/(workspace)/accounts-payable/credit-memos/[id]/page.tsx`:

```typescript
import { CreditMemoDetailWorkspace } from "@/features/accounts-payable/workflows/credit-memo-detail-workspace";

export default function Page() {
  return <CreditMemoDetailWorkspace />;
}
```

- [ ] **Step 6: Add the navigation item**

Modify `apps/web/components/default-shell/navigation.tsx`. Add a new item to the Finance group's `items` array (after "Payment status"):

```typescript
      {
        title: "Credit memos",
        url: "/accounts-payable/credit-memos",
        implemented: true,
        permission: canUseAccountsPayable,
      },
```

In the `getBreadcrumbs(pathname)` function, add:

```typescript
  if (normalizedPathname === "/accounts-payable/credit-memos") return [{ label: "Finance" }, { label: "Credit memos" }];
```

- [ ] **Step 7: Extend the payment status badge with `reversed`**

Modify `apps/web/features/accounts-payable/components/payment-status-badge.tsx`. Add `reversed` to both the `statusStyles` and `defaultLabels` maps. Use the rose-200 style.

- [ ] **Step 8: Commit**

```bash
git add apps/web/features/accounts-payable/components/credit-memo-status-badge.tsx \
        apps/web/features/accounts-payable/components/credit-memo-math-preview.tsx \
        apps/web/features/accounts-payable/components/credit-memo-create-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-line-editor.tsx \
        apps/web/features/accounts-payable/components/credit-memo-application-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-exception-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-approval-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-attachment-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-activity-timeline.tsx \
        apps/web/features/accounts-payable/components/credit-memo-void-panel.tsx \
        apps/web/features/accounts-payable/components/credit-memo-submit-button.tsx \
        apps/web/features/accounts-payable/workflows/credit-memo-queue-page.tsx \
        apps/web/features/accounts-payable/workflows/credit-memo-create-page.tsx \
        apps/web/features/accounts-payable/workflows/credit-memo-detail-workspace.tsx \
        'apps/web/app/(workspace)/accounts-payable/credit-memos/page.tsx' \
        'apps/web/app/(workspace)/accounts-payable/credit-memos/new/page.tsx' \
        'apps/web/app/(workspace)/accounts-payable/credit-memos/[id]/page.tsx' \
        apps/web/components/default-shell/navigation.tsx \
        apps/web/features/accounts-payable/components/payment-status-badge.tsx
git commit -m "feat(p1-49): add credit memo queue, create, detail pages with 11 components and nav item"
```

---

## Task 42: Web Tests (3 test files)

**Files:**
- Create: `apps/web/features/accounts-payable/__tests__/credit-memo-queue-page.test.tsx`
- Create: `apps/web/features/accounts-payable/__tests__/credit-memo-detail-workspace.test.tsx`
- Create: `apps/web/features/accounts-payable/__tests__/credit-memo-application-panel.test.tsx`

- [ ] **Step 1: Write the queue page test**

Create `apps/web/features/accounts-payable/__tests__/credit-memo-queue-page.test.tsx`:

```typescript
import { describe, expect, it, beforeEach } from "vitest";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { CreditMemoQueuePage } from "../workflows/credit-memo-queue-page";
import { resetCreditMemoMockState } from "../mocks/accounts-payable-credit-memo-handlers";
import { setStoredActiveTenantId } from "@/features/identity/api/identity-api";

function renderWithProviders() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <CreditMemoQueuePage />
    </QueryClientProvider>,
  );
}

describe("CreditMemoQueuePage", () => {
  beforeEach(() => {
    setStoredActiveTenantId("tenant-1");
    resetCreditMemoMockState();
  });

  it("renders the status tabs and seeded credit memos", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText("CM-2026-000001")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: "All" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Draft" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Open" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Voided" })).toBeInTheDocument();
  });

  it("filters by tab", async () => {
    renderWithProviders();
    const user = userEvent.setup();
    await waitFor(() => {
      expect(screen.getByText("CM-2026-000001")).toBeInTheDocument();
    });
    await user.click(screen.getByRole("button", { name: "Open" }));
    await waitFor(() => {
      expect(screen.getByText("CM-2026-000002")).toBeInTheDocument();
    });
    expect(screen.queryByText("CM-2026-000001")).not.toBeInTheDocument();
  });

  it("renders status badge for each state", async () => {
    renderWithProviders();
    await waitFor(() => {
      const draftCard = screen.getByText("CM-2026-000001").closest("div")?.parentElement?.parentElement;
      expect(within(draftCard!).getByText("Draft")).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 2: Write the detail workspace test**

Create `apps/web/features/accounts-payable/__tests__/credit-memo-detail-workspace.test.tsx`:

```typescript
import { describe, expect, it, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { CreditMemoDetailWorkspace } from "../workflows/credit-memo-detail-workspace";
import { resetCreditMemoMockState } from "../mocks/accounts-payable-credit-memo-handlers";
import { setStoredActiveTenantId } from "@/features/identity/api/identity-api";

vi.mock("next/navigation", () => ({
  useParams: () => ({ id: "cm-1" }),
  useRouter: () => ({ push: vi.fn() }),
}));

function renderWithProviders() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <CreditMemoDetailWorkspace />
    </QueryClientProvider>,
  );
}

describe("CreditMemoDetailWorkspace", () => {
  beforeEach(() => {
    setStoredActiveTenantId("tenant-1");
    resetCreditMemoMockState();
  });

  it("renders header with number, vendor, and total", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText("CM-2026-000001")).toBeInTheDocument();
    });
    expect(screen.getByText(/Acme Supplies/)).toBeInTheDocument();
    expect(screen.getByText(/1080\.00 USD/)).toBeInTheDocument();
  });

  it("renders math preview with line subtotal", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText("Math preview")).toBeInTheDocument();
    });
    expect(screen.getByText("1000.0000")).toBeInTheDocument();
  });

  it("shows submit and void buttons for draft credit memo", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByText("CM-2026-000001")).toBeInTheDocument();
    });
    expect(screen.getByRole("button", { name: /submit/i })).toBeInTheDocument();
  });
});
```

- [ ] **Step 3: Write the application panel test**

Create `apps/web/features/accounts-payable/__tests__/credit-memo-application-panel.test.tsx`:

```typescript
import { describe, expect, it, beforeEach } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { CreditMemoApplicationPanel } from "../components/credit-memo-application-panel";
import { resetCreditApplicationMockState } from "../mocks/accounts-payable-credit-application-handlers";
import { setStoredActiveTenantId } from "@/features/identity/api/identity-api";

function renderWithProviders() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <CreditMemoApplicationPanel creditMemoId="cm-3" />
    </QueryClientProvider>,
  );
}

describe("CreditMemoApplicationPanel", () => {
  beforeEach(() => {
    setStoredActiveTenantId("tenant-1");
    resetCreditApplicationMockState();
  });

  it("renders the apply form", async () => {
    renderWithProviders();
    await waitFor(() => {
      expect(screen.getByLabelText(/supplier invoice id/i)).toBeInTheDocument();
    });
    expect(screen.getByLabelText(/applied amount/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/application date/i)).toBeInTheDocument();
  });

  it("submits a new application", async () => {
    renderWithProviders();
    const user = userEvent.setup();
    await waitFor(() => {
      expect(screen.getByLabelText(/supplier invoice id/i)).toBeInTheDocument();
    });
    await user.type(screen.getByLabelText(/supplier invoice id/i), "inv-3");
    await user.type(screen.getByLabelText(/applied amount/i), "200.00");
    await user.type(screen.getByLabelText(/application date/i), "2026-06-21");
    await user.click(screen.getByRole("button", { name: /apply/i }));
    await waitFor(() => {
      expect(screen.getByText(/First application/)).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 4: Run the new web tests**

Run:
```bash
pnpm --filter @cognify/web test -- credit-memo-queue-page
pnpm --filter @cognify/web test -- credit-memo-detail-workspace
pnpm --filter @cognify/web test -- credit-memo-application-panel
```

Expected: PASS for all three test files.

- [ ] **Step 5: Run the full web test suite to confirm no regression**

Run:
```bash
pnpm --filter @cognify/web test -- accounts-payable
```

Expected: PASS for the full accounts-payable suite.

- [ ] **Step 6: Commit**

```bash
git add apps/web/features/accounts-payable/__tests__/credit-memo-queue-page.test.tsx \
        apps/web/features/accounts-payable/__tests__/credit-memo-detail-workspace.test.tsx \
        apps/web/features/accounts-payable/__tests__/credit-memo-application-panel.test.tsx
git commit -m "test(p1-49): add web tests for credit memo queue, detail, and application panel"
```

---

## Task 43: Final Verification -- Run Full Suite

- [ ] **Step 1: Regenerate the API client and verify the contract**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: clean contract check.

- [ ] **Step 2: Run all P1-49 backend tests**

```bash
cd apps/api && php artisan test --filter=SupplierCreditMemo
cd apps/api && php artisan test --filter=CreditApplication
cd apps/api && php artisan test --filter=SupplierCreditMemoException
cd apps/api && php artisan test --filter=SupplierCreditMemoPolicy
```

Expected: all P1-49 test files pass.

- [ ] **Step 3: Run the P1-12 / P1-17 / P1-47 / P1-48 regression tests**

```bash
cd apps/api && php artisan test --filter=SupplierInvoice
cd apps/api && php artisan test --filter=ApPaymentHandoff
cd apps/api && php artisan test --filter=SupplierInvoicePayment
cd apps/api && php artisan test --filter=SupplierInvoicePaymentStatusReversedEnum
```

Expected: P1-12 invoice + P1-17 invoice exception + P1-47/P1-48 handoff regression suite still green (no regression). The new `Reversed` enum value is asserted in `SupplierInvoicePaymentStatusReversedEnumTest`.

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

- [ ] **Step 8: Run the demo seeder to verify all 8 credit memos land**

```bash
cd apps/api && php artisan migrate:fresh --seed 2>&1 | tail -10
cd apps/api && php artisan tinker --execute='echo json_encode([\Domains\CreditMemo\Models\SupplierCreditMemo::query()->where("number", "like", "CM-DEMO-%")->orderBy("number")->pluck("status", "number")->all(), \Domains\Invoice\Models\SupplierInvoice::query()->where("payment_status", "reversed")->pluck("invoice_number")->all()]);'
```

Expected JSON shows all 8 credit memos and the reversed invoice.

---

## Completion Checklist

This slice is complete when:

- [ ] All 43 tasks have been executed with passing tests.
- [ ] `pnpm generate:api && pnpm check:api-contract` succeed.
- [ ] `php artisan test --filter=SupplierCreditMemo` shows all P1-49 tests passing.
- [ ] `php artisan test --filter=SupplierInvoice` shows the P1-12 invoice lifecycle still green.
- [ ] `php artisan test --filter=ApPaymentHandoff` shows the P1-47/P1-48 handoff lifecycle still green.
- [ ] `pnpm --filter @cognify/web test -- accounts-payable` shows all web tests passing.
- [ ] `php artisan migrate:fresh --seed` produces CM-DEMO-001 through 008 with the correct statuses, and `INV-2026-DEMO-038` is in `payment_status=reversed`.
- [ ] `supplier_credit_memos.number` values match the `CM-YYYY-NNNNNN` format with 6-digit zero-pad sequence (e.g. `CM-2026-000001`).
- [ ] `reversed` is reachable from `payment_eligible`, `payment_ready`, `partially_paid`, and `paid` (and from any other `canApplyCreditFrom()` state) by accumulating non-voided `credit_applications` to `>= invoice.total_amount`.
- [ ] `reversed` is terminal: `isTerminal()` returns `true`, and `canApplyCreditFrom()` returns `false`.
- [ ] `reversed` is distinct from `paid`: both terminal, but only `paid` allows new payment allocations; only `reversed` allows new credit applications if the prior `credit_application` is voided and the invoice reverts to `partially_paid`.
- [ ] `VoidCreditApplication` reverts an invoice from `reversed` back to `partially_paid` (asserted in `CreditApplicationApiTest`).
- [ ] `VoidSupplierCreditMemo` reverts the credit memo's `status` and voids all non-voided `CreditApplication` rows in a single transaction.
- [ ] Concurrent `CreateCreditApplication` calls on the same credit memo return `409` (lock-version mismatch, asserted in `CreditApplicationApiTest::test_concurrent_credit_application_returns_409`).
- [ ] Tenant isolation: cross-tenant credit memo / application / exception access returns `403` (asserted in each of the three test files).
- [ ] Stale `lockVersion` on credit memo / application / exception returns `409` (asserted in each test file).
- [ ] The credit memo queue is reachable from the Finance nav group at `/accounts-payable/credit-memos`.
- [ ] Approval routing uses the registered `SupplierCreditMemoApprovalSubjectHandler`; `onApproved` calls `PostSupplierCreditMemo` (asserted in `SupplierCreditMemoApprovalSubjectHandlerTest`).
- [ ] All 20 audit event types are recorded (`supplier_credit_memo.created`, `.updated`, `.line_added`, `.line_updated`, `.line_removed`, `.submitted_for_approval`, `.posted`, `.applied`, `.application_voided`, `.fully_applied`, `.closed`, `.voided`, `supplier_credit_memo_exception.created/acknowledged/resolved/escalated`, `supplier_invoice.credit_applied/credit_application_voided/reversed/credit_memo_voided`).
- [ ] `SupplierCreditMemoLine.purchase_order_line_id` is stored for the P1-50 budget commitment downstream hook.
- [ ] `NULLS NOT DISTINCT` unique index on `credit_applications` (tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date) prevents duplicate applications.

---

## Deviations and Notes

1. **Empty folders**: `apps/api/Domains/CreditMemo` ships without empty subdirectories. Each folder is created only when the first file in it lands. Don't pre-create empty folders.
2. **OpenAPI hand-maintained**: The `openapi.json` file is hand-edited. The generated `packages/api-client` is consumed but not hand-modified. Re-run `pnpm generate:api` after every OpenAPI change.
3. **Tenant scoping in seeded data**: The seeded `CM-DEMO-001` through `CM-DEMO-008` use the same tenant as the existing P1-12 / P1-47 / P1-48 demos. The `seedCreditMemos` call is positioned after `seedInvoices` and `seedPaymentStatuses` so referenced invoices (`INV-2026-DEMO-031` through `INV-2026-DEMO-038`) and vendors exist.
4. **PostgreSQL < 15 fallback**: If the deployment environment runs PostgreSQL < 15, replace the `NULLS NOT DISTINCT` line in Task 4 with:
   ```sql
   CREATE UNIQUE INDEX ca_memo_invoice_date_unique_idx
     ON credit_applications
     (tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date)
   ```
   The credit application creation is sequential (single user AP flow) so the rare race window is acceptable in non-PG-15 deployments.
5. **Approval handler uses `supplierInvoiceId` field as a generic subjectId**: The shared `ApprovalContextData` is reused across handlers; the `supplierInvoiceId` field is repurposed for the credit memo id (with a code comment in `buildContext`). A future P1-XX slice can add a dedicated `supplierCreditMemoId` field to `ApprovalContextData` if richer approval routing data is needed.
6. **`onRejected` and `onChangesRequested` reset to draft**: The handler uses a simple `forceFill` to return the credit memo to `draft`. The actual reason persistence and the approval domain's task reason are stored in the `ApprovalInstance` row, not on the credit memo. This matches `SupplierInvoiceApprovalSubjectHandler`'s pattern.
7. **MSW handler reset**: `resetCreditMemoMockState`, `resetCreditApplicationMockState`, and `resetCreditMemoExceptionMockState` are registered in `tests/setup.ts` `afterEach` to ensure deterministic test state across all credit memo tests.
8. **`paymentStatus` filter on `supplier-invoices` queue**: The `validPaymentStatuses` array in `SupplierInvoiceController::applyQueueFilters()` is extended to include `payment_scheduled`, `partially_paid`, `paid`, and `reversed` so the queue supports filtering by the new P1-49 values.

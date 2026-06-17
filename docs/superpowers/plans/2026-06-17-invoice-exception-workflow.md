# Invoice Exception Workflow Implementation Plan (P1-45)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build P1-45 so that when 2/3-way matching detects mismatches, AP buyers can resolve each exception via value adjustment or explanation, escalate when blocked, and advance the invoice to `ready_for_approval` for P1-46 handoff.

**Architecture:** Dedicated `SupplierInvoiceException` records in `Domains/Invoice` — not extending match results or reusing Approval domain. Resolution produces a proposed payment overlay (`value_adjustment`) or a human waiver (`explanation`). The original invoice and match results remain immutable. Post-resolution matching only re-runs the engine when at least one exception used `value_adjustment`; explanation-only paths advance directly.

**Tech Stack:** Laravel 12, Sanctum tenant middleware, Eloquent, PostgreSQL 16 `NULLS NOT DISTINCT`, OpenAPI JSON, Orval-generated TypeScript client, Next.js App Router, React, TanStack Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## Reference Inputs

- Approved design spec: `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md`
- Matching engine tests: `apps/api/tests/Feature/InvoiceMatchingTest.php`
- Current invoice domain:
  - `apps/api/Domains/Invoice/Models/SupplierInvoice.php`
  - `apps/api/Domains/Invoice/Models/SupplierInvoiceMatchResult.php`
  - `apps/api/Domains/Invoice/Models/SupplierInvoiceLine.php`
  - `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php`
  - `apps/api/Domains/Invoice/Actions/RunInvoiceMatching.php`
  - `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceMatchingController.php`
  - `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php`
  - `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceMatchResultResource.php`
  - `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php`
  - `apps/api/routes/api.php`
- OpenAPI spec: `apps/api/storage/openapi/openapi.json`
- Web AP invoicing:
  - `apps/web/features/accounts-payable/api/accounts-payable-invoices-api.ts`
  - `apps/web/features/accounts-payable/hooks/use-accounts-payable-invoices.ts`
  - `apps/web/features/accounts-payable/hooks/use-invoice-matching.ts`
  - `apps/web/features/accounts-payable/components/invoice-match-results-panel.tsx`
  - `apps/web/features/accounts-payable/components/invoice-review-panel.tsx`
  - `apps/web/features/accounts-payable/workflows/accounts-payable-invoice-queue-page.tsx`
  - `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-fixtures.ts`
  - `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-handlers.ts`
- Migration patterns: `apps/api/database/migrations/2026_06_17_000004_create_supplier_invoice_match_results_table.php`

---

## File Map

### API

- Modify: `apps/api/tests/Feature/InvoiceMatchingTest.php` — add exception workflow tests
- Create: `apps/api/database/migrations/2026_06_17_000006_create_supplier_invoice_exceptions_table.php`
  - Composite unique index with `NULLS NOT DISTINCT`
  - `exception_summary` column on `supplier_invoices`
- Modify: `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php` — add `ReadyForApproval`
- Create: `apps/api/Domains/Invoice/Models/SupplierInvoiceException.php`
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoice.php` — add `exceptions()` relation, `exception_summary` cast
- Create: `apps/api/Domains/Invoice/Actions/CreateExceptionsFromMatchResults.php`
- Create: `apps/api/Domains/Invoice/Actions/ResolveInvoiceException.php`
- Create: `apps/api/Domains/Invoice/Actions/EscalateInvoiceException.php`
- Create: `apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php`
- Create: `apps/api/Domains/Invoice/Data/SupplierInvoiceExceptionResolutionData.php`
- Create: `apps/api/Domains/Invoice/Http/Requests/ResolveInvoiceExceptionRequest.php`
- Create: `apps/api/Domains/Invoice/Http/Requests/EscalateInvoiceExceptionRequest.php`
- Create: `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceExceptionController.php`
- Create: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceExceptionResource.php`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php` — add `exceptionSummary`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php` — add `exceptionSummary`
- Modify: `apps/api/routes/api.php` — add exception routes
- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/**`

### Web

- Create: `apps/web/features/accounts-payable/api/accounts-payable-invoice-exceptions-api.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-invoice-exceptions.ts`
- Create: `apps/web/features/accounts-payable/components/invoice-exception-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-exception-resolution-form.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-exception-status-badge.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-resolved-payment-summary.tsx`
- Create: `apps/web/features/accounts-payable/mocks/invoice-exception-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/invoice-exception-handlers.ts`
- Create: `apps/web/features/accounts-payable/tests/invoice-exception-workflow.test.tsx`
- Modify: `apps/web/features/accounts-payable/components/invoice-match-results-panel.tsx` — add exception workflow entry point
- Modify: `apps/web/features/accounts-payable/workflows/accounts-payable-invoice-queue-page.tsx` — handle mismatch/exception states
- Modify: `apps/web/tests/msw/handlers.ts` — register exception handlers
- Modify: `apps/web/tests/setup.ts` — reset exception mock state

### Demo Data

- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php` — seed mismatch invoice with exceptions
- Modify: `apps/api/tests/Feature/DemoSeederTest.php` — assert exception data

---

## Task 1: Backend Exception Workflow Tests

**Files:**
- Modify: `apps/api/tests/Feature/InvoiceMatchingTest.php`

- [ ] **Step 1: Add helper method for resolving an exception**

Add to InvoiceMatchingTest.php:

```php
use Domains\Invoice\Models\SupplierInvoiceException;

/**
 * @return array<string, mixed>
 */
private function createMismatchInvoice(Tenant $tenant, User $buyer): array
{
    $po = $this->issuedPurchaseOrder($tenant, $buyer);
    $line = $po->lines->firstOrFail();

    // Capture invoice with different price to trigger mismatch
    $priceDiffLine = $line->replicate()->setRawAttributes([
        ...$line->getAttributes(),
        'unit_price' => '150.0000',
        'quantity' => '5.0000',
        'total_amount' => '750.0000',
    ]);

    $payload = $this->capturePayload($po, $line, ['unit_price' => '150.0000']);
    $invoice = $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", $payload)
        ->assertCreated()
        ->json('data');

    // Complete review
    $started = $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/supplier-invoices/{$invoice['id']}/start-review", [
            'lockVersion' => $invoice['lockVersion'],
        ])
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/supplier-invoices/{$invoice['id']}/complete-review", [
            'lockVersion' => $started['lockVersion'],
            'checklist' => $this->passingReviewChecklist(),
        ])
        ->assertOk();

    // Run matching — produces mismatch
    $matched = $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/supplier-invoices/{$invoice['id']}/run-matching", [
            'lockVersion' => $started['lockVersion'] + 1,
        ])
        ->assertOk()
        ->json('data');

    return ['tenant' => $tenant, 'buyer' => $buyer, 'invoice' => $matched, 'po' => $po, 'line' => $line];
}
```

- [ ] **Step 2: Add failing test for listing exceptions**

```php
public function test_exceptions_are_created_after_mismatch_matching(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'dimension', 'matchType', 'status',
                    'expectedValue', 'actualValue',
                    'supplierInvoiceLineId', 'purchaseOrderLineId',
                ],
            ],
        ]);
}

public function test_exception_list_is_tenant_scoped(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$otherTenant, $otherBuyer] = $this->tenantUserPair(TenantRole::Buyer->value);

    $this->actingAsTenant($otherTenant, $otherBuyer)
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertNotFound();
}
```

- [ ] **Step 3: Add failing tests for resolving exceptions**

```php
public function test_buyer_can_resolve_exception_with_explanation(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $exception = $exceptions[0];

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/resolve", [
            'lockVersion' => 1,
            'resolutionType' => 'explanation',
            'explanation' => 'Price variance accepted per buyer discretion — market rate increase since PO issuance.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolutionType', 'explanation');
}

public function test_buyer_can_resolve_exception_with_value_adjustment(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $exception = $exceptions[0];

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/resolve", [
            'lockVersion' => 1,
            'resolutionType' => 'value_adjustment',
            'adjustedValue' => '150.0000',
            'explanation' => 'Unit price $150.00 reflects updated contract pricing.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved')
        ->assertJsonPath('data.resolutionType', 'value_adjustment')
        ->assertJsonPath('data.resolutionData.adjustedValue', '150.0000');
}
```

- [ ] **Step 4: Add failing test for escalating exceptions**

```php
public function test_buyer_can_escalate_exception(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$escalationTenant, $escalationUser] = $this->tenantUserPair(TenantRole::Buyer->value, $ctx['tenant']);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $exception = $exceptions[0];

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/escalate", [
            'lockVersion' => 1,
            'escalatedToUserId' => (string) $escalationUser->id,
            'note' => 'Requires procurement manager approval for price deviation > 20%.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.escalatedToUserId', (string) $escalationUser->id);
}

public function test_cannot_resolve_already_escalated_exception(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$escTenant, $escUser] = $this->tenantUserPair(TenantRole::Buyer->value, $ctx['tenant']);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/escalate", [
            'lockVersion' => 1,
            'escalatedToUserId' => (string) $escUser->id,
            'note' => 'Escalated.',
        ])
        ->assertOk();

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => 2,
            'resolutionType' => 'explanation',
            'explanation' => 'Should not work.',
        ])
        ->assertStatus(409);
}

public function test_cannot_re_escalate_exception(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$escTenant, $escUser] = $this->tenantUserPair(TenantRole::Buyer->value, $ctx['tenant']);
    [$escTenant2, $escUser2] = $this->tenantUserPair(TenantRole::Buyer->value, $ctx['tenant']);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/escalate", [
            'lockVersion' => 1,
            'escalatedToUserId' => (string) $escUser->id,
            'note' => 'First escalation.',
        ])
        ->assertOk();

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/escalate", [
            'lockVersion' => 2,
            'escalatedToUserId' => (string) $escUser2->id,
            'note' => 'Second escalation.',
        ])
        ->assertStatus(409);
}
```

- [ ] **Step 5: Add failing test for rejection on escalated exceptions**

```php
public function test_escalated_user_can_reject_escalated_exception(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$escTenant, $manager] = $this->tenantUserPair(TenantRole::Buyer->value, $ctx['tenant']);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/escalate", [
            'lockVersion' => 1,
            'escalatedToUserId' => (string) $manager->id,
            'note' => 'Requires approval.',
        ])
        ->assertOk();

    // Manager can reject (resolve with explanation on escalated exception)
    $this->actingAsTenant($ctx['tenant'], $manager)
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => 2,
            'resolutionType' => 'explanation',
            'explanation' => 'Price variance rejected — revert to PO price.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    // Original buyer can no longer modify
    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => 3,
            'resolutionType' => 'explanation',
            'explanation' => 'Should not work.',
        ])
        ->assertForbidden();
}
```

- [ ] **Step 6: Add failing test for post-resolution flow**

```php
public function test_all_explanations_advances_invoice_to_ready_for_approval(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    // Resolve all with explanation
    foreach ($exceptions as $exception) {
        $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
            ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/resolve", [
                'lockVersion' => $exception['lockVersion'],
                'resolutionType' => 'explanation',
                'explanation' => 'Accepted per policy.',
            ])
            ->assertOk();
    }

    // Invoice should now be ready_for_approval
    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_approval');
}

public function test_value_adjustment_reruns_matching_then_advances(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    $po = $ctx['po'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    // Resolve all price-related exceptions with value_adjustment to PO price
    foreach ($exceptions as $exception) {
        $adjustedValue = $exception['dimension'] === 'unit_price' ? '100.0000' : null;
        if ($exception['dimension'] === 'unit_price') {
            $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
                ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/resolve", [
                    'lockVersion' => $exception['lockVersion'],
                    'resolutionType' => 'value_adjustment',
                    'adjustedValue' => $adjustedValue,
                    'explanation' => 'Adjusted to PO unit price.',
                ])
                ->assertOk();
        } else {
            $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
                ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exception['id']}/resolve", [
                    'lockVersion' => $exception['lockVersion'],
                    'resolutionType' => 'explanation',
                    'explanation' => 'Accepted.',
                ])
                ->assertOk();
        }
    }

    // Invoice should be ready_for_approval after re-match pass
    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'ready_for_approval');
}

public function test_post_resolution_matching_reruns_when_value_adjustment_exists(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    // Resolve first with value_adjustment that doesn't match PO price
    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => $exceptions[0]['lockVersion'],
            'resolutionType' => 'value_adjustment',
            'adjustedValue' => '200.0000',
            'explanation' => 'Still different from PO.',
        ])
        ->assertOk();

    // Invoice should still be mismatch because value_adjustment caused re-match with different value
    $updated = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}")
        ->assertOk()
        ->json('data');

    // If re-match with value_adjustment fails, status stays mismatch
    // If all matching permutations now pass, status is ready_for_approval
    $this->assertContains($updated['status'], ['mismatch', 'ready_for_approval']);
}
```

- [ ] **Step 7: Add failing tests for guards (lock version, permissions)**

```php
public function test_exception_resolve_rejects_stale_lock_version(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => 999,
            'resolutionType' => 'explanation',
            'explanation' => 'Stale.',
        ])
        ->assertStatus(409);
}

public function test_exception_resolve_requires_buyer_or_admin(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [, $requester] = $this->tenantUserPair(TenantRole::Requester->value, $ctx['tenant']);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $requester)
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/resolve", [
            'lockVersion' => 1,
            'resolutionType' => 'explanation',
            'explanation' => 'Should be forbidden.',
        ])
        ->assertForbidden();
}

public function test_exception_escalate_requires_valid_tenant_user(): void
{
    $ctx = $this->createMismatchInvoice(...$this->tenantUserPair(TenantRole::Buyer->value));
    $invoice = $ctx['invoice'];
    [$otherTenant, $outsideUser] = $this->tenantUserPair(TenantRole::Buyer->value);

    $exceptions = $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->getJson("/api/supplier-invoices/{$invoice['id']}/exceptions")
        ->assertOk()
        ->json('data');

    $this->actingAsTenant($ctx['tenant'], $ctx['buyer'])
        ->postJson("/api/supplier-invoices/{$invoice['id']}/exceptions/{$exceptions[0]['id']}/escalate", [
            'lockVersion' => 1,
            'escalatedToUserId' => (string) $outsideUser->id,
            'note' => 'Outside user.',
        ])
        ->assertUnprocessable();
}

- [ ] **Step 8: Run tests and confirm red**

```bash
cd apps/api
php artisan test --filter=InvoiceMatchingTest
```

Expected: new tests fail with route/controller/model not found errors. Existing tests pass.

- [ ] **Step 9: Commit failing tests**

```bash
git add apps/api/tests/Feature/InvoiceMatchingTest.php
git commit -m "test: cover invoice exception workflow"
```

---

## Task 2: Backend Exception State, Migration, and Model

**Files:**
- Create: `apps/api/database/migrations/2026_06_17_000006_create_supplier_invoice_exceptions_table.php`
- Modify: `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php`
- Create: `apps/api/Domains/Invoice/Models/SupplierInvoiceException.php`
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoice.php`

- [ ] **Step 1: Create migration**

Create `apps/api/database/migrations/2026_06_17_000006_create_supplier_invoice_exceptions_table.php`.

```php
<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->json('exception_summary')->nullable()->after('matching_status');
        });

        Schema::create('supplier_invoice_exceptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices')->cascadeOnDelete();
            $table->string('dimension');
            $table->string('match_type');
            $table->foreignUuid('supplier_invoice_line_id')->nullable()->constrained('supplier_invoice_lines')->nullOnDelete();
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->string('status'); // open, resolved, escalated
            $table->string('resolution_type')->nullable(); // value_adjustment, explanation
            $table->json('resolution_data')->nullable();
            $table->foreignIdFor(User::class, 'resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignIdFor(User::class, 'escalated_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignIdFor(User::class, 'escalated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_note')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            // Composite unique: one exception per (invoice, dimension, match_type, line) with NULLS NOT DISTINCT
            $table->unique(
                ['tenant_id', 'supplier_invoice_id', 'dimension', 'match_type', 'supplier_invoice_line_id'],
                'supplier_invoice_exceptions_composite_unique',
                algorithm: 'nulls not distinct',
            );

            $table->index(['supplier_invoice_id', 'status']);
            $table->index(['supplier_invoice_id', 'dimension']);
            $table->index(['escalated_to_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_exceptions');
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropColumn('exception_summary');
        });
    }
};
```

- [ ] **Step 2: Extend enum**

Modify `SupplierInvoiceStatus.php`:

```php
<?php

namespace Domains\Invoice\States;

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
    case InReview = 'in_review';
    case NeedsInformation = 'needs_information';
    case Reviewed = 'reviewed';
    case Matched = 'matched';
    case Mismatch = 'mismatch';
    case ReadyForApproval = 'ready_for_approval';
}
```

- [ ] **Step 3: Create model**

Create `SupplierInvoiceException.php`:

```php
<?php

namespace Domains\Invoice\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoiceException extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'supplier_invoice_id',
        'dimension',
        'match_type',
        'supplier_invoice_line_id',
        'purchase_order_line_id',
        'expected_value',
        'actual_value',
        'status',
        'resolution_type',
        'resolution_data',
        'resolved_by_user_id',
        'resolved_at',
        'escalated_to_user_id',
        'escalated_by_user_id',
        'escalated_at',
        'escalation_note',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'resolution_data' => 'array',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceLine::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function escalatedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
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
}
```

- [ ] **Step 4: Extend SupplierInvoice model**

Add to `$fillable`:
```php
'exception_summary',
```

Add cast:
```php
'exception_summary' => 'array',
```

Add relation:
```php
public function exceptions(): HasMany
{
    return $this->hasMany(SupplierInvoiceException::class)->orderBy('created_at');
}
```

Add import for `Domains\Invoice\Models\SupplierInvoiceException` (not needed since same namespace — but need `use Domains\Invoice\Models\SupplierInvoiceException;` if referenced) and `HasMany`.

- [ ] **Step 5: Run migration**

```bash
cd apps/api
php artisan migrate
```

- [ ] **Step 6: Run tests and confirm they still fail on missing actions/controllers**

```bash
cd apps/api
php artisan test --filter=InvoiceMatchingTest
```

Expected: migration/model/status failures resolved; action/controller/route failures remain.

- [ ] **Step 7: Commit backend state**

```bash
git add apps/api/database/migrations/2026_06_17_000006_create_supplier_invoice_exceptions_table.php apps/api/Domains/Invoice/Models/SupplierInvoiceException.php apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php apps/api/Domains/Invoice/Models/SupplierInvoice.php
git commit -m "feat: add supplier invoice exception migration and model"
```

---

## Task 3: Backend Exception Actions and Endpoints

**Files:**
- Create: `apps/api/Domains/Invoice/Data/SupplierInvoiceExceptionResolutionData.php`
- Create: `apps/api/Domains/Invoice/Actions/CreateExceptionsFromMatchResults.php`
- Create: `apps/api/Domains/Invoice/Actions/ResolveInvoiceException.php`
- Create: `apps/api/Domains/Invoice/Actions/EscalateInvoiceException.php`
- Create: `apps/api/Domains/Invoice/Actions/RunPostResolutionMatching.php`
- Create: `apps/api/Domains/Invoice/Http/Requests/ResolveInvoiceExceptionRequest.php`
- Create: `apps/api/Domains/Invoice/Http/Requests/EscalateInvoiceExceptionRequest.php`
- Create: `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceExceptionController.php`
- Create: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceExceptionResource.php`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Create data helper**

Create `SupplierInvoiceExceptionResolutionData.php`:

```php
<?php

namespace Domains\Invoice\Data;

use InvalidArgumentException;

class SupplierInvoiceExceptionResolutionData
{
    public const RESOLUTION_TYPES = ['value_adjustment', 'explanation'];

    public static function normalize(string $resolutionType, ?string $adjustedValue, ?string $explanation): array
    {
        if (! in_array($resolutionType, self::RESOLUTION_TYPES, true)) {
            throw new InvalidArgumentException('Resolution type must be value_adjustment or explanation.');
        }

        $data = [];

        if ($resolutionType === 'value_adjustment') {
            if ($adjustedValue === null || ! is_numeric($adjustedValue)) {
                throw new InvalidArgumentException('Adjusted value is required for value_adjustment resolution.');
            }
            $data['adjusted_value'] = $adjustedValue;
        }

        if ($explanation !== null && trim($explanation) !== '') {
            $data['explanation'] = trim($explanation);
        }

        return $data;
    }
}
```

- [ ] **Step 2: Create `CreateExceptionsFromMatchResults` action**

This is called internally (not from a route) when matching produces failures. It is invoked by `RunInvoiceMatching` after it persists match results and sets status to `mismatch`.

```php
<?php

namespace Domains\Invoice\Actions;

use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Illuminate\Support\Facades\DB;

class CreateExceptionsFromMatchResults
{
    public function handle(SupplierInvoice $invoice): void
    {
        $failResults = SupplierInvoiceMatchResult::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->where('result', 'fail')
            ->get();

        if ($failResults->isEmpty()) {
            return;
        }

        $now = now();
        $exceptions = [];

        foreach ($failResults as $result) {
            $exceptions[] = [
                'tenant_id' => $invoice->tenant_id,
                'supplier_invoice_id' => $invoice->id,
                'dimension' => $result->dimension,
                'match_type' => $result->match_type,
                'supplier_invoice_line_id' => $result->supplier_invoice_line_id,
                'purchase_order_line_id' => $result->purchase_order_line_id,
                'expected_value' => $result->expected_value,
                'actual_value' => $result->actual_value,
                'status' => 'open',
                'lock_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($exceptions, $invoice): void {
            // Insert each exception individually so unique index violation is caught gracefully
            // (a match result may have been processed in a previous run)
            foreach ($exceptions as $exception) {
                SupplierInvoiceException::query()->firstOrCreate(
                    [
                        'tenant_id' => $exception['tenant_id'],
                        'supplier_invoice_id' => $exception['supplier_invoice_id'],
                        'dimension' => $exception['dimension'],
                        'match_type' => $exception['match_type'],
                        'supplier_invoice_line_id' => $exception['supplier_invoice_line_id'],
                    ],
                    $exception,
                );
            }

            $this->updateExceptionSummary($invoice);
        });
    }

    public function updateExceptionSummary(SupplierInvoice $invoice): void
    {
        $summary = SupplierInvoiceException::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->selectRaw("count(*) as total")
            ->selectRaw("count(*) filter (where status = 'open') as open")
            ->selectRaw("count(*) filter (where status = 'resolved') as resolved")
            ->selectRaw("count(*) filter (where status = 'escalated') as escalated")
            ->first();

        if ($summary !== null && $summary->total > 0) {
            $invoice->forceFill([
                'exception_summary' => [
                    'total' => (int) $summary->total,
                    'open' => (int) $summary->open,
                    'resolved' => (int) $summary->resolved,
                    'escalated' => (int) $summary->escalated,
                ],
            ])->save();
        }
    }
}
```

- [ ] **Step 3: Integrate exception creation into `RunInvoiceMatching`**

Modify `RunInvoiceMatching.php`:

After the existing `$hasFailures` loop and before `$before = $invoice->only(...)`, add:

```php
// Create exception records from failed match results
if ($hasFailures) {
    (new CreateExceptionsFromMatchResults())->handle($invoice);
}
```

Add import: `use Domains\Invoice\Actions\CreateExceptionsFromMatchResults;`

- [ ] **Step 4: Create `ResolveInvoiceException` action**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Data\SupplierInvoiceExceptionResolutionData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ResolveInvoiceException
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
        private readonly RunPostResolutionMatching $postResolutionMatching,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        User $actor,
        string $resolutionType,
        ?string $adjustedValue,
        ?string $explanation,
        int $lockVersion,
    ): SupplierInvoiceException {
        return DB::transaction(function () use ($supplierInvoice, $exception, $actor, $resolutionType, $adjustedValue, $explanation, $lockVersion) {
            $exception = SupplierInvoiceException::query()
                ->whereKey($exception->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $exception->assertLockVersion($lockVersion);

            if ($exception->status === 'escalated') {
                // Only the escalation target user can resolve escalated exceptions
                if ((string) $actor->id !== (string) $exception->escalated_to_user_id) {
                    throw new AccessDeniedHttpException('Only the escalation target can resolve an escalated exception.');
                }
            } elseif ($exception->status !== 'open') {
                throw new ConflictHttpException('Exception is not in a resolvable state.');
            }

            $resolutionData = SupplierInvoiceExceptionResolutionData::normalize($resolutionType, $adjustedValue, $explanation);

            $before = $exception->only(['status', 'lock_version']);
            $exception->forceFill([
                'status' => 'resolved',
                'resolution_type' => $resolutionType,
                'resolution_data' => $resolutionData,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'lock_version' => $exception->lock_version + 1,
            ])->save();
            $after = $exception->only(['status', 'lock_version']);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $supplierInvoice->tenant,
                actor: $actor,
                action: 'supplier_invoice_exception.resolved',
                subject: $supplierInvoice,
                metadata: [
                    'exceptionId' => (string) $exception->id,
                    'dimension' => $exception->dimension,
                    'resolutionType' => $resolutionType,
                    'resolutionData' => $resolutionData,
                ],
                before: $before,
                after: $after,
            ));

            $this->exceptionCreator->updateExceptionSummary($supplierInvoice);

            // Check if all exceptions resolved → trigger post-resolution flow
            $this->resolveIfAllExceptionsResolved($supplierInvoice, $actor);

            return $exception->fresh();
        });
    }

    private function resolveIfAllExceptionsResolved(SupplierInvoice $invoice, User $actor): void
    {
        $openCount = SupplierInvoiceException::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('tenant_id', $invoice->tenant_id)
            ->where('status', '!=', 'resolved')
            ->count();

        if ($openCount > 0) {
            return;
        }

        // All exceptions are resolved — trigger post-resolution
        $this->postResolutionMatching->handle($invoice, $actor);
    }
}
```

- [ ] **Step 5: Create `EscalateInvoiceException` action**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EscalateInvoiceException
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
    ) {}

    public function handle(
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        User $actor,
        User $escalatedToUser,
        ?string $note,
        int $lockVersion,
    ): SupplierInvoiceException {
        return DB::transaction(function () use ($supplierInvoice, $exception, $actor, $escalatedToUser, $note, $lockVersion) {
            $exception = SupplierInvoiceException::query()
                ->whereKey($exception->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $exception->assertLockVersion($lockVersion);

            if ($exception->status !== 'open') {
                throw new ConflictHttpException('Only open exceptions can be escalated.');
            }

            $before = $exception->only(['status', 'lock_version']);
            $exception->forceFill([
                'status' => 'escalated',
                'escalated_to_user_id' => $escalatedToUser->id,
                'escalated_by_user_id' => $actor->id,
                'escalated_at' => now(),
                'escalation_note' => $note,
                'lock_version' => $exception->lock_version + 1,
            ])->save();
            $after = $exception->only(['status', 'lock_version']);

            $this->auditRecorder->record(new AuditEventData(
                tenant: $supplierInvoice->tenant,
                actor: $actor,
                action: 'supplier_invoice_exception.escalated',
                subject: $supplierInvoice,
                metadata: [
                    'exceptionId' => (string) $exception->id,
                    'dimension' => $exception->dimension,
                    'escalatedToUserId' => (string) $escalatedToUser->id,
                ],
                before: $before,
                after: $after,
            ));

            $this->exceptionCreator->updateExceptionSummary($supplierInvoice);

            return $exception->fresh();
        });
    }
}
```

- [ ] **Step 6: Create `RunPostResolutionMatching` action**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Illuminate\Support\Facades\DB;

class RunPostResolutionMatching
{
    public function __construct(
        private readonly RunInvoiceMatching $matchingAction,
        private readonly CreateExceptionsFromMatchResults $exceptionCreator,
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor): void
    {
        DB::transaction(function () use ($supplierInvoice, $actor) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if any resolved exception used value_adjustment
            $hasValueAdjustment = SupplierInvoiceException::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->where('tenant_id', $invoice->tenant_id)
                ->where('resolution_type', 'value_adjustment')
                ->exists();

            if ($hasValueAdjustment) {
                // Re-run matching to validate adjusted values
                $updatedInvoice = $this->matchingAction->handle(
                    $invoice,
                    $actor,
                    (int) $invoice->lock_version,
                    'post_resolution',
                );

                // Create exceptions for any new failures from re-match
                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Mismatch->value) {
                    $this->exceptionCreator->handle($updatedInvoice);
                }

                // If the re-match passed, status is now 'matched' from RunInvoiceMatching
                // We need to transition to ready_for_approval
                if ($updatedInvoice->matching_status === SupplierInvoiceStatus::Matched->value) {
                    $this->transitionToReadyForApproval($updatedInvoice, $actor);
                }
            } else {
                // All exceptions are explanation-only — advance directly
                $this->transitionToReadyForApproval($invoice, $actor);
            }
        });
    }

    private function transitionToReadyForApproval(SupplierInvoice $invoice, User $actor): void
    {
        $before = $invoice->only(['status', 'lock_version']);
        $invoice->forceFill([
            'status' => SupplierInvoiceStatus::ReadyForApproval,
            'lock_version' => $invoice->lock_version + 1,
        ])->save();
        $after = $invoice->only(['status', 'lock_version']);

        $this->auditRecorder->record(new AuditEventData(
            tenant: $invoice->tenant,
            actor: $actor,
            action: 'supplier_invoice.ready_for_approval',
            subject: $invoice,
            after: $after,
            before: $before,
        ));
    }
}
```

- [ ] **Step 7: Create FormRequest classes**

`ResolveInvoiceExceptionRequest.php`:

```php
<?php

namespace Domains\Invoice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveInvoiceExceptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'resolutionType' => ['required', 'string', Rule::in(['value_adjustment', 'explanation'])],
            'adjustedValue' => ['nullable', 'numeric', 'min:0', 'required_if:resolutionType,value_adjustment'],
            'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

`EscalateInvoiceExceptionRequest.php`:

```php
<?php

namespace Domains\Invoice\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EscalateInvoiceExceptionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'escalatedToUserId' => ['required', 'string', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 8: Create exception resource**

`SupplierInvoiceExceptionResource.php`:

```php
<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierInvoiceException
 */
class SupplierInvoiceExceptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'supplierInvoiceId' => (string) $this->supplier_invoice_id,
            'dimension' => $this->dimension,
            'matchType' => $this->match_type,
            'supplierInvoiceLineId' => $this->supplier_invoice_line_id !== null ? (string) $this->supplier_invoice_line_id : null,
            'purchaseOrderLineId' => $this->purchase_order_line_id !== null ? (string) $this->purchase_order_line_id : null,
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'actualValue' => $this->actual_value !== null ? (string) $this->actual_value : null,
            'status' => $this->status,
            'resolutionType' => $this->resolution_type,
            'resolutionData' => $this->resolution_data,
            'resolvedByUserId' => $this->resolved_by_user_id !== null ? (string) $this->resolved_by_user_id : null,
            'resolvedAt' => $this->resolved_at?->toISOString(),
            'escalatedToUserId' => $this->escalated_to_user_id !== null ? (string) $this->escalated_to_user_id : null,
            'escalatedByUserId' => $this->escalated_by_user_id !== null ? (string) $this->escalated_by_user_id : null,
            'escalatedAt' => $this->escalated_at?->toISOString(),
            'escalationNote' => $this->escalation_note,
            'lockVersion' => $this->lock_version,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 9: Create controller**

`SupplierInvoiceExceptionController.php`:

```php
<?php

namespace Domains\Invoice\Http\Controllers;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Invoice\Actions\EscalateInvoiceException;
use Domains\Invoice\Actions\ResolveInvoiceException;
use Domains\Invoice\Http\Requests\EscalateInvoiceExceptionRequest;
use Domains\Invoice\Http\Requests\ResolveInvoiceExceptionRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceExceptionResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierInvoiceExceptionController
{
    use AuthorizesRequests;

    public function index(
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('view', $supplierInvoice);

        $exceptions = SupplierInvoiceException::query()
            ->where('tenant_id', $tenant->id)
            ->where('supplier_invoice_id', $supplierInvoice->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierInvoiceExceptionResource::collection($exceptions),
        ]);
    }

    public function resolve(
        ResolveInvoiceExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        ResolveInvoiceException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $exception = $action->handle(
            $supplierInvoice,
            $exception,
            $request->user(),
            $request->validated('resolutionType'),
            $request->validated('adjustedValue'),
            $request->validated('explanation'),
            (int) $request->validated('lockVersion'),
        );

        return response()->json([
            'data' => (new SupplierInvoiceExceptionResource($exception))->resolve($request),
        ]);
    }

    public function escalate(
        EscalateInvoiceExceptionRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
        SupplierInvoiceException $exception,
        EscalateInvoiceException $action,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $supplierInvoice = $this->findTenantSupplierInvoice($tenant, $supplierInvoice);
        $this->authorize('review', $supplierInvoice);

        $escalatedToUser = User::query()
            ->whereKey($request->validated('escalatedToUserId'))
            ->whereHas('tenants', fn ($q) => $q->whereKey($tenant->id))
            ->firstOrFail();

        $exception = $action->handle(
            $supplierInvoice,
            $exception,
            $request->user(),
            $escalatedToUser,
            $request->validated('note'),
            (int) $request->validated('lockVersion'),
        );

        return response()->json([
            'data' => (new SupplierInvoiceExceptionResource($exception))->resolve($request),
        ]);
    }

    private function findTenantSupplierInvoice(Tenant $tenant, SupplierInvoice $supplierInvoice): SupplierInvoice
    {
        return SupplierInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($supplierInvoice->id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        return $tenant;
    }
}
```

- [ ] **Step 10: Add exception summary to resources**

In `SupplierInvoiceResource.php`, add to the response array:

```php
'exceptionSummary' => $this->exception_summary,
```

In `SupplierInvoiceQueueResource.php`, add:

```php
'exceptionSummary' => $this->exception_summary,
```

- [ ] **Step 11: Wire routes**

In `apps/api/routes/api.php`, add import:

```php
use Domains\Invoice\Http\Controllers\SupplierInvoiceExceptionController;
```

Add routes after the matching routes (around line 197):

```php
Route::get('/supplier-invoices/{supplierInvoice}/exceptions', [SupplierInvoiceExceptionController::class, 'index']);
Route::post('/supplier-invoices/{supplierInvoice}/exceptions/{exception}/resolve', [SupplierInvoiceExceptionController::class, 'resolve']);
Route::post('/supplier-invoices/{supplierInvoice}/exceptions/{exception}/escalate', [SupplierInvoiceExceptionController::class, 'escalate']);
```

These must be inside the `RequireTenantHeader` middleware group (same as existing invoice routes).

- [ ] **Step 12: Run focused tests and fix issues**

```bash
cd apps/api
php artisan test --filter=InvoiceMatchingTest
```

Expected: all tests pass.

- [ ] **Step 13: Check routes**

```bash
cd apps/api
php artisan route:list --path=supplier-invoices
```

Expected output includes:
```
GET|HEAD  api/supplier-invoices/{supplierInvoice}/exceptions
POST      api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/resolve
POST      api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/escalate
```

- [ ] **Step 14: Commit backend endpoints**

```bash
git add apps/api/Domains/Invoice/Data/SupplierInvoiceExceptionResolutionData.php apps/api/Domains/Invoice/Actions apps/api/Domains/Invoice/Http
git add apps/api/routes/api.php
git commit -m "feat: add invoice exception resolution endpoints"
```

---

## Task 4: OpenAPI Contract and Generated Client

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/**`

- [ ] **Step 1: Add OpenAPI schemas for exceptions**

In `apps/api/storage/openapi/openapi.json`, update `SupplierInvoice.status.enum`:

```json
["captured", "in_review", "needs_information", "reviewed", "matched", "mismatch", "ready_for_approval"]
```

Add these schemas under `components.schemas`:

```json
"SupplierInvoiceException": {
  "type": "object",
  "required": ["id", "supplierInvoiceId", "dimension", "matchType", "status", "lockVersion", "createdAt"],
  "properties": {
    "id": { "type": "string" },
    "supplierInvoiceId": { "type": "string" },
    "dimension": { "type": "string" },
    "matchType": { "type": "string" },
    "supplierInvoiceLineId": { "type": ["string", "null"] },
    "purchaseOrderLineId": { "type": ["string", "null"] },
    "expectedValue": { "type": ["string", "null"] },
    "actualValue": { "type": ["string", "null"] },
    "status": { "type": "string", "enum": ["open", "resolved", "escalated"] },
    "resolutionType": { "type": ["string", "null"], "enum": ["value_adjustment", "explanation", null] },
    "resolutionData": { "type": ["object", "null"] },
    "resolvedByUserId": { "type": ["string", "null"] },
    "resolvedAt": { "type": ["string", "null"], "format": "date-time" },
    "escalatedToUserId": { "type": ["string", "null"] },
    "escalatedByUserId": { "type": ["string", "null"] },
    "escalatedAt": { "type": ["string", "null"], "format": "date-time" },
    "escalationNote": { "type": ["string", "null"] },
    "lockVersion": { "type": "integer" },
    "createdAt": { "type": "string", "format": "date-time" }
  }
},
"SupplierInvoiceExceptionListResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "type": "array", "items": { "$ref": "#/components/schemas/SupplierInvoiceException" } }
  }
},
"SupplierInvoiceExceptionResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/SupplierInvoiceException" }
  }
},
"ResolveInvoiceExceptionRequest": {
  "type": "object",
  "required": ["lockVersion", "resolutionType"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "resolutionType": { "type": "string", "enum": ["value_adjustment", "explanation"] },
    "adjustedValue": { "type": ["string", "null"] },
    "explanation": { "type": ["string", "null"], "maxLength": 2000 }
  }
},
"EscalateInvoiceExceptionRequest": {
  "type": "object",
  "required": ["lockVersion", "escalatedToUserId"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 },
    "escalatedToUserId": { "type": "string" },
    "note": { "type": ["string", "null"], "maxLength": 2000 }
  }
}
```

Add `exceptionSummary` to `SupplierInvoice.properties` and `SupplierInvoiceQueueItem.properties`:

```json
"exceptionSummary": {
  "type": ["object", "null"],
  "properties": {
    "total": { "type": "integer" },
    "open": { "type": "integer" },
    "resolved": { "type": "integer" },
    "escalated": { "type": "integer" }
  }
}
```

- [ ] **Step 2: Add OpenAPI paths**

```json
"/api/supplier-invoices/{supplierInvoice}/exceptions": {
  "get": {
    "operationId": "listSupplierInvoiceExceptions",
    "tags": ["Accounts Payable"],
    "summary": "List exceptions for a supplier invoice",
    "parameters": [
      { "$ref": "#/components/parameters/TenantId" },
      { "name": "supplierInvoice", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "List of exceptions", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierInvoiceExceptionListResponse" } } } },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" }
    }
  }
},
"/api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/resolve": {
  "post": {
    "operationId": "resolveSupplierInvoiceException",
    "tags": ["Accounts Payable"],
    "summary": "Resolve an invoice exception",
    "parameters": [
      { "$ref": "#/components/parameters/TenantId" },
      { "name": "supplierInvoice", "in": "path", "required": true, "schema": { "type": "string" } },
      { "name": "exception", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/ResolveInvoiceExceptionRequest" } } }
    },
    "responses": {
      "200": { "description": "Resolved exception", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierInvoiceExceptionResponse" } } } },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" }
    }
  }
},
"/api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/escalate": {
  "post": {
    "operationId": "escalateSupplierInvoiceException",
    "tags": ["Accounts Payable"],
    "summary": "Escalate an invoice exception",
    "parameters": [
      { "$ref": "#/components/parameters/TenantId" },
      { "name": "supplierInvoice", "in": "path", "required": true, "schema": { "type": "string" } },
      { "name": "exception", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": { "application/json": { "schema": { "$ref": "#/components/schemas/EscalateInvoiceExceptionRequest" } } }
    },
    "responses": {
      "200": { "description": "Escalated exception", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/SupplierInvoiceExceptionResponse" } } } },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" }
    }
  }
}
```

- [ ] **Step 3: Regenerate client**

```bash
pnpm --filter @cognify/api-client generate
```

Expected: generated files include `listSupplierInvoiceExceptions`, `resolveSupplierInvoiceException`, `escalateSupplierInvoiceException`, and all exception schemas.

- [ ] **Step 4: Run contract check**

```bash
pnpm --filter @cognify/api-client check:contract
```

Expected: PASS.

- [ ] **Step 5: Commit contract and generated client**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add invoice exception API contract"
```

---

## Task 5: Web API, Hooks, MSW, and Tests

**Files:**
- Create: `apps/web/features/accounts-payable/api/accounts-payable-invoice-exceptions-api.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-invoice-exceptions.ts`
- Create: `apps/web/features/accounts-payable/mocks/invoice-exception-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/invoice-exception-handlers.ts`
- Create: `apps/web/features/accounts-payable/tests/invoice-exception-workflow.test.tsx`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/tests/setup.ts`

- [ ] **Step 1: Write failing web tests for exception workflow**

Create `invoice-exception-workflow.test.tsx`:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import type { ReactNode } from "react";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { resetInvoiceExceptionMockState } from "../mocks/invoice-exception-handlers";
import { AccountsPayableInvoiceQueuePage } from "../workflows/accounts-payable-invoice-queue-page";

describe("Invoice exception workflow", () => {
  beforeEach(() => {
    resetInvoiceExceptionMockState();
    window.localStorage.clear();
    window.localStorage.setItem("cognify.activeTenantId", "1");
  });

  it("shows exception panel when invoice has mismatches", async () => {
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await userEvent.click(within(row).getByRole("button", { name: "View exceptions" }));

    expect(await screen.findByText("Unit price mismatch")).toBeInTheDocument();
  });

  it("allows resolving an exception with explanation", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    await user.click(screen.getByRole("button", { name: "Resolve" }));

    await user.click(screen.getByLabelText("Explanation"));
    await user.type(screen.getByLabelText("Explanation notes"), "Price variance accepted per policy.");

    await user.click(screen.getByRole("button", { name: "Submit resolution" }));

    await waitFor(() => {
      expect(screen.getByText("resolved")).toBeInTheDocument();
    });
  });

  it("allows escalating an exception", async () => {
    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));

    await user.click(screen.getByRole("button", { name: "Escalate" }));
    await user.type(screen.getByLabelText("Escalation note"), "Requires manager review.");

    await user.click(screen.getByRole("button", { name: "Confirm escalation" }));

    await waitFor(() => {
      expect(screen.getByText("escalated")).toBeInTheDocument();
    });
  });

  it("shows conflict error when exception lock version is stale", async () => {
    server.use(
      http.post("/api/supplier-invoices/:supplierInvoice/exceptions/:exception/resolve", () =>
        HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        ),
      ),
    );

    const user = userEvent.setup();
    render(<AccountsPayableInvoiceQueuePage />, { wrapper: TestProviders });

    const row = await screen.findByRole("row", { name: /INV-MISMATCH/i });
    await user.click(within(row).getByRole("button", { name: "View exceptions" }));
    await user.click(screen.getByRole("button", { name: "Resolve" }));
    await user.click(screen.getByLabelText("Explanation"));
    await user.click(screen.getByRole("button", { name: "Submit resolution" }));

    expect(await screen.findByRole("alert")).toHaveTextContent("Exception was updated by another user");
  });
});

function TestProviders({ children }: { children: ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}
```

- [ ] **Step 2: Create API wrapper**

Create `accounts-payable-invoice-exceptions-api.ts`:

```typescript
import {
  listSupplierInvoiceExceptions,
  resolveSupplierInvoiceException,
  escalateSupplierInvoiceException,
} from "@cognify/api-client/endpoints";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import {
  withActiveTenantHeader,
  unwrapData,
  throwResponseData,
} from "@/features/identity/api/identity-api";

// --- Queries ---

export async function fetchInvoiceExceptions(
  supplierInvoiceId: string,
  tenantId: string | null,
): Promise<SupplierInvoiceException[]> {
  const response = await listSupplierInvoiceExceptions(supplierInvoiceId, {
    headers: withActiveTenantHeader(tenantId),
  });
  return unwrapData(response);
}

// --- Mutations ---

export interface ResolveExceptionPayload {
  lockVersion: number;
  resolutionType: "value_adjustment" | "explanation";
  adjustedValue?: string;
  explanation?: string;
}

export async function resolveException(
  supplierInvoiceId: string,
  exceptionId: string,
  payload: ResolveExceptionPayload,
  tenantId: string | null,
): Promise<SupplierInvoiceException> {
  const response = await resolveSupplierInvoiceException(
    supplierInvoiceId,
    exceptionId,
    payload,
    { headers: withActiveTenantHeader(tenantId) },
  );
  if (!response.ok) {
    throw await throwResponseData(response);
  }
  return unwrapData(response);
}

export interface EscalateExceptionPayload {
  lockVersion: number;
  escalatedToUserId: string;
  note?: string;
}

export async function escalateException(
  supplierInvoiceId: string,
  exceptionId: string,
  payload: EscalateExceptionPayload,
  tenantId: string | null,
): Promise<SupplierInvoiceException> {
  const response = await escalateSupplierInvoiceException(
    supplierInvoiceId,
    exceptionId,
    payload,
    { headers: withActiveTenantHeader(tenantId) },
  );
  if (!response.ok) {
    throw await throwResponseData(response);
  }
  return unwrapData(response);
}
```

- [ ] **Step 3: Create React Query hooks**

Create `use-invoice-exceptions.ts`:

```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchInvoiceExceptions,
  resolveException,
  escalateException,
  type ResolveExceptionPayload,
  type EscalateExceptionPayload,
} from "../api/accounts-payable-invoice-exceptions-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

export const invoiceExceptionKeys = {
  all: ["accounts-payable", "invoice-exceptions"],
  list: (tenantId: string, invoiceId: string) => [...invoiceExceptionKeys.all, "list", tenantId, invoiceId],
};

export function useInvoiceExceptions(supplierInvoiceId: string | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: invoiceExceptionKeys.list(tenantId ?? "no-tenant", supplierInvoiceId ?? "no-invoice"),
    queryFn: () => fetchInvoiceExceptions(supplierInvoiceId!, tenantId),
    enabled: Boolean(tenantId) && Boolean(supplierInvoiceId),
  });
}

export function useResolveException(supplierInvoiceId: string, onSuccess?: () => void) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: ResolveExceptionPayload;
    }) => resolveException(supplierInvoiceId, exceptionId, payload, tenantId),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: invoiceExceptionKeys.all,
      });
      queryClient.invalidateQueries({
        queryKey: accountsPayableInvoiceKeys.all,
      });
      onSuccess?.();
    },
  });
}

export function useEscalateException(supplierInvoiceId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: EscalateExceptionPayload;
    }) => escalateException(supplierInvoiceId, exceptionId, payload, tenantId),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: invoiceExceptionKeys.all,
      });
      queryClient.invalidateQueries({
        queryKey: accountsPayableInvoiceKeys.all,
      });
    },
  });
}
```

- [ ] **Step 4: Create fixtures and MSW handlers**

Create `invoice-exception-fixtures.ts`:

```typescript
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

export function buildExceptionFixture(overrides: Partial<SupplierInvoiceException> = {}): SupplierInvoiceException {
  return {
    id: "exception-1",
    supplierInvoiceId: "invoice-mismatch-1",
    dimension: "unit_price",
    matchType: "two_way",
    supplierInvoiceLineId: "line-1",
    purchaseOrderLineId: "po-line-1",
    expectedValue: "100.0000",
    actualValue: "150.0000",
    status: "open",
    resolutionType: null,
    resolutionData: null,
    resolvedByUserId: null,
    resolvedAt: null,
    escalatedToUserId: null,
    escalatedByUserId: null,
    escalatedAt: null,
    escalationNote: null,
    lockVersion: 1,
    createdAt: "2026-06-17T12:00:00Z",
    ...overrides,
  };
}

export const invoiceExceptionFixtures = {
  mismatchInvoice: {
    id: "invoice-mismatch-1",
    number: "INV-MISMATCH-001",
    status: "mismatch",
    matchingStatus: "mismatch",
    exceptionSummary: { total: 2, open: 2, resolved: 0, escalated: 0 },
  },
  exceptions: [
    buildExceptionFixture({ id: "exc-1", dimension: "unit_price" }),
    buildExceptionFixture({
      id: "exc-2",
      dimension: "line_total",
      expectedValue: "500.0000",
      actualValue: "750.0000",
    }),
  ],
};
```

Create `invoice-exception-handlers.ts`:

```typescript
import { http, HttpResponse } from "msw";
import { invoiceExceptionFixtures, buildExceptionFixture } from "./invoice-exception-fixtures";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

let exceptions = [...invoiceExceptionFixtures.exceptions];

export function resetInvoiceExceptionMockState() {
  exceptions = invoiceExceptionFixtures.exceptions.map((e) => ({ ...e }));
}

export const invoiceExceptionHandlers = [
  http.get("/api/supplier-invoices/:supplierInvoice/exceptions", () => {
    return HttpResponse.json({ data: exceptions });
  }),

  http.post(
    "/api/supplier-invoices/:supplierInvoice/exceptions/:exception/resolve",
    async ({ params, request }) => {
      const body = (await request.json()) as {
        lockVersion: number;
        resolutionType: string;
        adjustedValue?: string;
        explanation?: string;
      };
      const idx = exceptions.findIndex((e) => e.id === params.exception);
      if (idx === -1) return new HttpResponse(null, { status: 404 });
      if (exceptions[idx].lockVersion !== body.lockVersion) {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        );
      }
      exceptions[idx] = {
        ...exceptions[idx],
        status: "resolved" as const,
        resolutionType: body.resolutionType as "value_adjustment" | "explanation",
        resolutionData: {
          ...(body.adjustedValue ? { adjusted_value: body.adjustedValue } : {}),
          ...(body.explanation ? { explanation: body.explanation } : {}),
        },
        resolvedByUserId: "user-1",
        resolvedAt: new Date().toISOString(),
        lockVersion: exceptions[idx].lockVersion + 1,
      };
      return HttpResponse.json({ data: exceptions[idx] });
    },
  ),

  http.post(
    "/api/supplier-invoices/:supplierInvoice/exceptions/:exception/escalate",
    async ({ params, request }) => {
      const body = (await request.json()) as {
        lockVersion: number;
        escalatedToUserId: string;
        note?: string;
      };
      const idx = exceptions.findIndex((e) => e.id === params.exception);
      if (idx === -1) return new HttpResponse(null, { status: 404 });
      if (exceptions[idx].lockVersion !== body.lockVersion) {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        );
      }
      exceptions[idx] = {
        ...exceptions[idx],
        status: "escalated" as const,
        escalatedToUserId: body.escalatedToUserId,
        escalatedByUserId: "user-1",
        escalatedAt: new Date().toISOString(),
        escalationNote: body.note ?? null,
        lockVersion: exceptions[idx].lockVersion + 1,
      };
      return HttpResponse.json({ data: exceptions[idx] });
    },
  ),
];
```

- [ ] **Step 5: Register handlers in `handlers.ts` and `setup.ts`**

Modify `apps/web/tests/msw/handlers.ts` — add import and spread:

```typescript
import { invoiceExceptionHandlers } from "@/features/accounts-payable/mocks/invoice-exception-handlers";

export const handlers = [
  ...accountsPayableInvoiceHandlers,
  ...invoiceExceptionHandlers,
  // ... other handler spreads
];
```

Modify `apps/web/tests/setup.ts` — add to reset:

```typescript
import { resetInvoiceExceptionMockState } from "@/features/accounts-payable/mocks/invoice-exception-handlers";

// In afterEach or global cleanup:
resetInvoiceExceptionMockState();
```

- [ ] **Step 6: Run web tests (should fail on missing components)**

```bash
pnpm --filter @cognify/web test -- invoice-exception-workflow
```

Expected: failures for missing components (exception-panel, resolution-form, etc.).

- [ ] **Step 7: Commit web API layer**

```bash
git add apps/web/features/accounts-payable/api/accounts-payable-invoice-exceptions-api.ts apps/web/features/accounts-payable/hooks/use-invoice-exceptions.ts apps/web/features/accounts-payable/mocks apps/web/features/accounts-payable/tests apps/web/tests
git commit -m "feat: add invoice exception web API, hooks, mocks, and tests"
```

---

## Task 6: Web Exception Workflow UI Components

**Files:**
- Create: `apps/web/features/accounts-payable/components/invoice-exception-status-badge.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-exception-resolution-form.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-exception-panel.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-resolved-payment-summary.tsx`
- Modify: `apps/web/features/accounts-payable/components/invoice-match-results-panel.tsx`
- Modify: `apps/web/features/accounts-payable/workflows/accounts-payable-invoice-queue-page.tsx`

- [ ] **Step 1: Create exception status badge**

`invoice-exception-status-badge.tsx`:

```tsx
import { Badge } from "@cognify/ui/badge";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

interface InvoiceExceptionStatusBadgeProps {
  status: SupplierInvoiceException["status"];
}

const variantMap: Record<string, "default" | "secondary" | "outline" | "destructive"> = {
  open: "default",
  resolved: "secondary",
  escalated: "destructive",
};

const labelMap: Record<string, string> = {
  open: "Open",
  resolved: "Resolved",
  escalated: "Escalated",
};

export function InvoiceExceptionStatusBadge({ status }: InvoiceExceptionStatusBadgeProps) {
  return (
    <Badge variant={variantMap[status] ?? "outline"}>
      {labelMap[status] ?? status}
    </Badge>
  );
}
```

- [ ] **Step 2: Create resolution form**

`invoice-exception-resolution-form.tsx`:

```tsx
"use client";

import { Button } from "@cognify/ui/button";
import { Label } from "@cognify/ui/label";
import { RadioGroup, RadioGroupItem } from "@cognify/ui/radio-group";
import { Textarea } from "@cognify/ui/textarea";
import { Input } from "@cognify/ui/input";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@cognify/ui/sheet";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";

interface InvoiceExceptionResolutionFormProps {
  exception: SupplierInvoiceException;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (data: {
    lockVersion: number;
    resolutionType: "value_adjustment" | "explanation";
    adjustedValue?: string;
    explanation?: string;
  }) => void;
  isPending: boolean;
}

export function InvoiceExceptionResolutionForm({
  exception,
  open,
  onOpenChange,
  onSubmit,
  isPending,
}: InvoiceExceptionResolutionFormProps) {
  const [resolutionType, setResolutionType] = useState<"value_adjustment" | "explanation">("explanation");
  const [adjustedValue, setAdjustedValue] = useState(exception.actualValue ?? "");
  const [explanation, setExplanation] = useState("");

  const handleSubmit = () => {
    onSubmit({
      lockVersion: exception.lockVersion,
      resolutionType,
      ...(resolutionType === "value_adjustment" ? { adjustedValue } : {}),
      ...(explanation.trim() ? { explanation: explanation.trim() } : {}),
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Resolve exception</SheetTitle>
          <SheetDescription>
            <span className="font-medium">{exception.dimension}</span> —{" "}
            expected {exception.expectedValue}, actual {exception.actualValue}
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 py-4">
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Status:</span>
            <InvoiceExceptionStatusBadge status={exception.status} />
          </div>

          <RadioGroup
            value={resolutionType}
            onValueChange={(v) => setResolutionType(v as "value_adjustment" | "explanation")}
          >
            <div className="flex items-center gap-2">
              <RadioGroupItem value="explanation" id="explanation" />
              <Label htmlFor="explanation">Explanation (waive variance)</Label>
            </div>
            <div className="flex items-center gap-2">
              <RadioGroupItem value="value_adjustment" id="value_adjustment" />
              <Label htmlFor="value_adjustment">Value adjustment (propose payment overlay)</Label>
            </div>
          </RadioGroup>

          {resolutionType === "value_adjustment" && (
            <div className="space-y-2">
              <Label htmlFor="adjustedValue">Adjusted value</Label>
              <Input
                id="adjustedValue"
                type="text"
                value={adjustedValue}
                onChange={(e) => setAdjustedValue(e.target.value)}
              />
            </div>
          )}

          <div className="space-y-2">
            <Label htmlFor="explanation">Explanation notes</Label>
            <Textarea
              id="explanation"
              value={explanation}
              onChange={(e) => setExplanation(e.target.value)}
              placeholder="Why is this variance acceptable?"
              rows={3}
            />
          </div>
        </div>

        <SheetFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending ? "Submitting..." : "Submit resolution"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
```

- [ ] **Step 3: Create exception panel**

`invoice-exception-panel.tsx`:

```tsx
"use client";

import { Alert, AlertDescription, AlertTitle } from "@cognify/ui/alert";
import { Button } from "@cognify/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui/table";
import { AlertCircle, CheckCircle2, ArrowUpCircle } from "lucide-react";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { useInvoiceExceptions, useResolveException, useEscalateException } from "../hooks/use-invoice-exceptions";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";
import { InvoiceExceptionResolutionForm } from "./invoice-exception-resolution-form";
import { InvoiceExceptionEscalateForm } from "./invoice-exception-escalate-form";

interface InvoiceExceptionPanelProps {
  supplierInvoiceId: string;
}

export function InvoiceExceptionPanel({ supplierInvoiceId }: InvoiceExceptionPanelProps) {
  const { data: exceptions, isLoading, error } = useInvoiceExceptions(supplierInvoiceId);
  const resolveMutation = useResolveException(supplierInvoiceId);
  const escalateMutation = useEscalateException(supplierInvoiceId);
  const [selectedException, setSelectedException] = useState<SupplierInvoiceException | null>(null);
  const [escalatingException, setEscalatingException] = useState<SupplierInvoiceException | null>(null);

  if (isLoading) return <div className="text-sm text-muted-foreground">Loading exceptions...</div>;
  if (error) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Error</AlertTitle>
        <AlertDescription>Failed to load exceptions.</AlertDescription>
      </Alert>
    );
  }
  if (!exceptions?.length) {
    return <div className="text-sm text-muted-foreground">No exceptions found.</div>;
  }

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Invoice exceptions ({exceptions.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Dimension</TableHead>
                <TableHead>Expected</TableHead>
                <TableHead>Actual</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {exceptions.map((exc) => (
                <TableRow key={exc.id}>
                  <TableCell className="font-medium">{exc.dimension}</TableCell>
                  <TableCell>{exc.expectedValue ?? "—"}</TableCell>
                  <TableCell>{exc.actualValue ?? "—"}</TableCell>
                  <TableCell>
                    <InvoiceExceptionStatusBadge status={exc.status} />
                  </TableCell>
                  <TableCell className="text-right">
                    {exc.status === "open" && (
                      <div className="flex justify-end gap-2">
                        <Button
                          size="sm"
                          variant="outline"
                          onClick={() => setEscalatingException(exc)}
                        >
                          Escalate
                        </Button>
                        <Button
                          size="sm"
                          onClick={() => setSelectedException(exc)}
                        >
                          Resolve
                        </Button>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {selectedException && (
        <InvoiceExceptionResolutionForm
          exception={selectedException}
          open={!!selectedException}
          onOpenChange={() => setSelectedException(null)}
          onSubmit={(data) => {
            resolveMutation.mutate(
              { exceptionId: selectedException.id, payload: data },
              { onSuccess: () => setSelectedException(null) },
            );
          }}
          isPending={resolveMutation.isPending}
        />
      )}

      {escalatingException && (
        <InvoiceExceptionEscalateForm
          exception={escalatingException}
          open={!!escalatingException}
          onOpenChange={() => setEscalatingException(null)}
          onSubmit={(data) => {
            escalateMutation.mutate(
              { exceptionId: escalatingException.id, payload: data },
              { onSuccess: () => setEscalatingException(null) },
            );
          }}
          isPending={escalateMutation.isPending}
        />
      )}
    </>
  );
}
```

- [ ] **Step 4: Create escalation form**

`invoice-exception-escalate-form.tsx`:

```tsx
"use client";

import { Button } from "@cognify/ui/button";
import { Label } from "@cognify/ui/label";
import { Textarea } from "@cognify/ui/textarea";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@cognify/ui/sheet";
import { useState } from "react";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";
import { InvoiceExceptionStatusBadge } from "./invoice-exception-status-badge";

interface InvoiceExceptionEscalateFormProps {
  exception: SupplierInvoiceException;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (data: {
    lockVersion: number;
    escalatedToUserId: string;
    note?: string;
  }) => void;
  isPending: boolean;
}

export function InvoiceExceptionEscalateForm({
  exception,
  open,
  onOpenChange,
  onSubmit,
  isPending,
}: InvoiceExceptionEscalateFormProps) {
  const [note, setNote] = useState("");
  // Note: In a real implementation, escalatedToUserId would be a user picker
  // For now, we hardcode escalation to the procurement manager
  const escalatedToUserId = "procurement-manager-id";

  const handleSubmit = () => {
    onSubmit({
      lockVersion: exception.lockVersion,
      escalatedToUserId,
      ...(note.trim() ? { note: note.trim() } : {}),
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Escalate exception</SheetTitle>
          <SheetDescription>
            <span className="font-medium">{exception.dimension}</span> —{" "}
            expected {exception.expectedValue}, actual {exception.actualValue}
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 py-4">
          <div className="flex items-center gap-2">
            <span className="text-sm text-muted-foreground">Status:</span>
            <InvoiceExceptionStatusBadge status={exception.status} />
          </div>

          <div className="rounded-md bg-muted p-3 text-sm">
            Escalation transfers ownership to the selected user. Only they can resolve the exception after escalation.
          </div>

          <div className="space-y-2">
            <Label htmlFor="escalationNote">Escalation note</Label>
            <Textarea
              id="escalationNote"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Why does this need escalation?"
              rows={3}
            />
          </div>
        </div>

        <SheetFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button onClick={handleSubmit} disabled={isPending}>
            {isPending ? "Escalating..." : "Confirm escalation"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
```

- [ ] **Step 5: Create resolved payment summary component**

`invoice-resolved-payment-summary.tsx`:

```tsx
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui/card";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

interface InvoiceResolvedPaymentSummaryProps {
  exceptions: SupplierInvoiceException[];
  invoiceTotal: string;
}

export function InvoiceResolvedPaymentSummary({
  exceptions,
  invoiceTotal,
}: InvoiceResolvedPaymentSummaryProps) {
  const adjustments = exceptions.filter(
    (e) => e.status === "resolved" && e.resolutionType === "value_adjustment" && e.resolutionData?.adjusted_value,
  );
  const adjustmentTotal = adjustments.reduce((sum, e) => {
    return sum + parseFloat(e.resolutionData!.adjusted_value as string);
  }, 0);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-lg">Payment summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2 text-sm">
        <div className="flex justify-between">
          <span>Invoice total</span>
          <span>{invoiceTotal}</span>
        </div>
        {adjustments.length > 0 && (
          <div className="flex justify-between text-muted-foreground">
            <span>Adjustments ({adjustments.length})</span>
            <span>{adjustmentTotal.toFixed(4)}</span>
          </div>
        )}
        <div className="flex justify-between font-medium border-t pt-2">
          <span>Proposed payment</span>
          <span>{(parseFloat(invoiceTotal) + adjustmentTotal).toFixed(4)}</span>
        </div>
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 6: Update match results panel**

Modify `invoice-match-results-panel.tsx`:

In the mismatch section, add a button/message that links to the exception panel. The exact integration depends on how the panel is used — if the match results panel is embedded in the review panel, we add an "Exceptions" sub-section or a "View and resolve exceptions" button that expands the exception panel.

The simplest integration: add a card or section in the match results panel that conditionally renders the `InvoiceExceptionPanel` when status is `mismatch`.

```tsx
// Inside invoice-match-results-panel.tsx, in the mismatch rendering:
import { InvoiceExceptionPanel } from "./invoice-exception-panel";

// After the match results table and before run-matching button, add:
{invoice.status === "mismatch" && (
  <div className="mt-4">
    <InvoiceExceptionPanel supplierInvoiceId={invoice.id} />
  </div>
)}
```

- [ ] **Step 7: Run web tests and confirm green**

```bash
pnpm --filter @cognify/web test -- invoice-exception-workflow
```

Expected: tests pass.

- [ ] **Step 8: Run full typecheck**

```bash
pnpm typecheck
```

- [ ] **Step 9: Commit web UI components**

```bash
git add apps/web/features/accounts-payable
git commit -m "feat: add invoice exception workflow UI components"
```

---

## Task 7: Demo Data and Integration Verification

**Files:**
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`

- [ ] **Step 1: Seed mismatch invoice with exceptions**

In `DemoProcurementLifecycleSeeder.php`, add a mismatch scenario:
1. Capture an invoice with price/quantity different from PO
2. Complete review
3. Create match results with fail statuses
4. Set `matching_status` to `mismatch` and `status` to `mismatch`
5. Create exception records via `CreateExceptionsFromMatchResults`

- [ ] **Step 2: Assert exception data in DemoSeederTest**

```php
public function test_demo_seeds_invoice_exceptions(): void
{
    $invoice = SupplierInvoice::query()
        ->where('matching_status', SupplierInvoiceStatus::Mismatch->value)
        ->first();

    $this->assertNotNull($invoice);
    $this->assertNotNull($invoice->exception_summary);
    $this->assertGreaterThan(0, $invoice->exception_summary['total']);

    $exceptions = $invoice->exceptions;
    $this->assertNotEmpty($exceptions);
    $this->assertEquals('open', $exceptions->first()->status);
}
```

- [ ] **Step 3: Run full test suite**

```bash
cd apps/api
php artisan test
```

Expected: all tests pass.

```bash
pnpm test
```

Expected: all tests pass.

- [ ] **Step 4: Commit demo data updates**

```bash
git add apps/api/database/seeders apps/api/tests
git commit -m "feat: seed demo mismatch invoice with exceptions"
```

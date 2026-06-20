# PR Review Fixes — Accounts Payable

## TL;DR

> **Quick Summary**: Address 4 of 5 PR review comments (1 skipped — see rationale). Fixes float monetary math, conditional tenant header, and field-scoped validation exception.
>
> **Deliverables**:
> - Fix float-based monetary accumulation in `CreateApPaymentHandoff.php` → use `bcadd` string arithmetic
> - Fix float-cast monetary sum in `RemoveApPaymentHandoffInvoice.php` → use `bcadd` via `reduce`
> - Fix hard-fail `withActiveTenantHeader` in `api-helpers.ts` → return `undefined` when no tenant
> - Replace `abort(422)` with `ValidationException::withMessages` in `ApPaymentHandoffController.php`
>
> **Estimated Effort**: Quick
> **Parallel Execution**: YES - 4 tasks, 4 waves (all independent, can run fully in parallel)
> **Critical Path**: All tasks independent → Final validation

---

## Context

### Original Request
Address PR review inline comments about floating-point monetary drift, a hand-authored TypeScript type, a hard-fail tenant header, and a generic `abort(422)`.

### Skipped Item

**`ApPaymentHandoffJsonExport` type in `accounts-payable-handoff-api.ts`**: The PR comment asks to replace the hand-authored type with the generated type from `@cognify/api-client`. However, the generated types are:

```typescript
// ExportApPaymentHandoffJson200.ts
export type ExportApPaymentHandoffJson200 = { [key: string]: unknown };

// RecordApPaymentHandoffJsonExport200.ts
export type RecordApPaymentHandoffJsonExport200 = { [key: string]: unknown };
```

These provide **zero type safety** — they accept any object. The hand-authored `ApPaymentHandoffJsonExport` type has specific fields (`exportedAt`, `format`, `handoff`, `handoff.invoices`, etc.) and is more precise. Replacing with `{ [key: string]: unknown }` would be a type-safety regression. The correct fix is to update the OpenAPI spec to define the response schema, then regenerate — that's a separate task outside this PR review scope.

---

## Work Objectives

### Core Objective
Apply targeted, minimal fixes to 4 files addressing specific PR review comments.

### Concrete Deliverables
- `CreateApPaymentHandoff.php` — `bcadd` string arithmetic for monetary totals
- `RemoveApPaymentHandoffInvoice.php` — `bcadd` via `reduce` for monetary totals
- `api-helpers.ts` — conditional `RequestInit | undefined` return
- `ApPaymentHandoffController.php` — `ValidationException::withMessages` for invoice validation

### Definition of Done
- [ ] All 4 files modified with exact edits specified below
- [ ] `pnpm typecheck` passes (web)
- [ ] No PHP syntax errors in modified files

### Must Have
- Exact edits as specified — no scope creep, no additional refactoring

### Must NOT Have (Guardrails)
- Do NOT replace `ApPaymentHandoffJsonExport` with `{ [key: string]: unknown }` — that's a regression
- Do NOT change any other files
- Do NOT refactor surrounding code beyond the specified changes

---

## Verification Strategy

### Test Decision
- **Infrastructure exists**: YES (PHPUnit + Vitest)
- **Automated tests**: Tests-after (existing tests should still pass)
- **Framework**: PHPUnit / Vitest

### QA Policy
Run existing test suites after changes. No new tests needed — these are precision fixes with existing coverage.

---

## Execution Strategy

### Parallel Execution Waves

```
Wave 1 (All independent - run in parallel):
├── Task 1: Fix CreateApPaymentHandoff monetary math
├── Task 2: Fix RemoveApPaymentHandoffInvoice monetary math
├── Task 3: Fix withActiveTenantHeader conditional return
└── Task 4: Fix ApPaymentHandoffController ValidationException

Wave FINAL:
└── Task F1: Validation — typecheck + lint + test
```

---

## TODOs

- [ ] 1. Fix CreateApPaymentHandoff monetary math

  **What to do**:
  In `apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php`, make these exact changes:

  **Change A** (line 84) — Replace float `array_reduce` with `bcadd` string accumulation:
  ```php
  // BEFORE:
  $totalAmount = array_reduce($lockedInvoices, fn (float $carry, SupplierInvoice $invoice): float => $carry + (float) ($invoice->total_amount ?? 0), 0.0);

  // AFTER:
  $totalAmount = array_reduce($lockedInvoices, fn (string $carry, SupplierInvoice $invoice): string => bcadd($carry, (string) ($invoice->total_amount ?? '0'), 4), '0');
  ```

  **Change B** (line 88) — Remove redundant `(string)` cast (value is already a string):
  ```php
  // BEFORE:
  'totalAmount' => (string) $totalAmount,

  // AFTER:
  'totalAmount' => $totalAmount,
  ```

  **Change C** (line 117) — Remove redundant `(string)` cast in audit metadata:
  ```php
  // BEFORE:
  'totalAmount' => (string) $totalAmount,

  // AFTER:
  'totalAmount' => $totalAmount,
  ```

  Note: Line 99 `'total_amount' => $totalAmount,` needs no change — Eloquent casts string to decimal column type automatically.

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 2, 3, 4)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php` — the file to edit
  - `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php` — `bcadd`/`bcmul` pattern reference
  - `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php` — `bcadd` pattern reference

  **Acceptance Criteria**:
  - [ ] `$totalAmount` uses `bcadd` string accumulation instead of float addition
  - [ ] Redundant `(string)` casts removed from lines 88 and 117
  - [ ] No PHP syntax errors

  **QA Scenarios**:
  ```
  Scenario: PHP syntax valid
    Tool: Bash
    Steps:
      1. cd apps/api && php -l Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php
    Expected Result: "No syntax errors detected"
    Evidence: .sisyphus/evidence/task-1-php-syntax.txt
  ```

  **Commit**: YES (groups with Task 2)
  - Message: `fix(ap): use bcadd string arithmetic for monetary totals`
  - Files: `apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php`, `apps/api/Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php`

---

- [ ] 2. Fix RemoveApPaymentHandoffInvoice monetary math

  **What to do**:
  In `apps/api/Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php`, make these exact changes:

  **Change A** (line 42) — Replace `(float)` cast + `->sum()` with `bcadd` via `->reduce()`:
  ```php
  // BEFORE:
  $totalAmount = $remainingInvoices->sum(fn ($inv) => (float) ($inv->total_amount ?? 0));

  // AFTER:
  $totalAmount = $remainingInvoices->reduce(fn (string $carry, $inv): string => bcadd($carry, (string) ($inv->total_amount ?? '0'), 4), '0');
  ```

  **Change B** (line 46) — Remove redundant `(string)` cast:
  ```php
  // BEFORE:
  'totalAmount' => (string) $totalAmount,

  // AFTER:
  'totalAmount' => $totalAmount,
  ```

  Note: Line 53 `'total_amount' => $totalAmount,` needs no change — Eloquent handles string-to-decimal.

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 3, 4)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `apps/api/Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php` — the file to edit
  - `apps/api/Domains/Fulfillment/Support/DeliveryStatusCalculator.php` — `bcadd` via Collection reduce pattern

  **Acceptance Criteria**:
  - [ ] `$totalAmount` uses `bcadd` string accumulation instead of float sum
  - [ ] Redundant `(string)` cast removed from line 46
  - [ ] No PHP syntax errors

  **QA Scenarios**:
  ```
  Scenario: PHP syntax valid
    Tool: Bash
    Steps:
      1. cd apps/api && php -l Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php
    Expected Result: "No syntax errors detected"
    Evidence: .sisyphus/evidence/task-2-php-syntax.txt
  ```

  **Commit**: YES (groups with Task 1)
  - Message: `fix(ap): use bcadd string arithmetic for monetary totals`
  - Files: `apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php`, `apps/api/Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php`

---

- [ ] 3. Fix withActiveTenantHeader conditional return

  **What to do**:
  In `apps/web/features/accounts-payable/api/api-helpers.ts`, change `withActiveTenantHeader` to return `undefined` instead of throwing when `tenantId` is missing, matching the patterns in `requisitions-api.ts` (line 239-248) and `audit-api.ts` (line 11-19).

  **Change**: Replace the entire function (lines 3-13):
  ```typescript
  // BEFORE:
  export function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit {
    if (!tenantId) {
      throw new Error("Missing active tenant context");
    }

    return {
      headers: {
        "X-Tenant-Id": tenantId,
      },
    };
  }

  // AFTER:
  export function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
    if (!tenantId) {
      return undefined;
    }

    return {
      headers: {
        "X-Tenant-Id": tenantId,
      },
    };
  }
  ```

  Only two changes: return type `RequestInit` → `RequestInit | undefined`, and `throw new Error(...)` → `return undefined`.

  **Must NOT do**:
  - Do NOT change any call sites in `accounts-payable-handoff-api.ts` — the generated API client accepts `RequestInit | undefined`

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 4)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `apps/web/features/accounts-payable/api/api-helpers.ts` — the file to edit
  - `apps/web/features/requisitions/api/requisitions-api.ts:239-248` — reference implementation of conditional `withActiveTenantHeader`
  - `apps/web/features/audit/api/audit-api.ts:11-19` — another reference implementation

  **Acceptance Criteria**:
  - [ ] Return type changed to `RequestInit | undefined`
  - [ ] `throw new Error(...)` replaced with `return undefined`
  - [ ] `pnpm typecheck` passes
  - [ ] Call sites in `accounts-payable-handoff-api.ts` unchanged

  **QA Scenarios**:
  ```
  Scenario: TypeScript compiles
    Tool: Bash
    Steps:
      1. cd /home/leonidas/dev/cognify && pnpm --filter @cognify/web typecheck
    Expected Result: Exit code 0, no type errors
    Evidence: .sisyphus/evidence/task-3-typecheck.txt
  ```

  **Commit**: YES
  - Message: `fix(ap): return undefined instead of throwing when tenantId missing`
  - Files: `apps/web/features/accounts-payable/api/api-helpers.ts`

---

- [ ] 4. Fix ApPaymentHandoffController ValidationException

  **What to do**:
  In `apps/api/Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php`, make two changes:

  **Change A** — Add import for `ValidationException` (after line 24, the `use Illuminate\Http\Response;` import):
  ```php
  use Illuminate\Validation\ValidationException;
  ```

  **Change B** — Replace `abort(422, ...)` at lines 72-74 with a field-scoped `ValidationException`:
  ```php
  // BEFORE:
  if ($invoices->count() !== count($invoiceIds)) {
      abort(422, 'One or more invoices were not found or do not belong to the current tenant.');
  }

  // AFTER:
  if ($invoices->count() !== count($invoiceIds)) {
      throw ValidationException::withMessages([
          'invoiceIds' => 'One or more invoices were not found or do not belong to the current tenant.',
      ]);
  }
  ```

  This matches the pattern used in `ShipmentController.php` (line 55) and `GoodsReceiptController.php` (line 54).

  **Recommended Agent Profile**:
  - **Category**: `quick`
  - **Skills**: []

  **Parallelization**:
  - **Can Run In Parallel**: YES
  - **Parallel Group**: Wave 1 (with Tasks 1, 2, 3)
  - **Blocks**: None
  - **Blocked By**: None

  **References**:
  - `apps/api/Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php` — the file to edit
  - `apps/api/Domains/Fulfillment/Http/Controllers/ShipmentController.php:55` — reference `ValidationException::withMessages` pattern
  - `apps/api/Domains/Receiving/Http/Controllers/GoodsReceiptController.php:54` — reference pattern

  **Acceptance Criteria**:
  - [ ] `ValidationException` import added
  - [ ] `abort(422, ...)` replaced with `throw ValidationException::withMessages(['invoiceIds' => ...])`
  - [ ] No PHP syntax errors

  **QA Scenarios**:
  ```
  Scenario: PHP syntax valid
    Tool: Bash
    Steps:
      1. cd apps/api && php -l Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php
    Expected Result: "No syntax errors detected"
    Evidence: .sisyphus/evidence/task-4-php-syntax.txt
  ```

  **Commit**: YES
  - Message: `fix(ap): use ValidationException instead of abort(422) for invoice validation`
  - Files: `apps/api/Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php`

---

## Final Verification Wave

- [ ] F1. **Typecheck + Lint + Test** — `unspecified-high`
  Run `pnpm typecheck` and `pnpm lint` from repo root. Run `php -l` on all 4 modified PHP/TS files. Verify no errors.
  Output: `Typecheck [PASS/FAIL] | Lint [PASS/FAIL] | PHP Syntax [PASS/FAIL] | VERDICT`

---

## Commit Strategy

- **Task 1+2**: `fix(ap): use bcadd string arithmetic for monetary totals` — CreateApPaymentHandoff.php, RemoveApPaymentHandoffInvoice.php
- **Task 3**: `fix(ap): return undefined instead of throwing when tenantId missing` — api-helpers.ts
- **Task 4**: `fix(ap): use ValidationException instead of abort(422) for invoice validation` — ApPaymentHandoffController.php

---

## Success Criteria

### Verification Commands
```bash
php -l apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php
php -l apps/api/Domains/AccountsPayable/Actions/RemoveApPaymentHandoffInvoice.php
php -l apps/api/Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php
pnpm --filter @cognify/web typecheck
```

### Final Checklist
- [ ] All "Must Have" present
- [ ] All "Must NOT Have" absent
- [ ] Existing tests pass
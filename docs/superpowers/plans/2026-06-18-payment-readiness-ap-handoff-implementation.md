# Payment Readiness and AP Handoff Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend approved supplier invoices with payment readiness states (payment_eligible, on_hold, payment_ready, handoff_exported) and create a snapshot-based ApPaymentHandoff export package following the PurchaseOrderRequestHandoff pattern, with JSON/CSV export, hold/release workflow, dynamic snapshot (recalculable in draft, locks at ready), and frontend queue integration.

**Architecture:** New `Domains/AccountsPayable` backend domain. `payment_status` as separate column on `SupplierInvoice`. `ApPaymentHandoff` snapshot model with Draft→Ready→Exported→Cancelled states, lock-version concurrency. Currency homogeneity enforced at handoff creation. Ghost-approved recovery via manual retry action.

**Tech Stack:** PHP 8.3 / Laravel, PostgreSQL, Next.js 15 / React 19, TanStack Query, shadcn/ui, Orval-generated TypeScript client.

**Reference pattern:** `PurchaseOrderRequestHandoff` at `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderRequestHandoff.php` and associated actions/states/tests.

---

## File Structure Map

### Backend — Create (29 files)
`apps/api/Domains/AccountsPayable/` with States/, Models/, Support/, Data/, Actions/, Policies/, Http/Controllers/, Http/Requests/, Http/Resources/, routes/ subdirectories, plus 3 migration files. See each task for exact paths.

### Backend — Modify (6 files)
- `SupplierInvoice.php` — add casts, fillable, relationship, booted validation
- `MarkSupplierInvoiceApproved.php` — auto-advance call
- `EvaluateStraightThroughProcessing.php` — auto-advance call
- `SupplierInvoicePolicy.php` — hold/release/retry gates
- `SupplierInvoiceQueueResource.php` — payment fields
- `routes/api.php` — include AccountsPayable routes + payment endpoints

### OpenAPI (1 file)
- `openapi.json` — 12 new schemas, 12 new paths

### Frontend — Create (11 files)
API wrappers, hooks, components (badge, hold panel, handoff create dialog, workspace), MSW fixtures/handlers, test file

### Frontend — Modify (4 files)
- `accounts-payable-invoice-queue-page.tsx` — payment tabs, awaiting-induction badge
- `accounts-payable-invoice-queue-table.tsx` — paymentStatus column
- `navigation.tsx` — Payment queue sidebar item
- `tests/msw/handlers.ts`, `tests/setup.ts` — register new handlers

### Tests (2 backend test files)
- `SupplierInvoicePaymentApiTest.php` — 8 tests
- `ApPaymentHandoffApiTest.php` — 11 tests

---

## Task 1: Payment Status Migration

[x] **Done** — Migration file created with payment_status, payment_eligible_at, hold/release columns, indexes.

**Files:**
- Create: `apps/api/database/migrations/2026_06_18_000001_add_payment_status_to_supplier_invoices.php`

Run: `php artisan make:migration add_payment_status_to_supplier_invoices --table=supplier_invoices`

```php
// database/migrations/2026_06_18_000001_add_payment_status_to_supplier_invoices.php
public function up(): void
{
    Schema::table('supplier_invoices', function (Blueprint $table): void {
        $table->string('payment_status', 50)->nullable()->after('stp_processed_at');
        $table->timestamp('payment_eligible_at')->nullable()->after('payment_status');
        $table->foreignId('payment_on_hold_by_user_id')->nullable()
            ->constrained('users')->nullOnDelete();
        $table->timestamp('payment_on_hold_at')->nullable();
        $table->text('payment_on_hold_reason')->nullable();
        $table->foreignId('payment_hold_released_by_user_id')->nullable()
            ->constrained('users')->nullOnDelete();
        $table->timestamp('payment_hold_released_at')->nullable();
        $table->text('payment_hold_released_note')->nullable();
        $table->index(['tenant_id', 'payment_status'], 'si_tenant_payment_status_idx');
        $table->index(['tenant_id', 'payment_status', 'due_date'], 'si_tenant_payment_status_due_idx');
    });
}
public function down(): void
{
    Schema::table('supplier_invoices', function (Blueprint $table): void {
        $table->dropIndex('si_tenant_payment_status_due_idx');
        $table->dropIndex('si_tenant_payment_status_idx');
        $table->dropColumn(['payment_status','payment_eligible_at','payment_on_hold_by_user_id',
            'payment_on_hold_at','payment_on_hold_reason','payment_hold_released_by_user_id',
            'payment_hold_released_at','payment_hold_released_note']);
    });
}
```

## Task 2: Handoff Tables Migration

[x] **Done** — Both migrations created. `ap_payment_handoffs` with full column set, `ap_payment_handoff_invoice` pivot with cascade/restrict.

**Files:**
- Create: `apps/api/database/migrations/2026_06_18_000002_create_ap_payment_handoffs_table.php`
- Create: `apps/api/database/migrations/2026_06_18_000003_create_ap_payment_handoff_invoice_table.php`

`ap_payment_handoffs` — uuid PK, tenant_id FK, number (unique per tenant), status (default draft), effective_payment_date, notes, currency, total_amount (decimal 18,4), remittance_reference, created_by_user_id, ready_by_user_id, ready_at, cancelled_by_user_id/at/reason, last_exported_by_user_id/at/format, snapshot (json), readiness_warnings (json), lock_version (unsigned, default 1), timestamps.

`ap_payment_handoff_invoice` — uuid PK, tenant_id FK, ap_payment_handoff_id FK (cascade delete), supplier_invoice_id FK (restrict delete), timestamps. Unique on (handoff_id, invoice_id).

## Task 3: Payment Status and Handoff Status Enums

[x] **Done** — Both enums created with `label()`, `isEligibleForHandoff()`/`isTerminal()` methods.

**Files:**
- Create: `apps/api/Domains/AccountsPayable/States/SupplierInvoicePaymentStatus.php`
- Create: `apps/api/Domains/AccountsPayable/States/ApPaymentHandoffStatus.php`

`SupplierInvoicePaymentStatus`: string enum with cases `PaymentEligible`, `OnHold`, `PaymentReady`, `HandoffExported`. Methods: `label()`, `isEligibleForHandoff()`, `isTerminal()`.

`ApPaymentHandoffStatus`: string enum with cases `Draft`, `Ready`, `Exported`, `Cancelled`. Methods: `label()`, `isTerminal()`, `canTransitionTo(target)`.

## Task 4: ApPaymentHandoff Models

[x] **Done** — Both models created. SupplierInvoice updated with casts, fillable, and `activeHandoff()` BelongsToMany.

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Models/ApPaymentHandoff.php`
- Create: `apps/api/Domains/AccountsPayable/Models/ApPaymentHandoffInvoice.php`
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoice.php`

**ApPaymentHandoff**: HasUuids, non-incrementing. `$casts`: status→ApPaymentHandoffStatus, snapshot/readiness_warnings→array, lock_version→integer. `$fillable`: all columns. Relationships: tenant(), invoices() (BelongsToMany via pivot), createdByUser(), readyByUser(), cancelledByUser(), lastExportedByUser(). Methods: assertLockVersion(int). `booted()::saving` validates user FKs belong to tenant.

**ApPaymentHandoffInvoice**: HasUuids pivot. BelongsTo handoff, invoice.

**SupplierInvoice additions:**
- `$casts`: `payment_status => SupplierInvoicePaymentStatus`, `payment_eligible_at => datetime`, `payment_on_hold_at => datetime`, `payment_hold_released_at => datetime`
- `$fillable`: payment_status, payment_eligible_at, payment_on_hold_by_user_id, payment_on_hold_at, payment_on_hold_reason, payment_hold_released_by_user_id, payment_hold_released_at, payment_hold_released_note
- Relationship: `activeHandoff()` — BelongsToMany via pivot where handoff status IN (draft, ready, exported)
- Import `App\Domains\AccountsPayable\Models\ApPaymentHandoff` and `App\Domains\AccountsPayable\States\ApPaymentHandoffStatus`

## Task 5: Handoff Number Generator

[x] **Done** — Created per spec. Format: `APH-{YYYY}-{000001}`.

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Support/ApPaymentHandoffNumber.php`

```php
class ApPaymentHandoffNumber
{
    public function generate(int $tenantId): string
    {
        $year = now()->format('Y');
        Tenant::query()->whereKey($tenantId)->lockForUpdate()->exists();
        $lastNumber = DB::table('ap_payment_handoffs')
            ->where('tenant_id', $tenantId)
            ->where('number', 'like', "APH-{$year}-%")
            ->orderBy('number', 'desc')->value('number');
        if ($lastNumber === null) return "APH-{$year}-000001";
        $parts = explode('-', $lastNumber);
        $counter = (int) end($parts);
        return "APH-{$year}-" . str_pad((string) ($counter + 1), 6, '0', STR_PAD_LEFT);
    }
}
```

## Task 6: Snapshot Data DTO

[x] **Done** — Created with `handoff`, `invoices`, `totalByCurrency`, `readinessWarnings` fields and `toArray()`.

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Data/ApPaymentHandoffSnapshotData.php`

Typed DTO with `handoff[]`, `invoices[]`, `totalByCurrency[]`, `readinessWarnings[]`. Method `toArray()`.

## Task 7: Auto-Advance, Hold/Release, and Retry Actions

[x] **Done** — All 4 actions created. Named differently from plan (see deviations in Handoff section below).

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Actions/AutoAdvanceToPaymentEligible.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/RetryPaymentInduction.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/PlaceSupplierInvoiceOnPaymentHold.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/ReleaseSupplierInvoicePaymentHold.php`

All follow the established approval action pattern:
1. `lockForUpdate()` to re-fetch fresh
2. Status guard (check current payment_status matches expected)
3. `assertLockVersion($lockVersion)` → 409 on conflict
4. Capture `$before = $invoice->only([...])`
5. `forceFill([new values, lock_version+1])` + save
6. `auditRecorder->record(new AuditEventData(...))`

**AutoAdvanceToPaymentEligible**: Idempotent. Only runs if `payment_status = null` and `status = approved`. Sets payment_status=payment_eligible, payment_eligible_at=now. No lockVersion check needed (runs inside approval transaction, invoice already locked). Actor is null (system action).

**RetryPaymentInduction**: Manual human retry for ghost-approved edge case. Requires lockVersion. Validates `status = approved` and `payment_status = null`. Same mutation logic as AutoAdvance. Records audit with `'retryReason' => 'manual_retry_induction'`.

**PlaceSupplierInvoiceOnPaymentHold**: Validates `payment_status = payment_eligible`. Sets `payment_status = on_hold`, hold reason/timestamp/user. Requires lockVersion + reason string.

**ReleaseSupplierInvoicePaymentHold**: Validates `payment_status = on_hold`. Sets `payment_status = payment_eligible`, sets release note/timestamp/user. Requires lockVersion + releaseNote string.

## Task 8: Snapshot Builder, Create, Refresh, MarkReady, Export, Cancel Actions

[x] **Done** — All 6 actions created. Additional `UpdateApPaymentHandoff` and `RemoveApPaymentHandoffInvoice` actions added beyond plan scope. MarkReady recalculate snapshot + warnings-are-advisory (see deviations).

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Actions/BuildApPaymentHandoffSnapshot.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/CreateApPaymentHandoff.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/RefreshApPaymentHandoffSnapshot.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/MarkApPaymentHandoffReady.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/ExportApPaymentHandoff.php`
- Create: `apps/api/Domains/AccountsPayable/Actions/CancelApPaymentHandoff.php`

**BuildApPaymentHandoffSnapshot**: Accepts Collection of invoices (eager-loaded with vendor, PO, lines). Assembles snapshot array with handoff meta, invoice data, vendor, PO, lines, approval info. Calculates `readinessWarnings` (missing tax ID, no payment terms, no due date). Returns `ApPaymentHandoffSnapshotData`.

**CreateApPaymentHandoff**: Transactional. (1) Lock + fetch invoices, validate all exist. (2) Validate all `payment_status = payment_eligible`. (3) Validate none in active handoff. (4) **Currency homogeneity check** — distinct currencies > 1 → 422. (5) Create handoff, generate number, build initial snapshot. (6) Create pivot records, update invoices to `payment_ready`. (7) Record `ap_payment_handoff.created` audit.

**RefreshApPaymentHandoffSnapshot**: Only allowed in `draft`. Reloads invoices with relations, rebuilds snapshot, overwrites column. Records `ap_payment_handoff.snapshot_refreshed` audit.

**MarkApPaymentHandoffReady**: Transactional with lock. Validates `draft` status + lockVersion. Validates at least one invoice exists. **Recalculates snapshot one final time** by calling BuildApPaymentHandoffSnapshot with fresh data, then locks. Sets status=ready, ready_by_user_id, ready_at. Records `ap_payment_handoff.ready` audit.

**ExportApPaymentHandoff**: Transactional with lock. Validates `ready` or `exported`. For ready→exported: updates invoices to `handoff_exported`, sets export metadata. Returns StreamedResponse (JSON or CSV). JSON: `{exportedAt, format, handoff:{snapshot}}`. CSV: BOM + header row + one row per invoice line item. Records `ap_payment_handoff.exported` audit.

**CancelApPaymentHandoff**: Transactional with lock. Validates not already cancelled. Returns invoices to `payment_eligible`. Sets status=cancelled with reason/timestamp. Records `ap_payment_handoff.cancelled` audit.

## Task 9: Policies and Form Requests

[x] **Done** — Policy created, 7 form requests created (6 spec + 1 `UpdateApPaymentHandoffRequest`), SupplierInvoicePolicy updated.

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Policies/ApPaymentHandoffPolicy.php`
- Create: 6 FormRequest classes under `Http/Requests/`
- Modify: `apps/api/Domains/Invoice/Policies/SupplierInvoicePolicy.php`

**ApPaymentHandoffPolicy**: viewAny/create → buyerOrAdmin. view/update/refresh/markReady/export/cancel → isTenantScoped + buyerOrAdmin. Uses `TenantRole::Buyer` / `TenantRole::Admin`.

**SupplierInvoicePolicy additions**: `placeHold()`, `releaseHold()`, `retryPaymentInduction()` — all check isTenantScoped + buyerOrAdmin.

**Form requests**: Each extends FormRequest with `authorize() { return true; }` (delegates to policy). Rules:
- `CreateApPaymentHandoffRequest`: invoiceIds (required, array, min:1, each exists:supplier_invoices), effectivePaymentDate (nullable, date, after_or_equal:today), notes (nullable, string, max:5000)
- `PlaceInvoiceOnHoldRequest`: reason (required, string, min:5, max:2000), lockVersion (required, integer, min:1)
- `ReleaseInvoiceHoldRequest`: releaseNote (required, string, min:5, max:2000), lockVersion (required, integer, min:1)
- `MarkApPaymentHandoffReadyRequest`: lockVersion (required, integer, min:1)
- `CancelApPaymentHandoffRequest`: reason (required, string, min:5, max:2000), lockVersion (required, integer, min:1)
- `RefreshApPaymentHandoffSnapshotRequest`: lockVersion (nullable, integer, min:1)

## Task 10: Controllers, Resources, and Routes

[x] **Done** — Both controllers, resource, routes created. Routes are inline in main `routes/api.php` rather than a separate `AccountsPayable/routes/api.php` (see deviations).

**Files:**
- Create: `apps/api/Domains/AccountsPayable/Http/Resources/ApPaymentHandoffResource.php`
- Create: `apps/api/Domains/AccountsPayable/Http/Controllers/ApPaymentHandoffController.php`
- Create: `apps/api/Domains/AccountsPayable/Http/Controllers/SupplierInvoicePaymentController.php`
- Create: `apps/api/Domains/AccountsPayable/routes/api.php`
- Modify: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceQueueResource.php`
- Modify: `apps/api/routes/api.php`

**ApPaymentHandoffResource**: Maps camelCase JSON fields: id, number, status, statusLabel, effectivePaymentDate, notes, currency, totalAmount, remittanceReference, snapshot, readinessWarnings, createdByUserId, readyByUserId, readyAt, cancelledByUserId, cancelledAt, cancelledReason, lastExportedByUserId, lastExportedAt, lastExportFormat, lockVersion, invoiceCount (when relation loaded), createdAt, updatedAt.

**ApPaymentHandoffController**: Methods: index (paginated, tenant-scoped, with invoices), show, store, update (with lockVersion), refresh, markReady, exportJson, exportCsv, cancel. Each method authorizes via policy, delegates to action, returns resource response.

**SupplierInvoicePaymentController**: Methods: placeHold, releaseHold, retryInduction. Each validates via form request, authorizes via SupplierInvoicePolicy, delegates to action, returns JSON with payment-related fields + new lockVersion.

**SupplierInvoiceQueueResource additions**: `paymentStatus`, `paymentStatusLabel`, `paymentOnHoldReason`, `paymentEligibleAt`, `paymentOnHoldAt`, `paymentOnHoldByUserId`, `activeHandoffId`, `activeHandoffNumber`.

**Routes** (`Domains/AccountsPayable/routes/api.php`):
```
GET/POST /api/accounts-payable/handoffs
GET/PATCH /api/accounts-payable/handoffs/{handoff}
POST /api/accounts-payable/handoffs/{handoff}/refresh
POST /api/accounts-payable/handoffs/{handoff}/mark-ready
POST /api/accounts-payable/handoffs/{handoff}/cancel
GET /api/accounts-payable/handoffs/{handoff}/export.json
GET /api/accounts-payable/handoffs/{handoff}/export.csv
```

Payment endpoints added to existing supplier-invoices group:
```
POST /api/supplier-invoices/{supplierInvoice}/place-hold
POST /api/supplier-invoices/{supplierInvoice}/release-hold
POST /api/supplier-invoices/{supplierInvoice}/retry-payment-induction
```

Main `routes/api.php`: `require __DIR__ . '/../Domains/AccountsPayable/routes/api.php';`

## Task 11: Integration Point — Auto-Advance in Approval Actions

[x] **Done** — AutoAdvanceToPaymentEligible action created and wired into both approval actions.
- Modify: `apps/api/Domains/Invoice/Actions/MarkSupplierInvoiceApproved.php`
- Modify: `apps/api/Domains/Invoice/Actions/EvaluateStraightThroughProcessing.php`

After the invoice status is set to approved and saved, add:
```php
try {
    app(\App\Domains\AccountsPayable\Actions\AutoAdvanceToPaymentEligible::class)->execute($invoice);
} catch (\Throwable $e) {
    Log::warning('Auto-advance to payment_eligible failed', [
        'invoice_id' => (string) $invoice->id, 'error' => $e->getMessage(),
    ]);
}
```

The try/catch ensures the approval outcome is never rolled back by an auto-advance failure. The invoice remains approved with `payment_status = null`, visible in the UI as "Awaiting payment induction".

## Task 12: OpenAPI Spec Update

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

Add schemas: `SupplierInvoicePaymentStatus`, `ApPaymentHandoffStatus`, `ApPaymentHandoffSnapshot`, `PlaceInvoiceOnHoldRequest`, `ReleaseInvoiceHoldRequest`, `RetryPaymentInductionRequest`, `CreateApPaymentHandoffRequest`, `MarkApPaymentHandoffReadyRequest`, `CancelApPaymentHandoffRequest`, `ApPaymentHandoffResponse`, `ApPaymentHandoffListResponse`, `ApPaymentHandoff`.

Add 12 new paths following existing operationId convention (`{verb}{ResourceName}`):
- `placeSupplierInvoiceOnPaymentHold`, `releaseSupplierInvoicePaymentHold`, `retryPaymentInduction`
- `listApPaymentHandoffs`, `createApPaymentHandoff`, `showApPaymentHandoff`, `updateApPaymentHandoff`
- `refreshApPaymentHandoffSnapshot`, `markApPaymentHandoffReady`, `cancelApPaymentHandoff`
- `exportApPaymentHandoffJson`, `exportApPaymentHandoffCsv`

Regenerate and verify: `pnpm generate:api && pnpm check:api-contract`

[x] **Done** — JSON validated with `jq`, all 11 schemas and 6 paths (10 operations) added, SupplierInvoice schema updated with paymentStatus and paymentEligibleAt.

## Task 13: Backend Tests — Payment Status

**Files:**
- Create: `apps/api/tests/Feature/SupplierInvoicePaymentApiTest.php`

[x] **Done** — 8 tests, all passing (16 assertions). Tests: auto-advance, place hold with reason, stale lockVersion → 409, release hold with note, retry ghost-approved, retry idempotent, cross-tenant 403, audit event recorded.

## Task 14: Backend Tests — Handoff API

**Files:**
- Create: `apps/api/tests/Feature/ApPaymentHandoffApiTest.php`

[x] **Done** — 11 tests, all passing (42 assertions). Tests: create from eligible, on-hold → 409, mixed currencies → 409, active-handoff guard, snapshot data, mark ready, JSON export, CSV export, cancel returns to eligible, cross-tenant 403, non-AP-user 403.

## Task 15: Frontend — Mock Fixtures and Handlers

[x] **Done** — All fixtures and MSW handlers created.
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-payment-fixtures.ts`
- Create: `apps/web/features/accounts-payable/mocks/accounts-payable-payment-handlers.ts`
- Modify: `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-fixtures.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/tests/setup.ts`

**Payment fixtures**: 6 handoff fixtures (draft with 2 invoices, ready, exported, cancelled), payment-status-specific invoice variants (ghost approved with awaiting-induction flag, on_hold with reason).

**Handlers**: MSW handlers for all new endpoints. In-memory store with `resetAccountsPayablePaymentMockState()`. Handlers for: place-hold, release-hold, retry-induction, handoff list, create, show, update, refresh, mark-ready, export-json, export-csv, cancel.

**Existing invoice fixture additions**: Add `paymentStatus: null` and `lockVersion: 1` fields to all existing fixture invoices.

**Test setup registration**: Import `accounts-payable-payment-handlers` and register `resetAccountsPayablePaymentMockState` in the global test setup afterEach loop.

## Task 16: Frontend — Payment API and Hooks

[x] **Done** — Payment API wrappers and hooks created.
- Create: `apps/web/features/accounts-payable/api/accounts-payable-payment-api.ts`
- Create: `apps/web/features/accounts-payable/api/accounts-payable-handoff-api.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-payment-holds.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-payment-handoffs.ts`

**Payment API** (`accounts-payable-payment-api.ts`): Functions wrapping `@cognify/api-client/endpoints` for placeHold, releaseHold, retryInduction. Each reads tenant from `getStoredActiveTenantId()`, sets X-Tenant-Id header.

**Handoff API** (`accounts-payable-handoff-api.ts`): Functions for listHandoffs, createHandoff, showHandoff, updateHandoff, refreshHandoffSnapshot, markHandoffReady, cancelHandoff, exportHandoffJson, exportHandoffCsv.

**usePaymentHolds hook**: `usePlaceInvoiceHold(invoiceId)` mutation, `useReleaseInvoiceHold(invoiceId)` mutation. On success, invalidates `['supplier-invoices']` query cache.

**usePaymentHandoffs hook**: `useApPaymentHandoffs(filters)` list query, `useApPaymentHandoff(id)` detail query, `useCreateApPaymentHandoff()` mutation, `useMarkApPaymentHandoffReady()` mutation, `useCancelApPaymentHandoff()` mutation, `useRefreshApPaymentHandoffSnapshot()` mutation.

## Task 17: Frontend — Payment Status Badge and Hold Panel Components

[x] **Done** — PaymentStatusBadge and PaymentHoldPanel components created.
- Create: `apps/web/features/accounts-payable/components/payment-status-badge.tsx`
- Create: `apps/web/features/accounts-payable/components/payment-hold-panel.tsx`

**PaymentStatusBadge**: Renders a colored badge for each payment status:
- `payment_eligible` → green "Payment eligible"
- `on_hold` → amber "On hold" + tooltip with reason
- `payment_ready` → blue "Payment ready" + tooltip with handoff reference
- `handoff_exported` → gray "Exported"
- `null` (ghost approved) → red "Awaiting induction" with retry button

**PaymentHoldPanel**: Two modes:
- Display mode (on_hold): Shows who placed hold, when, and reason. "Release hold" button with form.
- Action mode (payment_eligible): "Place hold" button with reason form dialog.
Both use `usePaymentHolds` mutations, show lockVersion on stale conflict.

## Task 18: Frontend — Handoff Create Dialog and Workspace

[x] **Done** — PaymentHandoffCreateDialog and PaymentHandoffWorkspace components created.
- Create: `apps/web/features/accounts-payable/components/payment-handoff-create-dialog.tsx`
- Create: `apps/web/features/accounts-payable/components/payment-handoff-workspace.tsx`

**PaymentHandoffCreateDialog**: Multi-select dialog showing payment-eligible invoices in a table with checkboxes. On create: POST to handoffs endpoint. Requires at least one invoice selected. Validates same currency in UI before submitting. Date picker for effective payment date. Notes textarea.

**PaymentHandoffWorkspace**: Full handoff detail view:
- Header: handoff number, status badge, created date
- Invoice list table (from snapshot data)
- Readiness warnings section (yellow alert if warnings exist, green check if none)
- "Refresh snapshot" button (draft only) — calls refresh endpoint
- "Mark ready" button (draft only) — locks snapshot, transitions to ready
- "Export JSON" / "Export CSV" buttons (ready or exported)
- "Cancel handoff" button with reason dialog
- All operations show loading state and handle lockVersion conflicts

## Task 19: Frontend — Payment Queue Page

[x] **Done** — Separate payment queue page created with dedicated tab filters, shared table component updated with payment columns.

**Created:**
- `apps/web/features/accounts-payable/workflows/accounts-payable-payment-queue-page.tsx`
- `apps/web/app/(workspace)/accounts-payable/payment-queue/page.tsx`

**Modified:**
- `apps/web/features/accounts-payable/tables/accounts-payable-invoice-queue-table.tsx` — shared table gains `PaymentStatusBadge` column and hold/release/retry row action buttons

The payment queue is a **separate page** (`/accounts-payable/payment-queue`), distinct from the invoice review queue. The invoice review queue page (`accounts-payable-invoice-queue-page.tsx`) is left unchanged.

**Payment queue page additions**:
- Payment status filter tabs: "All", "Payment eligible", "On hold", "Payment ready", "Exported", "Awaiting induction"
- Tabs pass `paymentStatus` filter param to the shared `useAccountsPayableInvoices` query
- "Awaiting induction" tab uses `paymentStatus=none` filter (i.e. `payment_status IS NULL`)
- Default active tab is "All"
- "Create handoff" button at top — opens `PaymentHandoffCreateDialog` with filtered eligible invoices
- Detail panel shows `PaymentHoldPanel` (for eligible/on-hold) or `RetryInductionPanel` (for null payment_status)
- `ActiveHandoffsSection` below the invoice table lists all active handoffs

**Shared table component additions**:
- `paymentStatus` column with `PaymentStatusBadge` (visible on both invoice review and payment queue pages)
- "Hold"/"Release" action buttons in row actions for eligible/on-hold invoices
- "Retry induction" button for invoices awaiting induction
- Hold reason tooltip on hover

## Task 20: Frontend — Sidebar Navigation

[x] **Done** — Payment queue nav item, route page, and breadcrumb added.
- Modify: `apps/web/components/default-shell/navigation.tsx`

Add payment queue item under Finance group, below Invoice review:
```typescript
{
  title: "Payment queue",
  url: "/accounts-payable/payment-queue",
  implemented: true,
  permission: canUseAccountsPayable,
}
```

Add breadcrumb for the new path:
```typescript
if (normalizedPathname === "/accounts-payable/payment-queue") return [{ label: "Finance" }, { label: "Payment queue" }];
```

Optional: Create the route page at `apps/web/app/(workspace)/accounts-payable/payment-queue/page.tsx` (or reuse the existing invoices page with payment tabs — prefer adding tabs to the existing page for simplicity).

## Task 21: Final Verification — Run Full Suite

[x] **Done** — All checks pass: 591 backend tests, typecheck clean, lint clean, API contract passes.

```bash
# Backend
cd apps/api && php artisan test --filter=SupplierInvoicePaymentApiTest
cd apps/api && php artisan test --filter=ApPaymentHandoffApiTest
cd apps/api && php artisan test --filter=SupplierInvoiceApproval
cd apps/api && php artisan test

# OpenAPI client
pnpm generate:api
pnpm check:api-contract

# Frontend
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- accounts-payable

# Full repo
pnpm lint
pnpm build
pnpm typecheck
```

All must pass. Commit remaining changes.

# Purchase Order Issue To Supplier Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-38 so approved purchase orders can be officially issued to suppliers, exported from an immutable supplier-facing snapshot, and acknowledged with auditable metadata.

**Architecture:** Extend the existing `PurchaseOrder` aggregate with supplier issue and acknowledgement fields while keeping delivery channels out of scope. Laravel actions own issue, supplier export, and acknowledgement transitions; OpenAPI exposes generated-client endpoints; the existing Next.js purchase order workspace gains a supplier issue panel backed by TanStack Query mutations.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md`.
- Roadmap row: `docs/01-product/feature-roadmap.md` feature `P1-38`.
- Current branch: `goal-feature/p1-38-po-issue-supplier`.
- Existing PO domain: `apps/api/Domains/PurchaseOrder`.
- Existing PO workspace: `apps/web/features/purchase-orders`.
- Existing handoff export reference: `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php`.

## Scope Boundaries

Implement:

- `approved -> issued -> acknowledged` purchase order lifecycle transitions.
- Supplier issue metadata and immutable supplier version snapshot on the PO.
- JSON and CSV supplier export from the stored snapshot.
- Manual supplier acknowledgement metadata.
- API resources, OpenAPI contract, generated client, MSW handlers, web hooks, and workspace issue panel.
- Demo data for approved, issued, and acknowledged POs.
- Focused API/web tests, contract generation, typecheck, and real-app visual inspection.

Do not implement:

- Real email sending, supplier portal PO routes, supplier-authenticated acknowledgement, PDF templates, EDI/cXML/ERP sync, PO change orders, receiving, delivery tracking, invoice workflows, payment workflows, or budget encumbrance.

## File Map

### API

- Create: `apps/api/database/migrations/2026_06_10_000000_add_supplier_issue_fields_to_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/IssuePurchaseOrderToSupplier.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/ExportIssuedPurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/AcknowledgeIssuedPurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/IssuePurchaseOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/AcknowledgePurchaseOrderRequest.php`
- Modify: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`

### API Tests And Demo Data

- Create: `apps/api/tests/Feature/PurchaseOrderIssueToSupplierApiTest.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` by running `pnpm generate:api`.

### Web

- Modify: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Modify: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Create: `apps/web/features/purchase-orders/components/purchase-order-supplier-issue-panel.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`

### Docs

- Modify after PR merge: `docs/01-product/feature-roadmap.md`

---

## Task 1: API Red Tests For Supplier Issue

**Files:**

- Create: `apps/api/tests/Feature/PurchaseOrderIssueToSupplierApiTest.php`

- [ ] **Step 1: Create the failing test file**

Create `PurchaseOrderIssueToSupplierApiTest` with `RefreshDatabase`, tenant/user helpers, and PO fixture helpers copied narrowly from `PurchaseOrderReviewApprovalApiTest`.

Test methods:

```php
public function test_buyer_can_issue_approved_purchase_order_to_supplier(): void {}
public function test_issue_requires_approved_status(): void {}
public function test_issue_requires_current_lock_version(): void {}
public function test_issue_requires_supplier_facing_fields(): void {}
public function test_cross_tenant_issue_export_and_acknowledgement_are_denied(): void {}
public function test_supplier_exports_are_generated_from_stored_issue_snapshot(): void {}
public function test_recorded_supplier_export_updates_metadata_and_audit(): void {}
public function test_buyer_can_acknowledge_issued_purchase_order(): void {}
public function test_acknowledgement_requires_issued_status_lock_version_and_evidence(): void {}
```

- [ ] **Step 2: Add the approved-PO issue assertion**

Use this assertion shape:

```php
$po = $this->approvedPurchaseOrder();
$buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

$this->actingAsTenant($po->tenant, $buyer)
    ->postJson("/api/purchase-orders/{$po->id}/issue", [
        'lockVersion' => $po->lock_version,
        'method' => 'manual_email',
        'supplierContactName' => 'Priya Supplier',
        'supplierContactEmail' => 'priya.supplier@example.com',
        'message' => 'Please confirm receipt and planned delivery date.',
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'issued')
    ->assertJsonPath('data.supplierIssue.issueMethod', 'manual_email')
    ->assertJsonPath('data.supplierIssue.supplierContactEmail', 'priya.supplier@example.com')
    ->assertJsonPath('data.supplierIssue.supplierVersionNumber', 1)
    ->assertJsonPath('data.permissions.canIssueToSupplier', false)
    ->assertJsonPath('data.permissions.canExportSupplierVersion', true)
    ->assertJsonPath('data.permissions.canAcknowledgeSupplier', true);

$this->assertDatabaseHas('purchase_orders', [
    'id' => $po->id,
    'status' => 'issued',
    'issue_method' => 'manual_email',
    'supplier_contact_email' => 'priya.supplier@example.com',
    'supplier_version_number' => 1,
]);

$this->assertDatabaseHas('audit_events', [
    'tenant_id' => $po->tenant_id,
    'action' => 'purchase_order.issued',
]);
```

- [ ] **Step 3: Add invalid state and stale-lock assertions**

For statuses `draft`, `ready_for_review`, `in_review`, `changes_requested`, `rejected`, `cancelled`, `issued`, and `acknowledged`, post the same issue payload and expect `assertConflict()`. For stale lock, send `lockVersion => $po->lock_version - 1` and expect `assertConflict()`.

- [ ] **Step 4: Add supplier-facing field validation assertions**

Create an approved PO missing `payment_terms`, one missing `delivery_terms`, one missing shipping name/address, and one with no lines. Each issue attempt should return `assertConflict()` and include the missing field label in the response message.

- [ ] **Step 5: Add export assertions**

Issue a PO, mutate a live field after issue with `forceFill(['payment_terms' => 'Changed after issue'])->save()`, then call:

```php
$this->actingAsTenant($po->tenant, $buyer)
    ->getJson("/api/purchase-orders/{$po->id}/supplier-export.json")
    ->assertOk()
    ->assertJsonPath('format', 'json')
    ->assertJsonPath('purchaseOrder.number', $po->number)
    ->assertJsonPath('purchaseOrder.paymentTerms', 'Net 30');
```

Also call CSV export and assert the response contains the original PO number and original payment terms.

- [ ] **Step 6: Add recorded export and acknowledgement assertions**

Recorded JSON export via `POST /supplier-export.json` must update `last_supplier_exported_by_user_id`, `last_supplier_exported_at`, `last_supplier_export_format = json`, increment `lock_version`, and write `purchase_order.supplier_exported`.

Acknowledgement assertion:

```php
$this->actingAsTenant($po->tenant, $buyer)
    ->postJson("/api/purchase-orders/{$po->id}/acknowledge", [
        'lockVersion' => $po->refresh()->lock_version,
        'acknowledgedContactName' => 'Priya Supplier',
        'acknowledgementReference' => 'ACK-PO-100',
        'acknowledgementNote' => 'Supplier confirmed delivery in week 29.',
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'acknowledged')
    ->assertJsonPath('data.supplierIssue.acknowledgementReference', 'ACK-PO-100')
    ->assertJsonPath('data.permissions.canAcknowledgeSupplier', false);
```

- [ ] **Step 7: Run the red API test**

Run:

```bash
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
```

Expected: FAIL because routes, fields, statuses, actions, and resource properties do not exist yet.

---

## Task 2: Purchase Order Supplier Issue Backend

**Files:**

- Create: `apps/api/database/migrations/2026_06_10_000000_add_supplier_issue_fields_to_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/IssuePurchaseOrderToSupplier.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/ExportIssuedPurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/AcknowledgeIssuedPurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/IssuePurchaseOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/AcknowledgePurchaseOrderRequest.php`
- Modify: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add migration columns**

Create supplier issue, export, and acknowledgement columns exactly as specified in the design. Include nullable foreign keys to `users` for actor columns and composite indexes on `tenant_id,status,issued_at`, `tenant_id,vendor_id,issued_at`, and `tenant_id,acknowledged_at`.

- [ ] **Step 2: Extend status enum**

Add:

```php
case Issued = 'issued';
case Acknowledged = 'acknowledged';
```

Update `isTerminal()` so `Issued` and `Acknowledged` are terminal for this slice alongside `Approved`, `Rejected`, and `Cancelled`.

- [ ] **Step 3: Extend `PurchaseOrder` model**

Add fillable and casts for all new columns. Cast `supplier_version` to `array`, timestamps to `datetime`, and `supplier_version_number` to `integer`. Add `issuedByUser()`, `lastSupplierExportedByUser()`, and `acknowledgedByUser()` relations. Add same-tenant user checks in `booted()` for the new actor columns.

- [ ] **Step 4: Add request validation**

`IssuePurchaseOrderRequest` rules:

```php
return [
    'lockVersion' => ['required', 'integer', 'min:0'],
    'method' => ['required', Rule::in(['manual_email', 'portal_upload', 'external_system', 'manual_export'])],
    'supplierContactName' => ['sometimes', 'nullable', 'string', 'max:160'],
    'supplierContactEmail' => ['sometimes', 'nullable', 'email', 'max:255'],
    'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
];
```

`AcknowledgePurchaseOrderRequest` rules:

```php
return [
    'lockVersion' => ['required', 'integer', 'min:0'],
    'acknowledgedContactName' => ['sometimes', 'nullable', 'string', 'max:160'],
    'acknowledgementReference' => ['sometimes', 'nullable', 'string', 'max:160'],
    'acknowledgementNote' => ['sometimes', 'nullable', 'string', 'max:2000'],
];
```

Add an after-validator requiring at least one acknowledgement evidence field.

- [ ] **Step 5: Implement `IssuePurchaseOrderToSupplier`**

The action must lock the PO row with lines, assert `Approved`, assert lock version, validate issue readiness fields, build `supplier_version`, write issue metadata, increment lock version, save, and record `purchase_order.issued`.

Supplier snapshot keys must use the generated-client casing expected by the API resource:

```php
[
    'versionNumber' => 1,
    'issuedAt' => now()->toISOString(),
    'issueMethod' => $payload['method'],
    'purchaseOrder' => [
        'id' => (string) $po->id,
        'number' => $po->number,
        'currency' => $po->currency,
        'totalAmount' => (string) $po->total_amount,
        'paymentTerms' => $po->payment_terms,
        'deliveryTerms' => $po->delivery_terms,
    ],
    'vendor' => [
        'id' => (string) $po->vendor_id,
        'name' => data_get($po->source_snapshot, 'vendor.name'),
    ],
    'lines' => $po->lines->map(fn ($line): array => [
        'id' => (string) $line->id,
        'lineNumber' => $line->line_number,
        'description' => $line->description,
        'quantity' => (string) $line->quantity,
        'unit' => $line->unit,
        'unitPrice' => (string) $line->unit_price,
        'lineTotal' => (string) $line->total_amount,
        'currency' => $line->currency,
    ])->values()->all(),
    'source' => [
        'handoffId' => (string) $po->purchase_order_request_handoff_id,
        'rfqId' => (string) $po->rfq_id,
        'recommendationId' => (string) $po->rfq_award_recommendation_id,
        'requisitionId' => $po->requisition_id !== null ? (string) $po->requisition_id : null,
        'projectId' => $po->project_id !== null ? (string) $po->project_id : null,
    ],
    'approval' => [
        'approvalInstanceId' => $po->approval_instance_id !== null ? (string) $po->approval_instance_id : null,
        'approvedAt' => $po->approved_at?->toISOString(),
        'approvedByUserId' => $po->approved_by_user_id !== null ? (string) $po->approved_by_user_id : null,
    ],
]
```

- [ ] **Step 6: Implement `ExportIssuedPurchaseOrder`**

Accept format `json` or `csv`, and `recordExport`. Require status `Issued` or `Acknowledged`. JSON returns:

```php
[
    'format' => 'json',
    'exportedAt' => now()->toISOString(),
    'purchaseOrder' => $po->supplier_version['purchaseOrder'],
    'vendor' => $po->supplier_version['vendor'],
    'lines' => $po->supplier_version['lines'],
    'source' => $po->supplier_version['source'],
    'approval' => $po->supplier_version['approval'],
    'issue' => Arr::except($po->supplier_version, ['purchaseOrder', 'vendor', 'lines', 'source', 'approval']),
]
```

CSV headers must include `po_number`, `supplier_version_number`, `issued_at`, `issue_method`, `vendor_name`, `currency`, `line_number`, `description`, `quantity`, `unit`, `unit_price`, `line_total`, `payment_terms`, and `delivery_terms`.

- [ ] **Step 7: Implement `AcknowledgeIssuedPurchaseOrder`**

Lock by tenant, require `Issued`, assert lock version, write acknowledgement fields, set status `Acknowledged`, increment lock version, and record `purchase_order.acknowledged`.

- [ ] **Step 8: Wire policy, controller, resource, and routes**

Add policy methods `issueToSupplier`, `exportSupplierVersion`, and `acknowledgeSupplier`. Add controller methods `issue`, `exportSupplierJson`, `recordSupplierExportJson`, `exportSupplierCsv`, `recordSupplierExportCsv`, and `acknowledge`. Add routes under the existing purchase order route group.

Extend `PurchaseOrderResource` with:

```php
'supplierIssue' => [
    'issuedByUserId' => $purchaseOrder->issued_by_user_id !== null ? (string) $purchaseOrder->issued_by_user_id : null,
    'issuedAt' => $purchaseOrder->issued_at?->toISOString(),
    'issueMethod' => $purchaseOrder->issue_method,
    'supplierContactName' => $purchaseOrder->supplier_contact_name,
    'supplierContactEmail' => $purchaseOrder->supplier_contact_email,
    'message' => $purchaseOrder->issue_message,
    'supplierVersionNumber' => $purchaseOrder->supplier_version_number,
    'lastExportedByUserId' => $purchaseOrder->last_supplier_exported_by_user_id !== null ? (string) $purchaseOrder->last_supplier_exported_by_user_id : null,
    'lastExportedAt' => $purchaseOrder->last_supplier_exported_at?->toISOString(),
    'lastExportFormat' => $purchaseOrder->last_supplier_export_format,
    'acknowledgedByUserId' => $purchaseOrder->acknowledged_by_user_id !== null ? (string) $purchaseOrder->acknowledged_by_user_id : null,
    'acknowledgedAt' => $purchaseOrder->acknowledged_at?->toISOString(),
    'acknowledgedContactName' => $purchaseOrder->acknowledged_contact_name,
    'acknowledgementReference' => $purchaseOrder->acknowledgement_reference,
    'acknowledgementNote' => $purchaseOrder->acknowledgement_note,
],
```

Add permissions `canIssueToSupplier`, `canExportSupplierVersion`, and `canAcknowledgeSupplier`.

- [ ] **Step 9: Run focused API test**

Run:

```bash
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
```

Expected: PASS.

Commit:

```bash
git add apps/api
git commit -m "feat: add purchase order supplier issue workflow"
```

---

## Task 3: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under `packages/api-client/src/generated/**`

- [ ] **Step 1: Update OpenAPI**

Add status enum values `issued` and `acknowledged`. Add `supplierIssue` to `PurchaseOrder`. Add schemas `IssuePurchaseOrderRequest`, `AcknowledgePurchaseOrderRequest`, `IssuedPurchaseOrderExport`, and route definitions for issue, supplier-export JSON/CSV, recorded supplier-export JSON/CSV, and acknowledge.

- [ ] **Step 2: Regenerate generated client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: both commands pass.

- [ ] **Step 3: Inspect generated endpoints**

Confirm `packages/api-client/src/generated/endpoints.ts` exports endpoint functions for issue, acknowledge, and supplier export routes, and `packages/api-client/src/generated/schemas/purchaseOrderStatus.ts` includes `issued` and `acknowledged`.

Commit:

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "chore: update purchase order issue API contract"
```

---

## Task 4: Web Supplier Issue Workflow

**Files:**

- Modify: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Modify: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Create: `apps/web/features/purchase-orders/components/purchase-order-supplier-issue-panel.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`

- [ ] **Step 1: Extend API wrapper and hooks**

Add wrapper functions `issuePurchaseOrderToSupplier`, `acknowledgePurchaseOrder`, `exportPurchaseOrderSupplierJson`, and `recordPurchaseOrderSupplierJson`. Use generated request types and existing `throwResponseData` behavior. Add matching TanStack Query mutation hooks that invalidate list and detail query keys on success.

- [ ] **Step 2: Extend MSW fixtures and handlers**

Add mock purchase orders for statuses `approved`, `issued`, and `acknowledged`. Implement MSW handlers for:

```txt
POST /api/purchase-orders/:purchaseOrderId/issue
GET  /api/purchase-orders/:purchaseOrderId/supplier-export.json
POST /api/purchase-orders/:purchaseOrderId/supplier-export.json
POST /api/purchase-orders/:purchaseOrderId/acknowledge
```

Handlers must mutate in-memory mock state so tests can assert the UI updates after issue and acknowledgement.

- [ ] **Step 3: Build `PurchaseOrderSupplierIssuePanel`**

Render:

- Pre-approved states: disabled explanation.
- `approved`: issue method select, contact name/email, message textarea, and `Issue to supplier` button.
- `issued`: issued facts, JSON export/record buttons, acknowledgement form, and acknowledgement submit button.
- `acknowledged`: issued facts, export controls, and acknowledgement facts.

Use existing `Button`, `Input`, `Textarea`, and compact bordered sections. Surface mutation errors in a `role="alert"` block.

- [ ] **Step 4: Add panel to workspace**

Place `<PurchaseOrderSupplierIssuePanel purchaseOrder={purchaseOrder} />` after `<PurchaseOrderApprovalPanel />` and before `<PurchaseOrderActions />`.

- [ ] **Step 5: Add web tests**

Extend `purchase-order-workflow.test.tsx` with assertions:

```ts
it("issues an approved purchase order to a supplier", async () => {});
it("shows export and acknowledgement controls for issued purchase orders", async () => {});
it("records supplier acknowledgement", async () => {});
it("blocks supplier issue before approval", async () => {});
```

Use user-event to fill the form and assert visible text such as `Issued to supplier`, `ACK-PO-100`, and `Supplier issue unlocks after approval`.

- [ ] **Step 6: Run focused web test**

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --filter @cognify/web typecheck
```

Expected: both commands pass.

Commit:

```bash
git add apps/web
git commit -m "feat: add purchase order supplier issue UI"
```

---

## Task 5: Demo Data And Verification

**Files:**

- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`

- [ ] **Step 1: Seed issue-state purchase orders**

Extend demo PO seeding so demo data includes one `approved`, one `issued`, and one `acknowledged` PO. The issued and acknowledged records must include realistic supplier version snapshots, issue method, issued actor/time, supplier contact, and export or acknowledgement metadata.

- [ ] **Step 2: Assert demo counts and fields**

Update `DemoSeederTest` to assert issued and acknowledged PO examples exist and that `supplier_version_number = 1` for issued/acknowledged records.

- [ ] **Step 3: Run narrow API checks**

Run:

```bash
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
php artisan test --filter=DemoSeederTest
pnpm check:api-contract
git diff --check
```

Expected: all commands pass.

Commit:

```bash
git add apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php apps/api/tests/Feature/DemoSeederTest.php
git commit -m "test: seed purchase order supplier issue states"
```

---

## Task 6: Visual Inspection, Review, PR, Merge, Roadmap

**Files:**

- Modify after merge if not included in PR: `docs/01-product/feature-roadmap.md`

- [ ] **Step 1: Run final focused verification**

Run:

```bash
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
php artisan test --filter=DemoSeederTest
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --filter @cognify/web typecheck
git diff --check
```

Expected: all commands pass.

- [ ] **Step 2: Visual verification**

Run the real API-backed app with `pnpm dev:reset`. Capture Playwright screenshots for:

- Desktop approved PO issue form.
- Desktop issued PO export/acknowledgement form.
- Desktop acknowledged PO facts.
- Mobile approved, issued, and acknowledged PO panels.

Critique screenshots for overflow, button placement, density, responsive layout, disabled states, and whether the workflow is clear for buyer/admin users.

- [ ] **Step 3: CodeRabbit review**

Run one CodeRabbit review cycle, wait for all comments, fix valid findings, and rerun affected verification. Do not start a second CodeRabbit cycle within 15 minutes.

- [ ] **Step 4: Push and open PR**

Push `goal-feature/p1-38-po-issue-supplier` and open a ready PR. Include spec path, plan path, verification commands, visual evidence summary, and CodeRabbit summary in the PR body.

- [ ] **Step 5: Wait for PR checks and review**

Wait 10-15 minutes, inspect CI, CodeRabbit status, PR comments, and inline threads. Fix valid issues with minimal commits and rerun relevant checks.

- [ ] **Step 6: Merge and update roadmap**

When CI is green and comments are resolved, merge the PR, checkout `main`, pull latest, and update row `P1-38` in `docs/01-product/feature-roadmap.md` to `Fully Implemented` with this spec path, this plan path, PR number, and a note that supplier issue is implemented as audited issue/export/acknowledgement state.

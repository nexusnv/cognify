# Supplier Invoice Capture Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add PO-centric supplier invoice capture so AP and buyer users can record invoice headers, line allocations, duplicate checks, and supporting files from the purchase-order workspace.

**Architecture:** Introduce a dedicated `Invoice` domain in the API that persists durable `SupplierInvoice` and `SupplierInvoiceLine` records, enforces tenant and PO validation, emits audit events, and exposes tenant-scoped JSON endpoints. Extend the purchase-order resource with invoice permissions and summary data, then add a new purchase-order workspace panel in the web app that consumes the generated client, uses MSW for deterministic tests, and reuses the existing attachment patterns through invoice-specific attachment endpoints.

**Tech Stack:** Laravel 12, Eloquent UUID models, FormRequest validation, Sanctum tenant middleware, OpenAPI JSON, Orval-generated `@cognify/api-client`, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix UI.

---

## File Map

Backend domain:

- Create `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php`
- Create `apps/api/Domains/Invoice/Models/SupplierInvoice.php`
- Create `apps/api/Domains/Invoice/Models/SupplierInvoiceLine.php`
- Create `apps/api/Domains/Invoice/Actions/CaptureSupplierInvoice.php`
- Create `apps/api/Domains/Invoice/Support/SupplierInvoiceNumber.php`
- Create `apps/api/Domains/Invoice/Support/SupplierInvoiceDuplicateChecker.php`
- Create `apps/api/Domains/Invoice/Policies/SupplierInvoicePolicy.php`
- Create `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceController.php`
- Create `apps/api/Domains/Invoice/Http/Requests/CaptureSupplierInvoiceRequest.php`
- Create `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceResource.php`
- Create `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceLineResource.php`
- Create `apps/api/database/migrations/2026_06_12_010000_create_supplier_invoices_table.php`
- Create `apps/api/database/migrations/2026_06_12_010100_create_supplier_invoice_lines_table.php`
- Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Modify `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify `apps/api/routes/api.php`
- Modify `apps/api/storage/openapi/openapi.json`
- Create `apps/api/tests/Feature/SupplierInvoiceApiTest.php`

Invoice attachments:

- Create `apps/api/Domains/Attachment/Actions/StoreSupplierInvoiceAttachment.php`
- Create `apps/api/Domains/Attachment/Http/Controllers/SupplierInvoiceAttachmentController.php`
- Modify `apps/api/Domains/Attachment/Http/Resources/AttachmentResource.php`

Generated contract:

- Regenerate `packages/api-client/src/generated/endpoints.ts`
- Regenerate `packages/api-client/src/generated/schemas/*`

Web invoice workspace:

- Create `apps/web/features/purchase-orders/api/purchase-order-supplier-invoice-api.ts`
- Create `apps/web/features/purchase-orders/hooks/use-purchase-order-supplier-invoices.ts`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-panel.tsx`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-form.tsx`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-attachments.tsx`
- Create `apps/web/features/purchase-orders/mocks/purchase-order-supplier-invoice-fixtures.ts`
- Create `apps/web/features/purchase-orders/mocks/purchase-order-supplier-invoice-handlers.ts`
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify `apps/web/tests/msw/handlers.ts`
- Create `apps/web/features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx`

Demo and release plumbing:

- Modify `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify `apps/api/database/seeders/DatabaseSeeder.php`
- Modify `apps/api/tests/Feature/DemoSeederTest.php`
- Modify `docs/01-product/feature-roadmap.md`

---

### Task 1: Lock The Backend Contract With Red Tests

**Files:**
- Create `apps/api/tests/Feature/SupplierInvoiceApiTest.php`

- [ ] **Step 1: Write the failing API tests first**

Use the same style as the existing PO workflow tests and keep the assertions focused on the public API shape. Start with capture, duplicate rejection, tenant isolation, lock-version conflicts, and attachment upload/listing.

```php
public function test_buyer_can_capture_supplier_invoice(): void
{
    $po = $this->issuedPurchaseOrder();
    $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
    $line = $po->lines->first();

    $this->actingAsTenant($po->tenant, $buyer)
        ->postJson("/api/purchase-orders/{$po->id}/supplier-invoices", [
            'lockVersion' => $po->lock_version,
            'invoiceNumber' => 'INV-10001',
            'invoiceDate' => '2026-06-12',
            'dueDate' => '2026-07-12',
            'taxAmount' => '7200.00',
            'freightAmount' => '3900.00',
            'notes' => 'Supplier invoice received by AP.',
            'lines' => [[
                'purchaseOrderLineId' => (string) $line->id,
                'quantityInvoiced' => '10.0000',
                'unitPrice' => '12000.0000',
                'notes' => 'Invoice line matches PO line.',
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoiceNumber', 'INV-10001')
        ->assertJsonPath('data.totalAmount', '131100.00')
        ->assertJsonPath('data.lines.0.purchaseOrderLineId', (string) $line->id);
}
```

Add the remaining test methods in the same file:
- `test_duplicate_supplier_invoice_returns_conflict`
- `test_invoice_capture_is_tenant_scoped`
- `test_invoice_capture_rejects_lock_version_conflict`
- `test_invoice_list_includes_capture_summary`
- `test_supplier_invoice_attachment_upload_and_listing`
- `test_supplier_invoice_audit_event_is_recorded`

- [ ] **Step 2: Run the new test file and confirm it fails for the right reasons**

Run: `php artisan test --filter SupplierInvoiceApiTest --display-warnings`

Expected: route/model/controller failures before the new domain exists, not silent passes.

- [ ] **Step 3: Implement the backend domain, routes, and permissions**

Create the new `Domains/Invoice` models, state enum, action, duplicate checker, number generator, policy, controller, request, and resources. Add the two invoice tables, wire the routes in `apps/api/routes/api.php`, add `supplierInvoices()` to `PurchaseOrder`, expose `invoiceSummary` and `canCaptureInvoice` from `PurchaseOrderResource`, and eager load `lines` plus `supplierInvoices` in `PurchaseOrderController`.

Keep the invoice action behavior aligned with the spec:
- reject cross-tenant and cross-PO line references
- reject duplicate invoice numbers for the same tenant and PO context
- preserve the generated capture record even when attachments are added later
- record `supplier_invoice.captured` in the audit log

For invoice attachments, mirror the existing attachment domain pattern:
- store files through `AttachmentStorage`
- persist `Attachment` rows with `attachable_type` pointing at `SupplierInvoice`
- expose invoice-specific attachment list/upload endpoints
- keep preview/download/delete on the shared attachment file controller

- [ ] **Step 4: Re-run the backend feature tests**

Run: `php artisan test --filter SupplierInvoiceApiTest --display-warnings`

Expected: all invoice capture, duplicate, tenant, lock-version, and attachment assertions pass.

- [ ] **Step 5: Commit the backend slice**

```bash
git add apps/api/Domains/Invoice apps/api/Domains/Attachment apps/api/routes/api.php apps/api/storage/openapi/openapi.json apps/api/tests/Feature/SupplierInvoiceApiTest.php apps/api/database/migrations
git commit -m "feat: add supplier invoice capture backend"
```

---

### Task 2: Regenerate The API Contract And Client

**Files:**
- Modify `apps/api/storage/openapi/openapi.json`
- Regenerate `packages/api-client/src/generated/endpoints.ts`
- Regenerate `packages/api-client/src/generated/schemas/*`

- [ ] **Step 1: Add the invoice endpoints and schemas to OpenAPI**

Make sure the contract includes:
- `GET /api/purchase-orders/{purchaseOrder}/supplier-invoices`
- `POST /api/purchase-orders/{purchaseOrder}/supplier-invoices`
- `GET /api/supplier-invoices/{supplierInvoice}`
- `GET /api/supplier-invoices/{supplierInvoice}/attachments`
- `POST /api/supplier-invoices/{supplierInvoice}/attachments`

The response schema must include:
- `SupplierInvoice`
- `SupplierInvoiceLine`
- `PurchaseOrder.invoiceSummary`
- `PurchaseOrder.permissions.canCaptureInvoice`

- [ ] **Step 2: Regenerate the client and verify it compiles**

Run:
```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: the generated endpoints and schemas include the new supplier-invoice operations, and the client typecheck passes with no hand-written contract duplicates.

- [ ] **Step 3: Commit the contract update**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: generate supplier invoice client"
```

---

### Task 3: Build The Purchase-Order Invoice Workspace

**Files:**
- Create `apps/web/features/purchase-orders/api/purchase-order-supplier-invoice-api.ts`
- Create `apps/web/features/purchase-orders/hooks/use-purchase-order-supplier-invoices.ts`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-panel.tsx`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-form.tsx`
- Create `apps/web/features/purchase-orders/components/purchase-order-supplier-invoice-attachments.tsx`
- Create `apps/web/features/purchase-orders/mocks/purchase-order-supplier-invoice-fixtures.ts`
- Create `apps/web/features/purchase-orders/mocks/purchase-order-supplier-invoice-handlers.ts`
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify `apps/web/tests/msw/handlers.ts`
- Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Create `apps/web/features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx`

- [ ] **Step 1: Write the failing workspace tests**

Start with the invoice panel and form behavior before wiring the API client:

```ts
it("renders the supplier invoice panel and summary", async () => {
  renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

  expect(await screen.findByRole("region", { name: "Supplier invoices" })).toBeInTheDocument();
  expect(screen.getByText("No supplier invoices have been captured for this purchase order yet.")).toBeInTheDocument();
});

it("captures an invoice and keeps the entered line values on duplicate conflict", async () => {
  const user = userEvent.setup();
  renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);
  await user.click(await screen.findByRole("button", { name: "Capture invoice" }));
  await user.type(screen.getByLabelText("Invoice number"), "INV-10001");
  await user.click(screen.getByRole("button", { name: "Save invoice" }));
  expect(await screen.findByRole("alert")).toHaveTextContent("already exists");
});
```

Cover:
- permission gating through `canCaptureInvoice`
- invoice list cards with number, invoice date, due date, total, and captured-by metadata
- PO-line-keyed line editors
- duplicate conflict message rendering
- attachment upload/listing for the invoice record

- [ ] **Step 2: Run the new Vitest file and confirm it fails**

Run: `pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx`

Expected: missing hook, API, and panel failures until the invoice feature is implemented.

- [ ] **Step 3: Implement the invoice API layer, hook, panel, and attachment UI**

Use the generated client in `purchase-order-supplier-invoice-api.ts` and keep tenant headers consistent with the existing PO goods-receipt flow. The hook should cache by tenant and PO id, invalidate after create/upload, and surface server conflict payloads without clearing the form state.

The panel should:
- render inside the existing PO workspace
- default invoice currency from the PO currency
- keep line edits keyed by purchase-order line id
- preserve entered values on duplicate conflicts
- show the summary count, latest invoice date, and total invoiced amount
- show the attachment list and upload control after invoice creation

Reuse the existing UI primitives and MSW style, but keep invoice code under the purchase-order feature boundary.

- [ ] **Step 4: Re-run the workspace test and typecheck**

Run:
```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx
pnpm --filter @cognify/web typecheck
```

Expected: the new panel, list, duplicate handling, and attachment flows pass without breaking the existing PO workspace.

- [ ] **Step 5: Commit the web slice**

```bash
git add apps/web/features/purchase-orders apps/web/tests/msw/handlers.ts
git commit -m "feat: add supplier invoice workspace"
```

---

### Task 4: Seed Demo Data, Update Roadmap, And Verify The Real App

**Files:**
- Modify `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify `apps/api/database/seeders/DatabaseSeeder.php`
- Modify `apps/api/tests/Feature/DemoSeederTest.php`
- Modify `docs/01-product/feature-roadmap.md`

- [ ] **Step 1: Add seeded invoice coverage**

Seed at least one issued-PO supplier invoice with line data and one attachment so the local app has a real invoice surface to inspect. Update the seeder test so the demo graph still proves tenant scoping, line casting, and attachment metadata for the new records.

- [ ] **Step 2: Run the narrow backend and demo tests**

Run:
```bash
php artisan test --filter "SupplierInvoiceApiTest|DemoSeederTest" --display-warnings
```

Expected: the invoice slice and the seeded demo data both pass together.

- [ ] **Step 3: Run the focused web test again after seeding changes**

Run:
```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx
```

Expected: the seeded invoice fixture and the live panel still agree on invoice summary and attachment behavior.

- [ ] **Step 4: Verify the real app visually**

Run the local seeded backend and frontend, open the purchase-order workspace for the seeded invoice PO, and inspect:
- invoice summary correctness
- form layout on desktop and mobile width
- duplicate conflict messaging
- attachment upload affordances
- section ordering alongside goods receipt and fulfillment panels

Use the real API-backed app, not MSW-only rendering, for this pass.

- [ ] **Step 5: Update the roadmap and finish with the broad checks**

Update `docs/01-product/feature-roadmap.md` with the completed PR reference once the implementation is ready to merge, then run the final sweep:

```bash
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
pnpm --filter @cognify/web typecheck
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-supplier-invoice-workflow.test.tsx
php artisan test --filter "SupplierInvoiceApiTest|DemoSeederTest" --display-warnings
```

Expected: contract, API client, web typecheck, focused web test, and backend tests all pass before branch completion.

---

## Self-Review

1. Spec coverage:
- Durable invoice record and line records are covered in Task 1.
- Duplicate detection, lock-version conflicts, and tenant isolation are covered in Task 1.
- Purchase-order summary and `canCaptureInvoice` are covered in Task 1.
- OpenAPI and client regeneration are covered in Task 2.
- PO workspace UI, duplicate error handling, and attachment UX are covered in Task 3.
- Demo seeding, roadmap update, and visual inspection are covered in Task 4.

2. Placeholder scan:
- No `TBD`, `TODO`, or vague "implement later" steps remain.
- Every code-changing task names exact files and exact verification commands.

3. Type consistency:
- `SupplierInvoice`, `SupplierInvoiceLine`, `CaptureSupplierInvoiceRequest`, and `canCaptureInvoice` are used consistently across backend and web tasks.
- The invoice panel, API helper, and hooks all use the same PO-line-keyed invoice line model.
- Attachment endpoints are named consistently as supplier-invoice-specific routes in both the API and web tasks.

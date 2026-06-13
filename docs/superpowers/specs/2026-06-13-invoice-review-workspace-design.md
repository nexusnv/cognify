# Invoice Review Workspace Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-13
- Release scope: P1 core procure-to-pay lifecycle, slice P1-43 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-43`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-11-delivery-fulfillment-tracking-design.md`
  - `docs/superpowers/specs/2026-06-11-receiving-goods-receipt-design.md`
  - `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md`

## Roadmap Analysis

P1-43 asks Cognify to provide an operational AP/procurement queue for invoice completeness, coding, attachment, vendor identity, PO linkage, and exception review before matching or approval.

The current codebase already has durable supplier invoice capture in `apps/api/Domains/Invoice`, invoice attachments through the attachment domain, and purchase-order workspace invoice capture under `apps/web/features/purchase-orders`. That gives this slice a stable invoice record to review. The next roadmap items, P1-44 through P1-46, cover matching, exception routing, and invoice approval. This slice must prepare invoices for those downstream workflows without implementing them.

The selected scope is AP invoice queue first. The queue becomes the primary AP work surface for captured supplier invoices across purchase orders, while the purchase-order workspace remains a linked record context.

## Problem

Captured supplier invoices currently exist beside purchase orders, but AP users do not have one operational surface for deciding whether invoices are complete enough for matching or approval. Without an invoice review workspace:

- AP users must find invoices by opening purchase orders individually.
- Cognify cannot distinguish newly captured invoices from invoices that have been reviewed.
- Missing attachments, coding, vendor identity issues, and PO linkage issues are not recorded as workflow facts.
- Matching and approval slices would need to infer readiness from incomplete state.
- Audit trails cannot show who reviewed an invoice or why it was blocked before matching.

## Goals

- Add an AP invoice queue under `apps/web/features/accounts-payable`.
- Let permitted users review captured supplier invoices across the active tenant.
- Add durable invoice review statuses and review metadata.
- Capture checklist outcomes for completeness, coding, attachment, vendor identity, and PO linkage.
- Support review transitions: start review, mark needs information, and complete review.
- Keep reviewed invoices clearly eligible for P1-44 matching.
- Record audit events for review transitions and checklist outcomes.
- Expose OpenAPI-backed endpoints and consume them through `@cognify/api-client`.
- Seed realistic invoice review states for local development and demos.

## Non-Goals

- Two-way or three-way matching calculations.
- Matching tolerance configuration.
- Invoice exception owner routing.
- Invoice approval through the shared Approval domain.
- Payment readiness, AP export, payment status, or credit memo handling.
- OCR extraction, document parsing, or automated invoice normalization.
- Supplier portal invoice submission changes.
- A new `Domains/AccountsPayable` backend boundary.
- Formal finance/AP role creation; this slice maps AP access to existing buyer/admin permissions until a dedicated role exists.

## Approaches Considered

### 1. AP queue with lightweight review state

This is selected. Create an AP invoice queue and extend the existing invoice domain with review statuses, checklist metadata, review actions, and audit events. This matches the roadmap's operational queue language and creates a clean handoff to P1-44 matching without prematurely building exception routing or approval.

### 2. Read-only AP dashboard

This would list captured invoices and derive readiness hints without adding review transitions. It is smaller, but it would not let AP users complete review or record why an invoice is blocked, so it would leave P1-43 only partially useful.

### 3. New Accounts Payable backend domain

This would put invoice review under a new `Domains/AccountsPayable` boundary. The roadmap ownership table points broader invoices, matching, and payments toward accounts payable, but the current durable invoice aggregate already lives in `Domains/Invoice`. Creating a new backend domain now would add boundary churn before matching, approval, payment readiness, and credit memo behavior clarify the broader AP aggregate shape.

## Workflow

### Actors

- AP user: reviews captured supplier invoices for readiness.
- Buyer: reviews invoices when acting as procurement/AP operator.
- Admin: can review invoices for tenant operations.
- System: enforces tenant, permission, state, and lock-version rules; records audit events.

The first implementation maps AP capability to existing buyer/admin access. A dedicated finance or AP role remains future scope.

### Main Flow

1. User opens `/accounts-payable/invoices`.
2. Queue loads supplier invoices for the active tenant.
3. User filters by review status, vendor, purchase order, due date, attachment state, or blocker.
4. User opens a captured invoice from the queue.
5. User starts review.
6. User checks:
   - completeness
   - coding
   - attachment
   - vendor identity
   - PO linkage
7. If required checks pass, user completes review.
8. If information is missing, user marks the invoice as needing information with blockers and notes.
9. The queue updates to show the invoice as reviewed or needing information.
10. Reviewed invoices become eligible input for the later matching slice.

### State Model

Supplier invoice statuses for this slice:

- `captured`: invoice was captured and is waiting for AP review.
- `in_review`: invoice review has started.
- `needs_information`: invoice is blocked before matching because review found missing or invalid information.
- `reviewed`: invoice passed AP review and is eligible for matching.

Allowed transitions:

| From | Action | To |
| --- | --- | --- |
| `captured` | start review | `in_review` |
| `needs_information` | start review | `in_review` |
| `in_review` | mark needs information | `needs_information` |
| `in_review` | complete review | `reviewed` |

Invalid transitions return a conflict error. All state-changing actions require the current invoice `lockVersion`.

### Review Checklist

The review checklist records one outcome per required area:

- `completeness`
- `coding`
- `attachment`
- `vendorIdentity`
- `poLinkage`

Each checklist item has:

- `status`: `pass`, `fail`, or `needs_attention`
- `note`: nullable reviewer note

Completing review requires every checklist item to be `pass`. Marking needs information requires at least one `fail` or `needs_attention` item and a reviewer note.

## Backend Design

### Domain Ownership

The owning backend domain remains `apps/api/Domains/Invoice`.

Supporting domains:

- `Domains/PurchaseOrder`: source of PO, PO line, vendor, department, cost center, and project context.
- `Domains/Attachment`: source of invoice attachment counts and uploads.
- `app/Audit`: audit recording.
- `app/Tenancy`: tenant resolution and membership enforcement.

### Data Model

Add review fields to `supplier_invoices`:

- `review_started_by_user_id` nullable foreign key to users
- `review_started_at` nullable timestamp
- `reviewed_by_user_id` nullable foreign key to users
- `reviewed_at` nullable timestamp
- `review_notes` nullable text
- `review_checklist` nullable JSON
- `review_blockers` nullable JSON

Keep `lock_version` as the concurrency guard.

No new table is required for checklist items in this slice. JSON is sufficient because the checklist is fixed, small, invoice-local, and versionable in the resource contract. If future policy configuration or analytics require independent checklist records, that can be introduced after matching and exception workflows settle.

### Domain Structure

```txt
apps/api/Domains/Invoice/
  Actions/
    StartSupplierInvoiceReview.php
    MarkSupplierInvoiceNeedsInformation.php
    CompleteSupplierInvoiceReview.php
  Data/
    SupplierInvoiceReviewChecklistData.php
  Http/
    Controllers/
      SupplierInvoiceController.php
      SupplierInvoiceReviewController.php
    Requests/
      StartSupplierInvoiceReviewRequest.php
      MarkSupplierInvoiceNeedsInformationRequest.php
      CompleteSupplierInvoiceReviewRequest.php
    Resources/
      SupplierInvoiceResource.php
      SupplierInvoiceQueueResource.php
  Models/
    SupplierInvoice.php
  Policies/
    SupplierInvoicePolicy.php
  States/
    SupplierInvoiceStatus.php
```

Use only the files the implementation needs. Empty folders should not be created.

### Domain Behavior

`StartSupplierInvoiceReview`:

- Locks the invoice row.
- Allows `captured` or `needs_information`.
- Sets status to `in_review`.
- Sets `review_started_by_user_id` and `review_started_at`.
- Clears stale blockers only when the reviewer submits a fresh checklist.
- Increments `lock_version`.
- Records `supplier_invoice.review_started`.

`MarkSupplierInvoiceNeedsInformation`:

- Locks the invoice row.
- Allows only `in_review`.
- Requires current `lockVersion`.
- Requires at least one failed or attention-needed checklist item.
- Requires a reviewer note.
- Sets status to `needs_information`.
- Stores checklist, blockers, and review notes.
- Increments `lock_version`.
- Records `supplier_invoice.needs_information`.

`CompleteSupplierInvoiceReview`:

- Locks the invoice row.
- Allows only `in_review`.
- Requires current `lockVersion`.
- Requires all checklist items to pass.
- Sets status to `reviewed`.
- Stores checklist and notes.
- Sets `reviewed_by_user_id` and `reviewed_at`.
- Increments `lock_version`.
- Records `supplier_invoice.review_completed`.

### Authorization

Viewing and reviewing supplier invoices require:

1. Authenticated Sanctum session.
2. Active tenant context.
3. Invoice belongs to the current tenant.
4. Current tenant role is buyer or admin.

This matches the current invoice capture policy shape. A later finance/AP role can be added without changing the review workflow contract.

### Audit Metadata

Review audit events include:

- invoice id and internal number
- supplier invoice number
- purchase order id and number
- vendor id
- previous status
- next status
- checklist summary
- blocker count
- whether notes were supplied

Audit metadata must not duplicate full attachment payloads or long free-text notes when a short note-presence flag is enough for the audit list.

## API Contract

Add or extend authenticated tenant-scoped routes:

```txt
GET  /api/supplier-invoices
GET  /api/supplier-invoices/{supplierInvoice}
POST /api/supplier-invoices/{supplierInvoice}/start-review
POST /api/supplier-invoices/{supplierInvoice}/needs-information
POST /api/supplier-invoices/{supplierInvoice}/complete-review
```

`GET /api/supplier-invoices` supports:

- `status`
- `vendorId`
- `purchaseOrderNumber`
- `invoiceNumber`
- `dueBefore`
- `requiresAttachment`
- `reviewBlocker`
- `sort`
- `page`

Queue response fields:

- `id`
- `number`
- `invoiceNumber`
- `status`
- `invoiceDate`
- `dueDate`
- `currency`
- `totalAmount`
- `vendor`
- `purchaseOrder`
- `attachmentCount`
- `reviewChecklistSummary`
- `reviewBlockerCount`
- `reviewStartedAt`
- `reviewedAt`
- `lockVersion`
- `permissions`

Action request fields:

- `lockVersion`: required integer
- `checklist`: required for needs-information and complete-review
- `notes`: nullable for complete-review, required for needs-information

Action responses return the updated `SupplierInvoice` resource.

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas in the web feature. Do not duplicate contract response types in app code.

## Frontend Design

### Routes

Add:

```txt
apps/web/app/(workspace)/accounts-payable/invoices/page.tsx
```

The page renders an `AccountsPayableInvoiceQueuePage` from the feature folder.

### Feature Structure

```txt
apps/web/features/accounts-payable/
  api/
    accounts-payable-invoices-api.ts
  components/
    invoice-review-panel.tsx
    invoice-review-checklist.tsx
    invoice-review-status-badge.tsx
    invoice-queue-summary.tsx
  hooks/
    use-accounts-payable-invoices.ts
    use-supplier-invoice-review-actions.ts
  mocks/
    accounts-payable-invoice-fixtures.ts
    accounts-payable-invoice-handlers.ts
  tables/
    accounts-payable-invoice-queue-table.tsx
  tests/
    accounts-payable-invoice-queue.test.tsx
  workflows/
    accounts-payable-invoice-queue-page.tsx
```

### Queue UX

The queue page uses the existing dense procurement table pattern.

Header summary:

- total captured
- in review
- needs information
- reviewed

Filters:

- `Needs review`
- `In review`
- `Needs information`
- `Reviewed`
- `All`

Table columns:

- invoice
- vendor
- purchase order
- status
- due date
- total
- attachment state
- checklist state
- last review timestamp
- action

Rows link to the invoice review panel and expose a related PO link. Users should not need to open a purchase order to find invoices that need AP review.

### Review UX

The review surface shows:

- invoice header
- vendor and PO context
- invoice lines summary
- attachment count and link to attachment area
- review checklist
- notes
- status-aware actions

Actions:

- `Start review`
- `Complete review`
- `Needs information`

The UI must show loading, empty, populated, error, permission-denied, and stale-state conflict views.

### Navigation

The global sidebar should remain shallow. Add an accounts-payable invoice queue entry only if the current navigation model can expose it as a durable work area without showing future roadmap items. Command palette/search may also include an "Open invoice review queue" command when permissions allow it.

## Data Flow

```txt
AP queue page
  -> generated API client list supplier invoices
  -> queue table and summary
  -> open review panel
  -> start / needs information / complete review mutation
  -> Laravel invoice action
  -> tenant, policy, status, lock-version checks
  -> supplier_invoices update
  -> audit event
  -> generated resource response
  -> invalidate queue/detail queries
```

The browser sends credentials and `X-Tenant-Id` through existing generated-client helpers. The API remains the authority for tenant membership, permissions, and state transitions.

## Error Handling

- Validation errors map to checklist or notes fields.
- Permission denial shows a queue-level or action-level forbidden state.
- Missing tenant context follows the existing generated-client error behavior.
- Invalid state transitions return conflict responses with a user-facing message.
- Stale `lockVersion` returns a conflict response and prompts the user to refresh.
- Empty queues explain that no invoices match the current filters.

## Seed and Demo Data

Demo data should include at least:

- captured invoice needing review
- in-review invoice
- needs-information invoice with blockers
- reviewed invoice ready for matching

Seeded invoices should reference real seeded purchase orders, vendors, PO lines, and attachments where available.

## Testing and Verification

### API Tests

Add focused feature tests for:

- queue list is tenant-scoped
- queue filters by status and due date
- cross-tenant invoice access is denied
- requester or unauthorized role cannot review invoices
- buyer/admin can start review
- needs-information requires blocker checklist and note
- complete-review requires all checklist items to pass
- invalid transitions return conflict
- stale lock version returns conflict
- audit events are recorded for each transition

### Web Tests

Add tests for:

- queue loading, empty, populated, and error states
- status filters update visible rows
- row opens review panel
- start review action updates status
- complete review with passing checklist
- needs-information with failed checklist item and note
- permission-denied rendering
- stale-state conflict message

### Contract and Local Verification

Expected implementation verification:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
cd apps/api && php artisan test --filter=SupplierInvoiceReview
```

If shared navigation or command-palette behavior changes, also run focused shell/search tests.

## Exit Criteria

- AP users can review captured supplier invoices from one queue across purchase orders.
- Review state is durable, auditable, tenant-scoped, and OpenAPI-backed.
- Reviewed invoices are clearly eligible for P1-44 matching.
- Needs-information invoices remain visible and blocked before matching.
- The purchase-order invoice panel remains compatible with the new statuses.
- Matching, exception routing, invoice approval, and payment readiness remain deferred.

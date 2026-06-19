# Payment Readiness and AP Handoff Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-18
- Release scope: P1 core procure-to-pay lifecycle, slice P1-47 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-47`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-approval-design.md`
- Downstream slices:
  - P1-48 Payment Status Tracking — consumes handoff-exported invoices and tracks scheduled/paid/failed/voided status
  - P1-49 Credit Memo and Invoice Adjustment
- Reference pattern: `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`

## Roadmap Analysis

P1-47 asks Cognify to mark approved invoices as ready for payment and export structured AP/payment handoff data. The roadmap explicitly calls this the "invoice-side equivalent of PO handoff" and requires it to work before direct accounting integration exists.

P1-46 (Invoice Approval) is now complete. Invoices exit P1-46 in `approved` status through either Straight-Through Processing (STP) or human approval task resolution. P1-47 picks up from that state and creates the payment readiness pipeline.

The current codebase has:
- Durable supplier invoice records with capture, review, matching, exception, and approval state
- The `SupplierInvoiceStatus` enum culminating at `approved` / `rejected` — no post-approval states
- A `Vendor` model without payment or banking fields
- The `PurchaseOrderRequestHandoff` pattern (P1-34) as the established handoff export template
- A mature accounts-payable frontend feature with queue, review panel, matching panel, exception panel, and approval panel

No payment readiness, AP handoff, or payment status infrastructure exists yet. This slice introduces the first post-approval payment constructs.

## Problem

After P1-46, Cognify has approved supplier invoices but cannot:
- Distinguish approved invoices that are ready for payment from those AP needs to hold.
- Let AP users place a payment hold on an invoice with a reason.
- Export structured payment-ready invoice data for finance teams or downstream payment systems.
- Provide an AP workspace for managing the payment pipeline.
- Give auditors a clear record of which invoices were considered payment-ready and when.

Without P1-47, the P2P lifecycle stops at approval. Invoices are approved but there is no operational bridge to payment, no exportable data for external systems, and no visibility into the payment pipeline.

## Goals

- Extend `SupplierInvoice` with payment state: `payment_eligible`, `on_hold`, `payment_ready`, `handoff_exported`.
- Auto-advance approved invoices to `payment_eligible` within the same transactional boundary as the approval outcome.
- Let AP users place and release payment holds with reasons and audit trail.
- Introduce an `ApPaymentHandoff` aggregate that groups one or more payment-ready invoices into a snapshot-based export package.
- Provide JSON and CSV handoff exports containing structured remittance-ready invoice, vendor, PO, and line-item data.
- Keep tenant isolation, lock-version concurrency, generated-client contracts, and real route-stack tests consistent with established patterns.
- Add a new `AccountsPayable` backend domain boundary for post-approval payment lifecycle behavior.

## Non-Goals

- Payment execution, ACH/wire/check generation, or direct payment processing (P1-48 scope).
- Payment status tracking: scheduled, paid, failed, voided, or remitted (P1-48 scope).
- Direct ERP integration or accounting system sync (P3-x scope).
- Multi-currency handoff grouping (deferred — first slice assumes single-currency or dominant-currency handoffs).
- Payment batch approval workflow (deferred — no dual-approval for handoff release in this slice).
- Automated payment scheduling based on vendor payment terms (deferred).
- Credit memo, debit note, invoice reversal, or invoice adjustment handling (P1-49 scope).
- Vendor banking master data or remittance contact fields (P1-51 scope).
- Payment method selection (ACH vs wire vs check vs card) — deferred to P1-48.
- Remittance advice delivery to vendor portal or email (deferred).
- Purchase order changes triggered by invoice holds.
- Configurable export mapping templates or tenant-specific payment adapters.

## Design Decision

### Create A Dedicated AccountsPayable Domain

P1-47 should introduce `apps/api/Domains/AccountsPayable` for post-approval payment lifecycle behavior. The existing `Invoice` domain retains ownership of the invoice record, capture, review, matching, exception, and approval. The new domain owns:
- Payment eligibility and hold state
- Payment handoff packaging, snapshot, status
- Export format generation
- Future payment status tracking extension points

P1-43 (Invoice Review) intentionally kept all behavior in `Invoice` because "matching, approval, payment readiness, and credit memo behavior" had not yet clarified the broader AP aggregate shape. With P1-44 through P1-46 now complete and matching/exception/approval states stable, the AP boundary is clear enough to introduce a dedicated domain.

This boundary mirrors the PO handoff pattern where `PurchaseOrder` owns handoff packaging while `Quotation` owns the award recommendation.

### Select Invoices, Then Group Into Handoff

Unlike the PO handoff (which auto-creates one handoff per award recommendation), P1-47 lets AP users select multiple `payment_eligible` invoices and create one `ApPaymentHandoff` from them. This matches real AP payment run workflows where multiple invoices are paid together.

An invoice can belong to at most one active (non-cancelled) handoff at a time.

### Multi-Currency Guardrail

All invoices in a single handoff must share the same currency code. The system rejects handoff creation with a `422 Unprocessable` response if the selected invoices have mixed currencies. This prevents silent aggregation of values across different currencies (e.g., $500 + €500 = 1000) that would produce mathematically invalid financial records. Multi-currency handoff grouping is deferred as a non-goal; when supported in a future slice, it will require explicit currency conversion or separate per-currency totals.

### Auto-Advance On Approval

When `MarkSupplierInvoiceApproved` or STP transitions an invoice to `approved`, a new `AutoAdvanceToPaymentEligible` action fires in the same transaction. This ensures approved invoices immediately surface in the payment readiness queue with no manual step required.

If the auto-advance fails (e.g., constraint violation), the approval still commits — the invoice stays `approved` without `payment_eligible`. A retry action or recovery job handles the edge case.

**"Ghost Approved" recovery**: An invoice in this state (main `status = approved` but `payment_status = null`) is invisible to the handoff creation engine (which queries `payment_eligible`), but still appears in the "Approved" queue tab. To prevent this dead-end, the "Approved" tab must check for `payment_status = null` and flag such invoices with an `"Awaiting payment induction"` warning badge. A manual `RetryPaymentInduction` action (available in the invoice detail panel and as a "Retry induction" button in the queue row) re-attempts the auto-advance and moves the invoice to `payment_eligible`.

### Snapshot Dynamics: Draft Is Dynamic, Only Locked At Ready

The handoff snapshot captures invoice, vendor, PO, line item, and approval metadata. While the handoff is in `draft` status, the snapshot is **recalculable** — the AP user can refresh it to pick up master data changes (e.g., vendor tax ID was added, payment terms were updated). This prevents a Catch-22 where a user fixes a warning trigger in another tab but cannot clear the warning in the draft.

The snapshot **locks permanently** only when the handoff transitions from `draft` to `ready`. At that point, `MarkApPaymentHandoffReady` calls `BuildApPaymentHandoffSnapshot` one final time with current data before freezing. After `ready`, the snapshot is immutable — exactly as with the PO handoff pattern.

A dedicated `POST /api/accounts-payable/handoffs/{handoff}/refresh` endpoint allows on-demand recalculation while in `draft`.

### Separate payment_status Column

`payment_status` lives in a separate column from the main `status` on `SupplierInvoice`. This keeps the review/matching/approval state machine orthogonal to payment state. An invoice can be `approved` (main status) and `on_hold` (payment_status) simultaneously without conflict.

## Approaches Considered

1. **AP handoff package with holds (selected)**. Add `payment_eligible`/`on_hold`/`payment_ready`/`handoff_exported` states to SupplierInvoice. Create `ApPaymentHandoff` as a snapshot-based export record with draft/ready/exported/cancelled states. Support hold placement and release. JSON/CSV export. This is the invoice-side equivalent of PO handoff with added hold capability.

2. **Status-only on SupplierInvoice without separate handoff model**. Simpler — no new `ApPaymentHandoff` table. But loses the snapshot-based audit trail and makes it harder to group invoices into export packages. Selected against because it underserves the roadmap's "structured AP/payment handoff data" requirement and PO handoff precedent.

3. **Payment batch/run with batch-level controls**. Adds a `PaymentBatch` aggregate with batch lifecycle, batch-level approval, multi-currency grouping. More realistic for finance teams but materially larger scope. Better suited for P1-48 (Payment Status Tracking) or P1-54 (P2P Operational Queues). Rejected for P1-47 scope.

## Workflow

### Actors

- AP user: reviews payment-eligible invoices, places/releases holds, creates handoff packages, marks ready, exports. Maps to existing buyer/admin permission.
- Admin: same AP abilities for tenant operations.
- System: auto-advances approved invoices to `payment_eligible`, records audit events.
- Approver: read-only payment status visibility on invoices they approved.
- Requester: read-only payment status visibility on their invoices.
- Vendor portal visitor: no access.

### State Model

#### SupplierInvoice payment_status

Add `payment_status` as a separate column (distinct from main `status`):

| Payment Status | Meaning | Entry Condition |
|---|---|---|
| `payment_eligible` | Approved and cleared for payment processing | Auto-transition from `approved` |
| `on_hold` | AP placed a hold; blocked from handoff creation | Manual hold action on `payment_eligible` |
| `payment_ready` | Included in an active ApPaymentHandoff | Handoff creation |
| `handoff_exported` | Handoff package has been exported | Export action |

The main `status` continues to track the review/matching/approval lifecycle (captured → reviewed → matched → approved). The `payment_status` tracks the payment lifecycle orthogonally.

Allowed `payment_status` transitions:

| From | Action | To | Notes |
|---|---|---|---|
| `null` | Auto-advance (system) | `payment_eligible` | Fires when main status becomes `approved` |
| `payment_eligible` | Place hold (AP) | `on_hold` | Requires reason |
| `on_hold` | Release hold (AP) | `payment_eligible` | Requires note |
| `payment_eligible` | Handoff created | `payment_ready` | System action when added to handoff |
| `on_hold` | (handoff creation) | — blocked — | Must release hold first |
| `payment_ready` | Handoff cancelled/invoice removed | `payment_eligible` | Invoice returns to pool |
| `payment_ready` | Handoff exported | `handoff_exported` | System action when export completes |
| `handoff_exported` | (no further transitions in this slice) | — | Terminal for P1-47 |

#### ApPaymentHandoffStatus

| Status | Meaning |
|---|---|
| `draft` | Created from payment-eligible invoices, awaiting AP review |
| `ready` | AP has reviewed and confirmed the handoff is correct |
| `exported` | At least one JSON or CSV export has been generated |
| `cancelled` | Handoff voided — invoices return to `payment_eligible` |

State rules:
- Draft handoffs can be updated (notes, effective payment date).
- Draft → ready requires at least one invoice in the handoff.
- Only `ready` handoffs can be exported.
- Exporting a `ready` handoff moves it to `exported`.
- Exporting an already `exported` handoff creates a new audit/export event but does not change status.
- `draft`, `ready`, and `exported` handoffs can be cancelled by AP/admin with a reason.
- `cancelled` is terminal. Cancelling releases all invoices back to `payment_eligible`.
- Invoices in a cancelled handoff return to `payment_eligible` and can be included in future handoffs.

### Main Flow

1. Invoice is approved (P1-46 outcome — STP or human approval).
2. `AutoAdvanceToPaymentEligible` fires in the same transaction:
   - Sets `payment_status = payment_eligible`, `payment_eligible_at = now`.
   - Records `supplier_invoice.payment_eligible` audit event.
3. AP user opens the **payment queue** page at `/accounts-payable/payment-queue` (a dedicated page under the Finance group, separate from the invoice review queue).
4. AP user selects a payment status tab to filter invoices:
   - **All** — any invoice with a non-null `payment_status`
   - **Payment eligible** — `payment_eligible` invoices ready for handoff creation
   - **On hold** — `on_hold` invoices with hold reason visible
   - **Payment ready** — `payment_ready` invoices already in a handoff
   - **Exported** — `handoff_exported` invoices
   - **Awaiting induction** — invoices with `payment_status = null` (ghost-approved edge case)
5. AP user can:
   - **Place a hold**: click "Hold payment", provide reason. Invoice moves to `on_hold`.
   - **Release a hold**: click "Release hold", provide release note. Invoice returns to `payment_eligible`.
   - **Retry induction**: for awaiting-induction invoices, click "Retry induction" to re-attempt auto-advance.
   - **Select for handoff**: check one or more `payment_eligible` invoices and click "Create handoff" button at the top of the page.
6. AP user fills the handoff form: effective payment date, optional notes.
7. System creates `ApPaymentHandoff` in `draft` status:
   - Creates pivot records for selected invoices.
   - Builds initial snapshot from current invoice/vendor/PO data (recalculable while in draft).
   - Sets each invoice `payment_status = payment_ready`.
   - Records `ap_payment_handoff.created` audit event.
8. AP user reviews the handoff, sees readiness warnings (missing vendor tax ID, missing payment terms).
9. If the user fixes underlying master data in another tab, they can click "Refresh snapshot" (`POST /handoffs/{id}/refresh`) to recalculate warnings and data without leaving draft.
10. AP user clicks "Mark ready". Handoff moves to `ready`. The system recalculates the snapshot one final time before locking — capturing up-to-the-second master data and freezing it for audit.
10. AP user clicks "Export JSON" or "Export CSV". System generates downloadable export, moves handoff to `exported`, sets each invoice `payment_status = handoff_exported`.

The payment queue page also includes an **Active handoffs** section below the invoice table, showing all active handoffs (draft, ready, exported) with links to the handoff workspace.

### Failure Paths

- No `payment_eligible` invoices when creating handoff: return `409`.
- Invoice already in another active handoff: return `409` with handoff reference.
- Invoice currently `on_hold`: return `409` — must release hold first.
- Mixed currencies in handoff creation: return `422` with list of distinct currencies found.
- Stale `lockVersion` on invoice (hold/release): return `409`.
- Stale `lockVersion` on handoff (update/ready/cancel): return `409`.
- Export before ready: return `409`.
- Export cancelled handoff: return `409`.
- Cross-tenant access: return `403` or `404` consistent with adjacent invoice routes.
- Auto-advance failure (constraint or missing data): log error, keep invoice `approved` without `payment_eligible`. Surface a retry action in the UI (see "Awaiting payment induction" below).

## Backend Design

### Domain Ownership

**New domain**: `apps/api/Domains/AccountsPayable`

Supporting domains:
- `Domains/Invoice` — supplier invoice state, payment_status column extension
- `Domains/PurchaseOrder` — PO context for handoff snapshot
- `Domains/Vendor` — vendor name, tax ID for handoff snapshot
- `app/Audit` — audit recording
- `app/Tenancy` — tenant resolution and membership enforcement

### Data Model

#### SupplierInvoice — new columns

Add to `supplier_invoices`:

```sql
payment_status                   VARCHAR(50) NULL
payment_eligible_at              TIMESTAMP NULL
payment_on_hold_by_user_id       CHAR(36) NULL REFERENCES users(id)
payment_on_hold_at               TIMESTAMP NULL
payment_on_hold_reason           TEXT NULL
payment_hold_released_by_user_id CHAR(36) NULL REFERENCES users(id)
payment_hold_released_at         TIMESTAMP NULL
payment_hold_released_note       TEXT NULL
```

Indexes:
- `(tenant_id, payment_status)` for queue queries
- `(tenant_id, payment_status, due_date)` for aging queries

Add `payment_status` to the `$fillable` and `$casts` arrays. Exclude from existing `booted` tenant-validation logic since `payment_status` is not a foreign key column.

#### ApPaymentHandoff

Create `apps/api/Domains/AccountsPayable/Models/ApPaymentHandoff.php` backed by `ap_payment_handoffs`:

```sql
id                              CHAR(36) PRIMARY KEY
tenant_id                       CHAR(36) NOT NULL REFERENCES tenants(id)
number                          VARCHAR(50) NOT NULL
status                          VARCHAR(50) NOT NULL DEFAULT 'draft'
currency                        VARCHAR(3) NOT NULL
total_amount                    DECIMAL(20,4) NOT NULL DEFAULT 0
invoice_count                   INTEGER NOT NULL DEFAULT 0
notes                           TEXT NULL
effective_payment_date          DATE NULL
remittance_reference            VARCHAR(255) NULL
ready_by_user_id                CHAR(36) NULL REFERENCES users(id)
ready_at                        TIMESTAMP NULL
exported_by_user_id             CHAR(36) NULL REFERENCES users(id)
exported_at                     TIMESTAMP NULL
last_export_format              VARCHAR(10) NULL
cancelled_by_user_id            CHAR(36) NULL REFERENCES users(id)
cancelled_at                    TIMESTAMP NULL
cancelled_reason                TEXT NULL
snapshot                        JSONB NOT NULL DEFAULT '{}'
lock_version                    INTEGER NOT NULL DEFAULT 1
created_at                      TIMESTAMP NULL
updated_at                      TIMESTAMP NULL
```

Indexes:
- unique `(tenant_id, number)`
- index `(tenant_id, status, created_at)`
- index `(tenant_id, effective_payment_date)`

#### ApPaymentHandoffInvoice (pivot)

Create `ap_payment_handoff_invoices`:

```sql
id                              CHAR(36) PRIMARY KEY
ap_payment_handoff_id           CHAR(36) NOT NULL REFERENCES ap_payment_handoffs(id)
supplier_invoice_id             CHAR(36) NOT NULL REFERENCES supplier_invoices(id)
tenant_id                       CHAR(36) NOT NULL REFERENCES tenants(id)
```

Indexes:
- unique `(ap_payment_handoff_id, supplier_invoice_id)`
- index `(supplier_invoice_id)` for finding which handoff an invoice belongs to
- index `(ap_payment_handoff_id)` for loading invoices in a handoff

### Snapshot Shape

The snapshot captures immutable approval context for JSON export and UI rendering:

```json
{
  "handoff": {
    "number": "APH-2026-000001",
    "status": "draft",
    "createdAt": "2026-06-18T00:00:00Z",
    "effectivePaymentDate": "2026-07-15",
    "invoiceCount": 3,
    "totalAmount": "45200.00",
    "currency": "MYR",
    "remittanceReference": "AP-JULY-2026-001"
  },
  "invoices": [
    {
      "id": "inv-uuid-1",
      "number": "INV-2026-000042",
      "supplierInvoiceNumber": "SI-2024-7890",
      "invoiceDate": "2026-06-01",
      "dueDate": "2026-07-01",
      "currency": "MYR",
      "subtotalAmount": "15000.00",
      "taxAmount": "1200.00",
      "freightAmount": "500.00",
      "totalAmount": "16700.00",
      "paymentTerms": "Net 30",
      "approvedAt": "2026-06-05T00:00:00Z",
      "approvedByUserId": "user-uuid",
      "approvedByName": "Alice Tan",
      "matchingStatus": "matched",
      "exceptionSummary": null,
      "purchaseOrder": {
        "id": "po-uuid",
        "number": "PO-2026-000123",
        "department": "Operations",
        "costCenter": "OPS-MY"
      },
      "vendor": {
        "id": "ven-uuid",
        "name": "Northwind Traders",
        "contactName": "Maya Lim",
        "contactEmail": "maya@example.test",
        "taxId": null
      },
      "lines": [
        {
          "lineNumber": 1,
          "description": "Office chair - Ergonomic",
          "quantity": "10.000",
          "unitOfMeasure": "EA",
          "unitPrice": "1500.00",
          "lineTotal": "15000.00"
        }
      ]
    }
  ],
  "totalByCurrency": {
    "MYR": "45200.00"
  },
  "readinessWarnings": [
    "Vendor Northwind Traders has no tax ID on file",
    "Invoice INV-2026-000044 has no payment terms"
  ]
}
```

### Domain Structure

```txt
apps/api/Domains/AccountsPayable/
  Actions/
    AutoAdvanceToPaymentEligible.php
    RetryPaymentInduction.php
    PlaceSupplierInvoiceOnPaymentHold.php
    ReleaseSupplierInvoicePaymentHold.php
    CreateApPaymentHandoff.php
    BuildApPaymentHandoffSnapshot.php
    RefreshApPaymentHandoffSnapshot.php
    MarkApPaymentHandoffReady.php
    ExportApPaymentHandoff.php
    CancelApPaymentHandoff.php
  Data/
    ApPaymentHandoffSnapshotData.php
  Http/
    Controllers/
      ApPaymentHandoffController.php
    Requests/
      CreateApPaymentHandoffRequest.php
      PlaceInvoiceOnHoldRequest.php
      ReleaseInvoiceHoldRequest.php
      MarkApPaymentHandoffReadyRequest.php
      CancelApPaymentHandoffRequest.php
    Resources/
      ApPaymentHandoffResource.php
  Models/
    ApPaymentHandoff.php
    ApPaymentHandoffInvoice.php  (pivot)
  Policies/
    ApPaymentHandoffPolicy.php
  States/
    ApPaymentHandoffStatus.php
  routes/
    api.php
  tests/
    ApPaymentHandoffApiTest.php
    SupplierInvoicePaymentApiTest.php
```

Use only the files the implementation needs. Empty folders should not be created.

### Domain Behavior

**`AutoAdvanceToPaymentEligible`**:

- Accepts a supplier invoice that just reached `approved`.
- Instead of checking exclusively for `approved` main status, checks that `payment_status` is null (has not yet been processed).
- Sets `payment_status = payment_eligible`, `payment_eligible_at = now`.
- Increments `lock_version`.
- Records `supplier_invoice.payment_eligible` audit event.
- Idempotent: skips if `payment_status` is already set.

**`RetryPaymentInduction`**:

- Accepts supplier invoice ID, actor.
- Validates main `status = approved` and `payment_status = null`.
- Calls the same logic as `AutoAdvanceToPaymentEligible` (idempotent guard still applies).
- Sets `payment_status = payment_eligible`, `payment_eligible_at = now`.
- Increments `lock_version`.
- Records `supplier_invoice.payment_eligible` audit event (same event name as auto-advance).
- Returns the invoice to the payment-eligible pool. Available as a human-callable retry action when the "ghost approved" edge case occurs.

**`PlaceSupplierInvoiceOnPaymentHold`**:

- Accepts supplier invoice ID, actor, reason, lockVersion.
- Locks the invoice row.
- Validates `payment_status = payment_eligible` and `lockVersion`.
- Sets `payment_status = on_hold`, `payment_on_hold_by_user_id`, `payment_on_hold_at`, `payment_on_hold_reason`.
- Increments `lock_version`.
- Records `supplier_invoice.payment_hold_placed`.

**`ReleaseSupplierInvoicePaymentHold`**:

- Accepts supplier invoice ID, actor, release note, lockVersion.
- Locks the invoice row.
- Validates `payment_status = on_hold` and `lockVersion`.
- Sets `payment_status = payment_eligible`, clears hold fields, sets release actor/timestamp/note.
- Increments `lock_version`.
- Records `supplier_invoice.payment_hold_released`.

**`CreateApPaymentHandoff`**:

- Accepts array of invoice IDs, optional effectivePaymentDate, notes, actor.
- Validates all invoices belong to tenant.
- Validates all invoices have `payment_status = payment_eligible`.
- Validates no invoice is already in another active handoff.
- **Validates currency homogeneity**: all selected invoices must share the same `currency` code. Returns `422` with a list of distinct currencies found if mixed.
- Creates `ApPaymentHandoff` in `draft` status with generated number.
- Calls `BuildApPaymentHandoffSnapshot` to assemble the snapshot.
- Creates pivot records, updates each invoice `payment_status = payment_ready`.
- Records `ap_payment_handoff.created` audit event.
- Uses a database transaction.

**`BuildApPaymentHandoffSnapshot`**:

- Accepts array of supplier invoices (eager-loaded with vendor, PO, lines).
- Assembles JSON snapshot following the snapshot shape above.
- Calculates readiness warnings by checking current master data (vendor tax ID, payment terms, etc.).
- Returns the snapshot data object.
- Callable at handoff creation (initial snapshot), on-demand via the refresh endpoint while in `draft`, and one final time inside `MarkApPaymentHandoffReady` before locking.

**`MarkApPaymentHandoffReady`**:

- Lock handoff row.
- Validates status is `draft` and `lockVersion`.
- Validates at least one invoice exists.
- **Recalculates snapshot**: eager-loads invoices with vendor, PO, and lines, then calls `BuildApPaymentHandoffSnapshot` a final time with current data. This captures up-to-the-second master data changes before freezing.
- Overwrites the handoff `snapshot` column with the fresh snapshot.
- Sets status to `ready`, `ready_by_user_id`, `ready_at`.
- Increments `lock_version`.
- Records `ap_payment_handoff.ready`.

**`ExportApPaymentHandoff`**:

- Lock handoff row.
- Validates status is `ready` or `exported`, and `lockVersion`.
- For `ready` handoffs: sets status to `exported`, `exported_by_user_id`, `exported_at`, `last_export_format`.
- For already `exported` handoffs: updates `last_export_format` and re-records export audit.
- Iterates invoices, sets `payment_status = handoff_exported` if they were `payment_ready`.
- Returns the snapshot as JSON or generates CSV from the snapshot data.
- Records `ap_payment_handoff.exported` audit event.

**`RefreshApPaymentHandoffSnapshot`**:

- Validates handoff status is `draft`.
- Eager-loads invoices with vendor, PO, and lines.
- Calls `BuildApPaymentHandoffSnapshot` to recalculate the snapshot with current master data.
- Overwrites the handoff `snapshot` column.
- Records `ap_payment_handoff.snapshot_refreshed` audit event.
- Returns the updated snapshot data.

**`CancelApPaymentHandoff`**:

- Lock handoff row.
- Validates status is not already `cancelled`.
- Sets status to `cancelled`, `cancelled_by_user_id`, `cancelled_at`, `cancelled_reason`.
- Iterates invoices, sets `payment_status = payment_eligible` (returning them to the pool).
- Increments `lock_version`.
- Records `ap_payment_handoff.cancelled`.

### Integration Point

Modify the approval outcome actions (`MarkSupplierInvoiceApproved` and the STP path) to call `AutoAdvanceToPaymentEligible` after the approved state is committed:

```php
// Inside MarkSupplierInvoiceApproved, after status=approved is persisted:
app(AutoAdvanceToPaymentEligible::class)->execute($invoice);
```

This runs in the same transactional boundary. If it fails, the approval outcome is already committed — the invoice remains `approved` without `payment_eligible`, and a manual retry action recovers.

### Authorization

**`ApPaymentHandoffPolicy`**:
- AP user (buyer/admin) can view, create, update, mark-ready, export, and cancel tenant handoffs.
- Approver can view only if also buyer/admin.
- Requester has no handoff access.
- Vendor portal visitors have no access.
- Every action verifies: authenticated session, current tenant, handoff tenant_id, linked invoice tenant_ids.

**Hold/release authorization**: enforced through `SupplierInvoicePolicy` extensions (buyer/admin with tenant invoice scoping).

### Audit Metadata

| Event | Trigger |
|---|---|
| `supplier_invoice.payment_eligible` | Auto-advance from approved |
| `supplier_invoice.payment_hold_placed` | Hold action |
| `supplier_invoice.payment_hold_released` | Release action |
| `ap_payment_handoff.created` | Handoff creation |
| `ap_payment_handoff.updated` | Handoff field update |
| `ap_payment_handoff.snapshot_refreshed` | Snapshot recalculated while in draft |
| `ap_payment_handoff.ready` | Marked ready |
| `ap_payment_handoff.exported` | Export action |
| `ap_payment_handoff.cancelled` | Cancel action |

Audit metadata includes: handoff id and number, invoice ids, invoice numbers, vendor id, total amount, currency, effective payment date, previous status, next status, export format, hold reason, cancellation reason.

### Concurrency

All mutation actions lock the target row (`lockForUpdate()`) and check `lock_version`. The `ApPaymentHandoff` export action must lock the handoff row before iterating invoices to prevent concurrent modifications.

Handoff `lock_version` and individual invoice `lock_version` are checked independently. A stale lock on either returns `409`.

## API Contract

Add tenant-scoped authenticated routes:

```txt
# Invoice payment status
POST /api/supplier-invoices/{supplierInvoice}/place-hold
POST /api/supplier-invoices/{supplierInvoice}/release-hold
POST /api/supplier-invoices/{supplierInvoice}/retry-payment-induction

# Payment handoff
GET    /api/accounts-payable/handoffs
POST   /api/accounts-payable/handoffs
GET    /api/accounts-payable/handoffs/{handoff}
PATCH  /api/accounts-payable/handoffs/{handoff}
POST   /api/accounts-payable/handoffs/{handoff}/refresh
POST   /api/accounts-payable/handoffs/{handoff}/mark-ready
POST   /api/accounts-payable/handoffs/{handoff}/cancel
GET    /api/accounts-payable/handoffs/{handoff}/export.json
GET    /api/accounts-payable/handoffs/{handoff}/export.csv
```

Extend `GET /api/supplier-invoices` with:
- `payment_status` filter: `payment_eligible`, `on_hold`, `payment_ready`, `handoff_exported`, `none`
- `requiresPaymentAction` filter: shorthand for `payment_eligible,on_hold`

Extend `SupplierInvoiceQueueResource` with:
```json
{
  "paymentStatus": "payment_eligible",
  "paymentOnHoldReason": null,
  "paymentOnHoldByUserId": null,
  "paymentOnHoldAt": null,
  "paymentEligibleAt": "2026-06-18T00:00:00Z",
  "activeHandoffId": null,
  "activeHandoffNumber": null
}
```

Expected operation IDs:
- `placeSupplierInvoiceOnPaymentHold`
- `releaseSupplierInvoicePaymentHold`
- `retryPaymentInduction`
- `listApPaymentHandoffs`
- `createApPaymentHandoff`
- `showApPaymentHandoff`
- `updateApPaymentHandoff`
- `refreshApPaymentHandoffSnapshot`
- `markApPaymentHandoffReady`
- `cancelApPaymentHandoff`
- `exportApPaymentHandoffJson`
- `exportApPaymentHandoffCsv`

Expected schemas:
- `PlaceInvoiceOnHoldRequest`
- `ReleaseInvoiceHoldRequest`
- `CreateApPaymentHandoffRequest` — validates `invoice_ids` are all `payment_eligible`, same tenant, **same currency**, and not in another active handoff
- `UpdateApPaymentHandoffRequest`
- `MarkApPaymentHandoffReadyRequest`
- `CancelApPaymentHandoffRequest`
- `ApPaymentHandoffResponse`
- `ApPaymentHandoffStatus`
- `ApPaymentHandoffSnapshot`

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas in the web feature. Do not duplicate contract response types in app code.

## Export Format

### JSON

JSON is the canonical structured export. It includes:
- export metadata (`exportedAt`, `format`)
- handoff identifiers and summary
- per-invoice structured data with vendor, PO, line items, approval context
- readiness warnings

Envelope:
```json
{
  "exportedAt": "2026-06-18T00:00:00Z",
  "format": "json",
  "handoff": { ... }
}
```

### CSV

CSV is line-oriented. Each row represents one invoice line item, with header-level values repeated per row. This enables finance teams to import into basic tools.

Recommended CSV headers:

```txt
handoff_number, handoff_status, effective_payment_date, remittance_reference,
handoff_total_amount, handoff_currency,
invoice_number, supplier_invoice_number, invoice_date, due_date, payment_terms,
vendor_name, vendor_tax_id, vendor_contact_email,
po_number, department, cost_center,
currency, subtotal_amount, tax_amount, freight_amount, total_amount,
approved_at, approved_by_name, matching_status,
line_number, description, quantity, unit_of_measure, unit_price, line_total,
notes
```

## Seed and Demo Data

Dem `DemoProcurementLifecycleSeeder` seeds the following invoices with payment state:

| Invoice | Status | Payment Status | Description |
|---|---|---|---|
| INV-2026-DEMO-004 | Reviewed | `null` | Awaiting induction — no payment_status set, appears in "Awaiting induction" tab |
| INV-2026-DEMO-005 | Reviewed | `null` | Matched, no payment_status (awaiting induction) |
| INV-2026-DEMO-006 | Reviewed | `null` | Mismatched with exception, no payment_status |
| INV-2026-DEMO-007 | Reviewed | `null` | Pending matching, no payment_status |
| INV-2026-DEMO-008 | Approved | `payment_eligible` | Approved, matched, eligible for handoff creation |
| INV-2026-DEMO-009 | Approved | `on_hold` | Placed on hold with reason "Vendor bank details pending confirmation." |
| INV-2026-DEMO-010 | Approved | `payment_ready` | Included in a Ready handoff (HDOFF-DEMO-001) with snapshot |

The seeded payment-eligible, on-hold, and payment-ready invoices (DEMO-008 through DEMO-010) reference real seeded purchase orders, vendors, invoice lines, and include match results from `pickTwoLines()` matching the existing pattern.

Future slices should extend seed data to include:
- Invoice in "ghost approved" state (`status = approved`, `payment_status = null`) — used to verify the "Awaiting payment induction" UI
- A payment handoff in `draft` status with multiple invoices
- A payment handoff in `exported` status with export metadata
- An invoice in `handoff_exported` status

## Testing and Verification

### API Tests

Add focused tests for:

**Invoice payment status:**
- approved invoice auto-advances to `payment_eligible`
- AP user can place hold with reason
- placing hold requires `lockVersion`
- AP user can release hold with note
- cross-tenant hold/release denied
- stale lockVersion returns conflict
- hold audit event recorded
- auto-advance failure leaves invoice approved with `payment_status = null`
- invoice with `payment_status = null` appears with "Awaiting payment induction" indicator
- retry-payment-induction succeeds and moves invoice to `payment_eligible`
- retry-payment-induction is idempotent (returns success if already `payment_eligible`)

**Payment handoff:**
- creating handoff from payment-eligible invoices succeeds
- creating handoff with on-hold invoice returns 409
- creating handoff with invoice already in another handoff returns 409
- handoff snapshot includes correct invoice, vendor, PO, approval data
- AP user can update handoff notes and effective payment date with lockVersion
- mark-ready validates at least one invoice exists
- JSON export returns structured payload and moves ready handoff to exported
- CSV export returns text/csv with expected headers and line rows
- repeat export records audit without creating a new handoff
- cancelled handoff cannot be exported
- cancelled handoff returns invoices to payment_eligible
- cross-tenant handoff CRUD denied
- AP user can create view update ready export cancel
- non-AP user (requester) cannot access handoff endpoints
- real Sanctum/session route stack succeeds before logout and returns 401 after logout

### Web Tests

Add focused tests for:

**Payment queue page (separate from invoice review queue):**
- Payment status tabs (All, Payment eligible, On hold, Payment ready, Exported, Awaiting induction) filter correctly
- Payment status badge renders for each state in the table
- Header count includes payment-related invoice counts
- "Awaiting induction" tab shows invoices with `payment_status = null`
- "Retry induction" button in awaiting-induction invoice detail panel triggers retry-payment-induction
- Retry induction moves invoice out of "Awaiting induction" into "Payment eligible"
- Payment-eligible invoice shows hold button in detail panel
- Hold form requires reason
- On-hold invoice shows hold details and release button
- Retry induction panel shows for invoices with `payment_status = null` when selected

**Payment actions:**
- Payment-eligible invoice shows hold button
- Hold form requires reason
- On-hold invoice shows hold details and release button
- Release form requires note
- Hold and release update status without page navigation
- Stale lockVersion error is visible

**Handoff workflow:**
- Handoff creation form shows selected invoices
- Handoff list shows tenant-scoped handoffs
- Handoff workspace shows invoice list, total, readiness warnings
- Mark-ready button works for draft handoffs
- Export JSON button generates download
- Export CSV button generates download
- Cancel handoff with reason returns invoices to eligible
- Loading, empty, error, permission-denied, conflict states

### Verification Commands

Expected implementation verification:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=SupplierInvoicePayment
cd apps/api && php artisan test --filter=ApPaymentHandoff
cd apps/api && php artisan test --filter=SupplierInvoiceApproval   # regression - auto-advance integration
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
pnpm lint
```

## Non-Goals Reiteration

This slice explicitly does NOT include:
- Payment execution or direct payment processing
- Payment status tracking (scheduled, paid, failed, voided, remitted)
- Direct ERP integration or accounting system sync
- Multi-currency handoff grouping
- Payment batch approval workflow
- Automated payment scheduling by vendor terms
- Credit memo and invoice adjustment handling
- Vendor banking master data or payment method fields
- Payment method selection
- Remittance advice delivery to vendor portal
- Configurable export mapping templates or tenant-specific adapters

## Completion Definition

This slice is complete when:
- Approved invoices automatically become payment-eligible within the same transactional boundary as approval.
- If auto-advance fails, the invoice shows an "Awaiting payment induction" warning in the queue with a manual retry action.
- AP users can place and release payment holds with reasons, audit trail, and lock-version concurrency.
- AP users can create snapshot-based payment handoff packages from selected eligible invoices.
- Handoff supports draft → ready → exported → cancelled states mirroring the PO handoff pattern.
- Invoices in a cancelled handoff return to the payment-eligible pool.
- Handoff JSON and CSV exports contain structured invoice-level, vendor-level, and line-item data following the established export pattern.
- The payment readiness queue is surfaced as a dedicated **Payment queue** page at `/accounts-payable/payment-queue` under the Finance sidebar group, separate from the invoice review queue.
- An **Active handoffs** section on the payment queue page lists all active handoffs with links to the handoff workspace.
- All states, transitions, and exports are tenant-scoped, authorized, audited, and protected by lock-version concurrency.
- OpenAPI endpoints are generated and consumed by `@cognify/api-client`.
- Seeded demo data covers payment-eligible (DEMO-008), on-hold (DEMO-009), and payment-ready with a Ready handoff (DEMO-010 + HDOFF-DEMO-001).
- Downstream P1-48 (Payment Status Tracking) has a clear `handoff_exported` precondition to consume.

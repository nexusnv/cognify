# Invoice Exception Workflow Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-17
- Release scope: P1 core procure-to-pay lifecycle, slice P1-45 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-45`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
- Downstream slices:
  - P1-46 Invoice Approval — this slice produces `ready_for_approval` invoices as its terminal handoff

## Roadmap Analysis

P1-45 asks Cognify to route invoice mismatches to the right owner (requester, buyer, receiver, finance, or vendor), capture resolution notes, adjusted values, supporting evidence, approval impact, and audit events.

P1-44 gave Cognify durable per-line, per-dimension match results for two-way and three-way matching. Each match result has a pass/fail status with applied tolerances. When a match fails, that fact is stored but there is no workflow to assign, track, or resolve the exception — the invoice simply sits in `reviewed` with `matching_status=mismatch`.

P1-45 builds on those match results by adding the exception routing and resolution layer. Exceptions are first-class records with stable business identity keys decoupled from volatile match-result database rows. Resolution captures either proposed adjusted payment values (without mutating original invoice data) or explanatory evidence with human variance acceptance. The state machine handles both paths: mathematical correction via matching re-run and human override via all-exceptions-resolved entry.

## Problem

After matching detects a mismatch between invoice, PO, and receipt quantities or prices, Cognify cannot route the mismatch to the person who should resolve it. Without an exception workflow:

- AP users must manually determine who should fix each mismatch by paging through email or spreadsheets.
- Mismatches are not tracked as assignable, actionable work items.
- Resolution notes, corrected values, and supporting evidence are not captured as durable workflow facts.
- There is no audit trail of who resolved what, when, or why.
- The approval queue (P1-46) would need to guess whether mismatches have been investigated.
- AP users cannot easily see "my open exceptions" as a personal work queue.

## Goals

- Add durable `SupplierInvoiceException` records keyed by a stable composite business key (tenant, invoice, dimension, match type, invoice line), not by volatile match-result database primary keys.
- Route exceptions to the right owner based on mismatch dimension using tenant-configurable routing rules that resolve to specific P2P document owners from the record graph, not generic roles.
- Support two resolution paths: propose an adjusted payment value (stored as an overlay on the exception, never mutating the original captured invoice data) OR provide explanation + evidence with a human variance waiver.
- Track exception statuses: `open`, `in_progress`, `resolved`, `rejected`.
- On post-resolution matching: only re-run the P1-44 matching engine when at least one exception used a `value_adjustment` resolution (since only those change the underlying math). Exceptions resolved purely by explanation with human variance acceptance advance to `ready_for_approval` directly.
- Advance exception-resolved, matching-passed invoices to a new `ready_for_approval` status for P1-46 handoff.
- Handle rejected exceptions by requiring an escalation target user, preventing orphaned ownership.
- Surface exception assignments, counts, and dimensions in the AP invoice queue.
- Provide a "My exceptions" personal work view for assigned owners.
- Support evidence attachments on exceptions using the existing Attachment domain.
- Record audit events for exception assignment, resolution, rejection, and status transitions.
- Expose OpenAPI endpoints and consume them from `@cognify/api-client`.
- Seed realistic exception demo states with open, in-progress, and resolved examples.

## Non-Goals

- Invoice approval through the shared Approval domain (P1-46 scope).
- Payment readiness, AP export, payment status, or credit memo handling (P1-47 through P1-49).
- Purchase order change order creation triggered by exception resolution.
- Automated tolerance adjustment or learning.
- SLA-based escalation of stale exceptions.
- Vendor-facing exception portal or notification.
- AI-driven exception triage or resolution suggestions.
- Multi-currency or formal tax rule standardization beyond the existing matching values.

## Approaches Considered

### 1. Dedicated exception records in Invoice domain (selected)

Create `SupplierInvoiceException` and `SupplierInvoiceExceptionEvidence` models, an `ExceptionRoutingService`, and resolution actions within the existing `Domains/Invoice` boundary. Each failed match result generates one exception record keyed by a stable composite business identity. Exceptions are assignable, resolvable, and auditable. Matching re-run is gated by resolution type to avoid the explanation loop deadlock. Original invoice data is never mutated. Selected because it produces durable, auditable, routeable exception records that P1-46 can consume directly.

### 2. Extend match results with resolution fields

Add `assigned_to_user_id`, `resolution_notes`, and `resolution_status` directly to `SupplierInvoiceMatchResult`. Avoids new tables but mixes resolution logic with match computation. Rejected because re-running matching (which deletes and recreates match results) would destroy resolution data, and the routing/assignment model would be conflated with comparison results.

### 3. Reuse Approval domain for exception tasks

Route each mismatch as an approval-like task through the shared Approval domain. Reuses existing assignment, escalation, and SLA infrastructure. Rejected because exception resolution semantics (propose adjusted payment value, provide evidence, accept variance) differ fundamentally from approval actions (approve, reject, request changes). The Approval domain's state machine does not map cleanly to value adjustment or evidence capture.

## Workflow

### Actors

- AP user: views the exception queue, assigns exceptions to owners, confirms resolution, escalates rejected exceptions.
- Buyer: receives price and quantity exceptions automatically via P2P context resolution (the buyer who created the PO). Resolves by proposing adjusted value or providing evidence with variance acceptance.
- Receiver: receives three-way quantity exceptions automatically via P2P context resolution (the user who recorded the goods receipt). Confirms or disputes receipt quantities.
- Finance user: receives vendor identity, tax, freight, and invoice total exceptions automatically. Investigates potential fraud or coding issues.
- Requester: may be assigned exceptions when the mismatch relates to requisition scope or delivery acceptance.
- Admin: views and reassigns any exception across the tenant.
- System: creates exceptions from match failures, applies routing rules (resolving to specific users from the P2P document graph), re-runs matching after value-adjusted resolutions.

### State Model

Extend `SupplierInvoiceStatus`:

| Status | Meaning |
|---|---|
| `captured` | Invoice captured, awaiting AP review (unchanged) |
| `in_review` | AP review in progress (unchanged) |
| `needs_information` | Invoice blocked pending more info (unchanged) |
| `reviewed` | AP review passed, ready for matching (unchanged) |
| `mismatch` | Matching detected one or more failed dimensions; exception workflow active |
| `ready_for_approval` | All exceptions resolved (either matching passes after value adjustment, or all exceptions accepted by human override), ready for P1-46 |

Allowed supplier invoice status transitions:

| From | Action | To |
|---|---|---|
| `reviewed` | matching completes with failures | `mismatch` |
| `mismatch` | all exceptions resolved + (matching passes OR all resolved by human acceptance) | `ready_for_approval` |
| `mismatch` | all exceptions resolved + matching re-run still fails | `mismatch` |
| `mismatch` | AP determines root cause is missing data | `needs_information` |

### Exception Statuses

| Status | Meaning |
|---|---|
| `open` | Exception created, not yet assigned or in progress |
| `in_progress` | Assignee is actively working the exception |
| `resolved` | Assignee has provided resolution (proposed adjustment or explanation with variance acceptance) |
| `rejected` | Assignee determined the exception cannot be resolved; escalation to a specific target user required |

Allowed exception transitions:

| From | Action | To |
|---|---|---|
| `open` | assign | `in_progress` |
| `open` | self-assign | `in_progress` |
| `open` | auto-assign by routing rule | `in_progress` |
| `in_progress` | resolve with value_adjustment or explanation | `resolved` |
| `in_progress` | mark needs reassignment | `open` |
| `in_progress` | reject with escalation target | `rejected` |
| `rejected` | reassign to escalation target | `in_progress` |

### Critical Architectural Decision 1: Explanation Loop Deadlock Prevention

When an exception is resolved by **explanation** (e.g., "Surcharge approved via email"), the underlying invoice data values do not change. Re-running the P1-44 matching engine would evaluate the same raw numbers, calculate the same variance, and produce the same mismatch result — creating an infinite deadlock.

The state machine breaks this loop by gating matching re-run on resolution type:

- If at least one exception on the invoice was resolved with `value_adjustment`: re-run the matching engine (since the underlying values for that invoice line changed).
- If ALL exceptions were resolved with `explanation` (variance accepted by human): do NOT re-run the matching engine. Instead, check the all-exceptions-resolved condition directly.

The entry criteria for `ready_for_approval`:

$$ReadyForApproval = (MatchingPasses) \lor (AllExceptionsStatus = Resolved \land NoValueAdjustmentsPending)$$

This means:
- Value adjustments → matching re-run → if pass, advance; if fail, stay mismatch.
- Human explanations → no matching re-run → all resolved → advance directly.

### Critical Architectural Decision 2: Captured Invoice Data Immutability

Invoice data is a legal tax document issued by a vendor. Programmatically mutating captured line values (`supplier_invoice_lines.unit_price`, `line_subtotal`) to force a matching pass is a compliance risk. The database must reflect the physical document for audit, ERP export, and tax filing purposes.

Resolution path for value adjustments:
- The exception record stores the proposed adjusted value (`adjusted_value` on `supplier_invoice_exceptions`).
- The original captured invoice line values remain immutable.
- Downstream slices (P1-46 approval, P1-47 payment) read the exception's `adjusted_value` as the proposed payment amount for short-payment or debit memo processing.
- The matching engine re-run uses the adjusted value temporarily for computation but never persists it to the invoice line.

### Triggering

1. Matching completes for a `reviewed` invoice (auto or manual).
2. If all match results pass → invoice stays `reviewed` with `matching_status=matched`. No exceptions created.
3. If any match result fails → `CreateInvoiceExceptions` action fires.
4. For each failed match result, one `SupplierInvoiceException` record is created with a stable composite key: `(tenant_id, supplier_invoice_id, dimension, match_type, supplier_invoice_line_id)`.
5. `ExceptionRoutingService` resolves the specific owner user for each dimension by tracing the P2P document graph.
6. Invoice status transitions from `reviewed` to `mismatch`.
7. Audit event `supplier_invoice.exceptions_created` is recorded.

### Main Resolution Flow

1. Assigned owner sees the exception in their "My exceptions" queue or in the invoice workspace.
2. Owner opens the exception, reviews expected vs actual values, tolerance applied, and notes.
3. Owner resolves by choosing one of:
   - **Propose adjusted payment value**: enters a corrected value. The exception stores `adjusted_value` and `resolution_type=value_adjustment`. The original invoice line is NOT modified. The adjusted value becomes input for downstream payment processing.
   - **Provide explanation with variance acceptance**: adds notes and optional evidence attachments explaining why the variance is acceptable. The exception sets `variance_accepted_by_human=true`.
4. Owner submits resolution. Exception status becomes `resolved`.
5. System evaluates post-resolution state:
   - If any exception on this invoice has `resolution_type=value_adjustment`:
     - Run `RunPostResolutionMatching` which calls the matching engine.
     - If matching passes → invoice transitions to `ready_for_approval`.
     - If matching still fails → invoice stays `mismatch`, new exceptions created for new failures.
   - If ALL exceptions have `resolution_type=explanation` (no value adjustments):
     - Do NOT re-run matching engine (values unchanged → same result guaranteed).
     - Invoice transitions directly to `ready_for_approval` (all exceptions resolved by human acceptance).
6. Audit event recorded for each resolution and for the post-resolution matching outcome.

### Exception Rejection

When the assignee determines the exception cannot be resolved (e.g., the vendor refuses to correct the invoice, or the dispute requires management escalation), they reject with notes AND an escalation target user. The `assigned_to_user_id` is transferred to the escalation target. The exception status becomes `rejected`. The invoice stays in `mismatch`. The escalation target sees the exception in their queue with the rejection context.

This prevents the rejected-state orphan: ownership never stays with the rejecting user after they reject.

### Default Routing Rules

Routing rules map mismatch dimension + match type to a specific user resolved from the P2P document graph, not to a generic role. Stored as tenant-configurable data; hard-coded defaults for this slice.

| Dimension | Match type | Resolution strategy | Fallback |
|---|---|---|---|
| `vendor_identity` | two_way | Purchase order `created_by_user_id` | Tenant admin |
| `invoice_total` | two_way | Purchase order `created_by_user_id` | Tenant admin |
| `quantity` | two_way | Purchase order `created_by_user_id` (buyer who ordered) | Tenant admin |
| `quantity` | three_way | Latest goods receipt `recorded_by_user_id` (receiver who scanned) | Fall to two-way owner, then admin |
| `unit_price` | two_way | Purchase order `created_by_user_id` (buyer who negotiated) | Tenant admin |
| `line_total` | two_way | Purchase order `created_by_user_id` | Tenant admin |
| `tax` | two_way | Tenant finance role → first user with finance role | Tenant admin |
| `freight` | two_way | Tenant finance role → first user with finance role | Tenant admin |

**Resolution strategy for P2P document graph tracing:**

- `purchase_order` relationship: `ExceptionRoutingService` loads the invoice's linked purchase order, reads `created_by_user_id`, and checks that user is an active tenant member. If the PO creator is inactive or not found, falls back to the generic role match (buyer), then admin.
- `goods_receipt` relationship: For three-way quantity exceptions, the service queries `goods_receipts` for the purchase order, orders by `recorded_at DESC`, and takes the `recorded_by_user_id` of the most recent receipt. If no goods receipt exists (should not happen for three-way mismatch, but defensive), falls to two-way quantity owner.
- Role-based assignment (tax, freight): queries tenant members with the matching role, returns the first active user. If none, falls to admin.

The `ExceptionRoutingService` first attempts P2P graph resolution. If the resolved user is inactive, cross-tenant, or cannot be determined, it falls back to the generic role assignment, and finally to the tenant admin.

## Backend Design

### Domain Ownership

The owning domain remains `apps/api/Domains/Invoice`. Exception behavior is closely coupled with match results and invoice records. Creating a separate domain would add unnecessary boundary overhead before P1-46 clarifies the broader AP aggregate shape.

Supporting domains:
- `Domains/Invoice` — extended with exception models, actions, services, and controllers
- `Domains/PurchaseOrder` — PO and line data for context; PO `created_by_user_id` for routing
- `Domains/Receiving` — goods receipt `recorded_by_user_id` for three-way routing
- `app/Audit` — audit recording
- `app/Tenancy` — tenant resolution and membership enforcement
- `app/Attachment` — evidence file association

### Data Model

New table `supplier_invoice_exceptions`:

```
id                              UUID PK
tenant_id                       FK to tenants
supplier_invoice_id             FK to supplier_invoices
supplier_invoice_line_id        UUID FK to supplier_invoice_lines (nullable — null for header-level exceptions like vendor_identity)
purchase_order_line_id          UUID FK to purchase_order_lines (nullable — null for header-level exceptions)
dimension                       VARCHAR not null — vendor_identity, quantity, unit_price, line_total, tax, freight, invoice_total
match_type                      VARCHAR not null — two_way, three_way
expected_value                  DECIMAL(18,4) nullable — snapshot of what was expected
actual_value                    DECIMAL(18,4) nullable — snapshot of what was invoiced
applied_tolerance_percent       DECIMAL(6,4) nullable
assigned_to_user_id             FK to users nullable
assigned_by_user_id             FK to users nullable
assigned_at                     TIMESTAMP nullable
status                          VARCHAR not null default 'open' — open, in_progress, resolved, rejected
resolution_type                 VARCHAR nullable — value_adjustment, explanation
adjusted_value                  DECIMAL(18,4) nullable — proposed payment value when resolution_type=value_adjustment.
                                Original invoice line data is NEVER mutated. This overlay value is read by
                                downstream P1-46/P1-47 for short-payment or debit memo calculation.
variance_accepted_by_human      BOOLEAN default FALSE — set to true when a human reviewer accepts the
                                variance via explanation. Allows the state machine to advance to
                                ready_for_approval without re-running the matching engine.
resolution_notes                TEXT nullable
resolved_by_user_id             FK to users nullable
resolved_at                     TIMESTAMP nullable
escalated_to_user_id            FK to users nullable — required for rejected exceptions; the escalation target
lock_version                    INTEGER default 1
timestamps
```

Unique composite index (stable business identity key — NOT linked to volatile match-result PKs):

```sql
CREATE UNIQUE INDEX unique_exception_key
ON supplier_invoice_exceptions (
    tenant_id,
    supplier_invoice_id,
    dimension,
    match_type,
    supplier_invoice_line_id
) NULLS NOT DISTINCT;
```

**Critical note on NULL equality:** For header-level exceptions (`vendor_identity`, `invoice_total`), `supplier_invoice_line_id` is `NULL`. In standard SQL, `NULL = NULL` evaluates to `false`, which would cause the unique index to treat multiple rows with `NULL` line IDs as distinct — breaking idempotency. Using `NULLS NOT DISTINCT` (PostgreSQL 15+) forces the database to treat `NULL` values as equal for uniqueness. This ensures that re-running matching produces exactly one exception per `(tenant_id, supplier_invoice_id, dimension, match_type, supplier_invoice_line_id=null)` combination.

As a secondary defense, `CreateInvoiceExceptions` performs an application-level existence check using Eloquent's `whereNull` handling before inserting, so the system is protected even if the database engine lacks `NULLS NOT DISTINCT` support.

This index serves as the business identity key for deduplication and reverse lookups. It survives matching re-runs because it does not reference `supplier_invoice_match_result_id`, which is deleted and recreated by P1-44.

Other indexes:
- `supplier_invoice_id`, `status` — queue queries
- `assigned_to_user_id`, `status` — "my exceptions" queries
- `tenant_id`, `status` — tenant-scoped admin view

New table `supplier_invoice_exception_evidence`:

```
id                              UUID PK
tenant_id                       FK to tenants
supplier_invoice_exception_id   FK
attachment_id                   FK to attachments
uploaded_by_user_id             FK to users
notes                           TEXT nullable
timestamps
```

Indexes:
- `supplier_invoice_exception_id` — evidence per exception

Extend `supplier_invoices`:

- Add `mismatch` and `ready_for_approval` to the status enum.
- New column `exception_summary` JSON nullable — denormalized `{openCount, resolvedCount, rejectedCount, dimensionNames[], hasValueAdjustments}`.

No changes to `supplier_invoice_lines`. Original captured values remain immutable.

### Routing Rules Storage

Store routing rules as a JSON config in tenant settings. The `MatchingToleranceConfigData` object already supports dimension-keyed config; extend it to include routing rules with P2P graph resolution hints:

```json
{
  "exception_routing": {
    "unit_price": {
      "resolutionStrategy": "purchase_order_creator",
      "fallbackRole": "buyer",
      "ultimateFallback": "admin"
    },
    "quantity#three_way": {
      "resolutionStrategy": "goods_receipt_recorder",
      "fallbackRole": "receiver",
      "ultimateFallback": "buyer"
    },
    "vendor_identity": {
      "resolutionStrategy": "role",
      "role": "finance",
      "ultimateFallback": "admin"
    }
  }
}
```

If tenant settings do not exist, the system falls back to hard-coded defaults in `ExceptionRoutingService`.

### Domain Structure

```
apps/api/Domains/Invoice/
  Actions/
    CreateInvoiceExceptions.php
    AssignInvoiceException.php
    ResolveInvoiceException.php
    RejectInvoiceException.php
    RunPostResolutionMatching.php
  Services/
    ExceptionRoutingService.php
  Models/
    SupplierInvoiceException.php
    SupplierInvoiceExceptionEvidence.php
  Data/
    ExceptionRoutingRuleData.php
    DimensionRoutingConfigData.php
  Policies/
    SupplierInvoiceExceptionPolicy.php
  Http/
    Controllers/
      SupplierInvoiceExceptionController.php
    Requests/
      AssignInvoiceExceptionRequest.php
      ResolveInvoiceExceptionRequest.php
      RejectInvoiceExceptionRequest.php
    Resources/
      SupplierInvoiceExceptionResource.php
      SupplierInvoiceExceptionEvidenceResource.php
```

Use only the files the implementation needs. Empty folders should not be created.

### Domain Behavior

**`CreateInvoiceExceptions`:**

- Accepts supplier invoice and the match results array.
- Iterates failed match results (result = `fail`).
- For each failed result:
  - Performs defensive existence check using the stable composite key. Eloquent handles `NULL` `supplier_invoice_line_id` correctly via `whereNull`, so header-level exceptions are properly deduplicated regardless of database `NULLS NOT DISTINCT` support:
    ```php
    $exists = SupplierInvoiceException::where([
        'tenant_id' => $tenantId,
        'supplier_invoice_id' => $invoiceId,
        'dimension' => $dimension,
        'match_type' => $matchType,
        'supplier_invoice_line_id' => $lineId,
    ])->exists();
    ```
  - If an exception exists (from a prior matching run), skips creation — the exception record persists across matching re-runs.
  - Creates `SupplierInvoiceException` with dimension, match_type, expected/actual snapshots, tolerance applied, status `open`, and linked `supplier_invoice_line_id` / `purchase_order_line_id` from the match context.
  - Calls `ExceptionRoutingService` to resolve the specific owner user.
  - If owner resolved, sets `assigned_to_user_id` and status `in_progress`.
- If any exception was created, sets invoice status to `mismatch`.
- Updates `exception_summary` JSON on invoice.
- Records `supplier_invoice.exceptions_created` audit event with count and dimensions.

**`AssignInvoiceException`:**

- Accepts exception id, assigner, assignee user id, optional notes.
- Requires current `lockVersion`.
- Allows `open`, `in_progress`, or `rejected` status.
- Sets `assigned_to_user_id`, `assigned_by_user_id`, `assigned_at`.
- Sets status to `in_progress`.
- Increments `lock_version`.
- Records `supplier_invoice.exception_assigned` audit event.

**`ResolveInvoiceException`:**

- Accepts exception id, resolver, resolution type, optional adjusted value, notes, and optional evidence attachment IDs.
- Requires current `lockVersion`.
- Allows only `in_progress` status.
- If `resolution_type = value_adjustment`:
  - Validates `adjusted_value` is present and non-negative.
  - Stores the adjusted value on the exception record. Original `supplier_invoice_line` values are NOT modified.
  - Does NOT call the matching engine yet — deferred to `RunPostResolutionMatching`.
- If `resolution_type = explanation`:
  - Validates notes are present.
  - Sets `variance_accepted_by_human = true`.
  - Associates evidence attachments via `supplier_invoice_exception_evidence`.
- Sets status to `resolved`, `resolved_by_user_id`, `resolved_at`.
- Increments `lock_version`.
- After saving, checks: are all exceptions for this invoice `resolved`?
  - If yes, dispatches `RunPostResolutionMatching`.
  - If no, leaves invoice in `mismatch`.
- Records `supplier_invoice.exception_resolved` audit event.

**`RejectInvoiceException`:**

- Accepts exception id, rejecter, rejection notes, escalation target user id (required).
- Requires current `lockVersion`.
- Allows only `in_progress` status.
- Validates escalation target user is a valid, active tenant member.
- Sets status to `rejected`.
- Transfers `assigned_to_user_id` to the escalation target. The rejecting user is preserved in audit metadata.
- Increments `lock_version`.
- Invoice stays in `mismatch` status.
- Records `supplier_invoice.exception_rejected` audit event including escalation target.

**`RunPostResolutionMatching`:**

- Accepts supplier invoice.
- Loads the invoice with `lockForUpdate` and validates it is in `mismatch` status and all exceptions are `resolved`.
- Gathers all exceptions for this invoice.
- Determines whether any exception used `value_adjustment`:
  - If YES → at least one value changed, so the mathematical comparison may produce a different result:
    - Temporarily applies adjusted values from exceptions to their linked invoice lines for matching computation only. Original DB values are NOT modified.
    - Calls `RunInvoiceMatching` to re-run matching with temporary adjusted values.
    - If matching returns `matched`: records audit event, sets invoice to `ready_for_approval`.
    - If matching returns `mismatch`: calls `CreateInvoiceExceptions` for new failures. Previously resolved exceptions remain resolved. Invoice stays `mismatch`.
  - If NO → all exceptions resolved by explanation with human variance acceptance (no values changed):
    - Do NOT call the matching engine. Re-running unchanged data would produce the same mismatch result — the explanation loop deadlock.
    - Set invoice to `ready_for_approval` directly.
    - Records `supplier_invoice.ready_for_approval` audit event noting all exceptions cleared by human acceptance.
- Audit event includes: total exception count, resolved count, value_adjustment count, explanation count, and whether matching was re-run.

**`ExceptionRoutingService`:**

- Accepts `dimension`, `matchType`, `supplierInvoice`, tenant config.
- For P2P-graph-resolvable dimensions:
  - `unit_price`, `line_total`, `quantity` (two-way): Loads `supplierInvoice.purchaseOrder.created_by_user_id`. Verifies user is active tenant member. Returns user ID.
  - `quantity` (three-way): Queries most recent `GoodsReceipt` for the purchase order, reads `recorded_by_user_id`. Verifies user is active tenant member. Returns user ID. If no goods receipt, falls to two-way strategy.
  - `vendor_identity`, `invoice_total`: Queries tenant role membership for `finance` role. Returns first active user. Falls to admin.
  - `tax`, `freight`: Same as vendor_identity (finance role).
- If any step fails (user inactive, cross-tenant, not found), falls back to role-based match, then tenant admin.
- Returns resolved user ID or `null` for admin fallback.

### Authorization

Viewing, assigning, resolving, and rejecting exceptions require:

1. Authenticated Sanctum session.
2. Active tenant context.
3. Invoice belongs to the current tenant.

Specific permissions:

| Action | Allowed roles |
|---|---|
| View exceptions on an invoice | AP user, buyer, admin (any role with invoice view) |
| Assign/reassign exceptions | AP user, admin |
| Self-assign exceptions | Any role with invoice view |
| Resolve assigned exceptions | The assigned user, admin |
| Reject assigned exceptions | The assigned user, admin |
| View "My exceptions" queue | Any authenticated user (scoped to their assignments) |

### Audit Metadata

Exception audit events include:
- exception id
- supplier invoice id and number
- dimension and match type
- previous status and next status
- assigned/resolved/rejected by user id
- escalation target user id (for rejected)
- resolution type and whether variance was accepted by human
- evidence attachment count
- post-resolution matching outcome (for RunPostResolutionMatching)
- whether matching was re-run or skipped (explanation-only path)

## API Contract

Add authenticated tenant-scoped routes:

```
GET  /api/supplier-invoices/{supplierInvoice}/exceptions
POST /api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/assign
POST /api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/resolve
POST /api/supplier-invoices/{supplierInvoice}/exceptions/{exception}/reject
```

### GET exceptions

```
GET /api/supplier-invoices/{supplierInvoice}/exceptions
  ?status=open
  &dimension=unit_price
  &assignedTo={userId}
```

Response:
```json
{
  "data": [
    {
      "id": "uuid",
      "dimension": "unit_price",
      "matchType": "two_way",
      "expectedValue": "100.0000",
      "actualValue": "110.0000",
      "appliedTolerancePercent": "5.0000",
      "status": "in_progress",
      "assignedTo": { "id": "uuid", "name": "Alice Buyer" },
      "resolutionType": null,
      "adjustedValue": null,
      "varianceAcceptedByHuman": false,
      "resolutionNotes": null,
      "evidenceCount": 0,
      "escalatedTo": null,
      "lockVersion": 1
    }
  ]
}
```

### POST assign

```json
{
  "lockVersion": 1,
  "assignedToUserId": "uuid",
  "notes": "Please review this price discrepancy"
}
```

Response (200): Updated exception resource.
Error: 409 if stale lockVersion or invalid status transition.

### POST resolve

Value adjustment path:
```json
{
  "lockVersion": 1,
  "resolutionType": "value_adjustment",
  "adjustedValue": "105.0000",
  "notes": "Corrected unit price per email confirmation from vendor",
  "evidenceAttachmentIds": ["att-uuid-1", "att-uuid-2"]
}
```

Explanation with variance acceptance path:
```json
{
  "lockVersion": 1,
  "resolutionType": "explanation",
  "notes": "Price was agreed at 10% above PO due to surcharge; approval attached",
  "evidenceAttachmentIds": ["att-uuid-3"]
}
```

Response (200): Updated exception resource. If this was the last unresolved exception, the response includes the invoice's updated status (`ready_for_approval` or `mismatch`) and a `postResolutionMatchingRun` boolean indicating whether the matching engine was invoked.

### POST reject

```json
{
  "lockVersion": 1,
  "notes": "Vendor refuses to acknowledge the discrepancy. Escalating to AP manager.",
  "escalatedToUserId": "uuid-of-ap-manager"
}
```

Response (200): Updated exception resource with status `rejected` and ownership transferred to escalation target.

### Extended queue filters

`GET /api/supplier-invoices` additions:

- `exceptionStatus`: `open`, `in_progress`, `none`
- `hasOpenException`: boolean
- `assignedToMe`: boolean — filters to invoices with exceptions assigned to the current user

### Extended invoice resource

Add to existing `SupplierInvoice` resource:

```json
{
  "exceptionSummary": {
    "openCount": 2,
    "resolvedCount": 1,
    "rejectedCount": 0,
    "dimensions": ["unit_price", "quantity"],
    "hasValueAdjustments": false
  }
}
```

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas.

## Frontend Design

### Routes

No new routes. Exception UX lives in the existing AP invoice workspace at `(workspace)/accounts-payable/invoices/`.

### Feature Structure

```
apps/web/features/accounts-payable/
  components/
    invoice-exception-panel.tsx
    invoice-exception-row.tsx
    invoice-exception-assign-dialog.tsx
    invoice-exception-resolve-form.tsx
    invoice-exception-status-badge.tsx
  hooks/
    use-invoice-exceptions.ts
    use-invoice-exception-actions.ts
  mocks/
    accounts-payable-exception-fixtures.ts
    accounts-payable-exception-handlers.ts
```

### Invoice Exception Panel

Placed below the match results section in the invoice detail view.

**Header**: "Invoice Exceptions" with open count badge.

**Summary bar**: "2 of 3 exceptions resolved. Open: unit price, quantity."

**Per-exception cards**:
- Dimension name with icon (e.g., price tag for unit_price, box for quantity)
- Match type label (two-way/three-way)
- Expected vs invoiced value display
- Tolerance applied
- Status badge (Open / In Progress / Resolved / Rejected)
- Assigned owner name with avatar
- Escalation target shown for rejected exceptions
- Action buttons gated by role and status:
  - **Assign/Reassign**: available when user has AP/buyer/admin role
  - **Resolve**: available for assigned user or admin, when status is `in_progress`
  - **Reject with escalation**: available for assigned user or admin, when status is `in_progress`

**Assign Dialog**:
- User picker filtered to tenant users with appropriate roles for the dimension
- Optional notes field
- Confirm button

**Resolve Form**:
- Toggle: "Propose adjusted payment value" or "Accept variance with explanation"
- When "Propose adjusted value" selected:
  - Numeric input for corrected value
  - Shows original value and proposed difference
  - Note: "This adjusts the proposed payment amount. The original invoice record is preserved for audit."
- When "Accept variance" selected:
  - Notes textarea (required)
  - Evidence file upload (reuses existing Attachment components)
  - Checkbox or confirmation: "I confirm this variance is acceptable"
- Submit button with loading state

**Reject Dialog**:
- Notes textarea (required)
- Escalation target user picker (required) — filtered to tenant users with appropriate seniority/role
- Explanation: "Ownership will be transferred to the escalation target"
- Confirm button

### AP Queue Extensions

- New column: `Exceptions` — shows badge with open count, or checkmark if none
- New filter tabs: `All`, `My exceptions`, `Has open exceptions`
- New filter dropdown for `Exception dimension`
- Exception counts in the queue header summary

### "My Exceptions" Quick View

The AP queue should include a filter for `assignedToMe=true` (via the API filter). This gives each user a personal work view showing only invoices where they have open or in-progress exceptions.

### States

- **Loading**: spinner while exception list loads
- **Empty**: "No exceptions for this invoice" (shown when matching passed)
- **Populated**: list of exception cards with actions
- **Error**: failed to load exceptions
- **Conflict**: stale lockVersion on assign/resolve/reject
- **Permission denied**: user cannot view or act on exceptions

## Data Flow

```txt
Matching detects mismatch
  -> CreateInvoiceExceptions (stable composite key, no volatile FK)
  -> ExceptionRoutingService resolves specific user from P2P graph
     - unit_price -> purchase_order.created_by_user_id
     - quantity#three_way -> goods_receipt.recorded_by_user_id
  -> Invoice status: mismatch
  -> Exceptions visible in AP queue and invoice panel

Assignee resolves exception (value_adjustment)
  -> Stores adjusted_value on exception (original invoice line UNCHANGED)
  -> RunPostResolutionMatching when all exceptions resolved
  -> Re-runs matching engine with adjusted values (temp computation only)
  -> If pass: invoice -> ready_for_approval
  -> If fail: new exceptions created, invoice stays mismatch

Assignee resolves exception (explanation)
  -> Sets variance_accepted_by_human = true
  -> RunPostResolutionMatching when all exceptions resolved
  -> SKIPS matching engine (values unchanged -> same result guaranteed)
  -> All resolved by human acceptance -> invoice -> ready_for_approval

Assignee rejects exception
  -> Requires escalation target user
  -> Status: rejected, ownership transferred to escalation target
  -> Invoice stays mismatch
```

## Error Handling

- Validation errors map to form fields (resolution type, notes, adjusted value, escalation target).
- Stale `lockVersion` returns 409 conflict.
- Invalid status transitions (e.g., resolving a rejected exception) return 409.
- Assigning to a non-existent or cross-tenant user returns 422.
- Rejecting without an escalation target user returns 422.
- Escalation target user is invalid or inactive returns 422.
- Evidence attachment IDs that do not exist or belong to a different tenant return 422.
- Post-resolution matching failure (matching still fails after value adjustment) is not an error — it transitions the invoice back to exception state with new failure records.

## Seed and Demo Data

Demo data should include at least:

- Reviewed invoice with matching pass (no exceptions — baseline).
- Reviewed invoice with `mismatch` status and open exceptions created from match failures.
- Invoice in `mismatch` status with one resolved-by-explanation exception and one in-progress exception.
- Invoice in `mismatch` status with one resolved-by-value-adjustment exception and matching still failing.
- Invoice in `ready_for_approval` status via explanation path (all exceptions resolved, variance accepted).
- Invoice in `ready_for_approval` status via value adjustment path (matching passed after adjustment).
- Invoice with a rejected exception and escalation target assigned.

Seeded exceptions should reference real seeded users in appropriate P2P document ownership roles (PO creator as buyer, goods receipt recorder as receiver, finance role user) so routing rules produce visible assignments and "My exceptions" filter works.

## Testing and Verification

### API Tests

Add focused feature tests for:

- matching failure auto-creates exceptions with stable composite key (no volatile FK dependency).
- exception creation is idempotent across matching re-runs (deduplicates by composite key).
- **header-level exception deduplication**: re-running matching on a `vendor_identity` mismatch does not create duplicate `supplier_invoice_line_id=null` exception rows.
- **line-level exception deduplication**: re-running matching on a `unit_price` mismatch for the same invoice line does not create duplicate exception rows.
- exceptions are assigned to P2P document owners (PO creator for price, goods receipt recorder for quantity).
- routing falls back to role-based assignment when P2P owner is inactive.
- routing falls back to admin when no owner resolved.
- exception status transitions are enforced.
- resolve with `value_adjustment` stores adjusted value and does NOT mutate invoice line.
- resolve with `explanation` sets `variance_accepted_by_human`.
- **explanation loop deadlock prevented**: ALL-explanations path skips matching engine and advances to `ready_for_approval` directly.
- **value adjustment matching re-run**: at least one value_adjustment triggers matching engine re-run.
- post-resolution matching pass transitions invoice to `ready_for_approval`.
- post-resolution matching still fails keeps invoice in `mismatch` and creates new exceptions.
- exception rejection requires escalation target user.
- exception rejection transfers ownership to escalation target.
- reassigning an exception updates owner.
- cross-tenant exception access is denied.
- stale lock version on assign/resolve/reject returns conflict.
- invalid status transitions return conflict.
- audit events are recorded for each action.
- tenant-scoped exception lists.
- routing rule fallback to hard-coded defaults when tenant config is empty.
- routing rule override from tenant config.
- dimension with no matching routing rule falls back to admin.

### Web Tests

Add tests for:

- exception panel shows correct dimensions and statuses.
- "My exceptions" filter shows only assigned exceptions.
- assign dialog opens and submits.
- resolve form toggles between value adjustment and explanation.
- resolve form for value adjustment shows original value and adjusted input.
- resolve form for explanation shows variance acceptance confirmation.
- reject dialog requires escalation target.
- evidence upload works in resolve form.
- exception panel loading, empty, populated, error states.
- stale-state conflict message on assign/resolve/reject.
- permission-denied rendering for non-allowed roles.
- queue renders exception summary column and filters.

### Contract and Local Verification

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
cd apps/api && php artisan test --filter=InvoiceException
```

## Future Evolution

### P1-46 Invoice Approval Handoff

This slice terminates at `ready_for_approval` status. P1-46 will consume invoices in that status and route them through the shared Approval domain. The exception resolution data (adjusted values, variance acceptance evidence, audit trail) becomes input context for approval decisions. For value-adjustment exceptions, the `adjusted_value` on the exception record becomes the proposed payment amount.

### SLA-Based Escalation

When exceptions remain open past a configurable SLA (e.g., 5 business days), the system should automatically escalate to the assignee's manager or the next role in the routing chain. This is deferred to a later slice after the exception workflow stabilizes.

### Automated Resolution Suggestions

AI could analyze past exception resolutions for the same vendor, PO category, or dimension and suggest a resolution type or value. This is deferred to P2 AI assistance scope.

### Vendor-Facing Exception Portal

If a mismatch is attributed to a vendor error (wrong price, wrong quantity), the vendor could view and correct the exception through the vendor portal. This is deferred to P3 enterprise integration scope.

### Debit Memo and Short-Payment Integration

When a value adjustment is accepted and the invoice is approved, P1-47 payment readiness should calculate the payment amount as `original_invoice_total - sum(adjusted_value_differences)`. If a short-payment is not legally permitted in the jurisdiction, the system should instead generate a debit memo referencing the invoice and exception records. This is deferred to P1-47 and P1-49.

## Exit Criteria

- Matching failures on a reviewed invoice auto-create durable, assignable exception records keyed by stable composite business identity (not volatile match-result PKs).
- Exceptions are assigned to specific P2P document owners (PO creator for price mismatches, goods receipt recorder for quantity mismatches).
- Resolving by value adjustment stores the proposed payment amount without mutating original invoice line data.
- Resolving by explanation with variance acceptance sets `variance_accepted_by_human` and does not require matching re-run.
- **Explanation loop deadlock is eliminated**: invoices with all-explanations-only resolutions advance to `ready_for_approval` without calling the matching engine.
- **Value adjustment path works**: at least one value adjustment triggers matching re-run with temporary adjusted values.
- Post-resolution matching pass transitions invoice to `ready_for_approval`.
- Post-resolution matching failure keeps invoice in `mismatch` and creates new exception records.
- Rejecting an exception requires an escalation target, and ownership transfers to that user.
- Exception records survive matching re-runs (no FK dependency on deleted match results).
- AP queue shows exception counts and supports "My exceptions" and "Has open exceptions" filters.
- Exception assign, resolve, and reject actions are auditable.
- Evidence can be attached to exceptions via the existing Attachment domain.
- Seeded demo data includes realistic open, in-progress, resolved-by-explanation, resolved-by-adjustment, and rejected exception states.
- OpenAPI endpoints are generated and consumed by `@cognify/api-client`.
- P1-45 can be marked Fully Implemented after implementation, review, PR, and merge.

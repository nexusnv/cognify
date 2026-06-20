# Payment Status Tracking Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-19
- Release scope: P1 core procure-to-pay lifecycle, slice P1-48 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-48`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-approval-design.md`
  - `docs/superpowers/specs/2026-06-18-payment-readiness-ap-handoff-design.md`
- Downstream slices:
  - P1-49 Credit Memo and Invoice Adjustment
  - P1-54 P2P Operational Queues
- Reference patterns:
  - `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md` (handoff state machine)
  - `docs/superpowers/specs/2026-06-10-purchase-order-change-orders-design.md` (delta + lock_version + audit)
  - `docs/superpowers/specs/2026-06-10-purchase-order-issue-to-supplier-design.md` (post-approval lifecycle transition)
- External research: Odoo 19.0 `account.payment.state` + `account.move.payment_state`; ERPNext `PaymentRequest.status` + `PaymentEntryReference` child table; Oracle `AP_INVOICE_PAYMENTS_ALL` (junction with reversal lines); NetSuite BILL payment statuses; SAP S/4HANA Monitor Payments lifecycle; D365 ISO 20022 import staging pattern.

## Roadmap Analysis

P1-48 asks Cognify to track the payment lifecycle: scheduled, paid, partially paid, failed, voided, or remitted. The roadmap explicitly notes that early scope can be manual status update or import before live bank or accounting sync.

P1-47 (Payment Readiness and AP Handoff) is now complete. Handoffs exit P1-47 in `exported` status with each member invoice's `payment_status = handoff_exported`. P1-48 picks up at that point and adds the post-export payment lifecycle.

The current codebase has:
- Durable `ApPaymentHandoff` records with `draft → ready → exported → cancelled` states
- `SupplierInvoice.payment_status` ending at `handoff_exported` (terminal in P1-47)
- A mature `AccountsPayable` domain owning pre-execution payment readiness
- The `PurchaseOrder` post-approval lifecycle pattern (P1-38 issue, P1-39 change order) as the established template for layered post-approval state machines
- The `PurchaseOrderChangeOrder` event log pattern (before/after/delta/material_change/lock_version) as the established template for status event records

No post-export payment lifecycle, payment allocation, payment import, or payment void/reversal infrastructure exists yet. This slice introduces the first post-execution payment constructs.

## Problem

After P1-47, Cognify has exported AP payment handoffs but cannot:
- Distinguish exported handoffs that have been scheduled for payment from those awaiting scheduling.
- Record full or partial payment settlement against individual invoices in a handoff.
- Capture payment failures with reason and retry path.
- Void a scheduled or paid handoff and release invoices for re-attempt.
- Import payment status updates from external bank or ERP systems via CSV or JSON.
- Give auditors a clear record of which handoffs were scheduled, paid, failed, or voided and when.

Without P1-48, the P2P lifecycle stops at handoff export. Finance teams have no operational view of payment progress, no controlled way to record settlement, and no import path for bank file data.

## Goals

- Extend `ApPaymentHandoff` with post-export lifecycle states: `scheduled`, `paid`, `failed`, `voided`.
- Introduce an `ApPaymentAllocation` junction table linking handoffs to invoices with `allocated_amount` per allocation, enabling partial payment at the invoice level.
- Extend `SupplierInvoice.payment_status` with derived post-export states: `payment_scheduled`, `partially_paid`, `paid`. The `payment_failed` and `payment_voided` transitions are captured as audit events only — the invoice column releases directly to `handoff_exported` without a transient column write, avoiding wasteful double-writes within the same transaction and preventing accidental side effects from model boot observers or async dispatchers.
- Auto-advance member invoices from `handoff_exported` to `payment_scheduled` when the handoff transitions to `scheduled`.
- Provide CSV and JSON import with a staging table, preview, and confirm step so AP users can ingest bank file data without direct mutation of payment state.
- Record remittance advice as an audit event when a handoff transitions to `paid`, not as a separate payment state.
- Keep tenant isolation, lock-version concurrency, generated-client contracts, and real route-stack tests consistent with established patterns.
- Add a new `Payments` backend domain boundary for post-execution payment lifecycle behavior.

## Non-Goals

- Live bank integration, ACH/wire/check generation, or direct payment execution (P3-x scope).
- Payment method selection (ACH vs wire vs check vs card) — deferred.
- Automated payment scheduling based on vendor payment terms — deferred.
- Remittance advice document generation or delivery to vendor portal or email — deferred; this slice records the audit event only.
- BAI2, camt.054, pain.001, pain.002, or ISO 20022 file parsers — deferred; this slice supports CSV and JSON manual import only.
- Credit memo, debit note, invoice reversal, or post-paid adjustment handling (P1-49 scope).
- Vendor banking master data or remittance contact fields (P1-51 scope).
- Payment batch approval workflow (deferred; direct mutation by AP user in this slice).
- Reversal allocation rows (Oracle `AP_INVOICE_PAYMENTS_ALL` negative-line pattern) — deferred; void releases invoices back to `handoff_exported` and new schedule attempts create new allocation rows.
- `reversed` state for post-paid corrections (P1-49 scope).
- Spend analytics, payment aging dashboard, or reporting views (P2-x scope).
- Direct ERP or accounting system sync (P3-x scope).

## Design Decision

### Create A Dedicated Payments Domain

P1-48 introduces `apps/api/Domains/Payments` for post-execution payment lifecycle behavior. The existing `AccountsPayable` domain retains ownership of pre-execution: handoff creation, payment eligibility, holds, readiness, and export. The new domain owns:
- Post-export handoff state machine (scheduled, paid, failed, voided)
- Payment allocations (junction of handoff to invoice with allocated amount)
- Payment import staging and reconciliation
- Invoice payment status projection from handoff state and allocations
- Future payment execution and reversal extension points

This boundary mirrors the P1-47 split where `AccountsPayable` was carved out from `Invoice` once pre-execution behavior was clear enough. With P1-47 complete and the handoff lifecycle stable, the post-execution boundary is clear enough to introduce a dedicated domain.

This boundary also mirrors the PO-side split where `PurchaseOrder` owns handoff packaging while `Quotation` owns the award recommendation, and later slices (`PurchaseOrder` issue, change order) layered post-approval behavior onto the PO domain without bloating `Quotation`.

### Dual-Level Status: Handoff Lifecycle + Invoice Projection

Following the mature ERP pattern (Odoo, ERPNext, Oracle, NetSuite, SAP), payment status lives at two levels with strict separation:

- **Handoff-level**: `ApPaymentHandoff.status` tracks the workflow/transmission lifecycle: `exported → scheduled → {paid | failed | voided}`. This is the source of truth for what happened to the payment batch.
- **Invoice-level**: `SupplierInvoice.payment_status` is a derived projection of how paid the individual invoice is, computed from handoff state and allocation sums. It is not a dual-write source of truth.

This avoids the dual-write consistency problem (two sources of truth drifting) and matches how Odoo computes `account.move.payment_state` from `account.partial.reconcile` rows, and how ERPNext derives `outstanding_amount` from `PaymentEntryReference` allocations.

### Allocation Junction For Partial Payment

A new `ApPaymentAllocation` table records each application of a handoff's payment to a member invoice with an `allocated_amount`. Partial payment at the invoice level is naturally represented: an invoice with total `1000.00` and allocations summing `600.00` is partially paid with `400.00` outstanding.

This mirrors:
- Odoo's `account.partial.reconcile` (junction of payment to move with amount)
- ERPNext's `PaymentEntryReference` child table (reference_doctype, reference_name, allocated_amount, outstanding_amount)
- Oracle's `AP_INVOICE_PAYMENTS_ALL` (one row per payment per invoice with amount)

`outstanding_amount` on `SupplierInvoice` is derived (`invoice_total - SUM(allocations)`) and exposed via the API resource, not stored as an independent column. This avoids the stale-snapshot bugs ERPNext encountered (see frappe/erpnext#31166).

### Import To Staging, Then Reconcile

CSV and JSON import lands in a staging `ApPaymentImport` table. AP users upload a file, the system parses rows into staging records, presents a preview with proposed state changes and any matching errors, and the AP user confirms to run the reconciliation job. The reconciliation job matches staging rows to handoffs and invoices by `handoff_number` or `invoice_number`, applies state transitions, creates allocations, and records audit events.

This mirrors the D365 ISO 20022 import pattern where pain.002/camt.054 files land in a payment-transfer journal (staging) before being transferred into the actual vendor payment journal. Direct mutation of payment state from import is explicitly avoided.

### Drop Remitted As A State

Research across Odoo, NetSuite, and ERPNext converged: `remitted` is a document event (remittance advice sent), not a payment state. In US AP it is a synonym for paid. In UK/EU it specifically means the remittance advice was sent, which is a separate document from the payment itself.

Cognify records remittance as an audit event when a handoff transitions to `paid`, capturing `remittance_reference` and `remittance_advice_sent_at` as metadata. The UI shows remittance info in the paid state panel. Future slices can add remittance advice document generation or delivery without changing the payment state machine.

### No Approval Routing For Status Transitions

Payment status transitions (schedule, mark paid, mark failed, void) are direct mutations by AP users (buyer/admin role) with lock-version concurrency and audit events. This matches the roadmap framing of "manual status update" as early scope.

Approval routing can be added later if tenant policy requires release approval for payments above a threshold. The shared Approval domain (P1-37, P1-46) supports this via a new `PaymentApprovalSubjectHandler` when needed. This slice does not add it.

### Void Releases Invoices To Handoff_Exported

When a handoff is voided, member invoices return to `handoff_exported` (the P1-47 terminal state), ready for a new schedule attempt. Allocations for the voided handoff are marked `voided_at` but rows are kept for audit. A new schedule attempt creates a new allocation row on the same invoice.

This mirrors the P1-47 `CancelApPaymentHandoff` pattern where cancellation releases invoices back to `payment_eligible`. It preserves the re-attempt ability without requiring AP to rebuild the handoff from scratch.

The Oracle reversal-line pattern (voids insert negative `AP_INVOICE_PAYMENTS_ALL` rows) is deliberately deferred. It is better suited when allocations are immutable financial records with downstream GL impact, which is not yet the case in Cognify.

### Short-Pay / Close With Variance

In real-world supply chains, corporate buyers frequently issue short-payments: a supplier may fulfill a batch incorrectly, or a bank fee may be deducted from the wire transaction. The bank clearance file will show an allocation of $950 against a $1,000 invoice total.

Without a close-with-variance path, the AP clerk is stuck in an operational deadlock: they cannot close the handoff as `paid` (hard-stop 409 on under-allocated invoices), they cannot change the invoice total without violating audit trails, and P1-49 (credit memos) is a downstream non-goal.

Cognify introduces `CloseApPaymentHandoffWithVariance` as an explicit settlement exception path. The AP user closes the handoff with a `variance_reason`, the system computes `variance_amount = handoff_total - SUM(allocations)`, and the handoff transitions to `paid` with variance metadata. Fully-allocated invoices move to `paid`; partially-allocated invoices stay `partially_paid` with their outstanding balance visible for P1-49 credit memo or future settlement exception follow-up.

This is not an automated tolerance threshold (which would require tenant-configurable policy UI outside this slice's scope). It is a manual, reason-captured, audit-trailed action that gives the AP clerk an explicit escape hatch from the short-pay deadlock.

### Preserve Bank-Currency Settlement Values

Bank file clearing lines are generated from the perspective of the disbursement bank account, which may be denominated differently from the invoice's face currency (e.g., a USD treasury account clearing a EUR invoice). If the bank's settlement amount is mapped directly into `allocated_amount` (which is validated against the invoice total in invoice currency), the over-allocation guardrail fires and crashes the import.

Cognify stores both `allocated_amount` (always in invoice currency, used for outstanding balance calculation) and `settlement_amount` + `settlement_currency` (the raw bank clearance value in the disbursement account's currency) on each allocation. When currencies match, `settlement_amount` defaults to `allocated_amount`. When they differ, both values are stored as-is — no FX conversion is performed in this slice. This preserves the bank's presentation value for downstream reconciliation while keeping the outstanding balance mathematically correct in invoice currency.

### No Transient Column Writes For Audit Events

When a handoff fails or is voided, the invoice's `payment_status` column transitions directly to `handoff_exported`. The `payment_failed` and `payment_voided` actions are captured in the audit event payload only — the column never holds these values. This avoids wasteful double-writes within the same transaction (which external readers under read-committed isolation would never observe anyway) and prevents accidental side effects from model boot observers or async dispatchers firing on the transient value.

### NULLS NOT DISTINCT On Allocation Unique Index

Enterprise bank export files frequently omit per-transaction `payment_reference` for standard batch ACH clearances, leaving that field `NULL` during parsing. In standard PostgreSQL behavior, `NULL` values are treated as distinct entities in unique constraints, so the constraint would not prevent duplicate rows when `payment_reference` is null. If an operational user accidentally double-clicks "Confirm Reconciliation" or uploads the same settlement file twice, the system would silently insert duplicate allocation lines, driving the invoice's outstanding balance into an invalid negative state.

Cognify uses PostgreSQL's `NULLS NOT DISTINCT` modifier (PostgreSQL 15+) on the allocation unique index to treat `NULL` `payment_reference` values as equal. The service layer additionally normalizes empty-string references to `null` so CSV parsers do not bypass the semantics. If the deployment targets PostgreSQL < 15, a functional index on `(COALESCE(payment_reference, ''))` is the fallback.

## Approaches Considered

1. **Dual-level + allocation junction + import staging (selected)**. Extend `ApPaymentHandoff` with post-export lifecycle states. Add `ApPaymentAllocation` junction table. Extend `SupplierInvoice.payment_status` with derived states. Add `ApPaymentImport` staging table with preview and confirm. New `Payments` domain. This matches the mature ERP pattern from research and preserves the existing P1-47 boundary.

2. **Invoice-only status with paid_amount column**. Simpler — no new junction table. Add `paid_amount`, `settlement_reference`, `settlement_method` columns to `SupplierInvoice`. AP user manually marks each invoice. CSV import maps rows directly to invoice numbers and increments `paid_amount`. Rejected because it loses handoff-level tracking (when one invoice in a batch fails, the batch state is unclear), does not match the mature ERP pattern, and is harder to audit per-payment events.

3. **New ApPaymentRun aggregate with applications**. Add a new `ApPaymentRun` model tracking payment execution records, each linking to one handoff with lifecycle states. Per-invoice `ApPaymentApplication` records carry allocated amounts. Most flexible (handles multi-run, partial, void and re-prime cleanly) but largest schema — three new tables plus derivation logic. Approaches P1-54 P2P Operational Queues scope. Rejected for P1-48 scope.

## Workflow

### Actors

- AP user: schedules handoffs, marks paid, closes with variance for short-pays, marks failed with reason, voids, imports CSV/JSON files, confirms reconciliation. Maps to existing buyer/admin permission.
- Admin: same abilities for tenant operations.
- System: auto-advances member invoices when handoff is scheduled, records audit events, derives invoice payment_status from allocations.
- Approver: read-only payment status visibility on invoices they approved.
- Requester: read-only payment status visibility on their invoices.
- Vendor portal visitor: no access.

### State Model

#### ApPaymentHandoffStatus (extended)

P1-47 states remain: `draft`, `ready`, `exported`, `cancelled`.

P1-48 adds post-export states:

| Status | Meaning | Entry Condition |
|---|---|---|
| `scheduled` | Handoff scheduled for payment (manual or import) | Manual schedule action on `exported` |
| `paid` | Handoff fully paid — sum of allocations equals handoff total | Mark paid action on `scheduled` (requires full allocation), or close-with-variance action on `scheduled` (accepts shortfall with reason) |
| `failed` | Payment failed — requires reason and failure code | Mark failed action on `scheduled`, or import reconciliation |
| `voided` | Handoff voided — releases invoices back to `exported` | Void action on `scheduled` or `paid` |

The full enum becomes: `draft`, `ready`, `exported`, `cancelled`, `scheduled`, `paid`, `failed`, `voided`.

Allowed transitions:

| From | Action | To | Notes |
|---|---|---|---|
| `exported` | Schedule (AP) | `scheduled` | Requires `scheduled_for_date` (optional), `payment_reference` (optional) |
| `scheduled` | Mark paid (AP) | `paid` | Requires allocations covering full handoff total; records remittance audit event |
| `scheduled` | Close with variance (AP) | `paid` | Requires `variance_reason` (min 5 chars); accepts unallocated balance as variance; partially-allocated invoices stay `partially_paid` with outstanding visible for P1-49 follow-up |
| `scheduled` | Mark failed (AP) | `failed` | Requires `failure_reason` (min 5 chars) and `failure_code` (enum) |
| `scheduled` | Void (AP) | `voided` | Releases invoices to `exported`; marks allocations `voided_at` |
| `paid` | Void (AP) | `voided` | Releases invoices to `exported`; marks allocations `voided_at`; requires reason |
| `failed` | Re-schedule (AP) | `scheduled` | Resets failure fields; invoices must be in `exported` (auto-released on failure) |
| `failed` | Void (AP) | `voided` | Terminal cleanup; invoices already `exported` |
| `voided` | (no further transitions in this slice) | — | Terminal for P1-48 |
| `cancelled` (P1-47) | (no P1-48 transitions) | — | Pre-export terminal, unchanged |

Failure codes (enum `ApPaymentFailureCode`):
- `bank_rejected` — bank rejected the payment
- `insufficient_funds` — funding account had insufficient funds
- `vendor_blocked` — vendor was blocked between schedule and execution
- `system_error` — internal or integration error
- `other` — freeform `failure_reason` required

#### SupplierInvoicePaymentStatus (extended)

P1-47 states remain: `payment_eligible`, `on_hold`, `payment_ready`, `handoff_exported`.

P1-48 adds derived post-export states:

| Payment Status | Meaning | Derivation |
|---|---|---|
| `payment_scheduled` | Member of a scheduled handoff | Auto-advance when handoff transitions to `scheduled` |
| `partially_paid` | Has allocations summing to less than invoice total | Derived from `SUM(allocations.allocated_amount) < invoice_total` while handoff is `scheduled` or `paid` |
| `paid` | Has allocations summing to invoice total | Derived from `SUM(allocations.allocated_amount) = invoice_total` |
| `payment_failed` | Member of a failed handoff (audit-only — invoice releases to `handoff_exported`) | Audit event recorded when handoff transitions to `failed`; invoice column goes directly to `handoff_exported` |
| `payment_voided` | Member of a voided handoff (audit-only — invoice releases to `handoff_exported`) | Audit event recorded when handoff transitions to `voided`; invoice column goes directly to `handoff_exported` |

The `payment_failed` and `payment_voided` states are **audit-only events, not column values**. The `supplier_invoices.payment_status` column is never written to `payment_failed` or `payment_voided`. When a handoff fails or is voided, the invoice column transitions directly to `handoff_exported`, and the audit recorder captures the `payment_failed` or `payment_voided` action in the audit event payload (with `fromStatus` and `toStatus` metadata). This avoids wasteful double-writes within the same transaction (which external readers under read-committed isolation would never observe anyway) and prevents accidental side effects from model boot observers or async dispatchers firing on the transient value.

Allowed `payment_status` transitions (extending P1-47):

| From | Action | To | Notes |
|---|---|---|---|
| `handoff_exported` | Handoff scheduled (system) | `payment_scheduled` | Same transaction as handoff schedule |
| `payment_scheduled` | Allocation added (system) | `partially_paid` | When first allocation lands and sum < total |
| `payment_scheduled` | Allocation added (system) | `paid` | When allocations sum = total |
| `partially_paid` | Allocation added (system) | `paid` | When allocations sum reaches total |
| `partially_paid` | Handoff marked paid (system) | `paid` | When handoff transitions to `paid` (may require final allocation to cover remainder) |
| `payment_scheduled` / `partially_paid` / `paid` | Handoff failed (system) | `handoff_exported` | Audit records `supplier_invoice.payment_failed`; invoice column goes directly to `handoff_exported` (no transient write); allocations kept |
| `payment_scheduled` / `partially_paid` / `paid` | Handoff voided (system) | `handoff_exported` | Audit records `supplier_invoice.payment_voided`; invoice column goes directly to `handoff_exported` (no transient write); allocations marked `voided_at` |

### Main Flow

#### Manual Scheduling

1. AP user opens the **payment status queue** page at `/accounts-payable/payment-status` (a dedicated page under the Finance group, separate from the payment readiness queue).
2. AP user filters handoffs by status tab: All / Exported / Scheduled / Paid / Failed / Voided.
3. AP user selects an `exported` handoff and clicks "Schedule payment".
4. AP user fills the schedule form: `scheduled_for_date` (optional), `payment_reference` (optional), `notes` (optional).
5. System transitions handoff to `scheduled`:
   - Locks handoff row, validates `lockVersion`, validates status is `exported`.
   - Sets `status = scheduled`, `scheduled_by_user_id`, `scheduled_at`, `scheduled_for_date`, `payment_reference`, bumps `lock_version`.
   - Auto-advances each member invoice `payment_status` from `handoff_exported` to `payment_scheduled` in the same transaction.
   - Records `ap_payment_handoff.scheduled` audit event with `fromStatus`/`toStatus`.
   - Records `supplier_invoice.payment_scheduled` audit event per invoice.
6. AP user reviews the scheduled handoff detail panel showing member invoices, allocation status (none yet), and actions: Add Allocation, Mark Paid, Mark Failed, Void.

#### Manual Payment Recording

7. AP user clicks "Add Allocation" on a scheduled handoff.
8. AP user fills the allocation form: `supplier_invoice_id` (selected from member invoices), `allocated_amount`, `allocation_date`, `payment_reference` (optional), `settlement_amount` (optional, bank-currency value), `settlement_currency` (optional, disbursement bank account currency).
9. System creates an `ApPaymentAllocation` row:
   - Locks handoff and invoice rows, validates `lockVersion` on handoff.
   - Validates invoice is a member of the handoff.
   - Validates `allocated_amount > 0` (strictly positive, using `bccomp`) and `SUM(existing non-voided allocations + new) <= invoice_total` (no over-allocation, using `bcadd`).
   - If `settlement_currency` differs from invoice currency, validates `settlement_amount` is also provided. No FX conversion — both values stored as-is.
   - If `settlement_currency` matches invoice currency or is null, `settlement_amount` defaults to `allocated_amount`.
   - Normalizes `payment_reference`: trims whitespace, stores `null` if empty (ensures `NULLS NOT DISTINCT` semantics).
   - Creates allocation row with `lock_version = 1`.
   - Derives invoice `payment_status`: `partially_paid` if sum < total, `paid` if sum = total.
   - Bumps invoice `lock_version`.
   - Records `ap_payment_allocation.created` audit event.
10. AP user repeats until all invoices are fully allocated, OR clicks "Mark Paid" on the handoff.
11. AP user clicks "Mark Paid" on the handoff:
    - System validates all member invoices have allocations summing to their totals (handoff is fully paid).
    - Sets handoff `status = paid`, `paid_by_user_id`, `paid_at`, `remittance_reference` (optional), `remittance_advice_sent_at` (optional), bumps `lock_version`.
    - Derives each invoice `payment_status = paid` (idempotent if already paid via allocations).
    - Records `ap_payment_handoff.paid` audit event with remittance metadata.
    - Records `ap_payment.remitted` audit event with `remittanceReference` and `remittanceAdviceSentAt` metadata (the remittance event, not a state).
12. If an invoice is only partially allocated when "Mark Paid" is clicked, the system returns `409` with a list of under-allocated invoices and remaining amounts. AP user has two paths:
    - **Full payment path**: add allocations covering the remainder, then click "Mark Paid" again.
    - **Short-pay path**: click "Close with variance" instead. This accepts the unallocated balance as a variance. See "Short-Pay / Close With Variance" below.

#### Short-Pay / Close With Variance

12a. AP user clicks "Close with variance" on a scheduled handoff with partial allocations.
12b. AP user fills the variance form: `variance_reason` (min 5 chars), `remittance_reference` (optional), `remittance_advice_sent_at` (optional).
12c. System transitions handoff to `paid` with variance:
    - Locks handoff row, validates `lockVersion`, validates status is `scheduled`.
    - Validates at least one allocation exists (handoff must have some payment recorded, not zero).
    - Computes `variance_amount = handoff_total - SUM(all non-voided allocations across member invoices)`.
    - Sets handoff `status = paid`, `paid_by_user_id`, `paid_at = now`, `variance_amount`, `variance_reason`, `variance_closed_by_user_id`, `variance_closed_at = now`, `remittance_reference`, `remittance_advice_sent_at`, bumps `lock_version`.
    - Derives each invoice `payment_status`: invoices with allocations summing to their total → `paid`; invoices with partial allocations → stay `partially_paid` (outstanding balance visible for P1-49 credit memo or future settlement exception follow-up).
    - Bumps each invoice `lock_version`.
    - Records `ap_payment_handoff.paid_with_variance` audit event with `varianceAmount`/`varianceReason`/`remittanceReference`.
    - Records `ap_payment.remitted` audit event with remittance metadata.
    - Records `supplier_invoice.paid` audit event per fully-paid invoice.
    - Records `supplier_invoice.partially_paid` audit event per partially-paid invoice (if not already partially_paid).
12d. The handoff detail panel shows `paid` status with a variance warning badge, the variance amount, and the variance reason. Partially-paid invoices show their outstanding balance.
12e. The partially-paid invoices remain visible in the payment status queue under the "Partially paid" filter, with a link to the closed-with-variance handoff. P1-49 (Credit Memo) or a future settlement exception slice can pick up the remaining balance.

#### Manual Failure

13. AP user clicks "Mark Failed" on a scheduled handoff.
14. AP user fills the failure form: `failure_code` (enum), `failure_reason` (min 5 chars).
15. System transitions handoff to `failed`:
    - Locks handoff row, validates `lockVersion`, validates status is `scheduled`.
    - Sets `status = failed`, `failed_by_user_id`, `failed_at`, `failure_code`, `failure_reason`, bumps `lock_version`.
    - Sets each member invoice `payment_status` directly to `handoff_exported` (no transient `payment_failed` column write — the `payment_failed` action is captured in the audit event payload only, avoiding wasteful double-writes within the same transaction).
    - Allocations are kept (not marked voided) so AP can see partial payment history before failure.
    - Records `ap_payment_handoff.failed` audit event with `failureCode` and `failureReason`.
    - Records `supplier_invoice.payment_failed` audit event per invoice (audit-only — the invoice column never holds `payment_failed`).
16. AP user can re-schedule the failed handoff: clicks "Re-schedule" which transitions handoff from `failed` to `scheduled` (resets failure fields, re-advances invoices to `payment_scheduled`).

#### Manual Void

17. AP user clicks "Void" on a `scheduled` or `paid` handoff.
18. AP user fills the void form: `void_reason` (min 5 chars).
19. System transitions handoff to `voided`:
    - Locks handoff row, validates `lockVersion`, validates status is `scheduled` or `paid`.
    - Sets `status = voided`, `voided_by_user_id`, `voided_at`, `void_reason`, bumps `lock_version`.
    - Marks each allocation row `voided_at` (rows kept for audit).
    - Sets each member invoice `payment_status` directly to `handoff_exported` (no transient `payment_voided` column write — the `payment_voided` action is captured in the audit event payload only).
    - Records `ap_payment_handoff.voided` audit event with `voidReason`.
    - Records `supplier_invoice.payment_voided` audit event per invoice (audit-only — the invoice column never holds `payment_voided`).
20. Invoices are now back in `handoff_exported` and can be included in a new handoff schedule attempt.

#### CSV/JSON Import

21. AP user opens the **payment import** page at `/accounts-payable/payment-import` (or a dialog from the payment status queue).
22. AP user uploads a CSV or JSON file.
23. System parses rows into `ApPaymentImport` staging records:
    - Each row maps to: `handoff_number` OR `invoice_number` (one required), `payment_reference`, `allocated_amount` OR `mark_full` (boolean), `settlement_amount` (optional, bank-currency value), `settlement_currency` (optional, disbursement bank account currency), `paid_at`, `settlement_method`, `status` (paid | failed | voided), `failure_code` (if failed), `failure_reason` (if failed), `void_reason` (if voided).
    - Staging records are created in `pending` status with parsed data.
    - Parse errors (missing required fields, invalid enum values) are captured per row.
24. System presents a preview page:
    - Lists all staging rows with proposed state changes.
    - Highlights matching errors (handoff not found, invoice not found, handoff not in `exported` or `scheduled` state, invoice not a member of handoff).
    - AP user can edit individual staging rows to fix matches.
25. AP user clicks "Confirm reconciliation".
26. System runs the reconciliation job in a transaction per staging row:
    - Matches staging row to handoff/invoice.
    - If `status = paid` and `mark_full = true`: creates allocations covering full invoice totals, transitions handoff to `paid`, derives invoice `payment_status = paid`.
    - If `status = paid` and `allocated_amount` specified: creates allocation for that amount, derives invoice `payment_status` (partially_paid or paid), transitions handoff to `paid` only if all invoices fully allocated.
    - If `status = failed`: transitions handoff to `failed` with `failure_code` and `failure_reason`, releases invoices to `handoff_exported`.
    - If `status = voided`: transitions handoff to `voided` with `void_reason`, marks allocations voided, releases invoices to `handoff_exported`.
    - Records `ap_payment_import.reconciled` audit event per staging row.
    - Marks staging row `status = reconciled` with `reconciled_at` and `reconciled_by_user_id`.
27. AP user sees a reconciliation summary: rows reconciled, rows failed, rows skipped.
28. Failed reconciliation rows remain in `pending` status for re-attempt or discard.

### Failure Paths

- No `exported` handoffs when scheduling: return `409`.
- Scheduling a handoff with no member invoices: return `409` (should not happen — P1-47 export requires invoices).
- Scheduling a handoff not in `exported` state: return `409`.
- Adding allocation to handoff not in `scheduled` state: return `409`.
- Adding allocation with `allocated_amount <= 0`: return `422`.
- Adding allocation that would over-allocate invoice (sum > invoice total): return `422` with current allocated and remaining.
- Adding allocation for invoice not in handoff: return `409`.
- Marking paid when invoices are under-allocated: return `409` with list of under-allocated invoices and remaining amounts. AP user must either add allocations covering the remainder (full payment path) or use "Close with variance" to accept the shortfall (short-pay path). This is not a dead-end — the close-with-variance action is the explicit settlement exception path for short-payments.
- Closing with variance without `varianceReason`: return `422`.
- Closing with variance when no allocations exist (zero payment recorded): return `409` — use "Mark Failed" instead, as a zero-payment closure is a failure, not a variance.
- Marking failed without `failure_reason` or `failure_code`: return `422`.
- Voiding without `void_reason`: return `422`.
- Stale `lockVersion` on handoff: return `409`.
- Stale `lockVersion` on invoice: return `409`.
- Cross-tenant access: return `403` or `404` consistent with adjacent handoff routes.
- Import file parse error: return `422` with per-row error details.
- Import row matching error (handoff or invoice not found): staging row stays `pending` with `match_error` field; AP user can edit or discard.
- Re-scheduling a handoff not in `failed` state: return `409`.

## Backend Design

### Domain Ownership

**New domain**: `apps/api/Domains/Payments`

Supporting domains:
- `Domains/AccountsPayable` — handoff model, pre-execution states (unchanged)
- `Domains/Invoice` — supplier invoice, payment_status column extension
- `app/Audit` — audit recording
- `app/Tenancy` — tenant resolution and membership enforcement

### Data Model

#### ApPaymentHandoff — new columns

Add to `ap_payment_handoffs`:

```sql
scheduled_by_user_id          CHAR(36) NULL REFERENCES users(id)
scheduled_at                  TIMESTAMP NULL
scheduled_for_date            DATE NULL
payment_reference             VARCHAR(255) NULL
paid_by_user_id               CHAR(36) NULL REFERENCES users(id)
paid_at                       TIMESTAMP NULL
remittance_reference          VARCHAR(255) NULL
remittance_advice_sent_at     TIMESTAMP NULL
failed_by_user_id             CHAR(36) NULL REFERENCES users(id)
failed_at                     TIMESTAMP NULL
failure_code                  VARCHAR(50) NULL
failure_reason                TEXT NULL
voided_by_user_id             CHAR(36) NULL REFERENCES users(id)
voided_at                     TIMESTAMP NULL
void_reason                   TEXT NULL
variance_amount               DECIMAL(20,4) NULL
variance_reason               TEXT NULL
variance_closed_by_user_id    CHAR(36) NULL REFERENCES users(id)
variance_closed_at            TIMESTAMP NULL
```

Index additions:
- `(tenant_id, status, scheduled_at)` for scheduled handoff queries
- `(tenant_id, status, paid_at)` for paid handoff queries

The `variance_amount`, `variance_reason`, `variance_closed_by_user_id`, and `variance_closed_at` columns are set when the handoff is closed with discrepancy (short-pay) via `CloseApPaymentHandoffWithVariance`. The `variance_amount` is the handoff total minus the sum of allocations — the unallocated balance accepted by AP. See the "Short-Pay / Close With Variance" workflow below.

The existing `status` column now stores the extended enum values. The `ApPaymentHandoffStatus` enum adds `Scheduled`, `Paid`, `Failed`, `Voided` cases. The `canTransitionTo()` method is extended to enforce the P1-48 transition table.

The `booted()` tenant guard validates `scheduled_by_user_id`, `paid_by_user_id`, `failed_by_user_id`, `voided_by_user_id`, `variance_closed_by_user_id` belong to the tenant on save.

#### ApPaymentAllocation (new table)

Create `ap_payment_allocations`:

```sql
id                    CHAR(36) PRIMARY KEY
tenant_id             CHAR(36) NOT NULL REFERENCES tenants(id)
ap_payment_handoff_id CHAR(36) NOT NULL REFERENCES ap_payment_handoffs(id) ON DELETE CASCADE
supplier_invoice_id   CHAR(36) NOT NULL REFERENCES supplier_invoices(id) ON DELETE RESTRICT
allocated_amount      DECIMAL(20,4) NOT NULL
allocation_date       DATE NOT NULL
payment_reference     VARCHAR(255) NULL
settlement_amount     DECIMAL(20,4) NULL
settlement_currency   VARCHAR(3) NULL
voided_at             TIMESTAMP NULL
lock_version          INTEGER NOT NULL DEFAULT 1
created_at            TIMESTAMP NULL
updated_at            TIMESTAMP NULL
```

The `allocated_amount` is always in the invoice's base currency and is the value used for outstanding balance calculation. The `settlement_amount` and `settlement_currency` capture the raw bank clearance value in the disbursement bank account's currency (which may differ from the invoice currency when treasury pays from a USD account for a EUR invoice). When currencies match, `settlement_amount = allocated_amount`. When they differ, `settlement_amount` is informational/audit only and `allocated_amount` is the AP-user-specified invoice-currency equivalent. No FX rate conversion is performed in this slice — both values are stored as-is for downstream reconciliation.

Indexes:
- unique `(ap_payment_handoff_id, supplier_invoice_id, allocation_date, payment_reference) NULLS NOT DISTINCT` — prevents duplicate allocation rows for same handoff+invoice+date+reference. The `NULLS NOT DISTINCT` modifier (PostgreSQL 15+) treats `NULL` `payment_reference` values as equal, closing the double-import loophole when bank files omit per-transaction references. If the deployment targets PostgreSQL < 15, fall back to a functional index on `(COALESCE(payment_reference, '''))` and document the requirement in the migration.
- index `(tenant_id, supplier_invoice_id)` for finding allocations per invoice
- index `(tenant_id, ap_payment_handoff_id)` for loading allocations per handoff

The unique constraint allows multiple allocations per (handoff, invoice) pair across different dates or references (supporting partial payment over time) while preventing exact duplicates — including duplicates where `payment_reference` is omitted.

The service layer additionally normalizes `payment_reference` to `null` only when the trimmed value is empty, so empty-string references from CSV parsers do not bypass the `NULLS NOT DISTINCT` semantics.

The model uses `HasUuids`, `AsPivot` is not used (this is a first-class entity with its own lifecycle, not a pivot). The `booted()` guard validates both `ap_payment_handoff_id` and `supplier_invoice_id` belong to the same tenant.

#### ApPaymentImport (new staging table)

Create `ap_payment_imports`:

```sql
id                    CHAR(36) PRIMARY KEY
tenant_id             CHAR(36) NOT NULL REFERENCES tenants(id)
batch_id              CHAR(36) NOT NULL
row_index             INTEGER NOT NULL
handoff_number        VARCHAR(50) NULL
invoice_number        VARCHAR(255) NULL
payment_reference     VARCHAR(255) NULL
allocated_amount      DECIMAL(20,4) NULL
mark_full             BOOLEAN NOT NULL DEFAULT FALSE
settlement_amount     DECIMAL(20,4) NULL
settlement_currency   VARCHAR(3) NULL
paid_at               DATE NULL
settlement_method     VARCHAR(50) NULL
target_status         VARCHAR(50) NOT NULL
failure_code          VARCHAR(50) NULL
failure_reason        TEXT NULL
void_reason           TEXT NULL
status                VARCHAR(50) NOT NULL DEFAULT 'pending'
match_error           TEXT NULL
matched_handoff_id    CHAR(36) NULL REFERENCES ap_payment_handoffs(id)
matched_invoice_id    CHAR(36) NULL REFERENCES supplier_invoices(id)
reconciled_at         TIMESTAMP NULL
reconciled_by_user_id CHAR(36) NULL REFERENCES users(id)
imported_by_user_id   CHAR(36) NOT NULL REFERENCES users(id)
imported_at           TIMESTAMP NOT NULL
lock_version          INTEGER NOT NULL DEFAULT 1
created_at            TIMESTAMP NULL
updated_at            TIMESTAMP NULL
```

Indexes:
- index `(tenant_id, batch_id, row_index)` for batch loading
- index `(tenant_id, status)` for queue queries
- index `(tenant_id, matched_handoff_id)` for finding imports per handoff

`batch_id` is a UUID generated per upload to group rows from the same file. `row_index` is the 0-based position in the file. `status` is `pending`, `reconciled`, `failed`, or `discarded`. `match_error` is a human-readable string when matching fails. `target_status` is the desired post-reconciliation status: `paid`, `failed`, or `voided`.

#### SupplierInvoice — no new columns

`payment_status` is the existing column from P1-47. The enum extends with new cases. No new columns needed on `supplier_invoices` — `outstanding_amount` is derived from allocations at query time and exposed via the API resource.

### Snapshot Shape

No snapshot extension needed for the handoff. The existing P1-47 snapshot (invoices, vendor, PO, lines, approval context) remains the source of truth for what was in the handoff at export time. P1-48 records the post-export lifecycle as separate state transitions and allocation rows, not as snapshot extensions.

The `ApPaymentHandoffResource` is extended to include:
- Current post-export status fields (`scheduledAt`, `paidAt`, `failedAt`, `voidedAt`, etc.)
- Allocations array (per invoice: `allocatedAmount`, `allocationDate`, `paymentReference`, `voidedAt`)
- Per-invoice derived `paymentStatus` and `outstandingAmount`
- Permissions block: `canSchedule`, `canAddAllocation`, `canMarkPaid`, `canMarkFailed`, `canVoid`, `canRechedule`

### Domain Structure

```txt
apps/api/Domains/Payments/
  Actions/
    ScheduleApPaymentHandoff.php
    AddApPaymentAllocation.php
    MarkApPaymentHandoffPaid.php
    CloseApPaymentHandoffWithVariance.php
    MarkApPaymentHandoffFailed.php
    VoidApPaymentHandoff.php
    RescheduleFailedApPaymentHandoff.php
    ParsePaymentImportFile.php
    MatchPaymentImportRow.php
    ReconcilePaymentImportBatch.php
    DiscardPaymentImportRow.php
  Data/
    PaymentImportRowData.php
    PaymentImportPreviewData.php
    ReconciliationResultData.php
  Http/
    Controllers/
      ApPaymentStatusController.php
      ApPaymentAllocationController.php
      ApPaymentImportController.php
    Requests/
      ScheduleApPaymentHandoffRequest.php
      AddApPaymentAllocationRequest.php
      MarkApPaymentHandoffPaidRequest.php
      CloseApPaymentHandoffWithVarianceRequest.php
      MarkApPaymentHandoffFailedRequest.php
      VoidApPaymentHandoffRequest.php
      RescheduleApPaymentHandoffRequest.php
      UploadPaymentImportRequest.php
      UpdatePaymentImportRowRequest.php
      ReconcilePaymentImportBatchRequest.php
    Resources/
      ApPaymentAllocationResource.php
      ApPaymentImportResource.php
      ApPaymentImportBatchResource.php
  Models/
    ApPaymentAllocation.php
    ApPaymentImport.php
  Policies/
    ApPaymentAllocationPolicy.php
    ApPaymentImportPolicy.php
  States/
    ApPaymentFailureCode.php
    ApPaymentImportStatus.php
    ApPaymentImportTargetStatus.php
  Support/
    PaymentAllocationSumCalculator.php
    PaymentImportCsvParser.php
    PaymentImportJsonParser.php
    PaymentImportBatchIdGenerator.php
  routes/
    api.php
  tests/
    ApPaymentStatusApiTest.php
    ApPaymentAllocationApiTest.php
    ApPaymentImportApiTest.php
```

The existing `ApPaymentHandoffPolicy` in `Domains/AccountsPayable/Policies/` is extended with new abilities: `schedule`, `addAllocation`, `markPaid`, `markFailed`, `void`, `reschedule`. These gate the new Payments-domain controller actions but live on the handoff policy because the subject is still `ApPaymentHandoff`.

Use only the files the implementation needs. Empty folders should not be created.

### Domain Behavior

**`ScheduleApPaymentHandoff`**:
- Accepts handoff, actor, `scheduledForDate`, `paymentReference`, `notes`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `exported`.
- Sets `status = scheduled`, `scheduled_by_user_id`, `scheduled_at = now`, `scheduled_for_date`, `payment_reference`, bumps `lock_version`.
- Iterates member invoices, sets each `payment_status = payment_scheduled`, bumps invoice `lock_version`.
- Records `ap_payment_handoff.scheduled` audit event with `fromStatus`/`toStatus`/`scheduledForDate`/`paymentReference`.
- Records `supplier_invoice.payment_scheduled` audit event per invoice.
- Uses a database transaction.

**`AddApPaymentAllocation`**:
- Accepts handoff, invoice, actor, `allocatedAmount`, `allocationDate`, `paymentReference`, `settlementAmount`, `settlementCurrency`, `lockVersion` (on handoff).
- Locks handoff and invoice rows, validates handoff `lockVersion`, validates handoff status is `scheduled`.
- Validates invoice is a member of the handoff.
- Validates `allocatedAmount > 0` (strictly positive, using `bccomp` against `'0.0000'`).
- Validates `SUM(existing non-voided allocations for invoice) + allocatedAmount <= invoice.total_amount` (no over-allocation, using `bcadd` for precision). Returns `422` with `currentAllocated`, `remainingBalance`, and `attemptedAllocation` if violated.
- If `settlementCurrency` is provided and differs from invoice currency, validates that `settlementAmount` is also provided (bank-currency and invoice-currency values must both be present when currencies differ). No FX conversion is performed — both values are stored as-is.
- If `settlementCurrency` matches invoice currency or is null, `settlementAmount` defaults to `allocatedAmount` if not explicitly provided.
- Normalizes `paymentReference`: trims whitespace; stores `null` if the trimmed value is empty (ensures `NULLS NOT DISTINCT` semantics on the unique index).
- Creates `ApPaymentAllocation` row with `lock_version = 1`.
- Derives invoice `payment_status`: `partially_paid` if sum < total, `paid` if sum = total.
- Bumps invoice `lock_version`.
- Records `ap_payment_allocation.created` audit event with `allocatedAmount`/`allocationDate`/`paymentReference`/`settlementAmount`/`settlementCurrency`.
- If this allocation causes all member invoices to be fully allocated, does NOT auto-transition handoff to `paid` — AP user must explicitly mark paid or close with variance (allows final review).

**`MarkApPaymentHandoffPaid`**:
- Accepts handoff, actor, `remittanceReference`, `remittanceAdviceSentAt`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `scheduled`.
- Validates all member invoices have allocations summing to their totals (handoff is fully paid). Returns `409` with list of under-allocated invoices and remaining amounts if violated.
- Sets handoff `status = paid`, `paid_by_user_id`, `paid_at = now`, `remittance_reference`, `remittance_advice_sent_at`, bumps `lock_version`.
- Derives each invoice `payment_status = paid` (idempotent if already paid via allocations).
- Bumps each invoice `lock_version`.
- Records `ap_payment_handoff.paid` audit event with `fromStatus`/`toStatus`/`remittanceReference`/`remittanceAdviceSentAt`.
- Records `ap_payment.remitted` audit event with `remittanceReference` and `remittanceAdviceSentAt` metadata (the remittance event, not a state).
- Records `supplier_invoice.paid` audit event per invoice.

**`CloseApPaymentHandoffWithVariance`**:
- Accepts handoff, actor, `varianceReason`, `remittanceReference`, `remittanceAdviceSentAt`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `scheduled`.
- Validates `varianceReason` is non-empty (min 5 chars).
- Validates at least one non-voided allocation exists on the handoff (cannot close with variance if no payment was recorded at all — that is a failure, not a variance).
- Computes `variance_amount = handoff_total - SUM(all non-voided allocations across member invoices)` using `bcsub` with 4 decimal places.
- Sets handoff `status = paid`, `paid_by_user_id`, `paid_at = now`, `variance_amount`, `variance_reason`, `variance_closed_by_user_id`, `variance_closed_at = now`, `remittance_reference`, `remittance_advice_sent_at`, bumps `lock_version`.
- Derives each invoice `payment_status`: invoices with allocations summing to their total → `paid`; invoices with partial allocations → stay `partially_paid` (outstanding balance visible for P1-49 credit memo or settlement exception follow-up).
- Bumps each invoice `lock_version`.
- Records `ap_payment_handoff.paid_with_variance` audit event with `fromStatus`/`toStatus`/`varianceAmount`/`varianceReason`/`remittanceReference`/`remittanceAdviceSentAt`.
- Records `ap_payment.remitted` audit event with remittance metadata.
- Records `supplier_invoice.paid` audit event per fully-paid invoice.
- Records `supplier_invoice.partially_paid` audit event per partially-paid invoice (if not already partially_paid).

**`MarkApPaymentHandoffFailed`**:
- Accepts handoff, actor, `failureCode`, `failureReason`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `scheduled`.
- Validates `failureCode` is in `ApPaymentFailureCode` enum.
- Validates `failureReason` is non-empty (min 5 chars).
- Sets handoff `status = failed`, `failed_by_user_id`, `failed_at = now`, `failure_code`, `failure_reason`, bumps `lock_version`.
- Sets each member invoice `payment_status` directly to `handoff_exported` (no transient `payment_failed` column write — the `payment_failed` action is captured in the audit event payload only, avoiding wasteful double-writes and preventing accidental side effects from model boot observers or async dispatchers).
- Allocations are kept (not marked voided) so AP can see partial payment history before failure.
- Bumps each invoice `lock_version`.
- Records `ap_payment_handoff.failed` audit event with `fromStatus`/`toStatus`/`failureCode`/`failureReason`.
- Records `supplier_invoice.payment_failed` audit event per invoice (audit-only — the invoice column never holds `payment_failed`).

**`VoidApPaymentHandoff`**:
- Accepts handoff, actor, `voidReason`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `scheduled` or `paid`.
- Validates `voidReason` is non-empty (min 5 chars).
- Sets handoff `status = voided`, `voided_by_user_id`, `voided_at = now`, `void_reason`, bumps `lock_version`.
- Marks each allocation row `voided_at = now` (rows kept for audit).
- Sets each member invoice `payment_status` directly to `handoff_exported` (no transient `payment_voided` column write — the `payment_voided` action is captured in the audit event payload only).
- Bumps each invoice `lock_version`.
- Records `ap_payment_handoff.voided` audit event with `fromStatus`/`toStatus`/`voidReason`.
- Records `supplier_invoice.payment_voided` audit event per invoice (audit-only — the invoice column never holds `payment_voided`).

**`RescheduleFailedApPaymentHandoff`**:
- Accepts handoff, actor, `scheduledForDate`, `paymentReference`, `notes`, `lockVersion`.
- Locks handoff row, validates `lockVersion`, validates status is `failed`.
- Resets failure fields (`failed_by_user_id = null`, `failed_at = null`, `failure_code = null`, `failure_reason = null`).
- Sets `status = scheduled`, `scheduled_by_user_id`, `scheduled_at = now`, `scheduled_for_date`, `payment_reference`, bumps `lock_version`.
- Re-advances each member invoice `payment_status` from `handoff_exported` to `payment_scheduled`.
- Bumps each invoice `lock_version`.
- Records `ap_payment_handoff.rescheduled` audit event with `fromStatus = failed`/`toStatus = scheduled`.
- Records `supplier_invoice.payment_scheduled` audit event per invoice.

**`ParsePaymentImportFile`**:
- Accepts uploaded file (CSV or JSON), actor.
- Generates a `batch_id` (UUID).
- For CSV: parses with `PaymentImportCsvParser`, validates headers, creates `ApPaymentImport` rows per data row.
- For JSON: parses with `PaymentImportJsonParser`, validates envelope, creates `ApPaymentImport` rows per item.
- Each row gets `row_index`, `tenant_id`, `imported_by_user_id`, `imported_at = now`, `status = pending`.
- Parse errors (missing required fields, invalid enum values) are captured in `match_error` and `status = failed` (not `pending`).
- Returns `PaymentImportPreviewData` with batch_id and row count.
- Does NOT match or reconcile — that is a separate step.

**`MatchPaymentImportRow`**:
- Accepts `ApPaymentImport` row.
- Attempts to match `handoff_number` to an `ApPaymentHandoff` in tenant.
- If `invoice_number` is specified, attempts to match to a `SupplierInvoice` in tenant.
- Validates matched handoff is in `exported` or `scheduled` state (for paid target) or `scheduled` (for failed/voided target).
- Validates matched invoice is a member of matched handoff.
- Sets `matched_handoff_id`, `matched_invoice_id` on success.
- Sets `match_error` and `status = failed` on match failure.
- Does NOT apply state changes — that is reconciliation.

**`ReconcilePaymentImportBatch`**:
- Accepts `batch_id`, actor.
- Loads all `pending` rows in the batch.
- For each row: runs `MatchPaymentImportRow` if not already matched.
- If matched: applies state changes per `target_status`:
  - `paid` with `mark_full = true`: creates allocations covering full invoice totals (with `settlement_amount` and `settlement_currency` from the import row if provided), calls `MarkApPaymentHandoffPaid` (or transitions to `paid` if all invoices fully allocated).
  - `paid` with `allocated_amount`: determines `allocated_amount` for the allocation — if `settlement_currency` matches the invoice currency or is null, uses `settlement_amount` (or `allocated_amount` if `settlement_amount` is null); if `settlement_currency` differs from invoice currency, uses the explicit `allocated_amount` from the import row (AP user must have specified the invoice-currency equivalent during preview edit). Calls `AddApPaymentAllocation`, then `MarkApPaymentHandoffPaid` if all invoices fully allocated.
  - `failed`: calls `MarkApPaymentHandoffFailed` with `failure_code` and `failure_reason`.
  - `voided`: calls `VoidApPaymentHandoff` with `void_reason`.
- Marks row `status = reconciled`, `reconciled_at = now`, `reconciled_by_user_id`.
- Records `ap_payment_import.reconciled` audit event per row.
- Returns `ReconciliationResultData` with counts: reconciled, failed, skipped.

**`DiscardPaymentImportRow`**:
- Accepts `ApPaymentImport` row, actor.
- Validates row `status` is `pending` or `failed` (not `reconciled`).
- Sets `status = discarded`.
- Records `ap_payment_import.discarded` audit event.

### Integration Points

No changes to `MarkSupplierInvoiceApproved` or `AutoAdvanceToPaymentEligible` — P1-47 integration remains intact. P1-48 picks up at the `handoff_exported` state.

No changes to `ExportApPaymentHandoff` — the P1-47 export action continues to set `payment_status = handoff_exported` on member invoices. P1-48 then consumes that state.

### Authorization

**`ApPaymentHandoffPolicy` extensions** (in `Domains/AccountsPayable/Policies/`):
- `schedule`, `addAllocation`, `markPaid`, `closeWithVariance`, `markFailed`, `void`, `reschedule` — all require `Buyer` or `Admin` role + tenant scope on the handoff.

**`ApPaymentAllocationPolicy`** (new, in `Domains/Payments/Policies/`):
- `view`, `create` — all require `Buyer` or `Admin` role + tenant scope. Allocations are only voided as part of a handoff void in this slice.

**`ApPaymentImportPolicy`** (new, in `Domains/Payments/Policies/`):
- `view`, `upload`, `update`, `reconcile`, `discard` — all require `Buyer` or `Admin` role + tenant scope.

Approver can view only if also buyer/admin. Requester has no access. Vendor portal visitors have no access.

### Audit Metadata

| Event | Trigger |
|---|---|
| `ap_payment_handoff.scheduled` | Schedule action |
| `ap_payment_handoff.paid` | Mark paid action (full allocation) |
| `ap_payment_handoff.paid_with_variance` | Close with variance action (short-pay accepted) |
| `ap_payment_handoff.failed` | Mark failed action |
| `ap_payment_handoff.voided` | Void action |
| `ap_payment_handoff.rescheduled` | Re-schedule from failed |
| `ap_payment.remitted` | Remittance advice recorded on paid |
| `ap_payment_allocation.created` | Allocation added |
| `ap_payment_import.reconciled` | Import row reconciled |
| `ap_payment_import.discarded` | Import row discarded |
| `supplier_invoice.payment_scheduled` | Auto-advance on handoff schedule |
| `supplier_invoice.paid` | Derived paid from allocations |
| `supplier_invoice.payment_failed` | Audit event when handoff failed (invoice column goes directly to `handoff_exported`; no transient column write) |
| `supplier_invoice.payment_voided` | Audit event when handoff voided (invoice column goes directly to `handoff_exported`; no transient column write) |

Audit metadata includes: handoff id and number, invoice ids, allocation id and amount, fromStatus, toStatus, scheduledForDate, paymentReference, remittanceReference, remittanceAdviceSentAt, failureCode, failureReason, voidReason, import batch id, import row index.

Register `ApPaymentAllocation::class => 'ap_payment_allocation'` and `ApPaymentImport::class => 'ap_payment_import'` in `App\Audit\AuditSubject::$typeMap` (in `AppServiceProvider::boot()`).

### Concurrency

All mutation actions lock the target row (`lockForUpdate()`) and check `lock_version`. Allocation creation locks both the handoff and the invoice row to prevent concurrent allocations from over-allocating. The reconciliation job locks each handoff and invoice as it processes each staging row.

Handoff `lock_version` and individual invoice `lock_version` are checked independently. A stale lock on either returns `409`. Individual allocation void (separate from handoff void) is deferred — allocations are only voided as part of a handoff void in this slice.

## API Contract

Add tenant-scoped authenticated routes under the `RequireTenantHeader` middleware:

```txt
# Payment status (handoff lifecycle)
POST   /api/ap-payment-handoffs/{handoff}/schedule
POST   /api/ap-payment-handoffs/{handoff}/mark-paid
POST   /api/ap-payment-handoffs/{handoff}/close-with-variance
POST   /api/ap-payment-handoffs/{handoff}/mark-failed
POST   /api/ap-payment-handoffs/{handoff}/void
POST   /api/ap-payment-handoffs/{handoff}/reschedule

# Payment allocations
GET    /api/ap-payment-handoffs/{handoff}/allocations
POST   /api/ap-payment-handoffs/{handoff}/allocations
GET    /api/ap-payment-allocations/{allocation}

# Payment import
POST   /api/accounts-payable/payment-imports/upload
GET    /api/accounts-payable/payment-imports/{batchId}
PATCH  /api/accounts-payable/payment-imports/{importRow}
POST   /api/accounts-payable/payment-imports/{batchId}/reconcile
POST   /api/accounts-payable/payment-imports/{importRow}/discard

# Payment status queue (extends existing handoff list)
GET    /api/ap-payment-handoffs?postExportStatus=scheduled|paid|failed|voided
```

Extend `GET /api/supplier-invoices` with:
- `postExportPaymentStatus` filter: `payment_scheduled`, `partially_paid`, `paid`, `payment_failed`, `none`

Extend `SupplierInvoiceQueueResource` with:
```json
{
  "paymentStatus": "payment_scheduled",
  "outstandingAmount": "400.00",
  "allocatedAmount": "600.00",
  "activePaymentHandoffId": "uuid",
  "activePaymentHandoffNumber": "APH-2026-000123",
  "activePaymentHandoffStatus": "scheduled",
  "lastAllocationAt": "2026-06-19T00:00:00Z"
}
```

Extend `ApPaymentHandoffResource` with post-export fields and permissions:
```json
{
  "scheduledAt": "2026-06-19T00:00:00Z",
  "scheduledByUserId": "uuid",
  "scheduledForDate": "2026-06-20",
  "paymentReference": "PRN-2026-001",
  "paidAt": "2026-06-21T00:00:00Z",
  "paidByUserId": "uuid",
  "remittanceReference": "REM-2026-001",
  "remittanceAdviceSentAt": "2026-06-21T00:00:00Z",
  "failedAt": null,
  "failureCode": null,
  "failureReason": null,
  "voidedAt": null,
  "voidReason": null,
  "varianceAmount": null,
  "varianceReason": null,
  "varianceClosedAt": null,
  "allocations": [
    {
      "id": "uuid",
      "supplierInvoiceId": "uuid",
      "supplierInvoiceNumber": "INV-2026-000042",
      "allocatedAmount": "600.00",
      "allocationDate": "2026-06-20",
      "paymentReference": "PRN-2026-001",
      "settlementAmount": "615.00",
      "settlementCurrency": "USD",
      "voidedAt": null
    }
  ],
  "permissions": {
    "canSchedule": true,
    "canAddAllocation": true,
    "canMarkPaid": true,
    "canCloseWithVariance": true,
    "canMarkFailed": true,
    "canVoid": true,
    "canReschedule": false
  }
}
```

Expected operation IDs:
- `scheduleApPaymentHandoff`
- `markApPaymentHandoffPaid`
- `closeApPaymentHandoffWithVariance`
- `markApPaymentHandoffFailed`
- `voidApPaymentHandoff`
- `rescheduleApPaymentHandoff`
- `listApPaymentAllocations`
- `createApPaymentAllocation`
- `showApPaymentAllocation`
- `uploadPaymentImport`
- `showPaymentImportBatch`
- `updatePaymentImportRow`
- `reconcilePaymentImportBatch`
- `discardPaymentImportRow`

Expected schemas:
- `ScheduleApPaymentHandoffRequest`
- `MarkApPaymentHandoffPaidRequest`
- `CloseApPaymentHandoffWithVarianceRequest`
- `MarkApPaymentHandoffFailedRequest`
- `VoidApPaymentHandoffRequest`
- `RescheduleApPaymentHandoffRequest`
- `AddApPaymentAllocationRequest`
- `ApPaymentAllocationResponse`
- `ApPaymentFailureCode` (enum)
- `UploadPaymentImportRequest` (multipart with file)
- `UpdatePaymentImportRowRequest`
- `ReconcilePaymentImportBatchRequest`
- `ApPaymentImportRowResponse`
- `ApPaymentImportBatchResponse`
- `ApPaymentImportStatus` (enum: pending, reconciled, failed, discarded)
- `ApPaymentImportTargetStatus` (enum: paid, failed, voided)
- `ReconciliationResultResponse`

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas in the web feature. Do not duplicate contract response types in app code.

## Import File Formats

### CSV

Headers (required, order-insensitive):

```txt
handoff_number, invoice_number, payment_reference, allocated_amount, mark_full, settlement_amount, settlement_currency, paid_at, settlement_method, status, failure_code, failure_reason, void_reason
```

- `handoff_number` OR `invoice_number` required (one must be non-empty).
- `allocated_amount` required if `status = paid` and `mark_full = false` and `settlement_currency` differs from invoice currency. If `settlement_currency` matches invoice currency (or is omitted), `allocated_amount` defaults to `settlement_amount` and may be omitted. Decimal with up to 4 decimal places.
- `mark_full` boolean (`true`/`false`, `1`/`0`, `yes`/`no`). If `true`, system creates allocations covering full invoice totals.
- `settlement_amount` optional. The raw bank clearance value in the disbursement bank account's currency. Decimal with up to 4 decimal places.
- `settlement_currency` optional. ISO 4217 code (e.g., `USD`, `EUR`, `MYR`). When omitted, assumed to match the invoice currency.
- `paid_at` required if `status = paid`. Date in `YYYY-MM-DD` format.
- `settlement_method` optional. Freeform string (e.g., `ach`, `wire`, `check`, `card`).
- `status` required. One of `paid`, `failed`, `voided`.
- `failure_code` required if `status = failed`. One of `bank_rejected`, `insufficient_funds`, `vendor_blocked`, `system_error`, `other`.
- `failure_reason` required if `status = failed`. Min 5 chars.
- `void_reason` required if `status = voided`. Min 5 chars.

### JSON

Envelope:

```json
{
  "format": "json",
  "importedAt": "2026-06-19T00:00:00Z",
  "rows": [
    {
      "handoffNumber": "APH-2026-000123",
      "invoiceNumber": "INV-2026-000042",
      "paymentReference": "PRN-2026-001",
      "allocatedAmount": "600.00",
      "markFull": false,
      "settlementAmount": "615.00",
      "settlementCurrency": "USD",
      "paidAt": "2026-06-20",
      "settlementMethod": "ach",
      "status": "paid",
      "failureCode": null,
      "failureReason": null,
      "voidReason": null
    }
  ]
}
```

Same field rules as CSV. CamelCase keys in JSON.

## Web Design

### Pages

- `/accounts-payable/payment-status` — primary P1-48 page. Handoff status tabs (All / Exported / Scheduled / Paid / Failed / Voided). Handoff list with status badges, total amount, invoice count, scheduled/paid/failed/voided timestamps. Right panel: handoff detail with member invoices, allocations, actions.
- `/accounts-payable/payment-import` — import upload page. File upload form, batch preview, row edit, confirm reconciliation, reconciliation summary.

Navigation: add "Payment status" and "Payment import" sub-items under the existing Finance nav group, after "Payment queue". Gated by `canUseAccountsPayable` permission (same as existing Finance items).

### Components

- `payment-status-queue-page.tsx` — workflow component for the status queue page.
- `payment-status-badge.tsx` — extend existing P1-47 component with new states (`scheduled`, `paid`, `partially_paid`, `failed`, `voided`).
- `handoff-schedule-panel.tsx` — schedule form for exported handoffs.
- `handoff-allocation-panel.tsx` — allocation list and add form for scheduled handoffs.
- `handoff-payment-actions-panel.tsx` — mark paid, close with variance, mark failed, void, reschedule actions.
- `handoff-failure-detail.tsx` — failure code and reason display.
- `handoff-variance-detail.tsx` — variance amount and reason display (shown when handoff is `paid` with variance metadata).
- `payment-import-upload-panel.tsx` — file upload form.
- `payment-import-preview-panel.tsx` — batch preview with row edit and confirm.
- `payment-import-reconciliation-summary.tsx` — reconciliation result display.

### Hooks

- `use-ap-payment-handoff-status.ts` — `useScheduleApPaymentHandoff`, `useMarkApPaymentHandoffPaid`, `useCloseApPaymentHandoffWithVariance`, `useMarkApPaymentHandoffFailed`, `useVoidApPaymentHandoff`, `useRescheduleApPaymentHandoff`.
- `use-ap-payment-allocations.ts` — `useApPaymentAllocations(handoffId)`, `useAddApPaymentAllocation`. (Allocation void is via handoff void in this slice.)
- `use-ap-payment-import.ts` — `useUploadPaymentImport`, `usePaymentImportBatch(batchId)`, `useUpdatePaymentImportRow`, `useReconcilePaymentImportBatch`, `useDiscardPaymentImportRow`.

Cache invalidation: handoff status mutations invalidate both `apPaymentHandoffKeys.all` AND `accountsPayableInvoiceKeys.all` (mirrors P1-47 `useInvalidatePaymentCaches`).

### API Helpers

- `accounts-payable-payment-status-api.ts` — thin wrappers over generated client for schedule/mark-paid/mark-failed/void/reschedule.
- `accounts-payable-payment-allocation-api.ts` — thin wrappers for allocation CRUD.
- `accounts-payable-payment-import-api.ts` — thin wrappers for import upload/preview/reconcile.

### MSW Mocks

- `accounts-payable-payment-status-handlers.ts` + `accounts-payable-payment-status-fixtures.ts` — mock handoff status transitions.
- `accounts-payable-payment-allocation-handlers.ts` + `accounts-payable-payment-allocation-fixtures.ts` — mock allocation CRUD.
- `accounts-payable-payment-import-handlers.ts` + `accounts-payable-payment-import-fixtures.ts` — mock import upload, parse, match, reconcile.

Pattern: module-scoped mutable state, exported `reset*MockState()` and `set*MockState()` for tests, `{ data: ... }` envelope, `409` on `lockVersion` mismatch, derived `outstandingAmount` recalculated on each mutation.

## Seed and Demo Data

Extend `DemoProcurementLifecycleSeeder` with payment status scenarios:

| Handoff | Status | Member Invoices | Description |
|---|---|---|---|
| HDOFF-DEMO-002 | exported | INV-2026-DEMO-011, DEMO-012 | Exported, awaiting scheduling |
| HDOFF-DEMO-003 | scheduled | INV-2026-DEMO-013, DEMO-014 | Scheduled, awaiting allocations |
| HDOFF-DEMO-004 | scheduled | INV-2026-DEMO-015, DEMO-016 | Scheduled with partial allocations (DEMO-015 fully allocated, DEMO-016 partially allocated) |
| HDOFF-DEMO-005 | paid | INV-2026-DEMO-017, DEMO-018 | Fully paid with allocations and remittance reference |
| HDOFF-DEMO-006 | failed | INV-2026-DEMO-019, DEMO-020 | Failed with `bank_rejected` code, invoices released to `handoff_exported` |
| HDOFF-DEMO-007 | voided | INV-2026-DEMO-021, DEMO-022 | Voided after scheduling, allocations voided, invoices released to `handoff_exported` |
| HDOFF-DEMO-008 | paid (with variance) | INV-2026-DEMO-023, DEMO-024 | Closed with variance — DEMO-023 fully allocated and `paid`, DEMO-024 partially allocated and `partially_paid` with outstanding balance, variance amount and reason recorded |

Each scenario creates realistic `ApPaymentAllocation` rows, audit events, and derived `SupplierInvoice.payment_status` values. The seeded data references real seeded handoffs, invoices, vendors, and POs.

Add a `seedPaymentStatuses` method to `DemoProcurementLifecycleSeeder` following the existing pattern (idempotent `updateOrCreate`, audit events, `DemoSeedContext` extension if needed).

## Testing and Verification

### API Tests

Add focused tests for:

**Handoff status transitions:**
- exported handoff can be scheduled
- scheduling requires `lockVersion`
- scheduling non-exported handoff returns 409
- scheduled handoff auto-advances member invoices to `payment_scheduled`
- scheduled handoff can be marked paid when all invoices fully allocated
- marking paid with under-allocated invoices returns 409 with list (not a dead-end — close-with-variance is the alternative)
- scheduled handoff can be closed with variance when partially allocated
- closing with variance without reason returns 422
- closing with variance when no allocations exist returns 409 (use mark failed instead)
- closing with variance records `paid_with_variance` audit event with variance amount
- closing with variance keeps partially-paid invoices as `partially_paid` with outstanding visible
- marking paid records remittance audit event
- scheduled handoff can be marked failed with code and reason
- marking failed without reason returns 422
- marking failed releases invoices to `handoff_exported`
- failed handoff can be re-scheduled
- scheduled or paid handoff can be voided with reason
- voiding marks allocations voided and releases invoices to `handoff_exported`
- voiding non-scheduled/paid handoff returns 409
- cross-tenant status transitions denied
- stale lockVersion returns conflict
- real Sanctum/session route stack succeeds before logout and returns 401 after logout

**Allocations:**
- allocation can be added to scheduled handoff
- allocation with non-positive amount returns 422
- allocation that over-allocates invoice returns 422 with current and remaining
- allocation with `settlement_currency` differing from invoice currency requires `settlement_amount`
- allocation with matching `settlement_currency` defaults `settlement_amount` to `allocated_amount`
- allocation with `NULL` `payment_reference` does not create duplicate on retry (NULLS NOT DISTINCT)
- allocation for invoice not in handoff returns 409
- allocation to non-scheduled handoff returns 409
- first allocation moves invoice to `partially_paid`
- allocation reaching full invoice total moves invoice to `paid`
- voided allocation rows are kept for audit (voided via handoff void only in this slice)
- cross-tenant allocation CRUD denied

**Import:**
- CSV file with valid rows creates staging records
- JSON file with valid rows creates staging records
- CSV file with missing required headers returns 422
- CSV row with invalid enum value creates row with `match_error`
- preview shows proposed state changes and matching errors
- AP user can update staging row to fix match
- reconcile applies state changes for matched rows
- reconcile marks rows `reconciled` with audit event
- reconcile skips unmatched rows (leaves `pending`)
- discard marks row `discarded` with audit event
- cross-tenant import CRUD denied

### Web Tests

Add focused tests for:

**Payment status queue page:**
- Status tabs (All, Exported, Scheduled, Paid, Failed, Voided) filter correctly
- Payment status badge renders for each new state
- Header count includes payment-status handoff counts
- Exported handoff shows schedule button
- Scheduled handoff shows allocation panel and mark-paid/mark-failed/void buttons
- Paid handoff shows remittance info and void button
- Failed handoff shows failure code/reason and reschedule button
- Voided handoff shows void reason and no actions

**Allocation panel:**
- Allocation list shows per-invoice allocated amount and outstanding
- Add allocation form requires amount > 0 and allocation date
- Over-allocation error is visible
- Allocation added updates invoice `payment_status` without page navigation
- All-invoices-fully-allocated enables mark-paid button

**Import workflow:**
- File upload form accepts CSV and JSON
- Preview shows parsed rows with match status
- Match error rows show error message and edit form
- Edit row updates handoff/invoice number
- Confirm reconcile triggers reconciliation
- Reconciliation summary shows counts
- Failed reconciliation rows remain editable

### Verification Commands

Expected implementation verification:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApPaymentStatus
cd apps/api && php artisan test --filter=ApPaymentAllocation
cd apps/api && php artisan test --filter=ApPaymentImport
cd apps/api && php artisan test --filter=ApPaymentHandoff          # regression - P1-47 handoff lifecycle
cd apps/api && php artisan test --filter=SupplierInvoicePayment    # regression - P1-47 payment status
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
pnpm lint
```

## Non-Goals Reiteration

This slice explicitly does NOT include:
- Live bank integration, ACH/wire/check generation, or direct payment execution
- Payment method selection (ACH vs wire vs check vs card)
- Automated payment scheduling based on vendor payment terms
- Remittance advice document generation or delivery
- BAI2, camt.054, pain.001, pain.002, or ISO 20022 file parsers
- Credit memo, debit note, invoice reversal, or post-paid adjustment handling (P1-49)
- Vendor banking master data or remittance contact fields (P1-51)
- Payment batch approval workflow
- Reversal allocation rows (Oracle negative-line pattern)
- `reversed` state for post-paid corrections (P1-49)
- Spend analytics, payment aging dashboard, or reporting views (P2-x)
- Direct ERP or accounting system sync (P3-x)

## Completion Definition

This slice is complete when:
- Exported handoffs can be scheduled for payment with optional scheduled-for date and payment reference.
- Scheduling auto-advances member invoices from `handoff_exported` to `payment_scheduled` in the same transactional boundary.
- AP users can add allocations to scheduled handoffs, recording full or partial payment per invoice.
- Allocations derive invoice `payment_status` (`partially_paid` or `paid`) from allocation sums.
- AP users can mark scheduled handoffs as paid when all invoices are fully allocated, recording remittance advice as an audit event.
- AP users can close scheduled handoffs with variance when invoices are partially allocated (short-pay), recording the variance amount and reason, keeping partially-paid invoices visible for P1-49 follow-up.
- Allocations store both `allocated_amount` (invoice currency) and `settlement_amount` + `settlement_currency` (bank clearance currency) when they differ, preventing FX mismatch crashes on import.
- The unique constraint on `ap_payment_allocations` uses `NULLS NOT DISTINCT` to prevent duplicate allocations when `payment_reference` is omitted by bank files.
- The `payment_failed` and `payment_voided` transitions are audit-only events — the invoice column releases directly to `handoff_exported` without wasteful transient column writes.
- AP users can mark scheduled handoffs as failed with a failure code and reason, releasing invoices to `handoff_exported` for re-attempt.
- AP users can void scheduled or paid handoffs, marking allocations voided and releasing invoices to `handoff_exported`.
- AP users can re-schedule failed handoffs for a new payment attempt.
- AP users can upload CSV or JSON payment status files to a staging table, preview proposed state changes, edit unmatched rows, and confirm reconciliation.
- Reconciliation applies state changes per staging row, records audit events, and marks rows `reconciled` or `failed`.
- The payment status queue is surfaced as a dedicated page at `/accounts-payable/payment-status` under the Finance sidebar group, separate from the payment readiness queue.
- The payment import page is surfaced at `/accounts-payable/payment-import` under the Finance sidebar group.
- All states, transitions, allocations, and imports are tenant-scoped, authorized, audited, and protected by lock-version concurrency.
- OpenAPI endpoints are generated and consumed by `@cognify/api-client`.
- Seeded demo data covers exported (HDOFF-DEMO-002), scheduled (003), scheduled with partial allocations (004), paid (005), failed (006), voided (007), and paid-with-variance (008) handoffs.
- Downstream P1-49 (Credit Memo and Invoice Adjustment) has a clear `paid`, `partially_paid`, and `voided` precondition to consume for reversal, write-off, and adjustment flows.

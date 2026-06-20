# Credit Memo and Invoice Adjustment Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-20
- Release scope: P1 core procure-to-pay lifecycle, slice P1-49 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-49`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-approval-design.md`
  - `docs/superpowers/specs/2026-06-18-payment-readiness-ap-handoff-design.md`
  - `docs/superpowers/specs/2026-06-19-payment-status-tracking-design.md`
- Downstream slices:
  - P1-50 Budget Commitment (consumes `purchase_order_line_id` linkage on `SupplierCreditMemoLine`)
  - P1-51 Vendor Master (consumes credit memo aggregates on outstanding balance)
  - P1-52 Tax/Currency (consumes credit memo tax/currency for multi-currency gain/loss and tax reporting)
  - P1-53 Record Graph (consumes credit memos for procure-to-pay trace)
  - P1-54 Operational Queues (consumes unapplied credits aging and exception queue)
- Reference patterns:
  - `docs/superpowers/specs/2026-06-19-payment-status-tracking-design.md` (post-export payment allocation junction `ApPaymentAllocation` and its `lock_version` + `voided_at` pattern)
  - `docs/superpowers/specs/2026-06-17-invoice-approval-design.md` (shared `Approval` domain, `SupplierInvoiceApprovalSubjectHandler` for amount-based approval routing)
  - `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md` (exception resolution with audit + lock_version)
  - `docs/superpowers/specs/2026-06-18-payment-readiness-ap-handoff-design.md` (snapshot pattern on `ApPaymentHandoff`, numbering scheme, `HandoffExported` release pattern)
- External research: NetSuite *Vendor Credits* guide (Citrin Cooperman, cleverence, Stripe, Zuora); SAP S/4HANA *Credit Memo* transaction type and SAP Ariba credit memo IR document; Oracle Payables credit memo matching and Oracle Receivables *Credit Memo Request Workflow*; Microsoft Dynamics 365 Business Central *Purchase Credit Memos*; Coupa AP automation; Sage Intacct AP adjustments and `Reversed` terminal state; HubSpot credit memo management lifecycle (Draft → Unapplied → Partially Applied → Applied); Umbrex P2P playbook; Miami University AP policy; Oracle Fusion *Maximize Credits* auto-application pattern.

## Roadmap Analysis

P1-49 asks Cognify to support the vendor-issued credit memo lifecycle plus post-approval invoice adjustments. The roadmap explicitly notes: "Support credits, debit notes, invoice reversals, and invoice adjustments linked to original invoices and purchase order lines. Real AP operations need controlled correction paths."

P1-48 (Payment Status Tracking) is now complete. The P1-48 design spec's Non-Goals section explicitly defers to P1-49:

- *"Credit memo, debit note, invoice reversal, or post-paid adjustment handling (P1-49 scope)."*
- *"`reversed` state for post-paid corrections (P1-49 scope)."*

P1-47 (Payment Readiness and AP Handoff) created the `handoff_exported` payment state and the `ApPaymentHandoff` aggregate. P1-48 layered `payment_scheduled`, `partially_paid`, and `paid` on top, plus `ApPaymentAllocation` allocations and an `ApPaymentImport` staging workflow. P1-49 picks up at the `paid`/`partially_paid` end state and adds the reverse-direction construct: vendor credits applied to invoices.

The current codebase has:
- Mature `SupplierInvoice` and `SupplierInvoiceLine` with `status` (capture/review/matching/approval) and `payment_status` (pre-execution through `paid`/`partially_paid`) — no `reversed` value yet
- `ApPaymentHandoff` with full post-export lifecycle
- `ApPaymentAllocation` as a first-class entity with `lock_version`, `voided_at`, `allocated_amount` (invoice currency), and optional `settlement_amount` / `settlement_currency` (bank clearance)
- Shared `Approval` domain with `ApprovalSubjectHandler` interface and per-entity handlers (Requisition, RFQ Award, Purchase Order, Supplier Invoice)
- `AuditRecorder` writing tenant-scoped audit events with subject type, event name, actor, and payload
- `Attachment` polymorphic relation with `attachable_type` / `attachable_id` (used for invoice attachments)
- `Vendor` and `PurchaseOrder` (with `PurchaseOrderLine`) models, plus the `purchase_order_id` / `purchase_order_line_id` linkage on `SupplierInvoiceLine`
- Numbering: `INV-2026-000001` (SupplierInvoice), `APH-2026-000001` (ApPaymentHandoff), with a tenant-scoped `*_number_sequences` table

No credit memo, credit application, vendor-issued credit, or invoice-reversal infrastructure exists yet. This slice introduces the first credit-memo and credit-application constructs.

## Problem

After P1-48, Cognify can move invoices through `paid` and `partially_paid` states and can close handoffs with variance for short-pays. Cognify still cannot:

- Issue a vendor-issued credit memo when a supplier refunds part of an invoice (returned goods, pricing dispute, billing error).
- Apply credits to one or more invoices to reduce or fully offset the invoice outstanding balance.
- Reclassify an invoice's `payment_status` to `reversed` when credits fully offset it (the state mandated by P1-48's deferral).
- Net credits against invoices already in `partially_paid` or `paid` status (retroactive settlement).
- Distinguish `reversed` from `paid` in reporting and operational queues.
- Void or unapply a credit memo that was applied to the wrong invoice.
- Route credit memos through the shared approval domain with amount-based thresholds (mirror invoice approval).
- Link credit memo lines to original invoice lines and PO lines for audit, budget commitment relief (P1-50), and trace.
- Surface the credit memo lifecycle in the AP workspace as a first-class workflow (separate from invoices and handoffs).

Without P1-49, the AP clerk is stuck in an operational deadlock when a vendor issues a credit after invoice approval or payment. The only paths today are (a) record the credit outside Cognify and lose audit trail, (b) cancel the paid invoice manually (no path), or (c) create a new negative invoice (no path). All three leave the P2P lifecycle broken and the audit ledger incomplete. P1-48's short-pay path accepts a variance in `paid` handoffs, but the underlying liability on the supplier's books cannot be cleared without a credit memo construct.

## Goals

- Introduce a new `CreditMemo` Laravel domain boundary (mirrors P1-48's `Payments` domain carve-out from `AccountsPayable`) for credit-memo and credit-application behavior.
- Add three new models: `SupplierCreditMemo` (header), `SupplierCreditMemoLine` (line-level detail with optional PO/invoice line references), and `CreditApplication` (junction linking credits to invoices with applied amounts).
- Generate tenant-scoped credit memo numbers in the `CM-YYYY-NNNNNN` pattern (mirrors the existing `INV-YYYY-NNNNNN` pattern on `SupplierInvoice`).
- Extend `SupplierInvoicePaymentStatus` with a `reversed` value for invoices fully offset by credits — the value mandated by P1-48's deferral.
- Implement a post-first-then-apply model: credit memo is posted to the vendor account (transitions to `open`), then applied to one or more invoices via `CreditApplication` records. Partial application is supported, with remaining credit tracked.
- Add a shared `Approval` domain `SupplierCreditMemoApprovalSubjectHandler` for amount-based credit-memo approval routing, mirroring the invoice approval pattern.
- Reuse the `ApPaymentAllocation` pattern (allocated_amount, application_date, applied_by_user_id, voided_at, lock_version) for `CreditApplication` so that credit application and payment allocation share operational semantics.
- Reuse the `Attachment` polymorphic relation for credit memo attachments.
- Reuse the `AuditRecorder` for credit memo and credit application audit events.
- Validate credits lightly (no 3-way match) — vendor match, amount cap, tax code mirroring, duplicate detection, math validation, with an exception queue for failures.
- Keep tenant isolation, lock-version concurrency, generated-client contracts, and real route-stack tests consistent with established patterns.

## Non-Goals

- Debit notes (buyer-issued claims — different actor, different workflow).
- Full invoice reversals (complete nullification of an invoice — edge case, can be modeled as credit = invoice total).
- Refund/cash recovery path (vendor sends money back — requires negative handoff complexity).
- Multi-currency realized gain/loss on credit application.
- Vendor portal credit memo submission (suppliers creating credits in the portal).
- OCR/email intake automation for credit memos.
- Credit memo expiration / write-off workflow (expired credits, immaterial balances).
- Cross-subsidiary / cross-tenant credit application.
- Credit memo matching to PO without invoice reference (on-account credits without original invoice).
- Subsequent debit / subsequent credit (SAP S/4HANA's additional transaction types).
- Credit memo request workflow (pre-approval request document before credit memo creation).
- Automated credit application during payment runs (Oracle Fusion "maximize credits" pattern).
- Cash discount handling on credit memos (Microsoft Dynamics pattern).
- Remittance advice for credit applications.

## Design Decisions

### Dedicated `CreditMemo` Domain

P1-49 introduces `apps/api/Domains/CreditMemo` for credit-memo and credit-application behavior. The existing `Invoice` domain retains ownership of the supplier invoice itself and its lifecycle (capture → review → match → approve). The existing `AccountsPayable` domain retains ownership of pre-execution payment readiness. The new `CreditMemo` domain owns:

- Credit memo header and line lifecycle (draft → pending approval → approved → open → partially applied → fully applied → closed / voided)
- Credit application junction and its lifecycle (create, void)
- Invoice `payment_status` transition to `reversed` when fully offset by credits
- Lightweight credit validation (vendor match, tax mirroring, math check, duplicate detection)
- Exception queue for credit memo validation failures
- Forward hooks for P1-50 (PO line linkage), P1-51 (vendor balance), and P1-52 (tax/currency)

This boundary mirrors the P1-48 split where `Payments` was carved out from `AccountsPayable` once post-execution behavior was clear enough. With P1-48 complete and the payment lifecycle stable, the credit-memo boundary is the next logical carve-out.

### Post-First-Then-Apply Model

Cognify follows the NetSuite vendor credit pattern: credit memo is *posted* to the vendor account first (creates a credit on the vendor's books, transitions to `open`), then *applied* to one or more invoices via `CreditApplication` records. This separation is the consensus across major ERPs:

- **NetSuite**: "Vendor credits in NetSuite are records of credits owed by vendors. Once a vendor credit is approved, it is posted against the vendor's account, creating a credit balance. From there, it can be applied to one or more open bills." (Source: NetSuite cleverence vendor credits guide)
- **Oracle Payables**: "You can create a credit memo and then match it to one or more invoices. The credit memo reduces the invoice balance." (Source: Oracle Payables user guide)
- **SAP S/4HANA**: "The credit memo is posted to the vendor account. The credit can be applied to subsequent invoices or used to clear existing open items." (Source: SAP S/4HANA Financial Accounting learning)
- **Sage Intacct**: Credit memos are posted first, then applied: "Posted → Partially Applied → Fully Applied." (Source: Sage Intacct AP adjustments)
- **HubSpot**: Draft → Unapplied → Partially Applied → Applied. (Source: HubSpot credit memo management)

This is in contrast to a "create-and-apply-in-one-step" model (where the credit is immediately bound to a specific invoice at creation), which forces a tightly-coupled credit-invoice pair and prevents cross-invoice netting. The post-first-then-apply model supports partial application, cross-invoice application, and netting against future invoices — all consensus patterns in mature AP systems.

### Three Settlement Patterns — Netting Is Primary

Enterprise AP systems support three settlement patterns for vendor credits. Research across NetSuite, Oracle, Microsoft Dynamics, Coupa, and university AP policies converged:

1. **Netting** (primary): Apply the credit to one or more future invoices (or a partially-paid invoice) to reduce the amount the buyer owes. This is the dominant pattern in US and EU corporate AP. (Sources: Miami University AP policy, NetSuite vendor credits guide, Coupa AP automation)
2. **Refund**: Vendor sends cash back. Used when no future invoices are expected, or when the credit exceeds the buyer's outstanding invoice balance. (Source: Microsoft Dynamics 365 Business Central *Purchase Credit Memos*)
3. **Retroactive**: Apply the credit to a previously paid invoice. Netting path with the invoice in `paid` state and a `CreditApplication` row reducing the credit (or a negative allocation) — handled in this slice via the same `CreditApplication` model as netting.

Cognify implements the **netting** pattern in P1-49. Refund is deferred (non-goal). Retroactive netting against a `paid` invoice uses the same `CreditApplication` mechanics as forward netting and is in scope. The credit application reduces the invoice's outstanding balance; if the credit fully offsets the invoice, the invoice's `payment_status` transitions to `reversed` (new value, see below).

### `reversed` Payment Status Value

P1-48's Non-Goals section explicitly defers: *"`reversed` state for post-paid corrections (P1-49 scope)."* P1-49 introduces the new value `reversed` on `SupplierInvoicePaymentStatus` for invoices fully offset by credits.

**Research**: Sage Intacct has `Reversed` as a terminal payment state for AP invoices offset by credits. (Source: Sage Intacct AP adjustments). NetSuite uses an `Applied` credit balance that drives the bill status to "Paid in Full" but does not have a separate `reversed` value (their approach is implicit). Cognify makes the reversal explicit so the operational queue and reporting can distinguish `reversed` (fully offset by credit) from `paid` (fully offset by payment) without joining to `credit_applications`.

**Transition rule**: An invoice transitions to `reversed` when `SUM(non-voided credit_applications.allocated_amount) >= invoice.total_amount` AND the credit memo(s) covering that amount are in `partially_applied`/`fully_applied` state. The transition is derived at the same time the credit application is created (no transient column write — the `payment_status` value lands on `reversed` directly when the application pushes the invoice to full offset).

**Distinction from `paid`**: A `paid` invoice has been settled by bank transfer. A `reversed` invoice has been fully offset by credits (no cash ever moved). The two are operationally distinct: `paid` invoices can be reported as cash disbursed, `reversed` invoices as credit-net. Both are terminal.

**Distinction from `voided`**: A `voided` invoice is a pre-approval cancellation (status = `rejected` or `cancelled` upstream of `approved`). A `reversed` invoice is a post-approval credit offset (status = `approved` or later, with credit applications netting to full). Cognify does not introduce a `voided` payment status — that is a pre-approval concept, not a payment concept.

### Lightweight Validation — No 3-Way Match

Cognify does not perform 3-way match (PO ↔ receipt ↔ invoice) on credit memos. This is the consensus across all major ERPs:

- **SAP Ariba**: "Credit memos are processed through a streamlined validation. The system checks for math errors, missing references, and duplicate entries. Full PO/receipt matching is not required for credit memos." (Source: SAP Ariba credit memo processing)
- **Oracle PeopleSoft**: "Debit memo adjustment vouchers are auto-created from matching exceptions. The credit memo itself does not re-trigger 3-way match." (Source: Oracle PeopleSoft Payables)
- **NetSuite**: Vendor credits require approval but not PO/receipt match. (Source: NetSuite vendor credits guide)
- **Umbrex P2P Playbook**: "Credits reduce liability rather than create it. The risk profile is lower than invoices, and full matching is not industry standard." (Source: Umbrex P2P Playbook)

Cognify's lightweight validation:
- **Vendor match (zero tolerance)**: Credit memo vendor must match the original invoice vendor. Vendor mismatch is a hard exception.
- **Tax code mirroring**: Credit memo lines must use the same tax codes as the original invoice lines. Tax amount is the negative of the original tax amount.
- **Math validation**: Credit memo line subtotals must sum to the header subtotal; subtotal + tax + freight = total.
- **Duplicate detection**: A credit memo with the same `supplier_credit_memo_number` (vendor-issued number) + `vendor_id` + `original_invoice_id` is flagged as a duplicate.
- **Amount cap**: Credit memo total cannot exceed the original invoice total without an approval override (configurable per tenant; default = no override allowed; per-tenant setting in a future slice).

**What is NOT validated**:
- PO line quantities (credit can exceed invoiced quantity — supplier may have over-shipped, and the credit corrects regardless of receipt).
- Receipt matches.
- Currency conversion (credit must use original invoice currency; FX gain/loss is non-goal).

**Exception queue**: Validation failures land in `SupplierCreditMemoException` (mirroring the P1-17 invoice exception pattern) with a resolution workflow (acknowledge, resolve, escalate). The credit memo remains in `draft` until the exception is resolved.

### Shared `Approval` Domain Handler

P1-49 introduces `SupplierCreditMemoApprovalSubjectHandler` to route credit memo approvals through the shared `Approval` domain. The handler:

- Builds an `ApprovalContextData` with `amount = creditMemo.total_amount`, `currency = creditMemo.currency`, `vendorId = creditMemo.vendor_id`, `supplierCreditMemoId` (new context field on `ApprovalContextData`).
- Provides `taskSubjectSummary`, `taskTitle`, `notificationSubjectLabel`, `notificationBody` for the approval task UI.
- `onApproved` callback posts the credit memo (transitions from `approved` to `open`).
- `onRejected` callback returns the credit memo to `draft` with a reason.
- `onChangesRequested` callback returns the credit memo to `draft` with requested changes.

**Amount-based tiering**: Mirrors `SupplierInvoiceApprovalSubjectHandler`. The approval policy version is keyed on the credit memo's `vendor_id`, `currency`, and `total_amount`. Default tiers (mirroring invoice):
- Under threshold T1: Optional STP (Straight-Through Processing) for trusted vendors (deferred — non-goal to implement, design reserves the path).
- T1 to T2: Single approver (buyer or admin).
- Above T2: Multi-stage approval.

**No approval routing for**: Credit application creation (post-approval, AP-only). Credit memo voiding. Credit application voiding. These are AP-clerk direct mutations with audit.

### PO Line Linkage Hook for P1-50

`SupplierCreditMemoLine` carries an optional `purchase_order_line_id` FK (mirroring `SupplierInvoiceLine.purchase_order_line_id`). When a credit is applied to an invoice, the credit application carries the credit memo line id, which in turn carries the PO line id. P1-50 (Budget Commitment) will consume this linkage to relieve encumbrance on the PO line: a credit reduces the "invoiced" commitment, which frees budget for the same PO line.

P1-49 only stores the data; P1-50 computes the relief. This is a forward-looking hook, not a P1-49 commitment. The `purchase_order_line_id` field is optional and nullable; credits without a PO line reference (e.g., on-account credits) are supported.

### Tax Mirroring

Credit memo tax codes per line must mirror the original invoice tax codes. Research converges:

- **NetSuite**: "When creating a vendor credit for a product return, you should mirror the tax behavior of the original invoice. The credit must use the same tax codes to keep the books balanced." (Source: NetSuite vendor credits guide)
- **Oracle Payables**: "Credit memos assigned to invoices that calculate tax must also calculate tax. The tax amount is the negative of the original." (Source: Oracle Payables)
- **Sage 300**: "Credit notes use the same source codes as the original invoice for tax." (Source: Sage 300 AP journal entries)

Cognify stores `tax_code` and `tax_amount` (always negative of original) on `SupplierCreditMemoLine`. The credit memo `total_amount` is `subtotal_amount + tax_amount + freight_amount`, with `tax_amount` typically negative.

**Multi-currency note**: P1-49 does NOT support credit memos in a different currency than the original invoice. The credit memo `currency` must equal the referenced invoice `currency`. Cross-currency credit memos (and the resulting FX gain/loss) are P1-52 scope.

### Reuse `ApPaymentAllocation` Pattern for `CreditApplication`

The `CreditApplication` model mirrors `ApPaymentAllocation` (P1-48):
- `allocated_amount` (DECIMAL 20,4) — credit applied to the invoice in the invoice's currency.
- `application_date` (DATE) — when the credit was applied.
- `applied_by_user_id` (FK to users) — the AP user who applied the credit.
- `voided_at` (TIMESTAMP NULL) — when the application was voided (if voided).
- `voided_by_user_id` (FK to users NULL) — who voided it.
- `void_reason` (TEXT NULL) — reason for voiding.
- `lock_version` (INTEGER) — for optimistic concurrency.

This pattern is shared so the AP workspace can present credit applications and payment allocations with consistent operational semantics (both reduce outstanding balance, both can be voided, both carry lock_version).

### New `SupplierInvoicePaymentStatus::Reversed` Value

P1-49 extends `Domains\AccountsPayable\States\SupplierInvoicePaymentStatus` with `Reversed = 'reversed'`. The enum is extended, not replaced. Allowed transitions are updated:

| From | Action | To |
|---|---|---|
| `paid` | Credit application fully offsets remaining balance | `reversed` |
| `partially_paid` | Credit application fully offsets remaining balance | `reversed` |
| `payment_scheduled` | Credit application fully offsets invoice total | `reversed` |
| `payment_eligible` | Credit application fully offsets invoice total | `reversed` |
| `on_hold` | (cannot apply credit while on hold) | (no change) |
| `payment_ready` | Credit application fully offsets invoice total | `reversed` |
| `handoff_exported` | (credit application requires credit memo in `open`; handoff already exported) | (no change — defer to P1-50) |
| `reversed` | (terminal) | (no further transitions) |

The `reversed` value is terminal (like `paid`). `isTerminal()` returns `true` for both `paid` and `reversed`. The label is "Reversed" (matches Sage Intacct terminology).

### Supplier Credit Memo Numbering

Cognify generates `CM-YYYY-NNNNNN` per tenant, mirroring the existing `INV-YYYY-NNNNNN` pattern. The sequence is per-tenant, scoped to the credit memo domain, and stored in the existing `*_number_sequences` table or a new `credit_memo_number_sequences` table (consistent with how P1-12 invoice numbering was implemented — verify the existing pattern). The vendor's own credit memo number is stored separately as `vendor_credit_memo_number` for matching against supplier statements.

### Concurrency on Credit Application

Credit application creation locks both the credit memo row and the target invoice row to prevent concurrent applications from over-applying. The check is `SUM(non-voided credit applications for this credit memo) + new applied amount <= credit memo remaining_amount`, where `remaining_amount = credit_memo.total_amount - SUM(non-voided applications)`. The same lock pattern guards invoice over-application: `SUM(non-voided applications for this invoice across all credit memos) + new amount <= invoice.outstanding_amount`.

This is the same lock pattern used by P1-48's `AddApPaymentAllocation` and is extended naturally to credit memos.

## Approaches Considered

1. **Dedicated `CreditMemo` domain + post-first-then-apply + `reversed` payment status (selected)**. New `SupplierCreditMemo`, `SupplierCreditMemoLine`, `CreditApplication` tables. `reversed` added to `SupplierInvoicePaymentStatus`. Shared approval handler. Lightweight validation, no 3-way match. Mirrors the mature ERP pattern (NetSuite, Oracle, SAP, Sage Intacct) and integrates cleanly with the P1-47/P1-48 payment lifecycle.

2. **Type-field on `SupplierInvoice` for credits (rejected)**. Add a `type` field to `SupplierInvoice` (e.g., `credit`) and treat credit invoices as negative invoices. The status, payment_status, approval, and exception workflows are reused as-is. Rejected because (a) credit memos have a separate lifecycle (open → partially applied → fully applied) that does not map cleanly to invoice states, (b) credit applications are a separate junction (many-to-many between credits and invoices) that does not exist on invoices, (c) auditing credit memos separately from invoices is required for tax and reporting, and (d) NetSuite/Oracle/SAP all use a separate document with separate numbering, not a negative invoice. The "credit = negative invoice" anti-pattern is well-documented as a common AP mistake.

3. **Debit notes included (rejected)**. Add buyer-issued debit notes in the same slice as vendor-issued credit memos. Rejected because debit notes are a different actor (buyer-initiated, sent to vendor) with a different workflow (vendor must accept or dispute). Adding debit notes doubles the scope and conflates two distinct AP concepts. Debit notes are deferred to a future slice.

4. **Refund path included (rejected)**. Add a "refund" path where the vendor sends cash back, separate from credit application. Rejected because (a) refund requires negative payment handoff complexity (negative total, negative allocation), (b) refund is rare in practice compared to netting, (c) NetSuite/Oracle both treat refund as a separate credit-type transaction in a later accounting module, not part of AP credit memo. Refund is a non-goal in P1-49 and can be added later as a separate workflow.

5. **Type-field on `ApPaymentAllocation` for credit (rejected)**. Reuse the `ApPaymentAllocation` table with a `type` field (`payment` or `credit`) to represent credit applications. Rejected because (a) the junction subject is different (credit memo vs handoff), (b) `ApPaymentAllocation` references `ap_payment_handoff_id` which does not exist for credit memos, (c) audit events for credit application are different from payment allocation events, and (d) lock_version semantics differ (credit applications lock both credit memo and invoice; payment allocations lock handoff and invoice). A separate `CreditApplication` table is clearer and matches the P1-48 pattern of one junction per source aggregate.

6. **3-way match on credit memos (rejected)**. Require PO ↔ receipt ↔ credit memo match. Rejected because the consensus across all major ERPs is that credit memos do not require 3-way match. Credits reduce liability, not create it; the risk profile is lower; and over-validation creates operational friction. The lightweight validation (vendor match, tax mirroring, math, duplicates, amount cap) is sufficient.

7. **3-stage approval on all credit memos (rejected)**. Require multi-stage approval on every credit memo regardless of amount. Rejected because low-value credit memos (e.g., a $50 product return) do not warrant multi-stage approval. Amount-based tiering (mirroring invoice approval) is the consensus pattern.

## Workflow

### Actors

- **AP user** (buyer or admin): Creates, edits, submits, approves (within amount tier), posts, applies, voids credit memos. Creates and voids credit applications. Resolves credit memo exceptions. Mirrors existing P1-17 invoice exception resolution.
- **Approver**: Reviews credit memo approval tasks within their tier, approves/rejects/changes-requested. The credit memo's `onApproved` callback posts the memo (transitions to `open`).
- **Admin**: Same as AP user with additional tenant operations.
- **Requester**: Read-only visibility on credit memos that affect their invoices (rare — requesters don't typically see AP credit memos).
- **System**: Auto-derives invoice `payment_status` on credit application. Generates credit memo numbers. Routes approval tasks. Records audit events.
- **Vendor portal visitor**: No access in P1-49 (vendor portal credit memo submission is non-goal).

### State Model

#### `SupplierCreditMemoStatus` (new enum)

| Status | Meaning | Entry Condition |
|---|---|---|
| `draft` | Being created; no financial impact; editable | Create action |
| `pending_approval` | Submitted for approval; routed by amount tier | Submit action on `draft` |
| `approved` | Approved; ready to post | `onApproved` callback from approval domain |
| `open` | Posted to vendor account; credit available for application | Post action on `approved` (or directly after approval callback) |
| `partially_applied` | Some amount applied; remainder available | Credit application lands, sum < total |
| `fully_applied` | All amount applied; auto-transitions to `closed` | Credit application lands, sum = total |
| `closed` | Terminal archival state | Auto on `fully_applied` |
| `voided` | Cancelled before full application; reverses credit | Void action on `draft` / `pending_approval` / `approved` / `open` / `partially_applied` |

Allowed transitions:

| From | Action | To | Notes |
|---|---|---|---|
| `draft` | Submit | `pending_approval` | Routes approval task |
| `draft` | Void | `voided` | Requires reason |
| `pending_approval` | Approval callback (onApproved) | `approved` | Triggers post |
| `pending_approval` | Approval callback (onRejected) | `draft` | Returns to draft with reason |
| `pending_approval` | Approval callback (onChangesRequested) | `draft` | Returns to draft with requested changes |
| `pending_approval` | Void | `voided` | Requires reason |
| `approved` | Post | `open` | Usually automatic via onApproved callback |
| `approved` | Void | `voided` | Requires reason |
| `open` | Credit application created (partial) | `partially_applied` | Application sum < total |
| `open` | Credit application created (full) | `fully_applied` | Application sum = total |
| `partially_applied` | Credit application created (partial) | `partially_applied` | Application sum still < total |
| `partially_applied` | Credit application created (full) | `fully_applied` | Application sum = total |
| `partially_applied` | Void | `voided` | Requires reason; existing applications also voided |
| `fully_applied` | (auto) | `closed` | Same transaction as final application |
| `voided` | (no further transitions) | — | Terminal for P1-49 |
| `closed` | (no further transitions) | — | Terminal for P1-49 |

The `canTransitionTo()` method on the enum enforces the table.

#### `SupplierInvoicePaymentStatus` (extended)

P1-48 states remain: `payment_eligible`, `on_hold`, `payment_ready`, `handoff_exported`, `payment_scheduled`, `partially_paid`, `paid`.

P1-49 adds the derived value `reversed`.

| Payment Status | Meaning | Derivation |
|---|---|---|
| `reversed` | Fully offset by credits | `SUM(non-voided credit applications across all credit memos applied to this invoice) >= invoice.total_amount` AND the invoice is in a pre-terminal payment state |

Allowed transitions (extending P1-48):

| From | Action | To | Notes |
|---|---|---|---|
| `payment_eligible` | Credit application fully offsets total | `reversed` | No transient write; lands directly |
| `payment_ready` | Credit application fully offsets total | `reversed` | No transient write; lands directly |
| `partially_paid` | Credit application fully offsets remaining balance | `reversed` | No transient write; lands directly |
| `paid` | Credit application fully offsets total (retroactive netting) | `reversed` | No transient write; lands directly; existing payment allocations kept for audit |
| `payment_scheduled` | (deferred — credit application while handoff scheduled) | — | Return 409; user must void handoff first |
| `on_hold` | (cannot apply credit while on hold) | — | Return 409 |
| `handoff_exported` | (deferred — credit application while handoff exported) | — | Return 409; user must void handoff first |
| `reversed` | (no further transitions in this slice) | — | Terminal |

The `reversed` value is terminal. `isTerminal()` is updated to return `true` for `paid` and `reversed`. The label is "Reversed".

### Main Flow

#### 1. Creation

1. AP user opens the **credit memo queue** page at `/accounts-payable/credit-memos`.
2. AP user clicks "New credit memo".
3. AP user fills the create form: `vendor_id` (required), `original_invoice_id` (required for invoice-linked credits), `vendor_credit_memo_number` (required, freeform), `credit_date` (required), `currency` (auto-populated from original invoice, immutable), `lines[]` (each with `description`, `quantity`, `unit_price`, `tax_code`, `tax_amount`, optional `purchase_order_line_id`).
4. System creates a `SupplierCreditMemo` in `draft` with auto-generated `CM-YYYY-NNNNNN` number:
   - Locks the vendor row, validates tenant scope.
   - Validates original invoice is in same tenant and (if specified) is in `approved` or later status.
   - Auto-populates `currency` from the original invoice.
   - Creates `SupplierCreditMemoLine` rows (one per line).
   - Validates math: `lines[].line_subtotal.sum() + tax + freight = total`.
   - Validates tax code mirroring: if any line has a `tax_code`, the original invoice's corresponding line must have the same `tax_code`.
   - Validates vendor match: `credit_memo.vendor_id == original_invoice.vendor_id`.
   - Detects duplicate: same `vendor_id` + `original_invoice_id` + `vendor_credit_memo_number` raises a validation warning (not a hard error; the user can override if the supplier re-sent the same number with a correction).
   - Bumps `lock_version = 1`.
   - Records `supplier_credit_memo.created` audit event with the credit memo number, vendor, original invoice, lines, totals.
5. AP user reviews the credit memo detail panel showing header, lines, math validation, and exceptions (if any).

#### 2. Edit and Exception Resolution

6. AP user edits a `draft` credit memo (header or lines).
7. System updates the credit memo: PATCH on the header, POST/PATCH/DELETE on lines.
8. AP user resolves any open exceptions via the exception panel:
   - Click "Acknowledge" — marks exception as seen.
   - Click "Resolve" — provides resolution notes, marks exception as resolved.
   - Click "Escalate" — flags exception for senior review.
9. Once all blocking exceptions are resolved, the credit memo is submittable.

#### 3. Submit for Approval

10. AP user clicks "Submit for approval".
11. System transitions credit memo to `pending_approval`:
    - Locks credit memo row, validates `lockVersion`, validates status is `draft`.
    - Validates no open blocking exceptions.
    - Validates at least one line exists.
    - Sets `status = pending_approval`, `submitted_by_user_id`, `submitted_at`, bumps `lock_version`.
    - Calls `ApprovalSubjectRegistry::route($creditMemo, $actor)` to create approval instance and tasks per the approval policy version.
    - Records `supplier_credit_memo.submitted_for_approval` audit event.
12. The approval task appears in the approver's queue with the credit memo summary, vendor, total, currency, and exceptions.

#### 4. Approval

13. Approver reviews the credit memo in the approval task panel.
14. Approver clicks "Approve" / "Reject" / "Request changes":
    - **Approve**: System calls `SupplierCreditMemoApprovalSubjectHandler::onApproved`, which posts the credit memo (transitions to `open`).
    - **Reject**: System calls `onRejected`, which returns the credit memo to `draft` with a reason.
    - **Request changes**: System calls `onChangesRequested`, which returns to `draft` with requested changes.

#### 5. Post (Automatic on Approval)

15. The `onApproved` callback (or an explicit Post action on `approved` if no approval was required) transitions the credit memo to `open`:
    - Locks credit memo row, validates `lockVersion`, validates status is `approved`.
    - Sets `status = open`, `posted_by_user_id`, `posted_at = now`, bumps `lock_version`.
    - Records `supplier_credit_memo.posted` audit event with the credit memo number, vendor, total.
16. The credit memo is now available for application to invoices.

#### 6. Apply Credit to Invoice

17. AP user opens the credit memo detail page (or the invoice detail page → "Apply credit" panel).
18. AP user clicks "Apply to invoice".
19. AP user fills the application form: `supplier_invoice_id` (must be a member of the same vendor), `applied_amount` (DECIMAL 20,4, > 0), `application_date` (required, default today), `notes` (optional).
20. System creates a `CreditApplication` row:
    - Locks credit memo and invoice rows, validates `lockVersion` on credit memo.
    - Validates credit memo is in `open` or `partially_applied` status.
    - Validates invoice is a member of the credit memo's vendor (zero tolerance — vendor mismatch is a hard error).
    - Validates `applied_amount > 0` (using `bccomp`).
    - Validates `SUM(non-voided applications for this credit memo) + applied_amount <= credit_memo.total_amount` (no over-application of credit, using `bcadd`).
    - Validates `SUM(non-voided applications for this invoice across all credit memos) + applied_amount <= invoice.outstanding_amount` (no over-application of credit to invoice, using `bcadd`).
    - Validates invoice is not in `payment_scheduled` or `handoff_exported` (return 409 with "void handoff first" guidance).
    - Validates invoice is not in `on_hold` (return 409).
    - Creates `CreditApplication` row with `lock_version = 1`.
    - Derives credit memo `status`: `partially_applied` if sum < total, `fully_applied` if sum = total.
    - Bumps credit memo `lock_version`.
    - Derives invoice `payment_status`:
      - If `SUM(non-voided applications for this invoice) >= invoice.total_amount` → `reversed`.
      - Else if previously `paid` and now reversed → `reversed` (the credit application landed on a paid invoice and fully offset it — retroactive netting).
      - Else if sum < total and invoice was `payment_eligible` / `payment_ready` → stays in pre-state (credit doesn't change payment status for un-fully-offset credits; the application row itself is the operational signal).
      - Else if sum < total and invoice was `partially_paid` → stays `partially_applied` (or remains `partially_paid` for the credit-applied angle).
    - Bumps invoice `lock_version`.
    - Records `supplier_credit_memo.applied` audit event with the credit memo number, application id, applied amount, invoice number, invoice new payment status.
    - Records `supplier_invoice.credit_applied` audit event on the invoice with the credit memo number, application id, applied amount, prior payment status, new payment status.
    - If the application caused the credit memo to reach `fully_applied`, auto-transitions to `closed` in the same transaction and records `supplier_credit_memo.closed` audit event.
    - If the application caused the invoice to reach `reversed`, records `supplier_invoice.reversed` audit event.
21. AP user sees the updated credit memo detail with the application row, and the updated invoice detail with the new `payment_status`.

#### 7. Apply Credit to Multiple Invoices

22. AP user repeats step 18-21 for additional invoices until the credit memo is `fully_applied` or the remainder is reserved for future application.
23. Each application is its own `CreditApplication` row with its own `lock_version`, allowing partial / staged application.

#### 8. Void Credit Application (Unapply)

24. AP user opens the credit memo detail or invoice detail and clicks "Void application" on a specific `CreditApplication` row.
25. AP user fills the void form: `void_reason` (min 5 chars).
26. System voids the application:
    - Locks credit application row, validates `lockVersion`, validates `voided_at IS NULL`.
    - Sets `voided_at = now`, `voided_by_user_id`, `void_reason`, bumps `lock_version`.
    - Recomputes credit memo `status`: if all applications voided, returns to `open`; if some remain, stays `partially_applied`; never returns to `closed` from `fully_applied` (terminal). The `closed` state is preserved for audit; new applications cannot be created on a `closed` credit memo.
    - Recomputes invoice `payment_status`:
      - Recompute `SUM(non-voided applications for this invoice)`.
      - If sum = 0 and invoice was `reversed` due to this voided application: transition back to prior state (`partially_paid`, `paid`, etc.). The prior state is not stored — recompute from `payment_allocations` and `credit_applications` history. P1-49 keeps the prior state simple: if all credit applications on a reversed invoice are voided, transition back to `partially_paid` (the most common post-partial state). For invoices that were `paid` before the credit application, transition back to `paid`. For invoices that were `payment_eligible` before, transition back to `payment_eligible`.
      - If sum < total: transition back from `reversed` to `partially_paid` (or to the invoice's prior payment state if the original was `paid`).
      - Bumps invoice `lock_version`.
    - Records `supplier_credit_memo.application_voided` audit event with the application id, void reason, prior amounts, new amounts.
    - Records `supplier_invoice.credit_application_voided` audit event on the invoice.

#### 9. Void Credit Memo

27. AP user opens the credit memo detail and clicks "Void credit memo" (button visible on `draft`, `pending_approval`, `approved`, `open`, `partially_applied`).
28. AP user fills the void form: `void_reason` (min 5 chars).
29. System voids the credit memo:
    - Locks credit memo row, validates `lockVersion`.
    - Validates status is in `draft`, `pending_approval`, `approved`, `open`, or `partially_applied`. (Not `fully_applied`, `closed`, or already `voided`.)
    - Sets `status = voided`, `voided_by_user_id`, `voided_at`, `void_reason`, bumps `lock_version`.
    - Voids all non-voided `CreditApplication` rows on this credit memo (sets `voided_at = now`, `voided_by_user_id`, `void_reason`).
    - For each invoice that had a non-voided application: recomputes invoice `payment_status` (same logic as step 26).
    - Records `supplier_credit_memo.voided` audit event with the credit memo number, void reason, prior status, applications voided count.
    - Records `supplier_invoice.credit_memo_voided` audit event per affected invoice.

#### 10. Close (Automatic)

30. When the credit memo reaches `fully_applied` (sum of non-voided applications = total), the system auto-transitions to `closed` in the same transaction as the final application. No user action required. The `closed` state is terminal; new applications cannot be added. This mirrors NetSuite's auto-close behavior.

### Failure Paths

- Submitting a credit memo with no lines: return `422` with "at least one line required".
- Submitting a credit memo with math errors: return `422` with the line subtotal vs header total mismatch.
- Submitting a credit memo with vendor mismatch: return `422` (vendor mismatch is a hard error, not an exception).
- Submitting a credit memo with tax code mirroring violations: return `422` with the offending lines.
- Submitting a credit memo with open blocking exceptions: return `409` with the exception IDs.
- Submitting a credit memo not in `draft`: return `409`.
- Approving a credit memo not in `pending_approval`: return `409`.
- Posting a credit memo not in `approved`: return `409`.
- Applying a credit to a non-member vendor invoice: return `422` (vendor mismatch).
- Applying a credit with `applied_amount <= 0`: return `422`.
- Applying a credit that would over-apply the credit memo: return `422` with `currentApplied`, `remainingAmount`, `attemptedAmount`.
- Applying a credit that would over-apply the invoice: return `422` with `invoiceOutstanding`, `currentCreditApplied`, `attemptedAmount`.
- Applying a credit to a `payment_scheduled` or `handoff_exported` invoice: return `409` with "void handoff first" guidance.
- Applying a credit to an `on_hold` invoice: return `409` with "release hold first" guidance.
- Applying a credit to an invoice not in `open` / `partially_applied` credit memo: return `409`.
- Voiding a `CreditApplication` not in non-voided state: return `409`.
- Voiding a credit memo not in a voidable state (`fully_applied` / `closed` / already `voided`): return `409`.
- Cross-tenant access: return `403` or `404` consistent with adjacent handoff routes.
- Stale `lockVersion` on credit memo: return `409`.
- Stale `lockVersion` on invoice: return `409`.
- Stale `lockVersion` on credit application: return `409`.
- Concurrent application creation: locks the credit memo and invoice rows; second concurrent application returns `409` with lock version mismatch.

## Backend Design

### Domain Ownership

**New domain**: `apps/api/Domains/CreditMemo`

Supporting domains:
- `Domains/Invoice` — supplier invoice (status / payment_status column extension to add `reversed`)
- `Domains/AccountsPayable` — `SupplierInvoicePaymentStatus` enum (extended with `Reversed`)
- `Domains/Approval` — shared approval domain (`SupplierCreditMemoApprovalSubjectHandler` registered)
- `Domains/PurchaseOrder` — PO line model (FK target from `SupplierCreditMemoLine`)
- `Domains\Vendor\Models\Vendor` — vendor model (FK target from `SupplierCreditMemo`)
- `app/Audit` — audit recording
- `app/Tenancy` — tenant resolution and membership enforcement
- `app/Auth` — role checks

### Data Model

#### `SupplierCreditMemo` (new table)

Create `supplier_credit_memos`:

```sql
id                              CHAR(36) PRIMARY KEY
tenant_id                       CHAR(36) NOT NULL REFERENCES tenants(id)
number                          VARCHAR(50) NOT NULL
vendor_credit_memo_number       VARCHAR(255) NULL
vendor_id                       CHAR(36) NOT NULL REFERENCES vendors(id)
original_invoice_id             CHAR(36) NULL REFERENCES supplier_invoices(id) ON DELETE RESTRICT
status                          VARCHAR(50) NOT NULL DEFAULT 'draft'
currency                        VARCHAR(3) NOT NULL
subtotal_amount                 DECIMAL(20,4) NOT NULL DEFAULT 0
tax_amount                      DECIMAL(20,4) NOT NULL DEFAULT 0
freight_amount                  DECIMAL(20,4) NOT NULL DEFAULT 0
total_amount                    DECIMAL(20,4) NOT NULL DEFAULT 0
credit_date                     DATE NULL
notes                           TEXT NULL
captured_by_user_id             CHAR(36) NULL REFERENCES users(id)
captured_at                     TIMESTAMP NULL
submitted_by_user_id            CHAR(36) NULL REFERENCES users(id)
submitted_at                    TIMESTAMP NULL
approved_by_user_id             CHAR(36) NULL REFERENCES users(id)
approved_at                     TIMESTAMP NULL
posted_by_user_id               CHAR(36) NULL REFERENCES users(id)
posted_at                       TIMESTAMP NULL
voided_by_user_id               CHAR(36) NULL REFERENCES users(id)
voided_at                       TIMESTAMP NULL
void_reason                     TEXT NULL
approval_instance_id            CHAR(36) NULL REFERENCES approval_instances(id)
stp_eligible                    BOOLEAN NOT NULL DEFAULT FALSE
stp_processed_at                TIMESTAMP NULL
lock_version                    INTEGER NOT NULL DEFAULT 1
created_at                      TIMESTAMP NULL
updated_at                      TIMESTAMP NULL
```

Indexes:
- unique `(tenant_id, number)` — credit memo number uniqueness per tenant
- index `(tenant_id, vendor_id, status)` — for vendor + status queries
- index `(tenant_id, original_invoice_id)` — for "credits on this invoice" lookups
- index `(tenant_id, status, posted_at)` — for queue queries
- index `(tenant_id, vendor_credit_memo_number)` — for duplicate detection

The `number` column is auto-generated at create time using the per-tenant `credit_memo_number_sequences` table (or equivalent pattern — verify the existing `invoice_number_sequences` implementation and mirror it). The `vendor_credit_memo_number` is the supplier-issued number for matching against supplier statements (freeform, nullable for on-account credits).

The `original_invoice_id` is nullable to support on-account credits (credits without a referenced invoice). When non-null, the credit memo's `vendor_id` must match the invoice's `vendor_id`, and the credit memo's `currency` must match the invoice's `currency`.

`total_amount` is computed as `subtotal_amount + tax_amount + freight_amount` and validated on save (and re-validated on transitions). `tax_amount` is typically negative for credits (mirror of original tax).

`approval_instance_id` follows the same pattern as `SupplierInvoice.approval_instance_id` — points to the `ApprovalInstance` for the current pending or completed approval, or null if no approval is in progress.

The `booted()` guard validates:
- `vendor_id` belongs to the same tenant
- `original_invoice_id` (if set) belongs to the same tenant and has the same `vendor_id`
- `currency` matches `original_invoice.currency` when `original_invoice_id` is set
- `captured_by_user_id`, `submitted_by_user_id`, `approved_by_user_id`, `posted_by_user_id`, `voided_by_user_id` belong to the same tenant
- `approval_instance_id` (if set) belongs to the same tenant

The model uses `HasUuids`, `assertLockVersion(int $lockVersion): void` method that throws `ConflictHttpException` on mismatch (mirroring `SupplierInvoice`).

#### `SupplierCreditMemoLine` (new table)

Create `supplier_credit_memo_lines`:

```sql
id                              CHAR(36) PRIMARY KEY
tenant_id                       CHAR(36) NOT NULL REFERENCES tenants(id)
supplier_credit_memo_id         CHAR(36) NOT NULL REFERENCES supplier_credit_memos(id) ON DELETE CASCADE
purchase_order_line_id          CHAR(36) NULL REFERENCES purchase_order_lines(id) ON DELETE RESTRICT
original_invoice_line_id        CHAR(36) NULL REFERENCES supplier_invoice_lines(id) ON DELETE RESTRICT
line_number                     INTEGER NOT NULL
description_snapshot            TEXT NOT NULL
quantity                        DECIMAL(20,4) NOT NULL DEFAULT 1
unit_price                      DECIMAL(20,4) NOT NULL DEFAULT 0
line_subtotal                   DECIMAL(20,4) NOT NULL DEFAULT 0
tax_code                        VARCHAR(50) NULL
tax_amount                      DECIMAL(20,4) NOT NULL DEFAULT 0
notes                           TEXT NULL
created_at                      TIMESTAMP NULL
updated_at                      TIMESTAMP NULL
```

Indexes:
- index `(tenant_id, supplier_credit_memo_id, line_number)` — for ordering lines per credit memo
- index `(tenant_id, purchase_order_line_id)` — for P1-50 budget commitment consumption
- index `(tenant_id, original_invoice_line_id)` — for "credits on this invoice line" lookups

The `purchase_order_line_id` is optional and nullable — credits without a PO line reference are supported. The `original_invoice_line_id` is optional and nullable — credits without an invoice line reference (e.g., on-account credits) are supported. When both are set, they must reference the same PO (validates on save).

The `tax_code` and `tax_amount` mirror the original invoice line. The `tax_amount` is typically negative (mirror of original tax). Tax code validation against the original invoice line happens on save (or on transition to `submitted`).

The `booted()` guard validates:
- `supplier_credit_memo_id` belongs to the same tenant
- `purchase_order_line_id` (if set) belongs to the same tenant
- `original_invoice_line_id` (if set) belongs to the same tenant and to the same `original_invoice_id` as the credit memo

#### `CreditApplication` (new table)

Create `credit_applications`:

```sql
id                          CHAR(36) PRIMARY KEY
tenant_id                   CHAR(36) NOT NULL REFERENCES tenants(id)
supplier_credit_memo_id     CHAR(36) NOT NULL REFERENCES supplier_credit_memos(id) ON DELETE CASCADE
supplier_invoice_id         CHAR(36) NOT NULL REFERENCES supplier_invoices(id) ON DELETE RESTRICT
applied_amount              DECIMAL(20,4) NOT NULL
application_date            DATE NOT NULL
applied_by_user_id          CHAR(36) NOT NULL REFERENCES users(id)
notes                       TEXT NULL
voided_at                   TIMESTAMP NULL
voided_by_user_id           CHAR(36) NULL REFERENCES users(id)
void_reason                 TEXT NULL
lock_version                INTEGER NOT NULL DEFAULT 1
created_at                  TIMESTAMP NULL
updated_at                  TIMESTAMP NULL
```

Indexes:
- index `(tenant_id, supplier_credit_memo_id)` — for "applications for this credit memo" queries
- index `(tenant_id, supplier_invoice_id)` — for "credits applied to this invoice" queries
- unique `(tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date) NULLS NOT DISTINCT` — prevents duplicate applications for the same (credit memo, invoice, date) tuple. The `NULLS NOT DISTINCT` modifier (PostgreSQL 15+) is used so that any future nullable fields do not bypass the unique semantics. (Mirroring the P1-48 `ApPaymentAllocation` pattern.)

The `applied_amount` is always in the invoice's currency (which equals the credit memo's currency, which is enforced). No FX conversion is performed.

The `booted()` guard validates:
- `supplier_credit_memo_id` belongs to the same tenant
- `supplier_invoice_id` belongs to the same tenant and has the same `vendor_id` as the credit memo
- `applied_by_user_id`, `voided_by_user_id` belong to the same tenant

#### `SupplierCreditMemoException` (new table, mirrors P1-17 `SupplierInvoiceException`)

Create `supplier_credit_memo_exceptions`:

```sql
id                              CHAR(36) PRIMARY KEY
tenant_id                       CHAR(36) NOT NULL REFERENCES tenants(id)
supplier_credit_memo_id         CHAR(36) NOT NULL REFERENCES supplier_credit_memos(id) ON DELETE CASCADE
exception_type                  VARCHAR(100) NOT NULL
severity                        VARCHAR(50) NOT NULL DEFAULT 'warning'
description                     TEXT NOT NULL
resolution_type                  VARCHAR(50) NULL
resolution_notes                TEXT NULL
resolved_by_user_id             CHAR(36) NULL REFERENCES users(id)
resolved_at                     TIMESTAMP NULL
acknowledged_by_user_id         CHAR(36) NULL REFERENCES users(id)
acknowledged_at                 TIMESTAMP NULL
escalated_by_user_id            CHAR(36) NULL REFERENCES users(id)
escalated_at                    TIMESTAMP NULL
expected_value                  DECIMAL(20,4) NULL
adjusted_value                  DECIMAL(20,4) NULL
lock_version                    INTEGER NOT NULL DEFAULT 1
created_at                      TIMESTAMP NULL
updated_at                      TIMESTAMP NULL
```

Exception types (enum `SupplierCreditMemoExceptionType`):
- `missing_invoice_reference` — `original_invoice_id` is null but tenant policy requires it
- `over_credit` — credit memo total exceeds original invoice total beyond tenant cap
- `vendor_mismatch` — credit memo vendor does not match original invoice vendor (informational, not blocking — vendor mismatch is also a 422 on submit)
- `tax_code_mismatch` — credit memo line tax code does not match original invoice line tax code
- `math_error` — credit memo line subtotals do not sum to header subtotal
- `duplicate_credit` — same `vendor_id` + `original_invoice_id` + `vendor_credit_memo_number` already exists
- `missing_tax_code` — original invoice line has a tax code but credit memo line does not
- `currency_mismatch` — credit memo currency differs from original invoice currency

Resolution types (enum `SupplierCreditMemoExceptionResolutionType`):
- `accepted` — exception is accepted (no action needed)
- `value_adjustment` — credit memo total is adjusted
- `vendor_reassignment` — credit memo is reassigned to correct vendor
- `voided` — credit memo is voided
- `info_only` — informational exception, no resolution needed

`severity` is `blocking` (prevents submit), `warning` (allows submit), or `info` (no action). The exception types map to severity: `missing_invoice_reference` (when required by policy) is `blocking`, `over_credit` is `blocking`, `tax_code_mismatch` is `blocking`, `math_error` is `blocking`, `duplicate_credit` is `warning`, `vendor_mismatch` is `info_only` (also a 422 on submit), `missing_tax_code` is `warning`, `currency_mismatch` is `blocking`.

The model uses `HasUuids`, `assertLockVersion(int $lockVersion): void` method. The `booted()` guard validates tenant scope on `supplier_credit_memo_id` and user IDs.

#### `SupplierInvoice` — no new columns

`payment_status` is the existing column from P1-47. The enum extends with the `Reversed` case. No new columns needed on `supplier_invoices` — `outstanding_amount` is derived from `credit_applications` (in addition to existing `payment_allocations`) at query time and exposed via the API resource. P1-49 adds a new derived value: `credit_applied_amount = SUM(non-voided credit_applications.allocated_amount)`. Combined with `paid_amount = SUM(non-voided payment_allocations.allocated_amount)`, the new derived `outstanding_amount = invoice.total_amount - paid_amount - credit_applied_amount`.

#### `SupplierInvoicePaymentStatus` — extended

Add `Reversed = 'reversed'` to the enum. The `label()` method returns "Reversed" for `Reversed`. The `isTerminal()` method returns `true` for both `Paid` and `Reversed`. The `isEligibleForHandoff()` method returns `false` for `Reversed`.

### Snapshot Shape

No snapshot extension needed for the credit memo. The credit memo's `lines` array, header fields, and audit events are the source of truth for what was on the credit memo at any point. There is no "export" step for credit memos (they live in Cognify only). P1-50 may add a snapshot for budget commitment linkage; P1-49 does not.

The `SupplierCreditMemoResource` exposes:
- Header fields (number, status, vendor, original invoice, currency, totals, dates, audit timestamps)
- Lines array (per line: line_number, description, quantity, unit_price, line_subtotal, tax_code, tax_amount, PO line id, original invoice line id)
- Applications array (per application: id, invoice number, applied_amount, application_date, applied_by_user_id, voided_at)
- Computed `remaining_amount = total_amount - SUM(non-voided applications)`
- Computed `applied_amount = SUM(non-voided applications)`
- Exception list (if any)
- Approval task summary (if pending or completed)
- Permissions block: `canEdit`, `canSubmit`, `canApprove`, `canReject`, `canPost`, `canApply`, `canVoidApplication`, `canVoidCreditMemo`, `canResolveException`

### Domain Structure

```txt
apps/api/Domains/CreditMemo/
  Actions/
    CreateSupplierCreditMemo.php
    UpdateSupplierCreditMemo.php
    SubmitSupplierCreditMemoForApproval.php
    PostSupplierCreditMemo.php
    VoidSupplierCreditMemo.php
    AddSupplierCreditMemoLine.php
    UpdateSupplierCreditMemoLine.php
    RemoveSupplierCreditMemoLine.php
    CreateCreditApplication.php
    VoidCreditApplication.php
    AcknowledgeSupplierCreditMemoException.php
    ResolveSupplierCreditMemoException.php
    EscalateSupplierCreditMemoException.php
  Data/
    SupplierCreditMemoContextData.php
    CreditApplicationPreviewData.php
    SupplierCreditMemoExceptionData.php
  Http/
    Controllers/
      SupplierCreditMemoController.php
      SupplierCreditMemoLineController.php
      CreditApplicationController.php
      SupplierCreditMemoExceptionController.php
    Requests/
      CreateSupplierCreditMemoRequest.php
      UpdateSupplierCreditMemoRequest.php
      SubmitSupplierCreditMemoForApprovalRequest.php
      PostSupplierCreditMemoRequest.php
      VoidSupplierCreditMemoRequest.php
      AddSupplierCreditMemoLineRequest.php
      UpdateSupplierCreditMemoLineRequest.php
      CreateCreditApplicationRequest.php
      VoidCreditApplicationRequest.php
      AcknowledgeSupplierCreditMemoExceptionRequest.php
      ResolveSupplierCreditMemoExceptionRequest.php
      EscalateSupplierCreditMemoExceptionRequest.php
    Resources/
      SupplierCreditMemoResource.php
      SupplierCreditMemoLineResource.php
      CreditApplicationResource.php
      SupplierCreditMemoExceptionResource.php
  Models/
    SupplierCreditMemo.php
    SupplierCreditMemoLine.php
    CreditApplication.php
    SupplierCreditMemoException.php
  Policies/
    SupplierCreditMemoPolicy.php
    CreditApplicationPolicy.php
    SupplierCreditMemoExceptionPolicy.php
  States/
    SupplierCreditMemoStatus.php
    SupplierCreditMemoExceptionType.php
    SupplierCreditMemoExceptionSeverity.php
    SupplierCreditMemoExceptionResolutionType.php
  SubjectHandlers/
    SupplierCreditMemoApprovalSubjectHandler.php
  Support/
    SupplierCreditMemoNumberGenerator.php
    SupplierCreditMemoMathValidator.php
    SupplierCreditMemoDuplicateDetector.php
    SupplierCreditMemoTaxMirrorValidator.php
    CreditApplicationSumCalculator.php
    SupplierCreditMemoStateMachine.php
  routes/
    api.php
  tests/
    SupplierCreditMemoApiTest.php
    CreditApplicationApiTest.php
    SupplierCreditMemoExceptionApiTest.php
```

The `SupplierCreditMemoPolicy` lives in `Domains/CreditMemo/Policies/`. The `CreditApplicationPolicy` and `SupplierCreditMemoExceptionPolicy` are also in the same directory.

Register `SupplierCreditMemo::class => 'supplier_credit_memo'`, `SupplierCreditMemoLine::class => 'supplier_credit_memo_line'`, `CreditApplication::class => 'credit_application'`, and `SupplierCreditMemoException::class => 'supplier_credit_memo_exception'` in `App\Audit\AuditSubject::$typeMap` (in `AppServiceProvider::boot()`).

Register `Gate::policy(SupplierCreditMemo::class, SupplierCreditMemoPolicy::class)`, `Gate::policy(CreditApplication::class, CreditApplicationPolicy::class)`, and `Gate::policy(SupplierCreditMemoException::class, SupplierCreditMemoExceptionPolicy::class)`.

Register the new approval subject handler `$app->make(SupplierCreditMemoApprovalSubjectHandler::class)` in the `ApprovalSubjectRegistry` singleton in `AppServiceProvider::register()`.

### Domain Behavior

**`CreateSupplierCreditMemo`**:
- Accepts tenant, actor, `vendorId`, `originalInvoiceId`, `vendorCreditMemoNumber`, `creditDate`, `currency`, `lines[]`, `notes`.
- Locks the vendor row, validates tenant scope.
- Validates `originalInvoiceId` (if set) is in same tenant and (if specified) is in `approved` or later status.
- Auto-populates `currency` from original invoice if not provided.
- Generates next `CM-YYYY-NNNNNN` number via `SupplierCreditMemoNumberGenerator`.
- Validates math: `lines[].line_subtotal.sum() + tax + freight = total`.
- Validates tax code mirroring: each line's `tax_code` must match the original invoice line's `tax_code` (if any).
- Validates vendor match: `credit_memo.vendor_id == original_invoice.vendor_id` (zero tolerance).
- Detects duplicate: same `vendor_id` + `original_invoice_id` + `vendor_credit_memo_number` raises a duplicate exception (warning, not hard error).
- Creates `SupplierCreditMemo` row with `status = draft`, `lock_version = 1`, `captured_by_user_id`, `captured_at`.
- Creates `SupplierCreditMemoLine` rows.
- Runs `SupplierCreditMemoExceptionBuilder` to create any exceptions (math, duplicate, tax mirror, vendor mismatch, missing invoice reference).
- Records `supplier_credit_memo.created` audit event.
- Returns the credit memo with lines, applications (empty), and exceptions (if any).

**`UpdateSupplierCreditMemo`**:
- Accepts credit memo, actor, `notes`, `creditDate`, `vendorCreditMemoNumber`, `lockVersion`.
- Validates status is `draft`.
- Validates `lockVersion`.
- Updates fields, bumps `lock_version`.
- Records `supplier_credit_memo.updated` audit event.

**`AddSupplierCreditMemoLine`**:
- Accepts credit memo, actor, `lineNumber`, `description`, `quantity`, `unitPrice`, `taxCode`, `taxAmount`, `purchaseOrderLineId`, `originalInvoiceLineId`, `lockVersion`.
- Validates status is `draft`.
- Validates `lockVersion`.
- Validates `purchaseOrderLineId` (if set) belongs to tenant.
- Validates `originalInvoiceLineId` (if set) belongs to same `original_invoice_id` as the credit memo.
- Computes `line_subtotal = quantity * unit_price` (using `bcmul`).
- Creates `SupplierCreditMemoLine` row.
- Recomputes header totals from lines: `subtotal_amount = SUM(lines[].line_subtotal)`, `tax_amount = SUM(lines[].tax_amount)`, `total_amount = subtotal + tax + freight`.
- Re-runs math validator and tax mirror validator.
- Bumps credit memo `lock_version`.
- Records `supplier_credit_memo.line_added` audit event.

**`UpdateSupplierCreditMemoLine`**:
- Accepts line, actor, `description`, `quantity`, `unitPrice`, `taxCode`, `taxAmount`, `lockVersion`.
- Validates parent credit memo is in `draft`.
- Validates line `lockVersion`.
- Updates fields, recomputes `line_subtotal`, recomputes header totals.
- Bumps parent credit memo `lock_version` and line `lock_version`.
- Records `supplier_credit_memo.line_updated` audit event.

**`RemoveSupplierCreditMemoLine`**:
- Accepts line, actor, `lockVersion`.
- Validates parent credit memo is in `draft`.
- Validates line `lockVersion`.
- Deletes the line.
- Recomputes header totals.
- Bumps parent credit memo `lock_version`.
- Records `supplier_credit_memo.line_removed` audit event.

**`SubmitSupplierCreditMemoForApproval`**:
- Accepts credit memo, actor, `lockVersion`.
- Locks credit memo row, validates `lockVersion`, validates status is `draft`.
- Validates no open blocking exceptions.
- Validates at least one line exists.
- Sets `status = pending_approval`, `submitted_by_user_id`, `submitted_at`, bumps `lock_version`.
- Calls `ApprovalSubjectRegistry::route($creditMemo, $actor)` to create the approval instance and tasks per the approval policy version. The `SupplierCreditMemoApprovalSubjectHandler::onRouted` callback (no-op) is called after routing.
- Stores `approval_instance_id` on the credit memo.
- Records `supplier_credit_memo.submitted_for_approval` audit event.
- Returns the credit memo with approval task summary.

**`PostSupplierCreditMemo`** (also called from `onApproved` callback):
- Accepts credit memo, actor, `lockVersion`.
- Locks credit memo row, validates `lockVersion`, validates status is `approved`.
- Sets `status = open`, `posted_by_user_id`, `posted_at = now`, bumps `lock_version`.
- Records `supplier_credit_memo.posted` audit event.
- Returns the credit memo.

**`VoidSupplierCreditMemo`**:
- Accepts credit memo, actor, `voidReason`, `lockVersion`.
- Locks credit memo row, validates `lockVersion`, validates status is in `draft`, `pending_approval`, `approved`, `open`, or `partially_applied` (not `fully_applied`, `closed`, or already `voided`).
- Sets `status = voided`, `voided_by_user_id`, `voided_at = now`, `void_reason`, bumps `lock_version`.
- Voids all non-voided `CreditApplication` rows on this credit memo (`voided_at = now`, `voided_by_user_id`, `void_reason`).
- For each affected invoice: recomputes `payment_status` (revert from `reversed` if applicable), bumps `lock_version`.
- Records `supplier_credit_memo.voided` audit event with the credit memo number, void reason, prior status, applications voided count, invoices affected.
- Records `supplier_invoice.credit_memo_voided` audit event per affected invoice with the credit memo number, prior payment status, new payment status, applications voided.

**`CreateCreditApplication`**:
- Accepts credit memo, invoice, actor, `appliedAmount`, `applicationDate`, `notes`, `lockVersion` (on credit memo).
- Locks credit memo and invoice rows, validates credit memo `lockVersion`, validates credit memo status is `open` or `partially_applied`.
- Validates invoice `vendor_id` matches credit memo `vendor_id` (zero tolerance).
- Validates `appliedAmount > 0` (using `bccomp` against `'0.0000'`, scale 4).
- Validates `SUM(existing non-voided applications for this credit memo) + appliedAmount <= credit_memo.total_amount` (no over-application of credit, using `bcadd`).
- Validates `SUM(existing non-voided applications for this invoice across all credit memos) + appliedAmount <= invoice.outstanding_amount` (no over-application of credit to invoice, using `bcadd`).
- Validates invoice is not in `payment_scheduled` or `handoff_exported` (return 409 with "void handoff first" guidance).
- Validates invoice is not in `on_hold` (return 409 with "release hold first" guidance).
- Computes `outstanding_amount = invoice.total_amount - SUM(non-voided payment_allocations.allocated_amount) - SUM(existing non-voided credit_applications.allocated_amount for this invoice)`.
- Creates `CreditApplication` row with `lock_version = 1`.
- Derives credit memo `status`:
  - If `SUM(non-voided applications) = credit_memo.total_amount` → `fully_applied` (auto-transitions to `closed` below).
  - Else if `> 0` and `< total` → `partially_applied`.
  - Else stays `open`.
- Bumps credit memo `lock_version`.
- Derives invoice `payment_status`:
  - If `SUM(non-voided applications for this invoice) >= invoice.total_amount` → `reversed`.
  - Else if invoice was `paid` → stays `paid` (retroactive credit applied but invoice still effectively paid — the new `credit_applied_amount` is a separate accounting concept, not a payment status change). The credit application row itself is the operational signal.
  - Else if invoice was `partially_paid` → stays `partially_paid` (or remains `partially_paid` for the credit-applied angle).
  - Else (invoice was `payment_eligible`, `payment_ready`, etc.) → stays in pre-state (credit doesn't change payment status for un-fully-offset credits).
- Bumps invoice `lock_version`.
- Records `supplier_credit_memo.applied` audit event with the credit memo number, application id, applied amount, invoice number, invoice new payment status.
- Records `supplier_invoice.credit_applied` audit event on the invoice with the credit memo number, application id, applied amount, prior payment status, new payment status.
- If the application caused the credit memo to reach `fully_applied`, auto-transitions to `closed` in the same transaction and records `supplier_credit_memo.closed` audit event. The `closed` state is terminal.
- If the application caused the invoice to reach `reversed`, records `supplier_invoice.reversed` audit event.

**`VoidCreditApplication`**:
- Accepts credit application, actor, `voidReason`, `lockVersion`.
- Locks credit application row, validates `lockVersion`, validates `voided_at IS NULL`.
- Locks parent credit memo row, validates parent credit memo status is not `voided` (a voided parent already voided all applications).
- Sets `voided_at = now`, `voided_by_user_id`, `void_reason`, bumps application `lock_version`.
- Recomputes credit memo `status`:
  - If `SUM(non-voided applications) = 0` AND prior status was `partially_applied` → return to `open`.
  - If `SUM(non-voided applications) > 0` AND `< total` AND prior was `fully_applied` or `closed` → stays `closed` (terminal — applications voided after close do not reopen the credit memo).
  - If `SUM(non-voided applications) > 0` AND `< total` AND prior was `partially_applied` → stays `partially_applied`.
  - If `SUM(non-voided applications) = total` AND prior was `open` or `partially_applied` → `fully_applied` (auto-transitions to `closed`).
  - Never transitions out of `voided` or `closed` (terminal states).
- Bumps credit memo `lock_version`.
- Recomputes invoice `payment_status`:
  - If invoice was `reversed` and now `SUM(non-voided applications for this invoice) = 0`:
    - If invoice was `paid` before the original credit application (i.e., had `SUM(non-voided payment_allocations) = invoice.total_amount` before any credits): return to `paid`.
    - Else if invoice had partial payment allocations: return to `partially_paid`.
    - Else: return to `payment_eligible` (the most permissive pre-state for an unallocated invoice with no credits).
  - If invoice was `reversed` and now `SUM(non-voided applications) < invoice.total_amount` but `> 0`: transition to `partially_paid` (the credit is still partially applied).
  - Bumps invoice `lock_version`.
- Records `supplier_credit_memo.application_voided` audit event with the application id, void reason, prior amounts, new amounts, prior credit memo status, new credit memo status.
- Records `supplier_invoice.credit_application_voided` audit event on the invoice with the credit memo number, application id, void reason, prior payment status, new payment status.

**`AcknowledgeSupplierCreditMemoException`**:
- Accepts exception, actor, `lockVersion`.
- Validates `lockVersion`, validates exception is not already acknowledged.
- Sets `acknowledged_by_user_id`, `acknowledged_at = now`, bumps `lock_version`.
- Records `supplier_credit_memo_exception.acknowledged` audit event.

**`ResolveSupplierCreditMemoException`**:
- Accepts exception, actor, `resolutionType`, `resolutionNotes`, `lockVersion`.
- Validates `lockVersion`, validates exception is not already resolved.
- Sets `resolution_type`, `resolution_notes`, `resolved_by_user_id`, `resolved_at = now`, bumps `lock_version`.
- Records `supplier_credit_memo_exception.resolved` audit event with the resolution type and notes.

**`EscalateSupplierCreditMemoException`**:
- Accepts exception, actor, `lockVersion`.
- Validates `lockVersion`, validates exception is not already escalated.
- Sets `escalated_by_user_id`, `escalated_at = now`, bumps `lock_version`.
- Records `supplier_credit_memo_exception.escalated` audit event.

### Integration Points

- **`Approval` domain**: New `SupplierCreditMemoApprovalSubjectHandler` registered in `AppServiceProvider`. The handler's `onApproved` callback calls `PostSupplierCreditMemo` to transition the credit memo to `open`. The `onRejected` callback transitions back to `draft` with a reason. The `onChangesRequested` callback transitions back to `draft` with requested changes. The approval context data includes a new `supplierCreditMemoId` field (or reuses `subjectId`).
- **`AuditRecorder`**: New audit event types listed below. Subject types registered in `AuditSubject::$typeMap`.
- **`Attachment` morph**: `SupplierCreditMemo` implements the `attachable_type` / `attachable_id` pattern. The existing `Attachment` model supports arbitrary morph types via the same pattern used by `SupplierInvoice`.
- **`SupplierInvoice.attachments`**: No change.
- **`ApPaymentHandoff`**: No change in P1-49. The interaction with credit applications on `paid` invoices is via the existing `payment_allocations` (kept for audit) and the new `credit_applications` (the netting path). Future P1-50 may add handoff re-aggregation for credits applied to invoices in a handoff; deferred.
- **`P1-50 Budget Commitment`**: The `purchase_order_line_id` on `SupplierCreditMemoLine` is a forward hook. P1-50 will consume this linkage to relieve encumbrance.

### Authorization

**`SupplierCreditMemoPolicy`** (new, in `Domains/CreditMemo/Policies/`):
- `view` — `Buyer` or `Admin` role + tenant scope on credit memo. Approvers can view if they are currently assigned to the credit memo. Requesters can view if their requester ID is on the original invoice (rare; AP-only).
- `create` — `Buyer` or `Admin` role + tenant scope.
- `update` — `Buyer` or `Admin` role + tenant scope + credit memo in `draft` state.
- `submit` — `Buyer` or `Admin` role + tenant scope + credit memo in `draft` state + no open blocking exceptions + at least one line.
- `approve` — `Approver` role + tenant scope + assigned to the current approval task. Approval is a derived ability (the user is the current task owner).
- `reject` — Same as `approve`.
- `requestChanges` — Same as `approve`.
- `post` — `Buyer` or `Admin` role + tenant scope + credit memo in `approved` state. (Usually automatic via onApproved callback.)
- `apply` — `Buyer` or `Admin` role + tenant scope + credit memo in `open` or `partially_applied` state.
- `voidApplication` — `Buyer` or `Admin` role + tenant scope + application in non-voided state.
- `void` — `Buyer` or `Admin` role + tenant scope + credit memo in voidable state.

**`CreditApplicationPolicy`** (new, in `Domains/CreditMemo/Policies/`):
- `view` — `Buyer` or `Admin` role + tenant scope. Approvers can view if assigned.
- `create` — Same gate as `SupplierCreditMemoPolicy::apply`.
- `void` — Same gate as `SupplierCreditMemoPolicy::voidApplication`.

**`SupplierCreditMemoExceptionPolicy`** (new, in `Domains/CreditMemo/Policies/`):
- `view` — `Buyer` or `Admin` role + tenant scope.
- `acknowledge` — `Buyer` or `Admin` role + tenant scope + exception not already acknowledged.
- `resolve` — `Buyer` or `Admin` role + tenant scope + exception not already resolved.
- `escalate` — `Buyer` or `Admin` role + tenant scope + exception not already escalated.

Approver can view only if assigned to the credit memo's approval task. Requester has no access (AP-only domain). Vendor portal visitors have no access.

### Audit Metadata

| Event | Trigger |
|---|---|
| `supplier_credit_memo.created` | Create action |
| `supplier_credit_memo.updated` | Update action (header fields) |
| `supplier_credit_memo.line_added` | Add line action |
| `supplier_credit_memo.line_updated` | Update line action |
| `supplier_credit_memo.line_removed` | Remove line action |
| `supplier_credit_memo.submitted_for_approval` | Submit action |
| `supplier_credit_memo.approved` | Approval callback (onApproved) |
| `supplier_credit_memo.rejected` | Approval callback (onRejected) |
| `supplier_credit_memo.changes_requested` | Approval callback (onChangesRequested) |
| `supplier_credit_memo.posted` | Post action (or auto via onApproved) |
| `supplier_credit_memo.applied` | Credit application created |
| `supplier_credit_memo.application_voided` | Credit application voided |
| `supplier_credit_memo.fully_applied` | Auto when sum of applications = total |
| `supplier_credit_memo.closed` | Auto when fully_applied reached |
| `supplier_credit_memo.voided` | Void action |
| `supplier_credit_memo_exception.created` | Exception auto-detected on create / update / submit |
| `supplier_credit_memo_exception.acknowledged` | Acknowledge action |
| `supplier_credit_memo_exception.resolved` | Resolve action |
| `supplier_credit_memo_exception.escalated` | Escalate action |
| `supplier_invoice.credit_applied` | Credit application created (on the invoice) |
| `supplier_invoice.credit_application_voided` | Credit application voided (on the invoice) |
| `supplier_invoice.reversed` | Invoice fully offset by credit |
| `supplier_invoice.credit_memo_voided` | Credit memo voided (on the affected invoice) |

Audit metadata includes: credit memo id and number, line ids, application id and amount, invoice id and number, original invoice id, vendor id, prior status, new status, fromStatus, toStatus, application date, void reason, resolution type and notes, exception type and severity, tax code, currency, totals (subtotal, tax, freight, total).

Register the four new types in `App\Audit\AuditSubject::$typeMap`:
- `SupplierCreditMemo::class => 'supplier_credit_memo'`
- `SupplierCreditMemoLine::class => 'supplier_credit_memo_line'`
- `CreditApplication::class => 'credit_application'`
- `SupplierCreditMemoException::class => 'supplier_credit_memo_exception'`

### Concurrency

All mutation actions lock the target row (`lockForUpdate()`) and check `lock_version`. Credit application creation locks both the credit memo row and the target invoice row to prevent concurrent applications from over-applying. Credit memo void locks the credit memo and all of its non-voided applications.

Credit memo `lock_version`, credit application `lock_version`, and individual invoice `lock_version` are checked independently. A stale lock on any returns `409`. The `NULLS NOT DISTINCT` modifier on the unique index `(tenant_id, supplier_credit_memo_id, supplier_invoice_id, application_date)` treats `NULL` values as equal (mirroring P1-48's `ApPaymentAllocation` pattern), preventing duplicate application rows.

## API Contract

Add tenant-scoped authenticated routes under the `RequireTenantHeader` middleware:

```txt
# Credit memo CRUD
GET    /api/supplier-credit-memos
POST   /api/supplier-credit-memos
GET    /api/supplier-credit-memos/{creditMemo}
PATCH  /api/supplier-credit-memos/{creditMemo}
POST   /api/supplier-credit-memos/{creditMemo}/submit
POST   /api/supplier-credit-memos/{creditMemo}/post
POST   /api/supplier-credit-memos/{creditMemo}/void

# Credit memo lines
POST   /api/supplier-credit-memos/{creditMemo}/lines
PATCH  /api/supplier-credit-memos/{creditMemo}/lines/{line}
DELETE /api/supplier-credit-memos/{creditMemo}/lines/{line}

# Credit application
GET    /api/supplier-credit-memos/{creditMemo}/applications
POST   /api/supplier-credit-memos/{creditMemo}/applications
GET    /api/credit-applications/{application}
DELETE /api/credit-applications/{application}

# Credit memo exceptions
GET    /api/supplier-credit-memos/{creditMemo}/exceptions
POST   /api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/acknowledge
POST   /api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/resolve
POST   /api/supplier-credit-memos/{creditMemo}/exceptions/{exception}/escalate

# Attachments (reuse existing polymorphic pattern)
GET    /api/supplier-credit-memos/{creditMemo}/attachments
POST   /api/supplier-credit-memos/{creditMemo}/attachments
```

Extend `GET /api/supplier-invoices` with:
- `paymentStatus` filter: add `reversed` to existing `payment_eligible`, `on_hold`, `payment_ready`, `handoff_exported`, `payment_scheduled`, `partially_paid`, `paid`.

Extend `SupplierInvoiceQueueResource` with:
```json
{
  "paymentStatus": "reversed",
  "outstandingAmount": "0.00",
  "creditAppliedAmount": "1000.00",
  "appliedCreditMemos": [
    {
      "id": "uuid",
      "number": "CM-2026-000042",
      "appliedAmount": "1000.00",
      "applicationDate": "2026-06-20",
      "voidedAt": null
    }
  ]
}
```

Extend `SupplierInvoiceResource` (show) with:
```json
{
  "paymentStatus": "reversed",
  "creditApplications": [
    {
      "id": "uuid",
      "supplierCreditMemoId": "uuid",
      "supplierCreditMemoNumber": "CM-2026-000042",
      "appliedAmount": "1000.00",
      "applicationDate": "2026-06-20",
      "appliedByUserId": "uuid",
      "voidedAt": null,
      "voidReason": null,
      "lockVersion": 1
    }
  ]
}
```

`SupplierCreditMemoResource`:
```json
{
  "id": "uuid",
  "tenantId": "uuid",
  "number": "CM-2026-000042",
  "vendorCreditMemoNumber": "VCM-2026-001",
  "vendorId": "uuid",
  "vendorName": "Acme Supplies",
  "originalInvoiceId": "uuid",
  "originalInvoiceNumber": "INV-2026-000042",
  "status": "partially_applied",
  "currency": "USD",
  "subtotalAmount": "1000.00",
  "taxAmount": "80.00",
  "freightAmount": "0.00",
  "totalAmount": "1080.00",
  "appliedAmount": "500.00",
  "remainingAmount": "580.00",
  "creditDate": "2026-06-15",
  "notes": "Product return — pricing dispute",
  "capturedByUserId": "uuid",
  "capturedAt": "2026-06-15T00:00:00Z",
  "submittedByUserId": "uuid",
  "submittedAt": "2026-06-15T10:00:00Z",
  "approvedByUserId": "uuid",
  "approvedAt": "2026-06-15T14:00:00Z",
  "postedByUserId": "uuid",
  "postedAt": "2026-06-15T14:00:00Z",
  "voidedByUserId": null,
  "voidedAt": null,
  "voidReason": null,
  "approvalInstanceId": "uuid",
  "stpEligible": false,
  "stpProcessedAt": null,
  "lockVersion": 4,
  "lines": [
    {
      "id": "uuid",
      "lineNumber": 1,
      "descriptionSnapshot": "Widget A — pricing dispute",
      "quantity": "10.0000",
      "unitPrice": "100.0000",
      "lineSubtotal": "1000.0000",
      "taxCode": "TX_STD",
      "taxAmount": "80.0000",
      "purchaseOrderLineId": "uuid",
      "originalInvoiceLineId": "uuid",
      "notes": null
    }
  ],
  "applications": [
    {
      "id": "uuid",
      "supplierInvoiceId": "uuid",
      "supplierInvoiceNumber": "INV-2026-000042",
      "appliedAmount": "500.00",
      "applicationDate": "2026-06-20",
      "appliedByUserId": "uuid",
      "notes": "First application",
      "voidedAt": null,
      "voidReason": null,
      "lockVersion": 1
    }
  ],
  "exceptions": [
    {
      "id": "uuid",
      "exceptionType": "tax_code_mismatch",
      "severity": "blocking",
      "description": "Line 1 tax code TX_STD does not match original invoice line tax code TX_ZERO",
      "resolutionType": null,
      "resolutionNotes": null,
      "resolvedByUserId": null,
      "resolvedAt": null,
      "acknowledgedByUserId": null,
      "acknowledgedAt": null,
      "escalatedByUserId": null,
      "escalatedAt": null,
      "lockVersion": 1
    }
  ],
  "approvalTask": {
    "id": "uuid",
    "status": "approved",
    "currentApproverUserId": null,
    "approvedByUserId": "uuid",
    "approvedAt": "2026-06-15T14:00:00Z"
  },
  "permissions": {
    "canEdit": false,
    "canSubmit": false,
    "canApprove": false,
    "canReject": false,
    "canPost": false,
    "canApply": true,
    "canVoidApplication": true,
    "canVoidCreditMemo": true,
    "canResolveException": true
  }
}
```

Expected operation IDs:
- `listSupplierCreditMemos`
- `createSupplierCreditMemo`
- `showSupplierCreditMemo`
- `updateSupplierCreditMemo`
- `submitSupplierCreditMemoForApproval`
- `postSupplierCreditMemo`
- `voidSupplierCreditMemo`
- `addSupplierCreditMemoLine`
- `updateSupplierCreditMemoLine`
- `removeSupplierCreditMemoLine`
- `listCreditApplications`
- `createCreditApplication`
- `showCreditApplication`
- `voidCreditApplication`
- `listSupplierCreditMemoExceptions`
- `acknowledgeSupplierCreditMemoException`
- `resolveSupplierCreditMemoException`
- `escalateSupplierCreditMemoException`
- `listSupplierCreditMemoAttachments`
- `uploadSupplierCreditMemoAttachment`

Expected schemas:
- `CreateSupplierCreditMemoRequest`
- `UpdateSupplierCreditMemoRequest`
- `SubmitSupplierCreditMemoForApprovalRequest`
- `PostSupplierCreditMemoRequest`
- `VoidSupplierCreditMemoRequest`
- `AddSupplierCreditMemoLineRequest`
- `UpdateSupplierCreditMemoLineRequest`
- `CreateCreditApplicationRequest`
- `VoidCreditApplicationRequest`
- `AcknowledgeSupplierCreditMemoExceptionRequest`
- `ResolveSupplierCreditMemoExceptionRequest`
- `EscalateSupplierCreditMemoExceptionRequest`
- `SupplierCreditMemoResponse`
- `SupplierCreditMemoLineResponse`
- `CreditApplicationResponse`
- `SupplierCreditMemoExceptionResponse`
- `SupplierCreditMemoStatus` (enum: draft, pending_approval, approved, open, partially_applied, fully_applied, closed, voided)
- `SupplierCreditMemoExceptionType` (enum: missing_invoice_reference, over_credit, vendor_mismatch, tax_code_mismatch, math_error, duplicate_credit, missing_tax_code, currency_mismatch)
- `SupplierCreditMemoExceptionSeverity` (enum: blocking, warning, info)
- `SupplierCreditMemoExceptionResolutionType` (enum: accepted, value_adjustment, vendor_reassignment, voided, info_only)
- `SupplierInvoicePaymentStatus` (extended enum: existing values + `reversed`)

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas in the web feature. Do not duplicate contract response types in app code.

## Web Design

### Pages

- `/accounts-payable/credit-memos` — primary P1-49 page. Credit memo queue with status tabs (All / Draft / Pending Approval / Open / Partially Applied / Fully Applied / Closed / Voided). Credit memo list with status badges, vendor, original invoice, total, application progress (applied / total). Right panel: credit memo detail with header, lines, application panel, exception panel, approval panel, activity timeline, attachments.
- `/accounts-payable/credit-memos/new` — credit memo creation form (vendor, original invoice, lines, math preview).
- `/accounts-payable/credit-memos/[id]` — credit memo detail workspace.

Navigation: add "Credit memos" sub-item under the existing Finance nav group, after "Payment status". Gated by `canUseAccountsPayable` permission (same as existing Finance items).

### Components

- `credit-memo-queue-page.tsx` — workflow component for the credit memo queue page.
- `credit-memo-status-badge.tsx` — status badge for all credit memo states.
- `credit-memo-create-panel.tsx` — creation form with vendor, original invoice, lines.
- `credit-memo-detail-workspace.tsx` — main detail page.
- `credit-memo-line-editor.tsx` — line editor with math preview.
- `credit-memo-application-panel.tsx` — application list and create form.
- `credit-memo-exception-panel.tsx` — exception list with acknowledge/resolve/escalate actions.
- `credit-memo-approval-panel.tsx` — approval task summary.
- `credit-memo-attachment-panel.tsx` — attachment upload and list.
- `credit-memo-activity-timeline.tsx` — audit event timeline.
- `credit-memo-void-panel.tsx` — void form with reason.

### Hooks

- `use-supplier-credit-memos.ts` — `useSupplierCreditMemos(filters)`, `useCreateSupplierCreditMemo`, `useUpdateSupplierCreditMemo`, `useSubmitSupplierCreditMemoForApproval`, `usePostSupplierCreditMemo`, `useVoidSupplierCreditMemo`.
- `use-supplier-credit-memo.ts` — `useSupplierCreditMemo(id)` for detail page.
- `use-supplier-credit-memo-lines.ts` — `useAddSupplierCreditMemoLine`, `useUpdateSupplierCreditMemoLine`, `useRemoveSupplierCreditMemoLine`.
- `use-credit-applications.ts` — `useCreditApplications(creditMemoId)`, `useCreateCreditApplication`, `useVoidCreditApplication`.
- `use-supplier-credit-memo-exceptions.ts` — `useSupplierCreditMemoExceptions(creditMemoId)`, `useAcknowledgeSupplierCreditMemoException`, `useResolveSupplierCreditMemoException`, `useEscalateSupplierCreditMemoException`.

Cache invalidation: credit memo mutations invalidate both `supplierCreditMemoKeys.all` AND `accountsPayableInvoiceKeys.all` (mirrors P1-48 `useInvalidatePaymentCaches` for cross-aggregate consistency). Credit application mutations invalidate `supplierCreditMemoKeys.all` (for the credit memo's applications) AND `accountsPayableInvoiceKeys.all` (for the affected invoice's payment status) AND the specific invoice key.

### API Helpers

- `accounts-payable-credit-memo-api.ts` — thin wrappers over generated client for credit memo CRUD and state transitions.
- `accounts-payable-credit-application-api.ts` — thin wrappers for credit application CRUD.
- `accounts-payable-credit-memo-exception-api.ts` — thin wrappers for exception management.

### MSW Mocks

- `accounts-payable-credit-memo-handlers.ts` + `accounts-payable-credit-memo-fixtures.ts` — mock credit memo CRUD, state transitions, and number generation.
- `accounts-payable-credit-application-handlers.ts` + `accounts-payable-credit-application-fixtures.ts` — mock credit application CRUD with derived invoice `payment_status` transitions (including `reversed`).
- `accounts-payable-credit-memo-exception-handlers.ts` + `accounts-payable-credit-memo-exception-fixtures.ts` — mock exception management with auto-detection on create/update/submit.

Pattern: module-scoped mutable state, exported `reset*MockState()` and `set*MockState()` for tests, `{ data: ... }` envelope, `409` on `lockVersion` mismatch, derived `outstandingAmount` and `paymentStatus` recalculated on each mutation, `reversed` state visible in the mock invoice queue filter.

## Seed and Demo Data

Extend `DemoProcurementLifecycleSeeder` with credit memo scenarios:

| Credit Memo | Status | Original Invoice | Description |
|---|---|---|---|
| CM-DEMO-001 | draft | INV-2026-DEMO-031 | Draft credit memo linked to a paid invoice, 1 line with PO line linkage, math validated, no exceptions |
| CM-DEMO-002 | pending_approval | INV-2026-DEMO-032 | Pending approval credit memo for $500 product return, 1 line, exception: `tax_code_mismatch` (warning, not blocking) |
| CM-DEMO-003 | open | INV-2026-DEMO-033 | Open (posted, unapplied) credit memo for $300, 2 lines, no applications yet |
| CM-DEMO-004 | partially_applied | INV-2026-DEMO-034 | Partially applied credit memo for $1000 — $600 applied to INV-2026-DEMO-034, $400 remaining |
| CM-DEMO-005 | fully_applied / closed | INV-2026-DEMO-035 | Fully applied / Closed credit memo for $200, applied to INV-2026-DEMO-035 |
| CM-DEMO-006 | voided | INV-2026-DEMO-036 | Voided credit memo for $400 (voided before application, with reason) |
| CM-DEMO-007 | partially_applied | INV-2026-DEMO-037 | Credit memo applied to a `partially_paid` invoice (netting scenario) — credit $200 applied to a $500 partially paid invoice (with $200 paid via allocation), invoice stays `partially_paid` with reduced outstanding |
| CM-DEMO-008 | closed | INV-2026-DEMO-038 | Credit memo that fully offsets an invoice → invoice `reversed` scenario — credit $1000 applied to a $1000 paid invoice (retroactive netting), invoice transitions to `reversed` |

Each scenario creates realistic `SupplierCreditMemoLine` rows, `CreditApplication` rows, derived `SupplierInvoice.payment_status` values (including `reversed` for CM-DEMO-008), audit events, and where appropriate `SupplierCreditMemoException` rows. The seeded data references real seeded invoices, vendors, and PO lines.

Add a `seedCreditMemos` method to `DemoProcurementLifecycleSeeder` following the existing pattern (idempotent `updateOrCreate`, audit events, `DemoSeedContext` extension if needed). The `credit_memo_number_sequences` row is incremented atomically per tenant during seeding.

## Testing and Verification

### API Tests

Add focused tests for:

**Credit memo CRUD:**
- credit memo can be created with required fields
- credit memo requires vendor_id
- credit memo requires original_invoice_id (or warning if on-account credit is allowed by tenant policy)
- credit memo auto-generates `CM-YYYY-NNNNNN` number
- credit memo auto-populates currency from original invoice
- credit memo validates vendor match (zero tolerance — vendor mismatch returns 422)
- credit memo validates tax code mirroring (returns 422 on mismatch)
- credit memo validates math (returns 422 on line subtotal sum mismatch)
- credit memo detects duplicate (same vendor + original invoice + vendor_credit_memo_number)
- credit memo in `draft` can be updated
- credit memo in non-`draft` cannot be updated
- cross-tenant credit memo CRUD denied
- stale lockVersion returns conflict

**Lines:**
- line can be added to `draft` credit memo
- line quantity * unit_price computes line_subtotal
- line with PO line linkage validates tenant scope
- line with original invoice line linkage validates tenant scope and same original invoice
- line can be updated in `draft` credit memo
- line can be removed from `draft` credit memo
- adding line recomputes header totals
- cross-tenant line CRUD denied

**Submit and approval:**
- credit memo can be submitted when no open blocking exceptions
- credit memo cannot be submitted when open blocking exceptions exist
- credit memo cannot be submitted when no lines
- credit memo cannot be submitted when not in `draft`
- submit routes approval task
- approve callback posts credit memo (transitions to `open`)
- reject callback returns credit memo to `draft` with reason
- changes-requested callback returns credit memo to `draft` with requested changes
- approval routing uses amount-based tier

**Post:**
- credit memo can be posted from `approved` (usually automatic via onApproved)
- credit memo cannot be posted from non-`approved` state
- post records `supplier_credit_memo.posted` audit event

**Apply credit:**
- credit application can be created on `open` or `partially_applied` credit memo
- credit application with non-positive amount returns 422
- credit application that would over-apply credit memo returns 422
- credit application that would over-apply invoice returns 422
- credit application to non-member vendor invoice returns 422 (vendor mismatch)
- credit application to `payment_scheduled` invoice returns 409
- credit application to `handoff_exported` invoice returns 409
- credit application to `on_hold` invoice returns 409
- first partial application moves credit memo to `partially_applied`
- application reaching full credit memo total moves credit memo to `fully_applied` and auto-`closed`
- application that fully offsets invoice moves invoice to `reversed`
- application to `paid` invoice (retroactive netting) keeps invoice `paid` if not fully offset
- application to `paid` invoice that fully offsets it moves invoice to `reversed` (with existing payment allocations kept)
- cross-tenant credit application CRUD denied
- stale lockVersion returns conflict

**Void application:**
- credit application can be voided with reason
- voiding returns credit memo to `open` if all applications voided
- voiding keeps credit memo at `partially_applied` if some applications remain
- voiding a `closed` credit memo's application does not reopen the credit memo
- voiding reverts invoice from `reversed` to prior state
- voiding an already-voided application returns 409

**Void credit memo:**
- credit memo can be voided from `draft`, `pending_approval`, `approved`, `open`, `partially_applied`
- credit memo cannot be voided from `fully_applied`, `closed`, or `voided`
- voiding voids all non-voided applications
- voiding reverts all affected invoice `payment_status` values

**Exceptions:**
- math error auto-creates blocking exception on submit
- duplicate credit auto-creates warning exception on create
- tax code mismatch auto-creates blocking exception on create
- exception can be acknowledged
- exception can be resolved with resolution type and notes
- exception can be escalated
- resolved exception does not block submit
- cross-tenant exception CRUD denied

**Invoice `reversed` state:**
- invoice reaches `reversed` when credit applications sum to total
- `reversed` is terminal — no further transitions in P1-49
- `reversed` invoice is distinct from `paid` invoice
- voiding the credit application that caused reversal reverts the invoice to prior state

**Real route stack:**
- credit memo actions succeed before logout
- credit memo actions return 401 after logout (real Sanctum/session stack)

### Web Tests

Add focused tests for:

**Credit memo queue page:**
- Status tabs (All, Draft, Pending Approval, Open, Partially Applied, Fully Applied, Closed, Voided) filter correctly
- Status badge renders for each state
- Header count includes credit memo counts
- Draft credit memo shows edit and submit buttons
- Pending approval credit memo shows approval panel
- Open credit memo shows apply button
- Partially applied credit memo shows application panel and remaining amount
- Fully applied / closed credit memo shows no actions
- Voided credit memo shows void reason and no actions

**Credit memo detail:**
- Header shows number, vendor, original invoice, currency, totals
- Lines array shows per-line description, quantity, unit price, subtotal, tax code, PO line reference
- Math preview shows lines sum and total
- Application panel shows per-invoice applied amount, date, void status
- Exception panel shows per-exception type, severity, description, resolution actions
- Approval panel shows current task and approver
- Attachment panel shows uploaded files
- Activity timeline shows audit events

**Application panel:**
- Apply form requires amount > 0 and application date
- Over-credit error is visible (current applied, remaining, attempted)
- Over-invoice error is visible
- Vendor mismatch error is visible
- Invoice state error (e.g., `on_hold`) is visible
- Application added updates credit memo and invoice without page navigation
- Void application form requires reason
- Voided application reverts to non-voided state with reason

**Exception panel:**
- Exception list shows per-exception type and severity
- Acknowledge button moves exception to acknowledged state
- Resolve form requires resolution type and notes
- Escalate button moves exception to escalated state
- Resolved exceptions are visible but not blocking

### Verification Commands

Expected implementation verification:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=SupplierCreditMemo
cd apps/api && php artisan test --filter=CreditApplication
cd apps/api && php artisan test --filter=SupplierCreditMemoException
cd apps/api && php artisan test --filter=SupplierInvoice              # regression - P1-12 invoice lifecycle
cd apps/api && php artisan test --filter=ApPaymentHandoff             # regression - P1-47/P1-48 handoff lifecycle
cd apps/api && php artisan test --filter=SupplierInvoicePayment      # regression - P1-47/P1-48 payment status
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
pnpm lint
```

## Non-Goals Reiteration

This slice explicitly does NOT include:
- Debit notes (buyer-issued claims — different actor, different workflow)
- Full invoice reversals (complete nullification of an invoice — edge case, can be modeled as credit = invoice total)
- Refund / cash recovery path (vendor sends money back — requires negative handoff complexity)
- Multi-currency realized gain/loss on credit application
- Vendor portal credit memo submission (suppliers creating credits in the portal)
- OCR / email intake automation for credit memos
- Credit memo expiration / write-off workflow (expired credits, immaterial balances)
- Cross-subsidiary / cross-tenant credit application
- Credit memo matching to PO without invoice reference (on-account credits without original invoice) — supported as exception path, not primary
- Subsequent debit / subsequent credit (SAP S/4HANA's additional transaction types)
- Credit memo request workflow (pre-approval request document before credit memo creation)
- Automated credit application during payment runs (Oracle Fusion "maximize credits" pattern)
- Cash discount handling on credit memos (Microsoft Dynamics pattern)
- Remittance advice for credit applications
- 3-way match on credit memos (lightweight validation only)

## Completion Definition

This slice is complete when:
- AP users can create supplier credit memos in `draft` status with auto-generated `CM-YYYY-NNNNNN` numbers, including lines with optional PO line and original invoice line references.
- Credit memo creation validates vendor match (zero tolerance), tax code mirroring, math (line subtotals sum to header), and flags duplicates.
- AP users can submit credit memos for approval through the shared `Approval` domain with amount-based tiering, mirroring the supplier invoice approval pattern.
- Approval callbacks (`onApproved`, `onRejected`, `onChangesRequested`) post, return-to-draft, or return-to-draft-with-changes the credit memo as appropriate.
- Approved credit memos auto-transition to `open` (posted) via the `onApproved` callback.
- `open` credit memos can be applied to one or more invoices via `CreditApplication` records, supporting partial application with remaining credit tracking.
- `CreditApplication` reuses the `ApPaymentAllocation` pattern (`applied_amount`, `application_date`, `applied_by_user_id`, `voided_at`, `void_reason`, `lock_version`) for operational consistency.
- Invoices fully offset by credit applications transition to a new `reversed` payment status value (the value mandated by P1-48's deferral).
- `reversed` is terminal and distinct from `paid` — the operational queue and reporting can distinguish credit-reversed invoices from cash-paid invoices.
- `CreditApplication` records can be voided with a reason, with automatic reversion of the credit memo status and the affected invoice's payment status (including reverting from `reversed` to prior state).
- `SupplierCreditMemo` can be voided from `draft`, `pending_approval`, `approved`, `open`, or `partially_applied` (not from `fully_applied` or `closed` or already `voided`).
- `SupplierCreditMemo` auto-transitions to `closed` when the sum of non-voided applications equals the total.
- Credit memo exceptions (`missing_invoice_reference`, `over_credit`, `vendor_mismatch`, `tax_code_mismatch`, `math_error`, `duplicate_credit`, `missing_tax_code`, `currency_mismatch`) are auto-detected and resolved through the existing exception panel pattern.
- Tax codes on credit memo lines mirror the original invoice line tax codes (with `tax_amount` as the negative of the original).
- Credit memos use the same currency as the original invoice (cross-currency is P1-52 scope, not in P1-49).
- `SupplierCreditMemoLine.purchase_order_line_id` is stored as a forward hook for P1-50 (Budget Commitment).
- The credit memo queue is surfaced as a dedicated page at `/accounts-payable/credit-memos` under the Finance sidebar group, separate from the payment status queue and the supplier invoice queue.
- All states, transitions, applications, and exceptions are tenant-scoped, authorized, audited, and protected by lock-version concurrency.
- OpenAPI endpoints are generated and consumed by `@cognify/api-client`.
- Seeded demo data covers draft (CM-DEMO-001), pending approval (002), open (003), partially applied (004), fully applied / closed (005), voided (006), netting against partially_paid invoice (007), and reversed invoice (008) credit memos.
- Downstream P1-50 (Budget Commitment) has a clear `purchase_order_line_id` linkage on `SupplierCreditMemoLine` to consume for encumbrance relief.
- Downstream P1-51 (Vendor Master) has clear credit memo aggregates to consume for outstanding balance.
- Downstream P1-52 (Tax/Currency) has clear tax mirroring and currency fields to consume for multi-currency credit memos and tax reporting.
- Downstream P1-53 (Record Graph) has clear audit events and credit memo / application entities to consume for the procure-to-pay trace.
- Downstream P1-54 (Operational Queues) has clear status tabs and exception queue to consume for the unapplied credits aging report and credit memo exception queue.

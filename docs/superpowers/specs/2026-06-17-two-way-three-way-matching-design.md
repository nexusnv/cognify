# Two-Way and Three-Way Matching Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-17
- Release scope: P1 core procure-to-pay lifecycle, slice P1-44 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-44`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`

## Roadmap Analysis

P1-44 asks Cognify to match invoice lines against purchase order lines and receipt lines. The roadmap explicitly includes price, quantity, tax, freight, and receipt mismatches with configurable tolerances and clear exception reasons.

The codebase already has durable PurchaseOrder records with line-level detail (`quantity`, `unit_price`, `subtotal_amount`, `tax_amount`, `freight_amount`, `total_amount`, `cumulative_quantity_received`, `cumulative_quantity_accepted`), SupplierInvoice records with line-level capture (`quantity_invoiced`, `unit_price`, `line_subtotal`), and GoodsReceipt records with line-level receipt quantities. That gives this slice all the source data it needs to compute two-way (invoice vs PO) and three-way (invoice vs PO vs receipt) matches.

This slice extends the existing `Domains/Invoice` boundary with a matching service, durable match result records, configurable tolerances, and UI surfaces in both the invoice review panel and the AP queue. The next roadmap item, P1-45 (Invoice Exception Workflow), routes mismatched invoices to the right owner for resolution.

## Problem

After invoice review, Cognify cannot determine whether supplier invoices match the purchase order commitments and goods receipts. Without matching:

- AP users cannot see whether an invoice's quantities and prices match what was ordered and received.
- P1-45 exception routing has no match results to route.
- Invoice approval (P1-46) has no pass/fail signal to base approval decisions on.
- AP users must manually compare invoice, PO, and receipt data outside Cognify.
- Matching status and mismatch reasons are not captured as durable, auditable workflow facts.

## Goals

- Match invoice lines against purchase order lines (two-way: quantity, unit price, line total, tax, freight, vendor identity).
- Match invoice lines against goods receipt quantities (three-way: quantity received vs invoiced).
- Match invoice header total against PO header total for an additional header-level control.
- Support configurable matching policy per purchase order (two-way only for services, three-way for goods), defaulting to three-way for POs with receipts and two-way for POs without.
- Store durable per-line, per-dimension match results with pass/fail status, expected and actual values, and applied tolerances.
- Support both automatic matching (triggered when invoice reaches `reviewed` status) and on-demand manual re-triggering from the invoice workspace.
- Expose configurable per-tenant matching tolerances with percentage, min absolute floor, and max absolute cap (three-threshold model).
- Surface match results in the invoice review panel and AP queue with clear mismatch indicators.
- Record audit events for matching completion and mismatch detection.
- Expose OpenAPI endpoints and consume them from `@cognify/api-client`.
- Provide seeded demo data with both matched and mismatched invoice states.

## Non-Goals

- Invoice exception owner routing, resolution notes, or approval impact (P1-45 scope).
- Invoice approval through the shared Approval domain (P1-46 scope).
- Payment readiness, AP export, payment status, or credit memo handling.
- OCR extraction, document parsing, or automated invoice normalization.
- Automated tolerance adjustment or learning.
- Multi-currency and formal tax rule standardization beyond comparing stored values.
- Supplier portal invoice submission changes.

## Approaches Considered

### 1. Full batch match with durable results (selected)

Create a matching service that compares invoice lines against PO and receipt lines, stores per-dimension result records, and sets `matching_status` on the invoice. Auto-triggers on `reviewed` status; manual re-trigger available. Selected because P1-45 exception routing needs durable results to route, and AP users need visible match history and exception explanations.

### 2. Lightweight inline computation

Match is computed on-the-fly by comparing invoice/PO/receipt query values. No stored results and no match history. Simpler to implement initially, but leaves P1-45 with no durable exception records to route, no audit trail of when matching was last run, and no re-trigger semantics. Rejected because it creates technical debt for the very next roadmap slice.

## Workflow

### Actors

- AP user: views match results, re-triggers matching when needed.
- Buyer: views match results in the invoice workspace.
- Admin: views match results, can re-trigger matching.
- System: runs matching automatically on invoice review completion.

### Triggering

Matching runs automatically when a supplier invoice transitions to `reviewed` status. AP users can also manually trigger matching from the invoice workspace when:
- The invoice is in `reviewed` status.
- No matching has been attempted yet (`matching_status` is null or `pending`).
- Or a previous match completed but the user has corrected data and wants to re-run.

The manual trigger uses the same matching service as the automatic trigger so behavior is identical regardless of entry point.

### Main Flow

1. Invoice reaches `reviewed` status (or user clicks "Run matching").
2. System loads the invoice, its lines, the purchase order lines, cumulative goods receipt lines, and the PO line's cumulative invoiced quantity.
3. **Header-level matching** runs first: vendor identity (invoice vendor matches PO vendor) and invoice total vs PO total.
4. **Line-level matching** runs for each invoice line:
   - Two-way: compare invoiced quantity vs PO quantity (accounting for cumulative invoiced), unit price vs PO unit price, line subtotal vs PO line subtotal, tax vs PO line tax, freight vs PO line freight.
   - Three-way (only when PO policy is `three_way`): compare invoiced quantity vs cumulative accepted receipt quantity.
5. Each dimension comparison applies the tenant-level tolerance configuration.
6. Per-dimension match result records are created with pass/fail/not-applicable.
7. Invoice `matching_status` is set to `matched` (all dimensions on all lines pass) or `mismatch` (one or more dimensions fail).
8. **Cumulative invoiced quantity** on the PO line is updated transactionally with the match results.
9. Audit event `supplier_invoice.matching_completed` is recorded with match summary.
10. Results surface in the AP queue and invoice detail panel.

### Event-Driven Re-Trigger on Goods Receipt

In global supply chains, invoices often arrive before goods physically reach the warehouse. A three-way match would immediately produce a `mismatch` for quantity (invoiced qty > received qty, which is zero). Forcing AP staff to manually re-run matching on hundreds of invoices as goods arrive is an operational bottleneck.

To handle this, when a goods receipt is recorded:

1. `Domains/Receiving` dispatches a `GoodsReceiptLinePosted` domain event after recording receipt quantities.
2. A listener in `Domains/Invoice` identifies all supplier invoices for that purchase order that have `matching_status` of `pending` or `mismatch`.
3. The listener silently re-runs matching for those invoices using the same `RunInvoiceMatching` action.
4. If an invoice transitions from `mismatch` to `matched`, the AP queue updates automatically.

This removes the need for AP staff to manually re-trigger matching on receipt arrival. The matching service remains idempotent: re-running replaces prior results.

### State Model

Extend `SupplierInvoiceStatus` matching concerns:

| Invoice review status | Matching status | Meaning |
|---|---|---|
| `reviewed` | `pending` | Reviewed, matching not yet attempted |
| `reviewed` | `matched` | Reviewed, matching passed |
| `reviewed` | `mismatch` | Reviewed, one or more dimensions failed |
| `needs_information` | any | Returned to AP, matching not actionable |

Matching can only run when invoice is `reviewed`. If an invoice is moved back to `needs_information` after matching has run, matching status is preserved but the invoice is no longer eligible for matching re-run until it returns to `reviewed`.

Allowed matching transitions:

| From | Action | To |
|---|---|---|
| `pending` | matching auto/manual | `matched` or `mismatch` |
| `mismatch` | matching re-run | `matched` or `mismatch` |
| `matched` | matching re-run | `matched` or `mismatch` |

## Matching Policy: Two-Way vs Three-Way

In practice, not every purchase needs three-way matching. The industry norm is:

- **Two-way matching** (invoice vs PO only) for services, subscriptions, recurring charges, consulting fees, and low-risk recurring spend where no physical delivery occurs or receipt is not a meaningful control point.
- **Three-way matching** (invoice vs PO vs receipt) for physical goods, inventory items, capital purchases, new vendors, and high-value orders where verifying receipt is essential.

The matching policy is determined per purchase order at PO creation time. POs sourcing services use two-way. POs sourcing physical goods use three-way. The PO model already has a category field that can drive this default. An explicit `matching_policy` enum (`two_way`, `three_way`) on the PurchaseOrder table makes the policy testable and auditable.

When an invoice is captured against a PO, the matching service reads the PO's matching policy to determine which match type to apply.

## Matching Dimensions

### Two-Way (Invoice vs Purchase Order)

Two-way matching confirms that what was ordered matches what was billed. It applies to all POs regardless of policy.

| Dimension | Level | Invoice field | PO field | Comparison |
|---|---|---|---|---|---|
| Vendor identity | Header | `vendor_id` | `vendor_id` | Invoice vendor matches PO vendor (identity check, no tolerance) |
| Quantity | Line | `quantity_invoiced` | `quantity` | Invoiced qty ≤ PO qty (within over-receipt tolerance) |
| Unit price | Line | `unit_price` | `unit_price` | Within unit price tolerance (pct + floor + cap) |
| Line total | Line | `line_subtotal` | `subtotal_amount` | Within line total tolerance (pct + floor + cap) |
| Tax | Line/Header | `tax_amount` | `tax_amount` | Within tax tolerance (pct + floor + cap) |
| Freight | Line/Header | `freight_amount` | `freight_amount` | Within freight tolerance (pct + floor + cap) |
| Invoice total | Header | `total_amount` | `total_amount` | Within invoice total tolerance (pct + floor + cap) |

**Vendor identity** is a header-level precondition. If the invoice vendor does not match the PO vendor, the match result for that dimension is `fail` regardless of other dimension results. This catches the common fraud pattern where an invoice references a valid PO number but comes from a different supplier.

**Invoice total matching** is a header-level control that catches aggregate discrepancies not visible at the line level (e.g., missing line-level charges, unauthorized rounding, entirely extra lines). It compares the invoice's `total_amount` against the PO's `total_amount`.

**Freight and tax matching** can occur at either the line level or the header level depending on how the supplier structures the invoice. Many suppliers apply a single global freight charge and total sales tax at the invoice footer rather than breaking them down per line. When an invoice captures freight or tax as a header-level amount (not tied to a specific line), the matching service matches it against the PO's aggregate freight/tax at the header level using `match_level: header` and a null `supplier_invoice_line_id`. When the invoice captures them per line, matching occurs at the line level as usual.

### Three-Way (Invoice vs Receipt)

Three-way matching adds receipt verification on top of two-way. It only applies when the PO's matching policy is `three_way`.

| Dimension | Level | Invoice field | Receipt field | Comparison |
|---|---|---|---|---|
| Quantity | Line | `quantity_invoiced` | `cumulative_quantity_accepted` | Invoiced qty ≤ accepted receipt qty (within over-receipt tolerance) |

Three-way matching only applies to the quantity dimension because price, tax, freight, and line total are PO-level commitments, not receipt-level. The three-way check validates that the supplier did not invoice for more goods than were actually received and accepted. This prevents paying for items that were never delivered.

### Per-Line Matching Semantics

Each invoice line maps to one PO line (via `purchase_order_line_id`). The matching service iterates invoice lines, matches each against its PO line, and for three-way POs, against cumulative receipt data on the PO line.

If a PO line has been partially cancelled by a change order, the effective PO quantity for matching is the ordered quantity minus the cancelled quantity.

### Cumulative Over-Billing Protection

A critical gap in single-invoice matching is cumulative over-billing. Two invoices can each pass the quantity check independently while jointly exceeding the PO quantity:

- PO line qty = 100
- Invoice A: qty 60 → 60 ≤ 100 → pass
- Invoice B: qty 50 → 50 ≤ 100 → pass
- **Cumulative: 110 > 100 → over-billed**

To prevent this, the matching service tracks `cumulative_quantity_invoiced` on the PO line and checks:

```
cumulative_quantity_invoiced + current_invoice_line_quantity <= effective_po_quantity
```

`cumulative_quantity_invoiced` is updated transactionally as part of the matching action. When matching is re-run (e.g., after an invoice is corrected), the cumulative counter is recalculated from all matched invoices for that PO line, excluding the current invoice.

This protects against the over-billing loophole while allowing legitimate partial billings across multiple invoices.

### Match Result Aggregation

- Two-way PO: overall match = `matched` if all two-way dimensions pass.
- Three-way PO: overall match = `matched` if all two-way dimensions AND the three-way quantity dimension pass.
- Any dimension failing on any line = overall `mismatch`.

`not_applicable` dimensions (e.g., header-level total for a line result, or three-way quantity when PO policy is two-way) do not affect the overall result.

## Configurable Tolerances

Tolerances are stored per-tenant and can be configured by tenant admins. Each dimension uses a **three-threshold model** that addresses both extremes:

- **Percentage** — the base tolerance as a fraction of the expected value.
- **Min absolute floor** — the lowest variance that will ever be flagged, regardless of percentage. Prevents low-value items from failing matching over pennies.
- **Max absolute cap** — the largest variance that will ever auto-pass, regardless of percentage. Prevents high-value items from auto-approving large dollar variances.

A dimension passes only when the variance is within ALL applicable thresholds:

```
percentage_tolerance = expected * tolerance_percent / 100
effective_tolerance = max(percentage_tolerance, floor_absolute)  // floor for low-value
pass if variance <= effective_tolerance                           // within floored tolerance
AND    variance <= cap_absolute                                   // within hard cap
```

For low-value items: percentage_tolerance would be tiny (e.g., $0.10 on a $2 item), but the floor lifts it to a meaningful amount (e.g., $2.00). A $0.15 rounding error would pass.

For high-value items: percentage_tolerance would be large (e.g., $25,000 on a $500k item), but the cap limits it to a controlled amount (e.g., $250). A $25,000 variance would be flagged.

Defaults:

| Tolerance | Key | Default % | Min floor | Max cap |
|---|---|---|---|---|
| Unit price variance | `unit_price_tolerance` | 5% | $2.00 | $250.00 |
| Line total variance | `line_total_tolerance` | 5% | $10.00 | $500.00 |
| Tax variance | `tax_tolerance` | 2% | $5.00 | $100.00 |
| Freight variance | `freight_tolerance` | 5% | $5.00 | $100.00 |
| Invoice total variance | `invoice_total_tolerance` | 2% | $25.00 | $1,000.00 |
| Over-receipt quantity | `quantity_over_tolerance` | 0% | 0 units | 0 units |

For zero expected values: if both invoiced and expected are zero, pass. If expected is zero and invoiced is non-zero, fail.

**Tolerance configuration granularity:** This slice stores tolerances at the tenant level only. Per-vendor, per-category, and per-line tolerance overrides are deferred as future scope. The `MatchingToleranceConfigData` object is structured to support override keys (e.g., `vendor_id`, `category`) when that scope is added.

## Backend Design

### Domain Ownership

The owning backend domain remains `apps/api/Domains/Invoice`.

Supporting domains:
- `Domains/PurchaseOrder`: source of PO lines with quantity, price, and cumulative receipt data.
- `Domains/Receiving`: source of cumulative accepted receipt quantities.
- `app/Tenancy`: tenant resolution and membership enforcement.
- `app/Audit`: audit recording.

### Data Model

New table `supplier_invoice_match_results`:

```
id                           UUID PK
tenant_id                    FK to tenants
supplier_invoice_id          FK to supplier_invoices
supplier_invoice_line_id     FK to supplier_invoice_lines (nullable for header-level)
purchase_order_line_id       FK to purchase_order_lines (nullable for header-level)
match_type                   ENUM: two_way, three_way
match_level                  ENUM: header, line
dimension                    ENUM: vendor_identity, quantity, unit_price, line_total, tax, freight, invoice_total
expected_value               DECIMAL(18,4) — the PO or receipt value
actual_value                 DECIMAL(18,4) — the invoice value
tolerance_percent_applied    DECIMAL(6,4) — the % tolerance used, or null for identity checks
tolerance_floor_applied      DECIMAL(18,4) — the min absolute floor applied, or null
tolerance_cap_applied        DECIMAL(18,4) — the max absolute cap applied, or null
result                       ENUM: pass, fail, not_applicable
notes                        TEXT nullable
created_at                   TIMESTAMP
```

Note: `goods_receipt_line_id` is intentionally absent. Three-way matching compares against the PO line's cumulative accepted receipt quantity, which may span multiple receipt lines. The relationship from invoice line to receipt lines is inherently 1-to-many, so a single FK would be misleading. The cumulative data lives on the PO line.

New field on `supplier_invoices`:

```
matching_status              VARCHAR nullable — pending, matched, mismatch
```

New field on `purchase_orders`:

```
matching_policy              VARCHAR not null default 'three_way' — two_way, three_way
```

New field on `purchase_order_lines`:

```
cumulative_quantity_invoiced  DECIMAL(18,4) not null default 0.0000
```

This field tracks the total invoiced quantity across all matched invoices for this PO line. It is updated transactionally during the matching action and enables cumulative over-billing protection.

The `matching_policy` field determines whether three-way matching (including receipt verification) applies. POs created for services set this to `two_way`; POs created for physical goods default to `three_way`. The PO creation flow already captures a category field that can drive the default.

No new table for tolerance configuration in this slice. Store tolerances as a JSON column on the `tenants` table or a dedicated `tenant_settings` table pending the broader tenant settings infrastructure. If neither exists, start with an in-memory default config in `ToleranceService` and add persistence in the hardening phase.

### Domain Structure

```
apps/api/Domains/Invoice/
  Actions/
    RunInvoiceMatching.php
  Services/
    InvoiceMatchingService.php
    ToleranceService.php
  Models/
    SupplierInvoiceMatchResult.php
  Data/
    InvoiceMatchResultData.php
    MatchingToleranceConfigData.php
  Http/
    Controllers/
      SupplierInvoiceMatchingController.php
    Requests/
      RunInvoiceMatchingRequest.php
    Resources/
      SupplierInvoiceMatchResultResource.php
```

### Domain Behavior

**`RunInvoiceMatching` (action):**

- Accepts `supplierInvoiceId` and optional `actorId` (for audit).
- Loads the invoice with `lockForUpdate` and validates it is `reviewed`.
- Loads invoice lines, PO lines with cumulative receipt data, and tolerance config.
- Calls `InvoiceMatchingService` to compute line-by-line matches.
- Transactionally:
  - Deletes prior match results for this invoice (if re-running).
  - Creates new `SupplierInvoiceMatchResult` records.
  - Sets `matching_status` on the invoice.
  - Increments `lock_version`.
- Records `supplier_invoice.matching_completed` audit event with summary.

**`InvoiceMatchingService` (service):**

- Orchestrates matching for each invoice line:
  - Two-way: quantity, unit_price, line_total, tax, freight.
  - Three-way: quantity (only if receipt data is available).
- Uses `ToleranceService` for each dimension comparison.
- Returns an aggregated result set.

**`ToleranceService` (service):**

- Provides `compare(float $expected, float $actual, float $tolerancePercent, ?float $floorAbsolute, ?float $capAbsolute): string` returning `pass` or `fail`.
- Uses the three-threshold model:
  - `$percentageTolerance = $expected * $tolerancePercent / 100`
  - `$effectiveTolerance = max($percentageTolerance, $floorAbsolute)` — ensures low-value items have a meaningful floor.
  - Pass if `$variance <= $effectiveTolerance AND $variance <= $capAbsolute` — cap prevents high-value auto-approvals.
- Handles zero-division edge cases (both zero = pass; expected zero with non-zero actual = fail).
- Loads per-tenant tolerance configuration from settings store or defaults.

### Authorization

Viewing match results and triggering matching require:
1. Authenticated Sanctum session.
2. Active tenant context.
3. Invoice belongs to the current tenant.
4. Current tenant role is buyer or admin (matching AP permissions).

### Audit Metadata

Matching audit events include:
- invoice id and internal number
- supplier invoice number
- purchase order id and number
- matching status result (`matched` / `mismatch`)
- total lines, matched lines, mismatch lines
- dimensions with issues
- trigger source: `automatic` or `manual`

## API Contract

Add authenticated tenant-scoped routes:

```
POST /api/supplier-invoices/{supplierInvoice}/run-matching
GET  /api/supplier-invoices/{supplierInvoice}/match-results
```

### POST run-matching

Request:
```json
{
  "lockVersion": 1
}
```

Response (200): Updated `SupplierInvoice` with `matchingStatus` and `matchSummary`.

Error responses:
- 409 Conflict: invoice is not `reviewed`, or stale `lockVersion`.
- 422 Validation: missing `lockVersion`.

### GET match-results

Response (200):
```json
{
  "data": [
    {
      "id": "uuid",
      "lineNumber": null,
      "matchLevel": "header",
      "matchType": "two_way",
      "dimension": "vendor_identity",
      "expectedValue": null,
      "actualValue": null,
      "tolerancePercentApplied": null,
      "toleranceAbsoluteApplied": null,
      "result": "pass",
      "notes": null
    },
    {
      "id": "uuid",
      "lineNumber": 1,
      "matchLevel": "line",
      "matchType": "two_way",
      "dimension": "unit_price",
      "expectedValue": "100.0000",
      "actualValue": "105.0000",
      "tolerancePercentApplied": "5.0000",
      "toleranceAbsoluteApplied": "2.0000",
      "result": "pass",
      "notes": null
    }
  ]
}
```

Results include header-level dimensions (vendor identity, invoice total) with `lineNumber: null`, plus line-level dimensions grouped by invoice line.

Results are grouped by invoice line, ordered by `line_number` then `dimension`.

### Extended SupplierInvoice fields

Add to existing `SupplierInvoice` resource:
```json
{
  "matchingStatus": "matched",
  "matchSummary": {
    "totalLines": 3,
    "matchedLines": 2,
    "mismatchLines": 1,
    "dimensionsWithIssues": ["unit_price", "quantity"]
  }
}
```

### Extended queue filters

`GET /api/supplier-invoices` additions:
- `matchingStatus`: filter by `pending`, `matched`, `mismatch`
- `hasMismatch`: boolean shorthand

### Extended PO invoiceSummary

```json
{
  "invoiceSummary": {
    "totalInvoiceCount": 2,
    "latestInvoiceDate": "2026-06-15",
    "totalInvoicedAmount": "1500.00",
    "currency": "USD",
    "matchingStatusCounts": {
      "pending": 0,
      "matched": 1,
      "mismatch": 1
    }
  }
}
```

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas.

## Frontend Design

### Routes

No new routes. The matching UX lives in:
- The existing invoice review panel within `(workspace)/accounts-payable/invoices/`.
- The existing AP queue table.

### Feature Structure

```
apps/web/features/accounts-payable/
  api/
    accounts-payable-invoices-api.ts       — extend with matching endpoints
  components/
    invoice-matching-status-badge.tsx       — pending/matched/mismatch badge
    invoice-match-results-panel.tsx         — per-line match results table
    invoice-match-dimension-row.tsx         — single dimension pass/fail row
  hooks/
    use-invoice-matching.ts                — trigger and poll matching
    use-invoice-match-results.ts           — fetch match results
  tables/
    accounts-payable-invoice-queue-table.tsx — extend with matching columns/filters
```

### Invoice Match Results Panel

Added to the existing invoice detail view below the review checklist section.

- **Header**: "Matching Results" with status badge (Matched / Mismatch / Pending).
- **Summary bar**: "X of Y lines matched. Issues in: unit price, quantity."
- **Per-line table**:
  - Expandable row per invoice line showing PO line reference.
  - Dimensions as sub-rows: dimension name, expected value, invoiced value, tolerance, pass/fail icon.
  - Mismatch dimensions highlighted in red with the difference shown.
- **Action button**: "Run matching" when invoice is `reviewed` and matching is `pending` or `mismatch`.

### AP Queue Extensions

- New column: `Matching` — pending/matched/mismatch badge.
- New filter tabs: `All`, `Matched`, `Mismatch`, `Pending`.
- Mismatch count shown in the queue header summary alongside review status counts.

### States

- **Loading**: spinner while matching runs.
- **Pending**: "Matching has not been run yet" with "Run matching" button.
- **Matched**: green summary, all pass rows.
- **Mismatch**: red badge with mismatch count, expandable mismatch rows.
- **Error**: matching failed (e.g., missing PO lines, concurrency conflict).
- **Empty**: no match results for this invoice.

## Seed and Demo Data

Demo data should include at least:

- Reviewed invoice with matched two-way and three-way (all dimensions pass).
- Reviewed invoice with mismatched unit price (price variance exceeds tolerance).
- Reviewed invoice with mismatched quantity (quantity invoiced > quantity received).
- Reviewed invoice with pending matching (reviewed but matching not yet run).

Seeded mismatch reasons should be realistic and clearly distinguishable.

## Testing and Verification

### API Tests

Add focused feature tests for:

- matching auto-triggers when invoice transitions to `reviewed`.
- manual matching re-run returns updated results.
- two-way matching passes when all dimensions are within tolerance.
- two-way matching fails when unit price exceeds tolerance.
- two-way matching fails when quantity exceeds tolerance.
- **cumulative over-billing protection**: two invoices against the same PO line whose quantities sum to more than the PO quantity — the second invoice fails.
- **cumulative over-billing**: a single invoice that would cause the cumulative invoiced quantity to exceed the PO quantity fails.
- **cumulative over-billing**: matching re-run recalculates cumulative from all non-current matched invoices.
- **vendor identity mismatch** produces a `fail` result when invoice vendor differs from PO vendor.
- **invoice total mismatch** produces a `fail` result when total exceeds invoice total tolerance.
- three-way matching fails when invoiced quantity exceeds received quantity.
- three-way matching passes when invoiced quantity is within receipt tolerance.
- **two-way PO policy** does not run three-way receipt matching even if receipt data exists.
- **three-way PO policy** runs both two-way and three-way matching.
- **event-driven re-trigger**: recording a goods receipt line auto-re-runs matching for pending/mismatched invoices on that PO.
- matching on non-reviewed invoice returns conflict.
- stale `lockVersion` returns conflict.
- cross-tenant invoice matching is denied.
- match results include correct dimension data and applied tolerance.
- **header-level results** (vendor identity, invoice total) have `lineNumber: null`.
- re-running matching replaces prior results.
- audit event is recorded with match summary.
- match results list is tenant-scoped.
- tolerance of zero percent or zero absolute rejects any variance.
- **percentage + floor + cap tolerance**: variance that exceeds percentage but is within the floor passes; variance that is within percentage but exceeds the cap fails.
- zero-value edge cases (both zero = pass, expected zero with non-zero actual = fail).

### Web Tests

Add tests for:

- match results panel shows correct status for matched/mismatch/pending.
- "Run matching" button visibility gated by invoice status.
- matching filter in queue works correctly.
- mismatch rows expand to show dimension details.
- pending state shows actionable button.
- loading state during matching.

### Contract and Local Verification

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
cd apps/api && php artisan test --filter=InvoiceMatching
```

## Future Evolution: Straight-Through Processing

In mature AP operations, matching runs immediately after invoice capture (not after human review). Invoices that pass matching within tolerance auto-advance to approved or payment-ready status without human intervention. This is called Straight-Through Processing (STP).

This slice intentionally triggers matching on `reviewed` to align with the P1-43 state machine, where review establishes data completeness before matching. An STP model would:

- Fire matching immediately after invoice capture/validation.
- Auto-advance `matched` invoices to `payment_ready` (or `approved` via P1-46).
- Only route `mismatch` invoices to the AP review queue (P1-43).

STP is deferred until the invoice approval domain (P1-46) and payment readiness (P1-47) are implemented, since the auto-advance target states don't yet exist. When that work is planned, the matching trigger should shift from `reviewed` to `captured`/`validated`, and the review queue should become an exception-only surface.

## Exit Criteria

- Matching runs automatically when supplier invoice reaches `reviewed`.
- Manual "Run matching" action available from the invoice workspace.
- Two-way matching compares invoice against PO for vendor identity, invoice total, and line-level quantity, unit price, line total, tax, and freight.
- Three-way matching adds receipt quantity verification for POs with `three_way` policy.
- Matching policy (`two_way` / `three_way`) is stored on the purchase order and drives which match type applies.
- Cumulative over-billing protection prevents total invoiced quantity from exceeding the PO line quantity across multiple invoices.
- Event-driven re-trigger: recording a goods receipt line auto-re-runs matching for pending/mismatched invoices on that PO.
- Per-line, per-dimension match results are stored, auditable, and tenant-scoped.
- Configurable tolerances use the three-threshold model (percentage + min floor + max cap).
- AP queue shows matching status and supports filtering.
- Mismatched invoices surface clear exception reasons as input for P1-45.
- Matched and mismatched demo states exist in seeded data.
- Tolerance configuration initial defaults are seeded for all dimensions.

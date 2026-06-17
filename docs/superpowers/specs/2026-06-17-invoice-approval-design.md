# Invoice Approval Design

## Status

- Status: Proposed for implementation
- Date: 2026-06-17
- Release scope: P1 core procure-to-pay lifecycle, slice P1-46 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-46`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-06-12-supplier-invoice-capture-design.md`
  - `docs/superpowers/specs/2026-06-13-invoice-review-workspace-design.md`
  - `docs/superpowers/specs/2026-06-17-two-way-three-way-matching-design.md`
  - `docs/superpowers/specs/2026-06-17-invoice-exception-workflow-design.md`
- Downstream slices:
  - P1-47 Payment Readiness and AP Handoff — this slice produces `approved` invoices as its entry condition
  - P1-48 Payment Status Tracking
  - P1-49 Credit Memo and Invoice Adjustment

## Roadmap Analysis

P1-46 asks Cognify to route clean or exception-resolved invoices for approval based on amount, department, cost center, project, vendor, variance, or policy. The roadmap explicitly requires reuse of the shared Approval domain with invoice-specific subject metadata.

P1-44 and P1-45 (matching and exception workflow) are now fully implemented. Invoices exit P1-45 in `ready_for_approval` status when all exceptions are resolved and either matching passes after value adjustment or all exceptions are resolved by human acceptance. P1-47 (payment readiness) needs `approved` invoices as its entry condition.

P1-44 explicitly deferred Straight-Through Processing (STP) to P1-46: "STP is deferred until the invoice approval domain (P1-46) and payment readiness (P1-47) are implemented, since the auto-advance target states don't yet exist." This slice closes that commitment by making clean-matched and explanation-resolved invoices auto-advance to `approved` without human approval tasks.

## Problem

After matching and exception resolution, Cognify has invoices in `ready_for_approval` but cannot:

- Route invoices through the shared Approval domain for policy-based approval.
- Determine which invoices need human approval and which can auto-advance (STP).
- Record an approval decision as a durable, auditable workflow fact on the invoice.
- Provide approvers with invoice-specific context (matching summary, exception history, vendor info, PO linkage) in the approval queue.
- Gate downstream payment readiness (P1-47) on a completed approval decision.

Without P1-46, the entire P2P lifecycle stalls at the approval gate: invoices cannot reach payment readiness, cycle time remains hidden, and the Approval domain has no `supplier_invoice` subject coverage.

## Goals

- Extend the shared Approval domain with `supplier_invoice` as a first-class approval subject type.
- Implement Straight-Through Processing (STP): invoices with clean matching (zero exceptions ever) or all-exceptions-resolved-by-explanation auto-advance to `approved` without human tasks.
- Route non-STP invoices (those with value-adjustment exceptions) into the Approval domain automatically when they reach `ready_for_approval`.
- Support full policy-based routing matching on amount, department, cost center, project, vendor, matching status, and exception count through existing approval policy rules.
- Add invoice states: `in_approval`, `approved`, `rejected`, `changes_requested`.
- Expose invoice approval status in the AP queue, invoice workspace, and approval task queue.
- Record audit events for STP auto-advance, approval submit, approve, reject, and changes-requested.
- Keep tenant isolation, role checks, lock-version concurrency, generated-client contracts, and real route-stack tests consistent with existing approval patterns.

## Non-Goals

- Payment readiness, AP export, payment status, or credit memo handling (P1-47 through P1-49 scope).
- Purchase order change orders triggered by invoice rejection.
- AI-driven approval routing or automated approval decisions beyond STP.
- Multi-currency or formal tax rule standardization beyond existing invoice data.
- Supplier-facing approval status notifications.
- Batch invoice approval or P2P operational queues (P1-54 scope).
- Approval policy administration UI redesign; this slice only extends existing policy subject support.

## Approaches Considered

### 1. Straight-Through Processing + shared Approval domain (selected)

Add an STP gate at the `ready_for_approval` entry point. Clean-matched invoices and explanation-resolved invoices auto-advance. Non-STP invoices auto-submit to the shared Approval domain as `supplier_invoice` subjects. Selected because it closes the P1-44 STP commitment, follows industry best practice (85% cycle-time reduction for touchless invoices), and reuses the existing Approval infrastructure without building duplicate approval mechanisms.

### 2. Manual-submit-only with no STP

All invoices in `ready_for_approval` wait for an AP user to manually click "Submit for approval". No auto-advance. Simpler implementation, but misses the P1-44 STP commitment and forces human interaction on every invoice including those that match perfectly with zero discrepancies. Rejected.

### 3. Standalone invoice approval engine outside shared Approval

Build invoice-specific approval routing, task creation, and decision handling without extending the shared Approval domain. Avoids extending the Approval subject handler pattern. Rejected because it duplicates the existing Approval infrastructure, loses delegation/SLA/escalation/notification behavior, and would diverge from the consistent approval pattern used by requisitions, award recommendations, and purchase orders.

## Workflow

### Actors

- AP user: views invoices in the approval queue, monitors approval status, corrects invoices when changes are requested.
- Buyer: views invoice approval status for their POs, may receive approval tasks when policy assigns them.
- Finance/procurement approver: reviews invoice approval tasks, approves, rejects, or requests changes.
- Admin: views all invoice approval tasks across the tenant, can act on any task.
- System: evaluates STP eligibility, auto-advances or auto-submits when invoice reaches `ready_for_approval`, matches approval policy, creates tasks, records audit events, updates invoice state through subject handlers.

### STP Eligibility

Straight-Through Processing applies when an invoice reaches `ready_for_approval` and meets one of these criteria:

**Clean match path**: The invoice was matched (`matching_status = matched`) and zero `SupplierInvoiceException` records were ever created for it. No human intervention occurred at any point in the matching/exception lifecycle.

**Explanation-resolved path**: All exceptions on the invoice are resolved, and every resolved exception has `resolution_type = explanation` (variance accepted by human during exception resolution). No exceptions used `value_adjustment`.

STP eligibility is evaluated atomically when the invoice transitions to `ready_for_approval`. If eligible, the invoice moves directly to `approved` without creating an approval instance, tasks, or notifications.

### State Model

Extend `SupplierInvoiceStatus`:

| Status | Meaning |
|---|---|
| `captured` | Invoice captured, awaiting AP review (unchanged) |
| `in_review` | AP review in progress (unchanged) |
| `needs_information` | Invoice blocked pending more info (unchanged). Also used as the target for approval changes-requested |
| `reviewed` | AP review passed, eligible for matching (unchanged) |
| `matched` | Matching passed, no exceptions (unchanged) |
| `mismatch` | Matching failed, exceptions active (unchanged) |
| `ready_for_approval` | All exceptions resolved, ready for approval processing (unchanged) |
| `in_approval` | Routed to shared Approval domain, active approval instance |
| `approved` | Approval completed or STP auto-advanced |
| `rejected` | Approval task rejected — terminal |
| `changes_requested` | Approver requested corrections — transitions to `needs_information` |

Allowed transitions:

| From | Action | To |
|---|---|---|
| `ready_for_approval` | STP check passes | `approved` |
| `ready_for_approval` | Submit for approval (auto or manual) | `in_approval` |
| `in_approval` | Approval task approves | `approved` |
| `in_approval` | Approval task rejects | `rejected` |
| `in_approval` | Approval task requests changes | `changes_requested` → `needs_information` |
| `needs_information` | (from changes-requested) AP user corrects and resubmits | `reviewed` |
| `changes_requested` | Auto-transition (no user action) | `needs_information` |

[^reentry]: See §6.2 steps 7a–7b. A changes-requested invoice in `needs_information` must transition to `reviewed` (not directly to `ready_for_approval` or `in_approval`), re-enter the matching/exception pipeline, and then reach `ready_for_approval` before it can be submitted again. It is NOT eligible for STP on re-entry.

State rules:
- `ready_for_approval` is the only entry point for approval processing. Re-entering after changes-requested must pass through `reviewed` → matching/exception → `ready_for_approval` — submitting directly from `needs_information` is disallowed.
- STP evaluation fires immediately on entry to `ready_for_approval` before any user action.
- Non-STP invoices auto-submit to the Approval domain via `SubmitSupplierInvoiceForApproval` immediately on entry to `ready_for_approval`.
- `in_approval` invoices cannot be edited through invoice capture or review endpoints.
- `approved` is terminal for this slice — moved to `paid` or `payment_ready` by downstream P1-47.
- `rejected` is terminal for this slice — visible in the AP queue for audit and potential rework (deferred).
- `changes_requested` immediately transitions the invoice to `needs_information` status. The original approval reason and requested fields are preserved so the AP user knows what to fix.

### Main Flow

1. P1-45 `RunPostResolutionMatching` completes and transitions invoice to `ready_for_approval`.
2. **STP gate** fires automatically within the same transactional boundary:
   - `EvaluateStraightThroughProcessing` action checks STP eligibility.
   - If STP-eligible → invoice moves to `approved`. Records `supplier_invoice.stp_auto_approved` audit event. No approval tasks or notifications. Process complete.
   - If not STP-eligible → proceeds to step 3.
3. `SubmitSupplierInvoiceForApproval` action fires automatically (no manual click needed):
   - Validates invoice is in `ready_for_approval`.
   - Calls `RouteSubjectForApproval` in the Approval domain with `SupplierInvoiceApprovalSubjectHandler`.
   - Approval domain matches a published policy with `subject_type = supplier_invoice`.
   - Creates approval instance, stages, and tasks.
   - Sets invoice to `in_approval`, stores `approval_instance_id`, `approval_submitted_by_user_id` (system or actor).
   - Records `supplier_invoice.approval_submitted` audit event.
4. Approvers see the invoice approval task in the approval queue with invoice-specific context.
5. Approver action through existing approval task endpoints records the decision and invokes the subject handler:
   - `onApproved()` → invoice moves to `approved`, stores `approved_by_user_id`, `approved_at`.
   - `onRejected()` → invoice moves to `rejected`, stores `rejected_by_user_id`, `rejected_at`, `rejected_reason`.
   - `onChangesRequested()` → invoice moves to `changes_requested` → `needs_information`, stores reason.
6. Audit event recorded for each outcome.

7. **Changes-requested re-entry** (when approver selected `changes_requested` → invoice is now in `needs_information`):
   - 7a. AP user edits the invoice (corrects the requested fields) and submits, which transitions the invoice to `reviewed` (re-entering the review pipeline). This step must invalidate any cached matching results, since the underlying data changed.
   - 7b. P1-45 `RunPostResolutionMatching` runs again, detecting the re-entry. Matching/exception workflow proceeds through `matched`/`mismatch` → resolution → `ready_for_approval`. On re-entry to `ready_for_approval`, STP eligibility is re-evaluated as a normal gate (may or may not pass). The invoice then proceeds from step 2 (STP gate) normally.
   - Note: An invoice re-entering from `needs_information` (changes-requested path) is NOT eligible for STP on its first re-entry to `ready_for_approval`. If all exceptions are value-adjusted or explanation-resolved in this cycle, it still proceeds through human approval. This prevents silent auto-approval after an approver explicitly requested changes.

### Failure Paths

- No matching published approval policy with `subject_type = supplier_invoice`: return `409` with a message that invoice approval routing is not configured. Invoice remains in `ready_for_approval`.
- Wrong state (not `ready_for_approval`): return `409`.
- Stale `lockVersion`: return `409`.
- Missing required invoice fields or no PO linkage: return `409` with actionable information.
- Cross-tenant access: deny through tenant-scoped queries and policies.
- Non-AP/buyer/admin submit: return `403`.
- Non-assignee approval task action: use existing approval task authorization.
- STP evaluation error (e.g., missing matching data): log and keep invoice in `ready_for_approval` so the error does not block human intervention.

## Backend Design

### Domain Ownership

The owning domain remains `apps/api/Domains/Invoice`. Approval behavior lives in `apps/api/Domains/Approval` through the subject handler pattern.

Supporting domains:
- `Domains/Approval` — approval instance, stages, tasks, policy matching, delegation, SLA, escalation, notifications.
- `Domains/PurchaseOrder` — PO and line data for approval context and policy matching fields.
- `Domains/Invoice` — invoice state, STP evaluation, submit-for-approval action, approval outcome actions.
- `app/Audit` — audit recording.
- `app/Tenancy` — tenant resolution and membership enforcement.

### Approval Subject Handler

New class `SupplierInvoiceApprovalSubjectHandler` in `apps/api/Domains/Approval/SubjectHandlers/`:

```php
final class SupplierInvoiceApprovalSubjectHandler implements ApprovalSubjectHandler
{
    public function subjectType(): string { return 'supplier_invoice'; }
    public function modelClass(): string { return SupplierInvoice::class; }

    public function buildContext(Model $subject): ApprovalContextData
    {
        assert($subject instanceof SupplierInvoice);
        $subject->loadMissing([
            'purchaseOrder',
            'purchaseOrder.vendor',
            'exceptions',
        ]);

        $originalAmount = (float) ($subject->total_amount ?? 0);
        $netPayableAmount = $this->calculateNetPayableAmount($subject, $originalAmount);

        return new ApprovalContextData(
            tenantId: (string) $subject->tenant_id,
            subjectType: 'supplier_invoice',
            requisitionId: null,
            requesterId: null,
            amount: $netPayableAmount,
            currency: $subject->currency,
            department: $subject->purchaseOrder?->department,
            costCenter: $subject->purchaseOrder?->cost_center,
            projectId: $subject->purchaseOrder?->project_id !== null
                ? (string) $subject->purchaseOrder->project_id : null,
            lineItemCategories: [],
            riskClassification: null,
            vendorId: $subject->purchaseOrder?->vendor_id !== null
                ? (string) $subject->purchaseOrder->vendor_id : null,
            supplierInvoiceId: (string) $subject->id,
            purchaseOrderId: $subject->purchase_order_id !== null
                ? (string) $subject->purchase_order_id : null,
            purchaseOrderNumber: $subject->purchaseOrder?->number,
            matchingStatus: $subject->matching_status,
            exceptionCount: $subject->exceptions->count(),
            hasValueAdjustments: $subject->exceptions
                ->contains(fn ($e) => $e->resolution_type === 'value_adjustment'),
            originalInvoiceAmount: $originalAmount,
            totalVarianceAdjusted: $originalAmount - $netPayableAmount,
        );
    }

    private function calculateNetPayableAmount(SupplierInvoice $invoice, float $originalAmount): float
    {
        $adjustments = $invoice->exceptions
            ->where('resolution_type', 'value_adjustment');

        if ($adjustments->isEmpty()) {
            return $originalAmount;
        }

        $totalVariance = (float) $adjustments->sum(function ($exception): float {
            if ($exception->adjusted_value === null || $exception->expected_value === null) {
                return 0.0;
            }

            return (float) $exception->expected_value - (float) $exception->adjusted_value;
        });

        return max(0.0, $originalAmount - $totalVariance);
    }

    public function taskSubjectSummary(Model $subject): ApprovalSubjectSummary
    {
        // Return: type=supplier_invoice, id, number (INV-xxx),
        //   title="Invoice {number} from {vendorName}",
        //   primaryParty=vendorName, amount=totalAmount, currency,
        //   href="/accounts-payable/invoices/{id}",
        //   metadata with PO reference, matching summary, exception summary
    }

    public function taskTitle(Model $subject): string
    {
        return "Approve invoice {$subject->number} from {$vendorName}";
    }

    public function onApproved(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor): void
    {
        // MarkSupplierInvoiceApproved: status=approved, approved_by, approved_at, lockVersion++
    }

    public function onRejected(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
    {
        // MarkSupplierInvoiceRejected: status=rejected, rejected_by, rejected_at, rejected_reason, lockVersion++
    }

    public function onChangesRequested(Tenant $tenant, Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
    {
        // MarkSupplierInvoiceChangesRequested: status=changes_requested -> needs_information,
        // stores reason and requested fields, lockVersion++
    }
}
```

### Data Model

Add columns to `supplier_invoices`:

```sql
approval_instance_id            UUID FK to approval_instances nullable
approval_submitted_by_user_id   FK to users nullable
approval_submitted_at           TIMESTAMP nullable
approved_by_user_id             FK to users nullable
approved_at                     TIMESTAMP nullable
rejected_by_user_id             FK to users nullable
rejected_at                     TIMESTAMP nullable
rejected_reason                 TEXT nullable
changes_requested_by_user_id    FK to users nullable
changes_requested_at            TIMESTAMP nullable
changes_requested_reason        TEXT nullable
changes_requested_fields        JSON nullable -- field labels requested to change
```

### Domain Structure

```txt
apps/api/Domains/Invoice/
  Actions/
    EvaluateStraightThroughProcessing.php
    SubmitSupplierInvoiceForApproval.php
    MarkSupplierInvoiceApproved.php
    MarkSupplierInvoiceRejected.php
    MarkSupplierInvoiceChangesRequested.php
  States/
    SupplierInvoiceStatus.php -- extend with in_approval, approved, rejected, changes_requested

apps/api/Domains/Approval/
  SubjectHandlers/
    SupplierInvoiceApprovalSubjectHandler.php -- new
```

### Domain Behavior

**`EvaluateStraightThroughProcessing`**:

- Accepts a supplier invoice that has just entered `ready_for_approval`.
- Loads the invoice with its exceptions and matching status.
- Checks STP conditions:
  - If `matching_status = matched` AND no `SupplierInvoiceException` records exist → STP-eligible (clean match).
  - If all exceptions are resolved AND every resolved exception has `resolution_type = explanation` → STP-eligible (all resolved by human acceptance).
  - Otherwise → not STP-eligible.
- If STP-eligible, transitions invoice to `approved`, sets `approved_by_user_id` to null (system), `approved_at`, increments `lock_version`, records `supplier_invoice.stp_auto_approved`.
- Returns boolean indicating whether STP was applied.

**`SubmitSupplierInvoiceForApproval`**:

- Accepts a supplier invoice, optional actor (system or user).
- Locks the invoice row by tenant.
- Asserts the invoice is `ready_for_approval`. Invoices in `needs_information` (even those that entered via `changes_requested`) cannot be submitted directly — they must re-enter through `reviewed` → matching/exception → `ready_for_approval` (see §6.2 steps 7a–7b).
- Asserts `lock_version`.
- Calls `RouteSubjectForApproval` in the Approval domain with `SupplierInvoiceApprovalSubjectHandler`.
- On success: sets status to `in_approval`, stores `approval_instance_id`, `approval_submitted_by_user_id`, `approval_submitted_at`, increments `lock_version`.
- On no-matching-policy: returns conflict 409.
- Records `supplier_invoice.approval_submitted`.

**`MarkSupplierInvoiceApproved`**:

- Called by `onApproved` handler.
- Sets status to `approved`, `approved_by_user_id`, `approved_at`, increments `lock_version`.
- Records `supplier_invoice.approved`.

**`MarkSupplierInvoiceRejected`**:

- Called by `onRejected` handler.
- Sets status to `rejected`, `rejected_by_user_id`, `rejected_at`, `rejected_reason`, increments `lock_version`.
- Records `supplier_invoice.rejected`.

**`MarkSupplierInvoiceChangesRequested`**:

- Called by `onChangesRequested` handler.
- Sets status to `changes_requested`, then immediately transitions to `needs_information`.
- Stores `changes_requested_by_user_id`, `changes_requested_at`, `changes_requested_reason`, `changes_requested_fields`.
- Increments `lock_version`.
- Records `supplier_invoice.changes_requested`.
- Invalidates cached matching results on the invoice so that P1-45 re-runs matching when the invoice re-enters through `reviewed` (step 7a).

### Authorization

- Viewing invoice approval status: AP user, buyer, admin (existing invoice view permissions).
- Submitting for approval (manual override): AP user, admin.
- Auto-submit: system action, no user permission needed.
- Approval task actions: through existing approval task permissions (assigned approver or delegate).
- Admin: can view and act on any invoice approval task in the tenant.

### Concurrency

The `SupplierInvoiceApprovalSubjectHandler` callbacks (`onApproved`, `onRejected`, `onChangesRequested`) are invoked by the shared Approval domain within its own transaction. Because multiple approvers could act simultaneously on different tasks for the same approval instance, the handler callbacks MUST wrap their invoice state mutation in a pessimistic lock:

```php
DB::transaction(function () use ($tenant, $subject, $instance, $actor) {
    $lockedInvoice = SupplierInvoice::query()
        ->where('tenant_id', $tenant->id)
        ->where('id', $subject->id)
        ->lockForUpdate()
        ->firstOrFail();

    $lockedInvoice->lock_version += 1;
    $lockedInvoice->status = SupplierInvoiceStatus::APPROVED;
    // ...
    $lockedInvoice->save();
});
```

This prevents two concurrent handler invocations from seeing the same `lock_version` and double-applying the status change. The outer optimistic `lock_version` check on the submit-approval endpoint is sufficient for that single-user path; only the handler callbacks need `lockForUpdate()` because they run inside the Approval domain's task-resolution transaction which may interleave multiple simultaneous task actions.

Same pattern applies to all three handler callbacks. Without this, two approvers acting simultaneously could both see `in_approval`, both pass `lock_version` check, and both write conflicting status changes (e.g., one approves and one rejects, last write wins and loses the other's decision).

### Audit Events

| Event | Trigger |
|---|---|
| `supplier_invoice.stp_auto_approved` | STP auto-advanced invoice to approved |
| `supplier_invoice.approval_submitted` | Invoice submitted to Approval domain |
| `supplier_invoice.approved` | Approval task approved the invoice |
| `supplier_invoice.rejected` | Approval task rejected the invoice |
| `supplier_invoice.changes_requested` | Approval task requested changes |

Audit metadata includes: invoice id, number, vendor id, PO id, amount, matching status, exception count, STP path indicator, approval instance id, outcome reason.

## API Contract

Add tenant-scoped authenticated routes:

```txt
POST /api/supplier-invoices/{supplierInvoice}/submit-approval
```

Request:
```json
{
  "lockVersion": 1
}
```

Response (200): Updated `SupplierInvoice` with status `in_approval`.

Error responses:
- 403: Not authorized.
- 409: Invoice not in `ready_for_approval`, stale `lockVersion`, or no matching approval policy.

Extend `GET /api/supplier-invoices` filters:

```txt
?status=in_approval,approved,rejected
?approvalStatus=pending,approved,rejected
```

Extend `SupplierInvoice` resource with:

```json
{
  "approvalInstanceId": "uuid-or-null",
  "approvalSubmittedByUserId": "uuid-or-null",
  "approvalSubmittedAt": "iso-timestamp-or-null",
  "approvedByUserId": "uuid-or-null",
  "approvedAt": "iso-timestamp-or-null",
  "rejectedByUserId": "uuid-or-null",
  "rejectedAt": "iso-timestamp-or-null",
  "rejectedReason": "string-or-null",
  "changesRequestedByUserId": "uuid-or-null",
  "changesRequestedAt": "iso-timestamp-or-null",
  "changesRequestedReason": "string-or-null",
  "changesRequestedFields": ["string"]-or-null
}
```

Extend approval subject schemas:

- `ApprovalPolicySubjectType`: add `supplier_invoice`.
- `ApprovalTaskSubjectMetadata`: add `supplier_invoice` variant with invoice context.

Extend `ApprovalContextData` with invoice-specific fields (following the award-recommendation pattern of optional constructor parameters with defaults):

```php
public readonly ?string $supplierInvoiceId = null,
public readonly ?string $purchaseOrderId = null,
public readonly ?string $purchaseOrderNumber = null,
public readonly ?string $matchingStatus = null,
public readonly int $exceptionCount = 0,
public readonly bool $hasValueAdjustments = false,
public readonly float $originalInvoiceAmount = 0.0,
public readonly float $totalVarianceAdjusted = 0.0,
```

These fields are set by `SupplierInvoiceApprovalSubjectHandler::buildContext()` and are `null`/`false` for other subjects. Update `fromArray()`, `toArray()`, and `missingRequiredContext()` accordingly. Update the `resolveFieldValue` method in `ApprovalPolicyMatcher` so policy rules can match on:
- `supplierInvoiceId`
- `purchaseOrderId`
- `purchaseOrderNumber`
- `matchingStatus`
- `exceptionCount`
- `hasValueAdjustments`
- `originalInvoiceAmount`
- `totalVarianceAdjusted`

After OpenAPI changes, regenerate `packages/api-client` and consume generated endpoints and schemas in the web feature. Do not duplicate contract response types in app code.

## Frontend Design

### Routes

No new routes. Approval UX lives in:
- The existing AP invoice queue at `(workspace)/accounts-payable/invoices/`.
- The existing approval queue and task detail at `(workspace)/approvals/`.

### Feature Structure

```txt
apps/web/features/accounts-payable/
  components/
    invoice-approval-status-panel.tsx     -- approval timeline, submit action, outcome display
    invoice-approval-status-badge.tsx     -- in_approval/approved/rejected badge
  hooks/
    use-invoice-approval.ts              -- submit-approval mutation, STP status
  mocks/
    accounts-payable-approval-fixtures.ts
    accounts-payable-approval-handlers.ts
  tables/
    accounts-payable-invoice-queue-table.tsx -- extend with approval status columns/filters
```

### AP Queue Extensions

Add queue tabs:

| Tab | Filter |
|---|---|
| Needs review (existing) | `captured` |
| In review (existing) | `in_review` |
| Needs information (existing) | `needs_information` |
| Reviewed (existing) | `reviewed` |
| **In approval** (new) | `in_approval` |
| **Approved** (new) | `approved` |
| **Rejected** (new) | `rejected` |

Column additions:
- `Approval status` badge for `in_approval`/`approved`/`rejected`.
- `Approver` name when approved/rejected.

Header summary counts: include approved and rejected counts.

### Invoice Workspace Approval Panel

When invoice status is `ready_for_approval`, `in_approval`, `approved`, `rejected`, or `needs_information` (from changes-requested):

**`ready_for_approval`** (visible briefly before auto-submit):
- Status: "Ready for approval"
- If STP-eligible: "This invoice will be auto-approved" info banner.
- If non-STP: "Routing for approval" info banner (auto-submit fires immediately).

**`in_approval`**:
- Status badge: In approval (blue).
- Approval instance summary: stage, due date, approver count.
- Link to the active approval task if current user is assigned.

**`approved`**:
- Status badge: Approved (green).
- Approved by and timestamp.
- Link to approval instance summary for audit.

**`rejected`**:
- Status badge: Rejected (red).
- Rejected by, timestamp, and reason.
- No rework action in this slice (deferred to downstream).

**`needs_information`** (when originating from changes-requested):
- Shows changes-requested reason and requested field labels.
- AP user can correct fields and resubmit.
- Resubmission transitions the invoice to `reviewed` (step 7a), re-entering the review/matching/exception pipeline. It does NOT call `SubmitSupplierInvoiceForApproval` directly — that action only accepts `ready_for_approval`.

### Approval Queue

The existing approval task list supports `supplier_invoice` subjects:

- Subject label: "Approve invoice INV-2026-000042 from Acme Corp".
- Subject type filter: include `supplier_invoice`.
- Primary party: vendor name.
- Amount: invoice total.
- Task detail panel shows:
  - Invoice number, date, due date, total, currency.
  - Vendor name and PO reference (number, total).
  - Invoice line summary.
  - Matching status (matched / mismatch).
  - Exception summary (count, dimensions, resolution type).
  - For value-adjustment exceptions: the proposed adjusted value and difference from original.
  - Links to the invoice workspace at `/accounts-payable/invoices/{id}`.

### States

Every approval-related component must handle: loading, empty, populated, error, permission-denied, and stale-state conflict views.

## Data Flow

```txt
P1-45 RunPostResolutionMatching completes
  -> Invoice status: ready_for_approval
  -> EvaluateStraightThroughProcessing
  |  -> STP-eligible
  |     -> Invoice status: approved (system, auto)
  |     -> Audit: supplier_invoice.stp_auto_approved
  |     -> Complete (no approval tasks created)
  |
  -> Not STP-eligible (value adjustments exist)
     -> SubmitSupplierInvoiceForApproval (auto, same tx boundary)
        -> RouteSubjectForApproval with SupplierInvoiceApprovalSubjectHandler
        -> Approval domain: match policy, create instance/stages/tasks
        -> Invoice status: in_approval
        -> Approval tasks visible in queue
        -> Approver acts: approve/reject/request-changes
           -> Subject handler callback
           -> Invoice status: approved/rejected/needs_information
           -> Audit event recorded
```

The STP gate and auto-submit are in the same transactional boundary as the P1-45 state transition, so there is no window where the invoice sits in `ready_for_approval` without processing. If the transaction fails, the invoice remains in `mismatch` or whatever status prompted the resolution check.

## Error Handling

- No matching approval policy (`subject_type = supplier_invoice`): 409 conflict. Invoice stays `ready_for_approval`. AP user sees "Invoice approval policy not configured" message.
- Stale `lockVersion`: 409 conflict. User refreshes and retries.
- Wrong state for submit: 409 conflict with current vs expected status.
- Cross-tenant access: 403/404.
- STP evaluation that encounters missing data (no match results, deleted PO): log error, keep invoice `ready_for_approval`, and surface a human-noticeable error state.

## Seed and Demo Data

Demo data should include at least:

- Invoice in `ready_for_approval` that is STP-eligible (clean match, zero exceptions) — auto-approves on seed refresh.
- Invoice in `ready_for_approval` with value-adjustment exceptions — routes to approval automatically.
- Invoice in `in_approval` with an active approval task assigned to a demo approver.
- Invoice in `approved` status via approval task outcome.
- Invoice in `approved` status via STP auto-advance (distinguishable via `approved_by_user_id = null`).
- Invoice in `rejected` status with reason.
- Invoice in `needs_information` status from changes-requested with approver reason.
- Published approval policy with `subject_type = supplier_invoice` so the non-STP path works.

Seeded data should reference real seeded users (buyer, finance approver, admin), purchase orders, vendors, invoice lines, and where possible match results and exception records from the P1-44/P1-45 seeders.

## Testing and Verification

### API Tests

Add focused feature tests for:

- STP auto-approves invoice with zero exceptions and clean match.
- STP auto-approves invoice when all exceptions resolved by explanation.
- STP does NOT fire when value-adjustment exceptions exist.
- Non-STP invoice auto-submits to approval when entering `ready_for_approval`.
- Submit-approval creates an approval instance and tasks.
- Submit-approval fails on wrong state (captured, reviewed, approved, rejected).
- Submit-approval fails without published `supplier_invoice` approval policy.
- Approval task approve transitions invoice to `approved` with metadata.
- Approval task reject transitions invoice to `rejected` with reason.
- Approval task request-changes transitions invoice through `changes_requested` to `needs_information` with reason.
- Post-changes-requested resubmit transitions invoice to `reviewed` (not directly to `ready_for_approval` or `in_approval`).
- Post-changes-requested invoice that reaches `ready_for_approval` is NOT STP-eligible (must proceed through human approval).
- Submit-approval rejects invoice in `needs_information` (even from changes-requested).
- Cross-tenant submit and approval task access are denied.
- Stale `lockVersion` returns conflict on submit.
- Approval outcome audit events are recorded.
- STP audit event is recorded with path indicator.
- Invoice in `rejected` or `approved` cannot be resubmitted through submit-approval.
- Approval task subject detail shows invoice-specific context.

### Web Tests

Add tests for:

- AP queue approval tabs (`In approval`, `Approved`, `Rejected`) render correctly.
- Invoice workspace shows approval panel with correct status.
- `Ready_for_approval` STP-eligible invoice shows auto-approval info.
- `Ready_for_approval` non-STP invoice shows routing info.
- `In_approval` invoice shows approval instance summary and task link.
- `Approved` invoice shows green badge and approver metadata.
- `Rejected` invoice shows red badge and rejection reason.
- `Needs_information` from changes-requested shows approver reason and resubmit action.
- Approval queue lists `supplier_invoice` tasks with correct subject labels.
- Approval task detail shows invoice context (vendor, PO, matching, exceptions).
- Loading, empty, error, permission-denied, and stale-state conflict states.
- STP info banner does not show a submit button.

### Verification Commands

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=SupplierInvoiceApproval
cd apps/api && php artisan test --filter=ApprovalTask
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web test -- approvals
pnpm --filter @cognify/web typecheck
pnpm lint
```

Because this slice changes visible invoice and approval task screens, visual inspection against the real API-backed app is required before PR completion.

## Future Evolution

### Post-Rejection Rework

When an invoice is rejected, the current design makes it terminal. A future slice should allow AP users to correct the root cause (e.g., request a credit memo from the vendor, adjust the invoice capture) and resubmit for approval. That rework path should create a new approval instance rather than reopening the rejected one.

### SLA-Based Escalation for Invoice Approval

Invoice approval tasks should respect the same SLA-based escalation rules as other approval subjects. This works automatically through the shared Approval domain's escalation infrastructure, but explicit invoice-specific SLA defaults (e.g., 3 business days for invoice approval vs 5 for PO approval) could be configured in a future slice.

### Batch Approval

A future slice could add batch approval actions: "Approve selected invoices" from the queue. This would be a queue-level action, not a change to the approval task model.

### Auto-Approval Threshold Tuning

The STP criteria could be extended with configurable thresholds: e.g., "auto-approve invoices under $1,000 even if they have exceptions" or "require approval for all invoices over $50,000 regardless of match status." This would be a policy-level configuration rather than hard-coded STP logic.

### Integration with P1-47 Payment Readiness

P1-47 will consume `approved` invoices as its entry condition. The transition from `approved` to `payment_ready` is owned by that slice and may add its own validation (vendor banking status, currency checks, tax coding verification).

### Escalation and SLA for Invoice Approval

The shared Approval domain's escalation infrastructure already supports per-subject SLA defaults. A future slice could configure invoice-specific SLA rules (e.g., 3 business days for invoice approval, auto-escalate to finance manager after 5 days) through policy-level settings.

### Handler Callback Concurrency Hardening

The pessimistic locking pattern described in §Concurrency uses `lockForUpdate()` within handler callbacks. A future slice could replace this with a dedicated `SupplierInvoiceApprovalOutbox` pattern: handlers write a domain event to an outbox table, and a separate consumer processes the events sequentially per invoice ID, eliminating the need for `lockForUpdate()` in the hot approval task resolution path.

## Exit Criteria

- Invoices with zero exceptions and clean matching auto-advance to `approved` through STP (no human task).
- Invoices with all exceptions resolved by explanation auto-advance to `approved` through STP.
- Invoices with value-adjustment exceptions auto-submit to the shared Approval domain when they reach `ready_for_approval`.
- Approval policy `subject_type = supplier_invoice` supports matching on amount, vendor, department, cost center, project, matching status, and exception count.
- Invoice states `in_approval`, `approved`, `rejected`, and `changes_requested` are durable, auditable, and tenant-scoped.
- AP queue tabs filter by approval status (`In approval`, `Approved`, `Rejected`).
- Invoice workspace shows approval status panel for all relevant states.
- Approval queue lists `supplier_invoice` subjects with invoice-specific context (vendor, PO, matching, exceptions).
- Approval task actions (approve, reject, request-changes) correctly update invoice state via `SupplierInvoiceApprovalSubjectHandler`.
- No matching approval policy returns a clear `409` conflict message.
- Cross-tenant access is denied at every step.
- Audit events are recorded for STP auto-advance, submit, approve, reject, and changes-requested.
- OpenAPI endpoints are generated and consumed by `@cognify/api-client`.
- Seeded demo data includes STP-approved, approval-approved, in-approval, rejected, and changes-requested invoice states.
- Downstream P1-47 has a clear `approved` precondition to consume.

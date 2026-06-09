# Purchase Order Review And Approval Design

## Status

- Status: Accepted for implementation
- Date: 2026-06-09
- Release scope: P1 core procure-to-pay lifecycle, slice P1-37 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-37`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-26-award-approval-design.md`
  - `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`
  - `docs/superpowers/specs/2026-06-09-purchase-order-creation-design.md`

## Roadmap Analysis

`P1-37 Purchase Order Review and Approval` is the next unfinished P1 feature after `P1-36 Purchase Order Creation`, which is implemented and merged on `main` as PR 44. The current PO aggregate can be created from a ready/exported PO handoff, edited while in `draft`, and moved to `ready_for_review`. That state is intentionally terminal for P1-36 and is the entry point for this slice.

P1-37 must make the purchase order operationally approvable before it can be issued to suppliers in P1-38. The approval should validate finance/procurement readiness, coding, tax, vendor readiness, delivery details, and commercial accuracy without starting supplier delivery, change orders, receiving, invoices, or budget encumbrance.

## Problem

Cognify now has durable purchase orders, but `ready_for_review` does not yet create an approval task, enforce a reviewer decision, or record the outcome on the PO itself. Buyers can prepare a PO, but finance or procurement reviewers have no governed way to approve, reject, or request corrections before supplier issue. That leaves downstream P2P work without a trustworthy approval boundary.

## Goals

- Route a `ready_for_review` purchase order into the shared Approval domain as a first-class approval subject.
- Create approval tasks for configured finance/procurement approvers without inventing a PO-specific approval engine.
- Expose PO review state on the purchase order resource, list, workspace, and approval task details.
- Allow buyers/admins to submit a ready PO for approval, and allow assigned approvers to approve, reject, or request changes through existing approval task actions.
- Move purchase orders through explicit backend-owned states: `ready_for_review`, `in_review`, `approved`, `rejected`, `changes_requested`, and existing `cancelled`.
- Preserve audit evidence for route start, approval, rejection, and requested changes.
- Keep tenant isolation, role checks, lock-version conflict handling, generated-client contracts, and real route-stack tests.
- Keep the PO approval slice thin enough that P1-38 can issue only approved purchase orders.

## Non-Goals

- Supplier issue, email delivery, vendor portal exposure, supplier acknowledgement, or export-as-issued behavior. These belong to P1-38.
- PO change orders, amendment versioning, cancellation after issue, or re-approval after post-approval commercial changes. These belong to P1-39.
- Receiving, goods receipt, delivery tracking, invoices, matching, AP handoff, payment status, and budget encumbrance.
- Building a new approval engine or duplicating approval task routes.
- A full approval-policy administration redesign. P1-37 only extends existing policy subject support to purchase orders.
- Multi-PO batch review or P2P operational queues. Those belong to P1-54.

## Approaches Considered

### 1. Add PO-Specific Review Columns And Buttons Only

This would add `approved_by`, `approved_at`, and a direct approve/reject endpoint on purchase orders. It is rejected because it bypasses Cognify's existing Approval domain, loses SLA/delegation/escalation behavior, and creates a second approval model that future invoice approval would likely duplicate.

### 2. Reuse Award Approval As The PO Approval

This would treat the award approval instance copied onto the PO as enough approval evidence. It is rejected because award approval validates vendor selection and sourcing rationale, while PO approval validates operational purchasing details such as coding, tax, shipping, payment terms, vendor readiness, and issue accuracy.

### 3. Register Purchase Orders As Approval Subjects

This is the selected approach. The PO remains owned by `Domains\PurchaseOrder`, while `Domains\Approval` owns task routing and reviewer actions. A new purchase order approval subject handler maps PO context into approval policies and mutates the PO when approval tasks resolve. This matches existing requisition and award recommendation patterns and leaves P1-38 with a clear `approved` precondition.

## Workflow

### Actors

- Buyer: prepares the PO, marks it ready for review, submits it into approval, monitors the approval state, and revises the PO when changes are requested.
- Admin: can perform buyer actions and view all tenant approval tasks.
- Finance/procurement approver: receives approval tasks, reviews PO details, approves, rejects, requests changes, or delegates where policy allows.
- Requester: can view linked requisition context through existing surfaces, but does not approve POs in this slice unless explicitly assigned by approval policy.
- System: matches approval policy, creates approval tasks, records notifications/audit, and mutates PO state transactionally.

### States

Extend `PurchaseOrderStatus`:

- `draft`: editable preparation state from P1-36.
- `ready_for_review`: prepared by buyer/admin and eligible for approval routing.
- `in_review`: approval instance is active and at least one review task is open.
- `changes_requested`: approver requested PO corrections; buyer/admin can update allowed operational fields and resubmit for approval.
- `approved`: approval instance completed successfully; eligible for P1-38 supplier issue.
- `rejected`: approval instance rejected the PO; terminal for this slice unless a later change-order or rework feature explicitly reopens it.
- `cancelled`: terminal pre-approval cancellation from P1-36.

State rules:

- `ready_for_review` can move to `in_review` through `POST /api/purchase-orders/{purchaseOrder}/submit-approval`.
- `changes_requested` can be updated by buyer/admin and resubmitted to `in_review`.
- `in_review` cannot be edited through PO draft-update endpoints.
- Approval task approval moves the PO to `approved` when the approval instance completes.
- Approval task rejection moves the PO to `rejected`.
- Approval task request-changes moves the PO to `changes_requested`.
- `approved`, `rejected`, and `cancelled` are terminal for P1-37.

### Main Flow

1. Buyer/admin opens a PO that is `ready_for_review`.
2. The workspace shows review readiness, source award context, line totals, operational fields, and a `Submit for approval` action.
3. Buyer/admin submits the PO for approval with `lockVersion`.
4. The API validates the current tenant, authorization, status, required operational fields, line presence, source links, vendor, currency, totals, and lock version.
5. The API uses the existing Approval domain to match a published approval policy with subject type `purchase_order`.
6. The approval instance and tasks are created transactionally.
7. The purchase order moves to `in_review`, stores the approval instance id, increments `lock_version`, and records `purchase_order.approval_submitted`.
8. Assigned approvers see the PO task in the approval queue and task detail view.
9. Approver action through existing approval task endpoints records the task decision and invokes the purchase order subject handler.
10. The subject handler moves the PO to `approved`, `rejected`, or `changes_requested`, writes decision metadata, increments `lock_version`, and records a PO audit event.

### Failure Paths

- No matching published approval policy: return `409` with a message that PO approval routing is not configured.
- Wrong state: return `409` for draft, already in review, approved, rejected, cancelled, or other invalid transitions.
- Stale lock version: return `409`.
- Missing required PO fields or no lines: return `409` with actionable missing-field information.
- Cross-tenant PO, policy, approval task, or related source: deny through tenant-scoped queries and policies.
- Non-buyer/admin submission: return `403`.
- Non-assignee approval task action: use existing approval task authorization behavior.

## Backend Design

### Purchase Order Domain

Add an action `SubmitPurchaseOrderForApproval` in `apps/api/Domains/PurchaseOrder/Actions`. It should:

- Lock the purchase order row by tenant.
- Assert the lock version.
- Allow only `ready_for_review` and `changes_requested`.
- Re-run required field checks from ready-for-review.
- Start an approval workflow through the existing Approval domain service/action.
- Set `status = in_review`, `approval_instance_id`, `approval_submitted_by_user_id`, `approval_submitted_at`, and increment `lock_version`.
- Record `purchase_order.approval_submitted`.

Add migration columns to `purchase_orders` only for PO-owned state:

- `approval_submitted_by_user_id` nullable
- `approval_submitted_at` nullable
- `approved_by_user_id` nullable
- `approved_at` nullable
- `rejected_by_user_id` nullable
- `rejected_at` nullable
- `rejected_reason` nullable
- `changes_requested_by_user_id` nullable
- `changes_requested_at` nullable
- `changes_requested_reason` nullable
- `changes_requested_fields` JSON nullable

The existing `approval_instance_id` column remains the link to the shared Approval domain.

### Approval Domain

Add `PurchaseOrderApprovalSubjectHandler` implementing `ApprovalSubjectHandler`:

- `subjectType()` returns `purchase_order`.
- `modelClass()` returns `PurchaseOrder::class`.
- `buildContext()` exposes amount, currency, vendor, requester, department/cost center/project when available, PO number, source RFQ, source award recommendation, and delivery/payment terms.
- `taskSubjectSummary()` returns a compact PO summary for approval task resources.
- `taskTitle()` returns `Review purchase order {number}`.
- `onRouted()` records approval routing metadata when needed.
- `onApproved()` moves the PO to `approved` when the approval instance is complete.
- `onRejected()` moves the PO to `rejected` with reason.
- `onChangesRequested()` moves the PO to `changes_requested` with reason and requested field labels.

Register the handler in `AppServiceProvider` beside requisition and award recommendation handlers.

Update approval subject type validation, OpenAPI schemas, generated subject metadata, and approval task queue filtering to accept `purchase_order`.

## API Contract

Add or update tenant-scoped authenticated OpenAPI paths:

```txt
POST /api/purchase-orders/{purchaseOrder}/submit-approval
GET  /api/approval-tasks?subjectType=purchase_order
```

Extend generated schemas:

- `PurchaseOrderStatus`: add `in_review`, `changes_requested`, `approved`, `rejected`.
- `PurchaseOrder`: add `approvalInstanceId`, approval timestamps/reasons, and permissions such as `canSubmitForApproval`.
- `SubmitPurchaseOrderApprovalRequest`: `{ lockVersion: number }`.
- `ApprovalPolicySubjectType`: add `purchase_order`.
- `ApprovalTaskSubjectMetadata`: add purchase-order metadata variant.

The web must consume generated request/response types from `@cognify/api-client`.

## Web Design

Use the existing `apps/web/features/purchase-orders` feature group.

Workspace changes:

- Show an approval status panel for `ready_for_review`, `in_review`, `changes_requested`, `approved`, and `rejected`.
- Add `Submit for approval` when `permissions.canSubmitForApproval` is true.
- Show stale-state and conflict errors inline.
- Disable draft field editing while `in_review`, `approved`, `rejected`, or `cancelled`.
- For `changes_requested`, surface reviewer reason and requested fields above the edit controls.

Approval queue changes:

- Include `purchase_order` in approval task subject filters and subject detail rendering.
- Link PO approval tasks to `/purchase-orders/{id}`.
- Show PO number, vendor, amount, currency, and operational review context in the task detail.

No new shared UI primitives are required. Use existing cards, forms, tables, status badges, workflow layouts, and approval task components.

## Audit, Notifications, And Permissions

Audit actions:

- `purchase_order.approval_submitted`
- `purchase_order.approved`
- `purchase_order.rejected`
- `purchase_order.changes_requested`

Permissions:

- Buyers/admins can submit eligible POs for approval and revise `changes_requested` POs.
- Assigned approvers act through existing approval task permissions.
- Admins/buyers can view approval task details under the existing `all` scope.
- Tenant membership and tenant id must be checked on every PO, approval instance, approval task, and source relation query.

Notifications should use the existing approval task notification behavior. P1-37 does not add custom channels.

## Tests And Verification

Backend tests:

- Buyer/admin can submit `ready_for_review` PO for approval and receives an active approval task.
- Submission requires lock version and returns conflict on stale version.
- Submission is denied for draft, in-review, approved, rejected, and cancelled POs.
- Missing approval policy returns a clear conflict.
- Cross-tenant submit and approval task access are denied.
- Approval task approve moves PO to `approved`.
- Approval task reject moves PO to `rejected`.
- Approval task request changes moves PO to `changes_requested`.
- Buyer/admin can revise `changes_requested` PO and resubmit.
- Audit events are recorded for submit and outcomes.

Web tests:

- PO workspace shows submit action for ready PO and routes conflict errors into the page.
- PO workspace hides edit controls while in review and shows outcome panels.
- Changes-requested PO shows reviewer reason and can be revised/resubmitted.
- Approval task list/detail supports purchase order subjects and links back to PO workspace.

Verification commands:

```bash
php artisan test --filter=PurchaseOrderReviewApprovalApiTest
php artisan test --filter=ApprovalTaskApiTest
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- purchase-order
pnpm --filter @cognify/web test -- approval
pnpm --filter @cognify/web typecheck
```

Because this slice changes visible PO and approval task screens, visual inspection against the real API-backed app is required before PR completion.

## Rollout And Roadmap Update

When implementation, verification, visual inspection, CodeRabbit review, PR review, and merge are complete, update `docs/01-product/feature-roadmap.md` row `P1-37` to `Fully Implemented` with this spec path, the implementation plan path, PR number, and a concise note that PO approval reuses shared approval tasks and gates supplier issue on `approved` purchase orders.

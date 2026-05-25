# Award Approval Design

## Status

- Status: Draft for review
- Date: 2026-05-26
- Release scope: P1 Epic 7, slice 5 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-33`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`
  - `docs/superpowers/specs/2026-05-22-quotation-comparison-table-design.md`
  - `docs/superpowers/specs/2026-05-24-vendor-scoring-matrix-design.md`
  - `docs/superpowers/specs/2026-05-25-recommendation-award-decision-design.md`

## Purpose

This slice routes submitted RFQ award recommendations through Cognify's existing Approval domain. Buyers and admins can route a `pending_approval` award recommendation into an approval instance, approvers can decide through the existing approval queue and task screens, and the award recommendation records the approval outcome.

P1-33 approves or rejects the recommendation. It does not mark the RFQ as awarded, notify vendors, create purchase-order handoff records, or export to ERP. Those transitions remain downstream P1-34 and later award lifecycle work.

## Problem

P1-32 creates an auditable recommendation with selected vendor, quotation version, rationale, evidence references, and a `pending_approval` handoff state. Without P1-33, that handoff is not actionable: approvers cannot evaluate the recommendation in their queue, policy rules cannot decide who should review it, and the recommendation cannot reach a formal approved or rejected outcome.

Cognify already has an Approval domain with policies, route building, stages, tasks, delegation, SLA, notifications, and task actions. The current implementation is intentionally polymorphic at the data-model level, but several runtime paths still assume requisitions. P1-33 should make the Approval domain fluid enough to support award recommendations and future approval subjects while keeping subject-specific state transitions in the owning domain.

## Goals

- Reuse `apps/api/Domains/Approval` as the only approval orchestration owner.
- Add RFQ award recommendations as a supported approval subject type.
- Route `pending_approval` recommendations into existing approval policy, instance, stage, task, delegation, SLA, notification, and audit flows.
- Show award-specific context in the approval queue and task detail screens.
- Apply approval decisions back to `RfqAwardRecommendation` state.
- Add approval summary and route/decision visibility to the award recommendation workspace.
- Keep tenant boundaries, generated API contracts, session middleware, and audit behavior consistent with existing approval routes.
- Preserve requisition approval behavior while generalizing approval internals.

## Non-Goals

- Marking an RFQ, quotation, invitation, vendor, requisition, or project as awarded.
- Vendor-facing award or regret notifications.
- Purchase-order request handoff, ERP export, or finance integration.
- Split awards, line-level award approvals, partial awards, or negotiation rounds.
- A new standalone `Award` domain.
- A separate award-only approval task system.
- AI-generated approval recommendations or automated approver decisions.
- Replacing existing requisition approval policies or task UI.

## Design Decision

### Approval Domain Owns Orchestration For Every Subject

P1-33 should not create a parallel award approval engine inside the Quotation domain. The Approval domain must remain the single owner of:

- approval policy matching
- route construction
- approval instances, stages, and tasks
- task actions
- delegation
- SLA and escalation
- approval notifications
- approval queue resource shape

Quotation should own only award-recommendation facts and state transitions. This keeps approval behavior consistent for requisitions, award recommendations, and future approval subjects.

### Generalize Subject Behavior Through A Small Approval Contract

Current approval data is polymorphic in storage through `subject_type` and `subject_id`, but code paths such as `RouteRequisitionForApproval`, `ApprovalContextData::fromRequisition`, `ApprovalTaskResource`, queue filters, and task decision actions still contain requisition-specific behavior.

P1-33 should introduce a subject adapter layer inside `apps/api/Domains/Approval`, for example:

```txt
ApprovalSubjectHandler
  -> subjectType(): string
  -> modelClass(): class-string<Model>
  -> buildContext(Model $subject): ApprovalContextData
  -> taskSubjectSummary(Model $subject): array
  -> taskTitle(Model $subject): string
  -> notificationSubjectLabel(Model $subject): ?string
  -> onRouted(Model $subject, ApprovalInstance $instance, User $actor): void
  -> onApproved(Model $subject, ApprovalInstance $instance, User $actor): void
  -> onRejected(Model $subject, ApprovalInstance $instance, User $actor, string $reason): void
  -> onChangesRequested(Model $subject, ApprovalInstance $instance, User $actor, string $reason, array $requestedFields): void
```

The implementation plan may refine method names and return types, but the architectural requirement is fixed: Approval calls a subject handler at stable extension points, and subject-specific domain actions live behind that handler.

### Keep Award Approval Outcome Separate From Awarding

`approved` means the recommendation was approved by Cognify's approval workflow. It does not mean the vendor is awarded or the procurement process is complete.

This distinction prevents P1-33 from silently starting P1-34 work. P1-34 can later consume approved recommendations and create structured PO handoff records.

## Workflow

### Actors

- Buyer: routes a pending award recommendation, views approval progress, sees decision outcomes.
- Admin: same abilities as buyer for tenant workflows.
- Approver: views and actions assigned award approval tasks.
- Delegated approver: actions delegated active tasks when delegation remains valid.
- Requester: no direct award approval access in this slice unless future policy grants explicit visibility.
- Vendor portal visitor: no access.
- System: matches policy, creates tasks, sends approval notifications, records audit events, and updates recommendation state through subject handlers.

### States

Extend `RfqAwardRecommendationStatus` from P1-32:

- `draft`: editable recommendation.
- `pending_approval`: submitted and ready to route.
- `approval_routed`: active approval instance exists.
- `approved`: approval instance completed approved.
- `rejected`: approval instance completed rejected.
- `changes_requested`: approver requested buyer changes.
- `withdrawn`: buyer/admin withdrew before routing or while still allowed by policy.

State rules:

- Only `pending_approval` recommendations can be routed.
- Routing creates or returns the active approval instance idempotently.
- Routed recommendations cannot be edited through the P1-32 draft save path.
- Approving the final stage sets `approved`.
- Rejecting any active task sets `rejected`.
- Requesting changes sets `changes_requested`.
- `approved` recommendations are read-only for this slice.
- `rejected` and `changes_requested` recommendations are terminal for this slice. Buyer correction and resubmission ergonomics are deferred unless a later plan explicitly adds a new-draft or reopen flow.

## Approval Domain Generalization

### Existing Fluid Pieces

The current Approval schema already supports multiple subjects:

- `approval_instances.subject_type` and `subject_id`
- `approval_tasks.subject_type` and `subject_id`
- `ApprovalInstance::subject()`
- `ApprovalTask::subject()`
- tenant checks in `ApprovalTask::saving()`

These are good foundations and should remain.

### Required Changes

The following areas need Approval-domain changes so award recommendations are not bolted on as special cases:

1. Routing

   Replace or complement `RouteRequisitionForApproval` with a subject-aware routing action, such as `RouteSubjectForApproval`. It should accept a tenant, actor, model subject, and subject handler. `RouteRequisitionForApproval` can become a thin wrapper that passes the requisition handler.

2. Subject context

   Generalize `ApprovalContextData` so it can represent both requisition and award recommendation contexts. The existing fields can remain, but award approval needs additional fields such as:

   - `subjectType`
   - `awardRecommendationId`
   - `rfqId`
   - `rfqNumber`
   - `recommendedVendorId`
   - `recommendedVendorName`
   - `recommendedQuotationId`
   - `recommendedQuotationVersionId`
   - `recommendedAmount`
   - `recommendedCurrency`
   - `scorecardId`
   - `scorecardWeightedTotal`
   - `riskSummaryPresent`
   - `exceptionSummaryPresent`

   Policy matching should support new rule fields without breaking existing requisition policy rules.

3. Policy candidates

   Approval policies already store `subject_type`. Routing must query published versions for `rfq_award_recommendation` instead of hard-coded `requisition`.

4. Task subject summary

   `ApprovalTaskResource` currently assumes requisitions for subject labels, requester, department, cost center, amount, and route links. Move subject summary construction into subject handlers so task resources can return consistent fields:

   ```txt
   subject: {
     type,
     id,
     number,
     title,
     status,
     primaryParty,
     amount,
     currency,
     href,
     metadata
   }
   ```

   Requisition-specific fields can stay in `metadata` or subject-specific detail blocks.

5. Queue filters

   Approval task list filters currently use `whereHasMorph` for requisitions. P1-33 should add `subjectType` filtering and avoid applying requisition-only filters to award tasks unless `subjectType=requisition`. Award-specific filters can be minimal for this slice.

6. Decision side effects

   `ApproveApprovalTask`, `RejectApprovalTask`, and `RequestApprovalChanges` currently call requisition actions when the subject is a `Requisition`. Replace those hard-coded branches with handler callbacks. Requisition behavior stays the same through a requisition handler; award recommendation behavior is added through an award handler.

7. Notifications

   Notification title and body should remain generic enough for all approval subjects, while subject handlers provide labels and links. Award task notifications should link to `/approvals/tasks/{task}` and label the RFQ/recommendation clearly.

8. API resources and OpenAPI

   Generated schemas must represent subject summaries that can carry requisition or award recommendation subjects without frontend type duplication.

### Robustness Requirements

- Subject handlers must validate tenant ownership before routing and before decision callbacks.
- Approval actions must lock the task, instance, stage, and subject where needed to prevent stale decisions.
- Active approval instances must remain unique per subject.
- Re-running route on an already-routed recommendation should return the active instance, not create duplicate tasks.
- Decision callbacks must be idempotent enough to survive retries after transaction rollbacks.
- Requisition approval tests must stay green and should be expanded to prove the subject-handler refactor preserves existing behavior.

## Award Recommendation Domain Changes

Backend ownership remains in `apps/api/Domains/Quotation` for award facts and state.

Required additions:

- Extend `RfqAwardRecommendationStatus`.
- Add nullable approval metadata if not already derivable cleanly from `ApprovalInstance`:
  - `approval_instance_id`, nullable
  - `approved_by_user_id`, nullable
  - `approved_at`, nullable
  - `rejected_by_user_id`, nullable
  - `rejected_at`, nullable
  - `decision_reason`, nullable
  - `changes_requested_by_user_id`, nullable
  - `changes_requested_at`, nullable
  - `changes_requested_reason`, nullable
- Add domain actions:
  - `MarkRfqAwardRecommendationApprovalRouted`
  - `MarkRfqAwardRecommendationApproved`
  - `MarkRfqAwardRecommendationRejected`
  - `RequestRfqAwardRecommendationChanges`
- Add an award approval context builder that loads the RFQ, selected vendor, quotation version, scorecard summary, rationale, risk/exception summaries, and evidence reference counts.

If `approval_instance_id` is not stored on the recommendation, the summary endpoint can find the active or latest instance through `ApprovalInstance.subject_type` and `subject_id`. Storing it improves lookup clarity and should be preferred if the migration blast radius remains small.

## API Contract

Add endpoints:

```txt
POST /api/rfqs/{rfq}/award-recommendation/approval-route
GET /api/rfqs/{rfq}/award-recommendation/approval-summary
GET /api/rfqs/{rfq}/award-recommendation/approval-preview
```

Endpoint behavior:

- `approval-route` requires buyer/admin and `pending_approval`.
- `approval-route` returns `ApprovalSummary` or an award-specific route response containing the created/active `ApprovalInstance`.
- `approval-summary` returns the active/latest approval summary for the recommendation or `null`.
- `approval-preview` uses the approval policy matcher for `rfq_award_recommendation` without creating tasks.

Update existing approval endpoints:

- `GET /api/approval-tasks`
  - support `subjectType`
  - return subject summaries for requisition and award recommendation tasks
- `GET /api/approval-tasks/{task}`
  - include award-specific subject context when the task subject is an award recommendation
- `POST /api/approval-tasks/{task}/approve`
- `POST /api/approval-tasks/{task}/reject`
- `POST /api/approval-tasks/{task}/request-changes`
  - preserve existing request shapes
  - call subject handlers for completion side effects

OpenAPI and generated client updates are required.

## Web UX

### Award Recommendation Workspace

The existing `/quotations/awards/[rfqId]` workspace should show:

- approval status summary
- matched policy and current stage when routed
- active approvers and due date
- link to current user's approval task when applicable
- route-for-approval action for buyer/admin when status is `pending_approval`
- approved, rejected, and changes-requested outcomes

The workspace should not show vendor award or PO handoff controls.

### Approval Queue

`/approvals` should support award tasks in the existing table:

- subject label displays RFQ/recommendation context
- subject type is visible or filterable
- existing scopes still work: assigned to me, overdue, due soon, completed by me, all
- requisition-only columns should be made generic or moved into metadata display

### Approval Task Detail

`/approvals/tasks/[taskId]` should render a subject-aware detail panel.

For award recommendations, show:

- RFQ number and title
- recommended vendor
- quotation version and amount
- rationale
- tradeoff summary
- risk summary
- exception summary
- scorecard weighted total and completion state
- evidence references with links back to comparison, scoring, or quotation artifacts
- link to award recommendation workspace

Use the existing approval action dialogs for approve, reject, request changes, and delegation.

## Permissions

- Buyer/admin can route a `pending_approval` recommendation.
- Buyer/admin can view approval progress for tenant recommendations.
- Assigned approver or valid delegate can approve, reject, or request changes.
- Buyer/admin can view tasks but cannot action tasks unless assigned.
- Requester does not gain access by default.
- Vendor users never access approval tasks.
- All routes require session auth and tenant resolution.

## Audit And Notifications

Audit events:

- `rfq_award_recommendation.approval_routed`
- `rfq_award_recommendation.approved`
- `rfq_award_recommendation.rejected`
- `rfq_award_recommendation.changes_requested`
- existing `approval_instance.routed`
- existing `approval_task.approved`
- existing `approval_task.rejected`
- existing `approval_task.changes_requested`

Notifications:

- Reuse existing approval task assignment notification type.
- Subject label and notification body should come from the subject handler.
- No vendor-facing notifications in this slice.

## Data And Tenant Rules

- Recommendation, RFQ, quotation, vendor, scorecard, approval instance, stages, and tasks must share tenant id.
- Evidence references remain owned by the recommendation and same RFQ.
- Approver assignment must remain limited to users in the tenant.
- Cross-tenant recommendations must not be routeable, viewable, or actionable.
- Approval task subject validation must continue to reject non-model or cross-tenant subjects.

## Mock And Demo Data

MSW fixtures should include:

- pending recommendation that can be routed
- routed recommendation with active stage
- approved recommendation
- rejected recommendation
- changes-requested recommendation
- approval task for an award recommendation
- no matching award approval policy
- missing approver fallback path

Backend demo seeding is optional for this slice. If added, it should seed a complete RFQ recommendation and a published `rfq_award_recommendation` policy without mutating vendor award state.

## Testing

API tests:

- buyer/admin can route pending award recommendation for approval
- route is idempotent when an active instance already exists
- non-pending recommendations cannot be routed
- missing award approval policy returns the existing approval matching error shape
- approve final award task marks recommendation `approved`
- reject active award task marks recommendation `rejected`
- request changes marks recommendation `changes_requested`
- requisition approval behavior still passes after subject-handler refactor
- cross-tenant route, summary, task show, and task action attempts fail
- audit events and approval assignment notifications are recorded
- generated OpenAPI contract includes award subject summaries

Web tests:

- award workspace renders approval summary and route action
- route action moves the workspace into routed state
- approval queue lists award tasks with generic subject display
- approval task detail shows award context
- approve, reject, and request-changes flows reuse existing dialogs
- permission-denied, no-policy, stale task, and cross-tenant style errors render correctly

Verification commands:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=Approval
cd apps/api && php artisan test --filter=RfqAwardRecommendationApiTest
pnpm --filter @cognify/web exec vitest run features/approvals/tests
pnpm --filter @cognify/web exec vitest run features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
pnpm lint
pnpm typecheck
pnpm build
git diff --check
```

## Rollout Boundary

This slice is complete when a buyer/admin can route a submitted award recommendation into the existing approval queue, an assigned approver can decide it, and the recommendation records the approval outcome. It remains intentionally incomplete for operational award execution: approved recommendations do not yet create PO handoffs, award records, vendor communications, or ERP exports.

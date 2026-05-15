# Requisition Submission And Workspace Design

Date: 2026-05-15
Status: Draft for review
Source epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 2
Related roadmap: `docs/01-product/feature-roadmap.md` P1 Core Procurement Lifecycle
Architecture alignment: `ARCHITECTURE.md`
Runbook alignment: `docs/05-runbooks/feature-development.md`

## 1. Purpose

This spec defines the P1 Epic 2 design for Requisition Submission And Workspace. The goal is to turn the existing requisition draft and submission foundation into a durable submitted-record workspace where requesters, buyers, approvers, and admins can collaborate, request corrections, stop invalid work, and operate from a role-aware requisition queue.

The design builds on the current Cognify foundation instead of restarting it. The repository already has tenant-scoped requisition draft create/update endpoints, basic `draft -> submitted` behavior, list/detail routes, activity timeline integration, attachments, in-app notification infrastructure, generated API client usage, and MSW-backed web flows. Epic 2 should harden and extend that baseline without implementing approval routing, buyer intake, RFQ creation, quotation capture, or award workflows.

## 2. Explicit Scope

### 2.1 In Scope

- Submission workflow hardening around `draft -> submitted`.
- Role-aware requisition list and work queue filters on the existing requisition list route.
- Requisition detail workspace improvements for submitted, change-requested, withdrawn, and cancelled records.
- Requisition lifecycle states:
  - `draft`
  - `submitted`
  - `changes_requested`
  - `withdrawn`
  - `cancelled`
- Buyer/approver change request flow with reason and requested fields.
- Requester correction and resubmission from `changes_requested`.
- Requester withdrawal with required reason where policy allows.
- Admin cancellation with required reason where policy allows.
- Immutable terminal behavior for `withdrawn` and `cancelled`.
- Generic Collaboration domain for comments and mentions, wired only to requisitions in this epic.
- Mention validation that limits suggestions and saved mentions to users who already have permission to view the requisition.
- Audit and notification hooks for lifecycle and collaboration events.
- OpenAPI and generated client updates for every API contract change.
- Contract-faithful MSW behavior for the new frontend workflow.

### 2.2 Out Of Scope

- Approval routing rules, approval tasks, approval SLA, delegation, escalation, policy preview, or activation of `pending_approval`.
- Buyer intake review, RFQ creation, vendor invitations, quotation capture, award decisions, or purchase order handoff.
- A separate generic task queue or work item service.
- A single aggregate requisition workspace endpoint.
- Reopening `withdrawn` or `cancelled` requisitions.
- Comment support on projects, RFQs, quotations, awards, vendors, approvals, or evidence, even though the Collaboration domain should be designed to support those subjects later.
- Moving Cognify-specific requisition UI or collaboration semantics into `packages/ui`.

## 3. Runbook Alignment

This epic follows the feature-development runbook order:

```txt
Business workflow
  -> Workspace UX
  -> API contract
  -> Mocked frontend workflow
  -> Backend domain behavior
  -> Real API integration
  -> Hardening and observability
```

Implementation should proceed as vertical workflow slices. Each slice should define the business transition, workspace UX, OpenAPI contract, MSW behavior, backend domain action, generated-client integration, and verification evidence before it is considered complete.

## 4. Architecture Alignment

This design preserves the Cognify architecture boundaries:

- `apps/web` owns requisition workflow UI, queue composition, hooks, MSW handlers, tests, and product-specific state handling.
- `apps/api/Domains/Requisition` owns requisition lifecycle state, policies, transition actions, resources, filters, and domain validation.
- `apps/api/Domains/Collaboration` owns reusable comments and mentions for commentable procurement records.
- `apps/api/app/Audit` remains the immutable audit/event infrastructure.
- `apps/api/app/Notifications` remains the notification recording and preference infrastructure.
- `packages/api-client` remains generated from OpenAPI plus stable typed helpers.
- `packages/ui` remains reusable shadcn/Radix primitives only.
- No new shared package is introduced.

OpenAPI remains the frontend/backend contract. Any API change in this epic must update `apps/api/storage/openapi/openapi.json`, regenerate `packages/api-client`, and consume generated endpoints or schemas in the web app rather than duplicating response types by hand.

## 5. Business Workflow

### 5.1 Actors

| Actor | Description | Epic 2 responsibilities |
| --- | --- | --- |
| Requester | User who creates a procurement requisition. | Submit complete drafts, correct change-requested requisitions, resubmit, withdraw where allowed, comment, and mention visible users. |
| Buyer | Procurement user who reviews submitted requisitions. | Use the work queue, review submitted records, request changes, comment, mention visible users, and prepare for later intake/RFQ work. |
| Approver | User who may later approve spend requests. | View submitted requisitions and request changes where policy allows; approval task lifecycle remains out of scope. |
| Admin | Tenant administrator. | View tenant requisitions and cancel requisitions with reason where policy allows. |
| Mentioned user | Existing visible collaborator on the requisition. | Receive deep-linked in-app mention notifications. |
| System | Internal workflow actor. | Enforce states, permissions, tenant boundaries, audit events, notifications, and conflict handling. |

### 5.2 Durable States

| State | Meaning | Allowed actions |
| --- | --- | --- |
| `draft` | Requester is still authoring the requisition. | Update, submit, withdraw where allowed, comment where allowed. |
| `submitted` | Requester has submitted the requisition into the formal procurement workflow. | View, comment, request changes, withdraw where allowed, cancel where allowed. Requester field edits are locked. |
| `changes_requested` | Buyer or approver has returned the requisition for correction. | Requester can edit all draft fields and resubmit. Visible users can comment. Admin can cancel. |
| `withdrawn` | Requester has stopped the requisition. | Read-only terminal record. |
| `cancelled` | Admin has stopped the requisition. | Read-only terminal record. |
| `pending_approval` | Reserved for Approval Orchestration. | No active Epic 2 transition. |

### 5.3 Transitions

| Transition | Trigger | Guardrails | Audit event | Notification |
| --- | --- | --- | --- | --- |
| `draft -> submitted` | Requester submits. | Required fields valid, at least one line item, requester/admin permission, tenant ownership. | `requisition.submitted` | Buyers/admins, excluding actor. |
| `submitted -> changes_requested` | Buyer or approver requests changes. | Reason required, requested fields optional but structured, actor must view and request changes. | `requisition.changes_requested` | Requester. |
| `changes_requested -> submitted` | Requester resubmits after correction. | Required fields revalidated, requester/admin permission, state is `changes_requested`. | `requisition.resubmitted` | Buyers/admins or the user who requested changes. |
| `draft|submitted|changes_requested -> withdrawn` | Requester withdraws. | Reason required, requester/admin permission, state is not terminal. | `requisition.withdrawn` | Buyers/admins if previously submitted. |
| `submitted|changes_requested -> cancelled` | Admin cancels. | Reason required, admin permission, state is not terminal. | `requisition.cancelled` | Requester and visible collaborators as appropriate. |

Terminal states do not transition in Epic 2. A future copy-from-record convenience may let a requester create a new draft from a stopped requisition, but the original record remains immutable.

### 5.4 Correction Rules

`changes_requested` behaves like a correction state, not a new draft record. The requester can edit all draft fields before resubmitting, even when the buyer or approver listed specific requested fields. The requested fields and reason guide the requester, but they do not create field-level edit locks. This avoids blocking legitimate corrections where one requested change affects line items, totals, justification, needed-by date, or organizational metadata together.

Resubmission preserves the requisition ID, number, attachments, comments, and activity history.

## 6. Backend Domain Design

### 6.1 Requisition Domain

`apps/api/Domains/Requisition` remains the owner of requisition lifecycle behavior.

Expected additions:

- Extend `RequisitionStatus` with:
  - `changes_requested`
  - `withdrawn`
  - `cancelled`
- Add transition actions:
  - `RequestRequisitionChanges`
  - `ResubmitRequisition`
  - `WithdrawRequisition`
  - `CancelRequisition`
- Extend `RequisitionPolicy` with explicit checks for:
  - request changes
  - resubmit
  - withdraw
  - cancel
  - comment
  - mention
- Extend `RequisitionResource.permissions` with action-specific flags:
  - `canUpdate`
  - `canSubmit`
  - `canResubmit`
  - `canRequestChanges`
  - `canWithdraw`
  - `canCancel`
  - `canComment`
  - `canMention`
  - `canViewActivity`
- Extend list filtering on `GET /api/requisitions` without creating a separate queue endpoint.

Controllers should stay thin. Durable workflow logic belongs in domain actions and policies, not controllers, routes, jobs, or Eloquent model callbacks.

### 6.2 Collaboration Domain

Create `apps/api/Domains/Collaboration` as a generic collaboration domain for comments and mentions.

Epic 2 should support only requisition subjects through requisition-specific routes, but the data model should avoid requisition-only names so future domains do not reinvent comments and mentions.

Expected model:

`collaboration_comments`

- `id`
- `tenant_id`
- `subject_type`
- `subject_id`
- `author_id`
- `body`
- timestamps
- optional soft-delete metadata only if moderation/delete behavior is included in the implementation plan

`collaboration_mentions`

- `id`
- `tenant_id`
- `comment_id`
- `mentioned_user_id`
- notification metadata where useful
- timestamps

Subject support in Epic 2:

- Valid exposed subject type: `requisition`.
- Mention candidates: users who already have permission to view the requisition.
- Mentions never grant access to the requisition.
- Comment creation is denied if the user cannot view or comment on the requisition.

### 6.3 Audit And Notifications

Use existing cross-cutting infrastructure.

Audit events:

- `requisition.changes_requested`
- `requisition.resubmitted`
- `requisition.withdrawn`
- `requisition.cancelled`
- `collaboration.comment_created`
- `collaboration.mentioned`

Notification events:

- `requisition.changes_requested`
- `requisition.resubmitted`
- `requisition.withdrawn`
- `requisition.cancelled`
- `collaboration.mentioned`

Notification preferences remain in-app only. Do not add email, digest, Slack, Teams, push, vendor portal, or scheduled notifications in this epic.

## 7. API Contract Design

### 7.1 Existing Endpoints Preserved

Preserve existing routes and behavior where possible:

- `GET /api/requisitions`
- `POST /api/requisitions`
- `GET /api/requisitions/{requisitionId}`
- `PATCH /api/requisitions/{requisitionId}`
- `POST /api/requisitions/{requisitionId}/submit`
- `GET /api/requisitions/{requisitionId}/activity`
- `GET /api/requisitions/{requisitionId}/attachments`
- `POST /api/requisitions/{requisitionId}/attachments`

### 7.2 New Requisition Action Endpoints

Add action-style subresources:

- `POST /api/requisitions/{requisitionId}/request-changes`
- `POST /api/requisitions/{requisitionId}/resubmit`
- `POST /api/requisitions/{requisitionId}/withdraw`
- `POST /api/requisitions/{requisitionId}/cancel`

Request bodies:

- `request-changes`: reason required, requested fields optional.
- `resubmit`: may be empty or include optimistic state metadata if needed by implementation.
- `withdraw`: reason required.
- `cancel`: reason required.

Responses use existing conventions:

- Success returns `{ data: Requisition }`.
- Validation errors use the standard validation error envelope.
- Invalid transitions return conflict-shaped API errors.
- Authorization failures and cross-tenant access do not leak inaccessible record details.

### 7.3 Requisition List Filters

Extend `GET /api/requisitions` as the role-aware queue surface. Do not add a separate queue endpoint in Epic 2.

Supported filters should include:

- search
- status
- requester
- owner, if ownership is modeled in the existing requisition record or added narrowly
- department
- needed-by date range
- amount range
- updated date range
- page
- perPage
- queue preset

Queue presets are server-understood shortcuts for common role-aware views, not a separate task system.

### 7.4 Collaboration Endpoints

Add requisition-scoped collaboration routes:

- `GET /api/requisitions/{requisitionId}/comments`
- `POST /api/requisitions/{requisitionId}/comments`

The OpenAPI schemas should use generic collaboration names where practical:

- `CollaborationComment`
- `CollaborationMention`
- `CreateCollaborationCommentRequest`
- `CollaborationCommentListResponse`

The exposed route remains requisition-scoped to keep Epic 2 narrow.

## 8. Web UX Design

### 8.1 Work Queue

Extend the existing `/requisitions` page.

Queue presets:

- My drafts
- Submitted
- Needs my correction
- Buyer review
- Stopped
- All visible

Filters:

- status
- requester
- owner where supported
- department
- needed-by date range
- amount range
- updated date range
- search

UX rules:

- Desktop keeps a dense table with stable columns and permission-driven row actions.
- Mobile keeps compact list rows with essential metadata and clear action entry points.
- Filter state should be URL/query-backed where practical so queue views are refresh-safe and shareable.
- Empty, filtered-empty, loading, error, and permission-limited states must be explicit.

### 8.2 Detail Workspace

Preserve `/requisitions/{requisitionId}` as the durable source of truth.

Sections:

- Overview
- Line items
- Evidence
- Comments
- Activity
- Approval readiness summary
- Quotation readiness summary

Activity and comments remain distinct:

- Activity shows audit/system history.
- Comments show human collaboration.

The approval and quotation readiness sections should summarize current record completeness and clearly label later workflow behavior as deferred. Do not create empty pages for future approval, intake, RFQ, quotation, or award workflows.

### 8.3 Workflow Actions

Action availability comes from generated API data and backend policy flags.

- `Submit`: shown for valid draft submitters.
- `Request changes`: shown for authorized buyer/approver users on submitted requisitions.
- `Resubmit`: shown for requester on `changes_requested`.
- `Withdraw`: shown for requester where policy allows.
- `Cancel`: shown for admin where policy allows.

Workflow-changing actions use confirmation dialogs. `Request changes`, `withdraw`, and `cancel` require reason capture. Conflict, validation, and permission errors should be recoverable without losing local user input.

### 8.4 Correction Flow

When status is `changes_requested`, the requester sees a prominent correction panel with:

- reason
- requested fields
- requester-facing guidance
- actor who requested changes
- request timestamp

The requester can edit all draft fields. The existing `/requisitions/{requisitionId}/edit` route should load the existing requisition and pass it into the form instead of behaving like a new requisition form.

After successful resubmission, the UI returns to the detail workspace, refreshes the requisition, and updates activity/comments/notification-derived state.

### 8.5 Comments And Mentions

Add a comments section to the requisition detail workspace:

- chronological comments
- create comment form
- mention suggestions for visible users only
- validation and permission errors
- empty/loading/error states

Mentioned users receive in-app notifications with deep links to the requisition workspace. Comments should not grant access and should not import mock fixtures directly into production components.

## 9. Mocked Frontend Workflow

MSW should be updated before backend integration hardens.

MSW handlers should cover:

- new requisition states
- role-aware queue presets and filters
- request changes
- resubmit
- withdraw
- cancel
- comments list/create
- mention validation
- conflict errors for invalid transitions
- standard `ApiError` envelopes for new endpoints

Existing requisition MSW handlers should be tightened where the new work depends on contract behavior, especially pagination, filters, and error shapes.

## 10. Testing Strategy

### 10.1 Backend Tests

Required coverage:

- Requester can submit a valid draft and cannot edit after submission.
- Buyer/approver can request changes on submitted requisitions where policy allows.
- Requester can edit all fields in `changes_requested` and resubmit.
- Requester can withdraw allowed requisitions with reason.
- Admin can cancel allowed requisitions with reason.
- Terminal states reject submit, resubmit, request changes, withdraw, cancel, and edit attempts as appropriate.
- Cross-tenant users cannot view, act on, comment on, mention, withdraw, or cancel requisitions.
- Mention candidates exclude users without subject visibility.
- Comments require subject visibility and tenant membership.
- Audit events are recorded for lifecycle and collaboration events.
- Notifications are recorded for change requests, resubmissions, terminal transitions, and mentions.

### 10.2 Frontend Tests

Required coverage:

- Work queue presets and filters render correct states.
- Detail workspace shows correct actions by permission and status.
- Change request panel guides correction and resubmission.
- Existing edit route loads the existing requisition.
- Comments can be created.
- Mention suggestions only show visible users.
- Withdraw and cancel dialogs require reasons and handle conflicts.
- MSW uses standard API error envelopes for new endpoints.

### 10.3 Verification Commands

The implementation plan should refine these commands based on touched files:

```bash
php artisan test --filter=Requisition
php artisan test --filter=Notification
php artisan test --filter=Audit
pnpm check:api-contract
pnpm --filter @cognify/web test -- requisitions
```

If collaboration tests are split into a new backend test class, add a focused Collaboration test command to the plan.

## 11. Delivery Risks

- Collaboration domain can sprawl. Epic 2 should expose only requisition routes even if the model is generic.
- Approval overlap is likely. Do not build approval task lifecycle, SLA, delegation, escalation, or active `pending_approval` transitions.
- Queue behavior can become a task system. Keep Epic 2 as a filtered requisition list.
- Comments and mentions can accidentally grant access. Mention validation must be based on existing subject visibility.
- MSW contract drift already exists in some handlers. New Epic 2 handlers should follow the OpenAPI error envelope and list metadata conventions.
- Terminal states must be genuinely immutable or later workflows will need to reason about reopened stopped records.

## 12. Acceptance Criteria

- A requester can submit a complete requisition draft and see it become a read-only submitted record.
- Buyers and approvers can find submitted requisitions through role-aware queue presets and filters.
- Buyers or approvers can request changes with a reason and requested fields.
- A requester can correct any draft field on a change-requested requisition and resubmit it.
- A requester can withdraw an allowed requisition with a reason.
- An admin can cancel an allowed requisition with a reason.
- Withdrawn and cancelled requisitions remain visible but read-only.
- Users can comment on requisitions they are allowed to view.
- Users can mention only users who already have permission to view the requisition.
- Lifecycle and collaboration events appear in activity/audit where appropriate.
- In-app notifications deep-link users to relevant requisition records.
- OpenAPI, generated client, MSW handlers, backend tests, and frontend tests agree on request/response shapes.

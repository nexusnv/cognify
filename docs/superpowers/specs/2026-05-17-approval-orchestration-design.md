# Approval Orchestration Design

Date: 2026-05-17
Status: Draft for review
Source epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 4
Related roadmap: `docs/01-product/feature-roadmap.md` P1 Core Procurement Lifecycle
Architecture alignment: `ARCHITECTURE.md`
Runbook alignment: `docs/05-runbooks/feature-development.md`

## 1. Purpose

This spec defines the P1 Epic 4 design for Approval Orchestration. The goal is to turn submitted requisitions into governed approval work by adding configurable routing policy, policy preview, actionable approval tasks, ordered and concurrent approval stages, delegation, escalation, and SLA visibility.

Approval Orchestration is the first P1 epic where Cognify starts making workflow governance decisions beyond requisition authoring and collaboration. It must therefore be explicit about policy versions, task state, tenant boundaries, permissions, audit events, notification side effects, and downstream handoff to buyer intake.

The epic should stay focused on requisition approval. The approval model should be reusable enough to support award approval in Epic 7 later, but this epic should not implement award records, RFQs, quotation evaluation, purchase order handoff, or a generic workflow engine.

## 2. Roadmap Features Included

From `docs/01-product/feature-roadmap.md`, this epic includes exactly these P1 capabilities:

- Approval Routing Rules
- Approval Tasks
- Sequential Approval Chains
- Parallel Approval Groups
- Approval Delegation
- Approval Escalation
- Approval SLA Tracking
- Approval Policy Preview

The implementation should follow the seven slices named in `docs/02-release-management/2026-05-15-P1-Epics.md`:

1. Routing rule model and versioning.
2. Policy preview.
3. Approval task lifecycle.
4. Sequential chains.
5. Parallel groups.
6. Delegation.
7. Escalation and SLA tracking.

Adjacent roadmap capabilities are only integration surfaces:

- Requisition Submission And Workspace supplies submitted requisitions and receives approval summary/status sections.
- Procurement Project Workspace can display read-only approval summaries for linked requisitions or project-level context.
- Buyer Intake And RFQ Management should consume approved or approval-ready requisitions after this epic, but should not be implemented here.
- Award Approval in Epic 7 should reuse the approval domain concepts later, but no award approval route or data model is in this epic.

## 3. Explicit Scope

### 3.1 In Scope

- Tenant-scoped approval policy definitions for requisition approval.
- Versioned approval policy snapshots used when routing a specific requisition.
- Rule conditions based on available requisition fields:
  - amount
  - department
  - category
  - cost center
  - project
  - requester
  - risk classification placeholder when a prior workflow has already stored a value
  - vendor reference placeholder when a future sourcing or award workflow has already stored a value
- Policy preview before submission or routing for requesters, buyers, and admins where permissions allow.
- Approval instances created from a submitted requisition and policy version.
- Approval stages that support sequential execution.
- Approval groups that support parallel approver tasks in one stage.
- Approval task lifecycle:
  - assigned
  - viewed
  - approved
  - rejected
  - changes requested
  - delegated
  - escalated
  - cancelled by parent workflow termination
- Approver queue and approval detail surfaces under `apps/web/features/approvals`.
- Requisition detail approval summary and approval action entry points for authorized approvers.
- Approver actions from both the approval queue and requisition detail workspace.
- Delegation records with temporary or standing delegation within tenant and policy limits.
- Escalation rules for overdue tasks and fallback approvers.
- SLA due dates, overdue indicators, stage aging, and approval aging metrics.
- Audit events and activity timeline entries for policy, task, delegation, escalation, and requisition approval transitions.
- In-app notification hooks for assigned, delegated, overdue, escalated, approved, rejected, and changes-requested approval events.
- OpenAPI contract updates and regenerated `@cognify/api-client` usage.
- Contract-shaped MSW handlers for frontend workflow tests.
- Focused backend tests for tenant isolation, authorization, policy matching, state transitions, and stale/concurrent action conflicts.
- Critical-path browser coverage for preview, route, approve, reject/request changes, delegation, and overdue visibility.

### 3.2 Out Of Scope

- A fully generic workflow/task engine detached from procurement approval use cases.
- Award approval execution, award records, recommendation approvals, or vendor selection approval.
- Buyer intake queue implementation, RFQ creation, vendor invitations, quotation capture, evaluation, scoring, or PO handoff.
- Budget reservation, ERP posting, finance commitment accounting, or spend ledger behavior.
- Org chart management, HRIS integration, identity provisioning, or automatic manager hierarchy sync.
- Email, Slack, Teams, push, SMS, digest notifications, or realtime broadcasting beyond the existing in-app notification foundation.
- Complex policy authoring UI such as drag-and-drop workflow builders, expression editors, nested boolean builders, policy simulations across historical data, or import/export of policy rules.
- AI-generated approval decisions or automatic approval without human action.
- Reopening rejected or cancelled approval instances without a separate future correction workflow.
- Moving approval-specific UI, policy language, approval status badges, or task copy into `packages/ui`.

## 4. Runbook Alignment

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

The seven implementation slices should remain vertical. Each slice is complete only when workflow behavior, workspace UX, OpenAPI contract, MSW behavior, backend domain behavior, generated-client integration, audit/notification side effects, and focused verification are represented.

The first slice should define the durable approval domain model and contract boundaries even if later slices fill in sequential, parallel, delegation, and SLA behavior. This avoids building a policy preview shape that cannot route real tasks later.

## 5. Architecture Alignment

This design preserves Cognify's established boundaries:

- `apps/web` owns approval routes, approval queue/workspace composition, feature hooks, MSW handlers, product-specific approval components, and tests.
- `apps/web/features/approvals` owns approval queue, approval detail, policy preview, approval action dialogs, delegation UI, and SLA surfaces.
- `apps/web/features/requisitions` owns requisition detail integration points such as approval summary panels and approval action entry points.
- `apps/api/Domains/Approval` owns approval policies, policy versions, routing, approval instances, stages, tasks, delegation, escalation, SLA rules, policies, resources, queries, actions, services, jobs, events, and tests.
- `apps/api/Domains/Requisition` owns requisition lifecycle states and must expose narrow integration actions for routing submitted requisitions, moving approved requisitions forward, and applying changes-requested or rejected outcomes.
- `apps/api/Domains/Project` may display approval summaries through generated contracts but should not own approval state.
- `apps/api/app/Audit` remains the immutable audit/event infrastructure.
- `apps/api/app/Notifications` records in-app workflow notifications from explicit approval events and respects preferences.
- `packages/api-client` remains generated from OpenAPI plus stable typed helpers.
- `packages/ui` remains reusable shadcn/Radix primitives only.
- No new shared package is introduced.

OpenAPI remains the frontend/backend contract. Any API change in this epic must update `apps/api/storage/openapi/openapi.json`, regenerate `packages/api-client`, and consume generated endpoints or schemas in the web app instead of duplicating contract response types by hand.

Backend controllers must stay thin. Approval matching, task state changes, delegation, escalation, SLA calculations, audit recording, and requisition outcome transitions belong in domain actions/services/workflows, not controllers, jobs, routes, or Eloquent model callbacks.

## 6. Business Workflow

### 6.1 Actors

| Actor | Description | Epic 4 responsibilities |
| --- | --- | --- |
| Requester | User who created the requisition. | Preview approval path, track approval progress, respond to requested changes, receive final approval/rejection visibility. |
| Approver | User assigned approval work. | View assigned tasks, approve, reject, request changes, comment, delegate where allowed, and understand SLA state. |
| Buyer | Procurement user monitoring submitted requisitions. | Preview approval path, monitor approval status, see bottlenecks, and continue intake only when approval state allows. |
| Admin | Tenant administrator. | Configure approval policies, manage policy versions, view all tenant approval work, configure fallback approvers and SLA rules. |
| Delegate | User receiving delegated approval authority. | Act on delegated approval tasks within scope and time limits. |
| Fallback approver | User assigned by escalation policy when tasks are overdue. | Receive escalated approval work and act according to escalation rules. |
| System | Internal workflow actor. | Match policy, create instances/tasks, enforce state transitions, record audit, send notifications, run escalation/SLA jobs, and preserve tenant boundaries. |

### 6.2 Requisition Approval States

Approval Orchestration activates the `pending_approval` state that Epic 2 reserved.

| Requisition state | Meaning in this epic | Allowed approval behavior |
| --- | --- | --- |
| `draft` | Requester is authoring the requisition. | Policy preview may run if enough fields exist. No approval instance exists. |
| `submitted` | Requisition has entered formal workflow but is not yet routed, or routing is being prepared. | Policy preview and route action may create an approval instance. |
| `pending_approval` | Requisition is actively routed through approval stages. | Approval tasks can be acted on by assigned users or valid delegates. |
| `changes_requested` | An approver returned the requisition for correction. | Active approval instance is paused or closed as changes requested. Requester can correct and resubmit through requisition workflow. |
| `approved` | Approval instance completed successfully. | Buyer intake and later sourcing workflows may treat the requisition as approved. |
| `rejected` | An approver rejected the requisition. | Record is read-only for approval actions unless a future appeal/reopen workflow is designed. |
| `withdrawn` | Requester stopped the requisition. | Active approval instance/tasks are cancelled by parent termination. |
| `cancelled` | Admin stopped the requisition. | Active approval instance/tasks are cancelled by parent termination. |

If the current codebase has not yet introduced `approved` and `rejected` requisition states, this epic should add them explicitly as approval outcomes. They must be terminal for normal approval behavior until a later correction or appeal workflow is designed.

### 6.3 Approval Instance States

| State | Meaning | Allowed actions |
| --- | --- | --- |
| `previewed` | Non-durable or short-lived computed route shown before routing. | No task actions. Can be recomputed. |
| `pending` | Durable approval instance exists and has active or blocked stages. | Stage/task actions, delegation, escalation, cancellation by parent workflow. |
| `approved` | All required stages/groups completed successfully. | Read-only. Requisition outcome moves to `approved`. |
| `rejected` | A required approver rejected the request. | Read-only. Requisition outcome moves to `rejected`. |
| `changes_requested` | A required approver requested changes. | Read-only or paused based on implementation detail, but no task actions until resubmission reroutes. Requisition moves to `changes_requested`. |
| `cancelled` | Parent requisition was withdrawn/cancelled or superseded by resubmission. | Read-only. Open tasks cancelled. |

### 6.4 Approval Stage And Task States

Approval stages represent ordered route steps. A stage can contain one task or a parallel group of tasks.

| Stage state | Meaning |
| --- | --- |
| `blocked` | A prior sequential stage is not complete. |
| `active` | Tasks in this stage are actionable. |
| `completed` | Completion rule for the stage has been met. |
| `skipped` | Stage was not required after final policy evaluation. |
| `cancelled` | Parent approval instance ended before this stage completed. |

| Task state | Meaning |
| --- | --- |
| `assigned` | Task is assigned and actionable by the assignee or valid delegate. |
| `viewed` | Assignee opened the task; action is still required. |
| `approved` | Task was approved. |
| `rejected` | Task was rejected and parent instance moves to rejected. |
| `changes_requested` | Task requested changes and parent instance moves to changes requested. |
| `delegated` | Task authority moved to a delegate or delegate task. |
| `escalated` | Task was escalated to fallback approver or escalation path. |
| `cancelled` | Parent workflow ended or task was superseded. |

Task terminal states should be immutable. Corrections should create new approval instances or new tasks with audit links rather than rewriting prior decisions.

### 6.5 Core Transitions

| Transition | Trigger | Guardrails | Audit event | Notification |
| --- | --- | --- | --- | --- |
| preview policy | Requester, buyer, or admin previews route. | Actor can view or submit the requisition; tenant ownership; policy conditions use visible fields only. | Optional `approval.policy_previewed` if durable preview logging is needed. | None by default. |
| configure policy draft | Admin creates or edits policy. | Admin permission; valid condition/action shape; approvers/fallbacks are same-tenant users or valid role resolvers. | `approval_policy.draft_saved` | None. |
| publish policy version | Admin publishes active policy. | Valid version, no invalid approver references, effective date not conflicting with active version. | `approval_policy.published` | Optional admin notification only if useful. |
| route requisition | Submitted requisition is routed. | Requisition is submitted; actor/system has route permission; matching policy version exists or fallback no-approval rule applies. | `approval_instance.created`, `requisition.pending_approval` | First active approvers. |
| activate next stage | Prior stage completes. | Sequential order, parent instance pending, stage not terminal. | `approval_stage.activated` | New stage approvers. |
| approve task | Assigned approver or delegate approves. | Task active; actor authorized; optimistic lock/version matches; comment optional based on policy. | `approval_task.approved` | Requester/buyer on final approval; next approvers when stage advances. |
| reject task | Assigned approver or delegate rejects. | Reason required; task active; actor authorized; lock/version matches. | `approval_task.rejected`, `requisition.rejected` | Requester, buyer, admins as configured. |
| request changes | Assigned approver or delegate requests changes. | Reason required; requested fields optional but structured; task active; actor authorized. | `approval_task.changes_requested`, `requisition.changes_requested` | Requester. |
| delegate task | Assignee delegates task. | Delegate is same tenant, can approve under policy, not the requester when policy forbids self-approval, within effective dates. | `approval_task.delegated` | Delegate and original assignee. |
| escalate task | SLA job or admin-triggered escalation. | Task overdue; fallback approver exists or escalation policy defines target; idempotent job. | `approval_task.escalated` | Fallback approver, original approver, buyer/admin as configured. |
| cancel approval | Parent requisition is withdrawn/cancelled or rerouted. | Parent transition authorized; instance pending. | `approval_instance.cancelled` | Open task assignees. |

### 6.6 Completion Rules

Sequential routing is the default. Stages execute in ascending order. A later stage is visible as blocked but cannot be acted on until earlier required stages complete.

Parallel groups exist inside a stage. The first implementation should support two completion rules:

- `all`: every active task in the group must approve.
- `any`: any one active task approval completes the group and cancels or marks remaining sibling tasks as no longer required.

Reject and request-changes outcomes should short-circuit the parent approval instance unless a later policy version explicitly introduces non-blocking advisory approvals. Advisory approvals are out of scope for this epic.

### 6.7 Policy Matching

Policy matching should be deterministic and explainable. A preview and a routed instance using the same requisition data and active policy version should produce the same path.

Rules should evaluate against a normalized approval context:

- tenant ID
- requisition ID
- requester ID
- requester role or department when available
- amount and currency
- department
- cost center
- project
- line item categories
- risk classification, only when already present on the requisition context
- vendor reference, only when already present from a later workflow context

Policy version snapshots should preserve the matched route, condition explanations, due-date rules, and approver resolution results. Later policy edits must not silently change already-routed approval instances.

If no policy matches, the system should use a tenant-defined fallback policy. If no fallback exists in early local/demo data, the implementation may create a seeded default route to an admin or buyer approver, but this fallback must be explicit in the preview and audit metadata.

## 7. Data Model

The exact schema can be refined during implementation, but the domain should be built around these durable concepts:

### 7.1 Approval Policy

Represents a tenant-owned policy family.

Required fields:

- `id`
- `tenant_id`
- `name`
- `description`
- `subject_type`, initially `requisition`
- `status`: `draft`, `active`, `archived`
- `created_by`
- `updated_by`
- timestamps

Only one active effective policy set should apply for requisition approval unless implementation deliberately supports priority-based multiple policies. The first implementation should prefer a priority field on policy versions rather than multiple overlapping active policies without ordering.

### 7.2 Approval Policy Version

Represents an immutable published policy snapshot.

Required fields:

- `id`
- `approval_policy_id`
- `tenant_id`
- `version_number`
- `status`: `draft`, `published`, `retired`
- `effective_from`
- `effective_until`
- `priority`
- `rules` as structured JSON or normalized child rows
- `route_template` as structured stages/groups
- `sla_rules`
- `published_by`
- `published_at`
- timestamps

Published versions should be immutable except for retirement metadata. Draft versions can be edited until publication.

### 7.3 Approval Instance

Represents a routed approval workflow for one subject.

Required fields:

- `id`
- `tenant_id`
- `subject_type`, initially `requisition`
- `subject_id`
- `approval_policy_version_id`
- `status`
- `current_stage_id`
- `matched_context`
- `matched_explanation`
- `started_at`
- `completed_at`
- `cancelled_at`
- timestamps

This model should store enough route snapshot data to render historical approvals even after policy changes.

### 7.4 Approval Stage

Represents one ordered stage within an approval instance.

Required fields:

- `id`
- `tenant_id`
- `approval_instance_id`
- `sequence`
- `name`
- `completion_rule`: `all` or `any`
- `status`
- `activated_at`
- `completed_at`
- `due_at`
- timestamps

### 7.5 Approval Task

Represents one actionable approval item.

Required fields:

- `id`
- `tenant_id`
- `approval_instance_id`
- `approval_stage_id`
- `subject_type`
- `subject_id`
- `assigned_to_user_id`
- `original_assignee_user_id`
- `status`
- `decision`
- `decision_reason`
- `decision_comment`
- `delegated_from_task_id`
- `escalated_from_task_id`
- `assigned_at`
- `viewed_at`
- `due_at`
- `decided_at`
- `lock_version`
- timestamps

`lock_version` or equivalent optimistic concurrency control is required so stale browser tabs cannot double-act on tasks.

### 7.6 Approval Delegation

Represents a temporary or standing approval authority transfer.

Required fields:

- `id`
- `tenant_id`
- `delegator_user_id`
- `delegate_user_id`
- `scope`: task-specific, category, department, cost center, project, or all approval tasks allowed by policy
- `starts_at`
- `ends_at`
- `status`
- `reason`
- `created_by`
- timestamps

Delegation never grants visibility to a subject by itself. The delegated user must be granted approval-task visibility through the task assignment/delegation relation, and backend policies must still check tenant membership.

### 7.7 Approval SLA Rule

Represents due-date and escalation settings attached to a policy version or stage template.

Required fields:

- `duration_minutes` or business-hours equivalent
- `starts_when`: instance routed or stage activated
- `warning_before_minutes`
- `fallback_approver_user_id` or role resolver
- `escalation_after_minutes`
- `repeat_escalation` flag, false by default

The first implementation can use calendar time rather than business-hours calendars. Business calendars and holiday rules are out of scope.

## 8. Policy Authoring And Preview UX

### 8.1 Admin Policy Surface

The policy authoring surface should be useful without becoming a workflow-builder product. It should support:

- list policies
- create draft policy
- edit draft policy metadata
- define rule conditions through simple form sections
- define ordered stages
- define each stage as single approver or parallel approver group
- choose approvers by named users and, where existing permissions support it, role-based resolver such as buyer/admin/approver
- configure `all` or `any` completion rule for parallel groups
- configure due dates and fallback approvers per stage
- preview a policy against a selected requisition or example context
- publish a new immutable version
- archive a policy

Avoid a visual drag-and-drop builder in this epic. A clear form/table composition is better aligned with the current operational UI and easier to validate.

### 8.2 Policy Preview Surface

Policy preview should be available from:

- requisition draft/edit workspace when enough fields exist
- requisition detail workspace for submitted records
- admin policy configuration when testing a policy against a requisition or sample context

Preview should show:

- matched policy and version
- conditions that matched
- ordered stages
- parallel groups inside each stage
- approvers or resolver labels
- estimated due dates
- fallback approvers/escalation rule where configured
- missing data that prevents a confident route
- warning when the preview uses placeholder risk/vendor context

Preview must not create approval tasks. If preview writes an audit event, it should be low-noise and only for contexts where compliance requires it. The default design is computed preview without user notifications.

## 9. Approval Queue And Workspace UX

### 9.1 Approval Queue

Add an approval queue route under the authenticated app shell, for example `/approvals`.

The queue should support:

- assigned to me
- delegated to me
- overdue
- due soon
- completed by me
- all tenant approvals for admins/buyers where allowed
- filters by status, due date, requester, department, cost center, project, amount, and updated date
- sort by due date, assigned date, requester, amount, and status
- loading, empty, filtered-empty, permission-denied, error, and stale states

The queue should be dense and operational rather than card-heavy. It should reuse the data table foundation and mobile list fallback conventions.

### 9.2 Approval Detail

Approval detail should give approvers enough context to make a decision without leaving the workflow, while preserving the requisition detail workspace as the durable record source.

It should show:

- task status and due date
- requisition summary
- requester, department, cost center, project, amount, needed-by date
- line item summary
- justification
- attachments/evidence links if already available through requisition contracts
- approval stage map
- prior decisions and comments
- policy explanation
- action buttons for approve, reject, request changes, delegate, and comment where allowed

Decision dialogs should require reason for reject and request-changes. Approve may allow an optional comment. Delegation requires delegate, effective period, and reason.

### 9.3 Requisition Detail Integration

Requisition detail should gain an approval section that is driven by generated approval summary data:

- preview before routing where allowed
- current approval status
- current stage
- active approvers
- blocked future stages
- completed decisions
- due/overdue state
- entry point to act if the current user has an active task
- link to approval detail

Do not replace the requisition activity timeline with an approval-only timeline. Approval events should appear as activity/audit entries alongside existing requisition events.

### 9.4 Project Workspace Integration

Project workspace can show read-only approval summaries for linked requisitions where those requisitions are visible. It should not route project-level approvals in this epic.

## 10. API Contract

The exact route names can be refined during implementation, but the contract should expose these capabilities through generated clients.

### 10.1 Policy Routes

- `GET /api/approval-policies`
- `POST /api/approval-policies`
- `GET /api/approval-policies/{policyId}`
- `PATCH /api/approval-policies/{policyId}`
- `POST /api/approval-policies/{policyId}/versions`
- `POST /api/approval-policy-versions/{versionId}/publish`
- `POST /api/approval-policy-versions/{versionId}/retire`
- `POST /api/approval-policies/preview`

Policy routes are admin-only unless a future read-only policy viewer role is explicitly added.

### 10.2 Requisition Approval Routes

- `GET /api/requisitions/{requisitionId}/approval-preview`
- `POST /api/requisitions/{requisitionId}/route-approval`
- `GET /api/requisitions/{requisitionId}/approval-summary`

Routing should be idempotent for an already-routed requisition when the active approval instance is still pending. It should not create duplicate active instances.

### 10.3 Approval Task Routes

- `GET /api/approval-tasks`
- `GET /api/approval-tasks/{taskId}`
- `POST /api/approval-tasks/{taskId}/view`
- `POST /api/approval-tasks/{taskId}/approve`
- `POST /api/approval-tasks/{taskId}/reject`
- `POST /api/approval-tasks/{taskId}/request-changes`
- `POST /api/approval-tasks/{taskId}/delegate`

Task action requests should include optimistic state metadata such as `lockVersion`. Stale actions should return the standardized conflict error shape.

### 10.4 Delegation Routes

- `GET /api/approval-delegations`
- `POST /api/approval-delegations`
- `PATCH /api/approval-delegations/{delegationId}`
- `POST /api/approval-delegations/{delegationId}/cancel`

Delegation routes should be available to approvers for their own delegations and to admins for tenant management, subject to policy.

### 10.5 SLA And Metrics Routes

- `GET /api/approvals/sla-summary`
- `GET /api/approval-instances/{approvalInstanceId}`

The first SLA summary can be operational rather than analytical: counts for assigned, due soon, overdue, escalated, average age, and oldest pending approval. Deeper reporting belongs to later analytics work.

### 10.6 Contract Shape Rules

Contracts must define:

- resource identifiers and subject identifiers
- tenant and authorization behavior
- pagination, sorting, filtering, and search rules for queues
- workflow actions and allowed state transitions
- task/action request bodies and validation errors
- optimistic conflict errors
- policy preview shape
- policy version snapshot shape
- due date, overdue, and escalation fields
- audit and notification side effects where externally visible

Web code must consume generated OpenAPI schemas/endpoints through `@cognify/api-client` and feature API wrappers. Do not duplicate approval response types in `apps/web`.

## 11. Backend Domain Design

The approval backend should live under:

```txt
apps/api/Domains/Approval/
  Actions/
  Data/
  Events/
  Exceptions/
  Http/
    Controllers/
    Requests/
    Resources/
  Jobs/
  Models/
  Policies/
  Queries/
  Rules/
  Services/
  States/
  Support/
  Workflows/
  routes/
  tests/
```

Use only folders needed by each slice.

Expected domain services/actions:

- `CreateApprovalPolicy`
- `SaveApprovalPolicyDraft`
- `PublishApprovalPolicyVersion`
- `PreviewApprovalPolicy`
- `RouteRequisitionForApproval`
- `CreateApprovalInstanceFromPolicy`
- `ActivateApprovalStage`
- `ApproveApprovalTask`
- `RejectApprovalTask`
- `RequestApprovalChanges`
- `DelegateApprovalTask`
- `CreateApprovalDelegation`
- `CancelApprovalDelegation`
- `EscalateOverdueApprovalTasks`
- `CalculateApprovalSla`

Expected queries:

- approval task queue filtering
- approval policy list filtering
- approval instance lookup by subject
- SLA summary aggregation

Expected jobs:

- overdue task escalation scan
- due-soon notification scan if needed

Jobs must be idempotent. Running the escalation scan repeatedly should not create duplicate fallback tasks, duplicate audit events, or duplicate notifications for the same escalation threshold.

## 12. Permissions And Tenant Rules

Backend policies are authoritative. Frontend permissions only shape visible commands.

Tenant rules:

- Every approval policy, version, instance, stage, task, delegation, audit event, notification, and SLA query is tenant-scoped.
- Every subject record must belong to the same tenant as the approval records.
- Approver, delegate, fallback approver, requester, buyer, and admin users must be tenant members.
- Cross-tenant policy preview, routing, task action, delegation, and SLA query attempts must fail.
- Mention/comment behavior remains governed by the existing Collaboration domain and must not grant approval authority.

Permission rules:

- Admins can configure policies and see tenant approval governance.
- Buyers can preview and monitor approval state for requisitions they are allowed to view.
- Requesters can preview their own requisition path and view progress for their requisitions.
- Approvers can view and act only on assigned, delegated, or policy-visible tasks.
- Delegates can act only within active delegation scope and effective dates.
- Fallback approvers can act only after valid escalation creates or assigns an actionable task.
- Users cannot approve their own requisition unless an explicit policy version allows self-approval. The default should forbid self-approval.

## 13. Audit, Notifications, And Activity

Approval Orchestration is compliance-sensitive. These events should be auditable:

- `approval_policy.created`
- `approval_policy.updated`
- `approval_policy.published`
- `approval_policy.retired`
- `approval_policy.previewed` only if durable preview logging is enabled
- `approval_instance.created`
- `approval_stage.activated`
- `approval_task.assigned`
- `approval_task.viewed`
- `approval_task.approved`
- `approval_task.rejected`
- `approval_task.changes_requested`
- `approval_task.delegated`
- `approval_task.escalated`
- `approval_task.cancelled`
- `approval_delegation.created`
- `approval_delegation.cancelled`
- `approval_instance.approved`
- `approval_instance.rejected`
- `approval_instance.changes_requested`
- `approval_instance.cancelled`
- `requisition.pending_approval`
- `requisition.approved`
- `requisition.rejected`

Audit metadata should include tenant ID, actor ID, actor type, request/correlation ID, policy version, subject, task, stage, before/after status, delegation/escalation links, decision reason, and relevant due-date metadata.

In-app notifications should be recorded for:

- approval task assigned
- approval stage activated
- delegated task assigned
- task due soon, if implemented
- task overdue
- task escalated
- requisition approved
- requisition rejected
- changes requested
- approval cancelled because parent requisition was withdrawn or cancelled

Notification preferences remain in-app only. Do not add email, Slack, Teams, push, SMS, or digest delivery in this epic.

## 14. Error Handling And Conflict Rules

Approval actions must expose predictable errors:

- validation errors for missing reason, invalid delegate, invalid policy, invalid due-date settings, invalid condition shape, or invalid completion rule
- authorization errors for denied task action or policy configuration
- not-found errors scoped to tenant visibility
- conflict errors for stale `lockVersion`, already-decided task, cancelled parent instance, superseded policy draft, or duplicate route attempt
- degraded-state errors for escalation job failure or notification recording failure where visible to operators

Frontend UX should include:

- inline validation for policy forms and action dialogs
- error summaries for action failures
- stale state recovery that refreshes approval task/summary data after conflict
- disabled actions with clear reason where policy/state is known
- permission-denied states rather than empty screens when access is denied

## 15. MSW And Mocked Frontend Workflow

MSW handlers should return OpenAPI-shaped responses and model realistic workflow transitions:

- preview with matched policy and stages
- preview with missing data warning
- approval queue with assigned, delegated, due soon, overdue, and completed tasks
- task approve success advancing a sequential stage
- task approve success completing an `all` parallel group
- task approve success completing an `any` parallel group and closing sibling tasks
- reject requiring reason and moving requisition to rejected
- request changes requiring reason and moving requisition to changes requested
- delegation creating a delegated task state
- stale task action returning a conflict response
- permission denied for unauthorized task action

Production components must not import mock fixtures directly. Tests can use fixtures in the approved mock/test folders.

## 16. Critical-Path Browser Coverage

Approval Orchestration is a trust-critical workflow. Add Playwright coverage early enough that implementation cannot drift from the real user path.

Critical path:

1. Admin publishes a simple approval policy.
2. Requester previews approval path from a requisition.
3. Submitted requisition is routed and moves to `pending_approval`.
4. Approver sees task in `/approvals`.
5. Approver approves first stage.
6. Next sequential or parallel stage becomes visible.
7. Final approval moves requisition to `approved`.
8. Activity timeline and approval summary show decisions and policy version.

Additional focused browser scenarios should cover:

- reject with required reason
- request changes with required reason
- delegate to a valid user and act as delegate
- overdue or escalated task visibility
- unauthorized user cannot act on another user's task

The exact command can be set in the implementation plan, but the intended harness should live near existing web E2E coverage, for example `apps/web/tests/e2e/approval-orchestration.spec.ts` with a tag such as `@p1-approval`.

## 17. Implementation Slice Boundaries

### Slice 1: Routing Rule Model And Versioning

Build the approval policy and version foundation, admin policy list/detail/draft forms, backend policy actions, policy validation, audit, OpenAPI contract, generated client, and tests. This slice does not need to create live approval tasks.

### Slice 2: Policy Preview

Add preview endpoints and UI surfaces for requisitions and admin policy testing. Preview should explain matched conditions, route stages, approvers/resolvers, due dates, fallback behavior, and missing data. It should not create tasks.

### Slice 3: Approval Task Lifecycle

Create approval instances/tasks from submitted requisitions, approval queue/detail routes, approve/reject/request-changes actions, comments where existing collaboration behavior supports them, audit, notifications, and requisition outcome transitions.

### Slice 4: Sequential Chains

Harden ordered stage advancement, blocked-stage visibility, stage activation notifications, and tests that prove later stages cannot be acted on early.

### Slice 5: Parallel Groups

Add parallel group completion rules, concurrent task visibility, `all` and `any` behavior, sibling task closure for `any`, and timeline visibility for concurrent decisions.

### Slice 6: Delegation

Add delegation records, delegation management UI, delegate task assignment, policy guards, effective dates, audit trail, notifications, and permissions.

### Slice 7: Escalation And SLA Tracking

Add due dates, overdue state, escalation job, fallback approver behavior, SLA summary, due soon/overdue notifications, aging metrics, and operational visibility.

## 18. Risks And Mitigations

- Approval can sprawl into a generic workflow engine. Keep the first subject as requisitions and design reusable concepts without abstracting away procurement language.
- Policy authoring can become too complex. Use structured forms and versioned snapshots rather than a visual builder or expression language.
- Requisition and approval states can drift. Treat `apps/api/Domains/Requisition` as the owner of requisition outcomes and route all outcome changes through narrow integration actions.
- Stale approval actions are likely in real workflows. Require optimistic concurrency on tasks and refresh UI after conflict errors.
- Delegation can accidentally grant access. Delegation grants task authority only within tenant and policy scope; it does not grant broad subject access.
- Escalation jobs can duplicate side effects. Make escalation idempotent and store threshold markers or escalation records.
- Policy edits can rewrite history. Published policy versions must be immutable and approval instances must store route snapshots.
- Notifications can become noisy. Send only actionable in-app notifications and avoid preview notifications.

## 19. Verification

Expected verification for implementation slices:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- approvals requisitions
cd apps/api
php artisan test
php artisan route:list --path=api
```

Add narrower commands in each implementation plan when exact test files exist.

Required backend coverage:

- policy creation/edit/publish authorization
- policy matching by amount, department, cost center, category, project, requester, and fallback
- tenant isolation for policies, instances, tasks, delegations, and summaries
- preview without task creation
- route idempotency
- approve, reject, request changes
- sequential stage blocking
- parallel `all` and `any` completion
- delegation scope/effective dates
- escalation idempotency
- stale task conflict
- audit and notification side effects

Required frontend coverage:

- policy authoring forms
- policy preview states
- approval queue filters and empty/error/permission states
- approval detail actions
- requisition approval summary integration
- stale action recovery
- delegation UI
- SLA/overdue display

## 20. Exit Criteria

- Admins can configure and publish a tenant-scoped approval policy version.
- Requesters and buyers can preview the approval path for a requisition before or during routing.
- A submitted requisition can be routed through a configured approval path and move to `pending_approval`.
- Approvers can act from `/approvals` and from the requisition detail workspace when they have an active task.
- Sequential stages advance in order and blocked stages are visible but not actionable.
- Parallel groups support `all` and `any` completion rules.
- Approvers can delegate within policy limits, and delegates can act with a complete audit trail.
- Overdue approvals show SLA state, escalation state, and fallback approver visibility.
- Approval outcomes move the requisition to `approved`, `rejected`, or `changes_requested` through domain-owned transitions.
- Approval events appear in activity/audit timelines.
- In-app notifications deep-link users to approval tasks or requisition records.
- OpenAPI contracts and generated clients are aligned.
- Tenant isolation, permissions, conflicts, and critical browser paths are verified.

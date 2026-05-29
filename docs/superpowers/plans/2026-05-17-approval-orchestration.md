# Approval Orchestration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver P1 Epic 4 so submitted requisitions can be previewed, routed, approved, rejected, returned for changes, delegated, escalated, and monitored through SLA-aware approval workflows.

**Architecture:** Implement this as seven vertical slices from `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`. Approval policy, routing, task lifecycle, delegation, escalation, and SLA behavior live in `apps/api/Domains/Approval`; requisition outcome transitions remain owned by `apps/api/Domains/Requisition`; web approval workflows live in `apps/web/features/approvals`; OpenAPI and `@cognify/api-client` remain the contract boundary.

**Tech Stack:** Laravel 12, Sanctum, Eloquent migrations, Laravel feature tests, queue jobs, OpenAPI/Orval, Next.js App Router, React 19, TanStack Query, React Hook Form/Zod, MSW, Vitest, Testing Library, Playwright, shadcn/Radix primitives exported through `packages/ui`.

---

## Source Documents

- Design spec: `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`
- P1 epic plan: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Architecture: `ARCHITECTURE.md`
- Feature runbook: `docs/05-runbooks/feature-development.md`
- Agent guide: `AGENTS.md`

## Locked Implementation Decisions

- Build requisition approval first. Do not implement award approval, RFQ, quotation, buyer intake, PO handoff, org chart sync, email delivery, or a generic workflow engine.
- Activate `pending_approval` for routed requisitions and add `approved` and `rejected` as requisition approval outcomes.
- Published approval policy versions are immutable except retirement metadata.
- Routed approval instances store policy/version and route snapshot data so later policy edits do not rewrite history.
- Sequential stages execute in order. Blocked future stages are visible but never actionable.
- Parallel groups support only `all` and `any` completion rules in P1.
- Rejections and change requests short-circuit the approval instance.
- Delegation grants approval-task authority only inside tenant, policy, and effective-date scope. It does not grant broad requisition visibility.
- Escalation jobs are idempotent and must not duplicate tasks, audit events, or notifications.
- Policy previews compute route explanations but do not create approval tasks or send notifications.
- Keep approval UI in `apps/web/features/approvals`; keep requisition integration in `apps/web/features/requisitions`; keep `packages/ui` primitive-only.
- Historical note as of 2026-05-28: final award approval now intentionally auto-creates or reveals a draft PO handoff, and the handoff action is idempotent. Approval remains the decision boundary.

## File Map

### Backend Approval Domain

- Create: `apps/api/database/migrations/2026_05_17_000000_create_approval_policies_table.php`
- Create: `apps/api/database/migrations/2026_05_17_000001_create_approval_instances_table.php`
- Create: `apps/api/database/migrations/2026_05_17_000002_create_approval_delegations_table.php`
- Create: `apps/api/database/migrations/2026_05_17_000003_add_approval_outcomes_to_requisitions_table.php`
- Create/replace: `apps/api/Domains/Approval/Models/ApprovalPolicy.php`
- Create: `apps/api/Domains/Approval/Models/ApprovalPolicyVersion.php`
- Create: `apps/api/Domains/Approval/Models/ApprovalInstance.php`
- Create: `apps/api/Domains/Approval/Models/ApprovalStage.php`
- Modify: `apps/api/Domains/Approval/Models/ApprovalTask.php`
- Create: `apps/api/Domains/Approval/Models/ApprovalDelegation.php`
- Create: `apps/api/Domains/Approval/States/ApprovalPolicyStatus.php`
- Create: `apps/api/Domains/Approval/States/ApprovalPolicyVersionStatus.php`
- Create: `apps/api/Domains/Approval/States/ApprovalInstanceStatus.php`
- Create: `apps/api/Domains/Approval/States/ApprovalStageStatus.php`
- Create: `apps/api/Domains/Approval/States/ApprovalTaskStatus.php`
- Create: `apps/api/Domains/Approval/States/ApprovalDelegationStatus.php`
- Create: `apps/api/Domains/Approval/Data/ApprovalContextData.php`
- Create: `apps/api/Domains/Approval/Data/ApprovalPreviewData.php`
- Create: `apps/api/Domains/Approval/Data/ApprovalRouteStageData.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalRouteBuilder.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalSlaCalculator.php`
- Create: `apps/api/Domains/Approval/Actions/SaveApprovalPolicyDraft.php`
- Create: `apps/api/Domains/Approval/Actions/PublishApprovalPolicyVersion.php`
- Create: `apps/api/Domains/Approval/Actions/PreviewApprovalPolicy.php`
- Create: `apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php`
- Create: `apps/api/Domains/Approval/Actions/ApproveApprovalTask.php`
- Create: `apps/api/Domains/Approval/Actions/RejectApprovalTask.php`
- Create: `apps/api/Domains/Approval/Actions/RequestApprovalChanges.php`
- Create: `apps/api/Domains/Approval/Actions/DelegateApprovalTask.php`
- Create: `apps/api/Domains/Approval/Actions/CreateApprovalDelegation.php`
- Create: `apps/api/Domains/Approval/Actions/CancelApprovalDelegation.php`
- Create: `apps/api/Domains/Approval/Actions/EscalateOverdueApprovalTasks.php`
- Create: `apps/api/Domains/Approval/Jobs/EscalateOverdueApprovalTasksJob.php`
- Create: `apps/api/Domains/Approval/Policies/ApprovalPolicyPolicy.php`
- Create: `apps/api/Domains/Approval/Policies/ApprovalTaskPolicy.php`
- Create: `apps/api/Domains/Approval/Policies/ApprovalDelegationPolicy.php`
- Create: `apps/api/Domains/Approval/Queries/ApprovalTaskQueueQuery.php`
- Create: `apps/api/Domains/Approval/Queries/ApprovalSlaSummaryQuery.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/ApprovalPolicyController.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/ApprovalPolicyVersionController.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/RequisitionApprovalController.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/ApprovalDelegationController.php`
- Create: `apps/api/Domains/Approval/Http/Controllers/ApprovalSlaController.php`
- Create request/resource classes under `apps/api/Domains/Approval/Http/Requests` and `apps/api/Domains/Approval/Http/Resources` named in the task sections.
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/ApprovalPolicyApiTest.php`
- Test: `apps/api/tests/Feature/ApprovalPreviewApiTest.php`
- Test: `apps/api/tests/Feature/ApprovalTaskApiTest.php`
- Test: `apps/api/tests/Feature/ApprovalDelegationApiTest.php`
- Test: `apps/api/tests/Feature/ApprovalSlaApiTest.php`

### Backend Requisition/Audit/Notification Integration

- Modify: `apps/api/Domains/Requisition/States/RequisitionStatus.php`
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`
- Modify: `apps/api/Domains/Requisition/Policies/RequisitionPolicy.php`
- Create: `apps/api/Domains/Requisition/Actions/MarkRequisitionPendingApproval.php`
- Create: `apps/api/Domains/Requisition/Actions/MarkRequisitionApproved.php`
- Create: `apps/api/Domains/Requisition/Actions/MarkRequisitionRejected.php`
- Modify: `apps/api/database/seeders/Demo/DemoNotificationSeeder.php`
- Modify: `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`

### API Contract

- Modify: `apps/api/storage/openapi/openapi.json`
- Regenerate: `packages/api-client/src/generated/**`

### Frontend Approval Feature

- Create: `apps/web/app/(workspace)/approvals/page.tsx`
- Create: `apps/web/app/(workspace)/approvals/[approvalTaskId]/page.tsx`
- Create: `apps/web/app/(workspace)/approval-policies/page.tsx`
- Create: `apps/web/app/(workspace)/approval-policies/[policyId]/page.tsx`
- Create: `apps/web/features/approvals/api/approvals-api.ts`
- Create: `apps/web/features/approvals/types/approval-view-model.ts`
- Create: `apps/web/features/approvals/schemas/approval-action-schema.ts`
- Create: `apps/web/features/approvals/schemas/approval-policy-schema.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-policies.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-preview.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-tasks.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-task-actions.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-delegations.ts`
- Create: `apps/web/features/approvals/hooks/use-approval-sla-summary.ts`
- Create: `apps/web/features/approvals/components/approval-status-badge.tsx`
- Create: `apps/web/features/approvals/components/approval-stage-map.tsx`
- Create: `apps/web/features/approvals/components/approval-policy-preview.tsx`
- Create: `apps/web/features/approvals/components/approval-action-dialog.tsx`
- Create: `apps/web/features/approvals/components/approval-delegation-dialog.tsx`
- Create: `apps/web/features/approvals/components/approval-sla-summary.tsx`
- Create: `apps/web/features/approvals/forms/approval-policy-form.tsx`
- Create: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Create: `apps/web/features/approvals/workflows/approval-queue-page.tsx`
- Create: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Create: `apps/web/features/approvals/workflows/approval-policy-list-page.tsx`
- Create: `apps/web/features/approvals/workflows/approval-policy-detail-page.tsx`
- Create: `apps/web/features/approvals/mocks/approval-fixtures.ts`
- Create: `apps/web/features/approvals/mocks/approval-handlers.ts`
- Test: `apps/web/features/approvals/tests/approval-policy-schema.test.ts`
- Test: `apps/web/features/approvals/tests/approval-policy-preview.test.tsx`
- Test: `apps/web/features/approvals/tests/approval-queue-workflow.test.tsx`
- Test: `apps/web/features/approvals/tests/approval-task-actions.test.tsx`
- Test: `apps/web/features/approvals/tests/approval-delegation.test.tsx`

### Frontend Requisition/Shell/Test Integration

- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Create: `apps/web/features/requisitions/components/requisition-approval-summary.tsx`
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Create: `apps/web/tests/e2e/approval-orchestration.spec.ts`

---

## Task 0: Baseline Safety And Architecture Check

**Files:**
- Read: `AGENTS.md`
- Read: `ARCHITECTURE.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`

- [ ] **Step 1: Check branch and worktree**

Run:

```bash
git status --short --branch
```

Expected: current branch is visible. Do not overwrite unrelated user changes. The branch currently may be ahead of `origin/main` because the approval design spec was committed.

- [ ] **Step 2: Confirm current route and domain baseline**

Run:

```bash
find apps/api/Domains/Approval -maxdepth 3 -type f -print | sort
```

Expected: `apps/api/Domains/Approval/Models/ApprovalTask.php` exists and most approval domain files do not.

- [ ] **Step 3: Run focused baseline checks**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionApiTest
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx
```

Expected: existing requisition tests pass before approval changes. If either fails before edits, stop and use `superpowers:systematic-debugging`.

- [ ] **Step 4: Commit nothing**

Run:

```bash
git status --short
```

Expected: no implementation files changed in this task.

---

## Task 1: Routing Rule Model And Versioning

**Files:**
- Create migrations and approval policy/version models listed in the file map.
- Create: `apps/api/tests/Feature/ApprovalPolicyApiTest.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Create frontend policy files listed under Frontend Approval Feature.

- [ ] **Step 1: Write failing backend policy tests**

Create `apps/api/tests/Feature/ApprovalPolicyApiTest.php` with tests named:

```php
public function test_admin_can_create_policy_draft(): void {}
public function test_non_admin_cannot_create_policy_draft(): void {}
public function test_admin_can_publish_immutable_policy_version(): void {}
public function test_policy_version_rejects_invalid_completion_rule(): void {}
public function test_cross_tenant_policy_is_not_visible(): void {}
```

Each test must authenticate through Sanctum, send `X-Tenant-Id`, and assert JSON shapes using these keys: `id`, `tenantId`, `name`, `subjectType`, `status`, `versions`, `versionNumber`, `rules`, `routeTemplate`, `slaRules`, `createdAt`, `updatedAt`.

- [ ] **Step 2: Run policy tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalPolicyApiTest
```

Expected: FAIL because policy routes, models, and migrations do not exist.

- [ ] **Step 3: Add policy migrations**

Create:

```txt
apps/api/database/migrations/2026_05_17_000000_create_approval_policies_table.php
```

Tables:

- `approval_policies`: `id`, `tenant_id`, `name`, `description`, `subject_type`, `status`, `created_by`, `updated_by`, timestamps.
- `approval_policy_versions`: `id`, `approval_policy_id`, `tenant_id`, `version_number`, `status`, `effective_from`, `effective_until`, `priority`, JSON `rules`, JSON `route_template`, JSON `sla_rules`, `published_by`, `published_at`, timestamps.

Add indexes on `tenant_id`, `subject_type`, `status`, and `(tenant_id, subject_type, status, priority)`.

- [ ] **Step 4: Add policy states and models**

Create enum values:

```php
enum ApprovalPolicyStatus: string { case Draft = 'draft'; case Active = 'active'; case Archived = 'archived'; }
enum ApprovalPolicyVersionStatus: string { case Draft = 'draft'; case Published = 'published'; case Retired = 'retired'; }
```

Models must cast statuses to enums, JSON columns to arrays, and define `tenant`, `creator`, `updater`, `versions`, `policy`, and `publisher` relationships.

- [ ] **Step 5: Add policy actions, requests, resources, and controllers**

Create:

- `SaveApprovalPolicyDraft`
- `PublishApprovalPolicyVersion`
- `StoreApprovalPolicyRequest`
- `UpdateApprovalPolicyRequest`
- `StoreApprovalPolicyVersionRequest`
- `ApprovalPolicyResource`
- `ApprovalPolicyVersionResource`
- `ApprovalPolicyController`
- `ApprovalPolicyVersionController`

Validation must allow only `subjectType: requisition`, completion rules `all|any`, and SLA durations as positive integers. Publishing must copy draft route data into a published immutable version, retire overlapping active published versions for the same tenant/subject when necessary, and record audit events `approval_policy.created`, `approval_policy.updated`, and `approval_policy.published`.

- [ ] **Step 6: Register policy routes**

Modify `apps/api/routes/api.php` inside the tenant middleware group:

```php
Route::get('/approval-policies', [ApprovalPolicyController::class, 'index']);
Route::post('/approval-policies', [ApprovalPolicyController::class, 'store']);
Route::get('/approval-policies/{approvalPolicy}', [ApprovalPolicyController::class, 'show']);
Route::patch('/approval-policies/{approvalPolicy}', [ApprovalPolicyController::class, 'update']);
Route::post('/approval-policies/{approvalPolicy}/versions', [ApprovalPolicyVersionController::class, 'store']);
Route::post('/approval-policy-versions/{approvalPolicyVersion}/publish', [ApprovalPolicyVersionController::class, 'publish']);
Route::post('/approval-policy-versions/{approvalPolicyVersion}/retire', [ApprovalPolicyVersionController::class, 'retire']);
```

Add the matching `use` statements.

- [ ] **Step 7: Add OpenAPI policy contract**

Modify `apps/api/storage/openapi/openapi.json` to define schemas:

- `ApprovalPolicy`
- `ApprovalPolicyVersion`
- `ApprovalPolicyRule`
- `ApprovalRouteTemplate`
- `ApprovalRouteStageTemplate`
- `ApprovalSlaRule`
- `StoreApprovalPolicyRequest`
- `StoreApprovalPolicyVersionRequest`

Add paths for all policy routes from Step 6.

- [ ] **Step 8: Generate client and run backend tests**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalPolicyApiTest
```

Expected: PASS.

- [ ] **Step 9: Add policy authoring frontend**

Create policy API wrappers, schemas, hooks, forms, list/detail workflows, routes, fixtures, MSW handlers, and tests listed in the file map. The Zod schema must reject invalid completion rules, missing policy name, empty stages, cross-field SLA values where escalation is earlier than due date, and non-`requisition` subject types.

- [ ] **Step 10: Wire shell navigation for admin policy management**

Modify `apps/web/components/shell/shell-route-config.ts` to add an implemented admin-only `Approval policies` route under Governance or Manage:

```ts
{ label: "Approval policies", href: "/approval-policies", icon: CheckSquare, implemented: true, permission: canUseAudit }
```

Add breadcrumbs for `/approval-policies` and `/approval-policies/[policyId]`.

- [ ] **Step 11: Run frontend policy tests and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-policy-schema.test.ts approval-policy-preview.test.tsx
git status --short
git add apps/api apps/web packages/api-client
git commit -m "feat: add approval policy versioning"
```

Expected: tests pass; commit contains only policy/versioning slice files.

---

## Task 2: Policy Preview

**Files:**
- Create: `apps/api/tests/Feature/ApprovalPreviewApiTest.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php`
- Create: `apps/api/Domains/Approval/Services/ApprovalRouteBuilder.php`
- Create: `apps/api/Domains/Approval/Actions/PreviewApprovalPolicy.php`
- Create preview data/resource/request classes.
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Create/modify frontend preview components and requisition integration files.

- [ ] **Step 1: Write failing preview tests**

Create tests named:

```php
public function test_requester_can_preview_approval_path_for_own_draft(): void {}
public function test_buyer_can_preview_submitted_requisition_path(): void {}
public function test_preview_does_not_create_approval_tasks(): void {}
public function test_preview_explains_missing_required_context(): void {}
public function test_preview_uses_fallback_policy_when_no_rule_matches(): void {}
```

Assert preview JSON contains `matchedPolicy`, `matchedVersion`, `matchedConditions`, `stages`, `warnings`, `estimatedDueAt`, and `createsTasks: false`.

- [ ] **Step 2: Run preview tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalPreviewApiTest
```

Expected: FAIL because preview endpoints and matcher do not exist.

- [ ] **Step 3: Implement normalized approval context**

Create `ApprovalContextData` with fields:

```php
tenantId, requisitionId, requesterId, amount, currency, department, costCenter, projectId, lineItemCategories, riskClassification, vendorId
```

Build context from a loaded `Requisition` and its line items. Amount must use line item quantity times estimated unit price and round to two decimals.

- [ ] **Step 4: Implement matcher and route builder**

`ApprovalPolicyMatcher` must evaluate deterministic rule conditions against context in priority order. Supported operators are `equals`, `in`, `gte`, `lte`, and `between`. `ApprovalRouteBuilder` must convert the matched version route template into preview stages with approver labels, completion rules, due dates, fallback approvers, and warnings.

- [ ] **Step 5: Add preview endpoints**

Register:

```php
Route::post('/approval-policies/preview', [ApprovalPolicyController::class, 'preview']);
Route::get('/requisitions/{requisition}/approval-preview', [RequisitionApprovalController::class, 'preview']);
```

Policy preview is admin-only. Requisition preview is available to users who can view the requisition and to requesters editing their own draft.

- [ ] **Step 6: Update OpenAPI preview contract and generate client**

Add schemas:

- `ApprovalPreview`
- `ApprovalPreviewStage`
- `ApprovalPreviewApprover`
- `ApprovalPreviewWarning`

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalPreviewApiTest
```

Expected: PASS.

- [ ] **Step 7: Add frontend preview UI**

Create `approval-policy-preview.tsx`, `use-approval-preview.ts`, preview fixture states, MSW preview handlers, and tests. Add `requisition-approval-summary.tsx` to show preview from requisition detail/edit contexts without creating tasks.

- [ ] **Step 8: Run frontend preview tests and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-policy-preview.test.tsx requisitions-workflow.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: add approval policy preview"
```

Expected: tests pass and preview UI shows matched policy, route stages, warnings, and no task actions.

---

## Task 3: Approval Task Lifecycle

**Files:**
- Create: `apps/api/database/migrations/2026_05_17_000001_create_approval_instances_table.php`
- Create/modify approval instance, stage, task models/states/actions/resources/controllers.
- Modify requisition state/model/resource/policy/action files.
- Create: `apps/api/tests/Feature/ApprovalTaskApiTest.php`
- Modify OpenAPI and frontend approval queue/detail files.

- [ ] **Step 1: Write failing lifecycle tests**

Create tests named:

```php
public function test_submitted_requisition_can_be_routed_for_approval(): void {}
public function test_approver_can_approve_assigned_task(): void {}
public function test_approver_can_reject_with_required_reason(): void {}
public function test_approver_can_request_changes_with_required_reason(): void {}
public function test_stale_task_action_returns_conflict(): void {}
public function test_cross_tenant_user_cannot_act_on_task(): void {}
```

Assert requisition status transitions: `submitted -> pending_approval -> approved`, `pending_approval -> rejected`, and `pending_approval -> changes_requested`.

- [ ] **Step 2: Run lifecycle tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
```

Expected: FAIL because live approval instances and task actions do not exist.

- [ ] **Step 3: Add approval instance/stage/task schema**

Create tables:

- `approval_instances`: subject type/id, policy version, status, current stage, matched context/explanation JSON, started/completed/cancelled timestamps.
- `approval_stages`: instance, sequence, name, completion rule, status, activated/completed/due timestamps.
- `approval_tasks`: instance, stage, subject type/id, assignee/original assignee, status, decision fields, delegated/escalated links, assigned/viewed/due/decided timestamps, `lock_version`.

Add indexes on tenant, subject, assignee, status, due date, and instance/stage.

- [ ] **Step 4: Add requisition approval outcomes**

Modify `RequisitionStatus` to include:

```php
case Approved = 'approved';
case Rejected = 'rejected';
```

Add approval outcome timestamps/actor/reason columns through `2026_05_17_000003_add_approval_outcomes_to_requisitions_table.php`: `approved_at`, `approved_by_id`, `rejected_at`, `rejected_by_id`, `rejection_reason`, `approval_instance_id`.

- [ ] **Step 5: Implement routing and task actions**

Create actions:

- `RouteRequisitionForApproval`: idempotently creates one active approval instance, stages, active first-stage tasks, audit events, notifications, and marks requisition `pending_approval`.
- `ApproveApprovalTask`: checks authorization and lock version, records decision, advances stage/instance, marks requisition `approved` on final completion.
- `RejectApprovalTask`: requires reason, records decision, marks instance/requisition rejected.
- `RequestApprovalChanges`: requires reason and optional requested fields, records decision, marks instance changes requested and requisition `changes_requested`.

- [ ] **Step 6: Add task queue/detail/action routes**

Register:

```php
Route::post('/requisitions/{requisition}/route-approval', [RequisitionApprovalController::class, 'route']);
Route::get('/requisitions/{requisition}/approval-summary', [RequisitionApprovalController::class, 'summary']);
Route::get('/approval-tasks', [ApprovalTaskController::class, 'index']);
Route::get('/approval-tasks/{approvalTask}', [ApprovalTaskController::class, 'show']);
Route::post('/approval-tasks/{approvalTask}/view', [ApprovalTaskController::class, 'view']);
Route::post('/approval-tasks/{approvalTask}/approve', [ApprovalTaskController::class, 'approve']);
Route::post('/approval-tasks/{approvalTask}/reject', [ApprovalTaskController::class, 'reject']);
Route::post('/approval-tasks/{approvalTask}/request-changes', [ApprovalTaskController::class, 'requestChanges']);
```

- [ ] **Step 7: Update OpenAPI and generate client**

Add schemas:

- `ApprovalInstance`
- `ApprovalStage`
- `ApprovalTask`
- `ApprovalTaskQueueResponse`
- `ApprovalSummary`
- `ApprovalTaskActionRequest`
- `RejectApprovalTaskRequest`
- `RequestApprovalChangesRequest`

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
```

Expected: PASS.

- [ ] **Step 8: Build approval queue and detail UI**

Create approval API wrappers, hooks, status badge, stage map, action dialog, table, queue workflow, detail workflow, app routes, fixtures, MSW handlers, and tests. Queue filters must include assigned to me, overdue, due soon, completed by me, all tenant approvals for admin/buyer, status, due date, requester, department, cost center, project, amount, and updated date.

- [ ] **Step 9: Wire requisition approval summary**

Modify requisition API mappers, view model, detail page, fixtures, handlers, and tests so requisition detail shows current approval status, current stage, active approvers, completed decisions, due/overdue state, and an action entry point when the current user has an active task.

- [ ] **Step 10: Run lifecycle frontend checks and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-queue-workflow.test.tsx approval-task-actions.test.tsx requisitions-workflow.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: add approval task lifecycle"
```

Expected: tests pass and the commit includes route, queue, detail, and requisition summary behavior.

---

## Task 4: Sequential Chains

**Files:**
- Modify: `apps/api/Domains/Approval/Actions/ApproveApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RouteRequisitionForApproval.php`
- Modify: `apps/api/Domains/Approval/Services/ApprovalRouteBuilder.php`
- Modify: `apps/api/tests/Feature/ApprovalTaskApiTest.php`
- Modify: `apps/web/features/approvals/components/approval-stage-map.tsx`
- Modify: `apps/web/features/approvals/tests/approval-task-actions.test.tsx`

- [ ] **Step 1: Add failing sequential tests**

Add tests named:

```php
public function test_later_sequential_stage_is_blocked_until_prior_stage_completes(): void {}
public function test_stage_activation_notifies_next_approver(): void {}
```

Expected assertions: second-stage task starts as `blocked` or non-actionable, approving first stage activates second stage, and acting on second stage before activation returns authorization or conflict error.

- [ ] **Step 2: Run tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
```

Expected: FAIL for sequential blocking/activation.

- [ ] **Step 3: Implement sequential stage activation**

Update routing so only sequence `1` stages are `active`; later stages are `blocked`. Update approval completion so an all-approved active stage becomes `completed`, the next blocked stage becomes `active`, due dates are calculated on activation, audit event `approval_stage.activated` is written, and next-stage approvers receive in-app notifications.

- [ ] **Step 4: Update OpenAPI summary fields**

Ensure `ApprovalSummary` and `ApprovalStage` expose `sequence`, `status`, `activatedAt`, `completedAt`, `dueAt`, and `isActionable`.

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
```

Expected: PASS.

- [ ] **Step 5: Update stage map UI**

Render blocked stages as visible non-actionable rows in `approval-stage-map.tsx`. Add test coverage that blocked stages show label text and no action button.

- [ ] **Step 6: Run frontend checks and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-task-actions.test.tsx approval-queue-workflow.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: support sequential approval chains"
```

Expected: tests pass.

---

## Task 5: Parallel Groups

**Files:**
- Modify approval route builder, route action, approve action, resources, tests, and stage map components.

- [ ] **Step 1: Add failing parallel group tests**

Add tests named:

```php
public function test_all_parallel_group_requires_every_task_to_approve(): void {}
public function test_any_parallel_group_completes_when_one_task_approves(): void {}
public function test_any_parallel_group_closes_sibling_tasks_after_completion(): void {}
```

- [ ] **Step 2: Run tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
```

Expected: FAIL for parallel completion rules.

- [ ] **Step 3: Implement `all` and `any` completion**

For `all`, keep stage active until every active task is approved. For `any`, complete the stage after the first approval and mark sibling tasks `cancelled` with cancellation metadata linking to the deciding task. Reject and request-changes still short-circuit the full instance.

- [ ] **Step 4: Update mocks and UI**

Add MSW fixtures for `all` and `any` parallel groups. Update `approval-stage-map.tsx` to group parallel approvers under the same stage and show completion rule labels.

- [ ] **Step 5: Run checks and commit**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalTaskApiTest
pnpm --filter @cognify/web test -- approval-task-actions.test.tsx approval-policy-preview.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: support parallel approval groups"
```

Expected: backend and frontend checks pass.

---

## Task 6: Delegation

**Files:**
- Create delegation migration/model/state/action/policy/controller/request/resource files.
- Create: `apps/api/tests/Feature/ApprovalDelegationApiTest.php`
- Create frontend delegation hooks/dialog/tests.
- Modify OpenAPI.

- [ ] **Step 1: Write failing delegation tests**

Create tests named:

```php
public function test_approver_can_create_active_delegation_for_same_tenant_user(): void {}
public function test_delegate_can_act_on_delegated_task(): void {}
public function test_expired_delegation_cannot_act_on_task(): void {}
public function test_delegation_cannot_cross_tenants(): void {}
public function test_delegation_does_not_allow_self_approval_when_policy_forbids_it(): void {}
```

- [ ] **Step 2: Run delegation tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalDelegationApiTest
```

Expected: FAIL because delegation routes and actions do not exist.

- [ ] **Step 3: Add delegation persistence and actions**

Create `approval_delegations` table with delegator, delegate, scope, starts/ends, status, reason, created_by, timestamps. Implement `CreateApprovalDelegation`, `CancelApprovalDelegation`, and `DelegateApprovalTask`. Delegating a task must record `approval_task.delegated`, assign actionable authority to the delegate, notify delegate/original assignee, and preserve original assignee metadata.

- [ ] **Step 4: Add delegation routes and OpenAPI**

Register:

```php
Route::get('/approval-delegations', [ApprovalDelegationController::class, 'index']);
Route::post('/approval-delegations', [ApprovalDelegationController::class, 'store']);
Route::patch('/approval-delegations/{approvalDelegation}', [ApprovalDelegationController::class, 'update']);
Route::post('/approval-delegations/{approvalDelegation}/cancel', [ApprovalDelegationController::class, 'cancel']);
Route::post('/approval-tasks/{approvalTask}/delegate', [ApprovalTaskController::class, 'delegate']);
```

Add schemas `ApprovalDelegation`, `StoreApprovalDelegationRequest`, and `DelegateApprovalTaskRequest`.

- [ ] **Step 5: Generate client and run backend tests**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalDelegationApiTest
```

Expected: PASS.

- [ ] **Step 6: Add delegation UI**

Create delegation dialog, hooks, fixtures, handlers, and tests. The dialog must require delegate, effective dates, and reason, and must display server validation errors for cross-tenant, expired, and policy-denied delegations.

- [ ] **Step 7: Run frontend checks and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-delegation.test.tsx approval-task-actions.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: add approval delegation"
```

Expected: tests pass.

---

## Task 7: Escalation And SLA Tracking

**Files:**
- Create/modify SLA calculator, escalation action/job/query/controller/resource files.
- Create: `apps/api/tests/Feature/ApprovalSlaApiTest.php`
- Create frontend SLA summary hook/component/test files.
- Modify OpenAPI.

- [ ] **Step 1: Write failing SLA tests**

Create tests named:

```php
public function test_approval_task_due_date_is_set_when_stage_activates(): void {}
public function test_overdue_task_is_escalated_to_fallback_approver(): void {}
public function test_escalation_job_is_idempotent(): void {}
public function test_sla_summary_counts_due_soon_overdue_and_escalated_tasks(): void {}
public function test_admin_can_view_tenant_sla_summary_but_cross_tenant_data_is_excluded(): void {}
```

- [ ] **Step 2: Run SLA tests and confirm failure**

Run:

```bash
cd apps/api && php artisan test --filter=ApprovalSlaApiTest
```

Expected: FAIL for missing SLA summary and escalation behavior.

- [ ] **Step 3: Implement SLA calculator and escalation action**

`ApprovalSlaCalculator` must calculate due dates from policy/stage `durationMinutes` using calendar time. `EscalateOverdueApprovalTasks` must find active overdue tasks, create or assign fallback approver authority exactly once per escalation threshold, mark task escalation metadata, record `approval_task.escalated`, and send in-app notifications.

- [ ] **Step 4: Add job and summary endpoint**

Create `EscalateOverdueApprovalTasksJob` and register route:

```php
Route::get('/approvals/sla-summary', [ApprovalSlaController::class, 'summary']);
Route::get('/approval-instances/{approvalInstance}', [ApprovalSlaController::class, 'showInstance']);
```

SLA summary response keys: `assigned`, `dueSoon`, `overdue`, `escalated`, `averageAgeMinutes`, `oldestPendingApproval`.

- [ ] **Step 5: Update OpenAPI, generate client, and run backend tests**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=ApprovalSlaApiTest
```

Expected: PASS.

- [ ] **Step 6: Add SLA UI**

Create `use-approval-sla-summary.ts`, `approval-sla-summary.tsx`, overdue fixtures, handlers, and tests. Queue rows and requisition summaries must show due soon, overdue, and escalated state without relying on color alone.

- [ ] **Step 7: Run checks and commit**

Run:

```bash
pnpm --filter @cognify/web test -- approval-queue-workflow.test.tsx approval-task-actions.test.tsx
git add apps/api apps/web packages/api-client
git commit -m "feat: add approval SLA escalation"
```

Expected: tests pass.

---

## Task 8: Critical Path E2E, Demo Data, And Final Verification

**Files:**
- Create: `apps/web/tests/e2e/approval-orchestration.spec.ts`
- Modify: `apps/api/database/seeders/Demo/DemoRequisitionSeeder.php`
- Modify: `apps/api/database/seeders/Demo/DemoNotificationSeeder.php`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/components/shell/shell-route-config.ts`

- [ ] **Step 1: Add Playwright critical path**

Create `apps/web/tests/e2e/approval-orchestration.spec.ts` covering:

```ts
test("@p1-approval admin publishes policy and approver approves routed requisition", async ({ page }) => {});
test("@p1-approval approver rejects with required reason", async ({ page }) => {});
test("@p1-approval approver requests changes with required reason", async ({ page }) => {});
test("@p1-approval delegate can act on delegated task", async ({ page }) => {});
test("@p1-approval unauthorized user cannot act on another approver task", async ({ page }) => {});
```

Use existing login/test helper patterns from `apps/web/tests/e2e/requisition-authoring.spec.ts`.

- [ ] **Step 2: Register approval MSW handlers**

Modify `apps/web/tests/msw/handlers.ts`:

```ts
import { approvalHandlers } from "@/features/approvals/mocks/approval-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...approvalHandlers,
  ...requisitionsHandlers,
  ...projectHandlers,
  ...searchHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...notificationHandlers,
  ...systemReadinessHandlers,
  ...auditHandlers,
];
```

Preserve all existing handlers.

- [ ] **Step 3: Refresh demo data**

Seed at least one active approval policy, one routed pending requisition, one approved requisition, one rejected requisition, one delegated task, and one overdue task. Demo audit and notification records must deep-link to `/approvals/{taskId}` or `/requisitions/{requisitionId}`.

- [ ] **Step 4: Run full approval verification**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- approvals requisitions
cd apps/api && php artisan test --filter=Approval
cd apps/api && php artisan test --filter=RequisitionApiTest
cd apps/api && php artisan route:list --path=api
```

Expected: all commands pass and route list includes approval policy, preview, task, delegation, and SLA routes.

- [ ] **Step 5: Run E2E smoke**

Run:

```bash
pnpm --filter @cognify/web test:e2e -- --grep @p1-approval
```

Expected: approval critical path passes. If local browser setup is missing, document the exact missing dependency and run all API/web unit and integration checks instead.

- [ ] **Step 6: Architecture drift check**

Review the final diff for these exact conditions:

- no approval business logic in controllers
- no approval product types moved into `packages/types`
- no approval UI moved into `packages/ui`
- no production component imports from mocks
- OpenAPI and generated client changed together
- tenant ID is present in approval policy, version, instance, stage, task, delegation, audit, notification, and query behavior
- task actions use lock-version conflict handling
- escalation job is idempotent

- [ ] **Step 7: Final commit**

Run:

```bash
git status --short
git add apps/api apps/web packages/api-client docs
git commit -m "feat: complete approval orchestration"
```

Expected: final commit contains E2E/demo/hardening changes after slice commits.

## Completion Gate

The epic is complete when:

- all seven implementation slices have been committed,
- approval policy, preview, task lifecycle, sequential, parallel, delegation, escalation, and SLA behavior are covered by tests,
- generated client artifacts match OpenAPI,
- critical approval browser paths pass or have a documented environment blocker,
- the final architecture drift check has no findings.

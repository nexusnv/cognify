# Procurement Project Workspace Design

Date: 2026-05-15
Status: Draft for review
Source epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 3
Related roadmap: `docs/01-product/feature-roadmap.md` P1 Core Procurement Lifecycle
Architecture alignment: `ARCHITECTURE.md`
Runbook alignment: `docs/05-runbooks/feature-development.md`

## 1. Purpose

This spec defines the P1 Epic 3 design for Procurement Project Workspace. The goal is to give buyers and procurement managers a tenant-scoped project record that can group related requisitions before sourcing, approvals, quotations, awards, and purchase order handoff are fully implemented.

The epic should be intentionally narrow. Cognify already has early preview/demo project data, a `ProcurementProject` model, requisitions with a `projectId` field, global search coverage for projects, and workspace primitives from prior P0/P1 work. Epic 3 should convert that foundation into a real workflow surface without implementing approval orchestration, buyer RFQ intake, quotation evaluation, award decisions, or project risk management.

## 2. Roadmap Features Included

From `docs/01-product/feature-roadmap.md`, this epic includes exactly these P1 capabilities:

- Procurement Project Records
- Project Workspace

Adjacent roadmap capabilities are only represented as future-ready placeholders or read-only summaries when existing records already exist:

- Requisition Detail Workspace contributes related requisition links and pipeline summaries.
- Approval Tasks, RFQ Creation, Quotation Comparison, Recommendation and Award Decision, and Purchase Order Request Handoff remain out of scope.

This keeps the epic aligned with `docs/02-release-management/2026-05-15-P1-Epics.md`: two implementation slices, one for the project record foundation and one for the project workspace.

## 3. Explicit Scope

### 3.1 In Scope

- Tenant-scoped procurement project records.
- Project fields:
  - name
  - project number
  - charter or description
  - owner
  - budget amount
  - currency
  - status
  - target start date
  - target completion date
  - optional department
  - optional cost center
- Project lifecycle statuses:
  - `draft`
  - `active`
  - `on_hold`
  - `completed`
  - `cancelled`
- Project create, update, list, detail, and status-transition behavior.
- Project permissions for buyer, admin, and read-only visible participants.
- Linking and unlinking visible requisitions to projects.
- Requisition form/list/detail support for selecting and displaying project records.
- Project activity timeline entries for creation, updates, status changes, and requisition link changes.
- Project workspace route with overview, budget summary, requisition pipeline, approvals placeholder, activity, risks placeholder, and related awards placeholder.
- Project search integration using the real project contract.
- OpenAPI contract updates and generated `@cognify/api-client` consumption.
- Contract-shaped MSW handlers for frontend workflow tests.
- Demo/seed data refresh only where needed to exercise the real project workflow.

### 3.2 Out Of Scope

- Approval rule configuration, approval task lifecycle, policy preview, delegation, escalation, or SLA tracking.
- Buyer intake review, RFQ creation, vendor invitations, quotation upload, quotation comparison, scoring, recommendations, awards, and PO handoff.
- Real project risk registers, risk scoring, mitigation tasks, or AI risk insights.
- Project budget enforcement, budget reservation, or finance integration.
- Cross-project dependencies, programs, portfolios, milestones, Gantt views, or calendar scheduling.
- Comment threads on projects, unless the existing Collaboration domain is deliberately extended in a later epic.
- Moving Cognify-specific project cards, workspace copy, project badges, or procurement workflow UI into `packages/ui`.

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

Each implementation slice should be vertical. A slice is complete only when the workflow behavior, workspace UX, OpenAPI contract, MSW behavior, backend domain behavior, generated-client integration, and focused verification are all represented.

## 5. Architecture Alignment

This design preserves Cognify's established boundaries:

- `apps/web` owns project routes, workspace composition, feature hooks, MSW handlers, product-specific project components, and tests.
- `apps/api/Domains/Project` owns project models, status rules, actions, policies, resources, filters, project numbering, and project-requisition linking behavior.
- `apps/api/Domains/Requisition` remains the owner of requisition lifecycle behavior and should only expose project-linking rules where requisition state or permissions require it.
- `apps/api/app/Audit` remains the immutable audit/event infrastructure.
- `apps/api/app/Notifications` may record project events only when the existing notification foundation supports the event without widening delivery channels.
- `packages/api-client` remains generated from OpenAPI plus stable typed helpers.
- `packages/ui` remains reusable shadcn/Radix primitives only.
- No new shared package is introduced.

Frontend UI should prioritize shadcn/Radix primitive components exposed through `packages/ui` before creating custom UI. Cognify-specific composition belongs in `apps/web/features/projects`, where components can carry procurement meaning such as project status badges, budget summaries, requisition pipeline sections, and workspace action panels.

OpenAPI remains the frontend/backend contract. Any API change in this epic must update `apps/api/storage/openapi/openapi.json`, regenerate `packages/api-client`, and consume generated endpoints or schemas from the web app instead of duplicating contract response types by hand.

## 6. Business Workflow

### 6.1 Actors

| Actor | Description | Epic 3 responsibilities |
| --- | --- | --- |
| Buyer | Procurement user coordinating sourcing work. | Create projects, maintain project details, link related requisitions, monitor pipeline, and move active projects through simple statuses. |
| Admin | Tenant administrator. | Create, edit, cancel, and view all tenant projects. |
| Requester | User who creates requisitions. | Select an existing visible project on a draft or correction when allowed; view project context from linked requisitions. |
| Approver | User involved in later approval work. | View project context where linked requisitions are visible; no approval task actions in this epic. |
| System | Internal workflow actor. | Enforce tenant boundaries, permissions, status rules, audit events, generated API contracts, and search indexing behavior. |

### 6.2 Project States

| State | Meaning | Allowed actions |
| --- | --- | --- |
| `draft` | Project is being prepared and may not yet group active procurement work. | Edit, activate, cancel, link draft or submitted requisitions where policy allows. |
| `active` | Project is an operational procurement workspace. | Edit, put on hold, complete, cancel, link and unlink allowed requisitions. |
| `on_hold` | Work is paused but not terminal. | Edit limited project metadata, reactivate, cancel, keep linked requisitions visible. |
| `completed` | Project has reached its intended procurement outcome. | Read-only except limited admin metadata correction if explicitly allowed in policy. |
| `cancelled` | Project was stopped. | Read-only terminal record. |

`completed` and `cancelled` are terminal for normal users. A future admin-only correction workflow can be designed later if audit and compliance needs justify it.

### 6.3 Transitions

| Transition | Trigger | Guardrails | Audit event |
| --- | --- | --- | --- |
| create project | Buyer or admin creates a project. | Tenant membership, create permission, valid owner in same tenant, valid budget and dates. | `project.created` |
| update project | Buyer/admin edits project metadata. | Non-terminal status, update permission, owner belongs to tenant. | `project.updated` |
| `draft -> active` | Buyer/admin activates project. | Required fields valid, status is `draft`. | `project.activated` |
| `active -> on_hold` | Buyer/admin pauses project. | Reason optional in first slice, status is `active`. | `project.on_hold` |
| `on_hold -> active` | Buyer/admin resumes project. | Status is `on_hold`. | `project.reactivated` |
| `draft|active|on_hold -> cancelled` | Buyer/admin cancels project. | Reason required, status is not terminal. | `project.cancelled` |
| `active|on_hold -> completed` | Buyer/admin completes project. | Status is not terminal; later award checks are not required yet. | `project.completed` |
| link requisition | Buyer/admin or requester selects project for a requisition where allowed. | Same tenant, visible project, visible requisition, state-specific requisition edit permission. | `project.requisition_linked` and/or `requisition.project_linked` |
| unlink requisition | Buyer/admin removes project link where allowed. | Same tenant, visible project, visible requisition, requisition not terminal unless admin policy allows. | `project.requisition_unlinked` and/or `requisition.project_unlinked` |

The link/unlink behavior should preserve the requisition as the source of truth for its own lifecycle. A project groups records; it does not bypass requisition permissions or state rules.

## 7. Backend Domain Design

### 7.1 Project Domain

`apps/api/Domains/Project` becomes the owner of durable project workflow behavior.

Expected additions:

- `States/ProjectStatus.php`
- `Actions/CreateProcurementProject.php`
- `Actions/UpdateProcurementProject.php`
- `Actions/TransitionProcurementProjectStatus.php`
- `Actions/LinkRequisitionToProject.php`
- `Actions/UnlinkRequisitionFromProject.php`
- `Http/Controllers/ProcurementProjectController.php`
- `Http/Controllers/ProjectRequisitionController.php`
- `Http/Requests/*`
- `Http/Resources/ProcurementProjectResource.php`
- `Http/Resources/ProjectRequisitionResource.php`
- `Policies/ProcurementProjectPolicy.php`
- `Services/ProcurementProjectNumberGenerator.php`

Controllers should stay thin. Durable workflow rules belong in actions and policies, not controllers, routes, jobs, or Eloquent callbacks.

### 7.2 Data Model

The existing `procurement_projects` table should be reviewed before adding a new migration. The durable project table should support:

- `id`
- `tenant_id`
- `owner_id`
- `number`
- `name`
- `charter`
- `status`
- `budget_amount`
- `currency`
- `department`
- `cost_center`
- `target_start_date`
- `target_completion_date`
- `cancelled_at`
- `cancelled_by_id`
- `cancellation_reason`
- `completed_at`
- `completed_by_id`
- `metadata`
- timestamps

The implementation should prefer additive migrations over destructive rewrites because preview/demo project data may already exist. If existing seeded records use `metadata` for fields that become first-class columns, the migration or seeder should backfill deterministic values.

### 7.3 Requisition Linking

Requisitions already expose `project_id`/`projectId`. This epic should formalize the relationship instead of introducing a separate join table for the first version.

Rules:

- A requisition can belong to zero or one project.
- A project can group many requisitions.
- The project and requisition must share the same tenant.
- Requesters can select a visible project while they can edit a draft or correction.
- Buyers/admins can link or unlink visible non-terminal requisitions from the project workspace.
- Terminal requisitions remain visible in the project pipeline but are not relinked by normal users.
- Project cancellation does not cancel linked requisitions.
- Requisition withdrawal/cancellation does not cancel the project.

This keeps the model simple while preserving a future path to program/portfolio grouping if the product later needs many-to-many project relationships.

### 7.4 Search, Audit, And Notifications

The existing project search provider should move from preview-shaped behavior to contract-backed project fields. Search results should include project number, name, status, owner, budget summary, and deep link when the actor can view the project.

Audit events:

- `project.created`
- `project.updated`
- `project.activated`
- `project.on_hold`
- `project.reactivated`
- `project.completed`
- `project.cancelled`
- `project.requisition_linked`
- `project.requisition_unlinked`
- `requisition.project_linked`
- `requisition.project_unlinked`

Notifications are optional in this epic. If added, keep them in-app only and limited to owner assignment or linked-requisition changes. Do not add email, Slack, Teams, push, digest, or scheduled notifications.

## 8. API Contract Design

### 8.1 Project Endpoints

Add tenant-scoped project endpoints:

- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{projectId}`
- `PATCH /api/projects/{projectId}`
- `POST /api/projects/{projectId}/activate`
- `POST /api/projects/{projectId}/hold`
- `POST /api/projects/{projectId}/resume`
- `POST /api/projects/{projectId}/complete`
- `POST /api/projects/{projectId}/cancel`
- `GET /api/projects/{projectId}/requisitions`
- `POST /api/projects/{projectId}/requisitions`
- `DELETE /api/projects/{projectId}/requisitions/{requisitionId}`
- `GET /api/projects/{projectId}/activity`

All routes use `auth:sanctum` and tenant resolution middleware. Tenant selection endpoints remain outside tenant context as documented in `ARCHITECTURE.md`.

### 8.2 Request And Response Shapes

`ProcurementProject` response:

- `id`
- `tenantId`
- `number`
- `name`
- `charter`
- `status`
- `owner`
- `budgetAmount`
- `currency`
- `department`
- `costCenter`
- `targetStartDate`
- `targetCompletionDate`
- `createdAt`
- `updatedAt`
- `cancelledAt`
- `cancelledBy`
- `cancellationReason`
- `completedAt`
- `completedBy`
- `summary`
- `permissions`

`summary` should include:

- `estimatedRequisitionTotal`
- `linkedRequisitionCount`
- `draftRequisitionCount`
- `submittedRequisitionCount`
- `changesRequestedRequisitionCount`
- `stoppedRequisitionCount`
- `approvalPlaceholderCount`
- `awardPlaceholderCount`

`permissions` should include:

- `canUpdate`
- `canActivate`
- `canHold`
- `canResume`
- `canComplete`
- `canCancel`
- `canLinkRequisitions`
- `canUnlinkRequisitions`
- `canViewActivity`

List filters should support:

- `status`
- `ownerId`
- `department`
- `costCenter`
- `search`
- `updatedFrom`
- `updatedTo`
- `page`
- `perPage`
- `sort`

Validation and authorization errors should follow the existing API error contract. Stale status transitions should return conflict-shaped errors that the frontend can map to a refresh-and-retry state.

### 8.3 Requisition Contract Updates

Requisition responses should expose enough project context for the list, detail workspace, and form selector:

- `projectId`
- `projectSummary` with project `id`, `number`, `name`, `status`, and `owner`

Requisition create/update payloads may continue accepting `projectId`, but validation must prove the posted project belongs to the current tenant and is visible/selectable by the actor. The web app should consume generated schemas/endpoints through `@cognify/api-client`.

## 9. Frontend Workspace Design

### 9.1 Routes

Add project routes under the authenticated workspace shell:

- `apps/web/app/(workspace)/projects/page.tsx`
- `apps/web/app/(workspace)/projects/new/page.tsx`
- `apps/web/app/(workspace)/projects/[projectId]/page.tsx`

The project workspace should reuse the authenticated layout, breadcrumbs, command palette registration pattern, right-panel host, activity timeline, status badges, and data table conventions from existing requisition work.

### 9.2 Feature Folder

Create `apps/web/features/projects` with only the folders the implementation needs:

```txt
apps/web/features/projects/
  api/
  components/
  forms/
  hooks/
  mocks/
  schemas/
  tables/
  tests/
  types/
  utils/
  workflows/
```

Do not import mock fixtures directly into production components. Production components call typed hooks backed by generated clients or feature API wrappers.

### 9.3 Project List

The project list is an operational surface, not a marketing dashboard. It should use shadcn table, button, input, select, badge, dropdown menu, skeleton, alert, and pagination primitives through `packages/ui` before adding custom layout code.

Expected states:

- loading skeleton
- empty state with create action when permitted
- populated table with project number, name, status, owner, budget, linked requisitions, updated date, and row actions
- permission-denied action states
- API error state with retry
- mobile list fallback if the existing table pattern requires it

### 9.4 Project Create/Edit

Project forms should follow the existing form and validation foundation:

- inline field errors
- error summary
- visible save state
- unsaved-change guard
- generated validation mapping
- owner selector limited to tenant users
- budget amount and currency validation
- target date validation where completion date cannot precede start date

The create flow should return the user to the project workspace after successful creation.

### 9.5 Project Detail Workspace

The workspace should use the established record-focused layout pattern:

- Header: project number, name, status, owner, budget, primary actions.
- Overview: charter, dates, department, cost center, owner, lifecycle metadata.
- Budget summary: budget amount, linked requisition estimated total, remaining budget display, and non-enforcing over-budget warning if linked totals exceed budget.
- Requisition pipeline: linked requisitions grouped by status with direct links to requisition detail pages.
- Approvals placeholder: read-only section explaining that approval routing is not active yet, while showing future-ready counts only if available from existing data.
- Activity: project activity timeline.
- Risks placeholder: reserved empty section with no fake scoring or AI content.
- Related awards placeholder: reserved empty section for later award records.

Placeholders should be visually quiet and operational. They should not promise unavailable workflow actions.

### 9.6 Requisition Integration

Requisition draft and correction forms should use a project selector backed by real project list/search endpoints. The selector should:

- show project number, name, owner, and status
- exclude terminal projects unless the current requisition is already linked to one
- handle loading and error states
- preserve the rest of the form if project lookup fails
- submit only `projectId`, not a duplicated project object

Requisition list/detail pages should display project summary when present and link to the project workspace when the actor can view it.

## 10. Permissions And Tenant Rules

Backend policies are the source of truth.

Minimum policy rules:

- Buyers and admins can create projects.
- Project owners, buyers, and admins can update non-terminal projects, subject to tenant membership.
- Admins can cancel any non-terminal tenant project.
- Buyers and project owners can complete or hold active projects.
- Requesters can view projects linked to requisitions they can view.
- Approvers can view projects linked to requisitions they can view.
- Users cannot link a requisition to a project from another tenant.
- Users cannot use mention, search, or selector endpoints to discover projects outside their tenant or visibility.

Frontend permissions from API responses control visible actions, but UI hiding is not enforcement. API tests must include cross-tenant records that would fail if tenant filters were missing.

## 11. Error Handling

Expected user-facing failures:

- validation error on invalid project fields
- permission denial on create/update/status/link actions
- not found for inaccessible or missing projects
- conflict when a status transition is stale
- project selector lookup failure inside requisition forms
- requisition link failure when requisition state changed

The frontend should preserve local form input after validation or transient errors. Browser storage writes, if any are added for recent project navigation, must be best-effort and wrapped so they cannot break the core flow.

## 12. Testing And Verification

### 12.1 API Tests

Add focused Laravel feature tests for:

- project create/update/list/detail
- status transitions and terminal-state protection
- project owner must belong to the tenant
- project-requisition link and unlink behavior
- cross-tenant project and requisition isolation
- project activity events
- project search visibility
- requisition `projectId` validation against tenant and visibility

### 12.2 Web Tests

Add focused web tests for:

- project list loading, empty, populated, and error states
- project create/edit validation and success path
- project workspace rendering for overview, budget summary, requisition pipeline, placeholders, and activity
- project selector inside requisition form
- permission-gated action visibility
- MSW behavior matching generated contract shapes

### 12.3 Contract Verification

For OpenAPI changes:

```bash
pnpm generate:api
pnpm check:api-contract
```

Generated client diffs must be reviewed like source code because they expose drift between Laravel resources and frontend expectations.

### 12.4 Expected Narrow Checks

API:

```bash
cd apps/api
php artisan test --filter=ProcurementProject
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api/projects
```

Web:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test -- projects
pnpm --filter @cognify/web test -- requisitions
```

Root or broader checks should run if shared configuration, generated clients, or shared UI primitives change.

## 13. Implementation Slices

### Slice 1: Project Record Foundation

Goal: make project records real, tenant-scoped, permissioned, searchable, and linkable to requisitions.

Includes:

- project table/model migration alignment
- project status enum
- project create/update/list/detail/status endpoints
- project policies
- project number generator
- project resources and OpenAPI schemas
- project search provider update
- project-requisition link/unlink backend behavior
- requisition `projectSummary` response update
- generated client regeneration
- focused API tests

Exit criteria:

- A buyer can create, edit, activate, hold, resume, complete, or cancel a project according to policy.
- A visible requisition can be linked to a visible same-tenant project without bypassing requisition state rules.
- Project records are visible in search and hidden across tenant boundaries.

### Slice 2: Project Workspace

Goal: give buyers and managers a usable project workspace that groups requisitions and exposes project-level ownership/activity before later sourcing and award workflows exist.

Includes:

- project list route and feature table
- project create/edit form
- project detail workspace route
- budget summary and requisition pipeline
- approvals, risks, and awards placeholders
- project activity timeline
- requisition form selector integration
- requisition list/detail project summary links
- MSW handlers and web tests
- demo data refresh where useful

Exit criteria:

- Buyers can create a project, link requisitions, open the project workspace, and see budget, pipeline, ownership, and activity context.
- Requesters can select a visible active project while editing a draft or correction.
- Placeholder surfaces do not imply unavailable approval, risk, sourcing, award, or handoff actions.

## 14. Self-Review Notes

- No approval orchestration, RFQ, quotation, award, PO handoff, or real risk-management behavior is included.
- The epic uses the two roadmap capabilities assigned to Procurement Project Workspace.
- The design keeps project business behavior in `apps/api/Domains/Project` and Cognify-specific UI in `apps/web/features/projects`.
- UI composition explicitly prioritizes shadcn/Radix primitives through `packages/ui`.
- Requisition linking is intentionally one project per requisition for the first version, matching the existing `projectId` field.
- OpenAPI and generated-client workflow is required for all contract changes.

# Cognify Requisition Draft and Submission Implementation Plan

> **For agentic workers:** REQUIRED APPROACH: Follow `docs/05-runbooks/feature-development.md`. Use sub-agent-driven development for parallelizable implementation, with disjoint ownership across backend, API contract/client, and frontend workflow files. Do not move Cognify business behavior into shared packages.

## Changelog

- 2026-05-10: Initial high-fidelity implementation plan from the approved design spec.

## Source Spec

- `docs/superpowers/specs/2026-05-10-requisition-draft-submission-design.md`

## Goal

Implement the first Cognify product feature slice:

```txt
Tenant/Auth baseline
  -> Requisition draft
  -> Submit requisition
  -> Requisition detail workspace
  -> Basic approval readiness
```

This feature gives a tenant member the ability to create, save, view, and submit a procurement requisition. It establishes the root workflow object for later quotation, approval, evidence, AI scoring, and reporting features.

## Development Flow

Implementation must follow this order:

```txt
Workflow map
  -> OpenAPI contract
  -> Generated API client
  -> MSW-backed frontend workflow
  -> Laravel domain implementation
  -> Real API integration
  -> Hardening and verification
```

The frontend may be built against MSW before backend completion, but all mocks must use OpenAPI-shaped payloads.

## Worktree and Branch

Use a separate worktree for feature development:

```bash
git worktree add ../cognify-requisition-draft -b feature/requisition-draft-submission origin/main
```

All implementation work should happen in `../cognify-requisition-draft`.

## Ownership Boundaries

| Area | Owner | Write Scope |
| --- | --- | --- |
| Contract/client | Contract worker | `apps/api/storage/openapi/openapi.json`, `packages/api-client/src/generated/*`, `packages/api-client/src/index.ts`, `packages/api-client/src/client.ts` only if needed |
| Backend domain | Backend worker | `apps/api/Domains/Requisition/*`, `apps/api/app/Tenancy/*`, `apps/api/app/Audit/*`, `apps/api/app/Auth/*`, `apps/api/app/Http/*`, `apps/api/database/*`, `apps/api/routes/api.php`, `apps/api/tests/*` |
| Frontend workflow | Frontend worker | `apps/web/app/(workspace)/requisitions/*`, `apps/web/app/(dashboard)/*`, `apps/web/features/requisitions/*`, `apps/web/tests/*` |
| Integration lead | Main agent | Resolve conflicts, wire cross-area seams, run verification, keep docs current |

Workers are not alone in the codebase. They must not revert edits made by others and must adapt to existing changes.

## Phase 0: Baseline Verification

- [ ] Check worktree status.
- [ ] Confirm dependencies are installed or install them.
- [ ] Run or attempt baseline checks:

```bash
pnpm --version
composer --version
pnpm typecheck
cd apps/api && php artisan test
```

- [ ] Document any missing local tooling before continuing.

## Phase 1: Contract First

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Generate: `packages/api-client/src/generated/*`
- Modify only if needed: `packages/api-client/src/index.ts`

### Contract Requirements

- [ ] Add schemas:
  - `ApiError`
  - `ValidationError`
  - `UserSummary`
  - `RequisitionStatus`
  - `RequisitionPermissions`
  - `RequisitionLineItem`
  - `Requisition`
  - `RequisitionListResponse`
  - `RequisitionActivityEvent`
  - `CreateRequisitionRequest`
  - `UpdateRequisitionRequest`
  - `SubmitRequisitionResponse`

- [ ] Add endpoints:
  - `GET /api/requisitions`
  - `POST /api/requisitions`
  - `GET /api/requisitions/{requisitionId}`
  - `PATCH /api/requisitions/{requisitionId}`
  - `POST /api/requisitions/{requisitionId}/submit`
  - `GET /api/requisitions/{requisitionId}/activity`

- [ ] Include query parameters for list:
  - `search`
  - `status`
  - `owner`
  - `neededByFrom`
  - `neededByTo`
  - `page`
  - `perPage`

- [ ] Define errors:
  - `401` unauthenticated.
  - `403` unauthorized or wrong tenant.
  - `404` not found.
  - `422` validation failure.
  - `409` invalid state transition.

- [ ] Regenerate client:

```bash
pnpm generate:api
pnpm check:api-contract
```

- [ ] Run TypeScript check:

```bash
pnpm --filter @cognify/api-client typecheck
```

## Phase 2: Backend Foundation

**Files:**

- Create/modify under `apps/api/app/Tenancy`
- Create/modify under `apps/api/app/Auth`
- Create/modify under `apps/api/app/Audit`
- Create migrations under `apps/api/database/migrations`
- Modify `apps/api/database/seeders/DatabaseSeeder.php` if useful for local smoke data

### Tenant/Auth Minimum

- [ ] Add `Tenant` model or tenancy support model.
- [ ] Add membership persistence (`tenant_user` or equivalent).
- [ ] Add current tenant resolution suitable for v1 local/API tests.
- [ ] Ensure `User` can resolve tenant memberships.
- [ ] Define role names:
  - `requester`
  - `buyer`
  - `approver`
  - `admin`

### Audit Minimum

- [ ] Add first-party audit event model/table or configure Spatie activitylog consistently.
- [ ] Implement small audit recorder service under `apps/api/app/Audit`.
- [ ] Audit payload must capture:
  - tenant ID
  - actor ID
  - event type
  - subject type
  - subject ID
  - metadata
  - occurred timestamp

## Phase 3: Backend Requisition Domain

**Files:**

Create only the files needed under:

```txt
apps/api/Domains/Requisition/
  Actions/
  Data/
  Events/
  Http/
  Models/
  Policies/
  States/
  Support/
```

### Domain Model

- [ ] Add `Requisition` model.
- [ ] Add `RequisitionLineItem` model.
- [ ] Add migrations:
  - `requisitions`
  - `requisition_line_items`

### State and Numbering

- [ ] Add `RequisitionStatus` enum or equivalent state object.
- [ ] Persist `draft` and `submitted`.
- [ ] Reserve `pending_approval` in contract/docs but do not require full approval routing.
- [ ] Add tenant-scoped requisition number generation, for example `REQ-2026-000001`.

### Actions

- [ ] Add `CreateRequisitionDraft`.
- [ ] Add `UpdateRequisitionDraft`.
- [ ] Add `SubmitRequisition`.

Action rules:

- [ ] Create requires authenticated tenant member.
- [ ] Update requires owner/admin and `draft`.
- [ ] Submit requires owner/admin, `draft`, complete required fields, and at least one complete line item.
- [ ] Submit records `submitted_at`.
- [ ] Submit emits `requisition.submitted` audit event.

### API Layer

- [ ] Add request validation classes or equivalent validation.
- [ ] Add API resources/data transformers.
- [ ] Add controller methods for list/create/detail/update/submit/activity.
- [ ] Keep controllers thin.
- [ ] Wire routes in `apps/api/routes/api.php`.

### Backend Tests

- [ ] Can create draft.
- [ ] Can update own draft.
- [ ] Cannot update submitted requisition.
- [ ] Can submit valid draft.
- [ ] Cannot submit invalid draft.
- [ ] Cannot access another tenant's requisition.
- [ ] Buyer/approver can view submitted requisitions.
- [ ] Audit events are written for create/update/submit.

Run:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

## Phase 4: Frontend API, Mocks, and View Models

**Files:**

```txt
apps/web/features/requisitions/
  api/
  hooks/
  mocks/
  schemas/
  types/
  utils/
  workflows/
```

### API/Hook Layer

- [ ] Add generated-client wrappers if needed in `api/requisitions-api.ts`.
- [ ] Add hooks:
  - `use-requisitions`
  - `use-requisition`
  - `use-save-requisition-draft`
  - `use-submit-requisition`
  - `use-requisition-activity`

### Form and View Models

- [ ] Add `requisition-form-schema.ts` using Zod or existing validation pattern.
- [ ] Add `requisition-view-model.ts` for UI-only formatting if needed.
- [ ] Add totals utility with tests:
  - line totals
  - estimated total
  - currency consistency warning if needed

### MSW

- [ ] Add fixtures covering:
  - no requisitions
  - draft requisition
  - submitted requisition
  - validation failure
  - permission failure
  - server failure

- [ ] Add handlers for all contract endpoints.
- [ ] Register handlers through existing MSW setup.
- [ ] Ensure production UI components never import fixtures directly.

## Phase 5: Frontend Screens and UX

**Files:**

```txt
apps/web/app/(workspace)/requisitions/
  page.tsx
  new/page.tsx
  [requisitionId]/page.tsx

apps/web/features/requisitions/
  components/
  forms/
  tables/
  tests/
```

Modify if needed:

- `apps/web/app/(dashboard)/dashboard/page.tsx`
- `apps/web/components/shell/dashboard-shell.tsx`
- `apps/web/components/shell/workspace-shell.tsx`

### Requisition List

- [ ] Build desktop table with columns:
  - number
  - title
  - status
  - requester
  - needed by
  - estimated total
  - updated
  - actions

- [ ] Build mobile list layout.
- [ ] Add filters:
  - search
  - status
  - owner
  - needed-by range

- [ ] Add states:
  - loading skeleton
  - empty
  - empty filtered
  - error with retry

### New Requisition Form

- [ ] Build form sections:
  - request summary
  - business justification
  - line items
  - optional context

- [ ] Add line item editor:
  - add item
  - remove item
  - quantity/unit/price inputs
  - accessible labels

- [ ] Add submission checklist.
- [ ] Add estimated total panel.
- [ ] Add save draft action.
- [ ] Add submit action and confirmation dialog.
- [ ] Show save states: unsaved, saving, saved, failed.
- [ ] Show validation summary and inline errors.
- [ ] Focus first invalid field after failed submit.

### Detail Workspace

- [ ] Build header with number, title, status, total, needed-by date.
- [ ] Build overview section.
- [ ] Build line items section.
- [ ] Build activity timeline.
- [ ] Build approval readiness placeholder without fake approval routing.
- [ ] Show permission-aware actions.

### Dashboard Touchpoints

- [ ] Add requisition summary cards:
  - drafts
  - submitted
  - needs attention

- [ ] Add primary `New requisition` action.
- [ ] Add link to requisition list.

### UI/UX Quality Gates

- [ ] Controls are at least 44px high where practical.
- [ ] No icon-only button lacks `aria-label`.
- [ ] Status badges include text.
- [ ] Error states include recovery path.
- [ ] Form labels are visible.
- [ ] Mobile layout has no horizontal scroll.
- [ ] Tables/lists have stable skeleton dimensions.
- [ ] No decorative gradients/orbs or marketing hero patterns.

Run:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

## Phase 6: Real Integration

- [ ] Point frontend hooks at generated API client.
- [ ] Keep MSW for tests only.
- [ ] Confirm API error shape maps to form errors.
- [ ] Confirm submit transition returns updated requisition and activity.
- [ ] Confirm permission fields drive UI action availability.
- [ ] Confirm wrong-tenant data is not visible.

## Phase 7: Final Verification

- [ ] Contract:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

- [ ] Frontend:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web build
```

- [ ] Backend:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

- [ ] Root checks:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

- [ ] Optional browser workflow check if Playwright browsers are available:

```bash
pnpm --filter @cognify/web test:e2e
```

## Sub-Agent Execution Plan

Use these workers after the plan is committed and a feature worktree exists.

### Worker A: Contract and Client

Owns:

- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/*`
- `packages/api-client/src/index.ts`

Deliver:

- Complete requisition OpenAPI contract.
- Regenerated Orval client.
- Contract/typecheck notes.

### Worker B: Backend Domain

Owns:

- `apps/api/Domains/Requisition/*`
- `apps/api/app/Tenancy/*`
- `apps/api/app/Auth/*`
- `apps/api/app/Audit/*`
- `apps/api/app/Http/*`
- `apps/api/database/*`
- `apps/api/routes/api.php`
- `apps/api/tests/*`

Deliver:

- Tenant/auth minimum.
- Requisition domain implementation.
- API routes/controllers/resources.
- Feature and policy tests.

### Worker C: Frontend Workflow

Owns:

- `apps/web/app/(workspace)/requisitions/*`
- `apps/web/app/(dashboard)/*`
- `apps/web/features/requisitions/*`
- `apps/web/tests/*`

Deliver:

- MSW-backed requisition list, create, and detail flows.
- Form validation and submit dialog.
- Workflow tests.
- UI/UX checklist notes.

### Main Agent: Integration

Owns:

- Final integration between workers.
- Conflict resolution.
- Verification.
- Documentation updates.
- Final commit/PR preparation.

## Acceptance Criteria

- [ ] Tenant member can create a requisition draft.
- [ ] Requester can save draft changes and line items.
- [ ] Requester can submit a valid draft.
- [ ] Invalid submit shows accessible field-specific errors.
- [ ] Submitted requisition is visible in list and detail.
- [ ] Activity timeline shows create/update/submit events.
- [ ] API contract documents all feature endpoints and schemas.
- [ ] Orval client is regenerated.
- [ ] Backend tests cover state, policy, tenant, and audit behavior.
- [ ] Frontend tests cover list, form, detail, and submit interaction states.
- [ ] UI follows the approved operational SaaS direction.

# Feature Development Runbook

## Changelog

- 2026-05-10: Added workflow-first, contract-first feature development runbook.

## Purpose

Use this runbook whenever a human developer or agentic coding agent adds a Cognify feature. Cognify is a workflow-oriented procurement SaaS, so features should be built as vertical workflow slices instead of isolated pages, controllers, or database tables.

## Core Principle

Develop in this order:

```txt
Business workflow
  -> Workspace UX
  -> API contract
  -> Mocked frontend workflow
  -> Backend domain behavior
  -> Real API integration
  -> Hardening and observability
```

This does not mean the frontend must be finished before the backend starts. It means the workflow and contract should lead the implementation so frontend and backend work converge on the same behavior.

## Repo Boundaries

Keep feature work inside the established Cognify boundaries:

| Path | Owns |
| --- | --- |
| `apps/web` | App routes, shells, workflows, feature UI, hooks, MSW handlers, product-specific composition |
| `apps/api/Domains/*` | Business rules, actions, states, events, jobs, policies, workflow behavior |
| `apps/api/app/*` | Laravel framework integration, tenancy, auth, audit, observability, shared infrastructure |
| `packages/api-client` | Orval-generated client and typed client helpers |
| `packages/ui` | Reusable shadcn/Radix primitives only, without Cognify business meaning |
| `packages/schemas` | Stable shared schemas when a contract must be reused across apps/packages |
| `packages/types` | Stable TypeScript contracts that are not generated from OpenAPI |
| `packages/config` | Shared TypeScript, Tailwind, and tooling config |

Do not introduce new shared packages for a feature unless there is real cross-app reuse and the package boundary has been documented.

## Phase 0: Baseline Check

Before changing files:

1. Read `AGENTS.md`, this runbook, and the domain docs relevant to the feature.
2. Inspect the existing files that own adjacent behavior.
3. Check `git status --short --branch` and avoid overwriting unrelated work.
4. Confirm whether the feature changes API contracts, tenant-sensitive data, permissions, queues, audit trails, or AI behavior.

For agents: summarize the intended slice before editing if the work spans both `apps/web` and `apps/api`.

## Phase 1: Workflow Map

Write down the workflow before coding. Keep it short, but make it explicit.

Capture:

- Actors: requester, buyer, approver, vendor, system, AI worker.
- States: draft, submitted, extracting, normalized, scored, approved, rejected, awarded, archived.
- Transitions: who or what can move the workflow forward.
- Side effects: audit events, notifications, queue jobs, file writes, external calls.
- Failure paths: validation errors, OCR failure, AI unavailable, permission denial, stale workflow state.
- Tenant and permission rules.

The workflow map should drive route shape, UI states, API payloads, backend actions, tests, and observability.

## Phase 2: Feature Slice

Build one vertical slice at a time. Prefer a narrow workflow that can become usable end to end.

Examples:

- Create requisition draft.
- Upload quotation and show extraction status.
- Run quotation comparison for one requisition.
- Approve or reject a submitted requisition.
- Record award decision with audit trail.

Avoid building an entire backend module or frontend area before proving one slice.

## Phase 3: Workspace UX

Place Cognify-specific frontend work under `apps/web`.

Recommended structure:

```txt
apps/web/features/<feature>/
  api/
  components/
  forms/
  hooks/
  mocks/
  schemas/
  stores/
  tables/
  tests/
  types/
  utils/
  workflows/
```

Use route groups and app shells from `apps/web/app` and `apps/web/components/shell`. Keep reusable primitives in `packages/ui` only when they have no procurement-specific meaning.

Build the UX with realistic states:

- Loading, empty, populated, optimistic, error, permission-denied, and stale-state views.
- Workflow actions and disabled states.
- Audit timeline or activity summary when the workflow has meaningful transitions.
- AI insight surfaces only where they support a workflow decision.

UI components should call typed hooks. Do not import mock fixtures directly into production components.

## Phase 4: API Contract

Define or update the API contract before backend implementation hardens.

Contract work usually touches:

- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/*`
- `packages/api-client/src/client.ts` or typed helper exports when needed
- `apps/web/features/<feature>/api/*`
- `apps/web/features/<feature>/mocks/*`

Contracts must define:

- Request and response shapes.
- Resource identifiers and tenant boundaries.
- Pagination, filtering, sorting, and search rules.
- Workflow actions and allowed state transitions.
- Error responses and validation shape.
- Async job or processing status shape when relevant.

After OpenAPI changes, run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Generated client changes should be reviewed like source code because they reveal contract drift.

## Phase 5: Mocked Frontend Workflow

Use MSW to make the frontend behave as if the backend already exists.

Allowed mock locations:

- `apps/web/tests/msw`
- `apps/web/features/<feature>/mocks`

MSW handlers should return OpenAPI-shaped responses. Include realistic state transitions, errors, and permission cases when they affect the user workflow.

The mock layer is temporary integration scaffolding, not a parallel product model. Remove or narrow handlers once real endpoints are integrated, while keeping test fixtures needed for automated tests.

## Phase 6: Backend Domain Implementation

Place business behavior in the owning domain under `apps/api/Domains/<Domain>`.

Recommended domain structure when the feature needs it:

```txt
apps/api/Domains/<Domain>/
  Actions/
  Data/
  Events/
  Jobs/
  Models/
  Policies/
  Services/
  States/
  Support/
  Tests/
```

Use only the folders the slice needs. Do not create empty architecture layers for their own sake.

Backend implementation order:

1. Data objects and validation shape.
2. State model and transition rules.
3. Policies and tenant checks.
4. Actions that perform business behavior.
5. Events, jobs, and side effects.
6. API resources/controllers that expose the contract.
7. Feature tests for contract behavior and workflow transitions.

Keep controllers thin. Do not put durable business logic in controllers, routes, jobs, or Eloquent models when a domain action/service should own it.

## Phase 7: AI Workflow Rules

AI features must be workflow-bound and auditable.

Use this shape:

```txt
Workflow event
  -> AI processing
  -> Structured output
  -> Risk or insight UI
  -> Human validation
  -> Audit trail
```

Do not add AI as a generic chat surface or decorative helper. AI output should have schema, confidence or fallback behavior where relevant, and a clear human decision point before it affects procurement outcomes.

When credentials are absent, local development should use the documented echo provider or deterministic fallback path.

## Phase 8: Integration Swap

When the backend endpoint is ready:

1. Confirm the endpoint matches OpenAPI.
2. Regenerate the API client.
3. Point feature hooks at the generated client or typed helper.
4. Remove broad MSW handlers from development paths.
5. Keep focused MSW handlers for tests.
6. Verify loading, error, permission, and stale-state handling against the real API.

The UI should need minimal changes if the contract and hooks were designed correctly.

## Phase 9: Observability, Security, and Hardening

Before calling the feature complete, check:

- Tenant isolation is enforced in queries, policies, and tests.
- Authorization covers every workflow action.
- Audit events exist for important state changes.
- Queued work is idempotent or safely retryable.
- Files and evidence records use the approved storage path.
- AI and external provider failures degrade gracefully.
- API errors are shaped for frontend handling.
- Metrics or logs exist for important failures and async processing.
- Accessibility, keyboard flow, and responsive layout are acceptable.

## Verification

Run narrow checks for touched areas first.

Frontend feature:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

API feature:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

Contract change:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Shared package or config change:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

Run Playwright for critical browser workflows:

```bash
pnpm --filter @cognify/web test:e2e
```

If a required command cannot run locally, document the blocker and the highest-signal checks that did run.

## Pull Request Checklist

Every feature PR should answer:

- What workflow slice was added?
- Which actors, states, and transitions changed?
- Which API contracts changed?
- Was the Orval client regenerated?
- Which MSW handlers are temporary and which are test fixtures?
- Which backend domain owns the behavior?
- What tenant, permission, audit, queue, AI, and failure paths were tested?
- Which verification commands passed?

## Anti-Patterns

Avoid:

- Starting with database tables before workflow states are understood.
- Building all backend endpoints before any workspace UX validates the workflow.
- Building UI against ad hoc mock objects that do not match OpenAPI.
- Importing mock fixtures into production components.
- Putting Cognify business components in `packages/ui`.
- Putting domain behavior directly in Laravel controllers.
- Creating new shared packages for feature-local logic.
- Treating AI output as final without human validation and auditability.

# Cognify Architecture

## Status

- Status: Active root architecture reference
- Product: Cognify
- Last updated: 2026-05-15
- Source baseline: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

This document is the first file developers and agents should read before starting feature development, troubleshooting architecture issues, or reviewing implementation quality. The original greenfield runbook remains useful history, but this file is the root-level architecture contract for the current Cognify monorepo.

## Purpose

Cognify is a multi-tenant enterprise procurement SaaS. It combines a workflow-focused Next.js frontend, a Laravel API backend, generated API contracts, domain-oriented backend modules, and local development services for database, queues, cache, and object storage.

The architecture optimizes for:

- vertical workflow delivery instead of isolated pages or tables;
- contract-first frontend/backend integration;
- tenant-safe and permission-aware business behavior;
- auditable procurement workflows;
- manual continuity when AI or external providers fail;
- narrow shared package boundaries that are earned by reuse;
- production-grade verification before code is considered complete.

## System Map

```txt
cognify/
  apps/
    web/                     # Next.js App Router product frontend
    api/                     # Laravel API backend
  packages/
    api-client/              # Orval-generated API client and stable client helpers
    ui/                      # reusable shadcn/Radix primitives only
    config/                  # shared TypeScript, Tailwind, and tooling config
    schemas/                 # stable shared schemas when reuse is proven
    types/                   # stable shared TypeScript contracts
  docs/
    01-product/              # product context
    02-release-management/   # roadmap, epics, release planning
    03-domains/              # domain notes
    04-engineering/          # standards
    05-runbooks/             # operational runbooks
    06-architecture/         # deeper architecture notes
    07-history/              # historical records
    agentic/                 # agent-specific guidance
    superpowers/             # specs and plans
  infrastructure/
    docker/                  # local PostgreSQL, Redis, MinIO services
  tooling/
    scripts/                 # local automation
  AGENTS.md                  # lean always-loaded agent guide
  DEVELOPER_GUIDELINE.md     # human developer setup and workflow guide
  ARCHITECHTURAL.md          # this architecture reference
```

The deployable applications are `apps/web` and `apps/api`. Shared packages exist only for reusable primitives, generated contracts, and shared tooling. Feature-specific product behavior belongs in the owning app or domain, not in generic packages.

## Core Architecture Rules

These rules are mandatory unless a future architecture decision record explicitly changes them.

| Area                      | Rule                                                                                                                                            |
| ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Product naming            | Use Cognify in product copy, code, docs, tests, and examples. Do not revive legacy Atomy-Q naming.                                              |
| Frontend product behavior | Keep Cognify-specific shells, workflows, route composition, panels, and procurement UI in `apps/web`.                                           |
| Reusable UI               | Keep `packages/ui` limited to reusable shadcn/Radix primitives and business-neutral wrappers.                                                   |
| Backend business behavior | Keep business domains in `apps/api/Domains/*`.                                                                                                  |
| Backend infrastructure    | Keep Laravel framework integration, auth, tenancy, audit, observability, queues, storage, and shared services in `apps/api/app/*`.              |
| API contracts             | Use OpenAPI plus Orval. Frontend business API calls should consume `@cognify/api-client` through feature hooks or thin typed helpers.           |
| Mocks                     | UI components must not import mock fixtures directly. Use typed hooks backed by MSW handlers or generated clients.                              |
| Shared packages           | Do not create or expand shared packages for feature-local logic. A shared package needs documented cross-app or cross-domain reuse.             |
| Tenancy                   | Tenant-sensitive data must be scoped in queries, policies, request context, queues, tests, and audit metadata.                                  |
| AI                        | AI assists workflows. AI output must be structured, explainable where it affects decisions, auditable, and bypassable by manual workflow paths. |
| Completion                | A change is not complete until the right narrow tests and contract checks have passed, or blockers are documented with evidence.                |

## Application Boundaries

### `apps/web`

`apps/web` owns the browser product experience:

- App Router routes and route groups.
- Auth, dashboard, and workspace layouts.
- Cognify app shell, navigation, command palette, notification entry points, right-panel host, and workspace sidebars.
- Feature UI, forms, tables, hooks, MSW handlers, state stores, and workflow composition.
- Browser integration tests and Playwright smoke tests.

Recommended feature structure:

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

Use only the folders the slice needs. Avoid empty architecture ceremony.

### `apps/api`

`apps/api` owns the Laravel backend:

- HTTP API routes and controllers.
- Domain actions, models, policies, states, jobs, events, queries, and resources.
- Auth, tenancy, audit, notifications, queues, storage, search, AI/OCR adapters, observability, and system readiness.
- Backend feature tests and unit tests.
- OpenAPI export at `apps/api/storage/openapi/openapi.json`.

Backend business modules belong under `apps/api/Domains/<Domain>`. Laravel `app/` is not a dumping ground for business features.

### `packages/api-client`

`packages/api-client` owns generated and stable API access:

- Orval-generated endpoints and schemas.
- Stable client helpers such as request handling, error normalization, and multipart utilities.
- Exports consumed by the web app through `@cognify/api-client`.

Generated code is source of truth for frontend contract shape after OpenAPI changes. Review generated changes because they expose backend/frontend drift.

### `packages/ui`

`packages/ui` owns reusable UI primitives:

- business-neutral shadcn/Radix primitives;
- `cn()` and token utilities;
- primitive buttons, inputs, dialogs, menus, tabs, sheets, toasts, and similar controls.

It must not own Cognify-specific app shells, workflow cards, procurement copy, domain-specific badges, AI panels, command definitions, or screen-level composition.

### `packages/config`, `packages/schemas`, and `packages/types`

`packages/config` owns shared tool configuration.

`packages/schemas` and `packages/types` should stay small. Add to them only when a contract is stable and reused outside one feature. Prefer generated OpenAPI types for API shapes.

## Frontend Architecture

### Route Groups

The web app uses route groups for product modes:

```txt
apps/web/app/
  (auth)/          # login, registration, password recovery
  (dashboard)/     # authenticated dashboard and list workflows
  (workspace)/     # focused entity workspaces
  layout.tsx       # root shell, global providers, metadata
  globals.css
```

The root layout owns concerns that are genuinely global:

- HTML shell, metadata, fonts, and theme bootstrapping.
- TanStack Query provider.
- Toast host.
- Error reporting provider.
- Accessibility defaults such as skip links, focus restoration conventions, reduced-motion support, and live regions.

Authenticated product layouts own authenticated product concerns:

- dashboard navigation;
- workspace sidebars;
- command palette host;
- notification entry point;
- right-panel host;
- authenticated user and permission dependent commands.

Auth routes should opt out of product navigation and right-panel behavior by route structure, not by fragile page-level conditionals.

### Server State, Form State, and UI State

Use the right owner for state:

| State type                                     | Owner                          |
| ---------------------------------------------- | ------------------------------ |
| Server state                                   | TanStack Query                 |
| Form state                                     | React Hook Form                |
| Runtime validation                             | Zod                            |
| Small client-only UI state                     | Zustand                        |
| URL filters, table view, and query persistence | `nuqs`                         |
| API request/response shape                     | generated OpenAPI client types |

Do not mirror server state into Zustand. Zustand is appropriate for UI state such as right-panel visibility, command palette state, sidebar collapse, and unsaved-change guards.

### Feature APIs

Feature UI should call typed hooks in `apps/web/features/<feature>/api` or `hooks`. Those hooks should delegate to `@cognify/api-client` once a contract exists.

Allowed:

```txt
Component -> feature hook -> @cognify/api-client -> Laravel API
```

Avoid:

```txt
Component -> hand-written fetch -> ad hoc response type
Component -> imported mock fixture
Component -> duplicated generated response shape
```

Feature APIs may add thin wrappers for credentials, tenant headers, query invalidation, optimistic behavior, stale-state handling, and view-model convenience. They should not redefine contract shapes that already exist in generated schemas.

### Mocking

MSW is the mock system for frontend tests and mock-first workflow development.

Allowed mock locations:

```txt
apps/web/tests/msw/
apps/web/features/<feature>/mocks/
```

Mock handlers must return OpenAPI-shaped payloads. Production components must use hooks and should not know whether data came from MSW or Laravel.

Mocks should model important user-facing states:

- loading;
- empty;
- populated;
- validation error;
- permission denied;
- stale workflow state;
- async processing;
- degraded AI or provider failure.

### Accessibility and UX Quality

Product UI must be usable for repeated operational work. Favor dense, readable, predictable workflows over decorative layouts. Every feature workflow should handle:

- keyboard navigation;
- focus management around dialogs, sheets, menus, and panels;
- clear loading and disabled states;
- responsive layout for dashboard and workspace contexts;
- accessible error messaging;
- no text overlap or layout shift caused by dynamic labels;
- visible permission, conflict, or stale-state feedback where relevant.

## Backend Architecture

### Laravel `app/` Layer

`apps/api/app` owns framework and cross-cutting infrastructure:

```txt
apps/api/app/
  Auth/
  Audit/
  Exceptions/
  Foundation/
  Http/Middleware/
  Infrastructure/
  Models/
  Notifications/
  Observability/
  Providers/
  Shared/
  Support/
  Tenancy/
```

Appropriate responsibilities:

- Sanctum/session auth;
- tenant resolution and current tenant context;
- audit recording infrastructure;
- shared API error response shape;
- queue, storage, search, AI, OCR, and external provider adapters;
- observability, health, readiness, and logging;
- global middleware and service providers;
- framework-level shared models only when truly cross-domain.

Do not place durable business workflows here when a domain should own them.

### Domain Layer

Business behavior belongs under `apps/api/Domains/<Domain>`.

Current and expected domains include:

- `Ai`
- `Approval`
- `Attachment`
- `Award`
- `Demo`
- `EvidenceVault`
- `Metric`
- `Project`
- `Quotation`
- `Reporting`
- `Requisition`
- `Search`
- `Vendor`

Recommended domain shape:

```txt
apps/api/Domains/<Domain>/
  Actions/
  Data/
  Events/
  Exceptions/
  Http/
    Controllers/
    Requests/
    Resources/
  Jobs/
  Listeners/
  Models/
  Policies/
  Queries/
  Rules/
  Services/
  States/
  Support/
  ValueObjects/
  Workflows/
  routes/
  tests/
```

Use only the pieces needed by the slice.

Responsibility rules:

- Controllers are thin HTTP adapters.
- Requests validate transport input.
- Resources shape transport output.
- Actions coordinate one use case.
- Services hold reusable domain behavior.
- Queries encapsulate read-model and filtering logic.
- Policies enforce authorization.
- States and workflows own allowed transitions.
- Jobs own async work and retry behavior.
- Events represent business facts after they happen.
- Models persist state but should not become workflow coordinators.

### Events, Jobs, and Queues

Use events for business facts, such as requisition submission, quotation upload, comparison completion, approval request, award issuance, attachment upload, and system announcements.

Use queued jobs for expensive, slow, retryable, or failure-prone work:

- OCR extraction;
- AI enrichment;
- quotation normalization;
- comparison scoring;
- risk scoring;
- notification delivery;
- audit export generation;
- external integration calls.

Start with Laravel queues and Horizon. Split workers into separate deployable services only after operational isolation is justified by real workload, security, or scaling needs.

Queued work must be tenant-aware and safely retryable. Jobs that mutate business state should be idempotent or guard against duplicate side effects.

## Contract-First Integration

OpenAPI is the durable frontend/backend boundary.

Core files:

```txt
apps/api/storage/openapi/openapi.json
packages/api-client/orval.config.ts
packages/api-client/src/generated/
packages/api-client/src/client.ts
apps/web/features/*/api/
apps/web/features/*/mocks/
```

Feature contract work should define:

- request and response shapes;
- resource identifiers;
- tenant and authorization boundaries;
- pagination, sorting, filtering, and search rules;
- workflow actions and state transitions;
- validation errors and API error response shape;
- async processing status;
- file upload metadata;
- audit and notification side effects when externally visible.

After API contract changes, run:

```bash
pnpm generate:api
pnpm check:api-contract
```

`pnpm check:api-contract` regenerates or validates generated artifacts. Treat changed generated files as meaningful architecture evidence, not as noise.

## Tenancy Architecture

Cognify starts with single-database tenant isolation:

- tenant-scoped models;
- tenant context middleware;
- tenant-aware policies;
- tenant-safe query filters;
- tenant metadata in audit events;
- tenant-aware queues and notifications;
- cross-tenant denial tests for sensitive paths.

Enterprise database-per-tenant isolation is a future option, not the default architecture. Do not introduce tenancy packages or database splitting until there is a signed customer, security, or operational requirement that justifies the complexity.

Tenant-sensitive endpoints must prove three things:

1. The current user is authenticated.
2. The current user belongs to the tenant or has explicit system authority.
3. The requested resource belongs to the tenant context.

Tenant-selection endpoints are special because they establish tenant context. They must validate membership before setting active tenant state and must not require an existing tenant context when their purpose is to create one.

## Authorization, Audit, and Compliance

Procurement workflows are compliance-sensitive. Important user or system actions should be policy-checked and auditable.

Audit entries should capture:

- tenant ID;
- actor ID and actor type;
- request or correlation ID;
- domain and entity;
- action or event type;
- before and after values where appropriate;
- source: user, system, AI, import, or integration;
- evidence references;
- relevant metadata for troubleshooting and compliance review.

Do not use full event sourcing as a substitute for audit logs unless replayable domain state becomes an explicit requirement. Auditability is mandatory; event sourcing is optional.

## Files, Attachments, and Evidence

File and evidence workflows must be treated as domain behavior plus infrastructure storage:

- domain owns who may attach, view, replace, or delete files;
- infrastructure owns storage adapters and low-level persistence;
- metadata must include tenant, parent subject, uploader, MIME type, size, checksum where available, and storage path;
- deletes must enforce tenant ownership and remove stored bytes when required by the workflow;
- file upload contracts must preserve field-specific metadata such as filename, MIME type, and size.

Only actual browser `File` objects should carry filenames in frontend multipart helpers. Plain `Blob` values should not be assumed to have a meaningful filename.

## AI and OCR Architecture

AI and OCR are adapter-based infrastructure with domain-owned use cases.

Infrastructure examples:

```txt
apps/api/app/Infrastructure/Ai/
apps/api/app/Infrastructure/Ocr/
```

Domain examples:

```txt
apps/api/Domains/Quotation/Actions/
apps/api/Domains/Requisition/Actions/
apps/api/Domains/Vendor/Actions/
```

Rules:

- AI output must be schema-validated.
- AI output that affects procurement decisions must be explainable or evidence-backed.
- Confidence, fallback, or degraded-state behavior must be explicit where relevant.
- Manual workflow continuity is mandatory.
- Missing AI credentials in local development should use the documented echo or fake provider.
- Do not add generic AI chat surfaces unless a workflow requires them.
- Do not silently fail over AI providers for procurement decisions.

## Search and Reporting

Search, reporting, and metric features must preserve tenant and permission boundaries. Shared search or metric infrastructure can live under `app/Infrastructure` or a dedicated domain, but business meaning and visibility rules belong to the owning domain or query policy.

Search results should expose only resource types and records the current actor can view. Regression tests should use data that would actually reveal broken tenant or permission filtering.

## Notifications

Notifications are workflow side effects, not a separate product model detached from domain events.

Notification rules:

- record notifications from explicit workflow events;
- keep event type, subject, actor, tenant, priority, status, and metadata structured;
- respect user preferences where applicable;
- expose notifications through generated API contracts;
- keep web notification UI in `apps/web/features/notifications` and shell integration in `apps/web/components`.

Initial notification delivery is in-app. Additional channels should be introduced only after their delivery, preference, retry, and audit behavior is specified.

## Local Development Architecture

Local development uses Docker-backed services and local application servers.

Expected services:

| Service        | Local endpoint   | Purpose                      |
| -------------- | ---------------- | ---------------------------- |
| PostgreSQL     | `127.0.0.1:5433` | primary development database |
| Redis          | `127.0.0.1:6381` | queues, cache, rate limits   |
| MinIO API      | `127.0.0.1:9002` | local object storage         |
| MinIO console  | `127.0.0.1:9003` | object storage inspection    |
| Laravel API    | `127.0.0.1:8890` | backend API                  |
| Next.js web    | `127.0.0.1:8880` | frontend app                 |
| Optional proxy | `127.0.0.1:3001` | same-origin manual testing   |

Common commands:

```bash
pnpm install
pnpm dev:services
pnpm dev:services:down
pnpm dev:reset
pnpm dev:proxy
```

API setup and testing:

```bash
cd apps/api
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8890
php artisan test
php artisan route:list --path=api
```

Web development:

```bash
pnpm --filter @cognify/web dev
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web test:e2e
```

## Feature Development Workflow

Build features as vertical workflow slices:

```txt
Business workflow
  -> Workspace UX
  -> API contract
  -> Mocked frontend workflow
  -> Backend domain behavior
  -> Real API integration
  -> Hardening and observability
```

Before editing:

1. Read `AGENTS.md`, this file, `docs/05-runbooks/feature-development.md`, and relevant domain docs.
2. Inspect adjacent files that already own the behavior.
3. Check `git status --short --branch`.
4. Identify whether the slice touches contracts, tenancy, permissions, queues, audit trails, files, notifications, AI, or external providers.

For each feature slice, capture:

- actors;
- states;
- transitions;
- commands and side effects;
- failure paths;
- tenant and permission rules;
- audit and notification behavior;
- contract changes;
- verification commands.

Do not build a whole backend module or frontend area before proving one usable workflow slice.

## Testing and Verification

Testing should match risk and blast radius.

Frontend priorities:

- feature integration tests for forms, tables, workflows, panels, and hooks;
- MSW-backed tests for realistic API states;
- Playwright smoke coverage for critical browser workflows;
- focused unit tests for pure utilities, schemas, and state stores;
- accessibility checks where keyboard flow, dialogs, panels, or workflow decisions are involved.

Backend priorities:

- domain action tests;
- feature/API tests for contract behavior;
- authorization and tenant isolation tests;
- queue/job tests;
- audit trail tests;
- storage tests for attachment behavior;
- AI/OCR adapter tests with fakes.

Baseline commands:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
pnpm api:test
pnpm api:routes
pnpm generate:api
pnpm check:api-contract
```

Run the narrow checks for touched files first. Run broader checks when shared config, generated clients, contracts, package boundaries, providers, or cross-domain behavior changes.

If a command cannot run locally, document the exact blocker and the highest-signal commands that did run.

## Security Baseline

Cognify security work starts with these defaults:

- authenticated routes use Laravel Sanctum/session expectations;
- tenant context is explicit and validated;
- policy checks protect every workflow action;
- API errors preserve useful request IDs without leaking sensitive internals;
- file uploads validate size, MIME type, ownership, and storage metadata;
- external provider failures do not disclose secrets;
- browser storage writes are best-effort and must not break core flows if storage is unavailable;
- generated API clients should not bypass credential, CSRF, tenant, or error-handling conventions;
- secrets stay out of source control and generated artifacts.

For auth/session changes, prove the real route middleware stack with login/logout tests. Do not rely only on `actingAs()` when the behavior depends on Sanctum, cookies, sessions, or CSRF.

## Observability and Operations

Production-grade features need enough observability to troubleshoot workflow failures.

Use:

- request IDs and correlation IDs;
- structured logs for workflow transitions and async failures;
- health and readiness endpoints;
- audit events for compliance-sensitive actions;
- queue visibility through Horizon;
- explicit degraded-state UX when AI, OCR, storage, or external integrations fail.

Important async paths should expose user-visible status when the user needs to know whether work is queued, processing, failed, or complete.

## Dependency and Package Policy

Add dependencies only when the current slice needs them. Every new external package should have:

- a documented owner;
- one config location;
- at least one smoke test or verification command;
- an explanation of why the dependency belongs in Cognify rather than a local helper;
- a boundary note if it touches domain behavior.

First-party Nexus packages may be used when their mechanical responsibility is needed, but Cognify must retain domain meaning, persistence, authorization, workflow policy, UI language, and audit decisions.

Examples:

| Package                            | Appropriate use                         | Cognify retains                                   |
| ---------------------------------- | --------------------------------------- | ------------------------------------------------- |
| `azaharizaman/nexus-metric-engine` | KPI and formula calculation primitives  | reporting meaning, persistence, dashboards        |
| `azaharizaman/nexus-sequencing`    | atomic reference-code generation        | pattern policy, tenant rules, display semantics   |
| `azaharizaman/nexus-uom`           | unit conversion and quantity arithmetic | procurement catalog rules                         |
| `azaharizaman/nexus-telemetry`     | telemetry mechanics                     | dashboards, alert routing, customer-facing status |
| `azaharizaman/nexus-sanction`      | sanctions and PEP screening mechanics   | workflow timing, decisions, overrides, audit      |
| `azaharizaman/nexus-connector`     | resilient external API communication    | provider policy, business retries, disclosure     |
| `azaharizaman/nexus-idempotency`   | request deduplication mechanics         | endpoint policy, retry UX, audit messaging        |

## Documentation Rules

Update documentation when changing:

- package or app boundaries;
- domain ownership;
- local setup or commands;
- API contract workflow;
- tenant, auth, permission, audit, or queue behavior;
- AI or external provider behavior;
- development or verification expectations.

Root documents should stay concise and navigational. Detailed historical planning belongs in `docs/superpowers`. Operational runbooks belong in `docs/05-runbooks`. Architecture deep dives belong in `docs/06-architecture`.

## Troubleshooting Architecture Issues

Use this checklist when something feels wrong:

1. Is the behavior in the correct owner: web feature, backend domain, infrastructure, generated client, or shared package?
2. Is the frontend consuming generated contract types instead of duplicated shapes?
3. Did OpenAPI and `packages/api-client` change together?
4. Is tenant context applied in queries, policies, jobs, tests, and audit metadata?
5. Are UI components importing mocks or business data directly?
6. Is a shared package carrying Cognify-specific business meaning?
7. Are controllers, routes, jobs, or Eloquent models coordinating workflow logic that belongs in an action or service?
8. Are async paths idempotent and observable?
9. Does the local failure come from environment setup, stale generated code, missing services, or real product code?
10. Did the verification set exercise the actual broken behavior?

## Definition of Done

A feature, fix, or architecture change is done only when:

- Cognify naming is consistent.
- Ownership boundaries in this file are respected.
- Tenant and authorization behavior are tested where relevant.
- API contracts and generated clients are aligned.
- Frontend UI uses typed hooks and avoids direct mock imports.
- Backend business logic sits in the owning domain.
- Audit, queue, file, notification, and AI side effects are handled where relevant.
- Manual fallback exists for AI-assisted procurement decisions.
- Documentation is updated for new architecture rules or changed workflows.
- Narrow and relevant verification commands passed, or blockers are documented.

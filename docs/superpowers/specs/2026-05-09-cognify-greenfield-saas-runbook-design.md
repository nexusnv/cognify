# Cognify Greenfield SaaS Runbook Design

Date: 2026-05-09
Status: Draft for review
Source input: `atomy-q DNA.md`
Target product name: Cognify

## 1. Purpose

This design spec converts the Atomy-Q DNA document into a corrected greenfield runbook for Cognify, a multi-tenant enterprise procurement SaaS. The DNA document is useful as a product and architecture brief, but it should not be followed blindly. Cognify should start from a clean scaffold, reuse only the durable Atomy-Q lessons, and avoid premature package boundaries, tenancy complexity, and shared abstractions that are not yet justified by real reuse.

This spec defines the target setup, folder structure, dependency installation order, external package configuration, development strategy, testing strategy, and agent/developer guidance files that should exist before implementation begins.

## 1.1 Requested Deliverables Covered

This spec covers these requested outputs:

- setup runbook.
- folder structuring runbook.
- dependency installation runbook.
- external package configuration runbook.
- development strategy.
- testing strategy.
- `AGENTS.md` tuning plan.
- `docs/agentic/AGENTIC_CODING_GUIDELINES.md` plan. The request used `AGENTINC_CODING_GUIDELINE`; this spec standardizes the file as `AGENTIC_CODING_GUIDELINES.md`.
- `DEVELOPER_GUIDELINE.md` plan.
- Cognify rename strategy from the source document's Atomy-Q wording.

## 2. Corrected Reading of the DNA Document

### 2.1 Keep

- Next.js App Router frontend with TypeScript, shadcn/ui, Radix UI, Tailwind CSS, TanStack Query, React Hook Form, Zod, Zustand where useful, MSW for mock-first development, and Orval-generated API clients.
- Laravel API backend with PostgreSQL, Sanctum, queue workers, modular domains, event-driven workflow processing, auditability, and multi-tenant readiness.
- Monorepo shape using pnpm workspaces and Turborepo.
- Workspace-centric SaaS UX with separate auth, dashboard, and focused record workspace layouts.
- Contract-first frontend/backend integration through OpenAPI and generated clients.
- Feature-owned tests and high-value workflow E2E tests rather than shallow snapshot-heavy coverage.
- Lean root `AGENTS.md` plus deeper agentic and developer guidance docs.

### 2.2 Change

- Rename all product references from Atomy-Q to Cognify.
- Do not make `packages/ui` the home of every shell, workflow, and app-specific component. Start with shadcn primitives and genuinely reusable UI primitives. Keep Cognify-specific shells, AI panels, workflows, and procurement screens inside `apps/web` until reuse is proven.
- Do not create too many shared packages on day one. Start with a small package set and add packages only when the boundary is stable.
- Do not hard-commit to database-per-tenant or hybrid tenancy at scaffold time. Start with tenant isolation in the data model and tenant context. Keep the migration path open for enterprise tenant database isolation.
- Do not put all business routes/controllers/services under Laravel `app/`. Business domains belong in `apps/api/Domains/*`; `app/` is the framework kernel and shared infrastructure layer.
- Do not let AI features block manual procurement continuity. AI should assist and explain; manual workflows must remain available.
- Do not treat workers as a separate app immediately unless operational isolation requires it. Start with Laravel queues and Horizon; split workers later when OCR/AI workloads need independent deployment or scaling.

## 3. Target Monorepo Structure

```txt
cognify/
  apps/
    web/                         # Next.js App Router frontend
    api/                         # Laravel API backend
  packages/
    ui/                          # reusable UI primitives only
    api-client/                  # Orval-generated client and typed wrappers
    config/                      # shared eslint, prettier, tsconfig, tailwind presets
    schemas/                     # shared Zod/OpenAPI-adjacent schemas when stable
    types/                       # generated/shared TypeScript contracts when needed
  docs/
    README.md
    INDEX.md
    CURRENT_STATE.md
    01-product/
    02-release-management/
    03-domains/
    04-engineering/
      standards/
        coding-standards.md
        branching-strategy.md
        definition-of-done.md
        testing-strategy.md
        security-baseline.md
    05-runbooks/
    06-architecture/
    07-history/
    agentic/
      AGENTIC_CODING_GUIDELINES.md
      AGENT_LEARNINGS.md
  infrastructure/
    docker/
    github/
    terraform/
    k8s/
  tooling/
    scripts/
    generators/
    codemods/
  docs/superpowers/
    specs/
      2026-05-09-cognify-greenfield-saas-runbook-design.md
    plans/
  AGENTS.md
  DEVELOPER_GUIDELINE.md
  pnpm-workspace.yaml
  turbo.json
  package.json
```

Rationale: this gives Cognify a clean monorepo without pretending every future concern needs a package immediately. `apps` are deployable products. `packages` are reusable contracts and primitives. `docs` is the operating manual for product, engineering, release, architecture, and agent execution.

## 4. Setup Runbook

### 4.1 Repository Baseline

1. Initialize Git and create the base docs/spec path.
2. Configure the GitHub remote as `github.com/nexusnv/cognify`.
3. Use `main` as the base branch.
4. Protect `main` with pull-request-only changes, required review, and required CI checks before merge.
5. Create `package.json`, `pnpm-workspace.yaml`, and `turbo.json`.
6. Pin Node via `.nvmrc` or `.tool-versions`.
7. Add root `.editorconfig`, `.gitignore`, `.prettierrc`, and root TypeScript/ESLint config only after the frontend package exists.
8. Create `apps/web`, `apps/api`, `packages/ui`, `packages/api-client`, `packages/config`, and docs skeleton.

### 4.2 Frontend Scaffold

Preferred scaffold:

```bash
pnpm create next-app apps/web --ts --tailwind --eslint --app --src-dir false
```

Then initialize shadcn:

```bash
pnpm dlx shadcn@latest init --monorepo --template next
```

Then install:

```bash
pnpm --filter web add @tanstack/react-query zustand react-hook-form zod @hookform/resolvers
pnpm --filter web add @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-popover @radix-ui/react-tooltip
pnpm --filter web add lucide-react sonner cmdk nuqs
pnpm --filter web add @tanstack/react-table @tanstack/react-virtual
pnpm --filter web add -D vitest @testing-library/react @testing-library/user-event jsdom msw @playwright/test axe-core
```

Initialize shadcn from `apps/web`, but keep reusable primitives separated from application components from the beginning. The preferred setup is:

```txt
packages/ui/src/components/        # shadcn primitives and reusable UI wrappers
packages/ui/src/lib/               # cn(), token utilities, primitive helpers
apps/web/components/               # Cognify app shell and app-specific composition
apps/web/features/*/components/    # feature-owned procurement UI
```

This reduces developer confusion and prevents generated primitives from overwriting application components. If the shadcn monorepo workflow creates local app primitives first, the implementation plan must immediately normalize aliases and move primitives into `packages/ui` before feature work begins.

### 4.3 Backend Scaffold

Preferred scaffold:

```bash
composer create-project laravel/laravel apps/api
```

Install core backend dependencies:

```bash
cd apps/api
composer require laravel/sanctum laravel/horizon spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-data
composer require spatie/laravel-model-states
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
```

Add later only when needed:

- `laravel/pulse` for production runtime insight.
- `laravel/reverb` for realtime notifications.
- `spatie/laravel-event-sourcing` only if append-only event sourcing becomes a real product requirement. Do not use it merely for audit logs.
- `stancl/tenancy` only after the tenant isolation model is chosen and validated.

### 4.4 API Contract Generation

OpenAPI should be generated or maintained from the Laravel API and consumed by Orval:

```txt
apps/api/storage/openapi/openapi.json       # generated or exported API contract
packages/api-client/src/generated/          # Orval output
packages/api-client/src/client.ts           # stable typed wrapper surface
apps/web/features/*/api/                    # feature-owned hooks using api-client
```

Install Orval:

```bash
pnpm add -D orval
```

Rule: frontend code must not hand-write fetch calls for business APIs once a contract exists. Use Orval and typed feature hooks.

### 4.5 External Package Configuration

The first implementation plan should configure packages in this order:

| Package | Configuration owner | Required setup |
| --- | --- | --- |
| shadcn/ui | `apps/web/components.json` | aliases, Tailwind path, component path, utility path, icon library |
| Tailwind CSS | `apps/web/tailwind.config.ts`, `packages/config` later | content paths across app and promoted UI package |
| TanStack Query | `apps/web/components/providers` | query client provider, retry policy, error handling defaults |
| MSW | `apps/web/tests/msw`, `apps/web/features/*/mocks` | dev/test-only startup, handler registry, production import guard |
| Orval | root or `packages/api-client/orval.config.ts` | OpenAPI input, generated output, mutator/client config |
| Sanctum | `apps/api/config/sanctum.php` | stateful domains, CORS, CSRF/session expectations |
| Horizon | `apps/api/config/horizon.php` | queue groups, supervisor names, environment-specific workers |
| Spatie Permission | `apps/api/config/permission.php` | tenant-aware roles/permissions, cache invalidation, policy integration |
| Spatie Activitylog | `apps/api/config/activitylog.php` | audit defaults, actor metadata, tenant metadata |
| Laravel Model States | domain `States/` folders | explicit state classes for requisition, quotation, approval, and award flows |
| Pest or PHPUnit | `apps/api/phpunit.xml`, `tests/Pest.php` if Pest | test bootstrap, database isolation, tenant helpers |

### 4.6 First-Party Nexus Composer Packages

Cognify should consider the following first-party Nexus packages as preferred package candidates when their responsibilities are needed. They must remain stateless, framework-agnostic where designed that way, and interface/contract driven. Domain meaning, persistence, authorization, and UI representation stay in Cognify.

| Package | Use in Cognify | Boundary rule |
| --- | --- | --- |
| `azaharizaman/nexus-metric-engine` | Primitive calculation engine for KPI and formula evaluation. | The package evaluates prepared scalar/time-series inputs only. Cognify owns data fetching, persistence, reporting, and domain meaning. |
| `azaharizaman/nexus-sequencing` | Atomic reference-code generation for requisitions, RFQs, awards, imports, and audit exports. | The package owns sequence mechanics. Cognify owns pattern policy, tenant rules, and display semantics. |
| `azaharizaman/nexus-uom` | Unit-of-measure value objects, conversions, packaging hierarchy, and quantity arithmetic. | The package owns measurement math. Cognify owns procurement catalog meaning, validation policy, and source document interpretation. |
| `azaharizaman/nexus-telemetry` | Health checks, metrics, SLO tracking, alert evaluation, and retention primitives. | The package owns telemetry mechanics. Cognify owns operational dashboards, alert routing policy, and customer-facing status language. |
| `azaharizaman/nexus-sanction` | Sanctions screening and PEP detection for vendor governance and compliance workflows. | The package owns screening algorithms/list adapters. Cognify owns workflow timing, review decisions, audit trails, and override authority. |
| `azaharizaman/nexus-connector` | Standardized resilient gateway for external API communication. | The package owns connector mechanics. Cognify owns provider configuration, business retries, audit context, and error disclosure. |
| `azaharizaman/nexus-idempotency` | Idempotency keys for mutation safety. | The package owns request deduplication mechanics. Cognify owns endpoint policy, retry UX, and audit messaging. |

These packages should be added only when the implementation slice needs them. Each addition must include a package owner, adapter location, config location, smoke test, and a boundary note proving Cognify has not pushed application-specific business logic into the package.

Configuration rule: every external package must have one documented owner, one config file location, and at least one smoke verification command before it is considered installed.

## 5. Frontend Architecture

### 5.1 App Router Layouts

```txt
apps/web/app/
  (auth)/
    login/
    register/
    forgot-password/
    reset-password/
    layout.tsx
  (dashboard)/
    dashboard/
    requisitions/
    vendors/
    approvals/
    reporting/
    settings/
    layout.tsx
  (workspace)/
    requisitions/[requisitionId]/
    projects/[projectId]/
    quotations/[quotationId]/
    layout.tsx
  globals.css
```

The auth layout is minimal and centered. The dashboard layout owns the full navigation shell, sticky header, footer, command palette, notification entry point, and optional right panel. The workspace layout collapses global navigation and introduces an entity-specific contextual sidebar.

### 5.1.1 Root Layout and Global Providers

Cognify should have a thin root layout for concerns that are genuinely global across auth, dashboard, and workspace routes:

```txt
apps/web/app/layout.tsx
apps/web/components/providers/
  app-providers.tsx
  query-provider.tsx
  theme-provider.tsx
  analytics-provider.tsx
  accessibility-provider.tsx
  error-reporting-provider.tsx
```

The root layout should own:

- HTML shell, metadata, font loading, and theme bootstrapping.
- TanStack Query provider.
- global toast host.
- error reporting provider.
- accessibility defaults such as skip links, focus restoration conventions, reduced-motion support, and live-region host.
- analytics/user-tracking provider with privacy-aware consent gates.

The root layout should not own:

- dashboard navigation.
- workspace sidebars.
- command palette commands that require authenticated user context.
- right-panel content that only exists inside authenticated product routes.

Dashboard and workspace layouts should own the command palette host and right-panel host after authentication context is available. Auth routes should opt out of product navigation and right-panel behavior by construction, not by fragile per-page conditionals.

### 5.2 Feature Organization

```txt
apps/web/features/requisitions/
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

Use this structure for requisitions, quotations, projects, vendors, approvals, evidence vault, reporting, metrics, and AI. Domain logic stays with the feature. Shared folders are for truly cross-feature concerns only.

### 5.3 UI and Shell Boundaries

`packages/ui` should contain:

- shadcn primitives and wrappers.
- buttons, inputs, dialogs, menus, tabs, sheets, toasts.
- typography and token utilities.
- reusable table primitives only after two or more features need them.

`apps/web/components` should contain:

- Cognify app shell.
- dashboard layout.
- workspace layout.
- right-panel host.
- command palette composition.
- procurement-specific workflow cards.
- AI panels tied to Cognify product behavior.

### 5.4 Data and State

- TanStack Query owns server state.
- React Hook Form and Zod own form state and validation.
- Zustand owns small client-only UI state such as right panel, command palette, sidebar collapse, and unsaved edit tracking.
- URL state should use `nuqs` for filter, table, and view persistence.
- Do not mirror server state into Zustand.

### 5.5 Mock Strategy

MSW is the primary mock system. Mock data must never be imported directly by UI components.

Allowed:

```txt
features/*/mocks/
tests/mocks/
tests/msw/
```

Forbidden:

```ts
import { mockRequisitions } from "../mocks"
```

Required:

```ts
const { data } = useRequisitions()
```

MSW handlers should implement the same OpenAPI-shaped contract that Orval consumes. Build/lint rules should block `/mocks` imports outside tests, stories, and MSW setup.

## 6. Backend Architecture

### 6.1 Laravel `app/` Layer

`apps/api/app` is the framework kernel and shared infrastructure layer:

```txt
apps/api/app/
  Auth/
  Audit/
  Console/
  Foundation/
  Http/Middleware/
  Infrastructure/
    Ai/
    Ocr/
    Search/
    Storage/
    Queue/
  Observability/
  Providers/
  Shared/
  Support/
  Tenancy/
```

Do not place domain services, controllers, or models here unless they are truly global framework concerns.

### 6.2 Domain Layer

```txt
apps/api/Domains/
  Ai/
  Approval/
  EvidenceVault/
  Metric/
  Project/
  Quotation/
  Reporting/
  Requisition/
  Vendor/
```

Each domain should follow:

```txt
Domains/Requisition/
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

Controllers are thin. Actions coordinate one use case. Services hold reusable domain behavior. Queries encapsulate read models. Workflows own state transitions. Jobs own async processing.

### 6.3 Event and Queue Strategy

Use events for business facts:

- `RequisitionSubmitted`
- `VendorInvited`
- `QuotationUploaded`
- `QuotationNormalized`
- `ComparisonRunCompleted`
- `ApprovalRequested`
- `AwardIssued`

Use queued jobs for expensive or failure-prone work:

- OCR extraction.
- AI enrichment.
- quotation normalization.
- comparison scoring.
- risk scoring.
- notification delivery.
- audit export generation.

Start with Laravel queues and Horizon. Extract workers into separate deployable services only after queue workload isolation is proven necessary.

### 6.4 AI and OCR Architecture

AI and OCR should be adapter-based infrastructure with domain-owned use cases:

```txt
app/Infrastructure/Ai/
app/Infrastructure/Ocr/
Domains/Quotation/Actions/NormalizeQuotation.php
Domains/Requisition/Actions/GenerateRequisitionInsights.php
Domains/Vendor/Actions/ScoreVendorRisk.php
```

AI output must be structured, schema-validated, confidence-scored, and explainable. Every AI-assisted recommendation must preserve supporting evidence and manual override paths.

### 6.5 Audit Strategy

Use audit logs for operational traceability first. Do not introduce full event sourcing until Cognify has requirements for replayable domain state.

Audit entries should include:

- tenant ID.
- actor ID and actor type.
- request/correlation ID.
- domain/entity.
- before/after values where appropriate.
- source: user, system, AI, import, integration.
- evidence references.

### 6.6 Tenancy Strategy

Phase 1: single database with tenant-scoped models, tenant context middleware, policies, tests for cross-tenant denial, and tenant-aware queues.

Phase 2: enterprise isolation option with separate tenant databases only when required by signed customer/security needs.

This keeps Cognify scalable without forcing operational complexity before the product needs it.

## 7. Documentation and Guidance Files

### 7.1 Root `AGENTS.md`

Create a lean always-loaded file. It should contain:

- product identity: Cognify, not Atomy-Q.
- repo map.
- mandatory commands.
- core architecture boundaries.
- testing expectations.
- code review posture.
- links to deeper docs.

It should not contain long tutorials, broad coding standards, or historical lessons. Those belong in `docs/agentic`.

### 7.2 `docs/agentic/AGENTIC_CODING_GUIDELINES.md`

This should be the agent-facing deep guide:

- how to inspect before editing.
- how to preserve package/domain boundaries.
- how to handle mock-first frontend work.
- how to handle API contract regeneration.
- how to verify frontend and backend changes.
- how to document lessons without bloating root instructions.

### 7.3 `DEVELOPER_GUIDELINE.md`

This should be human-facing:

- local setup.
- branch strategy.
- dependency install commands.
- environment variable guide.
- development workflows.
- testing workflows.
- pull request expectations.
- troubleshooting.

### 7.4 Engineering Standards

Create live standards under `docs/04-engineering/standards/`, each with a changelog at the top:

- `coding-standards.md`
- `branching-strategy.md`
- `definition-of-done.md`
- `testing-strategy.md`
- `security-baseline.md`

## 8. Development Strategy

Use a contract-first, mock-first, vertical-slice workflow:

1. Define or update the domain workflow.
2. Define OpenAPI contract shape.
3. Generate Orval client.
4. Build UI against MSW handlers matching the contract.
5. Implement Laravel endpoint and domain action.
6. Add backend feature tests.
7. Swap UI flow to real API behind the same hook.
8. Add Playwright smoke coverage for the critical workflow.
9. Document any new domain rule.

Do not build the entire backend before the UI. Do not build UI with hardcoded data. The durable boundary is the contract.

## 9. Testing Strategy

### 9.1 Frontend

Use:

- Vitest.
- React Testing Library.
- MSW.
- Playwright.
- axe-core where accessibility risk is meaningful.

Priorities:

- feature integration tests for forms, tables, workflows, and panels.
- Playwright smoke tests for requisition lifecycle, quotation normalization, approvals, auth, and AI fallback states.
- Zod/schema validation tests for AI/OCR payloads.
- minimal unit tests for pure utilities and state stores.

Avoid:

- snapshot-heavy tests.
- testing Radix or shadcn internals.
- trivial render tests.

### 9.2 Backend

Use Pest or PHPUnit consistently. Preferred structure:

```txt
apps/api/tests/
  Feature/
  Unit/
  Support/
Domains/*/tests/
```

Priorities:

- domain action tests.
- feature/API tests for contracts.
- authorization and tenancy isolation tests.
- queued job tests.
- audit trail tests.
- AI/OCR adapter contract tests using fakes.

### 9.3 CI Gate

Minimum CI:

1. install dependencies.
2. typecheck.
3. lint.
4. backend static analysis.
5. frontend unit/integration tests.
6. backend tests.
7. build.
8. Playwright smoke tests.

## 10. Definition of Done

A scaffold or feature is not done until:

- Cognify naming is consistent.
- folder boundaries match this spec.
- no business domain logic is placed in Laravel `app/` infrastructure.
- frontend business data flows through typed hooks and API client boundaries.
- mock data is isolated behind MSW/test-only boundaries.
- tenant-aware paths include authorization tests.
- AI-assisted paths include manual fallback and explanation behavior.
- OpenAPI/Orval artifacts are regenerated when contracts change.
- docs are updated when architecture or domain rules change.

## 11. Implementation Plan Seed

After this spec is approved, the first implementation plan should be split into these phases:

1. Root monorepo scaffold and workspace tooling.
2. Documentation and agent guidance baseline.
3. Next.js web scaffold with shadcn and app shell placeholders.
4. Laravel API scaffold with modular domain provider.
5. OpenAPI/Orval generation pipeline.
6. MSW mock strategy and first requisition workflow contract.
7. Testing and CI baseline.
8. Local development runbook and verification commands.

## 12. Local Development Services and Tools

Cognify should ship a local development environment that uses Docker for backing services and local CLIs/MCPs for agent-assisted workflows.

Required development services:

| Service | Local endpoint | Purpose |
| --- | --- | --- |
| PostgreSQL | `localhost:5433`, user `postgres`, password `secret` | primary development and test database |
| Redis | `localhost:6379` | queues, cache, rate limits, Horizon |
| MinIO S3 | `localhost:9000`, access key `minioadmin`, secret key `minioadmin` | local object storage for quotations, OCR source files, evidence artifacts, and exports |

Default local database names:

- `cognify_dev`
- `cognify_test`

Default local bucket:

- `cognify-dev`

AI provider configuration:

- Use OpenRouter as the default local AI provider when credentials are present.
- Provide an echo/fake provider for development and tests so the product remains usable without live AI credentials.
- Keep the provider interface config-driven so Azure OpenAI, Anthropic, or another provider can be swapped without changing domain code.
- Never rely on silent provider failover for procurement decisions. Manual continuity and visible degraded-state UX are required.

Required local tools:

- GitHub MCP for repository and PR workflows when available.
- shadcn MCP for component lookup and registry work when available.
- Playwright for browser E2E and screenshot verification.
- `gh` CLI for GitHub operations that are not covered by MCP tools.

The setup implementation should provide `infrastructure/docker/docker-compose.yml` or equivalent, plus a `DEVELOPER_GUIDELINE.md` section that lists exact startup, migration, seed, queue, test, and teardown commands.

## 13. Open Decisions

- Choose Pest vs PHPUnit as the backend test default. Recommendation: Pest for new greenfield Cognify if the team accepts it; PHPUnit if compatibility and familiarity matter more.
- Choose tenancy package timing. Recommendation: do not install `stancl/tenancy` until Phase 2 unless tenant database isolation is immediately required.
- Choose initial AI provider. Recommendation: provider-neutral interface first, one configured provider at runtime, no silent failover, manual continuity always available.
- `packages/ui` timing is no longer open. Cognify should separate shadcn primitives into `packages/ui` from the beginning, with `apps/web` reserved for shell and application composition.

# Cognify Greenfield SaaS Scaffold Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the approved Cognify greenfield monorepo scaffold, docs baseline, local development runbook, package boundaries, and verification gates.

**Architecture:** Cognify is a pnpm/Turbo monorepo with deployable apps in `apps/`, reusable primitives/contracts in `packages/`, and operating guidance in `docs/`. The initial implementation creates a runnable frontend shell, Laravel backend baseline, package skeletons, Docker-backed local services, Orval contract plumbing, and lean agent/developer guidance without implementing full product workflows.

**Tech Stack:** pnpm, Turborepo, Next.js App Router, TypeScript, Tailwind, shadcn separation, Laravel, PostgreSQL, Redis, MinIO, Orval, Vitest, Playwright, Docker Compose.

---

## File Structure

- Create `package.json`, `pnpm-workspace.yaml`, `turbo.json`, `.editorconfig`, `.nvmrc`, `.prettierrc.json`: root workspace/tooling baseline.
- Modify `.gitignore`: preserve ignored `.superpowers`, add Node, Laravel, build, env, storage, and IDE ignores.
- Create `docs/README.md`, `docs/INDEX.md`, `docs/CURRENT_STATE.md`: docs entry points.
- Create `docs/01-product/README.md`, `docs/02-release-management/README.md`, `docs/03-domains/README.md`, `docs/04-engineering/standards/*.md`, `docs/05-runbooks/local-development.md`, `docs/06-architecture/README.md`, `docs/07-history/README.md`: operating docs baseline.
- Create `AGENTS.md`, `docs/agentic/AGENTIC_CODING_GUIDELINES.md`, `docs/agentic/AGENT_LEARNINGS.md`, `DEVELOPER_GUIDELINE.md`: agent and developer guidance.
- Create `infrastructure/docker/docker-compose.yml`, `infrastructure/docker/minio/init-bucket.sh`: local service dependencies.
- Create `packages/ui`, `packages/api-client`, `packages/config`, `packages/schemas`, `packages/types`: package boundaries.
- Create `apps/web`: Next.js app shell and provider/test baseline.
- Create `apps/api`: Laravel app with domain/infrastructure directory baseline and health endpoint.

---

### Task 1: Root Workspace Baseline

**Files:**

- Create: `package.json`
- Create: `pnpm-workspace.yaml`
- Create: `turbo.json`
- Create: `.editorconfig`
- Create: `.nvmrc`
- Create: `.prettierrc.json`
- Modify: `.gitignore`

- [ ] **Step 1: Create root workspace files**

Create root package metadata with scripts:

```json
{
  "name": "cognify",
  "private": true,
  "packageManager": "pnpm@10.33.3",
  "scripts": {
    "dev": "turbo run dev --parallel",
    "build": "turbo run build",
    "lint": "turbo run lint",
    "test": "turbo run test",
    "typecheck": "turbo run typecheck",
    "format": "prettier --write .",
    "format:check": "prettier --check .",
    "dev:services": "docker compose -f infrastructure/docker/docker-compose.yml up -d",
    "dev:services:down": "docker compose -f infrastructure/docker/docker-compose.yml down"
  },
  "devDependencies": {
    "prettier": "^3.6.2",
    "turbo": "^2.6.1",
    "typescript": "^5.9.3"
  }
}
```

- [ ] **Step 2: Create workspace and Turbo config**

Create `pnpm-workspace.yaml`:

```yaml
packages:
  - "apps/*"
  - "packages/*"
```

Create `turbo.json`:

```json
{
  "$schema": "https://turbo.build/schema.json",
  "tasks": {
    "build": {
      "dependsOn": ["^build"],
      "outputs": [".next/**", "dist/**", "build/**", "!**/.next/cache/**"]
    },
    "dev": {
      "cache": false,
      "persistent": true
    },
    "lint": {
      "dependsOn": ["^lint"]
    },
    "test": {
      "dependsOn": ["^build"],
      "outputs": ["coverage/**"]
    },
    "typecheck": {
      "dependsOn": ["^typecheck"]
    }
  }
}
```

- [ ] **Step 3: Create formatting/runtime config**

Create `.editorconfig`, `.nvmrc`, and `.prettierrc.json` with two-space JS/TS/YAML/JSON defaults, PHP four-space defaults, Node `22`, and standard Prettier settings.

- [ ] **Step 4: Expand `.gitignore`**

Keep `/.superpowers`, then add ignores for `node_modules`, `.next`, `dist`, `coverage`, `.env`, Laravel vendor/storage/cache artifacts, and IDE files.

- [ ] **Step 5: Verify root config**

Run:

```bash
pnpm install
pnpm format:check
```

Expected: dependencies install; format check may identify files to format before final verification.

---

### Task 2: Documentation and Guidance Baseline

**Files:**

- Create: `AGENTS.md`
- Create: `DEVELOPER_GUIDELINE.md`
- Create: `docs/README.md`
- Create: `docs/INDEX.md`
- Create: `docs/CURRENT_STATE.md`
- Create: `docs/agentic/AGENTIC_CODING_GUIDELINES.md`
- Create: `docs/agentic/AGENT_LEARNINGS.md`
- Create: `docs/01-product/README.md`
- Create: `docs/02-release-management/README.md`
- Create: `docs/03-domains/README.md`
- Create: `docs/04-engineering/standards/coding-standards.md`
- Create: `docs/04-engineering/standards/branching-strategy.md`
- Create: `docs/04-engineering/standards/definition-of-done.md`
- Create: `docs/04-engineering/standards/testing-strategy.md`
- Create: `docs/04-engineering/standards/security-baseline.md`
- Create: `docs/05-runbooks/local-development.md`
- Create: `docs/06-architecture/README.md`
- Create: `docs/07-history/README.md`

- [ ] **Step 1: Write lean `AGENTS.md`**

Include Cognify identity, repo map, hard architecture boundaries, common commands, verification expectations, and links to deeper docs. Keep it short.

- [ ] **Step 2: Write agentic guidelines**

Write `docs/agentic/AGENTIC_CODING_GUIDELINES.md` with inspect-before-editing, package/domain boundary rules, frontend mock boundary rules, API contract regeneration rules, and verification posture.

- [ ] **Step 3: Write developer guide**

Write `DEVELOPER_GUIDELINE.md` with prerequisites, service startup commands, frontend/backend commands, test commands, branch/PR expectations, and troubleshooting.

- [ ] **Step 4: Write docs tree entry points and standards**

Create the docs hierarchy and five engineering standards, each with a changelog section at top.

- [ ] **Step 5: Verify docs**

Run:

```bash
rg -n "Atomy-Q|TBD|TODO|FIXME" AGENTS.md DEVELOPER_GUIDELINE.md docs
```

Expected: only intentional references to source spec, no unresolved placeholders.

---

### Task 3: Local Development Services

**Files:**

- Create: `infrastructure/docker/docker-compose.yml`
- Create: `infrastructure/docker/minio/init-bucket.sh`
- Modify: `docs/05-runbooks/local-development.md`
- Modify: `DEVELOPER_GUIDELINE.md`

- [ ] **Step 1: Create Docker Compose services**

Define PostgreSQL on `5433`, Redis on `6379`, MinIO on `9000`/`9001`, and a bucket-init service for `cognify-dev`.

- [ ] **Step 2: Create MinIO bucket init script**

Create a POSIX shell script that waits for MinIO, creates `cognify-dev`, and applies a basic local-only policy.

- [ ] **Step 3: Document local env values**

Document `cognify_dev`, `cognify_test`, Redis, MinIO, OpenRouter optional config, and echo AI provider fallback in developer docs.

- [ ] **Step 4: Verify compose config**

Run:

```bash
docker compose -f infrastructure/docker/docker-compose.yml config
```

Expected: Docker Compose renders a valid config.

---

### Task 4: Package Skeletons

**Files:**

- Create: `packages/ui/package.json`
- Create: `packages/ui/src/lib/utils.ts`
- Create: `packages/ui/src/index.ts`
- Create: `packages/api-client/package.json`
- Create: `packages/api-client/orval.config.ts`
- Create: `packages/api-client/src/client.ts`
- Create: `packages/api-client/src/index.ts`
- Create: `packages/config/package.json`
- Create: `packages/config/tsconfig/base.json`
- Create: `packages/config/tsconfig/next.json`
- Create: `packages/config/tailwind/tailwind-preset.ts`
- Create: `packages/schemas/package.json`
- Create: `packages/schemas/src/index.ts`
- Create: `packages/types/package.json`
- Create: `packages/types/src/index.ts`

- [ ] **Step 1: Create package metadata**

Create package files for `@cognify/ui`, `@cognify/api-client`, `@cognify/config`, `@cognify/schemas`, and `@cognify/types`.

- [ ] **Step 2: Create UI primitive utilities**

Create `cn()` in `packages/ui/src/lib/utils.ts` using `clsx` and `tailwind-merge`; export from `packages/ui/src/index.ts`.

- [ ] **Step 3: Create API client placeholder**

Create a typed `createApiClientConfig()` helper and Orval config pointing at `apps/api/storage/openapi/openapi.json`.

- [ ] **Step 4: Create config presets**

Create TypeScript base/Next configs and a Tailwind preset placeholder.

- [ ] **Step 5: Verify package typecheck**

Run:

```bash
pnpm install
pnpm typecheck
```

Expected: package skeletons typecheck once app/package configs are in place.

---

### Task 5: Next.js Web Scaffold

**Files:**

- Create/modify: `apps/web/*`
- Create: `apps/web/components/providers/app-providers.tsx`
- Create: `apps/web/components/providers/query-provider.tsx`
- Create: `apps/web/components/providers/theme-provider.tsx`
- Create: `apps/web/components/providers/analytics-provider.tsx`
- Create: `apps/web/components/providers/accessibility-provider.tsx`
- Create: `apps/web/components/providers/error-reporting-provider.tsx`
- Create: `apps/web/components/shell/dashboard-shell.tsx`
- Create: `apps/web/components/shell/workspace-shell.tsx`
- Create: `apps/web/components/shell/right-panel-host.tsx`
- Create: `apps/web/components/shell/command-palette-host.tsx`
- Create: `apps/web/features/requisitions/{api,components,forms,hooks,mocks,schemas,stores,tables,tests,types,utils,workflows}/.gitkeep`
- Create: `apps/web/tests/msw/handlers.ts`
- Create: `apps/web/tests/msw/server.ts`
- Create: `apps/web/vitest.config.ts`
- Create: `apps/web/playwright.config.ts`

- [ ] **Step 1: Scaffold Next app**

Run:

```bash
pnpm create next-app apps/web --ts --tailwind --eslint --app --src-dir false --use-pnpm --import-alias "@/*"
```

Expected: Next app exists under `apps/web`.

- [ ] **Step 2: Install frontend dependencies**

Run the dependencies from the spec, plus `clsx` and `tailwind-merge` for UI primitives.

- [ ] **Step 3: Configure app providers and route groups**

Create root provider composition and route groups `(auth)`, `(dashboard)`, and `(workspace)` with placeholder pages.

- [ ] **Step 4: Configure shell placeholders**

Create dashboard shell, workspace shell, right-panel host, and command-palette host as application components in `apps/web/components`.

- [ ] **Step 5: Configure test baseline**

Add Vitest config and MSW setup, then create one provider smoke test.

- [ ] **Step 6: Verify frontend**

Run:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web build
```

Expected: all pass.

---

### Task 6: Laravel API Scaffold

**Files:**

- Create/modify: `apps/api/*`
- Create: `apps/api/Domains/{Ai,Approval,EvidenceVault,Metric,Project,Quotation,Reporting,Requisition,Vendor}/.gitkeep`
- Create: `apps/api/app/Infrastructure/{Ai,Ocr,Search,Storage,Queue}/.gitkeep`
- Create: `apps/api/app/{Auth,Audit,Foundation,Observability,Shared,Support,Tenancy}/.gitkeep`
- Create: `apps/api/storage/openapi/openapi.json`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/.env.example`

- [ ] **Step 1: Scaffold Laravel app**

Run:

```bash
composer create-project laravel/laravel apps/api
```

Expected: Laravel app exists under `apps/api`.

- [ ] **Step 2: Install backend dependencies**

Run:

```bash
cd apps/api
composer require laravel/sanctum laravel/horizon spatie/laravel-permission spatie/laravel-activitylog spatie/laravel-data spatie/laravel-model-states
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
```

- [ ] **Step 3: Create domain and infrastructure directories**

Create the domain and `app/` kernel directories from the spec, with `.gitkeep` where empty.

- [ ] **Step 4: Add health endpoint and OpenAPI seed**

Create a minimal `/api/health` route and a minimal OpenAPI document containing that endpoint.

- [ ] **Step 5: Configure local env example**

Update `.env.example` for PostgreSQL `5433`, Redis, MinIO, AI provider defaults, and app name `Cognify`.

- [ ] **Step 6: Verify backend**

Run:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

Expected: tests pass and `/api/health` appears.

---

### Task 7: Contract, Mock, and CI Baseline

**Files:**

- Create: `.github/workflows/ci.yml`
- Modify: `package.json`
- Modify: `packages/api-client/orval.config.ts`
- Modify: `docs/04-engineering/standards/testing-strategy.md`
- Modify: `docs/05-runbooks/local-development.md`

- [ ] **Step 1: Add contract scripts**

Add root/package scripts for `generate:api`, `check:api-contract`, and frontend generation through Orval.

- [ ] **Step 2: Add CI workflow**

Create a GitHub Actions workflow that installs pnpm, installs Composer dependencies, runs lint/typecheck/test/build, and validates Docker Compose config.

- [ ] **Step 3: Document contract and mock rules**

Update testing/runbook docs with MSW and Orval rules.

- [ ] **Step 4: Verify full baseline**

Run:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
docker compose -f infrastructure/docker/docker-compose.yml config
```

Expected: all local checks pass, or any scaffold-tool limitation is documented in the final report.

---

### Task 8: Final Review and Commit

**Files:**

- Modify: `docs/superpowers/plans/2026-05-09-cognify-greenfield-saas-scaffold.md` to mark completed checkboxes if execution keeps plan state updated.

- [ ] **Step 1: Search for unresolved placeholders**

Run:

```bash
rg -n "TBD|TODO|FIXME|Atomy-Q|atomy-q" . --glob '!atomy-q DNA.md' --glob '!node_modules/**' --glob '!vendor/**'
```

Expected: no stale product naming or unresolved placeholders in created scaffold files.

- [ ] **Step 2: Review Git diff**

Run:

```bash
git status --short
git diff --stat
```

Expected: only intended scaffold files are changed.

- [ ] **Step 3: Commit**

Run:

```bash
git add -A
git commit -m "Implement Cognify greenfield scaffold"
```

Expected: commit succeeds.

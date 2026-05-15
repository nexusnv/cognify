# Cognify

Cognify is a greenfield multi-tenant enterprise procurement SaaS. This monorepo contains a Next.js frontend, a Laravel API backend, generated API client packages, shared tooling, and local Docker services for development testing.

## Repository Layout

- `apps/web` - Next.js App Router frontend.
- `apps/api` - Laravel API backend.
- `packages/api-client` - Orval-generated API client and typed helpers.
- `packages/ui` - reusable shadcn/Radix UI primitives.
- `packages/config` - shared TypeScript, Tailwind, and tooling config.
- `packages/schemas` and `packages/types` - stable shared contracts.
- `docs` - product, engineering, architecture, runbook, and agent guidance.
- `infrastructure/docker` - local PostgreSQL, Redis, and MinIO services.

## Prerequisites

- Git
- Node.js 22 or newer
- pnpm 10.33.3
- PHP 8.3 or newer
- Composer
- Docker with Compose support

If your Docker install uses the legacy `docker-compose` binary instead of `docker compose`, run the equivalent command manually against `infrastructure/docker/docker-compose.yml`.

## Clone And Install

```bash
git clone https://github.com/nexusnv/cognify.git
cd cognify
pnpm install

cd apps/api
composer install
cp .env.example .env
php artisan key:generate
cd ../..
```

## API Environment

Set these values in `apps/api/.env` for local development:

```dotenv
APP_NAME=Cognify
APP_ENV=local
APP_VERSION=0.1.0
APP_DEBUG=true
APP_URL=http://127.0.0.1:8890

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=cognify_dev
DB_USERNAME=postgres
DB_PASSWORD=secret

SESSION_DRIVER=database
SESSION_DOMAIN=null
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8880,localhost:8880,127.0.0.1:3001,localhost:3001,127.0.0.1:8890,localhost:8890

CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6381

QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=minioadmin
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=cognify-dev
AWS_ENDPOINT=http://127.0.0.1:9002
AWS_USE_PATH_STYLE_ENDPOINT=true

AI_PROVIDER=echo
AI_PROVIDER_NAME="Echo Local"
OPENROUTER_API_KEY=
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
```

Notes:

- `DB_PORT=5433` matches the checked-in Docker Compose mapping.
- `APP_URL=http://127.0.0.1:8890` matches the API serve command below.
- `SANCTUM_STATEFUL_DOMAINS` must include the browser origin used for manual testing.
- Use `AI_PROVIDER=echo` for local development when no OpenRouter key is available.

## Web Environment

The web app currently does not require a checked-in `.env.local` for unit or component testing. Browser API calls use same-origin relative paths such as `/api/me`, so the web app must be served behind the same origin as the API proxy for manual backend testing.

Optional `apps/web/.env.local`:

```dotenv
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8880
```

Use `PLAYWRIGHT_BASE_URL` when running Playwright against the local manual-test origin. Do not add a `NEXT_PUBLIC_API_URL` unless the web client is changed to consume it.

## Start Local Services

```bash
pnpm dev:services
```

This starts:

- PostgreSQL on `127.0.0.1:5433`
- Redis on `127.0.0.1:6381`
- MinIO API on `127.0.0.1:9002`
- MinIO Console on `127.0.0.1:9003`

Stop services with:

```bash
pnpm dev:services:down
```

## Migrate And Seed Demo Data

```bash
cd apps/api
php artisan config:clear
php artisan migrate:fresh --seed
cd ../..
```

The seed creates deterministic local demo tenants, users, requisitions, vendors, projects, RFQs, quotations, approvals, awards, audit events, notifications, and sample attachment records.

Useful seeded users:

- `test@example.com` / `password` - requester, single tenant.
- `auditor@example.com` / `password` - admin, can view `/system`.
- `admin@example.com` / `password` - admin with multiple tenants.

Verify that the API is pointed at the expected database and that the seed user exists:

```bash
cd apps/api
php artisan tinker --execute='echo json_encode(["db" => config("database.connections.pgsql.database"), "host" => config("database.connections.pgsql.host"), "port" => config("database.connections.pgsql.port"), "user_exists" => \App\Models\User::where("email", "test@example.com")->exists(), "password_ok" => \Illuminate\Support\Facades\Hash::check("password", \App\Models\User::where("email", "test@example.com")->value("password"))], JSON_PRETTY_PRINT);'
cd ../..
```

The output should show `user_exists: true`, `password_ok: true`, and the same host/port you configured in `apps/api/.env`.

## Run For Manual Development Testing

Start the API:

```bash
cd apps/api
php artisan serve --host=127.0.0.1 --port=8890
```

In another terminal, start the web app:

```bash
pnpm --filter @cognify/web exec next dev --hostname 127.0.0.1 --port 8880
```

Or use the one-command local reset flow from the repo root:

```bash
pnpm dev:reset
```

That command waits for Docker services, runs `php artisan migrate:fresh --seed`, then starts the Laravel API and Next.js dev server together.

If you still want the optional same-origin development proxy, start it in a third terminal:

```bash
pnpm dev:proxy
```

Open the app:

```text
http://127.0.0.1:8880
```

The web dev server rewrites `/api/*` and `/sanctum/*` requests to `http://127.0.0.1:8890`, so login and authenticated API requests reach Laravel while the browser stays on one origin.

Then open:

```text
http://127.0.0.1:3001
```

The proxy forwards:

- page and `/_next/*` requests to `127.0.0.1:8880`
- `/api/*` and `/sanctum/*` requests to `127.0.0.1:8890`

If you customize ports, set these optional proxy environment variables:

```bash
COGNIFY_PROXY_PORT=3001 COGNIFY_WEB_PORT=8880 COGNIFY_API_PORT=8890 pnpm dev:proxy
```

For the direct Next dev server, customize the Laravel target with:

```bash
COGNIFY_API_URL=http://127.0.0.1:8890 pnpm --filter @cognify/web exec next dev --hostname 127.0.0.1 --port 8880
```

If login shows `Invalid credentials`, first confirm the Laravel API is running on the port used by `COGNIFY_API_URL` and then run the seed verification command above. The web form uses that message for login failures, but the most common local causes are an unseeded database, the API pointing at a different `DB_PORT`, the API server not running, or the web server needing a restart after config changes.

## Run Automated Checks

Full repo checks:

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

API-focused checks:

```bash
pnpm api:test
pnpm api:routes
```

Web-focused checks:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

API contract checks:

```bash
pnpm generate:api
pnpm check:api-contract
```

Run `pnpm check:api-contract` after OpenAPI changes so `packages/api-client` stays aligned with the Laravel contract.

## Useful URLs

- Web app through manual-test proxy: `http://127.0.0.1:3001`
- Next dev server directly: `http://127.0.0.1:8880`
- API health: `http://127.0.0.1:8890/api/health`
- System readiness: `http://127.0.0.1:8890/api/system/status`
- MinIO console: `http://127.0.0.1:9003`

## Development Boundaries

- Keep Cognify-specific app shells, workflows, and procurement UI in `apps/web`.
- Keep reusable UI primitives in `packages/ui`.
- Keep Laravel business domains in `apps/api/Domains/*`.
- Keep Laravel cross-cutting infrastructure in `apps/api/app/*`.
- Consume generated schemas/endpoints through `@cognify/api-client` after OpenAPI changes.

For deeper guidance, see:

- `AGENTS.md`
- `DEVELOPER_GUIDELINE.md`
- `docs/05-runbooks/local-development.md`
- `docs/05-runbooks/feature-development.md`

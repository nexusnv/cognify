# Cognify Developer Guideline

## Prerequisites

- Node.js 22
- pnpm 10.33.3
- PHP 8.3 or newer
- Composer 2
- Docker with Docker Compose
- `gh` CLI for GitHub workflows
- Playwright browsers after frontend install

## First Setup

```bash
pnpm install
pnpm dev:services
```

Backend setup after `apps/api` exists:

```bash
cd apps/api
composer install
php artisan key:generate
php artisan migrate
```

Frontend setup after `apps/web` exists:

```bash
pnpm --filter @cognify/web install
pnpm --filter @cognify/web exec playwright install
```

## Local Services

- PostgreSQL: `localhost:5433`, user `postgres`, password `secret`
- Databases: `cognify_dev`, `cognify_test`
- Redis: `localhost:6379`
- MinIO: `localhost:9000`, console `localhost:9001`
- MinIO credentials: `minioadmin` / `minioadmin`
- Default bucket: `cognify-dev`

## Development Commands

Before starting a new feature, follow `docs/05-runbooks/feature-development.md`.

```bash
pnpm dev
pnpm --filter @cognify/web dev
pnpm --filter @cognify/api-client generate
```

Backend commands:

```bash
cd apps/api
php artisan serve
php artisan queue:work
php artisan horizon
```

## Testing Commands

```bash
pnpm lint
pnpm typecheck
pnpm test
pnpm build
```

Backend:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api
```

The backend scaffold is pinned to Laravel 12 for PHP 8.3 compatibility with the selected package ecosystem. PHPUnit is the default backend test runner.

Frontend:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
pnpm --filter @cognify/web build
pnpm --filter @cognify/web test:e2e
```

## Branch and PR Rules

- Work on feature branches.
- Keep `main` protected.
- Use pull requests with review and CI checks.
- Include verification evidence in PR descriptions.

## Troubleshooting

- If PostgreSQL connection fails, check `docker compose -f infrastructure/docker/docker-compose.yml ps`.
- If MinIO upload fails, verify the `cognify-dev` bucket exists.
- If frontend contract types are stale, run `pnpm generate:api`.
- If a live AI key is missing, use the echo provider for local development.

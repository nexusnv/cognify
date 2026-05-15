# Local Development Runbook

## Changelog

- 2026-05-15: Added local demo data and system readiness notes.
- 2026-05-09: Initial runbook.

## Start Services

```bash
pnpm dev:services
```

## Stop Services

```bash
pnpm dev:services:down
```

## Services

- PostgreSQL: `localhost:5433`
- Redis: `localhost:6381`
- MinIO API: `localhost:9002`
- MinIO Console: `localhost:9003`

## Databases

- Development: `cognify_dev`
- Test: `cognify_test`

## Local Demo Data

One-command local reset and startup:

```bash
pnpm dev:reset
```

This waits for Docker services, runs `php artisan migrate:fresh --seed`, then starts the Laravel API on `127.0.0.1:8890` and the Next.js app on `127.0.0.1:8880`.

Run the local demo seed after migrations:

```bash
cd apps/api
php artisan migrate:fresh --seed
```

The seed creates deterministic Cognify demo tenants, users, requisitions, vendors, projects, RFQs, quotations, approvals, awards, audit events, notifications, and sample attachment files. It is idempotent for repeated local refreshes through the default database seeder.

Admin users can inspect local readiness in the web app at `/system`. The API readiness endpoint is `GET /api/system/status`; it reports core checks, demo seed metadata, and the API version.

Set `APP_VERSION` in `apps/api/.env` when local version metadata needs to differ from the default `0.1.0`.

## Object Storage

- Bucket: `cognify-dev`
- Access key: `minioadmin`
- Secret key: `minioadmin`

## AI Provider

Use OpenRouter when credentials are available. Use the echo provider for local development and tests when credentials are absent.

## Contract and Mock Rules

- Generate API clients with `pnpm generate:api`.
- After OpenAPI changes, run `pnpm check:api-contract` and consume generated endpoints or schemas from `@cognify/api-client`.
- Keep MSW handlers in test or feature mock folders.
- Do not import mock fixtures into production components.
- Keep system readiness mocks aligned with `GET /api/system/status` and generated system status schemas.

## Framework Notes

- The API scaffold is pinned to Laravel 12 for PHP 8.3 package compatibility with the selected Spatie package set.
- Backend tests use PHPUnit by default. Pest is not part of the initial scaffold.

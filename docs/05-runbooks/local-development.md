# Local Development Runbook

## Changelog

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
- Redis: `localhost:6379`
- MinIO API: `localhost:9000`
- MinIO Console: `localhost:9001`

## Databases

- Development: `cognify_dev`
- Test: `cognify_test`

## Object Storage

- Bucket: `cognify-dev`
- Access key: `minioadmin`
- Secret key: `minioadmin`

## AI Provider

Use OpenRouter when credentials are available. Use the echo provider for local development and tests when credentials are absent.

## Contract and Mock Rules

- Generate API clients with `pnpm generate:api`.
- Keep MSW handlers in test or feature mock folders.
- Do not import mock fixtures into production components.

## Framework Notes

- The API scaffold is pinned to Laravel 12 for PHP 8.3 package compatibility with the selected Spatie package set.
- Backend tests use PHPUnit by default. Pest is not part of the initial scaffold.

# Cognify Agentic Coding Guidelines

## Changelog

- 2026-05-12: Added implementation loopback checks from tenant auth access foundation audit.
- 2026-05-09: Initial agentic coding guide.

## Inspect First

Before editing, inspect the live files that own the behavior. Do not assume the DNA source document or older project history is current truth.

## Boundary Rules

- `apps/web` owns Cognify application composition and product workflows.
- `packages/ui` owns reusable UI primitives only.
- `apps/api/Domains/*` owns business behavior.
- `apps/api/app/*` owns framework infrastructure and shared cross-cutting services.
- `packages/api-client` owns generated API access and stable client helpers.

## Mock Strategy

MSW and mock fixtures are allowed in `apps/web/tests`, `apps/web/features/*/mocks`, and story/demo contexts. UI components must use typed hooks and must not import mock fixtures directly.

## Contract Strategy

OpenAPI is the frontend/backend contract. When API routes or payloads change, update the OpenAPI source and regenerate the Orval client.

Generated client code must be exported and consumed through `@cognify/api-client`. Feature APIs may add thin wrappers for state, credentials, tenant headers, or view-model convenience, but request and response shapes should use generated OpenAPI types so contracts do not drift.

## Implementation Loopback

When implementing from a design spec and plan:

- Convert review/audit findings into failing regression tests before production edits.
- Verify framework middleware assumptions with real route-stack tests, especially Laravel Sanctum session login/logout; do not mask missing middleware with skipped tests.
- Keep tenant-selection endpoints outside tenant-context middleware when their purpose is to establish that context.
- Persist client-side tenant context only after the API validates membership.
- Compare route middleware, generated clients, and production app imports against the plan before completion, not only test assertions.

## Verification

Run narrow checks first. Run root checks when touching shared config, package boundaries, generated clients, or provider setup.

## Documentation

Update docs when changing architecture, package boundaries, local setup, domain rules, or verification commands.

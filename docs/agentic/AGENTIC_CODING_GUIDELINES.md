# Cognify Agentic Coding Guidelines

## Changelog

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

## Verification

Run narrow checks first. Run root checks when touching shared config, package boundaries, generated clients, or provider setup.

## Documentation

Update docs when changing architecture, package boundaries, local setup, domain rules, or verification commands.

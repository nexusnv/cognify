# Cognify Current State

## Changelog

- 2026-05-15: Updated current state for the local demo data and system readiness slice.
- 2026-05-09: Created greenfield scaffold baseline from approved design spec.

## Status

Cognify is in the local demo and system readiness implementation slice for the final P0 readiness pass. The current goal is to keep deterministic demo data, readiness checks, generated contracts, mocks, and the `/system` admin surface aligned for local validation.

## Active Design Source

- `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`
- Prior P0 feature specs remain relevant context for requisitions, notifications, attachments, audit history, and search behavior.

## Active Implementation Plan

- `docs/superpowers/plans/2026-05-15-local-demo-system-readiness-implementation.md`

## Current Slice

- Local demo data is seeded through `apps/api/database/seeders/DatabaseSeeder.php` with deterministic tenants, users, requisitions, vendors, projects, RFQs, quotations, approvals, awards, audit events, notifications, and sample attachments.
- System readiness is exposed through `GET /api/system/status`, consumed from generated `@cognify/api-client` contracts, and surfaced to admins at `/system`.
- Local version metadata comes from `APP_VERSION`, defaulting to `0.1.0`.

## Local Validation Focus

- Refresh demo data with `cd apps/api && php artisan migrate:fresh --seed`.
- Keep the OpenAPI contract, generated API client, and MSW system readiness mocks synchronized after endpoint changes.

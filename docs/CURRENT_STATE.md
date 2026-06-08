# Cognify Current State

## Changelog

- 2026-06-08: Updated product direction to complete procure-to-pay SaaS and recorded that request-to-award is now the baseline before future P2P expansion.
- 2026-05-15: Updated current state for the local demo data and system readiness slice.
- 2026-05-09: Created greenfield scaffold baseline from approved design spec.

## Status

Cognify's product direction is now complete procure-to-pay SaaS. The implemented baseline covers the request-to-award procurement path through award approval, PO request handoff, and procurement calendar; the next strategic product expansion is durable purchase orders, receiving, supplier invoices, matching, payment readiness, payment status, vendor master readiness, and P2P operational queues.

## Active Design Source

- `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`
- `docs/superpowers/specs/2026-05-15-local-demo-system-readiness-design.md`
- Prior P0 and P1 feature specs remain relevant context for requisitions, notifications, attachments, audit history, search behavior, sourcing, quotation evaluation, award approval, PO handoff, and procurement calendar behavior.

## Planning Baseline

- Future P2P implementation plans should start from `docs/01-product/feature-roadmap.md`, `ARCHITECTURE.md`, and `docs/05-runbooks/feature-development.md`, then create one approved spec and implementation plan per vertical slice.

## Current Slice

- Local demo data is seeded through `apps/api/database/seeders/DatabaseSeeder.php` with deterministic tenants, users, requisitions, vendors, projects, RFQs, quotations, approvals, awards, audit events, notifications, and sample attachments.
- System readiness is exposed through `GET /api/system/status`, consumed from generated `@cognify/api-client` contracts, and surfaced to admins at `/system`.
- Local version metadata comes from `APP_VERSION`, defaulting to `0.1.0`.
- Purchase order, receiving, invoice, matching, payment readiness, and payment status records are roadmap scope and are not yet implemented as durable product domains.

## Local Validation Focus

- Refresh demo data with `cd apps/api && php artisan migrate:fresh --seed`.
- Keep the OpenAPI contract, generated API client, and MSW system readiness mocks synchronized after endpoint changes.

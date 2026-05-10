# Cognify Agent Guide

## Product

Cognify is a multi-tenant enterprise procurement SaaS. It is a greenfield codebase informed by the legacy DNA source document, but product naming, docs, code, and UX should use Cognify.

## Repo Map

- `apps/web`: Next.js App Router frontend.
- `apps/api`: Laravel API backend.
- `packages/ui`: reusable shadcn/Radix UI primitives only.
- `packages/api-client`: Orval-generated API client and typed client helpers.
- `packages/config`: shared TypeScript, Tailwind, and tooling config.
- `packages/schemas`: stable shared schemas.
- `packages/types`: stable shared TypeScript contracts.
- `docs`: product, engineering, release, architecture, runbook, and agent guidance.
- `infrastructure/docker`: local development services.

## Hard Boundaries

- Keep Cognify-specific app shells, workflows, and procurement UI in `apps/web`.
- Keep reusable UI primitives in `packages/ui`.
- Keep Laravel business domains in `apps/api/Domains/*`.
- Keep Laravel cross-cutting infrastructure in `apps/api/app/*`.
- Do not import mock fixtures directly into UI components. Use hooks backed by MSW or generated clients.
- Do not put application-specific business meaning into shared packages.

## Common Commands

- `pnpm install`
- `pnpm dev`
- `pnpm lint`
- `pnpm typecheck`
- `pnpm test`
- `pnpm build`
- `pnpm dev:services`
- `pnpm dev:services:down`

## Verification

Before claiming work is complete, run the narrow checks for the files touched and the relevant root checks when shared config changes. For API contract changes, regenerate the client and verify both API and web consumers.

## Deeper Guidance

- Agent workflow: `docs/agentic/AGENTIC_CODING_GUIDELINES.md`
- Developer workflow: `DEVELOPER_GUIDELINE.md`
- Feature development runbook: `docs/05-runbooks/feature-development.md`
- Engineering standards: `docs/04-engineering/standards/`
- Local services: `docs/05-runbooks/local-development.md`

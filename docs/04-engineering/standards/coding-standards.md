# Coding Standards

## Changelog

- 2026-05-09: Initial standard.

## General

- Prefer small files with one clear responsibility.
- Keep domain behavior near the domain that owns it.
- Avoid abstractions until reuse or complexity justifies them.

## Frontend

- Use TypeScript.
- Use typed API hooks.
- Keep app-specific UI out of `packages/ui`.
- Use lucide icons for action buttons where an icon exists.

## Backend

- Keep controllers thin.
- Put business actions in domain `Actions`.
- Put reusable domain behavior in domain `Services`.
- Keep framework infrastructure in `apps/api/app`.

# Security Baseline

## Changelog

- 2026-05-09: Initial standard.

## Baseline

- Treat tenant isolation as a security boundary.
- Use Sanctum for first-party app authentication.
- Use authorization policies for tenant-aware domain actions.
- Store secrets in environment variables.
- Do not expose AI provider raw errors to users.
- Audit procurement decisions, overrides, imports, exports, and AI-assisted recommendations.

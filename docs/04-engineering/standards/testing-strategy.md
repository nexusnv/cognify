# Testing Strategy

## Changelog

- 2026-05-09: Initial standard.

## Frontend

- Use Vitest and React Testing Library for component and integration tests.
- Use MSW for API-shaped mocks.
- Use Playwright for critical browser workflows.
- Avoid trivial render tests and broad snapshots.

## Backend

- Use PHPUnit for the scaffold baseline. Pest can be revisited when its dependency set aligns cleanly with the Laravel/PHP version in use.
- Use Laravel feature tests for API contracts and domain workflows.
- Test tenant isolation, permissions, queued jobs, audit trails, and adapter contracts.

## Contract Tests

- OpenAPI is the contract source.
- Orval clients must be regenerated when API payloads change.
- MSW handlers should match OpenAPI-shaped responses.

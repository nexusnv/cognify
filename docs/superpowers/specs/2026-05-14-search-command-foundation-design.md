# Search And Command Foundation Design

Date: 2026-05-14
Status: Draft approved for planning
Epic: Search And Command Foundation
Release priority: P0

## 1. Purpose

This spec defines Cognify's P0 search and command foundation. The first product workflow is keyboard-first navigation and tenant-scoped requisition lookup through the app shell command palette.

The design turns the existing shell search button into a real command palette while keeping the initial backend search intentionally narrow. The result contract is generic enough to add projects, vendors, quotations, approvals, awards, and evidence later.

## 2. Product Scope

### 2.1 In Scope

- Command palette opened from the existing shell button.
- `Cmd+K` and `Ctrl+K` shortcut support.
- Keyboard-accessible command navigation.
- Frontend-local navigation and create commands.
- Tenant-scoped global search API.
- Initial searchable entity: requisitions.
- Search result grouping and highlighting-ready result metadata.
- Loading, empty, and error states.
- OpenAPI contract export and generated `@cognify/api-client` consumption.
- MSW-backed frontend workflow before backend integration.

### 2.2 Out Of Scope

- Semantic or AI search.
- Saved searches.
- Advanced query syntax.
- Cross-tenant admin search.
- Search analytics dashboards.
- Server-side command execution.
- Full indexing infrastructure.
- Evidence document content search.

## 3. Workflow

A user presses `Cmd+K` or `Ctrl+K`, or clicks the shell search button. A command palette opens with grouped items:

- Navigation: dashboard, requisitions, approvals, settings, and other implemented routes.
- Actions: create requisition and other safe frontend-local actions.
- Recent records: locally tracked records opened during the current browser session.
- Search results: tenant-scoped records matching the typed query.

The user can type a keyword, arrow through results, press Enter to navigate or execute an action, or dismiss the palette with Escape.

## 4. Architecture

### 4.1 Frontend

Frontend ownership:

- `apps/web/components/shell/command-palette-host.tsx`: shell entry point and shortcut registration.
- `apps/web/features/search`: search API wrappers, hooks, result mapping, command definitions, MSW handlers, and tests.
- `apps/web/components/shell`: app route definitions reused by navigation command items.
- `packages/ui`: only low-level reusable primitives if needed. Cognify command behavior stays in `apps/web`.

The palette should use proven accessibility primitives already available in the stack, such as `cmdk` with Radix Dialog, rather than a hand-rolled combobox/dialog.

### 4.2 Backend

Backend ownership:

- `apps/api/Domains/Search`: search controller, request validation, result resources, and tests.
- `apps/api/Domains/Requisition`: reusable query or service method for permission-aware requisition search if needed.
- `apps/api/app`: cross-cutting API error behavior only.

The search domain coordinates searchable record providers. P0 should include only a requisition provider. Future providers can be added for projects, vendors, quotations, approvals, awards, and evidence without changing the command palette contract.

### 4.3 API Client

The API contract is exported from Laravel OpenAPI and consumed through `packages/api-client`. App feature hooks wrap generated endpoints and handle debounce, cancellation, and result grouping for UI use.

## 5. API Contract

Initial endpoint:

- `GET /api/search?query=&types=&limit=`

Parameters:

- `query`: keyword query. Trimmed. Remote search starts at two characters.
- `types`: optional comma-separated result type filter.
- `limit`: optional per-request cap with a server-side maximum.

Response shape:

```json
{
  "data": [
    {
      "type": "requisition",
      "id": "42",
      "title": "Office fit-out procurement",
      "subtitle": "REQ-2026-0042",
      "status": "submitted",
      "href": "/requisitions/42",
      "updatedAt": "2026-05-14T10:30:00Z"
    }
  ],
  "meta": {
    "query": "office",
    "limit": 10,
    "returned": 1
  }
}
```

The API returns only records the current user can view in the active tenant. It should not expose hidden snippets or fields that the user cannot access elsewhere.

## 6. Command Model

Commands are frontend-local in P0. A command item includes:

- `id`
- `group`
- `label`
- `description`
- `href` or `run`
- `keywords`
- `enabled`

Initial commands:

- Open dashboard.
- Open requisitions.
- Create requisition.
- Open approvals if the route is implemented.
- Open account/settings if the route is implemented.

The command model stays separate from backend search results. Search results navigate to records; commands execute local navigation or safe local actions. Server-side command execution is deferred until Cognify has enough privileged mutations to justify a separate authorization and audit model.

## 7. Search Semantics

P0 search is keyword-based. Requisition search should match:

- Requisition title.
- Requisition number.
- Requester name through the existing requisition requester relationship.

Results are ordered by relevance first, then recent update time. A simple order is acceptable for P0:

1. Exact number match.
2. Prefix number or title match.
3. Partial title match.
4. Most recently updated.

The implementation avoids introducing an external search service in P0. Database-backed search is sufficient until volume or ranking requirements prove otherwise.

## 8. Security And Compliance

- Every backend result is tenant-scoped.
- Result providers enforce the same permission rules as normal list/detail endpoints.
- Query length and limit are validated server-side.
- The frontend debounces user input and cancels stale queries.
- Search errors use the Cognify API error contract.
- Search must not leak existence of records from other tenants or unauthorized statuses.
- Search result subtitles must avoid sensitive document content in P0.

Search queries do not need audit events in P0. Record-opening behavior is already covered by normal access controls. If future compliance requires search audit trails, add it as a separate hardening slice with retention and privacy rules.

## 9. UX Requirements

The command palette includes:

- Shell button with clear accessible label.
- `Cmd+K` and `Ctrl+K` shortcut support.
- Dialog title for screen readers.
- Search input with stable focus on open.
- Groups for commands and search results.
- Loading state while remote search is pending.
- Empty state when no commands or records match.
- Error state with retry or clear recovery.
- Keyboard navigation with Enter and Escape.
- Route navigation without full page reload where Next.js routing supports it.

The interface should be dense, quiet, and work-focused. It should not become a marketing-style launcher or a broad AI assistant surface in P0.

## 10. Testing Strategy

Frontend tests:

- Shell button opens the palette.
- `Cmd+K` and `Ctrl+K` open the palette.
- Escape closes the palette.
- Local commands render and navigate.
- Search input triggers debounced MSW-backed results.
- Loading, empty, and error states render.
- Keyboard navigation can select a result.
- Unauthorized or failed search responses do not leave stale results visible.

Backend tests:

- Authenticated user can search visible requisitions in the active tenant.
- Cross-tenant requisitions never appear.
- Role-based requisition visibility matches existing requisition list behavior.
- Query length and limit validation use API error contract.
- Empty result sets return a successful empty response.
- Type filtering accepts `requisition` and rejects unsupported types.

Contract verification:

- OpenAPI export includes the search endpoint and schemas.
- `packages/api-client` is regenerated.
- Web search hooks consume generated endpoint types.

## 11. Slice Plan

Slice 1: Command palette and mocked search workflow

- Replace the inert shell command button with an accessible command palette.
- Add local command registry and route-backed actions.
- Add MSW-backed search hooks and grouped result rendering.
- Cover keyboard, state, and routing behavior with frontend tests.

Slice 2: Backend global search and real integration

- Add Laravel search domain, request validation, requisition provider, resource, route, and tests.
- Export OpenAPI and regenerate `@cognify/api-client`.
- Integrate generated search client in the web feature.
- Harden tenant isolation, permission filtering, and API error handling.

## 12. Acceptance Criteria

- A user can open the command palette by button or keyboard shortcut.
- A user can navigate to core app destinations and create a requisition from the palette.
- A user can search visible requisitions in the active tenant from the palette.
- Unauthorized records and cross-tenant records are never returned.
- Search and command UI covers loading, empty, error, and keyboard states.
- Frontend code uses MSW in tests and generated API clients for real integration.
- App-specific command behavior stays in `apps/web`.
- Backend search coordination stays in `apps/api/Domains/Search`.

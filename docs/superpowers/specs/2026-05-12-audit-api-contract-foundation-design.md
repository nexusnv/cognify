# Audit And API Contract Foundation Design

## Context

The P0 roadmap identifies audit event infrastructure, the API error contract, and the OpenAPI/generated client workflow as platform foundations. The release plan lists this as the fourth table row, but also places it second in delivery order immediately after Tenant, Auth, Access Foundation. This spec treats it as Epic 2 in delivery sequence.

Current repo state already includes the first pass of the identity and requisition foundations:

- `apps/api/app/Audit/AuditEvent`
- `apps/api/app/Audit/AuditRecorder`
- `apps/api/database/migrations/2026_05_10_000001_create_audit_events_table.php`
- requisition actions that record `requisition.created`, `requisition.updated`, and `requisition.submitted`
- a requisition activity endpoint that reads audit events for one requisition
- `apps/api/storage/openapi/openapi.json`
- Orval output in `packages/api-client/src/generated/*`
- `packages/api-client/src/client.ts` as the generated client mutator
- identity and requisition tests that already exercise tenant isolation and some audit behavior

Epic 2 should harden these foundations rather than replace them.

## Approved Scope

This epic delivers:

- Immutable, tenant-scoped audit event infrastructure with a stable recorder API.
- A platform-level audit event query endpoint for authorized tenant users.
- Shared audit resource shaping for both platform audit feeds and domain activity timelines.
- A normalized JSON API error contract for validation, authentication, authorization, not found, conflict, ambiguous tenant, throttling, and unexpected server failures.
- Laravel exception rendering that consistently emits the error contract for API routes.
- OpenAPI coverage for audit and error schemas.
- A repeatable contract generation workflow that fails when generated client output drifts from OpenAPI.
- Typed API client helpers for tenant-aware frontend calls and consistent API error handling.
- MSW test utilities that use OpenAPI-shaped error payloads.

This epic does not deliver:

- A full admin audit log UI.
- Audit exports, audit packs, or retention management.
- Event sourcing.
- Search indexing for audit events.
- Notification or activity timeline primitives beyond contract-ready resource shaping.
- Role management beyond using the existing role and permission foundation.
- New shared packages.

## Workflow

1. A tenant-scoped workflow action calls the audit recorder inside the same transaction as the state change.
2. The recorder creates one append-only audit event with actor, action, subject, timestamp, request correlation, IP/user-agent context, metadata, and optional before/after snapshots.
3. Domain activity endpoints query audit events through the subject and tenant boundary.
4. Authorized tenant users can query the tenant audit feed through `/api/audit/events`.
5. API failures are rendered through one stable error envelope.
6. OpenAPI documents the success and failure responses.
7. Orval regenerates the frontend client from the contract.
8. Frontend hooks and MSW handlers consume the generated types and normalized error helpers instead of local ad hoc shapes.

## Recommended Approach

### Option A: Lean Platform Audit And Contract Foundation

Keep audit infrastructure in `apps/api/app/Audit`, because it is cross-cutting infrastructure used by many domains. Add a small `AuditEventData` value object, a stronger `AuditRecorder`, an `AuditEventResource`, an authorized tenant audit endpoint, API exception rendering, and typed client error helpers.

This is the recommended approach. It strengthens the P0 foundations without introducing event sourcing, search, exports, or a heavy audit domain before product workflows need them.

### Option B: Dedicated Audit Domain

Move audit behavior into `apps/api/Domains/Audit` and treat audit as a business domain with its own services, policies, controllers, and later exports.

This is premature for P0. Audit is currently infrastructure used by requisitions, identity, future attachments, AI, and approvals. A domain boundary would make sense later when audit review, retention, export, and evidence-pack workflows become a user-facing product area.

### Option C: Activity-Only Audit

Leave audit as a hidden backend table and expose only domain-specific activity feeds.

This would unblock workflow UI, but it would not satisfy the P0 goal of enterprise readiness. Procurement auditability needs a stable contract now so future workflows do not each invent their own event shape and error behavior.

## Backend Architecture

Backend ownership stays split by responsibility:

- `apps/api/app/Audit/*`: cross-cutting audit infrastructure, resources, query controller, and policy.
- `apps/api/app/Exceptions/*`: API error envelope, error code constants, and exception rendering support.
- `apps/api/app/Http/Middleware/*`: request correlation middleware for traceable audit metadata.
- `apps/api/Domains/*`: domain actions call the audit recorder but do not own audit persistence mechanics.
- `apps/api/storage/openapi/openapi.json`: source of truth for API success and error contracts.

Planned backend structure:

```txt
apps/api/app/Audit/
  AuditEvent.php
  AuditEventData.php
  AuditEventResource.php
  AuditRecorder.php
  AuditSubject.php
  Http/Controllers/AuditEventController.php
  Policies/AuditEventPolicy.php

apps/api/app/Exceptions/
  ApiErrorCode.php
  ApiErrorResponse.php

apps/api/app/Http/Middleware/
  AssignRequestId.php
```

The existing audit table should be extended, not dropped. The migration adds nullable columns so existing development data remains migratable:

- `event_id`: immutable public UUID for external references.
- `action`: canonical action string, replacing `event_type` as the preferred API name.
- `subject_display`: short human-readable subject label for activity feeds.
- `before`: nullable JSON snapshot.
- `after`: nullable JSON snapshot.
- `ip_address`: nullable string.
- `user_agent`: nullable string.
- `request_id`: nullable string.

`event_type` remains in the table during the transition for compatibility. New code writes both `event_type` and `action` with the same value until a later cleanup removes the legacy name.

## Audit Event Model

The stable API event shape is:

```json
{
  "id": "9bfb4c6f-17a7-48c1-a4cc-0fba43b3f8f3",
  "action": "requisition.submitted",
  "actor": {
    "id": "1",
    "name": "Aisha Tan",
    "email": "aisha@example.com"
  },
  "subject": {
    "type": "requisition",
    "id": "42",
    "display": "REQ-2026-000042"
  },
  "metadata": {
    "status": "submitted"
  },
  "before": {
    "status": "draft"
  },
  "after": {
    "status": "submitted"
  },
  "occurredAt": "2026-05-12T08:20:00.000000Z",
  "requestId": "req_01HX..."
}
```

The API does not expose raw Eloquent class names. `AuditSubject` maps class names such as `Domains\Requisition\Models\Requisition` to stable subject types such as `requisition`.

## API Contract

New platform endpoint:

| Endpoint | Purpose |
| --- | --- |
| `GET /api/audit/events` | Return a paginated tenant-scoped audit feed for authorized users. |

Supported query parameters:

- `action`: exact action filter such as `requisition.submitted`.
- `actorId`: actor user ID.
- `subjectType`: stable subject type such as `requisition`.
- `subjectId`: subject ID, required when filtering a specific subject.
- `occurredFrom`: ISO date-time lower bound.
- `occurredTo`: ISO date-time upper bound.
- `page`: page number.
- `perPage`: 1 to 100, clamped server-side.

Errors use one envelope:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "details": {
      "fields": {
        "lineItems.0.quantity": ["The line items.0.quantity field must be greater than 0."]
      }
    },
    "requestId": "req_01HX..."
  }
}
```

Error codes:

- `validation_failed`
- `unauthenticated`
- `forbidden`
- `not_found`
- `conflict`
- `ambiguous_tenant`
- `too_many_requests`
- `server_error`

OpenAPI must define every shared error response under `components.responses` and the payload schemas under `components.schemas`.

## Frontend Architecture

Frontend work stays product-specific and typed:

```txt
apps/web/features/audit/
  api/audit-api.ts
  hooks/use-audit-events.ts
  mocks/audit-fixtures.ts
  mocks/audit-handlers.ts
  tests/audit-api.test.ts
  types/audit-view-model.ts

packages/api-client/src/
  client.ts
  errors.ts
  generated/*
  index.ts
```

No broad audit UI is required in this epic. The frontend scope is the typed contract layer, MSW fixtures, and tests proving future shell or admin UI can consume audit events through generated types.

The API client should expose:

- `ApiClientError`
- `ApiErrorEnvelope`
- `isApiClientError(value)`
- `getApiErrorCode(value)`
- `getApiErrorMessage(value)`
- `getApiValidationErrors(value)`

Feature hooks should catch normalized client errors and map them to workflow UI states later. They should not parse Laravel default payloads.

## Permission And Tenant Rules

- Tenant isolation is mandatory on every audit query.
- Audit event writes always receive an explicit `Tenant`.
- Audit event reads use `CurrentTenant`.
- Requester, buyer, approver, and admin can read audit events for records they can otherwise view.
- The platform `/api/audit/events` feed is limited to `buyer`, `approver`, and `admin` in P0. Requesters should use record-specific activity endpoints where domain policies can enforce ownership.
- Domain activity endpoints continue to apply the domain record policy before returning events.
- The API never accepts a tenant ID query parameter for audit reads; the tenant comes from the validated request context.

## Security And Integrity Rules

- Audit events are append-only through application code.
- No update or delete endpoints are added.
- The model should guard against accidental `save()` changes to existing events by throwing in `updating` and `deleting` model events.
- Metadata, before, and after snapshots must be JSON objects, not arbitrary scalar payloads.
- Sensitive values such as passwords, tokens, raw file contents, and provider secrets must not be recorded.
- IP address and user-agent are request context only; they are not authorization inputs.
- Request IDs are generated server-side when missing and returned in error envelopes.

## Error Handling

Laravel API exception rendering should map:

- validation exceptions to `422 validation_failed` with field errors.
- authentication exceptions to `401 unauthenticated`.
- authorization exceptions to `403 forbidden`.
- model not found and not-found HTTP exceptions to `404 not_found`.
- conflict HTTP exceptions to `409 conflict`.
- ambiguous tenant failures to `400 ambiguous_tenant`.
- throttle exceptions to `429 too_many_requests`.
- all other exceptions to `500 server_error` with a generic message.

Local logs can include exception details. API responses should not expose stack traces or raw provider errors.

## Implementation Slices

1. **Audit Infrastructure Hardening**
   Extend the audit event schema, add append-only protections, add `AuditEventData`, improve the recorder, and update requisition actions to record before/after context where useful.

2. **API Error Contract**
   Add request ID middleware, API error response helpers, Laravel exception rendering, tests for shared error shapes, and frontend error parsing helpers.

3. **OpenAPI And Generated Client Workflow**
   Update OpenAPI with audit and error schemas, regenerate Orval output, add drift checking guidance, add typed audit API hooks/MSW handlers, and verify contract commands.

## Verification

Each slice should run narrow checks first:

- `cd apps/api && php artisan test --filter=Audit`
- `cd apps/api && php artisan test --filter=ApiError`
- `cd apps/api && php artisan test --filter=RequisitionApiTest`
- `pnpm generate:api`
- `pnpm check:api-contract`
- `pnpm --filter @cognify/api-client typecheck`
- `pnpm --filter @cognify/web test -- audit`

Before claiming Epic 2 complete, run:

- `pnpm lint`
- `pnpm typecheck`
- `pnpm test`

If a verification command cannot run locally, the implementation notes must document the blocker and the highest-signal commands that did run.

## Implementation Planning Decisions

- Keep audit in `apps/api/app/Audit` for P0.
- Keep `event_type` as a compatibility column but make `action` the API contract name.
- Use a UUID `event_id` as the public audit event ID.
- Record request context automatically in `AuditRecorder` from the current request when available.
- Keep audit activity messages server-side for now through `AuditEventResource`; a later activity timeline primitive can decide whether to localize or re-render messages client-side.
- Do not add a visible audit page in this epic. Build the API, generated client, hook, mocks, and tests so the future shell/admin epic can consume it.
- Use OpenAPI as the contract source. Do not duplicate response types by hand in app code when generated schemas exist.
- Do not import MSW fixtures into production components or hooks.

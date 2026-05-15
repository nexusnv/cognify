# Local Demo and System Readiness Design

Date: 2026-05-15
Status: Approved design
Epic: P0 Local Demo And System Readiness
Related docs:
- `docs/02-release-management/2026-05-12-P0-Epics.md`
- `docs/01-product/feature-roadmap.md`
- `docs/05-runbooks/feature-development.md`
- `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

## 1. Purpose

This design covers the final P0 epic slice for Cognify: local demo data and system readiness. The goal is to make a local Cognify environment useful for product review, workflow testing, and operator/developer diagnosis without turning this slice into the first implementation of all P1 and P2 procurement workflows.

The design intentionally adds lightweight roadmap-preview data so the local demo resembles the fuller procurement platform described in the roadmap. Those preview records should make the product feel coherent across vendors, projects, RFQs, quotations, approvals, awards, activity, audit, and notifications, while keeping full workflow behavior for future dedicated feature slices.

## 2. Scope

This slice has two deliverables.

### 2.1 Demo Snapshot

Create deterministic local seed data that exercises the currently implemented P0 domains and previews the next procurement lifecycle:

- Tenants with distinct procurement operating contexts.
- Users across requester, buyer, approver, finance, auditor, vendor manager, and admin roles.
- Requisitions with draft, submitted, and review-oriented examples.
- Line items, activity, audit events, attachments, and notifications connected to those records.
- Lightweight roadmap-preview records for vendors, procurement projects, RFQs, quotations, approval tasks, and awards.

The demo snapshot should be repeatable and safe for local refresh. A developer should be able to rebuild the local database and see the same recognizable workspace each time.

### 2.2 System Readiness

Expose an authenticated system status contract and frontend surface that show whether the local or deployed Cognify environment is healthy enough for development and demos.

The readiness surface should report:

- API identity and version/build metadata.
- Database connectivity.
- Cache connectivity.
- Queue connection or backing table health.
- Storage disk write/read/delete health.
- OpenAPI contract availability.
- Demo seed state and summary counts.
- Overall status: `ok`, `warning`, or `error`.

The existing public `/api/health` route remains a simple liveness check. Detailed system readiness belongs behind authentication and tenant/admin authorization.

## 3. Explicit Non-Goals

This slice must not implement full P1 or P2 workflow behavior.

Out of scope:

- Vendor create/update workflows.
- Vendor portal behavior.
- RFQ lifecycle transitions.
- Quotation normalization, scoring, or extraction.
- Approval routing rules or sequential/parallel approval engines.
- Award approval or purchase-order handoff.
- Customer-facing production status page.
- Feature flag system.
- Full admin operations console.

The preview records should be useful data foundations and demo context, not hidden workflow implementations.

## 4. Backend Design

### 4.1 Ownership

Create a small demo/readiness ownership area instead of scattering demo-specific orchestration throughout real business domains.

Recommended locations:

```txt
apps/api/Domains/Demo/
  Data/
  Services/
  Support/
  Tests/

apps/api/app/Observability/
  SystemStatus/
```

`Domains/Demo` owns deterministic demo seed orchestration and demo summary logic. `app/Observability/SystemStatus` owns cross-cutting readiness checks because API, cache, queue, storage, and OpenAPI health are infrastructure concerns rather than procurement business behavior.

Lightweight roadmap-preview models should live in their plausible future domain folders so later feature slices can evolve them without moving tables:

```txt
apps/api/Domains/Vendor/
apps/api/Domains/Project/
apps/api/Domains/Quotation/
apps/api/Domains/Approval/
```

### 4.2 Lightweight Preview Tables

Add minimal tenant-scoped tables for demo and future foundation:

- `vendors`
- `procurement_projects`
- `rfqs`
- `quotations`
- `approval_tasks`
- `awards`

Each table should include only fields needed for coherent demo, search, status summaries, and future migration:

- `id`
- `tenant_id`
- display name or number
- status
- relevant foreign keys, for example requisition, project, vendor, RFQ, requester, owner, or approver IDs
- monetary summary fields where useful
- due or decision dates where useful
- `metadata` JSON for non-critical demo context
- timestamps

Avoid modeling advanced lifecycle details until the relevant P1/P2 slice owns them.

### 4.3 Seeder Structure

Move demo seed work out of the root `DatabaseSeeder` into focused seeders:

```txt
apps/api/database/seeders/Demo/
  DemoTenantSeeder.php
  DemoUserSeeder.php
  DemoRequisitionSeeder.php
  DemoRoadmapPreviewSeeder.php
  DemoAttachmentSeeder.php
  DemoAuditSeeder.php
  DemoNotificationSeeder.php
```

`DatabaseSeeder` should orchestrate the demo seeders in dependency order. Seed data should be deterministic:

- fixed user emails and names
- fixed tenant names
- predictable requisition/RFQ/award numbers
- stable statuses and dates relative to a fixed seed baseline where practical
- stable attachment sample filenames and storage paths

The seed flow should either be idempotent or clearly support a documented local refresh command such as migrate fresh plus seed. The implementation plan should choose one explicit mechanism and test it.

### 4.4 Demo Dataset Shape

Create at least three tenants:

- A primary tenant with the richest dataset.
- A second tenant with distinct data to prove tenant isolation.
- A smaller admin/sandbox tenant for cross-tenant membership and account switching.

Create users that cover the role baseline:

- requester
- buyer
- approver
- finance
- auditor
- vendor manager
- admin

The primary tenant should include:

- several requisitions across draft/submitted/review-like states
- line items with realistic procurement categories
- vendors with preferred, restricted, and evaluation-style statuses
- projects that group requisitions/RFQs/awards
- RFQs with due dates and invited vendors
- quotations tied to RFQs and vendors
- approval tasks tied to requisitions or awards
- an award decision record tied to one quotation/vendor
- audit events that explain visible transitions
- notifications that deep-link into implemented routes where possible
- attachments with small local sample files or metadata backed by real storage entries

Second-tenant data should intentionally resemble the primary tenant enough to catch accidental cross-tenant leaks in tests and search.

### 4.5 System Status API

Add a detailed readiness endpoint:

```txt
GET /api/system/status
```

This endpoint should require authentication, current tenant context, and admin-capable permission. The exact permission can reuse the current `canAccessAdmin`/admin baseline until a richer admin permission model exists.

The response should be included in OpenAPI and generated through `@cognify/api-client`.

Recommended shape:

```json
{
  "data": {
    "status": "ok",
    "environment": "local",
    "service": "cognify-api",
    "version": "0.1.0",
    "checkedAt": "2026-05-15T00:00:00Z",
    "checks": [
      {
        "id": "database",
        "label": "Database",
        "status": "ok",
        "message": "Connected",
        "remediation": null
      }
    ],
    "demo": {
      "status": "ok",
      "lastSeededAt": "2026-05-15T00:00:00Z",
      "counts": {
        "tenants": 3,
        "users": 7,
        "requisitions": 8,
        "vendors": 6,
        "rfqs": 3,
        "quotations": 7,
        "approvalTasks": 5,
        "awards": 1
      }
    }
  }
}
```

Check status values are:

- `ok`
- `warning`
- `error`

The endpoint should return HTTP `200` when the API can produce a readiness report, even if individual checks are degraded. Authentication, authorization, tenant-resolution, and unexpected server failures should still use the standard API error contract.

### 4.6 Readiness Checks

Implement checks as small independent classes or services with a common contract:

```txt
id
label
status
message
remediation
metadata
```

Initial checks:

- API metadata check.
- Database query check.
- Cache put/get/delete check.
- Queue connection or jobs table check.
- Storage write/read/delete check using a temporary readiness object.
- OpenAPI file presence and parse check.
- Demo seed summary check.

Checks should degrade gracefully. A failed storage check should mark storage and overall status as `error`, not crash the whole endpoint. A non-critical missing build SHA can be `warning`.

### 4.7 Search Integration

Extend tenant-scoped search to include lightweight preview records where practical:

- vendors
- procurement projects
- RFQs
- quotations
- awards

Search results should identify preview records with stable result types and labels. Links should point only to implemented routes. For unimplemented future workspaces, either omit `href` or route to the most relevant implemented context, such as a linked requisition, to avoid dead navigation.

Tenant isolation tests must prove preview records from another tenant do not appear.

## 5. Frontend Design

### 5.1 Ownership

Add a feature-owned readiness area:

```txt
apps/web/features/system-readiness/
  api/
  components/
  hooks/
  mocks/
  tests/
  types/
```

The feature should consume generated API client types. It should not duplicate the system status response contract in app-specific types except for narrow view-model helpers.

### 5.2 Route

Add an authenticated admin/debug route:

```txt
apps/web/app/(workspace)/system/page.tsx
```

or:

```txt
apps/web/app/(workspace)/admin/system/page.tsx
```

The implementation plan should choose the route that best matches the existing shell navigation. The route must be visible only to admin-capable users.

The navigation label should be operational, such as `System`, `System Status`, or `Readiness`.

### 5.3 Shell Footer Indicator

Update the existing shell footer to show a compact readiness indicator:

```txt
Cognify · Local demo · Healthy
Workspace: Acme Procurement
```

or, for degraded local environments:

```txt
Cognify · Local demo · Needs attention
Workspace: Acme Procurement
```

The footer should remain compact and should not block the main workflow if the readiness endpoint fails. For non-admin users, either hide the detailed status or show a neutral environment label without check details.

### 5.4 System Status Page

The system status page should be dense and operational:

- Overall status summary.
- API/environment metadata.
- Grouped checks with status badge, message, and remediation.
- Demo dataset summary counts.
- Last checked timestamp.
- Clear empty/error state if status cannot be loaded.

Avoid a marketing-style demo page. This is an admin/debug surface for developers and operators.

### 5.5 MSW And Tests

Add MSW handlers for:

- healthy readiness response
- warning response
- error/degraded response
- unauthorized/forbidden response

Web tests should cover:

- footer indicator renders healthy and degraded states without layout shift
- system page groups checks and demo counts
- forbidden users cannot see or use the admin route/navigation
- generated-type-backed API hook handles failed status fetches gracefully

## 6. Contract And Data Flow

Implementation should remain contract-first:

```txt
Backend OpenAPI update
  -> generated API client
  -> system-readiness hooks
  -> footer and system page
  -> tests against MSW and real API contract shape
```

The backend owns the system status response. The frontend should not infer health by manually probing multiple endpoints.

The demo data flow is:

```txt
migrations
  -> deterministic demo seeders
  -> demo summary service
  -> /api/system/status demo summary
  -> footer/system status UI
  -> search/command palette preview visibility
```

## 7. Error Handling

Readiness checks should report local remediation text. Examples:

- Database error: confirm local services are running and migrations have been applied.
- Cache error: confirm cache driver configuration and backing service.
- Queue warning: confirm queue connection and jobs table migration.
- Storage error: confirm local disk path is writable.
- OpenAPI warning: regenerate or export the API contract.
- Demo seed warning: run the documented seed command after migrations.

The endpoint should not expose secrets, database credentials, absolute sensitive paths, or stack traces.

The frontend should display check messages and remediation without crashing if optional fields are absent.

## 8. Security And Tenant Rules

Detailed readiness is not public. It may expose operational information, so it must require:

- authenticated user
- resolved current tenant
- admin-capable permission

Demo and preview records must remain tenant-scoped:

- every preview table includes `tenant_id`
- search providers filter by current tenant
- system demo counts either report global seed status safely or tenant-specific counts explicitly
- tests include same-named records in different tenants to prove isolation

Client-side tenant state must continue to be persisted only after server validation succeeds.

## 9. Verification Strategy

Backend verification:

- API feature tests for `/api/system/status` auth, tenant, admin permission, and response shape.
- Unit tests for readiness check aggregation and degraded check behavior.
- Seeder or command tests proving deterministic demo counts and refresh behavior.
- Search tests proving preview records are tenant-scoped.
- Existing auth, requisition, audit, attachment, notification, and search tests remain passing.

Frontend verification:

- System readiness hook tests.
- Footer indicator tests.
- System page tests for healthy, warning, error, and forbidden states.
- Search/command palette tests updated for preview result types.

Contract verification:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Relevant local checks:

```bash
cd apps/api
php artisan test
php artisan route:list --path=api

pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Run broader root checks if shared config or package exports change.

## 10. Implementation Slices

This epic can be implemented as two runbook slices:

### Slice 1: Demo Snapshot Data

Deliver deterministic demo data, lightweight preview tables/models, seeded storage artifacts or attachment records, audit/notification examples, and search integration for preview records.

Acceptance criteria:

- Fresh local seed produces a realistic multi-tenant procurement dataset.
- Demo users can log in and see coherent requisition, notification, search, audit, and attachment context.
- Preview records exist for vendors, projects, RFQs, quotations, approval tasks, and awards.
- Preview records remain tenant-isolated.
- Search returns preview records where appropriate.

### Slice 2: System Readiness Surface

Deliver `/api/system/status`, OpenAPI/generated client updates, web readiness feature, shell footer indicator, and admin system status page.

Acceptance criteria:

- Admin-capable users can view detailed system status.
- Non-admin users cannot view detailed readiness.
- Footer shows compact environment/readiness state without disrupting workflow.
- The status page reports degraded checks gracefully.
- Demo seed summary is visible from the status response and page.

## 11. Open Decisions For The Implementation Plan

The implementation plan should decide:

- Whether the detailed route is `/system` or `/admin/system`.
- Whether demo refresh is handled by idempotent seeders or by a documented `migrate:fresh --seed` workflow.
- Whether attachment demo artifacts are real tiny files on local storage or metadata-only records with tests proving preview/download behavior where implemented.
- How to source API version/build metadata in local development.
- Whether demo counts in `/api/system/status` are global non-sensitive counts or tenant-scoped counts plus a global seeded marker.

Each decision should be made before code changes for that slice begin.

# P1-47 Implementation Learnings

## PO Handoff Pattern (Reference - PurchaseOrderRequestHandoff)
- UUID PK, tenant_id FK, generated `number` (POH-YYYY-NNNNNN per tenant)
- Status enum: Draft → Ready → Exported → Cancelled (Status class in States/)
- Multiple JSON snapshots (source, line, approval, evidence) + readiness_warnings
- lock_version (unsigned int, default 1) on all mutations
- `booted()::saving` validates all FK → tenant_id matching
- Pivot table for handoff-invoice relationship: need to create ApPaymentHandoffInvoice pivot
- Number generator in `Support/` directory

## SupplierInvoice Model
- UUID PK, tenant_id, vendor_id, purchase_order_id
- 11-status state machine ending at Approved/Rejected/ChangesRequested
- No payment_status column yet — new column with 5 values
- lock_version, assertLockVersion() for concurrency
- Has lines(), purchaseOrder(), vendor() relationships

## Approval Transaction Pattern
1. `SupplierInvoice::query()->lockForUpdate()` (pessimistic lock)
2. Status guard (must be correct predecessor status)
3. `$invoice->assertLockVersion($lockVersion)` → 409 on stale
4. `$invoice->only([columns])` → capture before-state
5. `$invoice->forceFill([...])` → set new status + increment lock_version
6. `$invoice->save()`
7. `$this->auditRecorder->record(new AuditEventData(...))`
8. Return `$invoice->fresh([relations])`

## Audit System
- `AuditRecorder` at `app/Audit/AuditRecorder.php`
- Preferred: `record(new AuditEventData(tenant, actor, action, subject, metadata, before, after, subjectDisplay))`
- Action naming: `supplier_invoice.approved`, `supplier_invoice.payment_eligible`, `ap_payment_handoff.created`
- Immutable AuditEvent model

## Frontend Patterns
- Status tabs: array with `{ label, value, status }` in workflow page
- React Query hooks with key structure: `[tenantId, "supplier-invoices", filters]`
- MSW handlers per feature with `reset*MockState()` pattern
- Components import types from `@cognify/api-client/schemas`
- Tests: `TestProviders` wrapper, `server.use()` handler override, `resetAccountsPayableInvoiceMockState()` in beforeEach

## Test Patterns
- `apps/api/tests/Feature/` — all feature tests here
- `Tests\TestCase` base, `RefreshDatabase` trait
- `tenantUserPair()` creates Tenant + User, `actingAsTenant()` sets Sanctum + X-Tenant-Id + CurrentTenant
- Assertions: `assertCreated()`, `assertForbidden()`, `assertConflict()` (409)
- Handoff test includes real Sanctum session login test
- snake_case test names

## Route Pattern
- All routes in `apps/api/routes/api.php`
- `auth:sanctum` → `RequireTenantHeader` middleware for tenant-scoped routes
- Format: `Verb /api/resource/{id}/action` → Controller@method

## OpenAPI
- `apps/api/storage/openapi/openapi.json` — manually maintained, OpenAPI 3.1.0
- OperationId: `{verb}{ResourceName}` camelCase (e.g., `createApPaymentHandoff`)
- After changes: `pnpm generate:api && pnpm check:api-contract`

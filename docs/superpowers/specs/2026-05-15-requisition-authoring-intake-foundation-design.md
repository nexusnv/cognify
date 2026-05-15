# Requisition Authoring And Intake Foundation Design

Date: 2026-05-15
Status: Draft for review
Source epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 1
Related roadmap: `docs/01-product/feature-roadmap.md` P1 Core Procurement Lifecycle
Runbook alignment: `docs/05-runbooks/feature-development.md`
Greenfield alignment: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

## 1. Purpose

This spec defines the P1 Epic 1 design for Requisition Authoring And Intake Foundation. The goal is to let a requester create, save, leave, reopen, and continue a high-quality procurement requisition draft without data loss, while capturing the organizational spend metadata needed by later approval, sourcing, reporting, and finance workflows.

The design builds on the current Cognify requisition foundation instead of restarting it. The repository already has tenant-scoped requisition draft create/update endpoints, line items, department and cost center fields, activity timeline integration, generated client hooks, attachments, and a requester form. Epic 1 should harden and extend that foundation with reliable save behavior, template application, line item suggestions, and stronger server-side validation.

## 2. Explicit Scope

### 2.1 In Scope

- Requisition draft creation and editing.
- Department, project placeholder, cost center, delivery location, currency, and needed-by capture.
- Line item authoring with estimated pricing.
- Autosave and manual save reliability.
- Draft save conflict detection and recovery.
- Seeded requisition templates for common buying patterns.
- Seeded line item suggestions for common item names, units, categories, and estimated prices.
- Draft authoring audit events visible in the activity timeline.
- OpenAPI and generated client updates for every contract change.
- Sufficient end-to-end coverage for the critical requester authoring path.

### 2.2 Out Of Scope

- New submission workflow behavior.
- Requisition work queues beyond compatibility with existing list/detail surfaces.
- Comments, mentions, change requests, withdrawal, or cancellation.
- Approval routing, policy preview, approval tasks, and SLA handling.
- Buyer intake, RFQ, quotation, award, purchase order handoff, or procurement calendar behavior.
- Full catalog domain, inventory management, or admin-managed catalog records.
- Moving Cognify-specific requisition UI into `packages/ui`.

Existing submission behavior remains an integration boundary because the current product already has it. Epic 1 must avoid regressing submit compatibility, but should not expand submit behavior.

## 3. Runbook Alignment

This epic follows the feature-development runbook order:

```txt
Business workflow
  -> Workspace UX
  -> API contract
  -> Mocked frontend workflow
  -> Backend domain behavior
  -> Real API integration
  -> Hardening and observability
```

Implementation should proceed as vertical slices, not isolated backend or frontend projects. Each slice should define the workflow behavior, contract shape, mocked frontend state, backend domain behavior, integration swap, and verification evidence before it is considered complete.

## 4. Greenfield Architecture Alignment

This design preserves the greenfield boundaries:

- `apps/web` owns Cognify-specific requester workflows, feature hooks, MSW handlers, form composition, and requisition workspace UI.
- `apps/api/Domains/Requisition` owns requisition business rules, actions, states, policies, resources, template behavior, and suggestion behavior.
- `apps/api/app/*` remains Laravel framework and shared infrastructure only.
- `packages/api-client` remains generated from OpenAPI plus stable typed helpers.
- `packages/ui` remains reusable primitives only and must not absorb requisition-specific shells or workflows.
- No new shared package is introduced for templates or suggestions because the boundary is not proven reusable outside Cognify yet.

## 5. Business Workflow

Actors:

- Requester: creates and maintains draft requisitions.
- System: saves drafts, applies templates, returns suggestions, records audit events, and detects stale saves.
- Admin or seeded setup: provides available templates, line item suggestions, departments, and cost centers for the tenant.

States:

- `new`: local form has no server requisition ID yet.
- `draft`: server-backed requisition remains editable by the requester.
- `submitted`: existing adjacent state; requester editing is locked and outside Epic 1 expansion.

Workflow:

1. Requester opens `New requisition` or an existing draft.
2. Requester captures summary, justification, needed-by date, department, project placeholder, cost center, delivery location, currency, and line items.
3. Requester may apply a template to prefill common buying patterns.
4. Requester may select line item suggestions for item names, units, categories, estimated prices, and currency.
5. Requester manually saves first draft creation once minimum draft creation criteria are met.
6. After a server ID exists, changes autosave with debounce while manual save remains available.
7. System records meaningful draft authoring events in the activity timeline.
8. Requester can leave, reopen, and continue the draft without data loss.
9. If a stale save occurs, system returns a conflict and the requester chooses a recovery path.

Important transition rules:

- First save requires a title to avoid empty draft records.
- Draft fields can remain incomplete; submission-required fields should not block draft saving unless they are invalid when present.
- Template application never submits a requisition or advances state.
- Suggestions never mutate the draft automatically; the requester must select a suggestion.
- Non-draft requisitions cannot be edited, autosaved, or templated.

Side effects:

- `requisition.created` audit event on first persisted draft.
- `requisition.updated` audit event for meaningful manual save/autosave changes.
- `requisition.template_applied` or `requisition.updated` with template metadata when a template changes a draft.
- No new notification event is required for draft-only authoring.

Failure paths:

- Validation errors map to form fields and error summary.
- Permission denial renders read-only or denied state.
- Missing or inaccessible requisition renders not-found state.
- Stale draft save returns conflict with recovery metadata.
- Network/server failure preserves local edits and allows retry.
- Template unavailable keeps current draft unchanged.
- Suggestion endpoint unavailable does not block authoring.

## 6. Backend Domain Design

`apps/api/Domains/Requisition` remains the owning domain. Existing actions should be extended rather than replaced.

Existing action responsibilities:

- `CreateRequisitionDraft` owns first draft creation, number generation, line item persistence, and `requisition.created` audit.
- `UpdateRequisitionDraft` owns draft-only updates, line item persistence, conflict validation, and `requisition.updated` audit.
- `SubmitRequisition` remains adjacent compatibility behavior and should not gain Epic 1 requirements.

Expected additions:

- Draft concurrency check in create/update resources and actions.
- Template model/action/support code under the Requisition domain.
- Suggestion model/action/support code under the Requisition domain.
- Server-side validation for department and cost center when present.
- Focused policies and tests for template application and suggestion access.

Controllers should stay thin. Durable business logic belongs in domain actions/services, not controllers, routes, jobs, or Eloquent model callbacks.

## 7. Data Model Design

### 7.1 Existing Model Retained

Keep the current core records:

- `requisitions`
- `requisition_line_items`
- `requisition_sequences`
- audit events tied to requisition subjects

Current requisition fields already match most Epic 1 authoring needs: title, business justification, needed-by date, department, project placeholder, cost center, delivery location, currency, status, requester, and line items.

### 7.2 Additive Model Changes

Add only what Epic 1 needs.

`requisition_templates`:

- tenant-scoped or system-seeded records.
- fields: name, description, category, defaults JSON, active flag, sort order, timestamps.
- suggested seed examples: SaaS subscription, IT equipment, professional services, office supplies, facilities work.
- defaults JSON can include title prefix, business justification prompt, line items, department/category hints, delivery location prompt, currency, and needed-by guidance.

`requisition_item_suggestions`:

- tenant-scoped or system-seeded records.
- fields: item name, category, unit, estimated unit price, currency, optional aliases/search text, active flag, timestamps.
- deliberately not a full catalog domain.
- no inventory, supplier, contract, or price-book semantics in this epic.

Org metadata source:

- Use the narrowest viable server-side source for department and cost center validation.
- If admin-managed organizational structures are not ready, seed tenant org metadata for Epic 1.
- Do not add a broad organization-management module in this epic.

Concurrency:

- Prefer a lightweight `version` token derived from `updated_at` or a small integer `lock_version`.
- Do not add a draft revision table in Epic 1 unless implementation proves the lightweight token cannot support safe recovery.

## 8. API Contract Design

Existing endpoints retained:

- `GET /api/requisitions`
- `POST /api/requisitions`
- `GET /api/requisitions/{id}`
- `PATCH /api/requisitions/{id}`
- `POST /api/requisitions/{id}/submit`
- `GET /api/requisitions/{id}/activity`

New or changed Epic 1 contracts:

- `GET /api/requisition-templates`
  - Returns active templates available to the current tenant and requester.
- `POST /api/requisitions/{id}/apply-template`
  - Applies template defaults to a draft and returns the updated requisition.
  - Rejects non-draft requisitions with conflict.
  - Accepts overwrite mode.
- `GET /api/requisition-line-item-suggestions`
  - Query by search text, category, currency, and tenant context.
  - Returns item name, category, unit, estimated unit price, and currency.
- `GET /api/requisition-intake-options`
  - Optional endpoint if the frontend benefits from one bootstrap request for departments, cost centers, currencies, units, and template categories.

Draft save contract:

- Response includes concurrency metadata, either `version` or an equivalent stable `updatedAt` token.
- Update requests include the last-known version.
- Backend rejects stale updates with `409 conflict`.
- Conflict response should include enough metadata for safe recovery without leaking cross-tenant data.

Validation behavior:

- Title is required for persisted draft creation.
- Department and cost center are nullable for drafts but must match server-known options when present.
- Line item quantity must be greater than zero.
- Estimated unit price may be zero for draft authoring if backend validation permits `gte:0`.
- Submission-specific required fields remain submission rules and must not become draft blockers.

OpenAPI requirements:

- Every endpoint and changed schema must be represented in `apps/api/storage/openapi/openapi.json`.
- Generated schemas and endpoints in `packages/api-client` must be regenerated.
- Frontend feature hooks must consume `@cognify/api-client`; no hand-written business API fetch contracts.

## 9. Frontend Workflow Design

`apps/web/features/requisitions` remains the feature owner.

### 9.1 Form Responsibilities

The requester form should become a durable authoring workspace. It should support:

- new draft state without server ID.
- existing draft edit state.
- field-level validation and error summary.
- line item editing.
- department and cost center selection or validated input.
- template selection and confirmation.
- line item suggestion selection.
- save state display near primary actions.
- read-only state when status is not `draft`.

### 9.2 Save Reliability

Recommended behavior:

- First save requires title and creates the draft.
- Autosave starts only after the draft has a server ID.
- Autosave uses debounce and does not block further editing.
- Manual save remains available and flushes the latest local values.
- Navigation away with unsaved local changes triggers an unsaved-change guard.
- Save failure preserves local edits and allows retry.
- Manual save failure should be more visible than autosave failure.
- Server validation errors map to fields and summary.
- Stale save conflict shows recovery options instead of silently overwriting.

Implementation detail:

- Autosave/manual-save orchestration should live in a focused hook or workflow helper, not inside an oversized form component.
- The form component can own layout and field rendering, while save behavior owns debouncing, mutation calls, stale state, and retry state.

### 9.3 Template Application UX

Template application should be explicit:

- Empty or near-empty drafts can apply a template immediately.
- Non-empty drafts show a confirmation explaining what can change.
- Apply modes:
  - `fill-empty`: populate empty fields and append or fill missing line item defaults.
  - `replace`: replace template-owned fields and line items.
- Applying a template records audit-visible draft activity.
- The user can edit all applied values after application.
- Templates never submit or advance state.

### 9.4 Line Item Suggestions UX

Suggestions assist without taking control:

- As the requester types item name or category, show ranked suggestions.
- Selecting a suggestion fills item name, category if modeled, unit, estimated unit price, and currency.
- User can edit all suggested values after insertion.
- Suggestion load failure should hide suggestions or show non-blocking unavailable state.
- No authoring workflow should depend on suggestions being available.

## 10. Error Handling

Expected API failures and UI behavior:

- `422 validation_failed`: map field errors to inline fields and form summary.
- `403 forbidden`: render permission-denied or read-only state.
- `404 not_found`: render missing or inaccessible requisition state.
- `409 conflict`: render stale-save recovery.
- network or `5xx`: preserve local edits, show retry, and avoid data loss.
- inactive template: show non-blocking error and keep current draft unchanged.
- unavailable suggestions: keep form usable and suppress or annotate suggestions.

Conflict recovery should provide at least:

- reload latest server copy.
- keep local edits visible so the requester can compare manually.
- retry save after refresh or explicit overwrite only if the backend contract supports it safely.

## 11. Implementation Slices

### Slice 1: Draft Contract And Domain Hardening

- Confirm current requisition tables cover the authoring workflow.
- Add concurrency token to requisition responses and save requests.
- Harden server validation for department and cost center when present.
- Add backend regression tests for tenant isolation, permissions, validation, audit events, and stale save conflicts.
- Update OpenAPI and regenerate the generated client.

### Slice 2: Requester Draft Authoring Reliability

- Refactor save orchestration into a focused autosave/manual-save hook.
- Add visible save states and failed-save retry.
- Add unsaved-change guard.
- Add conflict recovery UI.
- Keep existing submission behavior unchanged except where compatibility requires minor adaptation.

### Slice 3: Template Application

- Add seeded/system templates and contract shape.
- Add template list/apply endpoints.
- Add UI for selecting and applying templates with `fill-empty` and `replace` modes.
- Record template application in activity.
- Add MSW and backend tests for template behavior.

### Slice 4: Line Item Suggestions

- Add seeded suggestion records or support data.
- Add suggestion endpoint and generated client usage.
- Add typeahead/select behavior in line item editing.
- Ensure suggestion failure is non-blocking.
- Add tests for selection and failure states.

### Slice 5: Authoring Polish And Integration Hardening

- Validate create, save, reopen, edit, template, and suggestion flows through real API integration.
- Confirm activity timeline shows authoring events.
- Review accessibility, keyboard flow, responsive layout, and save status announcements.
- Run contract verification and focused web/API checks.
- Run critical-path E2E tests.

## 12. Testing Strategy

### 12.1 Backend Feature Tests

Required tests:

- Requester can create a draft with minimum required fields.
- Requester can update own draft with organizational metadata and line items.
- Invalid department is rejected when present.
- Invalid cost center is rejected when present.
- Stale save returns `409 conflict` and does not overwrite the newer server version.
- Non-draft requisition cannot be edited.
- Non-draft requisition cannot receive template application.
- Template list is tenant-scoped.
- Applying template records an audit event and updates only allowed draft fields.
- Suggestion list is tenant-scoped and searchable.
- Another tenant cannot access draft, template application, or suggestions.

### 12.2 Frontend Feature Tests

Required tests:

- First save creates a draft and displays saved state.
- Autosave starts only after the draft has a server ID.
- Manual save flushes latest values.
- Failed save preserves local values and allows retry.
- Stale save shows conflict recovery instead of silent overwrite.
- Applying template to non-empty draft asks for confirmation.
- Template `fill-empty` mode preserves populated fields.
- Template `replace` mode replaces template-owned fields after confirmation.
- Line item suggestion fills fields only after user selection.
- Suggestion API failure does not block editing.
- Feature hooks use generated client endpoints or typed helpers.

### 12.3 Critical-Path E2E Tests

Epic 1 needs sufficient E2E coverage because draft authoring reliability is a trust-critical workflow. These tests should run against the integrated app stack or the closest existing project E2E harness, not only against isolated component tests.

Minimum E2E scenarios:

1. **Create, save, reopen, and continue draft**
   - Login as requester.
   - Open new requisition.
   - Enter title, department, cost center, needed-by date, justification, and one line item.
   - Save draft.
   - Navigate away to requisition list or detail workspace.
   - Reopen draft.
   - Confirm all saved values persist.
   - Edit a field and confirm the updated value persists after reload.

2. **Autosave after first save**
   - Create and manually save a draft.
   - Change a non-critical field and wait for autosave state to become saved.
   - Reload the page.
   - Confirm the autosaved change persists.

3. **Template application path**
   - Start a draft.
   - Apply an IT equipment or SaaS subscription template.
   - Confirm expected defaults and line items appear.
   - Save and reopen.
   - Confirm template-derived values persist and remain editable.

4. **Line item suggestion path**
   - Start or open a draft.
   - Type a common item search term.
   - Select a suggestion.
   - Confirm item name, unit, estimated price, and currency populate.
   - Save and reopen.
   - Confirm selected suggestion values persist as ordinary editable line item values.

5. **Stale save or conflict path**
   - Open the same draft in two browser contexts or simulate a stale version through the E2E harness.
   - Save a newer version in one context.
   - Attempt to save the stale version in the other context.
   - Confirm the UI shows conflict recovery and does not silently overwrite the newer server value.

6. **Authoring remains separate from submission scope**
   - Create and save an incomplete draft.
   - Confirm draft save succeeds even when submission-required fields are incomplete.
   - Confirm no new submit-side behavior is required for Epic 1 beyond preserving existing compatibility.

E2E coverage can be split across a small number of tests if setup cost is high, but the assertions above must be represented. If conflict simulation is too expensive for the first E2E harness, it must be covered by backend and frontend integration tests in the same implementation slice, and the reason must be documented before completion.

### 12.4 Contract Verification

For any OpenAPI change:

```bash
pnpm generate:api
pnpm check:api-contract
```

Generated client changes must be reviewed as source-code evidence of the contract shape.

### 12.5 Targeted Verification Commands

Expected focused checks for this epic:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
cd apps/api && php artisan test --filter=RequisitionApiTest
cd apps/api && php artisan route:list --path=api
pnpm check:api-contract
```

The implementation plan must identify the active E2E harness, test path, and command before coding the first E2E scenario.

## 13. Observability, Security, And Hardening

Before calling the epic complete:

- Tenant isolation is enforced in all draft, template, org metadata, and suggestion queries.
- Authorization covers draft creation, update, template application, and suggestion access.
- Audit events exist for important draft changes.
- Save conflicts are explicit and recoverable.
- Autosave failures do not destroy local input.
- API errors use the standardized Cognify error contract.
- Accessibility covers keyboard use, error summaries, focus handling, and save status announcements.
- Suggestions and templates degrade gracefully if unavailable.
- Existing submit behavior still passes its prior tests.

## 14. Exit Criteria

Epic 1 is complete when:

- A requester can create, save, leave, reopen, edit, and continue a draft without data loss.
- Autosave and manual save have visible, tested states.
- Stale saves are detected and recoverable.
- Department and cost center values are validated server-side when present.
- Requesters can apply seeded templates without advancing workflow state.
- Requesters can use seeded line item suggestions without requiring a catalog domain.
- Draft-created, draft-updated, and template-applied activity appears in the requisition timeline.
- Critical-path E2E tests cover create/save/reopen, autosave, template application, line item suggestions, and stale-save recovery or a documented equivalent coverage strategy.
- OpenAPI and generated client changes are current.
- Existing submission behavior still works, but no new Epic 2 submission scope was added.

## 15. Open Decisions For Implementation Planning

These decisions should be finalized during the implementation plan, not deferred into coding:

- Whether the concurrency token is derived from `updated_at` or stored as `lock_version`.
- Whether org metadata validation uses seeded tables, tenant settings, or a narrow Requisition-domain support table.
- Whether template application emits a distinct `requisition.template_applied` event or a `requisition.updated` event with template metadata.
- Which E2E harness and command should own the critical-path tests.

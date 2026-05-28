# P1 Full Audit Report

Date: 2026-05-27

Scope: P1 core procurement lifecycle from `docs/01-product/feature-roadmap.md`, P1-01 through P1-35.

Mode: Read-only source audit with parallel subsystem review. No production code was modified. This report is the only intended artifact from the pass.

## Executive Summary

P1 is broadly present in the repository. The implementation now covers requisition authoring, submission, projects, approvals, sourcing intake, RFQ management, vendor portal quotation capture, quotation normalization/comparison/scoring, award recommendation, award approval, PO handoff, and procurement calendar. The API route surface, OpenAPI/generated-client usage, backend domain actions, web feature groups, and test inventory all show substantial end-to-end implementation.

The main concern is not missing whole features. The main concern is workflow correctness and hardening after rapid feature delivery. Several roadmap rows are marked `Fully Implemented` while important edge cases, HTTP semantics, permission consistency, audit filtering, and UI accessibility still need follow-up. The highest-risk areas are:

- PO handoff and export mutate state from `GET` routes.
- Global search can expose RFQ, quotation, award, and vendor records more broadly than feature policies.
- Award approval policy matching does not evaluate award-specific fields reliably.
- Requisition change-request correction is backend-supported but the web form locks it, and resubmission skips the original submission validation.
- Project/requisition linking and project requisition listing have visibility gaps.
- Quotation version detail uses version row ID on the API side while web callers pass version number.

These are fixable without reshaping the whole architecture. The recommended next step is a focused P1 hardening epic, not new procurement capability work.

## Method

Inputs reviewed:

- `docs/01-product/feature-roadmap.md`
- `docs/02-release-management/2026-05-15-P1-Epics.md`
- `ARCHITECTURE.md`
- `docs/agentic/AGENTIC_CODING_GUIDELINES.md`
- Relevant `docs/superpowers/specs/*` and `docs/superpowers/plans/*`
- `apps/api/routes/api.php`
- Laravel domain code under `apps/api/app` and `apps/api/Domains`
- Web feature code under `apps/web/features`
- Generated-client surfaces under `packages/api-client`
- API and web test inventories

Parallel review split:

- P1-01 to P1-13: requisitions and projects
- P1-14 to P1-21 plus P1-33: approval orchestration and award approval
- P1-22 to P1-28: sourcing, RFQs, vendor portal, quotation capture
- P1-29 to P1-35: evaluation, award, PO handoff, calendar
- Cross-cutting: auth, tenancy, contracts, audit, search, export, security
- UI/UX: operational workflow usability, accessibility, responsive behavior

Tests were not run as part of this audit. Some useful commands, such as API contract checks and full test runs, may write generated output, caches, or database state. This pass intentionally stayed review-only.

## What Is Working

### Platform Fit

- P1 routes are mostly under `auth:sanctum` and `ResolveCurrentTenant`, with public vendor-token routes separated in `apps/api/routes/api.php`.
- Sanctum SPA behavior is in place through `statefulApi()`, login session regeneration, logout invalidation, CSRF bootstrap, and `credentials: "include"` usage.
- The app shell wraps authenticated workspaces with session gating, permission-aware navigation, command palette, notification host, right panel host, and mobile navigation.
- Most web feature API modules consume generated endpoints and schemas from `@cognify/api-client`, which keeps the contract discipline mostly intact.

### Requisitions And Projects

- Requisition draft creation, department/cost center capture, line items, templates, suggestions, autosave/manual save, conflict handling, submission, change request, withdrawal, cancellation, activity, comments, and mentions are represented in backend actions and web surfaces.
- Requisition lifecycle actions are mostly domain-action based and audited.
- Project records have a dedicated domain, status model, policy, API resources, web workflows, search integration, and API tests.

### Approvals

- The shared approval subject architecture is a good foundation: `ApprovalSubjectHandler`, `ApprovalSubjectRegistry`, requisition subject handlers, award subject handlers, and `RouteSubjectForApproval` keep the approval engine from being hard-coded only to requisitions.
- Approval task APIs cover queue/detail/view/approve/reject/request-changes/delegate.
- Award approval is integrated with the shared Approval domain and has route-stack and tenant tests.

### Sourcing, RFQs, Vendor Portal, Quotation Capture

- Buyer intake, RFQ draft, vendor invitations, portal link generation, quotation upload, manual quotation entry, and versioning are represented in routes, code, generated client, web features, and tests.
- Vendor portal token handling is materially sound for the current maturity stage: raw tokens are hashed, normal invitation listings do not expose raw tokens, invalid tokens 404, unavailable or expired access conflicts, and public routes are throttled.
- Buyer and vendor quotation capture converge through shared domain actions for quotation reveal, attachment upload, manual entry, and version snapshots.

### Evaluation, Award, Handoff, Calendar

- Normalization has queue/detail/review APIs, buyer/admin policies, correction/line-mapping/approval flows, redaction tests, and real login/logout coverage.
- Comparison reads approved normalization revisions rather than raw quotation data and keeps comparison notes non-decisioning.
- Scoring supports reusable templates, RFQ scorecard snapshots, weighted scoring, completion/reopen, and no direct award-state side effects.
- Award recommendation preserves evidence and rationale before routing to approval.
- Procurement calendar is implemented as a read-only Reporting read model over existing operational dates and exposes unavailable-source metadata where source data is not yet implemented.

### UI/UX

- The overall product shape fits an enterprise procurement tool: dense operational workspaces, tables, status badges, record layouts, and workflow actions instead of marketing-style screens.
- Shared table and record workspace primitives provide a good base for list/detail workflows.
- Command palette uses Radix Dialog and `cmdk`, which is stronger than the hand-rolled modal pattern used elsewhere.

## High Priority Findings

### H1. PO Handoff `GET` Creates Durable State

`GET /api/rfqs/{rfq}/award-recommendation/po-handoff` should read the current handoff. Current code creates a handoff if one does not exist:

- `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderRequestHandoffController.php:27`
- `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderRequestHandoffController.php:40`
- `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderRequestHandoffController.php:45`

Impact:

- Violates normal HTTP safety expectations.
- Makes prefetch/crawler/cache behavior risky.
- Confuses the route contract because a `POST` creation route also exists.

Recommended direction:

- Make `GET` read-only and return `null` or 404 when no handoff exists.
- Keep creation/reveal behavior on `POST`.
- Add route-stack regression tests that fail if a `GET` creates records.

### H2. PO Export `GET` Mutates Workflow State

PO export endpoints are `GET` downloads, but the export action changes status, records export metadata, increments `lock_version`, and writes audit events:

- `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php:55`
- `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php:67`
- `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php:74`
- `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php:84`

Impact:

- GET requests are not treated as state-changing by the frontend API helper's CSRF behavior.
- Repeated downloads mutate lock versions and export metadata.
- Future browser preload, link scanners, or integrations could trigger writes unintentionally.

Recommended direction:

- Split read-only download from explicit `POST /export` or `POST /mark-exported`.
- If export itself remains a state transition, make it a state-changing method with CSRF and a clear generated-client contract.

### H3. Global Search Leaks Records More Broadly Than Policies

RFQ, quotation, award, and vendor search providers primarily filter by tenant, not by actor-visible policy scope. Example:

- `apps/api/Domains/Search/Providers/RfqSearchProvider.php:23`
- `apps/api/Domains/Search/Providers/RfqSearchProvider.php:27`
- `apps/api/Domains/Search/Providers/RfqSearchProvider.php:29`

Impact:

- Requesters may discover sourcing, quotation, award, or vendor records they cannot otherwise access through feature screens.
- This conflicts with the architecture rule that search must be permission-aware.

Recommended direction:

- Apply the same policy-visible scope used by feature list/detail routes to each search provider.
- Add requester, buyer, approver, and cross-tenant search regression tests.

### H4. Award Policy Matching Does Not Evaluate Award-Specific Fields

`ApprovalContextData` carries award fields such as recommended amount and scorecard data, but `ApprovalPolicyMatcher::resolveFieldValue()` only resolves requisition-era fields:

- `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php:65`
- `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php:198`
- `apps/api/Domains/Approval/Services/ApprovalPolicyMatcher.php:200`

When no rule matches, the matcher can fall back to the highest-priority candidate with a warning. That means award approval may appear to route correctly while ignoring award-specific policy rules.

Impact:

- Award approval governance can be wrong while tests pass through fallback behavior.
- Policy preview can mislead users about why an award approval path was selected.

Recommended direction:

- Add matcher support for award fields.
- Add tests that prove award-specific rules match for the intended field, not because fallback selected a route.
- Decide whether unmatched durable routing should fail unless an explicit ruleless fallback policy exists.

### H5. Requisition Change Request Correction Is Broken In Web Flow

Backend permissions expose update capability for `draft` and `changes_requested`, but the web form only allows editing when status is `draft`:

- `apps/web/features/requisitions/forms/requisition-form.tsx:147`

Impact:

- P1-07 is only partially usable. Buyers or approvers can request changes, but requesters may not be able to correct the requisition through the intended form path.

Recommended direction:

- Drive editability from server permissions such as `permissions.canUpdate`, not local status strings.
- Add web tests for editing and saving `changes_requested` requisitions.

### H6. Requisition Resubmit Skips Submission Validation

Initial submission validates required fields and line items. Resubmission only checks status and flips the record back to submitted:

- `apps/api/Domains/Requisition/Actions/ResubmitRequisition.php:26`
- `apps/api/Domains/Requisition/Actions/ResubmitRequisition.php:33`

Impact:

- A malformed `changes_requested` requisition can be resubmitted.
- Backend state can drift away from the validation guarantees expected by buyers and approvers.

Recommended direction:

- Reuse the same submission eligibility validation on resubmit.
- Add regression tests for incomplete change-requested records.

### H7. Project Requisition Listing Can Expose Hidden Linked Requisitions

Once a user can view a project, `/api/projects/{project}/requisitions` returns all linked requisitions for that project and tenant:

- `apps/api/Domains/Project/Http/Controllers/ProjectRequisitionController.php:17`
- `apps/api/Domains/Project/Http/Controllers/ProjectRequisitionController.php:22`
- `apps/api/Domains/Project/Http/Controllers/ProjectRequisitionController.php:25`

Impact:

- A requester who can see one requisition on a project may see other linked requisitions in the project pipeline.

Recommended direction:

- Filter linked requisitions by the same actor-visible requisition scope used elsewhere.
- Add requester/approver visibility tests for project pipelines.

### H8. Requesters Can Link Drafts To Hidden Same-Tenant Projects

Requisition create/update validates same-tenant and non-terminal project status, but does not clearly prove the actor may see or link to that project. Project policy then treats linked requisitions as a visibility basis.

Impact:

- A user who guesses a same-tenant project ID may be able to attach their draft to it and gain project visibility.

Recommended direction:

- Centralize project-link authorization and apply it to requisition create/update plus project link endpoints.
- Add API tests for hidden same-tenant project IDs.

### H9. Quotation Version Detail Contract Is Ambiguous

The backend route treats `{version}` as `quotation_versions.id`:

- `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionController.php:40`
- `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionController.php:51`

The web API calls the parameter `versionNumber` and passes numeric version numbers:

- `apps/web/features/sourcing/api/quotation-api.ts:75`
- `apps/web/features/sourcing/api/quotation-api.ts:80`
- `apps/web/features/quotations/workflows/quotation-normalization-workspace.tsx:28`

Impact:

- Real flows can fetch the wrong version or fail when row ID differs from version number.
- Normalization workspace evidence may point at the wrong version detail.

Recommended direction:

- Decide whether the route is by version ID or version number.
- Align backend route, OpenAPI, generated client, hook naming, cache keys, and callers.
- Add regression with `quotation_versions.id != version_number`.

## Medium Priority Findings

### M1. Approval Delegation And Escalation Are Still Requisition-Specific

Delegation/escalation flows still load requisition relationships and only send some notifications for requisition subjects. Award tasks may miss notifications or break as approval subjects expand.

Recommended direction:

- Move delegation/escalation subject metadata and notification behavior behind `ApprovalSubjectHandler`.

### M2. Reject And Request-Changes Leave Future Tasks Misrepresented

Reject/request-changes cancel active sibling tasks, but future blocked-stage tasks can remain blocked under a rejected or changes-requested instance. Summary rendering can make non-active tasks look like completed decisions.

Recommended direction:

- Define terminal instance semantics for all active and future tasks.
- Update `ApprovalSummaryResource` behavior and tests.

### M3. Approval Task Comments Are Not Implemented

P1-15 says approval tasks should support comment actions. Routes cover view, approve, reject, request-changes, and delegate, but not task comments.

Recommended direction:

- Either add approval-task comments through the collaboration model or downgrade the roadmap row to partial until implemented.

### M4. Approval Policy Admin UI Is Too Thin For The Roadmap Claim

Backend accepts `rfq_award_recommendation` policies, but the web schema/form are still requisition-oriented and do not expose meaningful rule authoring, fallback approvers, approver selection, or multi-stage configuration.

Recommended direction:

- Reclassify this as partially implemented or build a fuller policy administration workflow.

### M5. Approval Task UI Ignores Per-Action Permissions

The API returns action permissions, but the task detail page renders action buttons when `task.status === "active"`:

- `apps/web/features/approvals/workflows/approval-task-detail-page.tsx:69`
- `apps/web/features/approvals/workflows/approval-task-detail-page.tsx:71`

Impact:

- API authorization still protects the backend, but users can see unusable buttons.

Recommended direction:

- Gate UI actions on generated permission flags.

### M6. Award Approval Now Auto-Creates PO Handoff

The award approval spec originally said it should not create PO handoff records, but approval completion now invokes PO handoff creation after P1-34 landed. This may be a valid post-P1-34 product decision, but docs/specs should be reconciled.

Recommended direction:

- Update P1-33/P1-34 docs to explain whether automatic handoff creation is deliberate.

### M7. Vendor Portal View Audit Is Over-Counted

The portal access resolver increments view count and records `portal_viewed` for package view, quotation lookup, version listing, upload, and manual-entry save.

Impact:

- Portal analytics and audit trail overstate real vendor package views.
- Read paths take unnecessary write locks.

Recommended direction:

- Separate token resolution from "record package viewed".
- Count only the actual package-open event unless product explicitly wants all portal calls counted.

### M8. Multi-File Upload Promise Is Ambiguous

The upload design mentions one or more files, but current backend/web requests handle one file per request. Repeated single-file upload works, but true multi-file submission is not implemented.

Recommended direction:

- Either implement multi-file request support or clarify the docs that multi-file means repeated uploads.

### M9. Audit Filtering Has Not Caught Up With P1

The public audit filter only accepts `subjectType=requisition`:

- `apps/api/app/Audit/Http/Controllers/AuditEventController.php:20`
- `apps/api/app/Audit/Http/Controllers/AuditEventController.php:23`

P1 now creates RFQ, quotation, scorecard, award, approval, and PO handoff audit events.

Recommended direction:

- Register and expose all P1 audit subjects.
- Add tests for filtering each subject type.

### M10. CSV Export Contract And Runtime Disagree

OpenAPI generated a CSV export endpoint, but web code bypasses it with raw `fetch`:

- `apps/web/features/quotations/api/quotation-award-recommendation-api.ts:206`
- `apps/web/features/quotations/api/quotation-award-recommendation-api.ts:210`

The generic generated-client fetch wrapper also needs a clear binary/text response contract for future exports.

Recommended direction:

- Add a shared generated-client-compatible blob/text download helper.
- Keep base URL, credentials, tenant headers, and error normalization consistent.

### M11. PO Handoff Partial PATCH Can Clear Omitted Fields

The update action uses `Arr::get()` for optional fields and writes omitted values as `null`:

- `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrderRequestHandoff.php:36`
- `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrderRequestHandoff.php:37`
- `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrderRequestHandoff.php:40`

Impact:

- Partial PATCH clients can accidentally erase finance notes or export memos.

Recommended direction:

- Define whether PATCH is partial or full replacement.
- Add omission-semantic tests.

### M12. Comparison Exposes `validUntil`, But Normalization Does Not Populate It

Comparison has a commercial term for validity, and quotation versions store `valid_until`, but deterministic normalization does not appear to carry manual entry validity into comparison output.

Recommended direction:

- Add regression coverage for valid-until flow from manual entry or version snapshot into comparison terms.

### M13. Scoring Can Reference Stale Quotation Versions

Scorecard updates accept a `quotationVersionId` belonging to the quotation, but do not require it to be the current version. Award submission later rejects stale recommended versions, but scoring evidence can still be captured against stale versions.

Recommended direction:

- Decide whether scoring should snapshot exactly what was scored or always require current versions.
- Add explicit tests for stale version scoring behavior.

### M14. Policies Sometimes Rely On Controller Query Shape For Tenant Safety

Some policies do not independently verify current-tenant ownership and rely on controller lookup filters. This pattern is currently protected at known call sites but is brittle for reuse.

Recommended direction:

- Strengthen policy-level tenant checks for reusable guards.
- Add direct policy/action tests for tenant mismatch where feasible.

### M15. `RequireTenantHeader` Is Strict But Narrowly Named

The middleware is applied to scoring, award, and PO handoff routes, but its error message says "quotation scoring routes".

Recommended direction:

- Rename/message it as a generic explicit-tenant-context middleware, or split if only some routes need strict header semantics.

## UI/UX Findings

### U1. Hand-Rolled Workflow Dialogs Need A Shared Accessible Primitive

Several dialogs lack the focus trap, Escape handling, backdrop close, and focus restore expected from Radix/shadcn dialogs. Examples include approval actions, approval delegation, sourcing decisions, and submit requisition dialogs.

Recommended direction:

- Create or adopt a shared `WorkflowDialog` backed by Radix/shadcn.
- Migrate approval, requisition, project, sourcing, and quotation dialogs.
- Add keyboard and focus-management tests.

### U2. Operational Filter State Is Mostly Local, Not URL-Backed

Requisition, project, approval, sourcing, and calendar work queues keep filters in local component state. This weakens refresh recovery, shareable URLs, browser back/forward behavior, and future saved views.

Recommended direction:

- Use `nuqs` or a shared query-state adapter for operational queues.

### U3. Approval Queue Bypasses The Stronger Shared Table UX

The approval queue uses a raw table with horizontal scrolling:

- `apps/web/features/approvals/tables/approval-tasks-table.tsx:28`
- `apps/web/features/approvals/tables/approval-tasks-table.tsx:30`

Other work queues use stronger table primitives with responsive behavior.

Recommended direction:

- Move approval queue to the shared `DataTable` pattern or extend the primitive to support approval-specific rows.

### U4. Dense Evaluation Tables Are Desktop-First

Comparison table, scoring matrix, and vendor portal line-item surfaces rely heavily on horizontal scrolling. This may be acceptable for buyer desktop evaluation, but it should be a deliberate product decision with responsive fallbacks for approvers and vendors.

Recommended direction:

- Prioritize responsive fallbacks for approval queue, calendar, award/PO review, and vendor portal.
- Keep scoring matrix desktop-first only if documented and covered by viewport tests.

### U5. Critical Award And PO Fields Need Visible Labels

Some decision fields rely on `aria-label` or placeholder-like affordances rather than persistent visible labels. Audit-heavy procurement workflows benefit from visible labels such as "Rationale", "Risk summary", and "Cancellation reason".

Recommended direction:

- Add visible labels and helper text for award/PO decision fields.

### U6. Calendar E2E Coverage Appears Stale

The current calendar UI uses checkbox source filters, while the Playwright test expects a "Source type" select.

Recommended direction:

- Refresh calendar E2E tests against the current UI.
- Add mobile and keyboard coverage for calendar filters.

## Maintainability Themes

### Domain Boundary Health

The overall architecture follows the intended boundary model: business behavior is mostly in Laravel domain actions, web workflows stay in `apps/web`, reusable primitives stay in shared packages, and generated contracts are broadly used.

Risk areas:

- Approval behavior is partly subject-handler based and partly requisition-specific.
- Project/requisition linking authorization is split across request rules, project actions, and policies.
- Quotation comparison, scoring, award recommendation, approval summary, and PO handoff build overlapping RFQ/vendor/quotation context arrays.
- Several web API modules repeat tenant-header helper logic instead of using one configured generated-client path.

Recommended direction:

- Add a P1 hardening pass before P2 that consolidates these repeated authorization and read-model patterns.

### Roadmap Status Accuracy

Some rows marked `Fully Implemented` are functionally present but not complete against the exact product wording:

- P1-15 approval task comments are not implemented.
- P1-24 communication history is status/deadline/manual portal-link tracking, not email or durable communication history.
- P1-26 multi-file upload is repeated single-file upload, not one multi-file request.
- P1-33/P1-34 handoff creation semantics need updated documentation after integration.

Recommended direction:

- Either adjust the roadmap status/notes or close the gaps as hardening work.

## Security And Vulnerability Concerns

### Most Important

- Mutating GET routes for PO handoff and export weaken CSRF and HTTP safety assumptions.
- Search visibility can leak records within the tenant beyond feature-policy access.
- Project/requisition visibility can leak project or linked requisition context through linking and pipeline listing.
- Award approval routing may ignore award-specific conditions and fall back silently.

### Important But Lower Risk

- Vendor portal token flow is generally sound, but portal access analytics are noisy and write-heavy.
- Policy-level tenant checks should be strengthened where controller filtering currently carries the guarantee.
- CSV export needs generated-client-compatible handling to keep headers, base URL, credentials, and errors consistent.
- Audit subject filtering is incomplete for P1, weakening governance review rather than directly leaking data.

## Test And Verification Gaps

Add focused tests before production fixes:

- `GET` PO handoff must not create records.
- PO export state transition must use an explicit state-changing method, or repeated downloads must be safe and intentional.
- Search providers must respect actor visibility for requesters, buyers, approvers, admins, and cross-tenant records.
- Award policy matcher must match award-specific fields without fallback.
- Requisition correction web flow must allow `changes_requested` edits when `canUpdate` is true.
- Resubmit must fail when submission requirements are not met.
- Project pipeline must filter linked requisitions by actor visibility.
- Requisition create/update must reject hidden same-tenant project IDs.
- Quotation version detail must work when database ID differs from version number.
- Portal view count/audit must increment only for intended view events.
- PO PATCH omission semantics must be explicit.
- Audit subject filters must cover P1 subject types.
- Workflow dialogs need keyboard/focus tests.
- Calendar and approval queue need mobile and accessibility coverage.

Suggested verification after fixes:

```bash
php artisan test tests/Feature/RequisitionApiTest.php tests/Feature/ProcurementProjectApiTest.php
php artisan test tests/Feature/ApprovalTaskApiTest.php tests/Feature/RfqAwardApprovalApiTest.php
php artisan test tests/Feature/SearchApiTest.php tests/Feature/PurchaseOrderRequestHandoffApiTest.php
php artisan test tests/Feature/QuotationVersionApiTest.php tests/Feature/ProcurementCalendarApiTest.php
pnpm check:api-contract
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm lint
pnpm build
git diff --check
```

Run contract generation before web checks when OpenAPI changes, and serialize these commands to avoid generated-client race conditions.

## Recommended Hardening Sequence

1. Fix security/semantic issues first: mutating GETs, search visibility, project visibility, quotation version contract.
2. Fix requisition correction flow: web editability and resubmit validation.
3. Fix award approval governance: award-specific policy matching and fallback behavior.
4. Harden approval lifecycle: subject-handler delegation/escalation, terminal task cleanup, approval comments, permission-gated UI actions.
5. Reconcile PO handoff/export contract: generated-client CSV handling, PATCH semantics, audit behavior.
6. Close audit/reporting gaps: P1 audit subject registration and filters.
7. Improve UI accessibility: shared Radix-backed workflow dialog, URL-backed queue state, approval queue table, visible labels, responsive surfaces.
8. Update roadmap notes to distinguish shipped MVP behavior from full product wording where intentionally deferred.

## Additional Review Pointers

No additional tools are required to continue a static code audit. The repo already has useful local tools for the next review layer:

- Playwright is available for screenshot and viewport review.
- `axe-core` is available for accessibility checks.
- Laravel and Vitest test suites cover most P1 domains.
- `pnpm check:api-contract` validates OpenAPI/generated-client drift.

Optional tools worth installing later if the team wants a deeper security pass:

- Semgrep for static application-security rules.
- OSV-Scanner or Trivy for dependency vulnerability checks.
- A browser screenshot workflow tied to Playwright for UI review artifacts.

For this first review pass, the highest value is focused regression tests and code fixes against the findings above, then a screenshot/accessibility pass for the main P1 workflows.

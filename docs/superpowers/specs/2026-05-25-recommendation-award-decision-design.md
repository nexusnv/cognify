# Recommendation and Award Decision Design

## Status

- Status: Draft for review
- Date: 2026-05-25
- Release scope: P1 Epic 7, slice 4 only
- Related roadmap: `docs/01-product/feature-roadmap.md` feature `P1-32`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`
  - `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`
  - `docs/superpowers/specs/2026-05-21-quotation-normalization-design.md`
  - `docs/superpowers/specs/2026-05-22-quotation-comparison-table-design.md`
  - `docs/superpowers/specs/2026-05-24-vendor-scoring-matrix-design.md`

## Purpose

This slice lets buyers record an auditable award recommendation for one RFQ. A buyer selects the recommended vendor response, explains the rationale, references supporting evidence, and submits the recommendation into a `pending_approval` handoff state.

P1-32 captures the decision context. It does not approve the award, create approval tasks, notify vendors, mark an RFQ as awarded, or create a purchase order handoff. Those transitions belong to P1-33 and P1-34.

## Problem

The quotation comparison and scoring slices give buyers structured evaluation inputs, but Cognify still needs the formal recommendation step that explains what the buyer wants to do and why. Without this step, award rationale remains informal and downstream approvers or auditors cannot reliably connect the selected vendor to comparison data, scoring, quotation versions, evidence, and exceptions.

The award recommendation must preserve the evaluated quotation version and rationale at the time of submission. It must also stay narrow enough that the next slice can route approval tasks without disentangling recommendation capture from approval orchestration.

## Goals

- Add an RFQ-level award recommendation workspace.
- Let buyers choose one recommended vendor response for an RFQ.
- Capture rationale, tradeoff summary, risk summary, and exception summary.
- Reference supporting evidence from existing quotation, comparison, and scoring artifacts.
- Save draft recommendations and resume them later.
- Submit recommendations into `pending_approval` for P1-33 to consume.
- Withdraw a submitted recommendation before approval routing exists.
- Preserve selected quotation version, scorecard reference, evidence references, and audit history.
- Keep the workflow tenant-scoped, policy-guarded, audit-backed, and OpenAPI-backed.
- Keep approval task creation, award approval, RFQ award status, vendor notifications, and PO handoff out of this slice.

## Non-Goals

- Creating approval tasks or invoking approval policy routing.
- Approving, rejecting, or finalizing an award.
- Marking an RFQ, quotation, invitation, vendor, requisition, or project as awarded.
- Sending vendor-facing award or regret notifications.
- Purchase order request handoff or ERP export.
- New award-specific file upload pipeline.
- Evidence Vault, evidence annotation, audit pack generation, or document retention policy.
- AI-generated award narratives, AI recommendation ranking, or automated risk scoring.
- Split awards, line-level awards, partial awards, multi-round negotiation, or scenario modeling.
- A standalone awards module or `Award` domain.

## Design Decisions

### Keep Initial Ownership In The Quotation Domain

Backend ownership remains in `apps/api/Domains/Quotation` and frontend ownership remains under `apps/web/features/quotations`. The roadmap ownership map allows award decision work to live in the quotation domain until the award lifecycle is large enough to split out.

This slice is still centered on evaluating RFQ quotation responses. A future `Award` domain can be introduced when approval, PO handoff, awarded-state management, vendor communications, and post-award operations create a broader lifecycle.

### Create A Real Recommendation Record

The recommendation should not be a special comparison note or a field on the scorecard. It needs its own state, selected vendor identity, selected quotation version, rationale fields, evidence references, and audit events.

This gives P1-33 a stable `pending_approval` source record without forcing approval behavior into P1-32.

### Reference Evidence Instead Of Uploading New Award Files

P1-32 supports evidence references only. Buyers can cite existing quotation versions, quotation attachments, comparison notes, and the RFQ scorecard. The slice does not create a new award attachment upload pipeline.

This keeps the feature usable and auditable without blocking on P2 Evidence Vault. New award-specific uploads can be added later when evidence ownership, classification, preview, retention, and audit pack behavior are designed.

### Preserve Evaluated Quotation Version

The recommendation stores the selected current quotation version. Submit must reject stale selections if a newer current quotation version exists before submission.

This avoids approving a recommendation for a vendor response that is no longer the evaluated version.

### Treat Scoring As Strong Context, Not A Hard Dependency

If an RFQ has a scorecard, submitting the recommendation should require the scorecard to be completed. If no scorecard exists, submission can proceed when comparison data is ready, but the UI should show that no scoring artifact is attached.

This respects P1-31 as an explanatory evaluation layer without making scoring mandatory for every award decision in the roadmap.

## Workflow

### Actors

- Buyer: creates, edits, submits, views, and withdraws award recommendations.
- Admin: same abilities as buyer for tenant workflows.
- Requester: no award recommendation access in P1-32.
- Approver: no approval-side award recommendation view in P1-32.
- Vendor portal visitor: no access.
- System: validates ownership, freezes selected context, records audit events, and exposes the `pending_approval` handoff state.

### States

- `draft`: editable recommendation saved for the RFQ.
- `pending_approval`: submitted recommendation, read-only except withdrawal.
- `withdrawn`: submitted recommendation withdrawn before approval routing exists.

Later states such as `approved`, `rejected`, `awarded`, and `po_handoff_ready` are out of scope for this slice.

### Entry Points

The primary entry points are the RFQ comparison and scoring workspaces. A buyer opens the award recommendation workspace from those surfaces.

Route:

```txt
/quotations/awards/[rfqId]
```

### Read Flow

1. Buyer opens `/quotations/awards/[rfqId]`.
2. Web hook calls the generated API client with `X-Tenant-Id`.
3. API verifies session, tenant, RFQ ownership, and policy.
4. API loads RFQ summary, current vendor quotation options, comparison readiness, scorecard summary, existing recommendation, and evidence references.
5. UI renders vendor options, decision form, evidence selector, and comparison/scoring context.

### Draft Flow

1. Buyer selects a vendor response or edits rationale fields.
2. Buyer saves a draft.
3. API validates any selected vendor, quotation, quotation version, scorecard, and evidence references that are present.
4. API upserts the RFQ recommendation in `draft` state.
5. API records `rfq_award_recommendation.saved`.
6. Web invalidates the recommendation query and re-renders the saved draft.

Drafts can be incomplete. They should preserve buyer work without requiring all submit-time fields.

### Submit Flow

1. Buyer completes selected vendor response, rationale, and evidence references.
2. Buyer submits the recommendation.
3. API locks the RFQ and recommendation record.
4. API validates selected vendor, quotation, current quotation version, same-tenant ownership, same-RFQ ownership, scorecard completion when a scorecard exists, and evidence references.
5. API sets status to `pending_approval`, stores `submitted_by_user_id`, and stores `submitted_at`.
6. API records `rfq_award_recommendation.submitted`.
7. UI renders the recommendation read-only with a withdrawal action.

Submit does not create approval tasks. P1-33 will consume `pending_approval` recommendations.

### Withdraw Flow

1. Buyer opens a submitted `pending_approval` recommendation.
2. Buyer provides a withdrawal reason.
3. API validates state and policy.
4. API sets status to `withdrawn`, stores withdrawal metadata, and records the reason.
5. API records `rfq_award_recommendation.withdrawn`.
6. UI shows the recommendation as withdrawn.

Withdrawal exists so teams can correct a submitted recommendation before award approval routing is implemented.

## Data Model

Backend ownership remains in `apps/api/Domains/Quotation`.

### `rfq_award_recommendations`

Stores the buyer's RFQ-level recommendation.

- `id`
- `tenant_id`
- `rfq_id`
- `recommended_vendor_id`
- `recommended_quotation_id`
- `recommended_quotation_version_id`
- `scorecard_id`, nullable
- `status`
- `rationale`
- `tradeoff_summary`, nullable
- `risk_summary`, nullable
- `exception_summary`, nullable
- `withdrawal_reason`, nullable
- `created_by_user_id`
- `updated_by_user_id`, nullable
- `submitted_by_user_id`, nullable
- `submitted_at`, nullable
- `withdrawn_by_user_id`, nullable
- `withdrawn_at`, nullable
- timestamps

Validation rules:

- One non-withdrawn recommendation per RFQ.
- `rationale` is required on submit, trimmed, and capped.
- Summary fields are optional, trimmed, and capped.
- `recommended_vendor_id`, `recommended_quotation_id`, and `recommended_quotation_version_id` are required on submit.
- Selected vendor must belong to an RFQ invitation or current quotation response for the same RFQ and tenant.
- Selected quotation must belong to the selected vendor, RFQ, and tenant.
- Selected quotation version must belong to the selected quotation and must be the current evaluated version at submit time.
- If `scorecard_id` is present, it must belong to the same RFQ and tenant.
- If an RFQ scorecard exists, submit requires that scorecard to be `completed`.
- `pending_approval` records are read-only except withdrawal.

### `rfq_award_recommendation_evidence`

Stores references to existing evidence-bearing records.

- `id`
- `tenant_id`
- `recommendation_id`
- `evidence_type`
- `evidence_id`
- `label`, nullable
- timestamps

Allowed `evidence_type` values:

- `quotation_version`
- `quotation_attachment`
- `comparison_note`
- `scorecard`

Validation rules:

- Referenced evidence must belong to the same tenant and RFQ.
- Evidence references are replaced atomically when a draft or submitted recommendation is saved.
- `label` is optional, trimmed, and capped.

## API Contract

Add authenticated tenant-scoped endpoints under the existing RFQ route group:

```txt
GET    /api/rfqs/{rfq}/award-recommendation
PUT    /api/rfqs/{rfq}/award-recommendation
POST   /api/rfqs/{rfq}/award-recommendation/submit
POST   /api/rfqs/{rfq}/award-recommendation/withdraw
```

The endpoints must be defined in `apps/api/storage/openapi/openapi.json` and consumed through regenerated `packages/api-client` code.

### Request Schemas

Use explicit OpenAPI schema names:

- `SaveRfqAwardRecommendationRequest`
- `SubmitRfqAwardRecommendationRequest`
- `WithdrawRfqAwardRecommendationRequest`
- `RfqAwardRecommendationEvidenceReferenceInput`

Draft save accepts partial recommendation fields. Submit accepts the same recommendation payload plus submit intent, or submits the already-saved draft if no payload is included. Withdraw requires a non-empty reason.

### Response Schemas

Use explicit OpenAPI schema names:

- `RfqAwardRecommendationResponse`
- `RfqAwardRecommendation`
- `RfqAwardRecommendationContext`
- `RfqAwardRecommendationVendorOption`
- `RfqAwardRecommendationEvidenceReference`
- `RfqAwardRecommendationPermissions`
- `RfqAwardRecommendationScorecardSummary`
- `RfqAwardRecommendationReadiness`

The response includes:

- `rfq`: ID, number, title, status, response due date, requisition/project summary when available.
- `recommendation`: existing recommendation or `null`.
- `vendorOptions`: one option per current RFQ quotation response, with vendor name, quotation ID, quotation version ID, readiness, amount, currency, lead time, score totals when available, missing required score count, and links.
- `scorecard`: scorecard summary when present.
- `readiness`: comparison readiness and scoring readiness.
- `evidenceReferences`: evidence currently attached to the recommendation.
- `links`: comparison, scoring, quotation version, and normalization links.
- `permissions`: `canViewAwardRecommendation`, `canManageAwardRecommendation`, `canSubmitAwardRecommendation`, `canWithdrawAwardRecommendation`.

## Permissions

Add a policy path for award recommendations under the quotation domain.

Rules:

- Buyer/admin can view award recommendations for RFQs in the current tenant when they can view/manage the RFQ.
- Buyer/admin can save drafts, submit drafts, and withdraw `pending_approval` recommendations.
- Requesters and approvers do not receive P1-32 access unless an existing RFQ policy already grants buyer-like access. Approval-facing visibility belongs in P1-33.
- Vendors cannot access award recommendation endpoints.
- Every query and mutation must verify tenant ownership across RFQ, invitation, quotation, quotation version, vendor, scorecard, comparison note, attachment reference, recommendation, and actor.

Frontend permission flags hide actions, but backend policies and domain actions remain authoritative.

## UI Design

Frontend work belongs in `apps/web/features/quotations`.

Suggested route:

```txt
apps/web/app/(workspace)/quotations/awards/[rfqId]/page.tsx
```

Suggested feature files:

```txt
apps/web/features/quotations/
  api/quotation-award-recommendation-api.ts
  hooks/use-rfq-award-recommendation.ts
  hooks/use-rfq-award-recommendation-actions.ts
  components/rfq-award-vendor-option-list.tsx
  components/rfq-award-rationale-form.tsx
  components/rfq-award-evidence-selector.tsx
  components/rfq-award-decision-summary.tsx
  workflows/rfq-award-recommendation-workspace.tsx
  mocks/quotation-award-recommendation-fixtures.ts
  mocks/quotation-award-recommendation-handlers.ts
  tests/rfq-award-recommendation-workspace.test.tsx
  tests/quotation-award-recommendation-api.test.ts
```

The workspace should use `RecordWorkspaceLayout`.

Primary regions:

- Header: RFQ number/title, status, back links to comparison and scoring.
- Vendor options: dense selectable table/list with amount, currency, lead time, readiness, score totals, and missing score count.
- Decision form: rationale, tradeoff summary, risk summary, exception summary.
- Evidence selector: existing quotation version, quotation attachment, comparison note, and scorecard references.
- Decision summary: selected vendor, selected quotation version, readiness warnings, and submit/withdraw state.
- Sidebar: compact comparison readiness and scorecard summary.

States:

- Loading.
- No eligible vendor responses.
- Draft recommendation.
- Pending approval read-only recommendation.
- Withdrawn recommendation.
- Permission denied.
- Stale selected quotation version.
- Incomplete scorecard blocking submit.
- API validation error.

Submit behavior:

- Disabled until selected vendor response and rationale are present.
- Blocked if selected quotation version is stale.
- Blocked if a scorecard exists but is incomplete.
- Allowed without scorecard when comparison readiness is complete, while clearly showing that no scoring artifact is attached.

## Audit And Side Effects

Record audit events through `AuditRecorder`:

- `rfq_award_recommendation.saved`
- `rfq_award_recommendation.submitted`
- `rfq_award_recommendation.withdrawn`

Event metadata should include:

- RFQ ID and number.
- Recommendation ID.
- Recommended vendor ID.
- Recommended quotation ID.
- Recommended quotation version ID.
- Scorecard ID, nullable.
- Previous and next status.
- Evidence reference count.

Notifications are out of scope for P1-32. P1-33 should emit approval notifications when approval tasks are created.

## Error Handling

Expected API failures:

- `404` when RFQ or recommendation is not found in the tenant.
- `403` for unauthorized actors.
- `409` for invalid state transitions, stale quotation version, duplicate non-withdrawn recommendation, pending record update attempt, and incomplete scorecard submit.
- `422` for invalid payload fields or evidence references.

The frontend should preserve user-entered draft form state when save or submit fails and show actionable messages near the affected form region.

## Testing

### API Tests

Add focused feature tests for:

- Buyer/admin can load award recommendation context.
- Buyer can create and update a draft.
- Submit creates `pending_approval` and records submit metadata.
- `pending_approval` recommendation is read-only except withdrawal.
- Withdraw requires reason and records withdrawal metadata.
- Submit rejects stale quotation versions.
- Submit rejects incomplete scorecard when a scorecard exists.
- Submit can proceed without a scorecard when comparison readiness is complete.
- Evidence references must belong to the same RFQ and tenant.
- Cross-tenant RFQ, vendor, quotation, scorecard, and evidence references are rejected.
- Requester, approver, and vendor portal actors cannot access endpoints.
- Audit events are recorded for save, submit, and withdraw.

### Web Tests

Add focused tests for:

- Workspace loading and populated state.
- Empty state when no eligible vendor responses exist.
- Selecting a vendor option updates the decision summary.
- Draft save calls the generated-client wrapper and preserves fields.
- Submit disabled until required fields are present.
- Incomplete scorecard blocks submit with clear copy.
- Pending approval renders read-only fields and shows withdrawal action.
- Withdrawal submits reason and refreshes state.
- Evidence selector renders allowed existing evidence references.
- API validation errors render without losing local draft inputs.

### Contract And Verification

Implementation should run:

```bash
pnpm generate:api
pnpm check:api-contract
cd apps/api && php artisan test --filter=RfqAwardRecommendation
pnpm --filter @cognify/web test -- features/quotations/tests/rfq-award-recommendation-workspace.test.tsx features/quotations/tests/quotation-award-recommendation-api.test.ts
pnpm lint
pnpm typecheck
git diff --check
```

If generated client or OpenAPI changes reveal contract drift, fix contract and consumers before broad validation.

## Implementation Notes

- Keep controllers thin and put workflow behavior in actions such as `SaveRfqAwardRecommendation`, `SubmitRfqAwardRecommendation`, and `WithdrawRfqAwardRecommendation`.
- Use policies for view/update/submit/withdraw authorization.
- Use transactions and row locks around submit and withdraw transitions.
- Do not duplicate comparison/scoring totals into recommendation rows except for stable references. The API response can assemble display context from existing comparison and scorecard read paths.
- Keep MSW fixtures OpenAPI-shaped and deterministic.
- Keep production components dependent on hooks/API wrappers, not mock fixtures.


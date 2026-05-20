# Vendor Portal Baseline Design

## Status

- Status: Draft for review
- Date: 2026-05-20
- Release scope: P1 Epic 6, slice 1 only
- Related roadmap: `docs/02-release-management/2026-05-15-P1-Epics.md`, `docs/01-product/feature-roadmap.md`
- Architecture alignment: `ARCHITECTURE.md`, `docs/05-runbooks/feature-development.md`
- Predecessor slices:
  - `docs/superpowers/specs/2026-05-19-buyer-intake-sourcing-review-design.md`
  - `docs/superpowers/specs/2026-05-19-rfq-draft-creation-design.md`
  - `docs/superpowers/specs/2026-05-19-vendor-invitation-to-rfq-design.md`

## Purpose

This slice gives invited vendors a secure, read-only external view of an RFQ package through an invitation token. It turns the internal RFQ invitation record into the first vendor-facing workflow surface while deliberately avoiding quotation upload, manual quote entry, quotation versioning, comparison, scoring, award, approval, and purchase order handoff.

The first portal slice should prove the hardest boundary early: external access to tenant-owned procurement data without a full vendor account model. Once token access, expiry, revocation, audit, and vendor-safe RFQ projection are stable, later Epic 6 slices can add quotation capture on top of the same portal foundation.

## Problem

Epic 5 lets buyers create RFQs and record vendor invitations, but vendors still cannot view the RFQ package in Cognify. If the next slice jumps directly to quotation upload or manual quote capture, the system will lack a secure external access model and the upload workflow will be forced to invent one under pressure.

The portal baseline must answer:

- how a vendor reaches an invited RFQ;
- what RFQ data is safe to expose externally;
- how access is revoked when an invitation is cancelled or expired;
- how vendor access is audited without requiring internal user authentication;
- how later quotation submission can attach to the same invitation boundary.

## Goals

- Generate and store secure opaque access tokens for RFQ invitations.
- Let a vendor with a valid token view a vendor-safe RFQ package.
- Expose invitation status, response deadline, buyer instructions, scope, line items, and required documents needed to respond.
- Deny invalid, expired, cancelled, or otherwise inactive token access without leaking private RFQ details.
- Record auditable portal access events.
- Keep the buyer RFQ invitation workspace compatible with the new token-backed portal state.
- Define OpenAPI contracts and regenerate `@cognify/api-client`.
- Preserve a clean handoff to quotation upload and structured quotation entry slices.

## Non-Goals

- Vendor account registration, login, password reset, or vendor user management.
- Email delivery of invitation links.
- Quotation upload.
- Manual quotation entry.
- Quotation versioning.
- Quotation normalization, comparison, scoring, recommendation, award decision, award approval, or PO handoff.
- Vendor profile management or standalone vendor directory.
- Two-way vendor messaging or Q&A.
- AI extraction, OCR, or recommendation behavior.

## Actors

- Buyer: creates and manages RFQ invitations from the authenticated RFQ workspace.
- Admin: can manage tenant RFQ invitations where policy allows.
- Vendor contact: opens a token link and views the RFQ package without an internal Cognify account.
- System: issues tokens, validates token access, revokes unusable invitations, and records audit events.

## Workflow

The workflow starts from an existing RFQ invitation.

```txt
buyer creates or resends invitation
  -> system ensures invitation has a portal token
  -> vendor opens token URL
  -> system validates token and invitation state
  -> vendor views read-only RFQ package
  -> system records portal view audit event
```

Access outcomes:

```txt
valid active token
  -> show RFQ package

unknown token
  -> generic invalid-link state

expired token
  -> generic expired-link state

cancelled or inactive invitation
  -> generic unavailable-link state
```

Workflow rules:

- Tokens are opaque random values and never encode tenant, RFQ, vendor, or invitation IDs.
- A token belongs to one RFQ invitation.
- Token access is read-only.
- Cancelled invitations immediately make portal access unavailable.
- Expired invitations should not expose RFQ details.
- Vendor portal responses must be projected from the invitation and RFQ relationship, not from unscoped IDs in the URL.
- Portal access must not require `X-Tenant-Id`; the token resolution determines the tenant context for that read-only request.

## Backend Design

Backend ownership belongs in `apps/api/Domains/Quotation` because the portal baseline extends RFQ invitation behavior. `apps/api/Domains/Vendor` remains a supporting data source through the existing vendor model. Cross-cutting token hashing and audit recording can use existing Laravel and `apps/api/app` infrastructure.

Extend `RfqInvitation` with token state:

- `portal_token_hash`
- `portal_token_created_at`
- `portal_token_expires_at`
- `portal_last_viewed_at`
- `portal_view_count`

The raw token should be returned only when issuing or regenerating the link. Store only a hash at rest. Existing internal list/detail resources may expose whether portal access exists and expires, but must not expose the raw token after creation.

Domain actions should own behavior:

- ensure or regenerate portal token for an invitation;
- resolve a portal token to an invitation;
- validate invitation state for portal viewing;
- record portal access audit;
- project the vendor-safe RFQ payload.

Controllers should stay thin:

- internal invitation actions call token issuance where needed;
- public portal controller resolves the token and returns a safe resource.

## API Contract

OpenAPI should define the contract before the frontend hardens against it.

Proposed endpoints:

- `GET /api/vendor-portal/rfq-invitations/{token}`
  Resolve a token and return a vendor-safe RFQ invitation view.
- `POST /api/rfq-invitations/{invitation}/portal-link`
  Internal buyer/admin endpoint to regenerate a portal token when allowed.

The vendor-safe response should include:

- invitation ID as a string;
- RFQ number and title;
- RFQ scope summary;
- response due date;
- response instructions;
- required documents;
- line items;
- vendor display name;
- invitation status;
- portal access expiry;
- tenant display name.

The response must exclude:

- internal notes;
- evaluation notes;
- requester-only approval context;
- audit timeline;
- project budget details;
- competing vendors;
- other invitation records;
- internal permission maps.

Error responses should use existing API error conventions:

- `404` with generic invalid-link copy for unknown tokens;
- `409` with the existing invalid-state shape for expired or unavailable portal access;
- normalized validation and transient failure shapes where applicable.

## Frontend Design

Vendor portal UI should live outside authenticated sourcing workspaces. Use a new feature folder so the external surface does not inherit buyer-only assumptions:

```txt
apps/web/features/vendor-portal/
  api/
  components/
  hooks/
  mocks/
  tests/
  types/
  workflows/
```

Suggested route:

```txt
apps/web/app/vendor/rfq-invitations/[token]/page.tsx
```

The page should not use `SessionGate`. It should render:

- loading state;
- invalid or expired link state;
- unavailable invitation state;
- populated read-only RFQ package;
- response deadline emphasis;
- required document checklist;
- line item table;
- clear copy that quotation submission arrives in a later workflow if upload is not available yet.

The buyer RFQ invitation panel may show portal availability and expiry, but should not become a token-sharing UX unless the implementation plan explicitly includes a safe copy-link action. Email delivery remains out of scope.

## Permissions And Tenancy

Internal portal-link regeneration is protected by `auth:sanctum`, `ResolveCurrentTenant`, and `RfqInvitationPolicy`.

Vendor portal read access is public in the authentication sense, but not public in the data sense. It must be constrained by:

- token hash lookup;
- invitation state;
- token expiry;
- RFQ and vendor relationships loaded through the invitation;
- generic error states that avoid tenant or RFQ enumeration.

No vendor portal endpoint should accept a tenant header as authority. No portal endpoint should accept RFQ ID plus vendor ID as proof of access.

## Audit And Observability

Audit events should include:

- `rfq_invitation.portal_token_created`
- `rfq_invitation.portal_token_regenerated`
- `rfq_invitation.portal_viewed`

Audit metadata should include tenant ID, RFQ ID, invitation ID, vendor ID, and a safe request context such as user agent or IP hash if the existing audit standards allow it. The vendor contact is not an authenticated `User`, so actor fields should use a system actor/null actor convention defined in the implementation plan.

Denied token access should be handled through normal application logs rather than audit events in this slice. Logs must not include raw tokens.

## Data And State Rules

Portal access should be allowed only while the invitation remains in a non-terminal response-eligible state. The implementation plan should map the exact allowed statuses, but the default should be:

- allowed: `sent`
- unavailable: `cancelled`, `declined`, `expired`
- conditional: `acknowledged` if acknowledgement only means the vendor opened or confirmed interest

The status mapping must not accidentally block future quotation upload. If `acknowledged` means "vendor has accessed the portal" in a later slice, it should remain portal-readable.

Token expiry should default to the RFQ response due date when present, with a small configurable grace period only if needed. If no response due date exists, use a fixed expiry duration so links are not permanent.

## Testing Strategy

Backend tests:

- valid token returns vendor-safe RFQ payload;
- invalid token returns generic denial;
- expired token returns generic expired/unavailable response;
- cancelled invitation denies portal access;
- cross-tenant data cannot be reached through token manipulation;
- portal view records audit without an authenticated user;
- internal portal token regeneration requires buyer/admin permission;
- raw token is not returned by normal invitation list responses.

Frontend tests:

- vendor portal page renders the RFQ package for a valid token;
- invalid token state is clear and does not expose private details;
- expired or unavailable invitation state is clear;
- required documents and line items render from OpenAPI-shaped MSW fixtures;
- page does not require authenticated session context.

Contract checks:

- update `apps/api/storage/openapi/openapi.json`;
- regenerate `packages/api-client/src/generated/**`;
- run `pnpm check:api-contract`.

## Scope Boundary For Later Epic 6 Slices

Slice 2, quotation upload, should attach files to the invitation/RFQ context and can reuse the portal token access model. It should not redesign token resolution.

Slice 3, manual quotation entry, should create structured quotation records for buyer-entered or vendor-entered responses.

Slice 4, quotation versioning, should preserve revisions and evaluation snapshots after quotation records exist.

This slice should end with a secure vendor-readable RFQ package and no quotation submission.

## Exit Criteria

- A vendor contact with a valid token can view only the RFQ package they were invited to view.
- Invalid, expired, cancelled, or unavailable token states do not leak tenant, RFQ, or vendor details.
- Buyers/admins can regenerate portal access where policy allows.
- Portal access and regeneration produce audit events.
- OpenAPI and generated client artifacts match.
- The implementation leaves a clear extension point for quotation upload without implementing it.

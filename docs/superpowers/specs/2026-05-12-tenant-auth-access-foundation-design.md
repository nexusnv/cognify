# Tenant, Auth, Access Foundation Design

## Context

Cognify P0 groups the multi-tenant account model, authentication, role and permission baseline, and user profile/preferences into the first epic: Tenant, Auth, Access Foundation.

This design uses a contract-first lean foundation. It builds the identity and access surface needed by P0 procurement workflows without adding full tenant administration, invitation flows, SSO, SCIM, or enterprise role management.

Current repo state already includes:

- `apps/api/app/Tenancy/Tenant`
- `apps/api/app/Tenancy/CurrentTenant`
- `apps/api/app/Http/Middleware/ResolveCurrentTenant`
- `apps/api/app/Auth/TenantRole`
- `apps/api/app/Models/User`
- Sanctum-protected requisition routes
- A tenant/user pivot with a `role` column
- Requisition tests that already exercise tenant isolation and coarse roles

The design should harden and expose these foundations rather than replace them.

## Approved Scope

This epic delivers:

- Tenant context and memberships.
- Sanctum-backed authentication for the first-party Next.js app.
- Baseline tenant roles: `requester`, `buyer`, `approver`, and `admin`.
- Explicit permission output for frontend workflow decisions.
- Basic profile/preferences: `name`, `email`, `avatar`, `timezone`, `locale`, and `theme`.

This epic does not deliver:

- User invitation.
- Role management UI.
- Tenant settings UI.
- SSO or SCIM.
- Finance, auditor, or vendor manager roles.
- Notification preferences.
- Enterprise admin workflows.

Deferred roles should be introduced only when their owning workflows arrive.

## Workflow

1. A user signs in with email/password through Sanctum.
2. The web app fetches session context from a `/me` style endpoint.
3. If the user has one tenant, that tenant becomes active automatically.
4. If the user has multiple tenants, the client must choose and send active tenant context.
5. Protected API requests resolve the active tenant and expose tenant-scoped role and permissions.
6. The user can update basic profile/preferences.
7. Later procurement workflows consume this contract instead of re-solving identity logic.

## Backend Architecture

Backend ownership stays in `apps/api/app/*` because this is cross-cutting platform infrastructure, not a procurement domain.

Primary areas:

- `app/Auth`: role enum, permission map, session/auth controllers or actions, and current-user response shaping.
- `app/Tenancy`: tenant model, current tenant resolver, membership helpers, tenant-switching/current context behavior.
- `app/Models/User`: profile/preference fields and tenant memberships.
- `routes/api.php`: public auth/session endpoints, protected identity endpoints, and existing protected workflow routes.
- `storage/openapi/openapi.json`: source of truth for the frontend/backend contract.

Authorization remains policy-driven. The first pass exposes coarse permissions such as requisition create/view/update/submit and admin access flags, but does not add a permission table. Permissions are computed from the active tenant role and can later be replaced or expanded when enterprise administration needs configurable authorization.

Tenant isolation remains enforced server-side by middleware and policies. The frontend may send tenant context, but the API always validates that the authenticated user is a member of that tenant.

## API Contract

The lean contract includes these endpoint groups:

| Endpoint | Purpose |
| --- | --- |
| `POST /auth/login` | Start Sanctum session auth for email/password. |
| `POST /auth/logout` | End the current session. |
| `POST /auth/forgot-password` | Accept a password reset request. |
| `GET /me` | Return authenticated user, available tenants, active tenant, active role, and computed permissions. |
| `PATCH /me/profile` | Update `name`, `avatar`, `timezone`, `locale`, and `theme`. |
| `POST /tenants/current` | Validate a selected tenant context for multi-tenant users. |

Expected `/me` response shape:

```json
{
  "data": {
    "user": {
      "id": "1",
      "name": "Test User",
      "email": "test@example.com",
      "avatarUrl": null,
      "timezone": "Asia/Kuala_Lumpur",
      "locale": "en",
      "theme": "system"
    },
    "tenants": [
      {
        "id": "1",
        "name": "Acme Procurement",
        "role": "requester"
      }
    ],
    "activeTenant": {
      "id": "1",
      "name": "Acme Procurement"
    },
    "activeRole": "requester",
    "permissions": {
      "canCreateRequisition": true,
      "canViewSubmittedRequisitions": false,
      "canAccessAdmin": false
    }
  }
}
```

OpenAPI must define validation errors, unauthenticated errors, unauthorized tenant membership errors, and ambiguous tenant context errors. After contract changes, regenerate the generated client and update web hooks to consume generated types or thin typed helpers.

`POST /tenants/current` validates a chosen tenant, but request-time tenant isolation still uses explicit API context such as `X-Tenant-Id`. The design avoids hidden tenant state causing unsafe API calls.

## Frontend Architecture

Frontend identity work lives under a new `apps/web/features/identity` group. App-shell integration belongs in existing `apps/web/app/*` and `apps/web/components/shell/*`.

Planned structure:

```txt
apps/web/features/identity/
  api/
  components/
  forms/
  hooks/
  mocks/
  schemas/
  tests/
  types/
  workflows/
```

Main behavior:

- Replace the current login stub with a real email/password form.
- Add a session bootstrap hook that fetches `/me` and provides `user`, `tenants`, `activeTenant`, `role`, and `permissions`.
- Include active tenant context in protected API helpers.
- Send single-tenant users directly to the workspace.
- Show a minimal tenant selection state for multi-tenant users before entering the workspace.
- Add a small account settings workflow for profile/preferences.
- Prepare existing requisition hooks to use shared auth/tenant context instead of missing or hard-coded tenant state.

MSW must mirror OpenAPI-shaped identity responses for frontend tests. Production UI components use hooks/API helpers and do not import fixtures directly.

## Data Model

Data additions stay small:

- `users.avatar_url`
- `users.timezone`
- `users.locale`
- `users.theme`

Existing `tenants` and `tenant_user.role` remain the membership foundation.

The current role enum should formalize only:

- `requester`
- `buyer`
- `approver`
- `admin`

## Security Rules

- Login uses Sanctum/session protections appropriate for the first-party Next.js app.
- Passwords remain hashed by Laravel.
- `/me` and profile updates require authentication.
- Tenant context requires authenticated membership.
- Tenant IDs from the client are treated as requested context, never trusted authorization.
- Role and permission decisions are computed server-side.
- Profile updates validate avatar URL/path, locale, timezone, and theme values.

## Error Handling

- `401`: unauthenticated session.
- `403`: tenant membership failure or denied permission.
- `400`: ambiguous tenant context when a multi-tenant user has not selected a tenant.
- `422`: validation failure.

Existing API error contracts should be reused where available and extended only as needed.

## Implementation Slices

1. **Identity Contract And Profile Schema**
   Add OpenAPI schemas/endpoints for `/me`, profile preferences, tenant summaries, role, and permissions. Add user fields and seed data.

2. **Sanctum Auth Flow**
   Implement login/logout/password reset endpoints and tests. Wire the login page and auth hooks to the generated client/MSW.

3. **Tenant Context And Permissions**
   Harden current tenant resolution, expose tenant memberships in `/me`, compute permissions by current role, and make protected API helpers consistently include tenant context.

4. **Profile Preferences Workflow**
   Add account settings UI and backend update behavior for `name`, `avatar`, `timezone`, `locale`, and `theme`.

## Verification

Each implementation slice should include narrow checks first:

- Backend feature tests for auth, `/me`, tenancy, profile updates, and role permissions.
- Frontend Vitest tests for login, session bootstrap, tenant context, and profile update behavior.
- Contract regeneration with `pnpm generate:api` after OpenAPI changes.
- API contract verification with `pnpm check:api-contract` when OpenAPI changes.

Before claiming the epic complete, run the relevant root checks for touched packages and confirm the generated client matches the Laravel contract.

## Implementation Planning Decisions

The implementation plan should use these decisions unless new code constraints make a change necessary:

- Auth endpoints use dedicated HTTP controllers with small private helpers or action classes only where logic would otherwise grow past controller orchestration.
- `avatar` is stored as nullable URL/path text in P0. File upload for avatars belongs to a future file attachment or profile hardening slice.
- First-pass `/me` permissions are `canCreateRequisition`, `canViewSubmittedRequisitions`, `canUpdateOwnDraftRequisition`, `canSubmitOwnDraftRequisition`, and `canAccessAdmin`.
- The Next.js app stores the selected active tenant ID in identity provider state and browser local storage, then sends it through `X-Tenant-Id` on protected API calls. The value is not trusted by the API without membership validation.

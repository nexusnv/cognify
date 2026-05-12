# Tenant, Auth, Access Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's lean P0 tenant, Sanctum auth, role/permission, and profile foundation so later procurement workflows can rely on one stable identity contract.

**Architecture:** Use a contract-first vertical slice. Identity and tenancy stay in `apps/api/app/*`; Cognify-specific web workflows live in `apps/web/features/identity`; API shape is defined in `apps/api/storage/openapi/openapi.json` and consumed through generated clients or thin typed helpers. Tenant context is always validated server-side and sent explicitly from the web app through `X-Tenant-Id`.

**Tech Stack:** Laravel 12, Sanctum, PHPUnit, Next.js App Router, React 19, TanStack Query, React Hook Form, Zod, MSW, Vitest, Orval-generated API client.

---

## Runbook Alignment

Follow `docs/05-runbooks/feature-development.md`:

1. Map the identity workflow before coding.
2. Build the workspace/auth UX against contract-shaped MSW responses.
3. Update OpenAPI before hardening backend behavior.
4. Implement Laravel auth, `/me`, tenancy, permissions, and profile updates.
5. Regenerate API client and swap web hooks to generated/typed helpers.
6. Harden tenant isolation, permission output, validation, and verification.

## File Structure

Create:

- `apps/api/app/Auth/Http/Controllers/AuthenticatedSessionController.php`: login/logout/password reset request endpoints.
- `apps/api/app/Auth/Http/Controllers/CurrentUserController.php`: `/me` response.
- `apps/api/app/Auth/Http/Controllers/UserProfileController.php`: profile update endpoint.
- `apps/api/app/Auth/Http/Controllers/CurrentTenantController.php`: active tenant validation endpoint.
- `apps/api/app/Auth/Http/Resources/CurrentUserResource.php`: stable `/me` response shape.
- `apps/api/app/Auth/Permissions/TenantPermissionResolver.php`: role-to-permission map.
- `apps/api/app/Http/Requests/Auth/LoginRequest.php`
- `apps/api/app/Http/Requests/Auth/UpdateProfileRequest.php`
- `apps/api/database/migrations/2026_05_12_000000_add_profile_preferences_to_users_table.php`
- `apps/api/tests/Feature/IdentityApiTest.php`
- `apps/web/features/identity/api/identity-api.ts`
- `apps/web/features/identity/components/tenant-selection.tsx`
- `apps/web/features/identity/forms/login-form.tsx`
- `apps/web/features/identity/forms/profile-form.tsx`
- `apps/web/features/identity/hooks/use-current-user.ts`
- `apps/web/features/identity/hooks/use-login.ts`
- `apps/web/features/identity/hooks/use-logout.ts`
- `apps/web/features/identity/hooks/use-profile-update.ts`
- `apps/web/features/identity/mocks/identity-fixtures.ts`
- `apps/web/features/identity/mocks/identity-handlers.ts`
- `apps/web/features/identity/schemas/login-schema.ts`
- `apps/web/features/identity/schemas/profile-schema.ts`
- `apps/web/features/identity/types/identity-view-model.ts`
- `apps/web/features/identity/workflows/account-settings-page.tsx`
- `apps/web/features/identity/workflows/login-page.tsx`
- `apps/web/features/identity/workflows/session-gate.tsx`
- `apps/web/features/identity/tests/identity-workflow.test.tsx`

Modify:

- `apps/api/app/Auth/TenantRole.php`: keep only `requester`, `buyer`, `approver`, `admin`.
- `apps/api/app/Models/User.php`: add fillable/casts for profile preferences.
- `apps/api/app/Http/Middleware/ResolveCurrentTenant.php`: preserve existing behavior and ensure ambiguous tenant errors remain deterministic.
- `apps/api/database/seeders/DatabaseSeeder.php`: seed tenant memberships and profile defaults.
- `apps/api/routes/api.php`: add identity routes and keep existing requisition routes protected.
- `apps/api/storage/openapi/openapi.json`: add identity schemas/endpoints.
- `packages/api-client/src/generated/*`: regenerate.
- `packages/api-client/src/client.ts`: support credentials and tenant header.
- `apps/web/components/providers/app-providers.tsx`: add identity provider/session gate if implemented as provider composition.
- `apps/web/tests/setup.ts`: reset identity mock state.
- `apps/web/tests/msw/handlers.ts`: register identity handlers.
- `apps/web/app/(auth)/login/page.tsx`: render identity login workflow.
- `apps/web/app/(workspace)/layout.tsx`: protect workspace with session gate.
- `apps/web/components/shell/workspace-shell.tsx`: surface minimal current-user/tenant context in the header.
- `apps/web/features/requisitions/api/requisitions-api.ts`: include tenant-aware fetch helper.

Do not modify `packages/ui` for Cognify-specific identity behavior.

## Task 1: Baseline And Workflow Map

**Files:**
- Read: `docs/superpowers/specs/2026-05-12-tenant-auth-access-foundation-design.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `apps/api/routes/api.php`
- Read: `apps/api/app/Http/Middleware/ResolveCurrentTenant.php`
- Read: `apps/web/features/requisitions/api/requisitions-api.ts`

- [ ] **Step 1: Confirm clean baseline**

Run:

```bash
git status --short --branch
```

Expected: no unrelated modified files. If unrelated files exist, do not edit or revert them.

- [ ] **Step 2: Record the workflow in the implementation PR notes**

Use this workflow map in the PR description and test naming:

```txt
Actor: authenticated Cognify user
Tenant context: one tenant resolves automatically; multiple tenants require X-Tenant-Id
Roles: requester, buyer, approver, admin
Transitions:
  unauthenticated -> login -> authenticated session
  authenticated session -> GET /me -> identity context
  multi-tenant session -> POST /tenants/current or X-Tenant-Id -> tenant context validated
  authenticated session -> PATCH /me/profile -> profile preferences updated
Failure paths:
  bad credentials -> 422
  unauthenticated -> 401
  invalid tenant membership -> 403
  missing tenant for multi-tenant user -> 400
  invalid profile fields -> 422
```

- [ ] **Step 3: Commit baseline note only if a tracking file is created**

No commit is required for read-only baseline work.

## Task 2: API Contract For Identity

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Generated after this task: `packages/api-client/src/generated/*`

- [ ] **Step 1: Add identity paths to OpenAPI**

In `apps/api/storage/openapi/openapi.json`, add these paths under `paths`:

```json
"/api/auth/login": {
  "post": {
    "operationId": "login",
    "summary": "Start an authenticated Sanctum session",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/LoginRequest" }
        }
      }
    },
    "responses": {
      "204": { "description": "Session started" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
},
"/api/auth/logout": {
  "post": {
    "operationId": "logout",
    "summary": "End the current authenticated session",
    "responses": {
      "204": { "description": "Session ended" },
      "401": { "$ref": "#/components/responses/Unauthenticated" }
    }
  }
},
"/api/auth/forgot-password": {
  "post": {
    "operationId": "requestPasswordReset",
    "summary": "Request a password reset email",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/ForgotPasswordRequest" }
        }
      }
    },
    "responses": {
      "204": { "description": "Password reset request accepted" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
},
"/api/me": {
  "get": {
    "operationId": "getCurrentUser",
    "summary": "Read current user and tenant context",
    "responses": {
      "200": {
        "description": "Current identity context",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CurrentUserResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Unauthorized" }
    }
  }
},
"/api/me/profile": {
  "patch": {
    "operationId": "updateCurrentUserProfile",
    "summary": "Update current user profile preferences",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/UpdateCurrentUserProfileRequest" }
        }
      }
    },
    "responses": {
      "200": {
        "description": "Profile updated",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CurrentUserResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
},
"/api/tenants/current": {
  "post": {
    "operationId": "setCurrentTenant",
    "summary": "Validate selected tenant context",
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/SetCurrentTenantRequest" }
        }
      }
    },
    "responses": {
      "200": {
        "description": "Tenant context validated",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CurrentUserResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Unauthorized" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
}
```

- [ ] **Step 2: Add identity schemas**

Add these component schemas:

```json
"LoginRequest": {
  "type": "object",
  "required": ["email", "password"],
  "properties": {
    "email": { "type": "string", "format": "email" },
    "password": { "type": "string", "minLength": 8 },
    "remember": { "type": "boolean", "default": false }
  }
},
"ForgotPasswordRequest": {
  "type": "object",
  "required": ["email"],
  "properties": {
    "email": { "type": "string", "format": "email" }
  }
},
"TenantRole": {
  "type": "string",
  "enum": ["requester", "buyer", "approver", "admin"]
},
"CurrentUserProfile": {
  "type": "object",
  "required": ["id", "name", "email", "timezone", "locale", "theme"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" },
    "email": { "type": "string", "format": "email" },
    "avatarUrl": { "type": ["string", "null"] },
    "timezone": { "type": "string" },
    "locale": { "type": "string" },
    "theme": { "type": "string", "enum": ["light", "dark", "system"] }
  }
},
"TenantMembershipSummary": {
  "type": "object",
  "required": ["id", "name", "role"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" },
    "role": { "$ref": "#/components/schemas/TenantRole" }
  }
},
"ActiveTenantSummary": {
  "type": "object",
  "required": ["id", "name"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" }
  }
},
"IdentityPermissions": {
  "type": "object",
  "required": [
    "canCreateRequisition",
    "canViewSubmittedRequisitions",
    "canUpdateOwnDraftRequisition",
    "canSubmitOwnDraftRequisition",
    "canAccessAdmin"
  ],
  "properties": {
    "canCreateRequisition": { "type": "boolean" },
    "canViewSubmittedRequisitions": { "type": "boolean" },
    "canUpdateOwnDraftRequisition": { "type": "boolean" },
    "canSubmitOwnDraftRequisition": { "type": "boolean" },
    "canAccessAdmin": { "type": "boolean" }
  }
},
"CurrentUser": {
  "type": "object",
  "required": ["user", "tenants", "activeTenant", "activeRole", "permissions"],
  "properties": {
    "user": { "$ref": "#/components/schemas/CurrentUserProfile" },
    "tenants": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/TenantMembershipSummary" }
    },
    "activeTenant": {
      "oneOf": [
        { "$ref": "#/components/schemas/ActiveTenantSummary" },
        { "type": "null" }
      ]
    },
    "activeRole": {
      "oneOf": [
        { "$ref": "#/components/schemas/TenantRole" },
        { "type": "null" }
      ]
    },
    "permissions": { "$ref": "#/components/schemas/IdentityPermissions" }
  }
},
"CurrentUserResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/CurrentUser" }
  }
},
"UpdateCurrentUserProfileRequest": {
  "type": "object",
  "required": ["name", "timezone", "locale", "theme"],
  "properties": {
    "name": { "type": "string", "minLength": 1, "maxLength": 255 },
    "avatarUrl": { "type": ["string", "null"], "maxLength": 2048 },
    "timezone": { "type": "string", "maxLength": 64 },
    "locale": { "type": "string", "minLength": 2, "maxLength": 12 },
    "theme": { "type": "string", "enum": ["light", "dark", "system"] }
  }
},
"SetCurrentTenantRequest": {
  "type": "object",
  "required": ["tenantId"],
  "properties": {
    "tenantId": { "type": "string" }
  }
}
```

- [ ] **Step 3: Add ambiguous tenant response**

Add to `components.responses`:

```json
"AmbiguousTenant": {
  "description": "Tenant context is required",
  "content": {
    "application/json": {
      "schema": { "$ref": "#/components/schemas/ApiError" }
    }
  }
}
```

- [ ] **Step 4: Validate and regenerate**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated identity schemas and endpoints are created under `packages/api-client/src/generated`, and contract check exits 0.

- [ ] **Step 5: Commit**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated packages/api-client/src/index.ts
git commit -m "feat: add identity API contract"
```

## Task 3: Mocked Identity Frontend Workflow

**Files:**
- Create: `apps/web/features/identity/types/identity-view-model.ts`
- Create: `apps/web/features/identity/mocks/identity-fixtures.ts`
- Create: `apps/web/features/identity/mocks/identity-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/tests/setup.ts`
- Create: `apps/web/features/identity/tests/identity-workflow.test.tsx`

- [ ] **Step 1: Add identity view model**

Create `apps/web/features/identity/types/identity-view-model.ts`:

```ts
export type TenantRole = "requester" | "buyer" | "approver" | "admin";
export type ThemePreference = "light" | "dark" | "system";

export type IdentityPermissions = {
  canCreateRequisition: boolean;
  canViewSubmittedRequisitions: boolean;
  canUpdateOwnDraftRequisition: boolean;
  canSubmitOwnDraftRequisition: boolean;
  canAccessAdmin: boolean;
};

export type CurrentUserProfile = {
  id: string;
  name: string;
  email: string;
  avatarUrl: string | null;
  timezone: string;
  locale: string;
  theme: ThemePreference;
};

export type TenantMembershipSummary = {
  id: string;
  name: string;
  role: TenantRole;
};

export type ActiveTenantSummary = {
  id: string;
  name: string;
};

export type CurrentUserContext = {
  user: CurrentUserProfile;
  tenants: TenantMembershipSummary[];
  activeTenant: ActiveTenantSummary | null;
  activeRole: TenantRole | null;
  permissions: IdentityPermissions;
};

export type CurrentUserResponse = {
  data: CurrentUserContext;
};
```

- [ ] **Step 2: Add fixtures**

Create `apps/web/features/identity/mocks/identity-fixtures.ts`:

```ts
import type { CurrentUserContext } from "../types/identity-view-model";

export const requesterIdentity: CurrentUserContext = {
  user: {
    id: "1",
    name: "Test User",
    email: "test@example.com",
    avatarUrl: null,
    timezone: "Asia/Kuala_Lumpur",
    locale: "en",
    theme: "system",
  },
  tenants: [{ id: "1", name: "Acme Procurement", role: "requester" }],
  activeTenant: { id: "1", name: "Acme Procurement" },
  activeRole: "requester",
  permissions: {
    canCreateRequisition: true,
    canViewSubmittedRequisitions: false,
    canUpdateOwnDraftRequisition: true,
    canSubmitOwnDraftRequisition: true,
    canAccessAdmin: false,
  },
};

export const multiTenantIdentity: CurrentUserContext = {
  ...requesterIdentity,
  tenants: [
    { id: "1", name: "Acme Procurement", role: "requester" },
    { id: "2", name: "Northwind Sourcing", role: "buyer" },
  ],
  activeTenant: null,
  activeRole: null,
  permissions: {
    canCreateRequisition: false,
    canViewSubmittedRequisitions: false,
    canUpdateOwnDraftRequisition: false,
    canSubmitOwnDraftRequisition: false,
    canAccessAdmin: false,
  },
};
```

- [ ] **Step 3: Add MSW handlers**

Create `apps/web/features/identity/mocks/identity-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import type { CurrentUserContext } from "../types/identity-view-model";
import { requesterIdentity } from "./identity-fixtures";

let currentIdentity: CurrentUserContext = requesterIdentity;
let authenticated = true;

export function resetIdentityMockState() {
  currentIdentity = requesterIdentity;
  authenticated = true;
}

export const identityHandlers = [
  http.post("/api/auth/login", async () => {
    authenticated = true;
    return new HttpResponse(null, { status: 204 });
  }),
  http.post("/api/auth/logout", () => {
    authenticated = false;
    return new HttpResponse(null, { status: 204 });
  }),
  http.post("/api/auth/forgot-password", () => new HttpResponse(null, { status: 204 })),
  http.get("/api/me", () => {
    if (!authenticated) {
      return HttpResponse.json({ message: "Unauthenticated." }, { status: 401 });
    }
    return HttpResponse.json({ data: currentIdentity });
  }),
  http.patch("/api/me/profile", async ({ request }) => {
    const body = (await request.json()) as Partial<CurrentUserContext["user"]>;
    currentIdentity = {
      ...currentIdentity,
      user: { ...currentIdentity.user, ...body },
    };
    return HttpResponse.json({ data: currentIdentity });
  }),
  http.post("/api/tenants/current", async ({ request }) => {
    const body = (await request.json()) as { tenantId?: string };
    const membership = currentIdentity.tenants.find((tenant) => tenant.id === body.tenantId);
    if (!membership) {
      return HttpResponse.json({ message: "Tenant membership is required." }, { status: 403 });
    }
    currentIdentity = {
      ...currentIdentity,
      activeTenant: { id: membership.id, name: membership.name },
      activeRole: membership.role,
    };
    return HttpResponse.json({ data: currentIdentity });
  }),
];
```

- [ ] **Step 4: Register handlers**

Modify `apps/web/tests/msw/handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...identityHandlers,
  ...requisitionsHandlers,
];
```

Modify `apps/web/tests/setup.ts`:

```ts
import "@testing-library/jest-dom/vitest";
import { afterAll, afterEach, beforeAll } from "vitest";
import { resetIdentityMockState } from "../features/identity/mocks/identity-handlers";
import { resetRequisitionMockState } from "../features/requisitions/mocks/requisitions-handlers";
import { server } from "./msw/server";

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => {
  server.resetHandlers();
  resetIdentityMockState();
  resetRequisitionMockState();
});
afterAll(() => server.close());
```

- [ ] **Step 5: Add failing workflow tests**

Create `apps/web/features/identity/tests/identity-workflow.test.tsx`:

```ts
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { describe, expect, it } from "vitest";
import { server } from "../../../tests/msw/server";
import { multiTenantIdentity } from "../mocks/identity-fixtures";
import { AccountSettingsPage } from "../workflows/account-settings-page";
import { LoginPage } from "../workflows/login-page";
import { SessionGate } from "../workflows/session-gate";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("identity workflow", () => {
  it("signs in and loads current identity context", async () => {
    const user = userEvent.setup();

    renderWithQuery(<LoginPage />);

    await user.type(screen.getByLabelText("Email"), "test@example.com");
    await user.type(screen.getByLabelText("Password"), "password");
    await user.click(screen.getByRole("button", { name: "Sign in" }));

    expect(await screen.findByText("Signed in")).toBeInTheDocument();
  });

  it("requires tenant selection for a multi-tenant identity", async () => {
    const user = userEvent.setup();
    server.use(
      http.get("/api/me", () => HttpResponse.json({ data: multiTenantIdentity })),
    );

    renderWithQuery(
      <SessionGate>
        <div>Workspace ready</div>
      </SessionGate>,
    );

    expect(await screen.findByRole("heading", { name: "Choose workspace" })).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Northwind Sourcing" }));

    await waitFor(() => {
      expect(screen.getByText("Workspace ready")).toBeInTheDocument();
    });
  });

  it("updates profile preferences through the account settings workflow", async () => {
    const user = userEvent.setup();

    renderWithQuery(<AccountSettingsPage />);

    expect(await screen.findByRole("heading", { name: "Account settings" })).toBeInTheDocument();
    await user.clear(screen.getByLabelText("Name"));
    await user.type(screen.getByLabelText("Name"), "Taylor Buyer");
    await user.selectOptions(screen.getByLabelText("Theme"), "dark");
    await user.click(screen.getByRole("button", { name: "Save profile" }));

    expect(await screen.findByText("Profile saved")).toBeInTheDocument();
    expect(screen.getByDisplayValue("Taylor Buyer")).toBeInTheDocument();
  });
});
```

Fill these tests against components introduced in later tasks: `LoginPage`, `SessionGate`, and `AccountSettingsPage`.

- [ ] **Step 6: Run tests and confirm failure**

Run:

```bash
pnpm --filter @cognify/web test -- identity-workflow
```

Expected: FAIL because the workflow components/hooks do not exist yet.

- [ ] **Step 7: Commit**

Do not commit the intentionally failing tests alone unless the execution style permits red commits. Otherwise continue through Task 4 and commit once tests pass.

## Task 4: Frontend Identity API, Hooks, And UX

**Files:**
- Create: `apps/web/features/identity/api/identity-api.ts`
- Create: `apps/web/features/identity/schemas/login-schema.ts`
- Create: `apps/web/features/identity/schemas/profile-schema.ts`
- Create: `apps/web/features/identity/hooks/use-current-user.ts`
- Create: `apps/web/features/identity/hooks/use-login.ts`
- Create: `apps/web/features/identity/hooks/use-logout.ts`
- Create: `apps/web/features/identity/hooks/use-profile-update.ts`
- Create: `apps/web/features/identity/forms/login-form.tsx`
- Create: `apps/web/features/identity/forms/profile-form.tsx`
- Create: `apps/web/features/identity/components/tenant-selection.tsx`
- Create: `apps/web/features/identity/workflows/login-page.tsx`
- Create: `apps/web/features/identity/workflows/session-gate.tsx`
- Create: `apps/web/features/identity/workflows/account-settings-page.tsx`
- Modify: `apps/web/app/(auth)/login/page.tsx`
- Modify: `apps/web/app/(workspace)/layout.tsx`

- [ ] **Step 1: Add schemas**

`apps/web/features/identity/schemas/login-schema.ts`:

```ts
import { z } from "zod";

export const loginSchema = z.object({
  email: z.string().email("Enter a valid email address."),
  password: z.string().min(8, "Password must be at least 8 characters."),
  remember: z.boolean().default(false),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
```

`apps/web/features/identity/schemas/profile-schema.ts`:

```ts
import { z } from "zod";

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required.").max(255),
  avatarUrl: z.string().url("Enter a valid URL.").max(2048).nullable().or(z.literal("")),
  timezone: z.string().min(1, "Timezone is required.").max(64),
  locale: z.string().min(2).max(12),
  theme: z.enum(["light", "dark", "system"]),
});

export type ProfileFormValues = z.infer<typeof profileSchema>;
```

- [ ] **Step 2: Add API helper**

`apps/web/features/identity/api/identity-api.ts`:

```ts
import type { CurrentUserResponse } from "../types/identity-view-model";
import type { LoginFormValues } from "../schemas/login-schema";
import type { ProfileFormValues } from "../schemas/profile-schema";

const ACTIVE_TENANT_KEY = "cognify.activeTenantId";

export function getStoredActiveTenantId() {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(ACTIVE_TENANT_KEY);
}

export function storeActiveTenantId(tenantId: string) {
  if (typeof window !== "undefined") {
    window.localStorage.setItem(ACTIVE_TENANT_KEY, tenantId);
  }
}

export async function login(values: LoginFormValues) {
  await fetchJson<void>("/api/auth/login", {
    method: "POST",
    body: JSON.stringify(values),
  });
}

export async function logout() {
  await fetchJson<void>("/api/auth/logout", { method: "POST" });
}

export async function getCurrentUser() {
  return fetchJson<CurrentUserResponse>("/api/me");
}

export async function updateCurrentUserProfile(values: ProfileFormValues) {
  return fetchJson<CurrentUserResponse>("/api/me/profile", {
    method: "PATCH",
    body: JSON.stringify({ ...values, avatarUrl: values.avatarUrl || null }),
  });
}

export async function setCurrentTenant(tenantId: string) {
  storeActiveTenantId(tenantId);
  return fetchJson<CurrentUserResponse>("/api/tenants/current", {
    method: "POST",
    body: JSON.stringify({ tenantId }),
  });
}

async function fetchJson<T>(url: string, init?: RequestInit): Promise<T> {
  const headers = new Headers(init?.headers);
  headers.set("Content-Type", "application/json");
  const tenantId = getStoredActiveTenantId();
  if (tenantId) headers.set("X-Tenant-Id", tenantId);

  const response = await fetch(url, {
    ...init,
    credentials: "include",
    headers,
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = (await response.json()) as T;
  if (!response.ok) throw payload;
  return payload;
}
```

- [ ] **Step 3: Add hooks**

Implement TanStack Query hooks with keys `["identity", "current-user"]`, `["identity", "login"]`, `["identity", "logout"]`, and `["identity", "profile"]`. On login/profile/tenant selection, invalidate `["identity", "current-user"]`.

- [ ] **Step 4: Add forms and workflows**

Build:

- `LoginForm`: email, password, remember checkbox, submit button, inline alert for errors.
- `LoginPage`: renders the form and calls `useLogin`.
- `TenantSelection`: renders tenant buttons when `activeTenant` is null and there is more than one tenant.
- `SessionGate`: shows loading, unauthenticated sign-in link, tenant selection, or children.
- `ProfileForm`: name, avatar URL, timezone, locale, theme select, save button.
- `AccountSettingsPage`: loads current user and renders profile form.

Use existing visual conventions: compact operational UI, no marketing page, no cards nested inside cards.

- [ ] **Step 5: Wire routes**

`apps/web/app/(auth)/login/page.tsx`:

```ts
import { LoginPage } from "@/features/identity/workflows/login-page";

export default LoginPage;
```

`apps/web/app/(workspace)/layout.tsx` should wrap the existing workspace layout in `SessionGate`.

- [ ] **Step 6: Run frontend tests**

```bash
pnpm --filter @cognify/web test -- identity-workflow
pnpm --filter @cognify/web typecheck
```

Expected: identity workflow tests pass and TypeScript exits 0.

- [ ] **Step 7: Commit**

```bash
git add apps/web/app apps/web/features/identity apps/web/tests
git commit -m "feat: add mocked identity frontend workflow"
```

## Task 5: Backend Profile Schema And Seed Data

**Files:**
- Create: `apps/api/database/migrations/2026_05_12_000000_add_profile_preferences_to_users_table.php`
- Modify: `apps/api/app/Models/User.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
- Test: `apps/api/tests/Feature/IdentityApiTest.php`

- [ ] **Step 1: Write failing profile schema test**

Create `apps/api/tests/Feature/IdentityApiTest.php` with a test asserting `/api/me` includes `avatarUrl`, `timezone`, `locale`, and `theme` after seeding a tenant user.

- [ ] **Step 2: Run test and verify failure**

```bash
cd apps/api
php artisan test --filter=IdentityApiTest
```

Expected: FAIL because identity routes and profile columns do not exist.

- [ ] **Step 3: Add migration**

Migration body:

```php
Schema::table('users', function (Blueprint $table): void {
    $table->string('avatar_url', 2048)->nullable()->after('email');
    $table->string('timezone', 64)->default('UTC')->after('avatar_url');
    $table->string('locale', 12)->default('en')->after('timezone');
    $table->string('theme', 16)->default('system')->after('locale');
});
```

Down method drops those four columns.

- [ ] **Step 4: Update User model**

Add `avatar_url`, `timezone`, `locale`, and `theme` to `$fillable`. Keep `password` hashed in casts.

- [ ] **Step 5: Update seeder**

Seed:

- Tenant `Acme Procurement`
- `test@example.com` requester with password `password`
- Optional buyer/approver/admin users for local role checks
- Attach users through `tenant_user.role`

- [ ] **Step 6: Run migration/test**

```bash
cd apps/api
php artisan migrate:fresh --seed
php artisan test --filter=IdentityApiTest
```

Expected: route tests may still fail until Task 6, but migration/seeding should complete.

- [ ] **Step 7: Commit after Task 6 passes**

Hold commit until backend identity routes exist and `IdentityApiTest` passes.

## Task 6: Backend Auth, Current User, Tenant, Permissions, Profile

**Files:**
- Create controllers, requests, resource, resolver listed in File Structure.
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/app/Auth/TenantRole.php`
- Test: `apps/api/tests/Feature/IdentityApiTest.php`

- [ ] **Step 1: Complete backend tests**

`IdentityApiTest` must cover:

```php
public function test_user_can_login_and_read_current_identity_context(): void {}
public function test_login_rejects_invalid_credentials(): void {}
public function test_logout_ends_session(): void {}
public function test_multi_tenant_user_without_tenant_header_receives_ambiguous_tenant_error(): void {}
public function test_current_tenant_rejects_non_member_tenant(): void {}
public function test_profile_update_validates_and_persists_preferences(): void {}
public function test_permissions_are_computed_by_role(): void {}
```

- [ ] **Step 2: Add permission resolver**

`TenantPermissionResolver` should return:

```php
return match ($role) {
    TenantRole::Requester->value => [
        'canCreateRequisition' => true,
        'canViewSubmittedRequisitions' => false,
        'canUpdateOwnDraftRequisition' => true,
        'canSubmitOwnDraftRequisition' => true,
        'canAccessAdmin' => false,
    ],
    TenantRole::Buyer->value, TenantRole::Approver->value => [
        'canCreateRequisition' => false,
        'canViewSubmittedRequisitions' => true,
        'canUpdateOwnDraftRequisition' => false,
        'canSubmitOwnDraftRequisition' => false,
        'canAccessAdmin' => false,
    ],
    TenantRole::Admin->value => [
        'canCreateRequisition' => true,
        'canViewSubmittedRequisitions' => true,
        'canUpdateOwnDraftRequisition' => true,
        'canSubmitOwnDraftRequisition' => true,
        'canAccessAdmin' => true,
    ],
    default => [
        'canCreateRequisition' => false,
        'canViewSubmittedRequisitions' => false,
        'canUpdateOwnDraftRequisition' => false,
        'canSubmitOwnDraftRequisition' => false,
        'canAccessAdmin' => false,
    ],
};
```

- [ ] **Step 3: Add requests**

`LoginRequest` validates:

```php
'email' => ['required', 'email'],
'password' => ['required', 'string'],
'remember' => ['sometimes', 'boolean'],
```

`UpdateProfileRequest` validates:

```php
'name' => ['required', 'string', 'max:255'],
'avatarUrl' => ['nullable', 'url', 'max:2048'],
'timezone' => ['required', 'timezone', 'max:64'],
'locale' => ['required', 'string', 'min:2', 'max:12'],
'theme' => ['required', 'in:light,dark,system'],
```

- [ ] **Step 4: Add resource**

`CurrentUserResource` must emit exactly:

```php
[
    'user' => [
        'id' => (string) $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatarUrl' => $user->avatar_url,
        'timezone' => $user->timezone,
        'locale' => $user->locale,
        'theme' => $user->theme,
    ],
    'tenants' => $memberships,
    'activeTenant' => $tenant ? ['id' => (string) $tenant->id, 'name' => $tenant->name] : null,
    'activeRole' => $role,
    'permissions' => $permissionResolver->forRole($role),
]
```

- [ ] **Step 5: Add controllers**

Implementation rules:

- Login uses `Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))`.
- Failed login returns validation error on `email`.
- Successful login regenerates the session and returns `204`.
- Logout invalidates the current session and returns `204`.
- Forgot password accepts the email and returns `204`; use Laravel password broker if configured.
- `/me` uses `ResolveCurrentTenant` so multi-tenant ambiguity remains centralized.
- `POST /tenants/current` validates membership and returns `CurrentUserResource`.
- Profile update maps `avatarUrl` to `avatar_url`.

- [ ] **Step 6: Wire routes**

In `apps/api/routes/api.php`, add public auth routes and protected identity routes:

```php
Route::post('/auth/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/auth/forgot-password', [AuthenticatedSessionController::class, 'forgotPassword']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::middleware(ResolveCurrentTenant::class)->group(function (): void {
        Route::get('/me', [CurrentUserController::class, 'show']);
        Route::patch('/me/profile', [UserProfileController::class, 'update']);
        Route::post('/tenants/current', [CurrentTenantController::class, 'store']);
    });
});
```

Keep existing requisition routes in the same `auth:sanctum` plus `ResolveCurrentTenant` protection.

- [ ] **Step 7: Run backend checks**

```bash
cd apps/api
php artisan test --filter=IdentityApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api
```

Expected: identity and requisition feature tests pass; route list includes all identity endpoints.

- [ ] **Step 8: Commit**

```bash
git add apps/api
git commit -m "feat: implement tenant identity backend"
```

## Task 7: Generated Client And Tenant-Aware Fetch

**Files:**
- Modify: `packages/api-client/src/client.ts`
- Generated: `packages/api-client/src/generated/*`
- Modify: `apps/web/features/identity/api/identity-api.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`

- [ ] **Step 1: Update shared client config**

Extend `ApiClientConfig`:

```ts
export type ApiClientConfig = {
  baseUrl: string;
  getAccessToken?: () => string | null | Promise<string | null>;
  getTenantId?: () => string | null | Promise<string | null>;
};
```

In `cognifyFetch`, always set:

```ts
credentials: "include",
```

and set `X-Tenant-Id` when `getTenantId` returns a value.

- [ ] **Step 2: Regenerate client**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoints include login, logout, current user, profile update, and tenant current operations.

- [ ] **Step 3: Swap identity API to generated response/request types**

Keep the thin `identity-api.ts` wrapper for tenant-header and credential handling. Import generated request/response types from `@cognify/api-client` so the wrapper cannot drift from OpenAPI.

- [ ] **Step 4: Add tenant header to requisition fetches**

Update `apps/web/features/requisitions/api/requisitions-api.ts` so its private `fetchJson` mirrors the identity helper: `credentials: "include"` and `X-Tenant-Id` from `getStoredActiveTenantId()`.

- [ ] **Step 5: Run checks**

```bash
pnpm --filter @cognify/api-client typecheck
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add packages/api-client apps/web/features/identity apps/web/features/requisitions apps/api/storage/openapi/openapi.json
git commit -m "feat: wire generated identity client"
```

## Task 8: Profile Workflow And Shell Integration

**Files:**
- Create: `apps/web/app/(workspace)/account/page.tsx`
- Modify: `apps/web/components/shell/workspace-shell.tsx`
- Modify: `apps/web/features/identity/workflows/account-settings-page.tsx`
- Test: `apps/web/features/identity/tests/identity-workflow.test.tsx`

- [ ] **Step 1: Add account route**

Create `apps/web/app/(workspace)/account/page.tsx`:

```ts
import { AccountSettingsPage } from "@/features/identity/workflows/account-settings-page";

export default AccountSettingsPage;
```

- [ ] **Step 2: Add shell affordance**

In `WorkspaceShell`, show current user name and active tenant name in the header through identity context. Keep it compact:

```tsx
<div className="text-sm text-muted-foreground">
  {activeTenant ? activeTenant.name : "Operational workspace"}
</div>
```

Add an account link to `/account` without adding tenant admin UI.

- [ ] **Step 3: Verify UI tests**

```bash
pnpm --filter @cognify/web test -- identity-workflow
```

Expected: profile workflow test passes and does not import mock fixtures into production components.

- [ ] **Step 4: Commit**

```bash
git add apps/web/app apps/web/components/shell apps/web/features/identity
git commit -m "feat: add identity shell integration"
```

## Task 9: Security, Tenant Isolation, And Error Hardening

**Files:**
- Modify: `apps/api/app/Http/Middleware/ResolveCurrentTenant.php`
- Modify: `apps/api/tests/Feature/IdentityApiTest.php`
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php`
- Modify: `apps/web/features/identity/tests/identity-workflow.test.tsx`

- [ ] **Step 1: Confirm tenant ambiguity behavior**

Tests must assert:

```php
$this->getJson('/api/me')
    ->assertStatus(400)
    ->assertJsonPath('message', 'X-Tenant-Id header is required for users with multiple tenants.');
```

- [ ] **Step 2: Confirm non-member tenant rejection**

Tests must assert:

```php
$this->withHeader('X-Tenant-Id', (string) $otherTenant->id)
    ->getJson('/api/me')
    ->assertStatus(403)
    ->assertJsonPath('message', 'Tenant membership is required.');
```

- [ ] **Step 3: Confirm role permissions**

Test each role has expected first-pass booleans:

```php
'requester' => ['canCreateRequisition' => true, 'canAccessAdmin' => false],
'buyer' => ['canViewSubmittedRequisitions' => true, 'canAccessAdmin' => false],
'approver' => ['canViewSubmittedRequisitions' => true, 'canAccessAdmin' => false],
'admin' => ['canCreateRequisition' => true, 'canAccessAdmin' => true],
```

- [ ] **Step 4: Run security-focused checks**

```bash
cd apps/api
php artisan test --filter=IdentityApiTest
php artisan test --filter=RequisitionApiTest
```

Expected: all tenant and permission boundary tests pass.

- [ ] **Step 5: Commit**

```bash
git add apps/api apps/web/features/identity
git commit -m "test: harden identity tenant boundaries"
```

## Task 10: Final Verification And Documentation

**Files:**
- Review: `docs/superpowers/specs/2026-05-12-tenant-auth-access-foundation-design.md`
- Review: `docs/05-runbooks/local-development.md`
- Review: `docs/CURRENT_STATE.md`

- [ ] **Step 1: Run backend verification**

```bash
pnpm api:test
pnpm api:routes
```

Expected: Laravel tests pass and API routes list identity plus requisition endpoints.

- [ ] **Step 2: Run contract verification**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Expected: generated client is current; TypeScript exits 0.

- [ ] **Step 3: Run frontend verification**

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
```

Expected: lint, Vitest, and typecheck pass.

- [ ] **Step 4: Run root checks if shared/generated files changed**

```bash
pnpm lint
pnpm test
pnpm build
```

Expected: all pass. If build cannot run because local services or environment variables are missing, document the exact blocker and the highest-signal passing checks.

- [ ] **Step 5: Review generated and staged changes**

```bash
git status --short
git diff --stat
git diff -- apps/api/storage/openapi/openapi.json packages/api-client/src/generated
```

Expected: no unrelated files; generated client changes match the identity OpenAPI additions.

- [ ] **Step 6: Final commit**

```bash
git add docs apps/api apps/web packages/api-client
git commit -m "feat: complete tenant auth access foundation"
```

Skip this commit if all work has already been committed in previous task commits and `git status --short` is clean.

## Completion Criteria

The epic is complete when:

- A user can log in through Sanctum from the web app.
- `/api/me` returns user, tenants, active tenant, active role, and first-pass permissions.
- Multi-tenant users must provide tenant context.
- Non-member tenant context is rejected.
- Profile preferences persist and return through `/api/me`.
- Web identity workflow is covered by MSW-backed tests.
- Backend identity behavior is covered by Laravel feature tests.
- Requisition API calls include active tenant context.
- OpenAPI and generated client are current.
- Relevant verification commands pass or blockers are documented with exact command output.

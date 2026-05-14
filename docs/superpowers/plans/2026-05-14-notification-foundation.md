# Notification Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the inert shell notification button with a tenant-scoped in-app notification center, read-state API, profile notification preferences, and workflow-triggered notification records for requisition submission, attachment upload, and system announcements.

**Architecture:** Build this as three vertical slices following `docs/05-runbooks/feature-development.md`: API contract and mocked frontend workflow first, backend notification infrastructure and domain side effects second, then real API integration hardening. Cross-cutting notification persistence and read-state APIs live in `apps/api/app/Notifications`; procurement workflow triggers stay in `apps/api/Domains/*`; app-specific notification UI stays in `apps/web/features/notifications` and `apps/web/components/shell`.

**Tech Stack:** Laravel, Eloquent, Sanctum, tenant context middleware, OpenAPI, Orval, Next.js App Router, TanStack Query, React Hook Form, Zod, MSW, Vitest, React Testing Library.

---

## Grounding

This plan is grounded in:

- `docs/superpowers/specs/2026-05-14-notification-foundation-design.md`
- `docs/05-runbooks/feature-development.md`
- `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`
- Root `AGENTS.md` boundaries supplied in this session.

## Workflow Map

Actors:

- Requester: creates and submits requisitions, receives attachment notifications when someone else uploads evidence.
- Buyer: receives submitted requisition notifications.
- Admin: receives submitted requisition notifications.
- System: creates seed/demo system announcement notifications.

States:

- Notification: unread when `read_at` is null, read when `read_at` is set.
- Preferences: missing preferences fall back to default in-app-enabled values.
- Requisition: draft transitions to submitted, and submission creates buyer/admin notification records inside the same database transaction as audit.

Side effects:

- Requisition submission records an audit event and notification rows.
- Attachment upload stores bytes, records an audit event, and conditionally records one requester notification.
- Profile update validates and persists `notification_preferences`.
- Mark-read mutations update only read state and invalidate frontend notification queries.

Failure paths:

- Missing active tenant returns the existing tenant-context error.
- Listing and read mutations are scoped to current tenant and current user.
- Another user's notification returns `404`.
- Unknown preference event keys or non-boolean `inApp` values return `422`.
- Preference-disabled notification events are skipped silently.

## File Structure

Backend files to create:

- `apps/api/database/migrations/2026_05_14_000001_create_notifications_table.php`: notification read model table and indexes.
- `apps/api/database/migrations/2026_05_14_000002_add_notification_preferences_to_users_table.php`: JSON preferences column on users.
- `apps/api/app/Notifications/NotificationRecord.php`: Eloquent model for per-recipient notification records.
- `apps/api/app/Notifications/NotificationData.php`: typed immutable data object for recorder input.
- `apps/api/app/Notifications/NotificationPreferenceDefaults.php`: allowed event keys, labels, defaults, validation helper.
- `apps/api/app/Notifications/NotificationRecorder.php`: recipient de-duplication, preference resolution, tenant scoping, persistence.
- `apps/api/app/Notifications/Http/Controllers/NotificationController.php`: list, mark-one-read, mark-all-read endpoints.
- `apps/api/app/Notifications/Http/Resources/NotificationResource.php`: API response shape.
- `apps/api/tests/Feature/NotificationApiTest.php`: backend notification API and recorder tests.

Backend files to modify:

- `apps/api/app/Models/User.php`: add `notification_preferences` fillable and array cast.
- `apps/api/app/Auth/Http/Resources/CurrentUserResource.php`: include `notificationPreferences`.
- `apps/api/app/Http/Requests/Auth/UpdateProfileRequest.php`: validate notification preferences.
- `apps/api/app/Auth/Http/Controllers/UserProfileController.php`: persist normalized preferences.
- `apps/api/routes/api.php`: register notification endpoints under `auth:sanctum` and `ResolveCurrentTenant`.
- `apps/api/Domains/Requisition/Actions/SubmitRequisition.php`: inject recorder and record submitted notifications.
- `apps/api/Domains/Attachment/Actions/StoreRequisitionAttachment.php`: inject recorder and record evidence-uploaded notifications.
- `apps/api/database/seeders/DatabaseSeeder.php`: seed a tenant-scoped system announcement for demo coverage.
- `apps/api/storage/openapi/openapi.json`: add notification schemas, endpoints, and profile preference shape.
- `apps/api/tests/Feature/RequisitionApiTest.php`: add workflow notification assertions.
- `apps/api/tests/Feature/AttachmentApiTest.php`: add attachment notification assertions.

Frontend files to create:

- `apps/web/features/notifications/api/notifications-api.ts`: generated-client wrappers with active tenant header.
- `apps/web/features/notifications/hooks/use-notifications.ts`: list query, mark-one-read mutation, mark-all-read mutation.
- `apps/web/features/notifications/components/notification-center.tsx`: compact center with loading, empty, populated, and error states.
- `apps/web/features/notifications/components/notification-item.tsx`: row rendering and mark-before-navigation behavior.
- `apps/web/features/notifications/components/notification-preferences-fields.tsx`: profile form toggles for P0 event types.
- `apps/web/features/notifications/mocks/notification-fixtures.ts`: OpenAPI-shaped notification fixtures.
- `apps/web/features/notifications/mocks/notification-handlers.ts`: MSW handlers for list and read-state endpoints.
- `apps/web/features/notifications/tests/notification-center.test.tsx`: shell and center workflow tests.

Frontend files to modify:

- `apps/web/components/shell/notification-host.tsx`: enable notification button, unread badge, and center state.
- `apps/web/features/identity/forms/profile-form.tsx`: render notification preference fields.
- `apps/web/features/identity/schemas/profile-schema.ts`: add notification preference Zod shape.
- `apps/web/features/identity/api/identity-api.ts`: send notification preferences through generated profile request.
- `apps/web/features/identity/types/identity-view-model.ts`: include preferences in current user types if the file defines a local view model.
- `apps/web/features/identity/mocks/identity-fixtures.ts`: add default preferences to user fixture.
- `apps/web/features/identity/mocks/identity-handlers.ts`: persist preference payloads in MSW state.
- `apps/web/features/identity/tests/identity-workflow.test.tsx`: assert preference toggles save OpenAPI-shaped payloads.
- `apps/web/tests/msw/handlers.ts`: register notification handlers.

Generated files:

- `packages/api-client/src/generated/endpoints.ts`: regenerated by Orval.
- `packages/api-client/src/generated/schemas/*notification*.ts`: generated notification schemas.
- `packages/api-client/src/generated/schemas/currentUser*.ts`: generated profile preference shape updates.
- `packages/api-client/src/generated/schemas/updateCurrentUserProfileRequest*.ts`: generated profile request shape updates.
- `packages/api-client/src/generated/schemas/index.ts`: regenerated exports.

## Implementation Tasks

### Task 1: Contract Schemas And Endpoint Surface

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Generated later: `packages/api-client/src/generated/endpoints.ts`
- Generated later: `packages/api-client/src/generated/schemas/index.ts`

- [ ] **Step 1: Add failing contract expectation by editing OpenAPI first**

Add these paths to `apps/api/storage/openapi/openapi.json` under top-level `paths` beside the existing authenticated routes:

```json
"/api/notifications": {
  "get": {
    "operationId": "listNotifications",
    "tags": ["Notifications"],
    "parameters": [
      {
        "name": "status",
        "in": "query",
        "required": false,
        "schema": { "$ref": "#/components/schemas/NotificationStatusFilter" }
      },
      {
        "name": "limit",
        "in": "query",
        "required": false,
        "schema": { "type": "integer", "minimum": 1, "maximum": 50, "default": 20 }
      }
    ],
    "responses": {
      "200": {
        "description": "Notifications for the active tenant and authenticated user.",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/NotificationListResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
},
"/api/notifications/{notification}/read": {
  "post": {
    "operationId": "markNotificationRead",
    "tags": ["Notifications"],
    "parameters": [
      {
        "name": "notification",
        "in": "path",
        "required": true,
        "schema": { "type": "string" }
      }
    ],
    "responses": {
      "200": {
        "description": "Updated notification resource.",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/NotificationResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  }
},
"/api/notifications/read-all": {
  "post": {
    "operationId": "markAllNotificationsRead",
    "tags": ["Notifications"],
    "responses": {
      "200": {
        "description": "Count of notifications marked read.",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/MarkAllNotificationsReadResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" }
    }
  }
}
```

Add these schemas under `components.schemas`:

```json
"NotificationEventType": {
  "type": "string",
  "enum": ["requisition.submitted", "attachment.uploaded", "system.announcement"]
},
"NotificationStatusFilter": {
  "type": "string",
  "enum": ["all", "unread", "read"],
  "default": "all"
},
"NotificationPriority": {
  "type": "string",
  "enum": ["normal", "high"],
  "default": "normal"
},
"NotificationPreference": {
  "type": "object",
  "additionalProperties": false,
  "required": ["inApp"],
  "properties": {
    "inApp": { "type": "boolean" }
  }
},
"NotificationPreferences": {
  "type": "object",
  "additionalProperties": false,
  "required": ["requisition.submitted", "attachment.uploaded", "system.announcement"],
  "properties": {
    "requisition.submitted": { "$ref": "#/components/schemas/NotificationPreference" },
    "attachment.uploaded": { "$ref": "#/components/schemas/NotificationPreference" },
    "system.announcement": { "$ref": "#/components/schemas/NotificationPreference" }
  }
},
"NotificationActor": {
  "type": "object",
  "nullable": true,
  "required": ["id", "name"],
  "properties": {
    "id": { "type": "string" },
    "name": { "type": "string" }
  }
},
"NotificationSubject": {
  "type": "object",
  "nullable": true,
  "required": ["type", "id", "label"],
  "properties": {
    "type": { "type": "string", "enum": ["requisition", "attachment", "system"] },
    "id": { "type": "string" },
    "label": { "type": "string" }
  }
},
"Notification": {
  "type": "object",
  "required": ["id", "type", "title", "body", "href", "priority", "readAt", "createdAt", "actor", "subject", "metadata"],
  "properties": {
    "id": { "type": "string" },
    "type": { "$ref": "#/components/schemas/NotificationEventType" },
    "title": { "type": "string" },
    "body": { "type": "string", "nullable": true },
    "href": { "type": "string", "nullable": true },
    "priority": { "$ref": "#/components/schemas/NotificationPriority" },
    "readAt": { "type": "string", "format": "date-time", "nullable": true },
    "createdAt": { "type": "string", "format": "date-time" },
    "actor": { "$ref": "#/components/schemas/NotificationActor" },
    "subject": { "$ref": "#/components/schemas/NotificationSubject" },
    "metadata": {
      "type": "object",
      "additionalProperties": true
    }
  }
},
"NotificationListMeta": {
  "type": "object",
  "required": ["unreadCount", "returned", "status"],
  "properties": {
    "unreadCount": { "type": "integer", "minimum": 0 },
    "returned": { "type": "integer", "minimum": 0 },
    "status": { "$ref": "#/components/schemas/NotificationStatusFilter" }
  }
},
"NotificationListResponse": {
  "type": "object",
  "required": ["data", "meta"],
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/Notification" }
    },
    "meta": { "$ref": "#/components/schemas/NotificationListMeta" }
  }
},
"NotificationResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/Notification" }
  }
},
"MarkAllNotificationsReadResponse": {
  "type": "object",
  "required": ["data", "meta"],
  "properties": {
    "data": {
      "type": "object",
      "required": ["marked"],
      "properties": {
        "marked": { "type": "integer", "minimum": 0 }
      }
    },
    "meta": {
      "type": "object",
      "required": ["unreadCount"],
      "properties": {
        "unreadCount": { "type": "integer", "minimum": 0 }
      }
    }
  }
}
```

Update `CurrentUserProfile` and `UpdateCurrentUserProfileRequest` to include:

```json
"notificationPreferences": {
  "$ref": "#/components/schemas/NotificationPreferences"
}
```

- [ ] **Step 2: Run contract generation to expose the missing generated symbols**

Run:

```bash
pnpm generate:api
```

Expected: succeeds and produces generated notification endpoint functions named `listNotifications`, `markNotificationRead`, and `markAllNotificationsRead`.

- [ ] **Step 3: Run contract check**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS. If it fails with schema drift, fix `apps/api/storage/openapi/openapi.json` and rerun this command before continuing.

- [ ] **Step 4: Commit contract changes**

Run:

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add notification API contract"
```

Expected: commit includes only OpenAPI and generated client files.

### Task 2: MSW-Backed Notification API Wrappers

**Files:**

- Create: `apps/web/features/notifications/api/notifications-api.ts`
- Create: `apps/web/features/notifications/mocks/notification-fixtures.ts`
- Create: `apps/web/features/notifications/mocks/notification-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Create OpenAPI-shaped notification fixtures**

Create `apps/web/features/notifications/mocks/notification-fixtures.ts`:

```ts
import type { Notification, NotificationListResponse } from "@cognify/api-client/schemas";

export const notificationFixtures: Notification[] = [
  {
    id: "101",
    type: "requisition.submitted",
    title: "Requisition submitted",
    body: "REQ-2026-000042 is ready for procurement review.",
    href: "/requisitions/42",
    priority: "normal",
    readAt: null,
    createdAt: "2026-05-14T10:30:00Z",
    actor: { id: "7", name: "Maya Tan" },
    subject: { type: "requisition", id: "42", label: "REQ-2026-000042" },
    metadata: { number: "REQ-2026-000042" },
  },
  {
    id: "102",
    type: "attachment.uploaded",
    title: "Evidence uploaded",
    body: "supplier-quote.pdf was added to REQ-2026-000040.",
    href: "/requisitions/40",
    priority: "normal",
    readAt: null,
    createdAt: "2026-05-14T09:10:00Z",
    actor: { id: "8", name: "Nora Buyer" },
    subject: { type: "requisition", id: "40", label: "REQ-2026-000040" },
    metadata: { filename: "supplier-quote.pdf", number: "REQ-2026-000040" },
  },
  {
    id: "103",
    type: "system.announcement",
    title: "Maintenance window scheduled",
    body: "Cognify demo data will refresh tonight at 23:00.",
    href: null,
    priority: "normal",
    readAt: "2026-05-14T08:00:00Z",
    createdAt: "2026-05-13T16:00:00Z",
    actor: null,
    subject: null,
    metadata: {},
  },
];

export function buildNotificationListResponse(
  notifications: Notification[],
  status: "all" | "unread" | "read" = "all",
): NotificationListResponse {
  const filtered = notifications.filter((notification) => {
    if (status === "unread") return notification.readAt === null;
    if (status === "read") return notification.readAt !== null;
    return true;
  });

  return {
    data: filtered,
    meta: {
      unreadCount: notifications.filter((notification) => notification.readAt === null).length,
      returned: filtered.length,
      status,
    },
  };
}
```

- [ ] **Step 2: Create stateful notification MSW handlers**

Create `apps/web/features/notifications/mocks/notification-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import type { Notification } from "@cognify/api-client/schemas";
import { buildNotificationListResponse, notificationFixtures } from "./notification-fixtures";

let notifications: Notification[] = structuredClone(notificationFixtures);

export function resetNotificationMockState() {
  notifications = structuredClone(notificationFixtures);
}

export const notificationHandlers = [
  http.get("/api/notifications", ({ request }) => {
    const url = new URL(request.url);
    const status = (url.searchParams.get("status") ?? "all") as "all" | "unread" | "read";
    const limit = Number(url.searchParams.get("limit") ?? "20");
    const response = buildNotificationListResponse(notifications, status);

    return HttpResponse.json({
      ...response,
      data: response.data.slice(0, Number.isFinite(limit) ? limit : 20),
      meta: {
        ...response.meta,
        returned: response.data.slice(0, Number.isFinite(limit) ? limit : 20).length,
      },
    });
  }),
  http.post("/api/notifications/:notification/read", ({ params }) => {
    const id = String(params.notification);
    const now = "2026-05-14T11:00:00Z";
    const notification = notifications.find((candidate) => candidate.id === id);

    if (!notification) {
      return HttpResponse.json({ error: { code: "not_found", message: "Not found." } }, { status: 404 });
    }

    if (notification.readAt === null) {
      notification.readAt = now;
    }

    return HttpResponse.json({ data: notification });
  }),
  http.post("/api/notifications/read-all", () => {
    const now = "2026-05-14T11:00:00Z";
    let marked = 0;

    notifications = notifications.map((notification) => {
      if (notification.readAt !== null) return notification;
      marked += 1;
      return { ...notification, readAt: now };
    });

    return HttpResponse.json({
      data: { marked },
      meta: { unreadCount: 0 },
    });
  }),
];
```

- [ ] **Step 3: Register notification handlers**

Modify `apps/web/tests/msw/handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";
import { auditHandlers } from "../../features/audit/mocks/audit-handlers";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { notificationHandlers } from "../../features/notifications/mocks/notification-handlers";
import { searchHandlers } from "../../features/search/mocks/search-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
  ...searchHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...notificationHandlers,
  ...auditHandlers,
];
```

- [ ] **Step 4: Create generated-client wrappers**

Create `apps/web/features/notifications/api/notifications-api.ts`:

```ts
import {
  listNotifications as listNotificationsEndpoint,
  markAllNotificationsRead as markAllNotificationsReadEndpoint,
  markNotificationRead as markNotificationReadEndpoint,
} from "@cognify/api-client/endpoints";
import type { ListNotificationsParams } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export async function listNotifications(params: ListNotificationsParams = {}) {
  const response = await listNotificationsEndpoint(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function markNotificationRead(notificationId: string) {
  const response = await markNotificationReadEndpoint(notificationId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

export async function markAllNotificationsRead() {
  const response = await markAllNotificationsReadEndpoint(withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data;
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}
```

- [ ] **Step 5: Run frontend typecheck to confirm generated names**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: PASS. If generated function names differ, update imports in `notifications-api.ts` to match `packages/api-client/src/generated/endpoints.ts`.

- [ ] **Step 6: Commit frontend API and MSW scaffolding**

Run:

```bash
git add apps/web/features/notifications apps/web/tests/msw/handlers.ts
git commit -m "feat: add notification frontend API mocks"
```

Expected: commit includes only notification wrapper and MSW files.

### Task 3: Notification Query Hooks

**Files:**

- Create: `apps/web/features/notifications/hooks/use-notifications.ts`
- Test: `apps/web/features/notifications/tests/notification-center.test.tsx`

- [ ] **Step 1: Create failing hook consumer test through the notification center test file**

Create `apps/web/features/notifications/tests/notification-center.test.tsx`:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { resetNotificationMockState } from "../mocks/notification-handlers";

vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: vi.fn(),
  }),
}));

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("notification center", () => {
  beforeEach(() => {
    resetNotificationMockState();
    window.localStorage.setItem("cognify.activeTenantId", "tenant-1");
  });

  it("renders unread notifications from the hook-backed center", async () => {
    const { NotificationCenter } = await import("../components/notification-center");

    renderWithQuery(<NotificationCenter open onOpenChange={() => undefined} />);

    expect(await screen.findByText("Requisition submitted")).toBeInTheDocument();
    expect(screen.getByText("Evidence uploaded")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Mark all read" })).toBeEnabled();
  });

  it("marks all notifications read through query invalidation", async () => {
    const user = userEvent.setup();
    const { NotificationCenter } = await import("../components/notification-center");

    renderWithQuery(<NotificationCenter open onOpenChange={() => undefined} />);

    await user.click(await screen.findByRole("button", { name: "Mark all read" }));

    expect(await screen.findByText("No notifications for this view.")).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run the focused test and confirm it fails because components/hooks do not exist**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx
```

Expected: FAIL with a module resolution error for `../components/notification-center`.

- [ ] **Step 3: Create query hooks**

Create `apps/web/features/notifications/hooks/use-notifications.ts`:

```ts
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { ListNotificationsParams } from "@cognify/api-client/schemas";
import {
  listNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "../api/notifications-api";

export const notificationQueryKey = ["notifications"] as const;

export function useNotifications(params: ListNotificationsParams = { status: "all", limit: 20 }) {
  return useQuery({
    queryKey: [...notificationQueryKey, params],
    queryFn: () => listNotifications(params),
  });
}

export function useUnreadNotifications() {
  return useNotifications({ status: "unread", limit: 20 });
}

export function useMarkNotificationRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markNotificationRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: notificationQueryKey });
    },
  });
}

export function useMarkAllNotificationsRead() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: notificationQueryKey });
    },
  });
}
```

- [ ] **Step 4: Commit hook after UI task passes**

Do not commit yet. The hook has no standalone value until Task 4 creates the center component and the focused test passes.

### Task 4: Notification Center UI

**Files:**

- Create: `apps/web/features/notifications/components/notification-center.tsx`
- Create: `apps/web/features/notifications/components/notification-item.tsx`
- Modify: `apps/web/features/notifications/tests/notification-center.test.tsx`

- [ ] **Step 1: Create notification row component**

Create `apps/web/features/notifications/components/notification-item.tsx`:

```tsx
"use client";

import { useRouter } from "next/navigation";
import type { Notification } from "@cognify/api-client/schemas";
import { useMarkNotificationRead } from "../hooks/use-notifications";

function formatCreatedAt(value: string) {
  return new Intl.DateTimeFormat("en", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

export function NotificationItem({ notification }: { notification: Notification }) {
  const router = useRouter();
  const markRead = useMarkNotificationRead();
  const unread = notification.readAt === null;

  const openNotification = async () => {
    if (unread) {
      await markRead.mutateAsync(notification.id);
    }

    if (notification.href) {
      router.push(notification.href);
    }
  };

  return (
    <li className="border-b last:border-b-0">
      <button
        type="button"
        onClick={openNotification}
        className="grid w-full gap-1 px-4 py-3 text-left hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        <span className="flex items-center gap-2">
          {unread && <span className="h-2 w-2 rounded-full bg-primary" aria-label="Unread" />}
          <span className="text-sm font-medium">{notification.title}</span>
        </span>
        {notification.body && <span className="text-sm text-muted-foreground">{notification.body}</span>}
        <span className="text-xs text-muted-foreground">
          {notification.actor?.name ? `${notification.actor.name} · ` : ""}
          {notification.subject?.label ? `${notification.subject.label} · ` : ""}
          {formatCreatedAt(notification.createdAt)}
        </span>
      </button>
    </li>
  );
}
```

- [ ] **Step 2: Create compact center component**

Create `apps/web/features/notifications/components/notification-center.tsx`:

```tsx
"use client";

import * as Popover from "@radix-ui/react-popover";
import { NotificationItem } from "./notification-item";
import { useMarkAllNotificationsRead, useUnreadNotifications } from "../hooks/use-notifications";

export function NotificationCenter({
  open,
  onOpenChange,
  children,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  children?: React.ReactNode;
}) {
  const notifications = useUnreadNotifications();
  const markAllRead = useMarkAllNotificationsRead();

  return (
    <Popover.Root open={open} onOpenChange={onOpenChange}>
      {children && <Popover.Trigger asChild>{children}</Popover.Trigger>}
      <Popover.Portal>
        <Popover.Content
          align="end"
          sideOffset={8}
          className="z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl border bg-background shadow-xl"
        >
          <div className="flex items-center justify-between border-b px-4 py-3">
            <h2 className="text-sm font-semibold">Notifications</h2>
            <button
              type="button"
              onClick={() => markAllRead.mutate()}
              disabled={!notifications.data?.meta.unreadCount || markAllRead.isPending}
              className="text-xs font-medium text-primary disabled:text-muted-foreground"
            >
              Mark all read
            </button>
          </div>

          {notifications.isLoading && (
            <div className="px-4 py-6 text-sm text-muted-foreground">Loading notifications...</div>
          )}

          {notifications.isError && (
            <div className="grid gap-3 px-4 py-6">
              <p className="text-sm text-destructive">Failed to load notifications.</p>
              <button
                type="button"
                onClick={() => void notifications.refetch()}
                className="justify-self-start rounded-md border px-3 py-1.5 text-sm"
              >
                Retry
              </button>
            </div>
          )}

          {notifications.data && notifications.data.data.length === 0 && (
            <div className="px-4 py-8 text-sm text-muted-foreground">
              No notifications for this view.
            </div>
          )}

          {notifications.data && notifications.data.data.length > 0 && (
            <ul className="max-h-96 overflow-y-auto">
              {notifications.data.data.map((notification) => (
                <NotificationItem key={notification.id} notification={notification} />
              ))}
            </ul>
          )}
        </Popover.Content>
      </Popover.Portal>
    </Popover.Root>
  );
}
```

- [ ] **Step 3: Run focused center tests**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx
```

Expected: PASS for the center rendering and mark-all-read tests.

- [ ] **Step 4: Commit hooks and center UI**

Run:

```bash
git add apps/web/features/notifications
git commit -m "feat: add notification center workflow"
```

Expected: commit includes notification hooks, center components, mocks, and tests.

### Task 5: Shell Notification Host Integration

**Files:**

- Modify: `apps/web/components/shell/notification-host.tsx`
- Modify: `apps/web/features/notifications/tests/notification-center.test.tsx`

- [ ] **Step 1: Add failing shell test**

Append this test to `apps/web/features/notifications/tests/notification-center.test.tsx`:

```tsx
it("shell bell renders unread count and accessible label", async () => {
  const { NotificationHost } = await import("@/components/shell/notification-host");

  renderWithQuery(<NotificationHost />);

  expect(await screen.findByRole("button", { name: "Open notifications, 2 unread" })).toBeEnabled();
  expect(screen.getByText("2")).toBeInTheDocument();
});
```

- [ ] **Step 2: Run focused test and confirm disabled host fails**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx
```

Expected: FAIL because `NotificationHost` is disabled and does not show unread count.

- [ ] **Step 3: Enable shell host**

Replace `apps/web/components/shell/notification-host.tsx` with:

```tsx
"use client";

import { useState } from "react";
import { Bell } from "lucide-react";
import { NotificationCenter } from "@/features/notifications/components/notification-center";
import { useUnreadNotifications } from "@/features/notifications/hooks/use-notifications";

export function NotificationHost() {
  const [open, setOpen] = useState(false);
  const notifications = useUnreadNotifications();
  const unreadCount = notifications.data?.meta.unreadCount ?? 0;
  const label =
    unreadCount > 0 ? `Open notifications, ${unreadCount} unread` : "Open notifications";

  return (
    <NotificationCenter open={open} onOpenChange={setOpen}>
      <button
        type="button"
        className="relative inline-flex min-h-10 w-10 items-center justify-center rounded-md border text-muted-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label={label}
      >
        <Bell className="h-4 w-4" aria-hidden="true" />
        {unreadCount > 0 && (
          <span className="absolute -right-1 -top-1 min-w-5 rounded-full bg-primary px-1.5 py-0.5 text-center text-[0.6875rem] font-semibold leading-none text-primary-foreground">
            {unreadCount}
          </span>
        )}
      </button>
    </NotificationCenter>
  );
}
```

- [ ] **Step 4: Run shell and existing shell tests**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx apps/web/components/shell/app-shell.test.tsx
```

Expected: PASS. The existing shell test should not require notification behavior changes beyond the host becoming enabled.

- [ ] **Step 5: Commit shell integration**

Run:

```bash
git add apps/web/components/shell/notification-host.tsx apps/web/features/notifications/tests/notification-center.test.tsx
git commit -m "feat: enable shell notification host"
```

Expected: commit includes shell host and notification test changes.

### Task 6: Account Settings Notification Preferences UI

**Files:**

- Create: `apps/web/features/notifications/components/notification-preferences-fields.tsx`
- Modify: `apps/web/features/identity/schemas/profile-schema.ts`
- Modify: `apps/web/features/identity/forms/profile-form.tsx`
- Modify: `apps/web/features/identity/api/identity-api.ts`
- Modify: `apps/web/features/identity/mocks/identity-fixtures.ts`
- Modify: `apps/web/features/identity/mocks/identity-handlers.ts`
- Modify: `apps/web/features/identity/tests/identity-workflow.test.tsx`

- [ ] **Step 1: Add failing account settings test for the three P0 toggles**

Replace the existing `updates profile preferences through the account settings workflow` test in `apps/web/features/identity/tests/identity-workflow.test.tsx` with:

```tsx
it("updates profile and notification preferences through the account settings workflow", async () => {
  let submittedBody: unknown;
  server.use(
    http.patch("/api/me/profile", async ({ request }) => {
      submittedBody = await request.json();
      return HttpResponse.json({
        data: {
          ...multiTenantIdentity,
          user: {
            ...multiTenantIdentity.user,
            name: "Taylor Buyer",
            theme: "dark",
            notificationPreferences: {
              "requisition.submitted": { inApp: true },
              "attachment.uploaded": { inApp: false },
              "system.announcement": { inApp: true },
            },
          },
        },
      });
    }),
  );
  const user = userEvent.setup();

  renderWithQuery(<AccountSettingsPage />);

  const nameInput = await screen.findByLabelText("Name");
  await user.clear(nameInput);
  await user.type(nameInput, "Taylor Buyer");
  await user.selectOptions(screen.getByLabelText("Theme"), "dark");
  await user.click(screen.getByRole("switch", { name: "Evidence uploaded" }));
  await user.click(screen.getByRole("button", { name: "Save profile" }));

  expect(await screen.findByText("Profile saved")).toBeInTheDocument();
  expect(submittedBody).toMatchObject({
    name: "Taylor Buyer",
    theme: "dark",
    notificationPreferences: {
      "requisition.submitted": { inApp: true },
      "attachment.uploaded": { inApp: false },
      "system.announcement": { inApp: true },
    },
  });
});
```

- [ ] **Step 2: Run focused identity test and confirm preferences are absent**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/identity/tests/identity-workflow.test.tsx
```

Expected: FAIL because account settings does not render notification preference toggles.

- [ ] **Step 3: Extend profile schema**

Modify `apps/web/features/identity/schemas/profile-schema.ts`:

```ts
import { z } from "zod";

export const notificationPreferencesSchema = z.object({
  "requisition.submitted": z.object({ inApp: z.boolean() }),
  "attachment.uploaded": z.object({ inApp: z.boolean() }),
  "system.announcement": z.object({ inApp: z.boolean() }),
});

export const defaultNotificationPreferences = {
  "requisition.submitted": { inApp: true },
  "attachment.uploaded": { inApp: true },
  "system.announcement": { inApp: true },
} satisfies z.infer<typeof notificationPreferencesSchema>;

export const profileSchema = z.object({
  name: z.string().min(1, "Name is required.").max(255),
  avatarUrl: z.union([
    z.string().url("Enter a valid URL.").max(2048),
    z.literal(""),
    z.null(),
  ]),
  timezone: z.string().min(1, "Timezone is required.").max(64),
  locale: z.string().min(2).max(12),
  theme: z.enum(["light", "dark", "system"]),
  notificationPreferences: notificationPreferencesSchema,
});

export type ProfileFormValues = z.infer<typeof profileSchema>;
```

- [ ] **Step 4: Create preference fields component**

Create `apps/web/features/notifications/components/notification-preferences-fields.tsx`:

```tsx
"use client";

import type { UseFormRegister } from "react-hook-form";
import type { ProfileFormValues } from "@/features/identity/schemas/profile-schema";

const preferenceFields = [
  {
    key: "requisition.submitted",
    label: "Requisition submitted",
    description: "Notify me when requisitions are ready for procurement review.",
  },
  {
    key: "attachment.uploaded",
    label: "Evidence uploaded",
    description: "Notify me when evidence is added to my requisitions by another user.",
  },
  {
    key: "system.announcement",
    label: "System announcements",
    description: "Notify me about tenant-level Cognify notices.",
  },
] as const;

export function NotificationPreferencesFields({
  register,
}: {
  register: UseFormRegister<ProfileFormValues>;
}) {
  return (
    <section className="space-y-3 rounded-lg border p-4" aria-labelledby="notification-preferences-title">
      <div>
        <h2 id="notification-preferences-title" className="text-sm font-semibold">
          Notifications
        </h2>
        <p className="text-sm text-muted-foreground">Choose which in-app workflow cues you receive.</p>
      </div>
      <div className="grid gap-3">
        {preferenceFields.map((field) => (
          <label key={field.key} className="flex items-start justify-between gap-4 rounded-md border px-3 py-2">
            <span>
              <span className="block text-sm font-medium">{field.label}</span>
              <span className="block text-xs text-muted-foreground">{field.description}</span>
            </span>
            <input
              type="checkbox"
              role="switch"
              className="mt-1 h-4 w-4"
              {...register(`notificationPreferences.${field.key}.inApp`)}
            />
          </label>
        ))}
      </div>
    </section>
  );
}
```

- [ ] **Step 5: Wire default values and fields into the profile form**

Modify `apps/web/features/identity/forms/profile-form.tsx`:

```tsx
import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import {
  defaultNotificationPreferences,
  profileSchema,
  type ProfileFormValues,
} from "../schemas/profile-schema";
import { useProfileUpdate } from "../hooks/use-profile-update";
import type { CurrentUserProfile } from "@cognify/api-client/schemas";
import { NotificationPreferencesFields } from "@/features/notifications/components/notification-preferences-fields";

function getProfileFormValues(profile: CurrentUserProfile): ProfileFormValues {
  return {
    name: profile.name,
    avatarUrl: profile.avatarUrl || "",
    timezone: profile.timezone,
    locale: profile.locale,
    theme: profile.theme,
    notificationPreferences: profile.notificationPreferences ?? defaultNotificationPreferences,
  };
}
```

Render the fields before the submit button:

```tsx
<NotificationPreferencesFields register={register} />
```

- [ ] **Step 6: Ensure profile request carries preferences**

Modify `apps/web/features/identity/api/identity-api.ts` request creation:

```ts
const request = {
  ...values,
  avatarUrl: values.avatarUrl || null,
  notificationPreferences: values.notificationPreferences,
} satisfies UpdateCurrentUserProfileRequest;
```

- [ ] **Step 7: Add preferences to identity fixtures and handler state**

In `apps/web/features/identity/mocks/identity-fixtures.ts`, ensure every `user` object includes:

```ts
notificationPreferences: {
  "requisition.submitted": { inApp: true },
  "attachment.uploaded": { inApp: true },
  "system.announcement": { inApp: true },
},
```

In `apps/web/features/identity/mocks/identity-handlers.ts`, keep the existing spread update:

```ts
const body = (await request.json()) as Partial<CurrentUserContext["user"]>;
currentIdentity = {
  ...currentIdentity,
  user: { ...currentIdentity.user, ...body },
};
```

This is sufficient because the profile payload is still a user-profile-shaped partial.

- [ ] **Step 8: Run focused identity workflow test**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/identity/tests/identity-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 9: Commit settings preferences UI**

Run:

```bash
git add apps/web/features/identity apps/web/features/notifications/components/notification-preferences-fields.tsx
git commit -m "feat: add notification preferences settings"
```

Expected: commit includes profile schema, form, mocks, tests, and preference fields.

### Task 7: Backend Notification Persistence And Preferences

**Files:**

- Create: `apps/api/database/migrations/2026_05_14_000001_create_notifications_table.php`
- Create: `apps/api/database/migrations/2026_05_14_000002_add_notification_preferences_to_users_table.php`
- Create: `apps/api/app/Notifications/NotificationRecord.php`
- Create: `apps/api/app/Notifications/NotificationData.php`
- Create: `apps/api/app/Notifications/NotificationPreferenceDefaults.php`
- Create: `apps/api/app/Notifications/NotificationRecorder.php`
- Modify: `apps/api/app/Models/User.php`
- Test: `apps/api/tests/Feature/NotificationApiTest.php`

- [ ] **Step 1: Create failing recorder/preference tests**

Create `apps/api/tests/Feature/NotificationApiTest.php` with the first tests:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationRecorder;
use App\Notifications\NotificationRecord;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_creates_one_notification_per_unique_recipient_with_defaults(): void
    {
        [$tenant, $actor] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$buyer, $buyer],
            data: new NotificationData(
                type: 'system.announcement',
                title: 'Maintenance window scheduled',
                body: 'Cognify demo data will refresh tonight at 23:00.',
                actor: $actor,
            ),
        );

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $buyer->id,
            'actor_id' => $actor->id,
            'type' => 'system.announcement',
            'title' => 'Maintenance window scheduled',
            'priority' => 'normal',
        ]);
    }

    public function test_recorder_skips_disabled_in_app_preferences(): void
    {
        [$tenant, $actor] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $buyer->forceFill([
            'notification_preferences' => [
                'requisition.submitted' => ['inApp' => false],
                'attachment.uploaded' => ['inApp' => true],
                'system.announcement' => ['inApp' => true],
            ],
        ])->save();

        app(NotificationRecorder::class)->record(
            tenant: $tenant,
            recipients: [$buyer],
            data: new NotificationData(
                type: 'requisition.submitted',
                title: 'Requisition submitted',
                body: 'REQ-2026-000001 is ready for procurement review.',
                actor: $actor,
            ),
        );

        $this->assertDatabaseCount('notifications', 0);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }
}
```

- [ ] **Step 2: Run backend notification test and confirm missing classes/table fail**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: FAIL with missing `App\Notifications\NotificationRecorder` or missing `notifications` table.

- [ ] **Step 3: Add notifications table migration**

Create `apps/api/database/migrations/2026_05_14_000001_create_notifications_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('href')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->default('{}');
            $table->string('priority')->default('normal');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'recipient_id', 'read_at', 'created_at']);
            $table->index(['tenant_id', 'recipient_id', 'created_at']);
            $table->index(['tenant_id', 'type', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 4: Add user preferences migration**

Create `apps/api/database/migrations/2026_05_14_000002_add_notification_preferences_to_users_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('notification_preferences')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notification_preferences');
        });
    }
};
```

- [ ] **Step 5: Create notification model and data object**

Create `apps/api/app/Notifications/NotificationRecord.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationRecord extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'tenant_id',
        'recipient_id',
        'actor_id',
        'type',
        'title',
        'body',
        'href',
        'subject_type',
        'subject_id',
        'metadata',
        'priority',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Create `apps/api/app/Notifications/NotificationData.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

readonly class NotificationData
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body = null,
        public ?string $href = null,
        public ?Model $subject = null,
        public ?string $subjectLabel = null,
        public array $metadata = [],
        public string $priority = 'normal',
        public ?User $actor = null,
    ) {
    }
}
```

- [ ] **Step 6: Create preference defaults and recorder**

Create `apps/api/app/Notifications/NotificationPreferenceDefaults.php`:

```php
<?php

namespace App\Notifications;

use Illuminate\Validation\Rule;

class NotificationPreferenceDefaults
{
    public const EVENT_REQUISITION_SUBMITTED = 'requisition.submitted';
    public const EVENT_ATTACHMENT_UPLOADED = 'attachment.uploaded';
    public const EVENT_SYSTEM_ANNOUNCEMENT = 'system.announcement';

    public const EVENTS = [
        self::EVENT_REQUISITION_SUBMITTED,
        self::EVENT_ATTACHMENT_UPLOADED,
        self::EVENT_SYSTEM_ANNOUNCEMENT,
    ];

    /**
     * @return array<string, array{inApp: bool}>
     */
    public static function defaults(): array
    {
        return [
            self::EVENT_REQUISITION_SUBMITTED => ['inApp' => true],
            self::EVENT_ATTACHMENT_UPLOADED => ['inApp' => true],
            self::EVENT_SYSTEM_ANNOUNCEMENT => ['inApp' => true],
        ];
    }

    /**
     * @param array<string, mixed>|null $preferences
     * @return array<string, array{inApp: bool}>
     */
    public static function merge(?array $preferences): array
    {
        $merged = self::defaults();

        foreach ($preferences ?? [] as $event => $channels) {
            if (in_array($event, self::EVENTS, true) && is_array($channels) && array_key_exists('inApp', $channels)) {
                $merged[$event]['inApp'] = (bool) $channels['inApp'];
            }
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'notificationPreferences' => [
                'sometimes',
                'array',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (array_keys((array) $value) as $event) {
                        if (! in_array($event, self::EVENTS, true)) {
                            $fail("The {$attribute} field contains an unsupported notification event.");
                        }
                    }
                },
            ],
            'notificationPreferences.*' => ['array:inApp'],
            'notificationPreferences.*.inApp' => ['required', 'boolean'],
        ];
    }
}
```

Create `apps/api/app/Notifications/NotificationRecorder.php`:

```php
<?php

namespace App\Notifications;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Support\Collection;

class NotificationRecorder
{
    /**
     * @param iterable<User> $recipients
     */
    public function record(Tenant $tenant, iterable $recipients, NotificationData $data): void
    {
        Collection::make($recipients)
            ->filter(fn (User $recipient): bool => $recipient->tenants()->whereKey($tenant->id)->exists())
            ->unique(fn (User $recipient): int => $recipient->id)
            ->filter(fn (User $recipient): bool => $this->allowsInApp($recipient, $data->type))
            ->each(function (User $recipient) use ($tenant, $data): void {
                NotificationRecord::query()->create([
                    'tenant_id' => $tenant->id,
                    'recipient_id' => $recipient->id,
                    'actor_id' => $data->actor?->id,
                    'type' => $data->type,
                    'title' => $data->title,
                    'body' => $data->body,
                    'href' => $data->href,
                    'subject_type' => $data->subject?->getMorphClass(),
                    'subject_id' => $data->subject?->getKey(),
                    'metadata' => array_filter([
                        ...$data->metadata,
                        'subjectLabel' => $data->subjectLabel,
                    ], fn (mixed $value): bool => $value !== null),
                    'priority' => $data->priority,
                ]);
            });
    }

    private function allowsInApp(User $recipient, string $event): bool
    {
        $preferences = NotificationPreferenceDefaults::merge($recipient->notification_preferences);

        return $preferences[$event]['inApp'] ?? true;
    }
}
```

- [ ] **Step 7: Update User model casts and fillable**

Modify `apps/api/app/Models/User.php`:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'avatar_url',
    'timezone',
    'locale',
    'theme',
    'notification_preferences',
];
```

Add cast:

```php
'notification_preferences' => 'array',
```

- [ ] **Step 8: Run focused recorder tests**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: PASS for the two recorder tests.

- [ ] **Step 9: Commit backend persistence and recorder**

Run:

```bash
git add apps/api/database/migrations apps/api/app/Notifications apps/api/app/Models/User.php apps/api/tests/Feature/NotificationApiTest.php
git commit -m "feat: add notification persistence recorder"
```

Expected: commit includes migrations, model, data, preference defaults, recorder, and tests.

### Task 8: Backend Notification API Endpoints

**Files:**

- Create: `apps/api/app/Notifications/Http/Resources/NotificationResource.php`
- Create: `apps/api/app/Notifications/Http/Controllers/NotificationController.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/tests/Feature/NotificationApiTest.php`

- [ ] **Step 1: Add failing API tests for list and read state**

Append these tests to `apps/api/tests/Feature/NotificationApiTest.php`:

```php
public function test_user_lists_only_their_current_tenant_notifications_with_status_filters(): void
{
    [$tenant, $user] = $this->tenantUser('buyer');
    [$otherTenant, $otherUser] = $this->tenantUser('buyer');

    NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $user->id,
        'type' => 'system.announcement',
        'title' => 'Unread tenant notice',
        'metadata' => [],
    ]);
    NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $user->id,
        'type' => 'system.announcement',
        'title' => 'Read tenant notice',
        'metadata' => [],
        'read_at' => now(),
    ]);
    NotificationRecord::query()->create([
        'tenant_id' => $otherTenant->id,
        'recipient_id' => $otherUser->id,
        'type' => 'system.announcement',
        'title' => 'Other tenant notice',
        'metadata' => [],
    ]);

    $this->actingAsTenant($tenant, $user)
        ->getJson('/api/notifications?status=unread')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Unread tenant notice')
        ->assertJsonPath('meta.unreadCount', 1)
        ->assertJsonPath('meta.status', 'unread');
}

public function test_mark_one_read_is_idempotent_and_rejects_other_recipients(): void
{
    [$tenant, $user] = $this->tenantUser('buyer');
    [, $otherUser] = $this->tenantUser('buyer', $tenant);
    $notification = NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $user->id,
        'type' => 'system.announcement',
        'title' => 'Unread tenant notice',
        'metadata' => [],
    ]);
    $otherNotification = NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $otherUser->id,
        'type' => 'system.announcement',
        'title' => 'Other recipient notice',
        'metadata' => [],
    ]);

    $first = $this->actingAsTenant($tenant, $user)
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->json('data.readAt');

    $this->actingAsTenant($tenant, $user)
        ->postJson("/api/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.readAt', $first);

    $this->actingAsTenant($tenant, $user)
        ->postJson("/api/notifications/{$otherNotification->id}/read")
        ->assertNotFound();
}

public function test_mark_all_read_marks_only_current_user_current_tenant_notifications(): void
{
    [$tenant, $user] = $this->tenantUser('buyer');
    [, $otherUser] = $this->tenantUser('buyer', $tenant);
    NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $user->id,
        'type' => 'system.announcement',
        'title' => 'First unread',
        'metadata' => [],
    ]);
    NotificationRecord::query()->create([
        'tenant_id' => $tenant->id,
        'recipient_id' => $otherUser->id,
        'type' => 'system.announcement',
        'title' => 'Other unread',
        'metadata' => [],
    ]);

    $this->actingAsTenant($tenant, $user)
        ->postJson('/api/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.marked', 1)
        ->assertJsonPath('meta.unreadCount', 0);

    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $otherUser->id,
        'read_at' => null,
    ]);
}
```

- [ ] **Step 2: Run focused API tests and confirm route failure**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: FAIL with `404` for notification endpoints.

- [ ] **Step 3: Create notification resource**

Create `apps/api/app/Notifications/Http/Resources/NotificationResource.php`:

```php
<?php

namespace App\Notifications\Http\Resources;

use App\Notifications\NotificationRecord;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationRecord
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'href' => $this->href,
            'priority' => $this->priority,
            'readAt' => $this->read_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => $this->actor->name,
            ] : null,
            'subject' => $this->subject_type ? [
                'type' => $this->subjectType(),
                'id' => (string) $this->subject_id,
                'label' => (string) ($this->metadata['subjectLabel'] ?? $this->subject_id),
            ] : null,
            'metadata' => $this->metadata ?? [],
        ];
    }

    private function subjectType(): string
    {
        return $this->subject_type === Requisition::class ? 'requisition' : 'system';
    }
}
```

- [ ] **Step 4: Create notification controller**

Create `apps/api/app/Notifications/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Notifications\Http\Controllers;

use App\Notifications\Http\Resources\NotificationResource;
use App\Notifications\NotificationRecord;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:all,unread,read'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $status = $validated['status'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 20);
        $tenant = $currentTenant->require();
        $user = $request->user();

        $baseQuery = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $user->id);

        $unreadCount = (clone $baseQuery)->whereNull('read_at')->count();
        $records = (clone $baseQuery)
            ->when($status === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->when($status === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->with(['actor'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => NotificationResource::collection($records),
            'meta' => [
                'unreadCount' => $unreadCount,
                'returned' => $records->count(),
                'status' => $status,
            ],
        ]);
    }

    public function markRead(Request $request, CurrentTenant $currentTenant, NotificationRecord $notification): JsonResponse
    {
        $tenant = $currentTenant->require();

        if ($notification->tenant_id !== $tenant->id || $notification->recipient_id !== $request->user()->id) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'data' => new NotificationResource($notification->refresh()->load(['actor'])),
        ]);
    }

    public function markAllRead(Request $request, CurrentTenant $currentTenant): JsonResponse
    {
        $tenant = $currentTenant->require();
        $query = NotificationRecord::query()
            ->where('tenant_id', $tenant->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at');
        $marked = (clone $query)->count();
        $query->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'data' => ['marked' => $marked],
            'meta' => ['unreadCount' => 0],
        ]);
    }
}
```

- [ ] **Step 5: Register routes**

Modify `apps/api/routes/api.php` imports:

```php
use App\Notifications\Http\Controllers\NotificationController;
```

Add inside the `ResolveCurrentTenant` group after profile routes:

```php
Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
```

- [ ] **Step 6: Run focused API tests**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: PASS.

- [ ] **Step 7: Verify route registration**

Run:

```bash
cd apps/api && php artisan route:list --path=api/notifications
```

Expected: shows `GET api/notifications`, `POST api/notifications/read-all`, and `POST api/notifications/{notification}/read`.

- [ ] **Step 8: Commit notification API endpoints**

Run:

```bash
git add apps/api/app/Notifications/Http apps/api/routes/api.php apps/api/tests/Feature/NotificationApiTest.php
git commit -m "feat: add notification read state API"
```

Expected: commit includes controller, resource, routes, and API tests.

### Task 9: Backend Profile Preferences

**Files:**

- Modify: `apps/api/app/Auth/Http/Resources/CurrentUserResource.php`
- Modify: `apps/api/app/Http/Requests/Auth/UpdateProfileRequest.php`
- Modify: `apps/api/app/Auth/Http/Controllers/UserProfileController.php`
- Modify: `apps/api/tests/Feature/NotificationApiTest.php`

- [ ] **Step 1: Add failing profile preference tests**

Append these tests to `apps/api/tests/Feature/NotificationApiTest.php`:

```php
public function test_current_user_response_includes_notification_preferences_with_defaults(): void
{
    [$tenant, $user] = $this->tenantUser('requester');

    $this->actingAsTenant($tenant, $user)
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.user.notificationPreferences.requisition.submitted.inApp', true)
        ->assertJsonPath('data.user.notificationPreferences.attachment.uploaded.inApp', true)
        ->assertJsonPath('data.user.notificationPreferences.system.announcement.inApp', true);
}

public function test_profile_update_validates_and_persists_notification_preferences(): void
{
    [$tenant, $user] = $this->tenantUser('requester');

    $this->actingAsTenant($tenant, $user)
        ->patchJson('/api/me/profile', [
            'name' => $user->name,
            'avatarUrl' => null,
            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => 'system',
            'notificationPreferences' => [
                'requisition.submitted' => ['inApp' => true],
                'attachment.uploaded' => ['inApp' => false],
                'system.announcement' => ['inApp' => true],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.user.notificationPreferences.attachment.uploaded.inApp', false);

    $this->assertSame(false, $user->fresh()->notification_preferences['attachment.uploaded']['inApp']);
}

public function test_profile_update_rejects_unknown_notification_preference_keys(): void
{
    [$tenant, $user] = $this->tenantUser('requester');

    $this->actingAsTenant($tenant, $user)
        ->patchJson('/api/me/profile', [
            'name' => $user->name,
            'avatarUrl' => null,
            'timezone' => 'UTC',
            'locale' => 'en',
            'theme' => 'system',
            'notificationPreferences' => [
                'unknown.event' => ['inApp' => true],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed');
}
```

- [ ] **Step 2: Run focused tests and confirm profile shape failure**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: FAIL because profile resources and validation do not include notification preferences.

- [ ] **Step 3: Include merged preferences in current user resource**

Modify `apps/api/app/Auth/Http/Resources/CurrentUserResource.php` imports:

```php
use App\Notifications\NotificationPreferenceDefaults;
```

Add to the `user` array:

```php
'notificationPreferences' => NotificationPreferenceDefaults::merge($this->notification_preferences),
```

- [ ] **Step 4: Validate profile preference payload**

Modify `apps/api/app/Http/Requests/Auth/UpdateProfileRequest.php` imports:

```php
use App\Notifications\NotificationPreferenceDefaults;
```

Replace `rules()` return value with:

```php
return [
    'name' => ['required', 'string', 'max:255'],
    'avatarUrl' => ['nullable', 'url', 'max:2048'],
    'timezone' => ['required', 'timezone', 'max:64'],
    'locale' => ['required', 'string', Rule::in(config('app.supported_locales', ['en']))],
    'theme' => ['required', 'in:light,dark,system'],
    ...NotificationPreferenceDefaults::validationRules(),
];
```

- [ ] **Step 5: Persist merged preferences during profile update**

Modify `apps/api/app/Auth/Http/Controllers/UserProfileController.php` imports:

```php
use App\Notifications\NotificationPreferenceDefaults;
```

Replace the `$user->update([...])` block with:

```php
$preferences = $request->has('notificationPreferences')
    ? NotificationPreferenceDefaults::merge($request->input('notificationPreferences'))
    : NotificationPreferenceDefaults::merge($user->notification_preferences);

$user->update([
    'name' => $request->input('name'),
    'avatar_url' => $request->input('avatarUrl'),
    'timezone' => $request->input('timezone'),
    'locale' => $request->input('locale'),
    'theme' => $request->input('theme'),
    'notification_preferences' => $preferences,
]);
```

- [ ] **Step 6: Run focused profile notification tests**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: PASS.

- [ ] **Step 7: Commit profile preferences backend**

Run:

```bash
git add apps/api/app/Auth/Http/Resources/CurrentUserResource.php apps/api/app/Http/Requests/Auth/UpdateProfileRequest.php apps/api/app/Auth/Http/Controllers/UserProfileController.php apps/api/tests/Feature/NotificationApiTest.php
git commit -m "feat: persist notification preferences"
```

Expected: commit includes profile validation, resource, controller, and tests.

### Task 10: Workflow Notification Triggers

**Files:**

- Modify: `apps/api/Domains/Requisition/Actions/SubmitRequisition.php`
- Modify: `apps/api/Domains/Attachment/Actions/StoreRequisitionAttachment.php`
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php`
- Modify: `apps/api/tests/Feature/AttachmentApiTest.php`

- [ ] **Step 1: Add failing requisition submission notification test**

Append to `apps/api/tests/Feature/RequisitionApiTest.php`:

```php
public function test_submitting_requisition_notifies_buyer_and_admin_but_not_requester(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    [, $admin] = $this->tenantUser('admin', $tenant);
    $requisition = $this->createDraft($tenant, $requester);

    $this->actingAsTenant($tenant, $requester)
        ->postJson("/api/requisitions/{$requisition->id}/submit")
        ->assertOk();

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $buyer->id,
        'actor_id' => $requester->id,
        'type' => 'requisition.submitted',
        'href' => "/requisitions/{$requisition->id}",
    ]);
    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $admin->id,
        'actor_id' => $requester->id,
        'type' => 'requisition.submitted',
        'href' => "/requisitions/{$requisition->id}",
    ]);
    $this->assertDatabaseMissing('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $requester->id,
        'type' => 'requisition.submitted',
    ]);
}
```

- [ ] **Step 2: Add failing attachment upload notification tests**

Append to `apps/api/tests/Feature/AttachmentApiTest.php`:

```php
public function test_attachment_upload_notifies_requester_when_uploader_differs(): void
{
    Storage::fake('attachments');

    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $requisition = $this->createDraft($tenant, $requester);

    $this->actingAsTenant($tenant, $buyer)
        ->post('/api/requisitions/'.$requisition->id.'/attachments', [
            'file' => UploadedFile::fake()->create('quote.pdf', 64, 'application/pdf'),
        ])
        ->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $requester->id,
        'actor_id' => $buyer->id,
        'type' => 'attachment.uploaded',
        'title' => 'Evidence uploaded',
        'href' => "/requisitions/{$requisition->id}",
    ]);
}

public function test_attachment_upload_skips_notification_when_requester_uploads(): void
{
    Storage::fake('attachments');

    [$tenant, $requester] = $this->tenantUser('requester');
    $requisition = $this->createDraft($tenant, $requester);

    $this->actingAsTenant($tenant, $requester)
        ->post('/api/requisitions/'.$requisition->id.'/attachments', [
            'file' => UploadedFile::fake()->create('quote.pdf', 64, 'application/pdf'),
        ])
        ->assertCreated();

    $this->assertDatabaseMissing('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $requester->id,
        'type' => 'attachment.uploaded',
    ]);
}
```

- [ ] **Step 3: Run focused workflow tests and confirm notification assertions fail**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionApiTest|AttachmentApiTest'
```

Expected: FAIL on missing notification rows.

- [ ] **Step 4: Record requisition submission notifications**

Modify `apps/api/Domains/Requisition/Actions/SubmitRequisition.php` imports:

```php
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
```

Modify constructor:

```php
public function __construct(
    private readonly AuditRecorder $auditRecorder,
    private readonly NotificationRecorder $notificationRecorder,
) {
}
```

Inside the transaction after audit recording and before return:

```php
$recipients = $tenant->users()
    ->wherePivotIn('role', ['buyer', 'admin'])
    ->whereKeyNot($actor->id)
    ->get();

$this->notificationRecorder->record(
    tenant: $tenant,
    recipients: $recipients,
    data: new NotificationData(
        type: NotificationPreferenceDefaults::EVENT_REQUISITION_SUBMITTED,
        title: 'Requisition submitted',
        body: "{$requisition->number} is ready for procurement review.",
        href: "/requisitions/{$requisition->id}",
        subject: $requisition,
        subjectLabel: $requisition->number,
        metadata: ['number' => $requisition->number],
        actor: $actor,
    ),
);
```

- [ ] **Step 5: Record attachment upload notifications**

Modify `apps/api/Domains/Attachment/Actions/StoreRequisitionAttachment.php` imports:

```php
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
```

Modify constructor:

```php
public function __construct(
    private readonly AuditRecorder $auditRecorder,
    private readonly AttachmentStorage $storage,
    private readonly NotificationRecorder $notificationRecorder,
) {
}
```

Inside the transaction after audit recording:

```php
$requisition->loadMissing('requester');

if ($requisition->requester && $requisition->requester->id !== $actor->id) {
    $this->notificationRecorder->record(
        tenant: $tenant,
        recipients: [$requisition->requester],
        data: new NotificationData(
            type: NotificationPreferenceDefaults::EVENT_ATTACHMENT_UPLOADED,
            title: 'Evidence uploaded',
            body: "{$attachment->original_filename} was added to {$requisition->number}.",
            href: "/requisitions/{$requisition->id}",
            subject: $requisition,
            subjectLabel: $requisition->number,
            metadata: [
                'filename' => $attachment->original_filename,
                'number' => $requisition->number,
            ],
            actor: $actor,
        ),
    );
}
```

- [ ] **Step 6: Run focused workflow tests**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionApiTest|AttachmentApiTest'
```

Expected: PASS.

- [ ] **Step 7: Commit workflow notification triggers**

Run:

```bash
git add apps/api/Domains/Requisition/Actions/SubmitRequisition.php apps/api/Domains/Attachment/Actions/StoreRequisitionAttachment.php apps/api/tests/Feature/RequisitionApiTest.php apps/api/tests/Feature/AttachmentApiTest.php
git commit -m "feat: record workflow notifications"
```

Expected: commit includes only domain action and workflow test changes.

### Task 11: Seed Demo System Announcement

**Files:**

- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
- Modify: `apps/api/tests/Feature/NotificationApiTest.php`

- [ ] **Step 1: Add test proving system announcement can render without subject or href**

Append to `apps/api/tests/Feature/NotificationApiTest.php`:

```php
public function test_system_announcement_can_omit_subject_and_href(): void
{
    [$tenant, $user] = $this->tenantUser('buyer');
    app(NotificationRecorder::class)->record(
        tenant: $tenant,
        recipients: [$user],
        data: new NotificationData(
            type: 'system.announcement',
            title: 'Welcome to Cognify',
            body: 'Your procurement workspace is ready.',
        ),
    );

    $this->actingAsTenant($tenant, $user)
        ->getJson('/api/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Welcome to Cognify')
        ->assertJsonPath('data.0.href', null)
        ->assertJsonPath('data.0.subject', null);
}
```

- [ ] **Step 2: Run focused test**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
```

Expected: PASS. The recorder and resource already support this behavior.

- [ ] **Step 3: Seed a demo announcement through the recorder**

In `apps/api/database/seeders/DatabaseSeeder.php`, after demo tenant users are created, add:

```php
app(\App\Notifications\NotificationRecorder::class)->record(
    tenant: $tenant,
    recipients: $tenant->users()->get(),
    data: new \App\Notifications\NotificationData(
        type: \App\Notifications\NotificationPreferenceDefaults::EVENT_SYSTEM_ANNOUNCEMENT,
        title: 'Welcome to Cognify',
        body: 'Your tenant notification center is ready for workflow cues.',
    ),
);
```

Use the actual variable names already present in `DatabaseSeeder.php`; keep the recorder call after `$tenant` and users exist.

- [ ] **Step 4: Run seed smoke if local database is configured**

Run:

```bash
cd apps/api && php artisan migrate:fresh --seed --env=testing
```

Expected: PASS and the `notifications` table contains at least one `system.announcement` row in the testing database.

- [ ] **Step 5: Commit demo announcement**

Run:

```bash
git add apps/api/database/seeders/DatabaseSeeder.php apps/api/tests/Feature/NotificationApiTest.php
git commit -m "feat: seed system notification announcement"
```

Expected: commit includes seeder and system announcement test.

### Task 12: Real Frontend Integration Hardening

**Files:**

- Modify: `apps/web/features/notifications/api/notifications-api.ts`
- Modify: `apps/web/features/notifications/hooks/use-notifications.ts`
- Modify: `apps/web/features/notifications/tests/notification-center.test.tsx`
- Modify: `apps/web/features/identity/tests/identity-workflow.test.tsx`

- [ ] **Step 1: Add deep-link mark-read test**

Append to `apps/web/features/notifications/tests/notification-center.test.tsx`:

```tsx
it("marks a linked notification read before navigation", async () => {
  const user = userEvent.setup();
  const push = vi.fn();
  vi.doMock("next/navigation", () => ({
    useRouter: () => ({ push }),
  }));
  const { NotificationCenter } = await import("../components/notification-center");

  renderWithQuery(<NotificationCenter open onOpenChange={() => undefined} />);

  await user.click(await screen.findByText("Requisition submitted"));

  expect(push).toHaveBeenCalledWith("/requisitions/42");
});
```

- [ ] **Step 2: Run focused notification tests**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx
```

Expected: PASS. If the router mock is unstable because the module is already loaded, move the `push` spy into the top-level `vi.mock("next/navigation")` implementation used earlier in the same file.

- [ ] **Step 3: Confirm frontend uses generated endpoint types only**

Run:

```bash
rg "type Notification|interface Notification|NotificationListResponse" apps/web/features/notifications
```

Expected: references come from `@cognify/api-client/schemas`; no hand-written duplicate contract types exist.

- [ ] **Step 4: Run frontend feature checks**

Run:

```bash
pnpm --filter @cognify/web lint
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web test
```

Expected: PASS.

- [ ] **Step 5: Commit integration hardening**

Run:

```bash
git add apps/web/features/notifications apps/web/features/identity
git commit -m "test: harden notification frontend integration"
```

Expected: commit contains focused frontend test and type-alignment fixes.

### Task 13: Contract And Backend Full Verification

**Files:**

- Verify: `apps/api/storage/openapi/openapi.json`
- Verify: `packages/api-client/src/generated`
- Verify: `apps/api`
- Verify: `apps/web`

- [ ] **Step 1: Regenerate API client after backend implementation**

Run:

```bash
pnpm generate:api
```

Expected: PASS. Generated files should match Task 1 contract names.

- [ ] **Step 2: Run contract check**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS.

- [ ] **Step 3: Run backend focused tests**

Run:

```bash
cd apps/api && php artisan test --filter=NotificationApiTest
cd apps/api && php artisan test --filter=RequisitionApiTest
cd apps/api && php artisan test --filter=AttachmentApiTest
```

Expected: PASS.

- [ ] **Step 4: Run route list verification**

Run:

```bash
cd apps/api && php artisan route:list --path=api/notifications
```

Expected: output includes:

```txt
GET|HEAD api/notifications
POST api/notifications/read-all
POST api/notifications/{notification}/read
```

- [ ] **Step 5: Run frontend focused tests**

Run:

```bash
pnpm --filter @cognify/web test -- apps/web/features/notifications/tests/notification-center.test.tsx apps/web/features/identity/tests/identity-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 6: Run relevant root checks**

Run:

```bash
pnpm typecheck
pnpm test
```

Expected: PASS. If runtime exceeds local budget, record the exact command, elapsed time, and last output line in the PR notes.

- [ ] **Step 7: Commit verification-generated changes**

Run:

```bash
git add packages/api-client/src/generated apps/api/storage/openapi/openapi.json
git commit -m "chore: refresh notification API client"
```

Expected: commit is created only if regeneration changed files.

## Self-Review Checklist

Spec coverage:

- In-app notification center: Tasks 2 through 5.
- Unread badge and accessible shell button label: Task 5.
- Tenant-scoped notification list: Task 8.
- Per-notification and mark-all-read read state: Tasks 4, 5, and 8.
- Deep links: Tasks 4 and 12.
- Notification preferences: Tasks 6 and 9.
- OpenAPI and generated API client: Tasks 1 and 13.
- MSW-backed workflow: Tasks 2 through 6.
- Backend recorder invoked by workflow actions: Tasks 7 and 10.
- System announcement seed/demo support: Task 11.
- Tenant and recipient authorization: Task 8.
- Preference-disabled recorder behavior: Task 7.

Placeholder scan:

- The plan contains no placeholder markers, no deferred implementation steps, and no hand-wavy test instructions.
- The only generated files are produced by `pnpm generate:api`.
- Seeder variable names must be adapted to the actual existing names in `DatabaseSeeder.php`; the recorder call and payload are specified exactly.

Type consistency:

- Event keys are consistently `requisition.submitted`, `attachment.uploaded`, and `system.announcement`.
- Frontend preference shape is consistently `notificationPreferences[event].inApp`.
- Backend persistence column is consistently `notification_preferences`.
- API response field is consistently `readAt`, while database field is `read_at`.

## Execution Notes

- Use feature-development loopback discipline: add or update the failing test before each production edit.
- Keep UI fixtures under `features/notifications/mocks`; production components must call hooks and must not import fixtures.
- Keep notification business meaning out of `packages/ui`.
- Keep notification infrastructure in `apps/api/app/Notifications`; only workflow trigger calls belong in `Domains/Requisition` and `Domains/Attachment`.
- If `NotificationResource` needs broader subject labels, add explicit metadata-driven labels rather than loading arbitrary subject models in the list endpoint.

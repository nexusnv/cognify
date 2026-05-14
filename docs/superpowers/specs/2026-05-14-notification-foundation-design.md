# Notification Foundation Design

Date: 2026-05-14
Status: Draft approved for planning
Epic: Notification Foundation
Release priority: P0

## 1. Purpose

This spec defines Cognify's P0 in-app notification foundation. The goal is to replace the inert app-shell notification host with a tenant-scoped notification center that gives users actionable workflow cues, read/unread state, deep links, and simple in-app preferences.

P0 intentionally avoids delivery channels such as email, digest, SMS, push, Slack, Teams, and realtime websocket updates. The design creates a durable notification read model and recorder API so those channels can be added in a future epic without rewriting workflow triggers.

## 2. Product Scope

### 2.1 In Scope

- In-app notification center opened from the existing app shell notification button.
- Unread count badge and accessible shell button label.
- Tenant-scoped notification list for the active user.
- Per-notification read state and mark-all-read action.
- Deep links to affected records when a notification is record-backed.
- Notification preferences for the three P0 event types in account settings.
- OpenAPI contract and generated `@cognify/api-client` consumption.
- MSW-backed frontend workflow before backend integration.
- Backend notification recorder invoked by existing workflow actions.

### 2.2 Initial Event Types

- `requisition.submitted`: a requester submits a requisition. Notify buyer and admin users in the active tenant.
- `attachment.uploaded`: a user uploads evidence to a requisition. Notify the requisition requester when the uploader is a different user.
- `system.announcement`: a tenant-scoped system notification used by seed/demo data and future operational notices.

### 2.3 Out Of Scope

- Email, digest, SMS, push, Slack, Teams, or webhook delivery.
- Realtime websocket updates.
- Notification scheduling, escalation, reminders, or retry workers.
- Advanced routing rules or admin-configurable recipient matrices.
- Admin broadcast composer.
- Global cross-tenant notification inbox.
- Vendor portal notifications.

## 3. Workflow

### 3.1 Workflow Notification Creation

1. A workflow action occurs, such as requisition submission.
2. The owning domain action updates business state and records its audit event.
3. The domain action asks notification infrastructure to record notifications for the target recipients.
4. The recorder applies tenant scope, recipient de-duplication, and user in-app preferences.
5. One notification row is persisted per recipient.
6. Users see the unread count and notification rows in the shell notification center.

Notifications are user-facing work cues, not audit history. Audit events remain immutable tenant history. Notifications are mutable per-recipient read models.

### 3.2 User Notification Center Workflow

1. The authenticated user clicks the shell notification button.
2. A compact notification center opens with loading, empty, populated, and error states.
3. Unread notifications are visually distinct and announced through accessible text.
4. The user can mark one notification read, mark all read, or follow a deep link.
5. Following a deep link should mark the notification read before navigation when feasible.
6. The unread badge updates through TanStack Query invalidation after read mutations.

### 3.3 Preferences Workflow

1. The user opens account settings.
2. A Notifications section shows one in-app toggle for each P0 event type.
3. The user saves profile settings.
4. Backend validates the event keys and boolean `inApp` values.
5. Future notification creation respects the saved preferences.

## 4. Architecture

### 4.1 Backend Ownership

Notification infrastructure belongs under `apps/api/app/Notifications` because it is cross-cutting SaaS infrastructure. The procurement domains own the workflow moments that trigger notifications, but not notification persistence, preference resolution, or read-state APIs.

Planned backend units:

- `apps/api/app/Notifications/NotificationRecord.php`: Eloquent model for persisted per-recipient notifications.
- `apps/api/app/Notifications/NotificationData.php`: typed data object for event type, title, body, href, subject, metadata, and priority.
- `apps/api/app/Notifications/NotificationRecorder.php`: service that resolves preferences, de-duplicates recipients, scopes by tenant, and persists records.
- `apps/api/app/Notifications/NotificationPreferenceDefaults.php`: canonical default preferences and allowed event keys.
- `apps/api/app/Notifications/Http/Controllers/NotificationController.php`: list and read-state endpoints.
- `apps/api/app/Notifications/Http/Resources/NotificationResource.php`: stable API response shape.
- `apps/api/app/Notifications/Policies/NotificationPolicy.php`: recipient and tenant ownership checks if policy registration is useful for mark-read actions.

Existing domain actions will be updated only at their workflow side-effect boundary:

- `Domains\Requisition\Actions\SubmitRequisition`
- `Domains\Attachment\Actions\StoreRequisitionAttachment`

### 4.2 Frontend Ownership

Frontend notification behavior belongs under `apps/web/features/notifications`. Shell composition stays in `apps/web/components/shell`.

Planned frontend units:

- `apps/web/components/shell/notification-host.tsx`: enabled shell entry point, unread badge, and center open state.
- `apps/web/features/notifications/api/notifications-api.ts`: generated-client wrappers with active tenant header.
- `apps/web/features/notifications/hooks/use-notifications.ts`: list query and read mutations.
- `apps/web/features/notifications/components/notification-center.tsx`: notification center UI.
- `apps/web/features/notifications/components/notification-item.tsx`: row rendering and deep-link behavior.
- `apps/web/features/notifications/components/notification-preferences-fields.tsx`: account settings toggles.
- `apps/web/features/notifications/mocks/notification-fixtures.ts`: OpenAPI-shaped fixtures.
- `apps/web/features/notifications/mocks/notification-handlers.ts`: MSW endpoints for tests.
- `apps/web/features/notifications/tests/notification-center.test.tsx`: shell and center workflow tests.
- `apps/web/features/identity/forms/profile-form.tsx`: include notification preference fields through the feature component.

Production UI components must not import mock fixtures directly.

### 4.3 Shared Package Boundaries

No Cognify-specific notification workflow code belongs in `packages/ui`. Reusable primitive components can remain in `packages/ui` if already available, but notification center composition, event labels, preferences, and deep-link behavior stay in `apps/web`.

The API contract remains in `apps/api/storage/openapi/openapi.json`, with generated TypeScript under `packages/api-client/src/generated`.

## 5. Data Model

### 5.1 Notifications Table

Create a tenant-scoped `notifications` table:

```txt
id
tenant_id
recipient_id
actor_id nullable
type
title
body nullable
href nullable
subject_type nullable
subject_id nullable
metadata json
priority default normal
read_at nullable
created_at
updated_at
```

Recommended indexes:

- `(tenant_id, recipient_id, read_at, created_at)`
- `(tenant_id, recipient_id, created_at)`
- `(tenant_id, type, created_at)`
- nullable polymorphic subject index if supported consistently by Laravel migrations.

### 5.2 User Preferences

Add `notification_preferences` JSON to `users`.

Default shape:

```json
{
  "requisition.submitted": { "inApp": true },
  "attachment.uploaded": { "inApp": true },
  "system.announcement": { "inApp": true }
}
```

Rules:

- Missing preferences fall back to defaults.
- Unknown event keys are rejected by profile update validation.
- `inApp` must be boolean.
- P0 stores only in-app preferences. Future channel keys can be added without changing the event key structure.

## 6. Recipient Rules

### 6.1 Requisition Submitted

When a draft requisition is submitted:

- Notify tenant users with role `buyer`.
- Notify tenant users with role `admin`.
- Do not notify the actor just because they submitted.
- Use `href: /requisitions/{id}`.
- Suggested title: `Requisition submitted`.
- Suggested body: `{number} is ready for procurement review.`

### 6.2 Attachment Uploaded

When evidence is uploaded to a requisition:

- Notify the requisition requester if the uploader is not the requester.
- Skip notification if the requester uploaded their own attachment.
- Use `href: /requisitions/{id}`.
- Suggested title: `Evidence uploaded`.
- Suggested body: `{filename} was added to {number}.`

### 6.3 System Announcement

System announcements are tenant-scoped records created by seed/demo data in P0:

- Recipient targeting is explicit in seed/demo creation.
- They may omit `subject_type`, `subject_id`, and `href`.
- Suggested title/body come from seed data.
- They prove non-record notifications can render without special UI branches.

## 7. API Contract

### 7.1 List Notifications

`GET /api/notifications?status=&limit=`

Parameters:

- `status`: optional enum `all`, `unread`, `read`; default `all`.
- `limit`: optional integer with server validation, recommended `1..50`; default `20`.

Response:

```json
{
  "data": [
    {
      "id": "101",
      "type": "requisition.submitted",
      "title": "Requisition submitted",
      "body": "REQ-2026-000042 is ready for procurement review.",
      "href": "/requisitions/42",
      "priority": "normal",
      "readAt": null,
      "createdAt": "2026-05-14T10:30:00Z",
      "actor": {
        "id": "7",
        "name": "Maya Tan"
      },
      "subject": {
        "type": "requisition",
        "id": "42",
        "label": "REQ-2026-000042"
      },
      "metadata": {
        "number": "REQ-2026-000042"
      }
    }
  ],
  "meta": {
    "unreadCount": 3,
    "returned": 1,
    "status": "all"
  }
}
```

### 7.2 Mark One Read

`POST /api/notifications/{notification}/read`

Behavior:

- Requires active tenant context.
- Only the recipient can mark their notification read.
- Returns the updated notification resource.
- Repeated calls are idempotent and keep the original `readAt`.

### 7.3 Mark All Read

`POST /api/notifications/read-all`

Behavior:

- Marks all unread notifications for the current user in the active tenant.
- Returns `{ "data": { "marked": 3 }, "meta": { "unreadCount": 0 } }`.

### 7.4 Profile Preferences

`GET /api/me` includes `user.notificationPreferences`.

`PATCH /api/me/profile` accepts `notificationPreferences` with the default shape shown above. Existing profile fields remain required as they are today.

## 8. Frontend UX Requirements

### 8.1 Shell Notification Host

The shell host:

- Uses the existing bell button location.
- Is enabled only after authenticated identity context is available.
- Shows an unread badge when `unreadCount > 0`.
- Uses an accessible label such as `Open notifications, 3 unread`.
- Opens a compact center using existing Radix/shadcn primitives where available.

### 8.2 Notification Center

The center should be quiet, dense, and operational:

- Header with title `Notifications` and a mark-all-read action.
- Loading state while notifications load.
- Empty state when there are no notifications for the selected status.
- Error state with retry affordance.
- Rows show title, body, created time, unread marker, and optional actor/subject context.
- Rows with `href` navigate through Next router.
- Rows without `href` can still be marked read.

P0 does not need filters or tabs unless implementation complexity is low. The list endpoint supports `status` so a future unread filter can be added without backend redesign.

### 8.3 Account Settings Preferences

Account settings gains a Notifications section:

- `Requisition submitted`
- `Evidence uploaded`
- `System announcements`

Each control is a simple in-app toggle. No email or digest labels should appear in P0 because those channels are not implemented.

## 9. Security And Compliance

- Every notification is tenant-scoped.
- Users can list and mutate only their own notifications for the active tenant.
- Notification `href` must point only to routes the recipient can normally access.
- Notification creation must not leak cross-tenant record existence.
- Preference validation rejects unknown event keys and malformed channel settings.
- Notification APIs use the existing normalized error envelope.
- Notification records are mutable only for read state; event type, title, subject, and tenant fields are not user-editable.
- Audit events remain the compliance history. Notifications do not replace audit trails.

## 10. Error Handling And Consistency

Workflow-triggered notifications should be recorded in the same database transaction as the workflow state and audit side effect for P0. This keeps tests deterministic and avoids a submitted requisition without its expected in-app notification.

Preference-disabled events are skipped silently. No error should be shown to the workflow actor because another recipient disabled a notification type.

Mark-read behavior is idempotent. Calling mark-read on an already-read notification returns success with the existing `readAt`.

Authorization failure for another user or tenant's notification should return `404` to avoid leaking notification existence, matching adjacent tenant-sensitive record behavior such as requisitions and attachments.

## 11. Testing Strategy

### 11.1 Backend Tests

Feature tests should cover:

- Requisition submission creates notifications for buyer/admin tenant users.
- Requisition submission does not create requester self-notifications.
- Attachment upload notifies the requester when uploader differs.
- Attachment upload skips notification when uploader is the requester.
- Disabled `inApp` preference prevents notification creation.
- Notification list is tenant-scoped and recipient-scoped.
- `status=unread` and `status=read` filters work.
- Mark-one-read sets `read_at` and is idempotent.
- Mark-all-read marks only current user and current tenant notifications.
- Read endpoint rejects another user's notification.
- Profile update validates and persists notification preferences.

### 11.2 Frontend Tests

Frontend tests should cover:

- Shell bell renders unread count and accessible label.
- Notification center opens from the shell button.
- Loading, empty, populated, and error states render.
- Clicking a notification with `href` marks it read and navigates.
- Mark-all-read clears the unread badge.
- Account settings renders the three preference toggles.
- Saving account settings sends OpenAPI-shaped preferences.
- MSW handlers return generated-contract-shaped responses.

### 11.3 Contract Verification

Contract checks should cover:

- OpenAPI includes notification endpoints, schemas, errors, and profile preference shape.
- `packages/api-client` is regenerated.
- Frontend notification hooks consume generated endpoint and schema types.
- MSW fixtures use generated types or feature aliases over generated types.

## 12. Runbook And Architecture Alignment

This design follows `docs/05-runbooks/feature-development.md`:

1. Workflow-first: start from workflow side effects and user notification-center behavior.
2. Contract-first: define notification and profile preference API shapes before hardening backend behavior.
3. Mocked frontend workflow: MSW-backed notification center and preferences before real integration.
4. Backend domain behavior: domain actions emit notification intents while `app/Notifications` owns infrastructure.
5. Real API integration: regenerate and consume `@cognify/api-client`.
6. Hardening: tenant isolation, recipient authorization, preference validation, read-state idempotency, and state tests.

This design follows the greenfield architecture spec:

- Cross-cutting notification infrastructure lives in `apps/api/app/Notifications`.
- Procurement workflow state remains in `apps/api/Domains/*`.
- App-specific notification UI lives in `apps/web/features/notifications` and `apps/web/components/shell`.
- No notification business behavior is added to `packages/ui`.
- OpenAPI and Orval remain the contract boundary.

## 13. Slice Plan

Slice 1: Notification center and preferences contract

- Add OpenAPI schemas/endpoints for notifications and profile preferences.
- Add MSW fixtures/handlers and generated-client wrappers.
- Replace inert shell host with notification center UI.
- Extend account settings with notification preference toggles.
- Cover UI loading, empty, populated, error, deep-link, read-state, and preference behavior.

Slice 2: Backend notification infrastructure and workflow triggers

- Add `notifications` table and `users.notification_preferences`.
- Add notification model, recorder, resource, controller, and validation.
- Invoke recorder from requisition submission and attachment upload actions.
- Add seed/demo system announcement support.
- Cover tenant, recipient, read-state, preference, and workflow trigger tests.

Slice 3: Integration hardening

- Regenerate API client and wire frontend hooks to real endpoints.
- Verify profile settings persists preferences through the real API.
- Verify notification center reflects backend read state and unread count.
- Run backend, frontend, and contract checks from the feature-development runbook.

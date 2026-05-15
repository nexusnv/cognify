# Requisition Submission And Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver P1 Epic 2 so submitted requisitions become role-aware work queue records with correction, withdrawal, cancellation, comments, mentions, audit, and in-app notifications.

**Architecture:** Extend the existing requisition foundation without replacing it. Requisition lifecycle state and transitions stay in `apps/api/Domains/Requisition`, reusable comments and mentions live in a new `apps/api/Domains/Collaboration` domain, OpenAPI remains the contract source, `@cognify/api-client` is regenerated for web consumers, and Cognify-specific UI remains under `apps/web/features/requisitions`.

**Tech Stack:** Laravel, Sanctum, Eloquent migrations, OpenAPI/Orval, Next.js App Router, React 19, TanStack Query, MSW, Vitest, Testing Library.

---

## Source Documents

- Design spec: `docs/superpowers/specs/2026-05-15-requisition-submission-workspace-design.md`
- Runbook: `docs/05-runbooks/feature-development.md`
- Architecture: `ARCHITECTURE.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Epic source: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Agent guide: `AGENTS.md`

## Locked Implementation Decisions

- Keep Epic 2 requisition-native for lifecycle and workspace behavior.
- Add `changes_requested`, `withdrawn`, and `cancelled`; do not activate `pending_approval`.
- Treat `withdrawn` and `cancelled` as immutable terminal states.
- Let requesters edit all draft fields while in `changes_requested`.
- Extend `GET /api/requisitions` for role-aware queue behavior; do not add a separate queue endpoint.
- Keep detail, activity, attachments, and comments as separate endpoints; do not add an aggregate workspace endpoint.
- Create a generic `apps/api/Domains/Collaboration` domain, but expose only requisition-scoped comments in this epic.
- Mentions only target users who already have permission to view the requisition. Mentions never grant access.
- Keep notifications in-app only.
- Keep `packages/ui` primitive-only.

## File Map

### Backend Requisition

- Create: `apps/api/database/migrations/2026_05_15_030000_add_submission_workspace_fields_to_requisitions_table.php`
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify: `apps/api/Domains/Requisition/States/RequisitionStatus.php`
- Modify: `apps/api/Domains/Requisition/Policies/RequisitionPolicy.php`
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`
- Create: `apps/api/Domains/Requisition/Http/Requests/RequestRequisitionChangesRequest.php`
- Create: `apps/api/Domains/Requisition/Http/Requests/ReasonedRequisitionActionRequest.php`
- Create: `apps/api/Domains/Requisition/Actions/RequestRequisitionChanges.php`
- Create: `apps/api/Domains/Requisition/Actions/ResubmitRequisition.php`
- Create: `apps/api/Domains/Requisition/Actions/WithdrawRequisition.php`
- Create: `apps/api/Domains/Requisition/Actions/CancelRequisition.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php`

### Backend Collaboration

- Create: `apps/api/database/migrations/2026_05_15_031000_create_collaboration_tables.php`
- Create: `apps/api/Domains/Collaboration/Models/CollaborationComment.php`
- Create: `apps/api/Domains/Collaboration/Models/CollaborationMention.php`
- Create: `apps/api/Domains/Collaboration/Actions/CreateCollaborationComment.php`
- Create: `apps/api/Domains/Collaboration/Http/Controllers/RequisitionCommentController.php`
- Create: `apps/api/Domains/Collaboration/Http/Requests/CreateCollaborationCommentRequest.php`
- Create: `apps/api/Domains/Collaboration/Http/Resources/CollaborationCommentResource.php`
- Create: `apps/api/tests/Feature/CollaborationApiTest.php`

### Backend Notifications And Audit

- Modify: `apps/api/app/Notifications/NotificationPreferenceDefaults.php`
- No new audit infrastructure files; use `App\Audit\AuditRecorder` and `App\Audit\AuditEventData` from domain actions.

### Contract And Client

- Modify: `apps/api/storage/openapi/openapi.json`
- Regenerate: `packages/api-client/src/generated/*`
- Read: `packages/api-client/src/index.ts` to confirm existing generated exports remain surfaced.

### Frontend Requisitions

- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`
- Modify: `apps/web/features/requisitions/hooks/use-requisitions.ts`
- Modify: `apps/web/features/requisitions/hooks/use-requisition.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-actions.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-comments.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`
- Modify: `apps/web/features/requisitions/workflows/requisition-list-page.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-create-page.tsx`
- Modify: `apps/web/app/(workspace)/requisitions/[requisitionId]/edit/page.tsx`
- Modify: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Modify: `apps/web/features/requisitions/components/requisition-status-badge.tsx`
- Modify: `apps/web/features/requisitions/components/requisition-activity-timeline.tsx`
- Create: `apps/web/features/requisitions/components/requisition-action-dialog.tsx`
- Create: `apps/web/features/requisitions/components/requisition-correction-panel.tsx`
- Create: `apps/web/features/requisitions/components/requisition-comments.tsx`
- Create: `apps/web/features/requisitions/components/requisition-mention-input.tsx`
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`
- Create: `apps/web/features/requisitions/tests/requisition-comments.test.tsx`

---

## Task 1: Baseline Safety And Architecture Check

**Files:**
- Read: `AGENTS.md`
- Read: `ARCHITECTURE.md`
- Read: `docs/05-runbooks/feature-development.md`
- Read: `docs/superpowers/specs/2026-05-15-requisition-submission-workspace-design.md`

- [ ] **Step 1: Check branch and worktree**

Run:

```bash
git status --short --branch
```

Expected: Current branch and uncommitted files are visible. Do not overwrite unrelated user changes.

- [ ] **Step 2: Run current focused backend baseline**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionApiTest
```

Expected: Existing requisition API tests pass before Epic 2 changes. If this fails before edits, stop and use `superpowers:systematic-debugging`.

- [ ] **Step 3: Run current focused frontend baseline**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx
```

Expected: Existing requisition workflow tests pass before Epic 2 changes. If this fails before edits, stop and use `superpowers:systematic-debugging`.

- [ ] **Step 4: Confirm no files changed**

Run:

```bash
git status --short
```

Expected: No implementation files changed in this task.

---

## Task 2: OpenAPI Contract For Epic 2

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

- [ ] **Step 1: Extend `RequisitionStatus` enum in OpenAPI**

In `apps/api/storage/openapi/openapi.json`, update `components.schemas.RequisitionStatus.enum` to include:

```json
[
  "draft",
  "submitted",
  "pending_approval",
  "changes_requested",
  "withdrawn",
  "cancelled"
]
```

Expected: `pending_approval` remains present but no endpoint in this plan transitions to it.

- [ ] **Step 2: Extend `RequisitionPermissions` schema**

Update `components.schemas.RequisitionPermissions.required` and `properties` to include:

```json
[
  "canUpdate",
  "canSubmit",
  "canResubmit",
  "canRequestChanges",
  "canWithdraw",
  "canCancel",
  "canComment",
  "canMention",
  "canViewActivity"
]
```

Each new property is:

```json
{ "type": "boolean" }
```

- [ ] **Step 3: Extend `Requisition` schema for change and terminal metadata**

Add these nullable properties to `components.schemas.Requisition.properties` and include them in the required list:

```json
"changesRequestedAt": { "type": ["string", "null"], "format": "date-time" },
"changesRequestedBy": { "oneOf": [{ "$ref": "#/components/schemas/UserSummary" }, { "type": "null" }] },
"changeRequestReason": { "type": ["string", "null"] },
"changeRequestFields": {
  "type": "array",
  "items": { "type": "string" }
},
"withdrawnAt": { "type": ["string", "null"], "format": "date-time" },
"withdrawnBy": { "oneOf": [{ "$ref": "#/components/schemas/UserSummary" }, { "type": "null" }] },
"withdrawalReason": { "type": ["string", "null"] },
"cancelledAt": { "type": ["string", "null"], "format": "date-time" },
"cancelledBy": { "oneOf": [{ "$ref": "#/components/schemas/UserSummary" }, { "type": "null" }] },
"cancellationReason": { "type": ["string", "null"] }
```

Use `[]` for `changeRequestFields` when there is no active change request.

- [ ] **Step 4: Add request schemas for action endpoints**

Add these schemas under `components.schemas`:

```json
"RequestRequisitionChangesRequest": {
  "type": "object",
  "required": ["reason"],
  "properties": {
    "reason": { "type": "string", "minLength": 1, "maxLength": 2000 },
    "requestedFields": {
      "type": "array",
      "items": { "type": "string", "maxLength": 80 },
      "default": []
    }
  }
},
"ReasonedRequisitionActionRequest": {
  "type": "object",
  "required": ["reason"],
  "properties": {
    "reason": { "type": "string", "minLength": 1, "maxLength": 2000 }
  }
}
```

- [ ] **Step 5: Add action endpoint paths**

Add paths:

```json
"/api/requisitions/{requisitionId}/request-changes": {
  "post": {
    "operationId": "requestRequisitionChanges",
    "summary": "Request changes for a submitted requisition",
    "parameters": [{ "$ref": "#/components/parameters/RequisitionId" }],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/RequestRequisitionChangesRequest" }
        }
      }
    },
    "responses": {
      "200": {
        "description": "Changes requested",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/RequisitionResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
}
```

Repeat the same response shape for:

- `POST /api/requisitions/{requisitionId}/resubmit` with `operationId: "resubmitRequisition"` and no request body.
- `POST /api/requisitions/{requisitionId}/withdraw` with `operationId: "withdrawRequisition"` and `ReasonedRequisitionActionRequest`.
- `POST /api/requisitions/{requisitionId}/cancel` with `operationId: "cancelRequisition"` and `ReasonedRequisitionActionRequest`.

- [ ] **Step 6: Add collaboration schemas**

Add:

```json
"CollaborationMention": {
  "type": "object",
  "required": ["id", "mentionedUser"],
  "properties": {
    "id": { "type": "string" },
    "mentionedUser": { "$ref": "#/components/schemas/UserSummary" }
  }
},
"CollaborationComment": {
  "type": "object",
  "required": ["id", "subjectType", "subjectId", "author", "body", "mentions", "createdAt", "updatedAt"],
  "properties": {
    "id": { "type": "string" },
    "subjectType": { "type": "string", "enum": ["requisition"] },
    "subjectId": { "type": "string" },
    "author": { "$ref": "#/components/schemas/UserSummary" },
    "body": { "type": "string" },
    "mentions": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/CollaborationMention" }
    },
    "createdAt": { "type": "string", "format": "date-time" },
    "updatedAt": { "type": "string", "format": "date-time" }
  }
},
"CollaborationCommentListResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/CollaborationComment" }
    }
  }
},
"CreateCollaborationCommentRequest": {
  "type": "object",
  "required": ["body"],
  "properties": {
    "body": { "type": "string", "minLength": 1, "maxLength": 5000 },
    "mentionedUserIds": {
      "type": "array",
      "items": { "type": "string" },
      "default": []
    }
  }
},
"CollaborationCommentResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/CollaborationComment" }
  }
},
"CollaborationMentionCandidateListResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/UserSummary" }
    }
  }
}
```

- [ ] **Step 7: Add collaboration endpoint paths**

Add:

```json
"/api/requisitions/{requisitionId}/comments": {
  "get": {
    "operationId": "listRequisitionComments",
    "summary": "List comments for a requisition",
    "parameters": [{ "$ref": "#/components/parameters/RequisitionId" }],
    "responses": {
      "200": {
        "description": "Requisition comments",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CollaborationCommentListResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  },
  "post": {
    "operationId": "createRequisitionComment",
    "summary": "Create a comment on a requisition",
    "parameters": [{ "$ref": "#/components/parameters/RequisitionId" }],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/CreateCollaborationCommentRequest" }
        }
      }
    },
    "responses": {
      "201": {
        "description": "Comment created",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CollaborationCommentResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
}
```

Add:

```json
"/api/requisitions/{requisitionId}/mention-candidates": {
  "get": {
    "operationId": "listRequisitionMentionCandidates",
    "summary": "List users who can be mentioned on a requisition",
    "parameters": [{ "$ref": "#/components/parameters/RequisitionId" }],
    "responses": {
      "200": {
        "description": "Mention candidates",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/CollaborationMentionCandidateListResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  }
}
```

- [ ] **Step 8: Extend requisition list parameters**

In `GET /api/requisitions`, add query parameters:

```json
{ "name": "requester", "in": "query", "schema": { "type": "string" } },
{ "name": "department", "in": "query", "schema": { "type": "string" } },
{ "name": "amountMin", "in": "query", "schema": { "type": "number", "minimum": 0 } },
{ "name": "amountMax", "in": "query", "schema": { "type": "number", "minimum": 0 } },
{ "name": "updatedFrom", "in": "query", "schema": { "type": "string", "format": "date" } },
{ "name": "updatedTo", "in": "query", "schema": { "type": "string", "format": "date" } },
{ "name": "queuePreset", "in": "query", "schema": { "type": "string", "enum": ["my_drafts", "submitted", "needs_my_correction", "buyer_review", "stopped", "all_visible"] } }
```

Keep the existing `owner` parameter for backwards compatibility and map it to requester filtering until a separate owner field exists.

- [ ] **Step 9: Validate JSON syntax**

Run:

```bash
node -e "JSON.parse(require('fs').readFileSync('apps/api/storage/openapi/openapi.json','utf8')); console.log('openapi json ok')"
```

Expected: Prints `openapi json ok`.

- [ ] **Step 10: Commit contract changes**

Run:

```bash
git add apps/api/storage/openapi/openapi.json
git commit -m "docs: define requisition workspace api contract"
```

Expected: Commit contains only OpenAPI contract changes.

---

## Task 3: Generate API Client And Add Web Contract Types

**Files:**
- Regenerate: `packages/api-client/src/generated/*`
- Modify: `apps/web/features/requisitions/types/requisition-view-model.ts`
- Modify: `apps/web/features/requisitions/api/requisitions-api.ts`

- [ ] **Step 1: Regenerate generated API client**

Run:

```bash
pnpm generate:api
```

Expected: `packages/api-client/src/generated/*` updates with Epic 2 endpoints and schemas.

- [ ] **Step 2: Verify generated contract drift**

Run:

```bash
pnpm check:api-contract
```

Expected: Passes and leaves generated files aligned with `apps/api/storage/openapi/openapi.json`.

- [ ] **Step 3: Extend requisition view model types**

In `apps/web/features/requisitions/types/requisition-view-model.ts`, update `RequisitionStatus`:

```ts
export type RequisitionStatus =
  | "draft"
  | "submitted"
  | "pending_approval"
  | "changes_requested"
  | "withdrawn"
  | "cancelled";
```

Update `RequisitionPermissions`:

```ts
export type RequisitionPermissions = {
  canUpdate: boolean;
  canSubmit: boolean;
  canResubmit: boolean;
  canRequestChanges: boolean;
  canWithdraw: boolean;
  canCancel: boolean;
  canComment: boolean;
  canMention: boolean;
  canViewActivity: boolean;
};
```

Add:

```ts
export type RequisitionQueuePreset =
  | "my_drafts"
  | "submitted"
  | "needs_my_correction"
  | "buyer_review"
  | "stopped"
  | "all_visible";

export type CollaborationMention = {
  id: string;
  mentionedUser: UserSummary;
};

export type CollaborationComment = {
  id: string;
  subjectType: "requisition";
  subjectId: string;
  author: UserSummary;
  body: string;
  mentions: CollaborationMention[];
  createdAt: string;
  updatedAt: string;
};
```

Extend `Requisition` with:

```ts
changesRequestedAt?: string | null;
changesRequestedBy?: UserSummary | null;
changeRequestReason?: string | null;
changeRequestFields: string[];
withdrawnAt?: string | null;
withdrawnBy?: UserSummary | null;
withdrawalReason?: string | null;
cancelledAt?: string | null;
cancelledBy?: UserSummary | null;
cancellationReason?: string | null;
```

- [ ] **Step 4: Extend API wrapper imports**

In `apps/web/features/requisitions/api/requisitions-api.ts`, import generated endpoints:

```ts
import {
  cancelRequisition as cancelRequisitionEndpoint,
  createRequisitionComment as createRequisitionCommentEndpoint,
  listRequisitionComments as listRequisitionCommentsEndpoint,
  listRequisitionMentionCandidates as listRequisitionMentionCandidatesEndpoint,
  requestRequisitionChanges as requestRequisitionChangesEndpoint,
  resubmitRequisition as resubmitRequisitionEndpoint,
  withdrawRequisition as withdrawRequisitionEndpoint,
} from "@cognify/api-client/endpoints";
```

These names come from the OpenAPI operation IDs defined in Task 2. Keep the local wrapper names below unchanged.

- [ ] **Step 5: Add action wrapper functions**

In `requisitions-api.ts`, add:

```ts
export async function requestRequisitionChanges(
  requisitionId: string,
  values: { reason: string; requestedFields: string[] },
) {
  const response = await requestRequisitionChangesEndpoint(
    requisitionId,
    values,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return { data: mapRequisition(response.data.data) };
}

export async function resubmitRequisition(requisitionId: string) {
  const response = await resubmitRequisitionEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return { data: mapRequisition(response.data.data) };
}

export async function withdrawRequisition(requisitionId: string, values: { reason: string }) {
  const response = await withdrawRequisitionEndpoint(
    requisitionId,
    values,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return { data: mapRequisition(response.data.data) };
}

export async function cancelRequisition(requisitionId: string, values: { reason: string }) {
  const response = await cancelRequisitionEndpoint(
    requisitionId,
    values,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return { data: mapRequisition(response.data.data) };
}
```

- [ ] **Step 6: Add comments wrapper functions**

In `requisitions-api.ts`, add:

```ts
export async function listRequisitionComments(requisitionId: string) {
  const response = await listRequisitionCommentsEndpoint(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data;
}

export async function createRequisitionComment(
  requisitionId: string,
  values: { body: string; mentionedUserIds: string[] },
) {
  const response = await createRequisitionCommentEndpoint(
    requisitionId,
    values,
    withActiveTenantHeader(),
  );
  if (response.status !== 201) throw response.data;
  return response.data.data;
}

export async function listRequisitionMentionCandidates(requisitionId: string) {
  const response = await listRequisitionMentionCandidatesEndpoint(
    requisitionId,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return response.data.data.map((user) => ({
    id: user.id,
    name: user.name,
    email: user.email ?? "",
  }));
}
```

- [ ] **Step 7: Map new requisition fields**

In `mapRequisition()`, add:

```ts
changesRequestedAt: requisition.changesRequestedAt ?? undefined,
changesRequestedBy: requisition.changesRequestedBy
  ? {
      id: requisition.changesRequestedBy.id,
      name: requisition.changesRequestedBy.name,
      email: requisition.changesRequestedBy.email ?? "",
    }
  : null,
changeRequestReason: requisition.changeRequestReason ?? undefined,
changeRequestFields: requisition.changeRequestFields ?? [],
withdrawnAt: requisition.withdrawnAt ?? undefined,
withdrawnBy: requisition.withdrawnBy
  ? {
      id: requisition.withdrawnBy.id,
      name: requisition.withdrawnBy.name,
      email: requisition.withdrawnBy.email ?? "",
    }
  : null,
withdrawalReason: requisition.withdrawalReason ?? undefined,
cancelledAt: requisition.cancelledAt ?? undefined,
cancelledBy: requisition.cancelledBy
  ? {
      id: requisition.cancelledBy.id,
      name: requisition.cancelledBy.name,
      email: requisition.cancelledBy.email ?? "",
    }
  : null,
cancellationReason: requisition.cancellationReason ?? undefined,
```

- [ ] **Step 8: Run typecheck for contract consumers**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: Typecheck passes or exposes generated naming mismatches to fix in this task.

- [ ] **Step 9: Commit generated client and web type wrappers**

Run:

```bash
git add packages/api-client/src/generated apps/web/features/requisitions/types/requisition-view-model.ts apps/web/features/requisitions/api/requisitions-api.ts
git commit -m "feat: add requisition workspace client contract"
```

Expected: Commit contains generated client updates and web wrapper/type changes.

---

## Task 4: Backend Requisition Lifecycle Tests

**Files:**
- Modify: `apps/api/tests/Feature/RequisitionApiTest.php`

- [ ] **Step 1: Add test for buyer requesting changes**

Add to `RequisitionApiTest`:

```php
public function test_buyer_can_request_changes_on_submitted_requisition(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

    $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/requisitions/{$requisition->id}/request-changes", [
            'reason' => 'Please clarify the delivery location and line item quantity.',
            'requestedFields' => ['deliveryLocation', 'lineItems'],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'changes_requested')
        ->assertJsonPath('data.changeRequestReason', 'Please clarify the delivery location and line item quantity.')
        ->assertJsonPath('data.changeRequestFields.0', 'deliveryLocation')
        ->assertJsonPath('data.permissions.canRequestChanges', false);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_id' => $buyer->id,
        'event_type' => 'requisition.changes_requested',
    ]);

    $this->assertDatabaseHas('notifications', [
        'tenant_id' => $tenant->id,
        'recipient_id' => $requester->id,
        'actor_id' => $buyer->id,
        'type' => 'requisition.changes_requested',
        'href' => "/requisitions/{$requisition->id}",
    ]);
}
```

- [ ] **Step 2: Add test for requester correction and resubmission**

Add:

```php
public function test_requester_can_edit_change_requested_requisition_and_resubmit(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $requisition = $this->createDraft($tenant, $requester, [
        'status' => RequisitionStatus::ChangesRequested,
        'change_request_reason' => 'Clarify line items.',
        'change_request_fields' => ['lineItems'],
        'changes_requested_by_id' => $buyer->id,
        'changes_requested_at' => now(),
    ]);

    $this->actingAsTenant($tenant, $requester)
        ->patchJson("/api/requisitions/{$requisition->id}", [
            'lockVersion' => 0,
            'title' => 'Updated after change request',
            'businessJustification' => 'Updated justification for resubmission.',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated after change request');

    $this->actingAsTenant($tenant, $requester)
        ->postJson("/api/requisitions/{$requisition->id}/resubmit")
        ->assertOk()
        ->assertJsonPath('data.status', RequisitionStatus::Submitted->value)
        ->assertJsonPath('data.changeRequestReason', null)
        ->assertJsonPath('data.changeRequestFields', []);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_id' => $requester->id,
        'event_type' => 'requisition.resubmitted',
    ]);
}
```

- [ ] **Step 3: Add test for requester withdrawal**

Add:

```php
public function test_requester_can_withdraw_submitted_requisition_with_reason(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

    $this->actingAsTenant($tenant, $requester)
        ->postJson("/api/requisitions/{$requisition->id}/withdraw", [
            'reason' => 'Budget moved to a different project.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'withdrawn')
        ->assertJsonPath('data.withdrawalReason', 'Budget moved to a different project.')
        ->assertJsonPath('data.permissions.canSubmit', false)
        ->assertJsonPath('data.permissions.canWithdraw', false);

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_id' => $requester->id,
        'event_type' => 'requisition.withdrawn',
    ]);
}
```

- [ ] **Step 4: Add test for admin cancellation**

Add:

```php
public function test_admin_can_cancel_submitted_requisition_with_reason(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $admin] = $this->tenantUser('admin', $tenant);
    $requisition = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Submitted]);

    $this->actingAsTenant($tenant, $admin)
        ->postJson("/api/requisitions/{$requisition->id}/cancel", [
            'reason' => 'Duplicate request already approved outside this record.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellationReason', 'Duplicate request already approved outside this record.');

    $this->assertDatabaseHas('audit_events', [
        'tenant_id' => $tenant->id,
        'actor_id' => $admin->id,
        'event_type' => 'requisition.cancelled',
    ]);
}
```

- [ ] **Step 5: Add test for terminal states rejecting transitions**

Add:

```php
public function test_terminal_requisitions_reject_workflow_actions_and_updates(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $admin] = $this->tenantUser('admin', $tenant);
    $withdrawn = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Withdrawn]);
    $cancelled = $this->createDraft($tenant, $requester, ['status' => RequisitionStatus::Cancelled]);

    $this->actingAsTenant($tenant, $requester)
        ->postJson("/api/requisitions/{$withdrawn->id}/submit")
        ->assertStatus(409);

    $this->actingAsTenant($tenant, $requester)
        ->postJson("/api/requisitions/{$withdrawn->id}/resubmit")
        ->assertStatus(409);

    $this->actingAsTenant($tenant, $requester)
        ->patchJson("/api/requisitions/{$withdrawn->id}", [
            'lockVersion' => 0,
            'title' => 'Should not change',
        ])
        ->assertStatus(409);

    $this->actingAsTenant($tenant, $admin)
        ->postJson("/api/requisitions/{$cancelled->id}/cancel", [
            'reason' => 'Already cancelled.',
        ])
        ->assertStatus(409);
}
```

- [ ] **Step 6: Update imports and helper references**

If PHP reports missing enum cases, keep the test code as written and implement the enum cases in Task 5. Do not rename the statuses.

- [ ] **Step 7: Run tests to verify expected failures**

Run:

```bash
cd apps/api && php artisan test --filter='buyer_can_request_changes|requester_can_edit_change_requested|requester_can_withdraw|admin_can_cancel|terminal_requisitions'
```

Expected: Fails because routes, statuses, fields, and actions are not implemented yet.

- [ ] **Step 8: Commit failing tests**

Run:

```bash
git add apps/api/tests/Feature/RequisitionApiTest.php
git commit -m "test: cover requisition workspace transitions"
```

Expected: Commit contains only backend lifecycle tests.

---

## Task 5: Backend Requisition Lifecycle Implementation

**Files:**
- Create: `apps/api/database/migrations/2026_05_15_030000_add_submission_workspace_fields_to_requisitions_table.php`
- Modify: `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify: `apps/api/Domains/Requisition/States/RequisitionStatus.php`
- Modify: `apps/api/Domains/Requisition/Policies/RequisitionPolicy.php`
- Modify: `apps/api/Domains/Requisition/Http/Resources/RequisitionResource.php`
- Create: `apps/api/Domains/Requisition/Http/Requests/RequestRequisitionChangesRequest.php`
- Create: `apps/api/Domains/Requisition/Http/Requests/ReasonedRequisitionActionRequest.php`
- Create: `apps/api/Domains/Requisition/Actions/RequestRequisitionChanges.php`
- Create: `apps/api/Domains/Requisition/Actions/ResubmitRequisition.php`
- Create: `apps/api/Domains/Requisition/Actions/WithdrawRequisition.php`
- Create: `apps/api/Domains/Requisition/Actions/CancelRequisition.php`
- Modify: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionController.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/app/Notifications/NotificationPreferenceDefaults.php`

- [ ] **Step 1: Add additive migration for submission workspace fields**

Create `apps/api/database/migrations/2026_05_15_030000_add_submission_workspace_fields_to_requisitions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->timestamp('changes_requested_at')->nullable()->after('submitted_at');
            $table->foreignId('changes_requested_by_id')->nullable()->after('changes_requested_at')->constrained('users')->nullOnDelete();
            $table->text('change_request_reason')->nullable()->after('changes_requested_by_id');
            $table->json('change_request_fields')->nullable()->after('change_request_reason');
            $table->timestamp('withdrawn_at')->nullable()->after('change_request_fields');
            $table->foreignId('withdrawn_by_id')->nullable()->after('withdrawn_at')->constrained('users')->nullOnDelete();
            $table->text('withdrawal_reason')->nullable()->after('withdrawn_by_id');
            $table->timestamp('cancelled_at')->nullable()->after('withdrawal_reason');
            $table->foreignId('cancelled_by_id')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_id');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_id');
            $table->dropConstrainedForeignId('withdrawn_by_id');
            $table->dropConstrainedForeignId('changes_requested_by_id');
            $table->dropColumn([
                'changes_requested_at',
                'change_request_reason',
                'change_request_fields',
                'withdrawn_at',
                'withdrawal_reason',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
```

- [ ] **Step 2: Extend requisition model**

In `apps/api/Domains/Requisition/Models/Requisition.php`, add fillable fields:

```php
'changes_requested_at',
'changes_requested_by_id',
'change_request_reason',
'change_request_fields',
'withdrawn_at',
'withdrawn_by_id',
'withdrawal_reason',
'cancelled_at',
'cancelled_by_id',
'cancellation_reason',
```

Add casts:

```php
'changes_requested_at' => 'datetime',
'change_request_fields' => 'array',
'withdrawn_at' => 'datetime',
'cancelled_at' => 'datetime',
```

Add relationships:

```php
public function changesRequestedBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'changes_requested_by_id');
}

public function withdrawnBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'withdrawn_by_id');
}

public function cancelledBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'cancelled_by_id');
}
```

- [ ] **Step 3: Extend requisition status enum**

In `apps/api/Domains/Requisition/States/RequisitionStatus.php`, add:

```php
case ChangesRequested = 'changes_requested';
case Withdrawn = 'withdrawn';
case Cancelled = 'cancelled';
```

Keep existing cases unchanged.

- [ ] **Step 4: Add request classes**

Create `apps/api/Domains/Requisition/Http/Requests/RequestRequisitionChangesRequest.php`:

```php
<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestRequisitionChangesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'requestedFields' => ['sometimes', 'array'],
            'requestedFields.*' => ['string', 'max:80'],
        ];
    }
}
```

Create `apps/api/Domains/Requisition/Http/Requests/ReasonedRequisitionActionRequest.php`:

```php
<?php

namespace Domains\Requisition\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReasonedRequisitionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 5: Extend policy**

In `RequisitionPolicy`, add:

```php
public function requestChanges(User $user, Requisition $requisition): bool
{
    $role = app(CurrentTenant::class)->roleFor($user);

    return $requisition->status === RequisitionStatus::Submitted
        && in_array($role, [TenantRole::Buyer->value, TenantRole::Approver->value, TenantRole::Admin->value], true);
}

public function resubmit(User $user, Requisition $requisition): bool
{
    return $requisition->status === RequisitionStatus::ChangesRequested
        && ($requisition->requester_id === $user->id || app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value);
}

public function withdraw(User $user, Requisition $requisition): bool
{
    return in_array($requisition->status, [
        RequisitionStatus::Draft,
        RequisitionStatus::Submitted,
        RequisitionStatus::ChangesRequested,
    ], true)
        && ($requisition->requester_id === $user->id || app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value);
}

public function cancel(User $user, Requisition $requisition): bool
{
    return in_array($requisition->status, [
        RequisitionStatus::Submitted,
        RequisitionStatus::ChangesRequested,
    ], true)
        && app(CurrentTenant::class)->roleFor($user) === TenantRole::Admin->value;
}

public function comment(User $user, Requisition $requisition): bool
{
    return $this->view($user, $requisition)
        && ! in_array($requisition->status, [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled], true);
}

public function mention(User $user, Requisition $requisition): bool
{
    return $this->comment($user, $requisition);
}
```

Update `update()` so `ChangesRequested` can be edited by the requester/admin:

```php
return in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::ChangesRequested], true)
    && ($role === TenantRole::Admin->value || $requisition->requester_id === $user->id);
```

- [ ] **Step 6: Allow updates in `changes_requested`**

In `UpdateRequisitionDraft`, replace both draft-only checks with:

```php
if (! in_array($requisition->status, [RequisitionStatus::Draft, RequisitionStatus::ChangesRequested], true)) {
    throw new DraftConflictException('Only draft or change-requested requisitions can be updated.');
}
```

- [ ] **Step 7: Extend resource permissions and metadata**

In `RequisitionResource`, load actor relationships where present and add:

```php
'changesRequestedAt' => $this->changes_requested_at?->toISOString(),
'changesRequestedBy' => $this->userSummary($this->whenLoaded('changesRequestedBy')),
'changeRequestReason' => $this->change_request_reason,
'changeRequestFields' => $this->change_request_fields ?? [],
'withdrawnAt' => $this->withdrawn_at?->toISOString(),
'withdrawnBy' => $this->userSummary($this->whenLoaded('withdrawnBy')),
'withdrawalReason' => $this->withdrawal_reason,
'cancelledAt' => $this->cancelled_at?->toISOString(),
'cancelledBy' => $this->userSummary($this->whenLoaded('cancelledBy')),
'cancellationReason' => $this->cancellation_reason,
```

Replace `permissions` with:

```php
'permissions' => [
    'canUpdate' => $request->user()?->can('update', $this->resource) ?? false,
    'canSubmit' => $this->status === RequisitionStatus::Draft
        && ($request->user()?->can('submit', $this->resource) ?? false),
    'canResubmit' => $request->user()?->can('resubmit', $this->resource) ?? false,
    'canRequestChanges' => $request->user()?->can('requestChanges', $this->resource) ?? false,
    'canWithdraw' => $request->user()?->can('withdraw', $this->resource) ?? false,
    'canCancel' => $request->user()?->can('cancel', $this->resource) ?? false,
    'canComment' => $request->user()?->can('comment', $this->resource) ?? false,
    'canMention' => $request->user()?->can('mention', $this->resource) ?? false,
    'canViewActivity' => $request->user()?->can('view', $this->resource) ?? false,
],
```

Add helper:

```php
/**
 * @return array{id: string, name: string, email: string|null}|null
 */
private function userSummary(mixed $user): ?array
{
    if (! $user instanceof \App\Models\User) {
        return null;
    }

    return [
        'id' => (string) $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
}
```

- [ ] **Step 8: Add lifecycle actions**

Create each action with transaction, status checks, audit, notification, and loaded return.

`RequestRequisitionChanges` sets:

```php
'status' => RequisitionStatus::ChangesRequested,
'changes_requested_at' => now(),
'changes_requested_by_id' => $actor->id,
'change_request_reason' => $data['reason'],
'change_request_fields' => $data['requestedFields'] ?? [],
```

Records audit action `requisition.changes_requested` and notification type `NotificationPreferenceDefaults::EVENT_REQUISITION_CHANGES_REQUESTED` to the requester.

`ResubmitRequisition` sets:

```php
'status' => RequisitionStatus::Submitted,
'submitted_at' => now(),
'changes_requested_at' => null,
'changes_requested_by_id' => null,
'change_request_reason' => null,
'change_request_fields' => [],
```

Records audit action `requisition.resubmitted` and notifies buyers/admins.

`WithdrawRequisition` sets:

```php
'status' => RequisitionStatus::Withdrawn,
'withdrawn_at' => now(),
'withdrawn_by_id' => $actor->id,
'withdrawal_reason' => $reason,
```

Records audit action `requisition.withdrawn`.

`CancelRequisition` sets:

```php
'status' => RequisitionStatus::Cancelled,
'cancelled_at' => now(),
'cancelled_by_id' => $actor->id,
'cancellation_reason' => $reason,
```

Records audit action `requisition.cancelled` and notifies the requester.

All actions return:

```php
return $requisition->refresh()->load([
    'requester',
    'lineItems',
    'changesRequestedBy',
    'withdrawnBy',
    'cancelledBy',
]);
```

- [ ] **Step 9: Extend notification event constants**

In `NotificationPreferenceDefaults`, add:

```php
public const EVENT_REQUISITION_CHANGES_REQUESTED = 'requisition.changes_requested';
public const EVENT_REQUISITION_RESUBMITTED = 'requisition.resubmitted';
public const EVENT_REQUISITION_WITHDRAWN = 'requisition.withdrawn';
public const EVENT_REQUISITION_CANCELLED = 'requisition.cancelled';
public const EVENT_COLLABORATION_MENTIONED = 'collaboration.mentioned';
```

Add each constant to `EVENTS` and `defaults()` with `['inApp' => true]`.

- [ ] **Step 10: Wire controller methods**

In `RequisitionController`, import the new request/action classes and add methods:

```php
public function requestChanges(
    RequestRequisitionChangesRequest $request,
    CurrentTenant $currentTenant,
    RequestRequisitionChanges $requestRequisitionChanges,
    int $requisition,
): RequisitionResource {
    $requisition = $this->findTenantRequisition($currentTenant, $requisition);
    $this->authorize('requestChanges', $requisition);

    return new RequisitionResource($requestRequisitionChanges->handle(
        $this->tenantOrAbort($currentTenant),
        $request->user(),
        $requisition,
        $request->validated(),
    ));
}
```

Add the `resubmit` method:

```php
public function resubmit(
    Request $request,
    CurrentTenant $currentTenant,
    ResubmitRequisition $resubmitRequisition,
    int $requisition,
): RequisitionResource {
    $requisition = $this->findTenantRequisition($currentTenant, $requisition);
    $this->authorize('resubmit', $requisition);

    return new RequisitionResource($resubmitRequisition->handle(
        $this->tenantOrAbort($currentTenant),
        $request->user(),
        $requisition,
    ));
}
```

Add the `withdraw` method:

```php
public function withdraw(
    ReasonedRequisitionActionRequest $request,
    CurrentTenant $currentTenant,
    WithdrawRequisition $withdrawRequisition,
    int $requisition,
): RequisitionResource {
    $requisition = $this->findTenantRequisition($currentTenant, $requisition);
    $this->authorize('withdraw', $requisition);

    return new RequisitionResource($withdrawRequisition->handle(
        $this->tenantOrAbort($currentTenant),
        $request->user(),
        $requisition,
        $request->validated('reason'),
    ));
}
```

Add the `cancel` method:

```php
public function cancel(
    ReasonedRequisitionActionRequest $request,
    CurrentTenant $currentTenant,
    CancelRequisition $cancelRequisition,
    int $requisition,
): RequisitionResource {
    $requisition = $this->findTenantRequisition($currentTenant, $requisition);
    $this->authorize('cancel', $requisition);

    return new RequisitionResource($cancelRequisition->handle(
        $this->tenantOrAbort($currentTenant),
        $request->user(),
        $requisition,
        $request->validated('reason'),
    ));
}
```

For `withdraw` and `cancel`, pass `$request->validated('reason')`.

- [ ] **Step 11: Load lifecycle actor relationships in list/show**

In `index()`, `show()`, and action returns, ensure requisitions load:

```php
['requester', 'lineItems', 'changesRequestedBy', 'withdrawnBy', 'cancelledBy']
```

- [ ] **Step 12: Extend list filters**

In `RequisitionController::index()`, add filters:

```php
$query->when($request->query('requester'), fn ($query, string $requester) => $query->where('requester_id', $requester));
$query->when($request->query('department'), fn ($query, string $department) => $query->where('department', $department));
$query->when($request->query('updatedFrom'), fn ($query, string $date) => $query->whereDate('updated_at', '>=', $date));
$query->when($request->query('updatedTo'), fn ($query, string $date) => $query->whereDate('updated_at', '<=', $date));
```

For `queuePreset`, implement:

```php
match ($request->query('queuePreset')) {
    'my_drafts' => $query->where('requester_id', $user->id)->where('status', RequisitionStatus::Draft),
    'submitted' => $query->where('status', RequisitionStatus::Submitted),
    'needs_my_correction' => $query->where('requester_id', $user->id)->where('status', RequisitionStatus::ChangesRequested),
    'buyer_review' => $query->where('status', RequisitionStatus::Submitted),
    'stopped' => $query->whereIn('status', [RequisitionStatus::Withdrawn, RequisitionStatus::Cancelled]),
    'all_visible', null => null,
    default => null,
};
```

For amount filters, implement database-side filtering with a subquery. Do not filter after pagination.

```php
$query->when($request->query('amountMin'), function ($query, string $amount): void {
    $query->whereRaw('(select coalesce(sum(quantity * estimated_unit_price), 0) from requisition_line_items where requisition_line_items.requisition_id = requisitions.id) >= ?', [(float) $amount]);
});

$query->when($request->query('amountMax'), function ($query, string $amount): void {
    $query->whereRaw('(select coalesce(sum(quantity * estimated_unit_price), 0) from requisition_line_items where requisition_line_items.requisition_id = requisitions.id) <= ?', [(float) $amount]);
});
```

- [ ] **Step 13: Add routes**

In `apps/api/routes/api.php`, add below `/submit`:

```php
Route::post('/requisitions/{requisition}/request-changes', [RequisitionController::class, 'requestChanges']);
Route::post('/requisitions/{requisition}/resubmit', [RequisitionController::class, 'resubmit']);
Route::post('/requisitions/{requisition}/withdraw', [RequisitionController::class, 'withdraw']);
Route::post('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel']);
```

- [ ] **Step 14: Run backend lifecycle tests**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionApiTest
```

Expected: Requisition API tests pass.

- [ ] **Step 15: Commit backend lifecycle implementation**

Run:

```bash
git add apps/api/database/migrations apps/api/Domains/Requisition apps/api/routes/api.php apps/api/app/Notifications/NotificationPreferenceDefaults.php apps/api/tests/Feature/RequisitionApiTest.php
git commit -m "feat: add requisition workspace transitions"
```

Expected: Commit contains lifecycle implementation and tests.

---

## Task 6: Backend Collaboration Tests

**Files:**
- Create: `apps/api/tests/Feature/CollaborationApiTest.php`

- [ ] **Step 1: Create collaboration feature test file**

Create `apps/api/tests/Feature/CollaborationApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollaborationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_visible_user_can_comment_on_requisition_and_mention_visible_user(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $admin] = $this->tenantUser('admin', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, RequisitionStatus::Submitted);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'Looping in admin for visibility.',
                'mentionedUserIds' => [(string) $admin->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Looping in admin for visibility.')
            ->assertJsonPath('data.mentions.0.mentionedUser.id', (string) $admin->id);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'collaboration.comment_created',
        ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $tenant->id,
            'recipient_id' => $admin->id,
            'actor_id' => $buyer->id,
            'type' => 'collaboration.mentioned',
            'href' => "/requisitions/{$requisition->id}",
        ]);
    }

    public function test_mention_rejects_user_without_requisition_visibility(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $outsider] = $this->tenantUser('requester');
        $requisition = $this->createRequisition($tenant, $requester, RequisitionStatus::Submitted);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'This mention should be rejected.',
                'mentionedUserIds' => [(string) $outsider->id],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->assertDatabaseMissing('notifications', [
            'tenant_id' => $otherTenant->id,
            'recipient_id' => $outsider->id,
            'type' => 'collaboration.mentioned',
        ]);
    }

    public function test_mention_candidates_only_include_users_with_requisition_visibility(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [, $otherRequester] = $this->tenantUser('requester', $tenant);
        $requisition = $this->createRequisition($tenant, $requester, RequisitionStatus::Submitted);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/requisitions/{$requisition->id}/mention-candidates")
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $requester->id])
            ->assertJsonFragment(['id' => (string) $buyer->id])
            ->assertJsonMissing(['id' => (string) $otherRequester->id]);
    }

    public function test_cross_tenant_user_cannot_list_or_create_requisition_comments(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [$otherTenant, $outsider] = $this->tenantUser('buyer');
        $requisition = $this->createRequisition($tenant, $requester, RequisitionStatus::Submitted);

        $this->actingAsTenant($otherTenant, $outsider)
            ->getJson("/api/requisitions/{$requisition->id}/comments")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $outsider)
            ->postJson("/api/requisitions/{$requisition->id}/comments", [
                'body' => 'Forbidden cross tenant comment.',
            ])
            ->assertNotFound();
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

    private function createRequisition(Tenant $tenant, User $requester, RequisitionStatus $status): Requisition
    {
        return Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace unsupported devices.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'MYR',
            'status' => $status,
            'lock_version' => 0,
            'submitted_at' => $status === RequisitionStatus::Submitted ? now() : null,
        ]);
    }
}
```

- [ ] **Step 2: Run collaboration tests to verify expected failures**

Run:

```bash
cd apps/api && php artisan test --filter=CollaborationApiTest
```

Expected: Fails because collaboration tables, models, controller, and routes do not exist.

- [ ] **Step 3: Commit failing collaboration tests**

Run:

```bash
git add apps/api/tests/Feature/CollaborationApiTest.php
git commit -m "test: cover requisition collaboration api"
```

Expected: Commit contains only collaboration tests.

---

## Task 7: Backend Collaboration Implementation

**Files:**
- Create: `apps/api/database/migrations/2026_05_15_031000_create_collaboration_tables.php`
- Create: `apps/api/Domains/Collaboration/Models/CollaborationComment.php`
- Create: `apps/api/Domains/Collaboration/Models/CollaborationMention.php`
- Create: `apps/api/Domains/Collaboration/Actions/CreateCollaborationComment.php`
- Create: `apps/api/Domains/Collaboration/Http/Controllers/RequisitionCommentController.php`
- Create: `apps/api/Domains/Collaboration/Http/Requests/CreateCollaborationCommentRequest.php`
- Create: `apps/api/Domains/Collaboration/Http/Resources/CollaborationCommentResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Create collaboration migration**

Create `apps/api/database/migrations/2026_05_15_031000_create_collaboration_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });

        Schema::create('collaboration_mentions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('comment_id')->constrained('collaboration_comments')->cascadeOnDelete();
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['comment_id', 'mentioned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_mentions');
        Schema::dropIfExists('collaboration_comments');
    }
};
```

- [ ] **Step 2: Create collaboration models**

Create `CollaborationComment.php`:

```php
<?php

namespace Domains\Collaboration\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CollaborationComment extends Model
{
    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'author_id',
        'body',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(CollaborationMention::class, 'comment_id');
    }
}
```

Create `CollaborationMention.php`:

```php
<?php

namespace Domains\Collaboration\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollaborationMention extends Model
{
    protected $fillable = [
        'tenant_id',
        'comment_id',
        'mentioned_user_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(CollaborationComment::class, 'comment_id');
    }

    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
```

- [ ] **Step 3: Create request class**

Create `CreateCollaborationCommentRequest.php`:

```php
<?php

namespace Domains\Collaboration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCollaborationCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'mentionedUserIds' => ['sometimes', 'array'],
            'mentionedUserIds.*' => ['string'],
        ];
    }
}
```

- [ ] **Step 4: Create resource class**

Create `CollaborationCommentResource.php`:

```php
<?php

namespace Domains\Collaboration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollaborationCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'subjectType' => $this->subject_type,
            'subjectId' => (string) $this->subject_id,
            'author' => [
                'id' => (string) $this->author->id,
                'name' => $this->author->name,
                'email' => $this->author->email,
            ],
            'body' => $this->body,
            'mentions' => $this->mentions->map(fn ($mention): array => [
                'id' => (string) $mention->id,
                'mentionedUser' => [
                    'id' => (string) $mention->mentionedUser->id,
                    'name' => $mention->mentionedUser->name,
                    'email' => $mention->mentionedUser->email,
                ],
            ])->values()->all(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 5: Create comment action**

Create `CreateCollaborationComment.php` with this behavior:

```php
<?php

namespace Domains\Collaboration\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCollaborationComment
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {
    }

    /**
     * @param array{body: string, mentionedUserIds?: array<int, string>} $data
     */
    public function handle(Tenant $tenant, User $actor, Requisition $requisition, array $data): CollaborationComment
    {
        $mentionedUsers = User::query()
            ->whereIn('id', $data['mentionedUserIds'] ?? [])
            ->get();

        foreach ($mentionedUsers as $mentionedUser) {
            if (! $mentionedUser->can('view', $requisition)) {
                throw ValidationException::withMessages([
                    'mentionedUserIds' => ['Mentioned users must be able to view the requisition.'],
                ]);
            }
        }

        return DB::transaction(function () use ($tenant, $actor, $requisition, $data, $mentionedUsers): CollaborationComment {
            $comment = CollaborationComment::query()->create([
                'tenant_id' => $tenant->id,
                'subject_type' => 'requisition',
                'subject_id' => $requisition->id,
                'author_id' => $actor->id,
                'body' => $data['body'],
            ]);

            foreach ($mentionedUsers as $mentionedUser) {
                $comment->mentions()->create([
                    'tenant_id' => $tenant->id,
                    'mentioned_user_id' => $mentionedUser->id,
                ]);
            }

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'collaboration.comment_created',
                subject: $requisition,
                metadata: ['commentId' => (string) $comment->id],
                before: null,
                after: ['body' => $data['body']],
                subjectDisplay: $requisition->number,
            ));

            $this->notificationRecorder->record(
                tenant: $tenant,
                recipients: $mentionedUsers->reject(fn (User $user): bool => $user->id === $actor->id),
                data: new NotificationData(
                    type: NotificationPreferenceDefaults::EVENT_COLLABORATION_MENTIONED,
                    title: 'You were mentioned',
                    body: "{$actor->name} mentioned you on {$requisition->number}.",
                    href: "/requisitions/{$requisition->id}",
                    subject: $requisition,
                    subjectLabel: $requisition->number,
                    metadata: ['commentId' => (string) $comment->id],
                    actor: $actor,
                ),
            );

            return $comment->refresh()->load(['author', 'mentions.mentionedUser']);
        });
    }
}
```

- [ ] **Step 6: Create requisition comments controller**

Create `RequisitionCommentController.php`:

```php
<?php

namespace Domains\Collaboration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Collaboration\Actions\CreateCollaborationComment;
use Domains\Collaboration\Http\Requests\CreateCollaborationCommentRequest;
use Domains\Collaboration\Http\Resources\CollaborationCommentResource;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequisitionCommentController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $requisition): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant->id, $requisition);
        $this->authorize('view', $requisition);

        $comments = CollaborationComment::query()
            ->with(['author', 'mentions.mentionedUser'])
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', 'requisition')
            ->where('subject_id', $requisition->id)
            ->oldest()
            ->get();

        return CollaborationCommentResource::collection($comments);
    }

    public function candidates(CurrentTenant $currentTenant, int $requisition): \Illuminate\Http\JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant->id, $requisition);
        $this->authorize('mention', $requisition);

        $users = $tenant->users()
            ->get()
            ->filter(fn (User $user): bool => $user->can('view', $requisition))
            ->values()
            ->map(fn (User $user): array => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json(['data' => $users]);
    }

    public function store(
        CreateCollaborationCommentRequest $request,
        CurrentTenant $currentTenant,
        CreateCollaborationComment $createComment,
        int $requisition,
    ): \Illuminate\Http\JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $requisition = $this->findTenantRequisition($tenant->id, $requisition);
        $this->authorize('comment', $requisition);

        $comment = $createComment->handle($tenant, $request->user(), $requisition, $request->validated());

        return (new CollaborationCommentResource($comment))
            ->response()
            ->setStatusCode(201);
    }

    private function findTenantRequisition(int $tenantId, int $id): Requisition
    {
        return Requisition::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): \App\Tenancy\Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
```

- [ ] **Step 7: Add routes**

In `apps/api/routes/api.php`, import:

```php
use Domains\Collaboration\Http\Controllers\RequisitionCommentController;
```

Add after requisition activity route:

```php
Route::get('/requisitions/{requisition}/comments', [RequisitionCommentController::class, 'index']);
Route::post('/requisitions/{requisition}/comments', [RequisitionCommentController::class, 'store']);
Route::get('/requisitions/{requisition}/mention-candidates', [RequisitionCommentController::class, 'candidates']);
```

- [ ] **Step 8: Run collaboration backend tests**

Run:

```bash
cd apps/api && php artisan test --filter=CollaborationApiTest
```

Expected: Collaboration tests pass.

- [ ] **Step 9: Run notification and requisition regression tests**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionApiTest|NotificationApiTest|AuditApiTest'
```

Expected: Tests pass.

- [ ] **Step 10: Commit collaboration implementation**

Run:

```bash
git add apps/api/database/migrations apps/api/Domains/Collaboration apps/api/routes/api.php apps/api/tests/Feature/CollaborationApiTest.php
git commit -m "feat: add requisition collaboration"
```

Expected: Commit contains collaboration implementation and tests.

---

## Task 8: MSW Workflow And Frontend Tests First

**Files:**
- Modify: `apps/web/features/requisitions/mocks/requisitions-fixtures.ts`
- Modify: `apps/web/features/requisitions/mocks/requisitions-handlers.ts`
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`
- Create: `apps/web/features/requisitions/tests/requisition-comments.test.tsx`

- [ ] **Step 1: Extend fixtures with new statuses and permissions**

In `requisitions-fixtures.ts`, add at least three records:

```ts
export const changeRequestedRequisition = {
  ...requisitionFixtures[0],
  id: "req-changes",
  number: "REQ-2026-000010",
  title: "Returned laptop request",
  status: "changes_requested",
  changesRequestedAt: "2026-05-15T09:00:00.000Z",
  changesRequestedBy: {
    id: "user-buyer",
    name: "Priya Buyer",
    email: "priya.buyer@acme.test",
  },
  changeRequestReason: "Please clarify quantity and delivery location.",
  changeRequestFields: ["lineItems", "deliveryLocation"],
  withdrawnAt: null,
  withdrawnBy: null,
  withdrawalReason: null,
  cancelledAt: null,
  cancelledBy: null,
  cancellationReason: null,
  permissions: {
    canUpdate: true,
    canSubmit: false,
    canResubmit: true,
    canRequestChanges: false,
    canWithdraw: true,
    canCancel: false,
    canComment: true,
    canMention: true,
    canViewActivity: true,
  },
};
```

Add `withdrawn` and `cancelled` fixtures with read-only permissions.

- [ ] **Step 2: Add comments fixture state**

In `requisitions-handlers.ts`, add:

```ts
let comments: Record<string, CollaborationComment[]> = {
  "req-1": [
    {
      id: "comment-1",
      subjectType: "requisition",
      subjectId: "req-1",
      author: { id: "user-2", name: "Priya Buyer", email: "priya.buyer@acme.test" },
      body: "Can you confirm delivery timing?",
      mentions: [],
      createdAt: "2026-05-15T09:00:00.000Z",
      updatedAt: "2026-05-15T09:00:00.000Z",
    },
  ],
};
```

Reset it inside `resetRequisitionMockState()`.

- [ ] **Step 3: Add MSW action handlers**

Add handlers for:

- `POST /api/requisitions/:requisitionId/request-changes`
- `POST /api/requisitions/:requisitionId/resubmit`
- `POST /api/requisitions/:requisitionId/withdraw`
- `POST /api/requisitions/:requisitionId/cancel`
- `GET /api/requisitions/:requisitionId/mention-candidates`

Each handler must:

- return `{ error: { code: "not_found", message: "Requisition not found." } }` with 404 when missing
- return `{ error: { code: "conflict", message: "..." } }` with 409 on invalid state
- update status and metadata on success
- return `{ data: updatedRequisition }`

- [ ] **Step 4: Add MSW comments handlers**

Add:

```ts
http.get("/api/requisitions/:requisitionId/comments", ({ params }) => {
  return HttpResponse.json({ data: comments[String(params.requisitionId)] ?? [] });
}),

http.get("/api/requisitions/:requisitionId/mention-candidates", () => {
  return HttpResponse.json({
    data: [
      { id: "user-1", name: "Maya Tan", email: "maya.tan@acme.test" },
      { id: "user-2", name: "Priya Buyer", email: "priya.buyer@acme.test" },
    ],
  });
}),

http.post("/api/requisitions/:requisitionId/comments", async ({ params, request }) => {
  const body = (await request.json()) as { body?: string; mentionedUserIds?: string[] };
  if (!body.body?.trim()) {
    return HttpResponse.json(
      { error: { code: "validation_failed", message: "Validation failed", details: { fields: { body: ["Comment is required."] } } } },
      { status: 422 },
    );
  }

  const mentionedUsers = (body.mentionedUserIds ?? []).map((id) => ({
    id: `mention-${id}`,
    mentionedUser: {
      id,
      name: id === "user-2" ? "Priya Buyer" : "Maya Tan",
      email: id === "user-2" ? "priya.buyer@acme.test" : "maya.tan@acme.test",
    },
  }));

  const comment = {
    id: `comment-${Date.now()}`,
    subjectType: "requisition" as const,
    subjectId: String(params.requisitionId),
    author: { id: "user-1", name: "Maya Tan", email: "maya.tan@acme.test" },
    body: body.body,
    mentions: mentionedUsers,
    createdAt: new Date().toISOString(),
    updatedAt: new Date().toISOString(),
  };

  comments[String(params.requisitionId)] = [...(comments[String(params.requisitionId)] ?? []), comment];
  return HttpResponse.json({ data: comment }, { status: 201 });
}),
```

- [ ] **Step 5: Add frontend workflow failing tests**

In `requisitions-workflow.test.tsx`, add tests for:

```ts
it("shows correction guidance and resubmits a change-requested requisition", async () => {
  const user = userEvent.setup();
  renderWithQuery(<RequisitionDetailPage requisitionId="req-changes" />);

  expect(await screen.findByText("Changes requested")).toBeInTheDocument();
  expect(screen.getByText("Please clarify quantity and delivery location.")).toBeInTheDocument();

  await user.click(screen.getByRole("button", { name: "Resubmit" }));
  await waitFor(() => {
    expect(screen.getByText("Submitted")).toBeInTheDocument();
  });
});

it("requires a reason before withdrawing a requisition", async () => {
  const user = userEvent.setup();
  renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

  await user.click(await screen.findByRole("button", { name: "Withdraw" }));
  await user.click(screen.getByRole("button", { name: "Confirm withdrawal" }));

  expect(await screen.findByText("Reason is required.")).toBeInTheDocument();
});
```

- [ ] **Step 6: Add comments failing tests**

Create `requisition-comments.test.tsx`:

```ts
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";
import { RequisitionComments } from "../components/requisition-comments";

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("requisition comments", () => {
  it("lists and creates comments", async () => {
    const user = userEvent.setup();
    renderWithQuery(<RequisitionComments requisitionId="req-1" canComment canMention />);

    expect(await screen.findByText("Can you confirm delivery timing?")).toBeInTheDocument();

    await user.type(screen.getByLabelText("Comment"), "Confirmed for next week.");
    await user.click(screen.getByRole("button", { name: "Post comment" }));

    await waitFor(() => {
      expect(screen.getByText("Confirmed for next week.")).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 7: Run frontend tests to verify expected failures**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-comments.test.tsx
```

Expected: Fails because UI components and hooks are not implemented yet.

- [ ] **Step 8: Commit MSW and failing frontend tests**

Run:

```bash
git add apps/web/features/requisitions/mocks apps/web/features/requisitions/tests
git commit -m "test: cover requisition workspace web flows"
```

Expected: Commit contains MSW state and failing frontend tests.

---

## Task 9: Frontend Hooks And Components

**Files:**
- Modify: `apps/web/features/requisitions/hooks/use-requisitions.ts`
- Modify: `apps/web/features/requisitions/hooks/use-requisition.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-actions.ts`
- Create: `apps/web/features/requisitions/hooks/use-requisition-comments.ts`
- Modify: `apps/web/features/requisitions/components/requisition-status-badge.tsx`
- Modify: `apps/web/features/requisitions/components/requisition-activity-timeline.tsx`
- Create: `apps/web/features/requisitions/components/requisition-action-dialog.tsx`
- Create: `apps/web/features/requisitions/components/requisition-correction-panel.tsx`
- Create: `apps/web/features/requisitions/components/requisition-comments.tsx`
- Create: `apps/web/features/requisitions/components/requisition-mention-input.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`

- [ ] **Step 1: Add action hooks**

Create `use-requisition-actions.ts`:

```ts
"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import {
  cancelRequisition,
  requestRequisitionChanges,
  resubmitRequisition,
  withdrawRequisition,
} from "../api/requisitions-api";

export function useRequestRequisitionChanges(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: { reason: string; requestedFields: string[] }) =>
      requestRequisitionChanges(requisitionId, values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId] }),
  });
}

export function useResubmitRequisition(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => resubmitRequisition(requisitionId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId] }),
  });
}

export function useWithdrawRequisition(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: { reason: string }) => withdrawRequisition(requisitionId, values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId] }),
  });
}

export function useCancelRequisition(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (values: { reason: string }) => cancelRequisition(requisitionId, values),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId] }),
  });
}
```

- [ ] **Step 2: Add comments hook**

Create `use-requisition-comments.ts`:

```ts
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createRequisitionComment,
  listRequisitionComments,
  listRequisitionMentionCandidates,
} from "../api/requisitions-api";

export function useRequisitionComments(requisitionId: string) {
  return useQuery({
    queryKey: ["requisition", requisitionId, "comments"],
    queryFn: () => listRequisitionComments(requisitionId),
  });
}

export function useCreateRequisitionComment(requisitionId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values: { body: string; mentionedUserIds: string[] }) =>
      createRequisitionComment(requisitionId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId, "comments"] });
      queryClient.invalidateQueries({ queryKey: ["requisition", requisitionId, "activity"] });
    },
  });
}

export function useRequisitionMentionCandidates(requisitionId: string, enabled: boolean) {
  return useQuery({
    queryKey: ["requisition", requisitionId, "mention-candidates"],
    queryFn: () => listRequisitionMentionCandidates(requisitionId),
    enabled,
  });
}
```

- [ ] **Step 3: Update status badge**

In `requisition-status-badge.tsx`, add configs:

```ts
changes_requested: { label: "Changes requested", tone: "warning" },
withdrawn: { label: "Withdrawn", tone: "locked" },
cancelled: { label: "Cancelled", tone: "danger" },
```

Use existing badge tone names from `apps/web/components/workflow/status-badge.tsx`.

- [ ] **Step 4: Update activity icons**

In `requisition-activity-timeline.tsx`, map:

```ts
"requisition.changes_requested": MessageSquareWarning,
"requisition.resubmitted": Send,
"requisition.withdrawn": CircleStop,
"requisition.cancelled": CircleX,
"collaboration.comment_created": MessageSquare,
"collaboration.mentioned": AtSign,
```

Import these icons from `lucide-react`.

- [ ] **Step 5: Create correction panel**

Create `requisition-correction-panel.tsx`:

```tsx
"use client";

import type { Requisition } from "../types/requisition-view-model";

export function RequisitionCorrectionPanel({ requisition }: { requisition: Requisition }) {
  if (requisition.status !== "changes_requested") return null;

  return (
    <section className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950">
      <h2 className="text-base font-semibold">Changes requested</h2>
      <p className="mt-2">{requisition.changeRequestReason}</p>
      {requisition.changeRequestFields.length ? (
        <ul className="mt-3 list-disc space-y-1 pl-5">
          {requisition.changeRequestFields.map((field) => (
            <li key={field}>{field}</li>
          ))}
        </ul>
      ) : null}
      {requisition.changesRequestedBy ? (
        <p className="mt-3 text-xs">
          Requested by {requisition.changesRequestedBy.name}
          {requisition.changesRequestedAt ? ` on ${requisition.changesRequestedAt}` : ""}
        </p>
      ) : null}
    </section>
  );
}
```

- [ ] **Step 6: Create action dialog**

Create `requisition-action-dialog.tsx` with props:

```tsx
type RequisitionActionDialogProps = {
  action: "request-changes" | "withdraw" | "cancel";
  title: string;
  description: string;
  confirmLabel: string;
  requireRequestedFields?: boolean;
  isPending: boolean;
  onSubmit: (values: { reason: string; requestedFields: string[] }) => void;
};
```

Render:

- a button supplied by parent through `trigger`
- a `<textarea aria-label="Reason">`
- requested fields text input only when `requireRequestedFields` is true
- validation message `Reason is required.`
- submit button with `confirmLabel`

Keep state local to the dialog. Split comma-separated requested fields into trimmed strings.

- [ ] **Step 7: Create comments component**

Create `requisition-mention-input.tsx`:

```tsx
"use client";

import type { UserSummary } from "../types/requisition-view-model";

export function RequisitionMentionInput({
  candidates,
  selectedIds,
  onChange,
}: {
  candidates: UserSummary[];
  selectedIds: string[];
  onChange: (selectedIds: string[]) => void;
}) {
  return (
    <label className="block text-sm font-medium">
      Mention
      <select
        className="mt-1 min-h-11 w-full rounded-md border px-3 text-base font-normal"
        value=""
        onChange={(event) => {
          const value = event.target.value;
          if (value && !selectedIds.includes(value)) {
            onChange([...selectedIds, value]);
          }
        }}
      >
        <option value="">Select a visible collaborator</option>
        {candidates.map((candidate) => (
          <option key={candidate.id} value={candidate.id}>
            {candidate.name}
          </option>
        ))}
      </select>
    </label>
  );
}
```

- [ ] **Step 8: Create comments component**

Create `requisition-comments.tsx`:

```tsx
"use client";

import { useState } from "react";
import {
  useCreateRequisitionComment,
  useRequisitionComments,
  useRequisitionMentionCandidates,
} from "../hooks/use-requisition-comments";
import { RequisitionMentionInput } from "./requisition-mention-input";

export function RequisitionComments({
  requisitionId,
  canComment,
  canMention,
}: {
  requisitionId: string;
  canComment: boolean;
  canMention: boolean;
}) {
  const commentsQuery = useRequisitionComments(requisitionId);
  const mentionCandidatesQuery = useRequisitionMentionCandidates(requisitionId, canMention);
  const createComment = useCreateRequisitionComment(requisitionId);
  const [body, setBody] = useState("");
  const [selectedMentionIds, setSelectedMentionIds] = useState<string[]>([]);

  if (commentsQuery.isLoading) {
    return <p className="text-sm text-muted-foreground">Loading comments</p>;
  }

  if (commentsQuery.isError) {
    return <p className="text-sm text-red-700">Comments could not be loaded.</p>;
  }

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        {(commentsQuery.data ?? []).length === 0 ? (
          <p className="text-sm text-muted-foreground">No comments yet.</p>
        ) : null}
        {(commentsQuery.data ?? []).map((comment) => (
          <article key={comment.id} className="rounded-md border p-3 text-sm">
            <p className="font-medium">{comment.author.name}</p>
            <p className="mt-2 whitespace-pre-wrap">{comment.body}</p>
          </article>
        ))}
      </div>
      {canComment ? (
        <form
          className="space-y-2"
          onSubmit={(event) => {
            event.preventDefault();
            if (!body.trim()) return;
            createComment.mutate(
              { body, mentionedUserIds: selectedMentionIds },
              {
                onSuccess: () => {
                  setBody("");
                  setSelectedMentionIds([]);
                },
              },
            );
          }}
        >
          <label className="block text-sm font-medium">
            Comment
            <textarea
              className="mt-1 min-h-24 w-full rounded-md border px-3 py-2 text-base font-normal"
              value={body}
              onChange={(event) => setBody(event.target.value)}
            />
          </label>
          {canMention ? (
            <RequisitionMentionInput
              candidates={mentionCandidatesQuery.data ?? []}
              selectedIds={selectedMentionIds}
              onChange={setSelectedMentionIds}
            />
          ) : null}
          <button type="submit" className="min-h-11 rounded-md bg-foreground px-4 text-sm font-medium text-background">
            Post comment
          </button>
        </form>
      ) : null}
    </div>
  );
}
```

- [ ] **Step 9: Wire detail page**

In `requisition-detail-page.tsx`:

- import `RequisitionCorrectionPanel`
- import `RequisitionComments`
- import action hooks
- add `comments` section to `sections`
- render correction panel above overview
- render comments section before activity
- add buttons/dialogs for request changes, resubmit, withdraw, cancel based on permission flags

For resubmit:

```tsx
{requisition.permissions.canResubmit ? (
  <button
    type="button"
    className="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-foreground px-3 text-sm font-medium text-background"
    onClick={() => resubmitRequisition.mutate()}
  >
    Resubmit
  </button>
) : null}
```

- [ ] **Step 10: Run frontend tests**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-comments.test.tsx
```

Expected: Tests pass after implementing components and hooks.

- [ ] **Step 11: Commit frontend workspace components**

Run:

```bash
git add apps/web/features/requisitions
git commit -m "feat: add requisition workspace interactions"
```

Expected: Commit contains frontend hooks, components, MSW, and tests.

---

## Task 10: Work Queue Filters And Edit Route Integration

**Files:**
- Modify: `apps/web/features/requisitions/hooks/use-requisitions.ts`
- Modify: `apps/web/features/requisitions/workflows/requisition-list-page.tsx`
- Modify: `apps/web/features/requisitions/tables/requisitions-table.tsx`
- Modify: `apps/web/app/(workspace)/requisitions/[requisitionId]/edit/page.tsx`
- Modify: `apps/web/features/requisitions/workflows/requisition-create-page.tsx`

- [ ] **Step 1: Extend requisition query type**

In `use-requisitions.ts` and `requisitions-api.ts`, support:

```ts
type RequisitionQuery = {
  search?: string;
  status?: string;
  requester?: string;
  owner?: string;
  department?: string;
  neededByFrom?: string;
  neededByTo?: string;
  amountMin?: string;
  amountMax?: string;
  updatedFrom?: string;
  updatedTo?: string;
  queuePreset?: RequisitionQueuePreset;
};
```

- [ ] **Step 2: Add queue preset controls**

In `requisition-list-page.tsx`, add preset buttons:

```tsx
const queuePresets = [
  { value: "all_visible", label: "All visible" },
  { value: "my_drafts", label: "My drafts" },
  { value: "submitted", label: "Submitted" },
  { value: "needs_my_correction", label: "Needs my correction" },
  { value: "buyer_review", label: "Buyer review" },
  { value: "stopped", label: "Stopped" },
] as const;
```

Render as buttons with `aria-pressed={queuePreset === preset.value}` and update query state.

- [ ] **Step 3: Add filters**

Add department, amount min/max, and updated date fields to the filter grid. Keep current search/status filters.

Each input should have a visible label and min-height 44px.

- [ ] **Step 4: Extend table display**

In `requisitions-table.tsx`, add columns or mobile metadata for:

- updated date
- department when present

Keep existing row actions. Add `Request changes`, `Withdraw`, or `Cancel` only on detail page, not table rows, to avoid clutter.

- [ ] **Step 5: Fix edit route**

Update `apps/web/app/(workspace)/requisitions/[requisitionId]/edit/page.tsx` to pass the route param into a workflow that loads an existing requisition:

```tsx
import { RequisitionCreatePage } from "@/features/requisitions/workflows/requisition-create-page";

export default async function EditRequisitionPage({
  params,
}: {
  params: Promise<{ requisitionId: string }>;
}) {
  const { requisitionId } = await params;

  return <RequisitionCreatePage requisitionId={requisitionId} />;
}
```

Update `RequisitionCreatePage` to accept:

```ts
export function RequisitionCreatePage({ requisitionId }: { requisitionId?: string } = {}) {
```

When `requisitionId` exists, use `useRequisition(requisitionId)` and pass the loaded requisition as initial form values. Preserve create behavior when `requisitionId` is absent.

- [ ] **Step 6: Add/edit tests for queue and edit route**

In `requisitions-workflow.test.tsx`, add:

```ts
it("filters the work queue by queue preset", async () => {
  const user = userEvent.setup();
  renderWithQuery(<RequisitionListPage />);

  await user.click(await screen.findByRole("button", { name: "Needs my correction" }));

  expect(await screen.findByText("Returned laptop request")).toBeInTheDocument();
});
```

Add a focused test for `RequisitionCreatePage requisitionId="req-changes"` that verifies existing title loads.

- [ ] **Step 7: Run frontend requisition tests**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx
```

Expected: Tests pass.

- [ ] **Step 8: Commit queue and edit route integration**

Run:

```bash
git add apps/web/app/(workspace)/requisitions/[requisitionId]/edit/page.tsx apps/web/features/requisitions
git commit -m "feat: improve requisition work queue and edit route"
```

Expected: Commit contains list/queue and edit route changes.

---

## Task 11: Final Contract, Security, And Regression Verification

**Files:**
- Inspect: `packages/api-client/src/generated/*`
- Inspect: `apps/api/Domains`
- Inspect: `apps/api/app`
- Inspect: `apps/web/features/requisitions`
- Inspect: `packages/ui`

- [ ] **Step 1: Run API contract verification**

Run:

```bash
pnpm check:api-contract
```

Expected: Passes with no uncommitted generated drift.

- [ ] **Step 2: Run backend focused suite**

Run:

```bash
cd apps/api && php artisan test --filter='RequisitionApiTest|CollaborationApiTest|NotificationApiTest|AuditApiTest|AttachmentApiTest'
```

Expected: All focused backend tests pass.

- [ ] **Step 3: Run frontend focused suite**

Run:

```bash
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-comments.test.tsx notification-center.test.tsx
```

Expected: All focused frontend tests pass.

- [ ] **Step 4: Run broader type and lint checks if touched files require it**

Run:

```bash
pnpm --filter @cognify/web typecheck
pnpm lint
```

Expected: Both pass. If `pnpm lint` is too broad or exposes unrelated existing failures, capture the exact unrelated failures in the final implementation handoff and still run the focused checks above.

- [ ] **Step 5: Inspect git diff for architecture drift**

Run:

```bash
git diff --stat HEAD
git diff -- apps/api/Domains apps/api/app apps/web/features/requisitions packages/ui
```

Expected:

- Requisition lifecycle code is in `apps/api/Domains/Requisition`.
- Collaboration code is in `apps/api/Domains/Collaboration`.
- Shared audit/notification infrastructure stays in `apps/api/app`.
- Requisition UI stays in `apps/web/features/requisitions`.
- No Cognify-specific code was added to `packages/ui`.
- No active approval task/RFQ/buyer intake behavior was added.

- [ ] **Step 6: Commit final verification fixes**

When Step 1-5 uncover implementation fixes, run:

```bash
git add apps/api apps/web packages/api-client
git commit -m "chore: verify requisition workspace integration"
```

Expected: Commit contains only final integration fixes. If there were no changes, skip this commit.

---

## Task 12: Implementation Handoff Notes

**Files:**
- Read: `docs/superpowers/plans/2026-05-15-requisition-submission-workspace.md`

- [ ] **Step 1: Record final verification evidence in the PR or final response**

Include:

```txt
php artisan test --filter='RequisitionApiTest|CollaborationApiTest|NotificationApiTest|AuditApiTest|AttachmentApiTest'
pnpm check:api-contract
pnpm --filter @cognify/web test -- requisitions-workflow.test.tsx requisition-comments.test.tsx notification-center.test.tsx
pnpm --filter @cognify/web typecheck
pnpm lint
```

For any command not run or any unrelated failure, include the exact reason and failure summary.

- [ ] **Step 2: Confirm acceptance criteria**

Verify and report:

- requester can submit draft
- buyer/approver can request changes
- requester can edit and resubmit `changes_requested`
- requester can withdraw
- admin can cancel
- terminal states are read-only
- comments work on visible requisitions
- mentions only target visible users
- audit and notification events are recorded
- generated client and MSW agree with OpenAPI

- [ ] **Step 3: Confirm no out-of-scope behavior was introduced**

Report explicitly:

- no approval routing/task lifecycle was added
- no RFQ/buyer intake workflow was added
- no aggregate workspace endpoint was added
- no separate task queue endpoint was added
- no `packages/ui` business meaning was added

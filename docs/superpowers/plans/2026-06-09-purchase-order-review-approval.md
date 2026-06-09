# Purchase Order Review And Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-37 so ready purchase orders can be routed through Cognify's shared approval workflow and end as approved, rejected, or changes requested before supplier issue.

**Architecture:** Extend the existing `PurchaseOrder` aggregate with approval outcome fields and route only `ready_for_review` or `changes_requested` POs through `Domains\Approval\Actions\RouteSubjectForApproval`. Register purchase orders as a shared Approval subject so existing approval tasks, delegations, SLA behavior, notifications, OpenAPI contracts, and web approval queues handle PO review. Keep supplier issue, change orders, receiving, invoices, and payment workflows out of this slice.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions, shared Approval domain, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-06-09-purchase-order-review-approval-design.md`.
- Roadmap row: `docs/01-product/feature-roadmap.md` feature `P1-37`.
- Current branch: `goal-feature/p1-37-po-review-approval`.
- Existing PO domain: `apps/api/Domains/PurchaseOrder`.
- Existing approval domain: `apps/api/Domains/Approval`.
- Existing PO web feature: `apps/web/features/purchase-orders`.
- Existing approval web feature: `apps/web/features/approvals`.

## File Map

### API

- Create: `apps/api/database/migrations/2026_06_09_010000_add_review_approval_fields_to_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/SubmitPurchaseOrderForApproval.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApprovalRouted.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApproved.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderRejected.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/RequestPurchaseOrderChanges.php`
- Create: `apps/api/Domains/Approval/SubjectHandlers/PurchaseOrderApprovalSubjectHandler.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/SubmitPurchaseOrderApprovalRequest.php`
- Modify: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrder.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `apps/api/routes/api.php`

### API Tests And Demo Data

- Create: `apps/api/tests/Feature/PurchaseOrderReviewApprovalApiTest.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` by running `pnpm generate:api`.

### Web

- Modify: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Modify: `apps/web/features/purchase-orders/components/purchase-order-actions.tsx`
- Create: `apps/web/features/purchase-orders/components/purchase-order-approval-panel.tsx`
- Modify: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify: `apps/web/features/approvals/forms/approval-policy-form.tsx`
- Modify: `apps/web/features/approvals/schemas/approval-policy-schema.ts`
- Modify: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Modify: `apps/web/features/approvals/types/approval-view-model.ts`
- Modify: `apps/web/features/approvals/workflows/approval-queue-page.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Modify: `apps/web/features/approvals/mocks/approval-fixtures.ts`
- Modify: `apps/web/features/approvals/tests/approval-queue-workflow.test.tsx`
- Create: `apps/web/features/approvals/tests/approval-purchase-order-task-detail.test.tsx`

### Docs

- Modify after PR merge: `docs/01-product/feature-roadmap.md`

---

## Task 1: API Red Tests For PO Approval

**Files:**

- Create: `apps/api/tests/Feature/PurchaseOrderReviewApprovalApiTest.php`

- [x] **Step 1: Create the feature test file with explicit scenarios**

Create `PurchaseOrderReviewApprovalApiTest` using `RefreshDatabase`. Reuse fixture helpers from `PurchaseOrderCreationApiTest` by copying the minimum tenant/user/PO seeding helpers into this new test file.

Initial test methods:

```php
public function test_buyer_can_submit_ready_purchase_order_for_approval(): void {}
public function test_submit_requires_ready_or_changes_requested_status(): void {}
public function test_submit_requires_current_lock_version(): void {}
public function test_submit_without_matching_policy_returns_conflict(): void {}
public function test_cross_tenant_submit_is_denied(): void {}
public function test_approval_task_approve_marks_purchase_order_approved(): void {}
public function test_approval_task_reject_marks_purchase_order_rejected(): void {}
public function test_approval_task_request_changes_marks_purchase_order_changes_requested(): void {}
public function test_buyer_can_update_changes_requested_purchase_order_and_resubmit(): void {}
public function test_approval_task_list_filters_purchase_order_subjects(): void {}
```

- [x] **Step 2: Add a ready-PO submit assertion**

Use this assertion shape in `test_buyer_can_submit_ready_purchase_order_for_approval`:

```php
$po = $this->readyPurchaseOrder();
$buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
$approver = $this->tenantUser($po->tenant, TenantRole::Approver->value);
$this->publishedApprovalPolicy($po->tenant, 'purchase_order', $approver);

$this->actingAsTenant($po->tenant, $buyer)
    ->postJson("/api/purchase-orders/{$po->id}/submit-approval", [
        'lockVersion' => $po->lock_version,
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'in_review')
    ->assertJsonPath('data.approval.approvalInstanceId', fn ($value) => is_string($value) && $value !== '')
    ->assertJsonPath('data.permissions.canSubmitForApproval', false);

$this->assertDatabaseHas('approval_tasks', [
    'tenant_id' => $po->tenant_id,
    'subject_type' => PurchaseOrder::class,
    'subject_id' => $po->id,
    'assignee_id' => $approver->id,
    'status' => 'active',
]);

$this->assertDatabaseHas('audit_events', [
    'tenant_id' => $po->tenant_id,
    'action' => 'purchase_order.approval_submitted',
]);
```

- [x] **Step 3: Add state, stale-lock, no-policy, and tenant-denial assertions**

Cover `draft`, `in_review`, `approved`, `rejected`, and `cancelled` with `assertConflict()`. Use stale lock payload `['lockVersion' => $po->lock_version - 1]` and expect `assertConflict()`. Submit without creating a policy and expect the existing approval engine message `No approval policy versions are available.` Cross-tenant submission must use an authenticated buyer from another tenant and expect `assertForbidden()`.

- [x] **Step 4: Add approval outcome assertions through existing task endpoints**

For approve:

```php
[$po, $task, $approver] = $this->submittedPurchaseOrderForApproval();

$this->actingAsTenant($po->tenant, $approver)
    ->postJson("/api/approval-tasks/{$task->id}/approve", [
        'lockVersion' => $task->lock_version,
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'approved');

$this->assertDatabaseHas('purchase_orders', [
    'id' => $po->id,
    'status' => 'approved',
    'approved_by_user_id' => $approver->id,
]);
```

Repeat for reject with `reason => 'Tax coding does not match the approved quotation.'` and for request changes with `requestedFields => ['taxAmount', 'paymentTerms']`.

- [x] **Step 5: Run the red API test**

Run:

```bash
php artisan test --filter=PurchaseOrderReviewApprovalApiTest
```

Expected: FAIL because the submit route, statuses, migration columns, handler, and OpenAPI-backed behavior do not exist yet.

---

## Task 2: Purchase Order Approval State And Actions

**Files:**

- Create: `apps/api/database/migrations/2026_06_09_010000_add_review_approval_fields_to_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/SubmitPurchaseOrderForApproval.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApprovalRouted.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApproved.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderRejected.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/RequestPurchaseOrderChanges.php`
- Modify: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrder.php`

- [x] **Step 1: Add migration columns**

Add nullable approval fields listed in the design spec, foreign keys to `users` for actor columns, `changes_requested_fields` JSON, and indexes for `tenant_id,status,approval_submitted_at` plus `tenant_id,approval_instance_id`.

- [x] **Step 2: Extend `PurchaseOrderStatus`**

Add enum cases:

```php
case InReview = 'in_review';
case ChangesRequested = 'changes_requested';
case Approved = 'approved';
case Rejected = 'rejected';
```

Update `isTerminal()` to return true for `Approved`, `Rejected`, and `Cancelled`.

- [x] **Step 3: Extend `PurchaseOrder` fillable, casts, and relations**

Add fillable/casts for all approval fields. Add `approvalSubmittedByUser()`, `approvedByUser()`, `rejectedByUser()`, and `changesRequestedByUser()` relations. Add same-tenant user checks for the new actor columns in `booted()`.

- [x] **Step 4: Allow updates only for draft or changes-requested POs**

Modify `UpdatePurchaseOrder` so the allowed editable statuses are `Draft` and `ChangesRequested`. Keep `InReview`, `Approved`, `Rejected`, and `Cancelled` blocked with a conflict message.

- [x] **Step 5: Add PO state mutation actions**

Each action must lock the PO by tenant, set its target status and relevant actor/timestamp/reason fields, increment `lock_version`, and record a `purchase_order.*` audit event through `PurchaseOrderAuditMetadata::for()`.

Actions:

- `MarkPurchaseOrderApprovalRouted`: set `status = in_review`, `approval_instance_id`, submitted actor/time.
- `MarkPurchaseOrderApproved`: set `status = approved`, approved actor/time, clear rejected/changes-requested fields.
- `MarkPurchaseOrderRejected`: set `status = rejected`, rejected actor/time/reason.
- `RequestPurchaseOrderChanges`: set `status = changes_requested`, changes actor/time/reason/fields.

- [x] **Step 6: Run the API test again**

Run:

```bash
php artisan test --filter=PurchaseOrderReviewApprovalApiTest
```

Expected: still FAIL because the route/controller and approval subject handler are not wired yet.

---

## Task 3: Approval Subject Handler And Submit Route

**Files:**

- Create: `apps/api/Domains/Approval/SubjectHandlers/PurchaseOrderApprovalSubjectHandler.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/SubmitPurchaseOrderApprovalRequest.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/SubmitPurchaseOrderForApproval.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/Domains/Approval/Http/Controllers/ApprovalTaskController.php`
- Modify: `apps/api/routes/api.php`

- [x] **Step 1: Implement `SubmitPurchaseOrderApprovalRequest`**

Rules:

```php
return [
    'lockVersion' => ['required', 'integer', 'min:1'],
];
```

- [x] **Step 2: Implement `SubmitPurchaseOrderForApproval`**

Inject `RouteSubjectForApproval` and call it after locking the PO, checking status, asserting lock version, and confirming required operational fields and at least one line. Return the fresh PO with lines and approval relation loaded.

- [x] **Step 3: Add `submitApproval` policy method**

Allow buyer/admin only when the current tenant matches the PO tenant. Do not allow requester-only users to submit POs.

- [x] **Step 4: Add controller route method**

In `PurchaseOrderController`, add:

```php
public function submitApproval(
    SubmitPurchaseOrderApprovalRequest $request,
    CurrentTenant $currentTenant,
    PurchaseOrder $purchaseOrder,
    SubmitPurchaseOrderForApproval $action,
): JsonResponse
```

Resolve the tenant-scoped PO with the existing private finder, authorize `submitApproval`, call the action, and return `PurchaseOrderResource`.

- [x] **Step 5: Add API route**

Add under purchase-order routes:

```php
Route::post('purchase-orders/{purchaseOrder}/submit-approval', [PurchaseOrderController::class, 'submitApproval']);
```

- [x] **Step 6: Implement `PurchaseOrderApprovalSubjectHandler`**

Build context from PO fields and `source_snapshot`, return subject summary with:

```php
type: 'purchase_order'
id: (string) $purchaseOrder->id
number: $purchaseOrder->number
title: 'Purchase order '.$purchaseOrder->number
status: $purchaseOrder->statusState()->value
primaryParty: $purchaseOrder->vendor?->name ?? data_get($purchaseOrder->source_snapshot, 'vendor.name')
amount: (float) $purchaseOrder->total_amount
currency: $purchaseOrder->currency
href: "/purchase-orders/{$purchaseOrder->id}"
```

Delegate outcome callbacks to the four PO state mutation actions.

- [x] **Step 7: Register the handler and approval task filter**

Add the handler to `ApprovalSubjectRegistry` construction in `AppServiceProvider`. Add `purchase_order` to `ApprovalTaskController` subjectType validation.

- [x] **Step 8: Extend `PurchaseOrderResource` approval data and permissions**

Add:

```php
'approval' => [
    'approvalInstanceId' => $purchaseOrder->approval_instance_id !== null ? (string) $purchaseOrder->approval_instance_id : null,
    'submittedByUserId' => $purchaseOrder->approval_submitted_by_user_id !== null ? (string) $purchaseOrder->approval_submitted_by_user_id : null,
    'submittedAt' => $purchaseOrder->approval_submitted_at?->toISOString(),
    'approvedByUserId' => $purchaseOrder->approved_by_user_id !== null ? (string) $purchaseOrder->approved_by_user_id : null,
    'approvedAt' => $purchaseOrder->approved_at?->toISOString(),
    'rejectedByUserId' => $purchaseOrder->rejected_by_user_id !== null ? (string) $purchaseOrder->rejected_by_user_id : null,
    'rejectedAt' => $purchaseOrder->rejected_at?->toISOString(),
    'rejectedReason' => $purchaseOrder->rejected_reason,
    'changesRequestedByUserId' => $purchaseOrder->changes_requested_by_user_id !== null ? (string) $purchaseOrder->changes_requested_by_user_id : null,
    'changesRequestedAt' => $purchaseOrder->changes_requested_at?->toISOString(),
    'changesRequestedReason' => $purchaseOrder->changes_requested_reason,
    'changesRequestedFields' => $purchaseOrder->changes_requested_fields ?? [],
],
```

Add `permissions.canSubmitForApproval` for `ready_for_review` and `changes_requested` when policy allows.

- [x] **Step 9: Run focused API verification**

Run:

```bash
php artisan test --filter=PurchaseOrderReviewApprovalApiTest
php artisan test --filter=ApprovalTaskApiTest
```

Expected: PASS or only fail on OpenAPI/client drift that Task 4 resolves.

---

## Task 4: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under `packages/api-client/src/generated/**`
- Modify: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Modify: `apps/web/features/approvals/api/approval-api.ts`

- [x] **Step 1: Update OpenAPI schemas and path**

Add `POST /api/purchase-orders/{purchaseOrder}/submit-approval`, `SubmitPurchaseOrderApprovalRequest`, purchase-order status enum values, purchase-order approval fields, `canSubmitForApproval`, `purchase_order` approval subject type, and purchase-order approval task metadata.

- [x] **Step 2: Regenerate generated client**

Run:

```bash
pnpm generate:api
```

Expected: generated endpoint and schema files update under `packages/api-client/src/generated`.

- [x] **Step 3: Consume generated endpoint wrappers**

Update `purchase-order-api.ts` to expose `submitPurchaseOrderApproval(purchaseOrderId, payload, tenantId)` using the generated endpoint and `withActiveTenantHeader`.

- [x] **Step 4: Run contract checks**

Run:

```bash
pnpm check:api-contract
```

Expected: PASS.

---

## Task 5: Web Purchase Order Approval Workflow

**Files:**

- Modify: `apps/web/features/purchase-orders/components/purchase-order-actions.tsx`
- Create: `apps/web/features/purchase-orders/components/purchase-order-approval-panel.tsx`
- Modify: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`

- [x] **Step 1: Update purchase-order mock fixtures**

Add fixture states for `ready_for_review`, `in_review`, `changes_requested`, `approved`, and `rejected`. Include `approval` payload fields and `permissions.canSubmitForApproval`.

- [x] **Step 2: Add submit approval hook**

In `use-purchase-order-actions.ts`, add a mutation that calls `submitPurchaseOrderApproval`, invalidates PO queries, and maps API conflict errors into existing alert behavior.

- [x] **Step 3: Add approval panel component**

Create `PurchaseOrderApprovalPanel` with compact operational states:

- Ready: submit call to action.
- In review: show active approval instance id and locked-edit explanation.
- Changes requested: show reviewer reason and requested fields.
- Approved: show approval timestamp.
- Rejected: show rejection reason.

- [x] **Step 4: Integrate panel and edit disablement**

Place the approval panel above `PurchaseOrderActions` in the workspace. Disable draft edit controls unless status is `draft` or `changes_requested`.

- [x] **Step 5: Add web tests**

Cover submit success, submit conflict rendering, no submit action while in review, changes-requested reason display, and approved/rejected panel display.

- [x] **Step 6: Run focused purchase-order web tests**

Run:

```bash
pnpm --filter @cognify/web test -- purchase-order
```

Expected: PASS.

---

## Task 6: Web Approval Queue Purchase Order Subject Support

**Files:**

- Modify: `apps/web/features/approvals/forms/approval-policy-form.tsx`
- Modify: `apps/web/features/approvals/schemas/approval-policy-schema.ts`
- Modify: `apps/web/features/approvals/tables/approval-tasks-table.tsx`
- Modify: `apps/web/features/approvals/types/approval-view-model.ts`
- Modify: `apps/web/features/approvals/workflows/approval-queue-page.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Modify: `apps/web/features/approvals/mocks/approval-fixtures.ts`
- Modify: `apps/web/features/approvals/tests/approval-queue-workflow.test.tsx`
- Create: `apps/web/features/approvals/tests/approval-purchase-order-task-detail.test.tsx`

- [x] **Step 1: Add purchase-order subject fixture**

Add an approval task fixture with `subject.type = "purchase_order"`, `href = "/purchase-orders/po-1"`, `number = "PO-2026-SUSTAIN-REVIEW"`, vendor name, amount, currency, and metadata.

- [x] **Step 2: Update subject filters and labels**

Where approval UI lists subject types, add purchase orders as a selectable/filterable subject. Render purchase-order labels and links from the generated `subject` payload rather than hard-coded requisition/award assumptions.

- [x] **Step 3: Add approval web tests**

Assert the purchase-order approval task appears in the list, the detail view shows vendor/amount metadata, and the task link points to `/purchase-orders/po-1`.

- [x] **Step 4: Run focused approval web tests**

Run:

```bash
pnpm --filter @cognify/web test -- approval
```

Expected: PASS.

---

## Task 7: Demo Data, Integration Gates, Visual Inspection, And PR Readiness

**Files:**

- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`
- Modify after merge: `docs/01-product/feature-roadmap.md`

- [x] **Step 1: Seed a PO approval policy and reviewed PO examples**

Update demo lifecycle seeding to create a `purchase_order` approval policy and at least one PO in each visible review state: ready for review, in review, changes requested, and approved.

- [x] **Step 2: Verify demo seeder expectations**

Run:

```bash
php artisan test --filter=DemoSeederTest
```

Expected: PASS with assertions for the new policy and PO states.

- [x] **Step 3: Run final API and contract gates**

Run:

```bash
php artisan test --filter=PurchaseOrderReviewApprovalApiTest
php artisan test --filter=ApprovalTaskApiTest
pnpm generate:api
pnpm check:api-contract
```

Expected: PASS for all commands and no unexpected generated drift after the final generation.

- [x] **Step 4: Run final web gates**

Run:

```bash
pnpm --filter @cognify/web test -- purchase-order
pnpm --filter @cognify/web test -- approval
pnpm --filter @cognify/web typecheck
```

Expected: PASS.

- [x] **Step 5: Visual inspection**

Start real local services and app with seeded data:

```bash
pnpm dev:reset
pnpm --filter @cognify/web dev
```

Capture desktop and smaller-viewport screenshots of the PO workspace in ready, in-review, changes-requested, approved, and rejected states, plus approval task detail for a PO task. Critique density, labels, disabled states, reviewer reason visibility, responsive behavior, and whether the approval workflow is clear enough for enterprise procurement users.

- [x] **Step 6: CodeRabbit review**

Run no more than two review cycles and leave at least 15 minutes between starts:

```bash
coderabbit review --agent --type all
```

Apply valid findings only, then re-run affected verification commands.

- [ ] **Step 7: Commit, push, and open PR**

Commit all feature changes:

```bash
git add docs/superpowers/specs/2026-06-09-purchase-order-review-approval-design.md docs/superpowers/plans/2026-06-09-purchase-order-review-approval.md apps/api apps/web packages/api-client
git commit -m "feat: add purchase order review approval"
git push -u origin goal-feature/p1-37-po-review-approval
```

Open a ready-for-review PR with spec path, plan path, tests run, screenshot evidence, and CodeRabbit summary.

- [ ] **Step 8: PR review loop and merge**

Wait 10-15 minutes for review comments and checks. Retrieve unresolved comments, apply valid fixes, push updates, and repeat until no blockers remain. Merge the PR, checkout `main`, pull `origin main`, and confirm the merge is present.

- [ ] **Step 9: Roadmap follow-up**

If the roadmap row was not updated in the feature PR, create a follow-up docs change marking `P1-37` as `Fully Implemented` with this spec, this plan, PR number, and a note that PO approval uses shared approval tasks and gates supplier issue on approved POs.

---

## Self-Review Checklist

- Spec coverage: tasks cover PO states, submit route, shared approval subject registration, approval outcomes, OpenAPI/client generation, web PO workspace, approval queue, demo data, verification, visual inspection, review, PR, merge, and roadmap update.
- Placeholder scan: no task contains deferred implementation language; every task names exact files, commands, and expected outcomes.
- Type consistency: subject type is consistently `purchase_order`; route is consistently `/api/purchase-orders/{purchaseOrder}/submit-approval`; request is consistently `SubmitPurchaseOrderApprovalRequest`; status values match the design.

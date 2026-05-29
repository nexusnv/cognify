# P1 Medium Audit Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate the remaining P1 audit findings M1-M4 and M6-M9 so approval lifecycle, audit visibility, portal analytics, and roadmap status are reliable enough for real-user testing.

**Architecture:** Keep approval workflow behavior in `apps/api/Domains/Approval`, collaboration behavior in `apps/api/Domains/Collaboration`, vendor portal behavior in `apps/api/Domains/Quotation`, and roadmap/status truth in `docs`. Use existing `ApprovalSubjectHandler` as the extension point for subject-specific approval lifecycle behavior, and keep OpenAPI/generated client changes flowing through `@cognify/api-client`.

**Tech Stack:** Laravel 12 API, Sanctum feature tests, Next.js/React frontend, TanStack Query, MSW, Orval-generated API client, Vitest.

---

## Scope

Included:

- M1: Approval delegation and escalation must work for requisition and RFQ award recommendation subjects through `ApprovalSubjectHandler`.
- M2: Reject and request-changes must present terminal/future tasks accurately.
- M3: Approval task comments must exist or the roadmap claim must be downgraded. This plan implements comments.
- M4: Approval policy admin UI must support meaningful P1 policy authoring for requisition and award policies.
- M6: Reconcile docs/specs around automatic PO handoff creation after award approval.
- M7: Vendor portal view audit must count package views, not every token resolution.
- M8: Multi-file upload promise must be clarified or implemented. This plan clarifies the current repeated single-file behavior and adds UI copy/tests to make it explicit.
- M9: Audit filtering must support P1 subjects beyond requisition.

Excluded:

- U1-U6 UI/UX refactors.
- M12-M15.
- Full audit-pack generation.
- True batch multi-file upload request handling. That can be a later enhancement if product wants one HTTP request containing many files.

## Execution Order

1. Approval lifecycle API correctness: M1 and M2.
2. Approval comments: M3.
3. Policy admin UI: M4.
4. Portal/audit/docs correctness: M6-M9.
5. Contract regeneration and full verification.

The first two tasks touch the most shared approval state and should be completed before frontend policy/admin work.

---

## Task 1: Subject-Aware Delegation And Escalation (M1)

**Files:**

- Modify: `apps/api/Domains/Approval/Contracts/ApprovalSubjectHandler.php`
- Modify: `apps/api/Domains/Approval/SubjectHandlers/RequisitionApprovalSubjectHandler.php`
- Modify: `apps/api/Domains/Approval/SubjectHandlers/RfqAwardRecommendationApprovalSubjectHandler.php`
- Modify: `apps/api/Domains/Approval/Actions/DelegateApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/EscalateOverdueApprovalTasks.php`
- Test: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`
- Test: `apps/api/tests/Feature/ApprovalDelegationApiTest.php`

- [ ] **Step 1: Add failing delegation coverage for award approval tasks**

Add a feature test proving an assigned award approval task can be delegated, the delegate receives the assignment, and the notification subject is the award recommendation, not a requisition.

Run:

```bash
php artisan test --filter='award approval task can be delegated'
```

Expected: FAIL because delegation notification/subject behavior is still requisition-specific.

- [ ] **Step 2: Add failing escalation coverage for award approval tasks**

Add a feature test proving an overdue RFQ award recommendation task escalates to fallback approvers, creates an actionable escalated task, records `approval_task.escalated`, and notifies the fallback assignee with an award-specific subject label.

Run:

```bash
php artisan test --filter='overdue award approval task escalates through subject handler'
```

Expected: FAIL because escalation notification is currently guarded by `instanceof Requisition`.

- [ ] **Step 3: Extend the approval subject contract**

Add subject lifecycle methods to `ApprovalSubjectHandler`:

```php
public function canDelegateTo(Model $subject, User $delegate): bool;

public function delegationValidationMessage(Model $subject): string;

/**
 * @return iterable<int, User>
 */
public function escalationFallbackRecipients(Tenant $tenant, Model $subject, array $stageTemplate): iterable;
```

Implement requisition behavior:

```php
public function canDelegateTo(Model $subject, User $delegate): bool
{
    return ! $subject instanceof Requisition || (int) $subject->requester_id !== (int) $delegate->id;
}

public function delegationValidationMessage(Model $subject): string
{
    return 'The delegate cannot be the requester of the requisition.';
}
```

Implement award behavior:

```php
public function canDelegateTo(Model $subject, User $delegate): bool
{
    return true;
}

public function delegationValidationMessage(Model $subject): string
{
    return 'The selected delegate cannot approve this subject.';
}
```

- [ ] **Step 4: Use `ApprovalSubjectRegistry` in delegation**

Inject `ApprovalSubjectRegistry` into `DelegateApprovalTask`.

Replace hard-coded requisition checks and notification subject construction with:

```php
$handler = $this->subjects->forSubject($task->subject);

if (! $handler->canDelegateTo($task->subject, $delegation->delegate)) {
    throw ValidationException::withMessages([
        'approvalDelegationId' => [$handler->delegationValidationMessage($task->subject)],
    ]);
}
```

Use existing handler methods for notifications:

```php
body: $handler->notificationBody($subject),
subject: $subject,
subjectLabel: $handler->notificationSubjectLabel($subject),
```

- [ ] **Step 5: Use `ApprovalSubjectRegistry` in escalation**

Inject `ApprovalSubjectRegistry` into `EscalateOverdueApprovalTasks`.

After resolving `$subject = $task->subject`, use:

```php
$handler = $this->subjects->forSubject($subject);
$fallbackAssignee = $this->fallbackAssigneeForTask($tenant, $task, $stageTemplate, $handler);
```

When notifying fallback approvers, use handler methods instead of requisition-only fields.

- [ ] **Step 6: Run task verification**

Run:

```bash
php artisan test --filter=ApprovalDelegationApiTest
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=ApprovalSlaApiTest
```

Expected: all pass.

---

## Task 2: Terminal Task Semantics And Approval Summary Accuracy (M2)

**Files:**

- Modify: `apps/api/Domains/Approval/Actions/RejectApprovalTask.php`
- Modify: `apps/api/Domains/Approval/Actions/RequestApprovalChanges.php`
- Modify: `apps/api/Domains/Approval/Http/Resources/ApprovalSummaryResource.php`
- Test: `apps/api/tests/Feature/RequisitionApprovalApiTest.php`
- Test: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`

- [ ] **Step 1: Add failing tests for future blocked tasks after rejection**

Create a multi-stage approval route, reject stage 1, then assert:

- instance status is rejected,
- active sibling tasks are cancelled,
- future blocked-stage tasks are cancelled or marked non-actionable,
- `completedDecisions` contains only real decisions.

Run:

```bash
php artisan test --filter='rejecting approval cancels future blocked tasks'
```

Expected: FAIL if blocked tasks still render as completed decisions.

- [ ] **Step 2: Add failing tests for request-changes**

Repeat the same shape for request-changes:

```bash
php artisan test --filter='requesting changes cancels future blocked tasks'
```

Expected: FAIL until terminal task cleanup is applied consistently.

- [ ] **Step 3: Centralize terminal cleanup**

Create a private method in both actions or a small service under `apps/api/Domains/Approval/Actions/Concerns/TerminatesApprovalInstanceTasks.php`:

```php
private function cancelRemainingTasks(ApprovalInstance $instance, ApprovalTask $decidingTask): void
{
    ApprovalTask::query()
        ->where('approval_instance_id', $instance->id)
        ->whereKeyNot($decidingTask->id)
        ->whereIn('status', [
            ApprovalTaskStatus::Active->value,
            ApprovalTaskStatus::Blocked->value,
        ])
        ->update([
            'status' => ApprovalTaskStatus::Cancelled->value,
            'updated_at' => now(),
        ]);
}
```

- [ ] **Step 4: Fix summary rendering**

In `ApprovalSummaryResource`, change completed decisions to include only tasks with actual decisions:

```php
$completedDecisions = $this->tasks
    ->filter(fn ($task): bool => $task->decision !== null && $task->decided_at !== null);
```

Expose cancelled/future counts separately if needed:

```php
'taskCounts' => [
    'active' => $this->tasks->where('status', ApprovalTaskStatus::Active)->count(),
    'blocked' => $this->tasks->where('status', ApprovalTaskStatus::Blocked)->count(),
    'cancelled' => $this->tasks->where('status', ApprovalTaskStatus::Cancelled)->count(),
],
```

If `taskCounts` changes OpenAPI, regenerate the contract.

- [ ] **Step 5: Run task verification**

Run:

```bash
php artisan test --filter=RequisitionApprovalApiTest
php artisan test --filter=RfqAwardApprovalApiTest
pnpm check:api-contract
```

Expected: all pass.

---

## Task 3: Approval Task Comments (M3)

**Files:**

- Create: `apps/api/Domains/Collaboration/Http/Controllers/ApprovalTaskCommentController.php`
- Modify: `apps/api/Domains/Collaboration/Actions/CreateCollaborationComment.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `packages/api-client/src/generated/*` via `pnpm check:api-contract`
- Create: `apps/web/features/approvals/components/approval-task-comments.tsx`
- Create: `apps/web/features/approvals/hooks/use-approval-task-comments.ts`
- Modify: `apps/web/features/approvals/workflows/approval-task-detail-page.tsx`
- Test: `apps/api/tests/Feature/ApprovalTaskCommentApiTest.php`
- Test: `apps/web/features/approvals/tests/approval-task-comments.test.tsx`

- [ ] **Step 1: Add failing API tests**

Cover:

- assigned approver can list/create task comments,
- tenant admin can list task comments,
- non-visible tenant member cannot comment,
- comments are tenant scoped,
- comments create audit/activity entries.

Run:

```bash
php artisan test --filter=ApprovalTaskCommentApiTest
```

Expected: FAIL because routes/controllers do not exist.

- [ ] **Step 2: Generalize comment creation**

Update `CreateCollaborationComment` so it accepts a generic `Model $subject` instead of only `Requisition`, while preserving existing requisition behavior:

```php
public function handle(Tenant $tenant, User $actor, Model $subject, array $data): CollaborationComment
```

Use `AuditSubject::typeFor($subject)` for public subject naming.

- [ ] **Step 3: Add approval task comment controller**

Implement:

```php
Route::get('/approval-tasks/{approvalTask}/comments', [ApprovalTaskCommentController::class, 'index']);
Route::post('/approval-tasks/{approvalTask}/comments', [ApprovalTaskCommentController::class, 'store']);
```

Controller authorization:

```php
$task = ApprovalTask::query()
    ->where('tenant_id', $tenant->id)
    ->findOrFail($approvalTask);

$this->authorize('view', $task);
```

- [ ] **Step 4: Register audit subject type**

In `AppServiceProvider`, register:

```php
AuditSubject::registerType(ApprovalTask::class, 'approval_task');
```

- [ ] **Step 5: Add web comments component**

Build `ApprovalTaskComments` with the same interaction model as `RequisitionComments`:

- list comments,
- empty state,
- textarea,
- submit button,
- mutation invalidates the task comments query.

Do not import requisition fixtures into approval components.

- [ ] **Step 6: Regenerate contract and run tests**

Run:

```bash
php artisan test --filter=ApprovalTaskCommentApiTest
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/approvals/tests/approval-task-comments.test.tsx
```

Expected: all pass.

---

## Task 4: Approval Policy Admin UI Depth (M4)

**Files:**

- Modify: `apps/web/features/approvals/schemas/approval-policy-schema.ts`
- Modify: `apps/web/features/approvals/forms/approval-policy-form.tsx`
- Modify: `apps/web/features/approvals/components/approval-policy-preview.tsx`
- Modify: `apps/web/features/approvals/workflows/approval-policy-detail-page.tsx`
- Modify: `apps/web/features/approvals/types/approval-view-model.ts`
- Test: `apps/web/features/approvals/tests/approval-policy-schema.test.ts`
- Test: `apps/web/features/approvals/tests/approval-policy-preview.test.tsx`
- Create: `apps/web/features/approvals/tests/approval-policy-form.test.tsx`

- [ ] **Step 1: Add schema tests for award policies**

Test that the form accepts:

```ts
{
  subjectType: "rfq_award_recommendation",
  rules: [
    { field: "recommendedAmount", operator: "gte", value: 10000 },
    { field: "riskClassification", operator: "equals", value: "high" }
  ],
  routeTemplate: {
    stages: [
      {
        name: "Commercial review",
        completionRule: "all",
        approvers: [{ type: "role", role: "buyer", label: "Buyer" }],
        fallbackApprovers: [{ type: "role", role: "admin", label: "Admin" }]
      }
    ]
  }
}
```

Run:

```bash
pnpm --dir apps/web exec vitest run features/approvals/tests/approval-policy-schema.test.ts
```

Expected: FAIL until schema supports the richer shape.

- [ ] **Step 2: Expand schema field catalog**

Add subject-aware field catalogs:

```ts
const requisitionRuleFields = ["amount", "department", "costCenter", "projectId", "riskClassification"] as const;
const awardRuleFields = [
  "recommendedAmount",
  "recommendedCurrency",
  "recommendedVendorId",
  "scorecardWeightedTotal",
  "riskSummaryPresent",
  "exceptionSummaryPresent",
] as const;
```

Validate fields based on `subjectType`.

- [ ] **Step 3: Add route-template editing controls**

The form must expose:

- subject type selector,
- rules editor,
- stage name,
- completion rule,
- approver type/user/role,
- fallback approver type/user/role,
- SLA escalation hours.

Use existing shadcn/Radix controls where available; keep this operational and dense, not a marketing layout.

- [ ] **Step 4: Improve preview context**

For award policies, send preview context with `subjectType: "rfq_award_recommendation"` and award fields. The preview panel must show matched policy, matched version, warnings, stages, and fallback approvers.

- [ ] **Step 5: Run UI verification**

Run:

```bash
pnpm --dir apps/web exec vitest run features/approvals/tests/approval-policy-schema.test.ts features/approvals/tests/approval-policy-preview.test.tsx features/approvals/tests/approval-policy-form.test.tsx
pnpm --filter @cognify/web typecheck
```

Expected: all pass.

---

## Task 5: Reconcile Award Approval And PO Handoff Docs (M6)

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/specs/2026-05-17-approval-orchestration-design.md`
- Modify: `docs/superpowers/plans/2026-05-17-approval-orchestration.md`
- Modify or create: `docs/07-history/2026-05-28-p1-medium-audit-remediation-notes.md`

- [ ] **Step 1: Decide product truth**

Use this product decision:

```text
After P1-34, automatic PO handoff creation on final award approval is intentional. Approval remains the decision boundary; PO handoff remains the operational handoff boundary. The automatic creation is idempotent and creates a draft handoff only after approval.
```

- [ ] **Step 2: Update roadmap wording**

Adjust P1-33/P1-34 notes to say the handoff is auto-created after final award approval, while explicit GET reads and POST creation/reveal semantics remain safe.

- [ ] **Step 3: Add history note**

Record:

- audit finding M6,
- chosen product decision,
- exact behavior after remediation,
- tests that prove idempotent auto-creation.

- [ ] **Step 4: Run doc verification**

Run:

```bash
rg "should not create PO handoff|must not create PO handoff|auto-creates PO handoff" docs
```

Expected: no stale claim that award approval must never create a handoff.

---

## Task 6: Vendor Portal View Audit Counting (M7)

**Files:**

- Modify: `apps/api/Domains/Quotation/Actions/ResolveRfqInvitationPortalAccess.php`
- Create: `apps/api/Domains/Quotation/Actions/RecordRfqInvitationPortalView.php`
- Modify: vendor portal controllers that call `ResolveRfqInvitationPortalAccess`
- Test: `apps/api/tests/Feature/VendorPortalRfqInvitationApiTest.php`

- [ ] **Step 1: Add failing tests**

Cover:

- opening the vendor package increments `portal_view_count` and records `rfq_invitation.portal_viewed`,
- quotation lookup does not increment view count,
- version listing does not increment view count,
- upload does not increment view count,
- manual-entry save does not increment view count.

Run:

```bash
php artisan test --filter='portal view audit'
```

Expected: FAIL because token resolution currently writes every time.

- [ ] **Step 2: Make token resolution read-only**

Change `ResolveRfqInvitationPortalAccess::handle()` so it only validates and returns the invitation. It must not lock for update, increment counters, or record audit events.

- [ ] **Step 3: Add explicit view recorder**

Create `RecordRfqInvitationPortalView`:

```php
public function handle(RfqInvitation $invitation, Request $request): RfqInvitation
{
    return DB::transaction(function () use ($invitation, $request): RfqInvitation {
        $locked = RfqInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
        $locked->forceFill([
            'portal_last_viewed_at' => now(),
            'portal_view_count' => (int) $locked->portal_view_count + 1,
        ])->save();

        $this->audit->record(/* rfq_invitation.portal_viewed */);

        return $locked->refresh()->load(['tenant', 'vendor', 'rfq']);
    });
}
```

- [ ] **Step 4: Call recorder only from the package-open route**

Identify the controller method that serves the main vendor package view and call `RecordRfqInvitationPortalView` there only.

- [ ] **Step 5: Run verification**

Run:

```bash
php artisan test --filter=VendorPortalRfqInvitationApiTest
```

Expected: all pass.

---

## Task 7: Clarify Multi-File Upload Promise (M8)

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
- Modify: `apps/web/features/sourcing/components/*upload*.tsx` or the actual upload component path found by `rg "Buyer-received quotation file" apps/web/features/sourcing`
- Test: `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`
- Test: `apps/api/tests/Feature/QuotationVersionApiTest.php`

- [ ] **Step 1: Clarify implementation decision**

Use this product decision:

```text
P1 supports multiple files by repeated single-file uploads. One multipart request containing multiple files is out of scope until a later batch-upload enhancement.
```

- [ ] **Step 2: Update docs**

Replace ambiguous "one or more files" wording with "repeat upload for each file" where the current implementation is described.

- [ ] **Step 3: Add UI label/helper text if missing**

The upload UI should make repeated uploads clear without adding instructional clutter. Prefer concise text near the upload control:

```tsx
<p className="text-xs text-muted-foreground">Upload one file at a time.</p>
```

- [ ] **Step 4: Add regression tests**

Add or confirm tests that upload two files in sequence and assert both appear as separate attachments/versions as designed.

Run:

```bash
php artisan test --filter=QuotationVersionApiTest
pnpm --dir apps/web exec vitest run features/sourcing/tests/rfq-invitations-workflow.test.tsx
```

Expected: all pass.

---

## Task 8: P1 Audit Subject Filtering (M9)

**Files:**

- Modify: `apps/api/app/Audit/AuditSubject.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/app/Audit/Http/Controllers/AuditEventController.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `packages/api-client/src/generated/*` via `pnpm check:api-contract`
- Test: `apps/api/tests/Feature/AuditApiTest.php`

- [ ] **Step 1: Add failing audit filter tests**

Add data-provider tests for:

```php
[
    'requisition',
    'attachment',
    'project',
    'rfq',
    'rfq_invitation',
    'quotation',
    'quotation_version',
    'quotation_normalization',
    'scorecard',
    'award',
    'approval_task',
    'po_handoff',
]
```

Run:

```bash
php artisan test --filter='audit feed filters p1 subject types'
```

Expected: FAIL because the controller currently validates only `requisition`.

- [ ] **Step 2: Centralize allowed public subject types**

Add to `AuditSubject`:

```php
/**
 * @return array<int, string>
 */
public static function publicTypes(): array
{
    return array_values(static::$typeMap);
}
```

- [ ] **Step 3: Register all P1 subjects**

In `AppServiceProvider::boot()`, register model mappings for project, RFQ, invitation, quotation, quotation version, normalization, scorecard, award recommendation, approval task, and PO handoff.

- [ ] **Step 4: Use centralized validation**

Change `AuditEventController` validation:

```php
'subjectType' => ['sometimes', 'string', Rule::in(AuditSubject::publicTypes())],
```

If `classFor()` returns null after validation, return a 422 validation error rather than silently filtering to null.

- [ ] **Step 5: Regenerate contract and verify**

Run:

```bash
php artisan test --filter=AuditApiTest
pnpm check:api-contract
```

Expected: all pass.

---

## Final Verification

Run these before claiming the work is complete:

```bash
php artisan test --filter=ApprovalDelegationApiTest
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=RequisitionApprovalApiTest
php artisan test --filter=ApprovalSlaApiTest
php artisan test --filter=ApprovalTaskCommentApiTest
php artisan test --filter=VendorPortalRfqInvitationApiTest
php artisan test --filter=QuotationVersionApiTest
php artisan test --filter=AuditApiTest
pnpm --dir apps/web exec vitest run features/approvals/tests/approval-task-comments.test.tsx features/approvals/tests/approval-policy-schema.test.ts features/approvals/tests/approval-policy-preview.test.tsx features/approvals/tests/approval-policy-form.test.tsx features/sourcing/tests/rfq-invitations-workflow.test.tsx
pnpm --filter @cognify/web typecheck
pnpm check:api-contract
git diff --check
semgrep scan --config auto --error --exclude node_modules --exclude vendor --exclude .next --exclude storage/framework .
trivy fs --scanners vuln,secret,misconfig --exit-code 1 --severity HIGH,CRITICAL --skip-dirs .worktrees --skip-dirs apps/api/vendor --skip-dirs node_modules --skip-dirs .git --skip-dirs apps/api/storage/framework --skip-dirs apps/web/.next .
composer audit
```

Expected:

- API tests pass.
- Web focused tests pass.
- Web typecheck passes.
- API contract check passes without regenerated drift.
- Semgrep has 0 blocking findings.
- Trivy has 0 HIGH/CRITICAL findings in the scoped repo scan.
- Composer audit reports no advisories.
- `git diff --check` exits 0.

## Suggested Subagent Split

- Agent 1: M1-M2 approval lifecycle API.
- Agent 2: M3 approval comments API and web component.
- Agent 3: M4 approval policy admin UI.
- Agent 4: M6-M9 docs, vendor portal audit, upload promise, and audit filters.

Do not let Agent 1 and Agent 2 modify `ApprovalTaskResource` or approval routes at the same time without coordination.

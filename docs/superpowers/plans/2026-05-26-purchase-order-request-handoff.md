# Purchase Order Request Handoff Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-34 so approved RFQ award recommendations automatically create a tenant-scoped purchase-order request handoff that buyers/admins can review, mark ready, export as JSON or CSV, and discover through global search.

**Architecture:** Add a focused `apps/api/Domains/PurchaseOrder` domain that owns handoff state, snapshots, export generation, policy checks, resources, and audit events. Keep award recommendation approval in `apps/api/Domains/Quotation`, but call the PurchaseOrder action from `MarkRfqAwardRecommendationApproved` so creation is synchronous and idempotent. Expose OpenAPI-backed endpoints, regenerate `@cognify/api-client`, extend the existing award recommendation workspace, and add a search provider following the tenant/role discipline in `apps/api/Domains/Search/Providers/RequisitionSearchProvider.php`.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped domain actions, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md`.
- Required grounding file: `apps/api/Domains/Search/Providers/RequisitionSearchProvider.php`.
  - Use its `tenant_id` base query, role-aware visibility, normalized search constraints, and deterministic ranking pattern for the new PO handoff provider.
- Runbook: `docs/05-runbooks/feature-development.md`.
  - Follow workflow-first, contract-first slicing: workflow map, workspace UX/MSW, API contract, backend actions, real integration, hardening.
- Roadmap: `docs/01-product/feature-roadmap.md`.
  - P1-34 remains `Not Implemented` until this plan is fully executed and verified.
  - Do not implement P1-35 Procurement Calendar, P3-21 Purchase Order Status Sync, or real ERP integration.
- Predecessor context:
  - P1-32 recommendation and P1-33 award approval already use `RfqAwardRecommendation` in `apps/api/Domains/Quotation`.
  - Approval completion currently calls `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php`.
  - Existing award recommendation UI lives in `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`.

## Scope Boundaries

Implement:

- `PurchaseOrderRequestHandoff` model, status enum, policy, actions, resources, controller, routes, and migration.
- Automatic draft handoff creation when `RfqAwardRecommendation` becomes `approved`.
- Handoff review/update, mark-ready, cancel, JSON export, CSV export.
- Audit events for created, updated, ready, exported, and cancelled.
- OpenAPI schemas/endpoints and generated client updates.
- Web API wrappers, hooks, MSW fixtures/handlers, and an award-workspace PO handoff panel.
- Global search support for PO handoffs, including API provider, OpenAPI type enum, client contract, MSW fixtures, and command palette tests.
- Focused API/web regression tests and roadmap update after verification.

Do not implement:

- ERP adapters, external submissions, finance queue, official PO number issuance, PO status sync, receipt/invoice/payment, vendor notifications, split awards, contract lifecycle handoff, procurement calendar, or configurable export mapping templates.

## File Map

### API PurchaseOrder Domain

- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderRequestHandoffStatus.php`
- Create: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderRequestHandoffPolicy.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CreateOrRevealPurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/BuildPurchaseOrderRequestHandoffSnapshot.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderRequestHandoffReady.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CancelPurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/ExportPurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderRequestHandoffController.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/UpdatePurchaseOrderRequestHandoffRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/MarkPurchaseOrderRequestHandoffReadyRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/CancelPurchaseOrderRequestHandoffRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderRequestHandoffResource.php`
- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderRequestHandoffNumber.php`
- Modify: `apps/api/database/migrations/*_create_purchase_order_request_handoffs_table.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/app/Audit/AuditSubject.php` through `AppServiceProvider` registration if the project prefers runtime registration.
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php`

### API Search

- Create: `apps/api/Domains/Search/Providers/PurchaseOrderRequestHandoffSearchProvider.php`
- Modify: `apps/api/Domains/Search/Services/SearchService.php`
- Modify: `apps/api/Domains/Search/Http/Requests/SearchRequest.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `apps/api/tests/Feature/SearchApiTest.php`

### API Tests

- Create: `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php`
- Modify: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`
- Modify: `apps/api/tests/Feature/SearchApiTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` by running `pnpm generate:api`.
- Expected new operation IDs:
  - `showRfqAwardRecommendationPoHandoff`
  - `createRfqAwardRecommendationPoHandoff`
  - `updatePurchaseOrderRequestHandoff`
  - `markPurchaseOrderRequestHandoffReady`
  - `cancelPurchaseOrderRequestHandoff`
  - `exportPurchaseOrderRequestHandoffJson`
- CSV export helper should be handwritten in web code because it returns `text/csv`.

### Web

- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Create: `apps/web/features/quotations/components/rfq-award-po-handoff-panel.tsx`
- Modify: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Modify: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`
- Modify: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`
- Modify: `apps/web/features/search/search-contract.ts`
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
- Modify: `apps/web/features/search/mocks/search-handlers.ts`
- Modify: `apps/web/features/search/tests/command-palette.test.tsx`

### Docs

- Modify: `docs/01-product/feature-roadmap.md` only after implementation verification passes.
- Modify this plan during execution by checking completed boxes.

---

## Task 1: API Regression Tests For PO Handoff Workflow

**Files:**

- Create: `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php`
- Modify: `apps/api/tests/Feature/RfqAwardApprovalApiTest.php`

- [x] **Step 1: Create failing feature test file**

Create `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php` with these scenario names. Reuse tenant/auth/RFQ/recommendation fixture patterns from `RfqAwardApprovalApiTest.php` and quotation line-item setup from quotation version tests.

```php
<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderRequestHandoffApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_award_recommendation_auto_creates_draft_po_handoff(): void {}
    public function test_award_approval_callback_is_idempotent_for_po_handoff_creation(): void {}
    public function test_buyer_can_create_or_reveal_handoff_for_already_approved_recommendation(): void {}
    public function test_non_approved_recommendations_cannot_create_handoffs(): void {}
    public function test_handoff_snapshot_contains_source_line_approval_and_evidence_details(): void {}
    public function test_buyer_can_update_optional_handoff_fields_with_lock_version(): void {}
    public function test_ready_action_validates_blockers_and_records_ready_actor(): void {}
    public function test_json_export_returns_structured_payload_and_marks_handoff_exported(): void {}
    public function test_csv_export_returns_expected_headers_and_line_rows(): void {}
    public function test_repeat_export_records_audit_without_duplicate_handoff(): void {}
    public function test_cancelled_handoff_cannot_be_exported(): void {}
    public function test_cross_tenant_view_update_ready_export_and_cancel_fail(): void {}
    public function test_requester_and_vendor_like_users_cannot_access_handoff_endpoints(): void {}
    public function test_handoff_routes_require_real_session_auth_and_tenant_context(): void {}
}
```

- [x] **Step 2: Fill the approval auto-create assertion**

Use a helper that creates an approved-ready recommendation with a selected vendor, quotation, current version, line item, approval instance, and buyer/admin actor. The first test must assert this shape after approving the final task or directly exercising the approval callback through the existing award approval route:

```php
$this->assertDatabaseHas('purchase_order_request_handoffs', [
    'tenant_id' => $tenant->id,
    'rfq_award_recommendation_id' => $recommendation->id,
    'rfq_id' => $rfq->id,
    'vendor_id' => $vendor->id,
    'quotation_id' => $quotation->id,
    'quotation_version_id' => $version->id,
    'status' => 'draft',
    'currency' => 'MYR',
    'total_amount' => '131100.00',
]);

$handoff = PurchaseOrderRequestHandoff::query()->firstOrFail();

$this->assertSame('POH-', substr($handoff->number, 0, 4));
$this->assertSame('RFQ-2026-POH', data_get($handoff->source_snapshot, 'rfq.number'));
$this->assertSame('Northwind Traders', data_get($handoff->source_snapshot, 'vendor.name'));
$this->assertSame('Pallet rack bay', data_get($handoff->line_snapshot, '0.description'));
```

- [x] **Step 3: Fill endpoint behavior tests**

Use these endpoint assertions:

```php
$this->actingAsTenant($tenant, $buyer)
    ->getJson("/api/rfqs/{$rfq->id}/award-recommendation/po-handoff")
    ->assertOk()
    ->assertJsonPath('data.status', 'draft')
    ->assertJsonPath('data.source.rfq.number', 'RFQ-2026-POH')
    ->assertJsonPath('data.lines.0.description', 'Pallet rack bay')
    ->assertJsonPath('data.permissions.canMarkReady', true);

$this->actingAsTenant($tenant, $buyer)
    ->patchJson("/api/po-handoffs/{$handoff->id}", [
        'lockVersion' => $handoff->lock_version,
        'requestedPoDate' => '2026-06-15',
        'deliveryAttention' => 'Warehouse receiving',
        'financeNote' => 'Charge to expansion budget.',
        'exportMemo' => 'Upload to ERP batch MY-0626.',
    ])
    ->assertOk()
    ->assertJsonPath('data.review.requestedPoDate', '2026-06-15')
    ->assertJsonPath('data.review.financeNote', 'Charge to expansion budget.');

$this->actingAsTenant($tenant, $buyer)
    ->postJson("/api/po-handoffs/{$handoff->id}/ready", [
        'lockVersion' => $handoff->fresh()->lock_version,
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'ready')
    ->assertJsonPath('data.readyByUserId', (string) $buyer->id);
```

- [x] **Step 4: Fill export tests**

Assert JSON and CSV exports:

```php
$this->actingAsTenant($tenant, $buyer)
    ->getJson("/api/po-handoffs/{$handoff->id}/export.json")
    ->assertOk()
    ->assertJsonPath('format', 'json')
    ->assertJsonPath('handoff.number', $handoff->number)
    ->assertJsonPath('handoff.lines.0.description', 'Pallet rack bay');

$this->actingAsTenant($tenant, $buyer)
    ->get("/api/po-handoffs/{$handoff->id}/export.csv", ['X-Tenant-Id' => (string) $tenant->id])
    ->assertOk()
    ->assertHeader('content-type', 'text/csv; charset=UTF-8')
    ->assertSee('handoff_number,')
    ->assertSee('Pallet rack bay');
```

- [x] **Step 5: Add real session route-stack test**

The real route-stack test must not use `actingAs()` for the verified part. It must call:

```txt
GET /sanctum/csrf-cookie
POST /api/auth/login
GET /api/rfqs/{rfq}/award-recommendation/po-handoff
POST /api/po-handoffs/{handoff}/ready
GET /api/po-handoffs/{handoff}/export.json
POST /api/auth/logout
GET /api/rfqs/{rfq}/award-recommendation/po-handoff
```

Expected after logout: `401`.

- [x] **Step 6: Run failing API tests**

Run:

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
```

Expected: failures for missing table/model/routes/classes. Do not implement code before confirming these failures.

---

## Task 2: PurchaseOrder Data Model, Policy, Resource, And Search Provider Tests

**Files:**

- Create: `apps/api/database/migrations/2026_05_26_200000_create_purchase_order_request_handoffs_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderRequestHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderRequestHandoffStatus.php`
- Create: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderRequestHandoffPolicy.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderRequestHandoffResource.php`
- Create: `apps/api/Domains/Search/Providers/PurchaseOrderRequestHandoffSearchProvider.php`
- Modify: `apps/api/Domains/Search/Services/SearchService.php`
- Modify: `apps/api/Domains/Search/Http/Requests/SearchRequest.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/app/Audit/AuditSubject.php` or register audit type in `AppServiceProvider`
- Modify: `apps/api/tests/Feature/SearchApiTest.php`

- [x] **Step 1: Write failing search provider tests**

Extend `SearchApiTest` with:

```php
public function test_buyer_can_search_purchase_order_handoffs_by_number_rfq_vendor_and_status(): void
{
    [$tenant, $buyer] = $this->tenantUser('buyer');
    $vendor = $this->createVendor($tenant, ['name' => 'Northwind Traders']);
    $rfq = $this->createRfq($tenant, ['number' => 'RFQ-2026-POH', 'title' => 'Warehouse racking']);
    $handoff = $this->createPurchaseOrderRequestHandoff($tenant, [
        'number' => 'POH-2026-000001',
        'status' => 'ready',
        'rfq_id' => $rfq->id,
        'vendor_id' => $vendor->id,
        'source_snapshot' => ['rfq' => ['number' => $rfq->number], 'vendor' => ['name' => $vendor->name]],
    ]);

    $response = $this->actingAsTenant($tenant, $buyer)
        ->getJson('/api/search?query='.urlencode('POH-2026').'&types[]=po_handoff&limit=10');

    $response->assertOk()
        ->assertJsonPath('meta.returned', 1)
        ->assertJsonPath('data.0.type', 'po_handoff')
        ->assertJsonPath('data.0.id', (string) $handoff->id)
        ->assertJsonPath('data.0.title', 'POH-2026-000001')
        ->assertJsonPath('data.0.subtitle', 'Northwind Traders')
        ->assertJsonPath('data.0.status', 'ready')
        ->assertJsonPath('data.0.href', "/quotations/awards/{$rfq->id}");
}

public function test_requester_cannot_search_purchase_order_handoffs(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    $this->createPurchaseOrderRequestHandoff($tenant, ['number' => 'POH-2026-000002']);

    $this->actingAsTenant($tenant, $requester)
        ->getJson('/api/search?query=POH-2026&types[]=po_handoff&limit=10')
        ->assertOk()
        ->assertJsonPath('meta.returned', 0);
}
```

Add a local helper in `SearchApiTest`:

```php
private function createPurchaseOrderRequestHandoff(Tenant $tenant, array $attributes = []): PurchaseOrderRequestHandoff
{
    return PurchaseOrderRequestHandoff::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'number' => 'POH-2026-000001',
        'status' => 'draft',
        'currency' => 'MYR',
        'total_amount' => '100.00',
        'source_snapshot' => [],
        'line_snapshot' => [['description' => 'Default line']],
        'approval_snapshot' => [],
        'evidence_snapshot' => [],
        'readiness_warnings' => [],
        'requested_by_user_id' => $tenant->users()->first()?->id ?? User::factory()->create()->id,
        'lock_version' => 1,
    ], $attributes));
}
```

- [x] **Step 2: Create migration**

Create the handoff table:

```php
Schema::create('purchase_order_request_handoffs', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
    $table->foreignUuid('rfq_award_recommendation_id')->constrained('rfq_award_recommendations')->cascadeOnDelete();
    $table->foreignUuid('approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
    $table->foreignIdFor(Rfq::class)->constrained('rfqs')->restrictOnDelete();
    $table->foreignIdFor(Requisition::class)->nullable()->constrained('requisitions')->nullOnDelete();
    $table->foreignUuid('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
    $table->foreignIdFor(Vendor::class)->constrained('vendors')->restrictOnDelete();
    $table->foreignIdFor(Quotation::class)->constrained('quotations')->restrictOnDelete();
    $table->foreignIdFor(QuotationVersion::class)->constrained('quotation_versions')->restrictOnDelete();
    $table->string('number');
    $table->string('status');
    $table->string('currency', 3)->nullable();
    $table->decimal('subtotal_amount', 14, 2)->nullable();
    $table->decimal('tax_amount', 14, 2)->nullable();
    $table->decimal('freight_amount', 14, 2)->nullable();
    $table->decimal('discount_amount', 14, 2)->nullable();
    $table->decimal('total_amount', 14, 2)->nullable();
    $table->date('requested_po_date')->nullable();
    $table->string('delivery_attention')->nullable();
    $table->text('finance_note')->nullable();
    $table->text('export_memo')->nullable();
    $table->foreignIdFor(User::class, 'requested_by_user_id')->constrained('users')->restrictOnDelete();
    $table->foreignIdFor(User::class, 'ready_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('ready_at')->nullable();
    $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancelled_reason')->nullable();
    $table->foreignIdFor(User::class, 'last_exported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('last_exported_at')->nullable();
    $table->string('last_export_format')->nullable();
    $table->json('source_snapshot');
    $table->json('line_snapshot');
    $table->json('approval_snapshot');
    $table->json('evidence_snapshot');
    $table->json('readiness_warnings')->nullable();
    $table->unsignedInteger('lock_version')->default(1);
    $table->timestamps();

    $table->unique(['tenant_id', 'rfq_award_recommendation_id']);
    $table->unique(['tenant_id', 'number']);
    $table->index(['tenant_id', 'status', 'updated_at']);
    $table->index(['tenant_id', 'rfq_id']);
    $table->index(['tenant_id', 'vendor_id']);
});
```

- [x] **Step 3: Create status enum**

```php
enum PurchaseOrderRequestHandoffStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Exported = 'exported';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return $this === self::Cancelled;
    }
}
```

- [x] **Step 4: Create model**

Create `PurchaseOrderRequestHandoff` with `HasUuids`, fillable fields matching the migration, casts for decimals/dates/json/status, relationships to tenant, recommendation, approval instance, RFQ, requisition, project, vendor, quotation, quotation version, requested/ready/cancelled/exported users, and a `saving` guard that verifies all linked records belong to the same tenant.

Include this lock helper:

```php
public function assertLockVersion(int $lockVersion): void
{
    if ((int) $this->lock_version !== $lockVersion) {
        throw new ConflictHttpException('The PO handoff has changed. Reload and try again.');
    }
}
```

- [x] **Step 5: Create policy and register it**

Policy methods:

```php
public function view(User $user, PurchaseOrderRequestHandoff $handoff): bool
public function create(User $user, Tenant $tenant): bool
public function update(User $user, PurchaseOrderRequestHandoff $handoff): bool
public function markReady(User $user, PurchaseOrderRequestHandoff $handoff): bool
public function export(User $user, PurchaseOrderRequestHandoff $handoff): bool
public function cancel(User $user, PurchaseOrderRequestHandoff $handoff): bool
```

Return true only for tenant `buyer` or `admin`. Register in `AppServiceProvider::boot()`:

```php
Gate::policy(PurchaseOrderRequestHandoff::class, PurchaseOrderRequestHandoffPolicy::class);
```

Register audit subject:

```php
AuditSubject::registerType(PurchaseOrderRequestHandoff::class, 'po_handoff');
```

- [x] **Step 6: Create resource**

`PurchaseOrderRequestHandoffResource` should return:

```php
[
    'id' => (string) $handoff->id,
    'number' => $handoff->number,
    'status' => $handoff->statusState()->value,
    'rfqId' => (string) $handoff->rfq_id,
    'recommendationId' => (string) $handoff->rfq_award_recommendation_id,
    'vendorId' => (string) $handoff->vendor_id,
    'currency' => $handoff->currency,
    'totalAmount' => $handoff->total_amount !== null ? (string) $handoff->total_amount : null,
    'source' => $handoff->source_snapshot ?? [],
    'lines' => $handoff->line_snapshot ?? [],
    'approval' => $handoff->approval_snapshot ?? [],
    'evidence' => $handoff->evidence_snapshot ?? [],
    'review' => [
        'requestedPoDate' => $handoff->requested_po_date?->toDateString(),
        'deliveryAttention' => $handoff->delivery_attention,
        'financeNote' => $handoff->finance_note,
        'exportMemo' => $handoff->export_memo,
    ],
    'readinessWarnings' => $handoff->readiness_warnings ?? [],
    'readyByUserId' => $handoff->ready_by_user_id !== null ? (string) $handoff->ready_by_user_id : null,
    'readyAt' => $handoff->ready_at?->toISOString(),
    'cancelledReason' => $handoff->cancelled_reason,
    'lastExportFormat' => $handoff->last_export_format,
    'lastExportedAt' => $handoff->last_exported_at?->toISOString(),
    'lockVersion' => $handoff->lock_version,
    'permissions' => [
        'canUpdate' => Gate::forUser($request->user())->check('update', $handoff),
        'canMarkReady' => Gate::forUser($request->user())->check('markReady', $handoff),
        'canExport' => Gate::forUser($request->user())->check('export', $handoff),
        'canCancel' => Gate::forUser($request->user())->check('cancel', $handoff),
    ],
]
```

- [x] **Step 7: Add search provider**

Create `PurchaseOrderRequestHandoffSearchProvider` modeled on `RequisitionSearchProvider.php`:

- Always start from `where('tenant_id', $tenant->id)`.
- Buyers/admins can search handoffs.
- Approvers and requesters get no handoff results unless they also have buyer/admin role.
- Search by handoff number, status, RFQ number/title, vendor name, quotation number.
- Rank exact number, number prefix, status, then related record matches.
- Return href `/quotations/awards/{rfq_id}`.

Register it in `SearchService::providers()` after `AwardSearchProvider`.

- [x] **Step 8: Update search request validation**

Modify `SearchRequest` so `po_handoff` is an allowed `types[]` value.

- [x] **Step 9: Run model/search tests**

Run:

```bash
php artisan test --filter=SearchApiTest
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
```

Expected after this task: search tests for `po_handoff` pass; handoff workflow tests still fail on missing actions/routes.

- [ ] **Step 10: Commit Task 2**

```bash
git add apps/api/database/migrations apps/api/Domains/PurchaseOrder apps/api/Domains/Search apps/api/app apps/api/tests/Feature/SearchApiTest.php
git commit -m "feat: add purchase order handoff model and search"
```

---

## Task 3: PurchaseOrder Domain Actions And Approval Integration

**Files:**

- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderRequestHandoffNumber.php`
- Create action files listed in the File Map
- Modify: `apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php`
- Modify: `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php`

- [x] **Step 1: Implement number generator**

`PurchaseOrderRequestHandoffNumber::next(Tenant $tenant): string` should generate `POH-YYYY-000001` using the current year and the tenant's latest handoff number for that year. Lock the query where possible. Keep it deterministic in tests by using `travelTo()`.

- [x] **Step 2: Implement snapshot builder**

`BuildPurchaseOrderRequestHandoffSnapshot::handle(RfqAwardRecommendation $recommendation): array` returns:

```php
[
    'source' => [...],
    'lines' => [...],
    'approval' => [...],
    'evidence' => [...],
    'warnings' => [...],
]
```

Load:

```php
$recommendation->loadMissing([
    'tenant',
    'rfq.requisition.requester',
    'rfq.project',
    'recommendedVendor.contacts',
    'recommendedQuotation',
    'recommendedQuotationVersion.lineItems',
    'scorecard.criteria',
    'scorecard.entries',
    'approvalInstance.stages.tasks.assignee',
    'evidenceReferences',
]);
```

If `Vendor::contacts` does not exist, do not add the eager load; use only fields available on `Vendor`.

Line mapping must use `QuotationVersionLineItem` fields:

```php
[
    'lineNumber' => $line->position,
    'itemCode' => null,
    'description' => $line->description,
    'quantity' => $this->decimal($line->quantity, 4),
    'unitOfMeasure' => $line->unit,
    'unitPrice' => $this->decimal($line->unit_price, 2),
    'taxAmount' => $this->decimal($line->tax_amount, 2),
    'freightAmount' => null,
    'discountAmount' => null,
    'lineTotal' => $this->decimal($line->total_amount, 2),
    'currency' => $version->currency,
    'notes' => $line->notes,
]
```

- [x] **Step 3: Implement create/reveal action**

`CreateOrRevealPurchaseOrderRequestHandoff::handle(RfqAwardRecommendation $recommendation, User $actor): PurchaseOrderRequestHandoff`

Rules:

- Lock recommendation by id and tenant.
- Require `status === Approved`.
- If handoff exists for `tenant_id + rfq_award_recommendation_id`, return it.
- Build snapshot.
- Require selected vendor, quotation, quotation version, currency, total amount.
- Create `draft` with `requested_by_user_id = actor->id`.
- Record `purchase_order_handoff.created`.

- [x] **Step 4: Implement update action**

`UpdatePurchaseOrderRequestHandoff::handle(PurchaseOrderRequestHandoff $handoff, User $actor, array $data): PurchaseOrderRequestHandoff`

Rules:

- Allowed only in `draft`.
- Check `lockVersion`.
- Persist `requested_po_date`, `delivery_attention`, `finance_note`, `export_memo`.
- Increment `lock_version`.
- Record before/after audit.

- [x] **Step 5: Implement ready action**

`MarkPurchaseOrderRequestHandoffReady::handle(PurchaseOrderRequestHandoff $handoff, User $actor, int $lockVersion): PurchaseOrderRequestHandoff`

Rules:

- Allowed only in `draft`.
- Require non-empty `line_snapshot`.
- Require approved `approval_snapshot.finalDecision === approved`.
- Require `currency` and `total_amount`.
- Set `status=ready`, `ready_by_user_id`, `ready_at`, increment lock.
- Record audit.

- [x] **Step 6: Implement cancel action**

`CancelPurchaseOrderRequestHandoff::handle(PurchaseOrderRequestHandoff $handoff, User $actor, int $lockVersion, string $reason): PurchaseOrderRequestHandoff`

Rules:

- Allowed from `draft`, `ready`, or `exported`.
- Require non-empty reason.
- Set `status=cancelled`, cancellation fields, increment lock.
- Record audit.

- [x] **Step 7: Implement export action**

`ExportPurchaseOrderRequestHandoff::handle(PurchaseOrderRequestHandoff $handoff, User $actor, string $format): array|string`

Rules:

- Format is `json` or `csv`.
- Allowed from `ready` or `exported`.
- On first export from `ready`, set `status=exported`.
- Always update `last_exported_*`, increment lock, and record audit.
- JSON returns export envelope.
- CSV uses `fputcsv()` into `php://temp` and returns the string.

CSV headers must exactly match the design spec.

- [x] **Step 8: Wire approval integration**

Modify `MarkRfqAwardRecommendationApproved` constructor:

```php
public function __construct(
    private readonly AuditRecorder $auditRecorder,
    private readonly CreateOrRevealPurchaseOrderRequestHandoff $createOrRevealPoHandoff,
) {}
```

After recording `rfq_award_recommendation.approved`, call:

```php
$this->createOrRevealPoHandoff->handle($recommendation->fresh(), $actor);
```

Keep it inside the same transaction boundary used by the approval action. If the current action is not wrapping this call in a transaction, wrap the status transition, audit event, and handoff creation in `DB::transaction()`.

- [x] **Step 9: Run API tests**

Run:

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
php artisan test --filter=RfqAwardApprovalApiTest
```

Expected after this task: action-level tests pass except route/controller-specific assertions.

- [ ] **Step 10: Commit Task 3**

```bash
git add apps/api/Domains/PurchaseOrder apps/api/Domains/Quotation/Actions/MarkRfqAwardRecommendationApproved.php apps/api/tests/Feature
git commit -m "feat: create purchase order handoffs from award approval"
```

---

## Task 4: API Controller, Routes, OpenAPI, And Generated Client

**Files:**

- Create: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderRequestHandoffController.php`
- Create request files listed in the File Map
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: generated files under `packages/api-client/src/generated/**`
- Modify: `packages/api-client/src/generated/schemas/listGlobalSearchTypesItem.ts` through generation

- [x] **Step 1: Implement form requests**

`UpdatePurchaseOrderRequestHandoffRequest` rules:

```php
[
    'lockVersion' => ['required', 'integer', 'min:1'],
    'requestedPoDate' => ['nullable', 'date'],
    'deliveryAttention' => ['nullable', 'string', 'max:255'],
    'financeNote' => ['nullable', 'string', 'max:5000'],
    'exportMemo' => ['nullable', 'string', 'max:5000'],
]
```

`MarkPurchaseOrderRequestHandoffReadyRequest`:

```php
['lockVersion' => ['required', 'integer', 'min:1']]
```

`CancelPurchaseOrderRequestHandoffRequest`:

```php
[
    'lockVersion' => ['required', 'integer', 'min:1'],
    'reason' => ['required', 'string', 'min:3', 'max:2000'],
]
```

- [x] **Step 2: Implement controller**

Controller methods:

```php
showForRfq(CurrentTenant $currentTenant, int $rfq): JsonResponse
createForRfq(CurrentTenant $currentTenant, int $rfq, CreateOrRevealPurchaseOrderRequestHandoff $action): JsonResponse
update(UpdatePurchaseOrderRequestHandoffRequest $request, PurchaseOrderRequestHandoff $handoff, UpdatePurchaseOrderRequestHandoff $action): JsonResponse
ready(MarkPurchaseOrderRequestHandoffReadyRequest $request, PurchaseOrderRequestHandoff $handoff, MarkPurchaseOrderRequestHandoffReady $action): JsonResponse
cancel(CancelPurchaseOrderRequestHandoffRequest $request, PurchaseOrderRequestHandoff $handoff, CancelPurchaseOrderRequestHandoff $action): JsonResponse
exportJson(PurchaseOrderRequestHandoff $handoff, ExportPurchaseOrderRequestHandoff $action): JsonResponse
exportCsv(PurchaseOrderRequestHandoff $handoff, ExportPurchaseOrderRequestHandoff $action): Response
```

Every method must:

- Resolve current tenant.
- Query by tenant for RFQ/recommendation/handoff.
- Authorize through policy.
- Return `['data' => resource]` for JSON resource methods.
- Return `['format' => 'json', 'exportedAt' => ..., 'handoff' => ...]` for JSON export.
- Return `response($csv, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$handoff->number.'.csv"'])` for CSV.

- [x] **Step 3: Add routes**

Inside the existing `RequireTenantHeader` RFQ award group in `apps/api/routes/api.php`:

```php
Route::get('/rfqs/{rfq}/award-recommendation/po-handoff', [PurchaseOrderRequestHandoffController::class, 'showForRfq']);
Route::post('/rfqs/{rfq}/award-recommendation/po-handoff', [PurchaseOrderRequestHandoffController::class, 'createForRfq']);
Route::patch('/po-handoffs/{handoff}', [PurchaseOrderRequestHandoffController::class, 'update']);
Route::post('/po-handoffs/{handoff}/ready', [PurchaseOrderRequestHandoffController::class, 'ready']);
Route::post('/po-handoffs/{handoff}/cancel', [PurchaseOrderRequestHandoffController::class, 'cancel']);
Route::get('/po-handoffs/{handoff}/export.json', [PurchaseOrderRequestHandoffController::class, 'exportJson']);
Route::get('/po-handoffs/{handoff}/export.csv', [PurchaseOrderRequestHandoffController::class, 'exportCsv']);
```

- [x] **Step 4: Update OpenAPI**

Add paths and schemas from the design spec. Also update global search type enums to include `po_handoff`:

- `parameters` for `types[]`
- `SearchResult.type`
- generated `ListGlobalSearchTypesItem`

Use `PurchaseOrderRequestHandoffResponse` as `{"data": PurchaseOrderRequestHandoff}`.

- [x] **Step 5: Regenerate client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoint functions and schemas include the PO handoff APIs, and contract check exits 0.

- [x] **Step 6: Run API tests**

Run:

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
php artisan test --filter=SearchApiTest
php artisan route:list --path=po-handoffs
```

Expected: tests pass; route list includes all PO handoff routes.

- [ ] **Step 7: Commit Task 4**

```bash
git add apps/api/Domains/PurchaseOrder apps/api/routes/api.php apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: expose purchase order handoff API contract"
```

---

## Task 5: Web API Wrappers, Hooks, MSW Fixtures, And Search Contract

**Files:**

- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Modify: `apps/web/features/search/search-contract.ts`
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
- Modify: `apps/web/features/search/mocks/search-handlers.ts`
- Modify: `apps/web/features/quotations/tests/quotation-award-recommendation-api.test.ts`
- Modify: `apps/web/features/search/tests/command-palette.test.tsx`

- [x] **Step 1: Add web API wrappers**

In `quotation-award-recommendation-api.ts`, import generated JSON endpoints and schemas:

```ts
import {
  cancelPurchaseOrderRequestHandoff,
  createRfqAwardRecommendationPoHandoff,
  exportPurchaseOrderRequestHandoffJson,
  markPurchaseOrderRequestHandoffReady,
  showRfqAwardRecommendationPoHandoff,
  updatePurchaseOrderRequestHandoff,
} from "@cognify/api-client/endpoints";
import type {
  CancelPurchaseOrderRequestHandoffRequest,
  MarkPurchaseOrderRequestHandoffReadyRequest,
  PurchaseOrderRequestHandoff,
  UpdatePurchaseOrderRequestHandoffRequest,
} from "@cognify/api-client/schemas";
```

Add:

```ts
export async function fetchRfqAwardRecommendationPoHandoff(rfqId: string, tenantId = getStoredActiveTenantId()) {
  const response = await showRfqAwardRecommendationPoHandoff(rfqId, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  return unwrapOk(response) as PurchaseOrderRequestHandoff | null;
}
```

Add create/update/ready/cancel wrappers with the same `unwrapOk()` pattern.

Add CSV download helper:

```ts
export async function downloadPurchaseOrderRequestHandoffCsv(handoffId: string, tenantId = getStoredActiveTenantId()): Promise<Blob> {
  const response = await fetch(`/api/po-handoffs/${handoffId}/export.csv`, {
    credentials: "include",
    headers: tenantId ? { "X-Tenant-Id": tenantId } : undefined,
  });

  if (!response.ok) {
    throw await response.json().catch(() => ({ error: { message: "CSV export failed." } }));
  }

  return response.blob();
}
```

- [x] **Step 2: Add hooks**

In `use-rfq-award-recommendation.ts` add:

```ts
export function rfqAwardRecommendationPoHandoffQueryKey(rfqId: string, tenantId?: string | null) {
  return ["rfq-award-recommendation-po-handoff", tenantId ?? "no-tenant", rfqId] as const;
}
```

Add `useRfqAwardRecommendationPoHandoff(rfqId, enabled)` that only fetches when recommendation status is approved in the workspace.

In `use-rfq-award-recommendation-actions.ts`, add create/update/ready/cancel/export mutations that invalidate:

- `rfqAwardRecommendationPoHandoffQueryKey(rfqId, tenantId)`
- `rfqAwardRecommendationQueryKey(rfqId, tenantId)`
- `["search"]` only if existing search invalidation is already used; otherwise do not introduce broad invalidation.

- [x] **Step 3: Extend MSW fixture state**

In `quotation-award-recommendation-fixtures.ts`, add handoff state keyed by RFQ id:

```ts
type PoHandoffFixtureState = {
  handoff: PurchaseOrderRequestHandoff | null;
};
```

For `rfq-approved-recommendation`, seed a draft handoff with:

```ts
{
  id: "po-handoff-1",
  number: "POH-2026-000001",
  status: "draft",
  rfqId: "rfq-approved-recommendation",
  currency: "USD",
  totalAmount: "125000.00",
  source: { rfq: { number: "RFQ-2026-001" }, vendor: { name: "Northwind Traders" } },
  lines: [{ lineNumber: 1, description: "Managed services", quantity: "1.0000", unitOfMeasure: "EA", unitPrice: "125000.00", lineTotal: "125000.00", currency: "USD" }],
  approval: { finalDecision: "approved" },
  evidence: [],
  review: { requestedPoDate: null, deliveryAttention: null, financeNote: null, exportMemo: null },
  readinessWarnings: [],
  lockVersion: 1,
  permissions: { canUpdate: true, canMarkReady: true, canExport: false, canCancel: true }
}
```

- [x] **Step 4: Add MSW handlers**

Add handlers for:

```txt
GET /api/rfqs/:rfq/award-recommendation/po-handoff
POST /api/rfqs/:rfq/award-recommendation/po-handoff
PATCH /api/po-handoffs/:handoff
POST /api/po-handoffs/:handoff/ready
POST /api/po-handoffs/:handoff/cancel
GET /api/po-handoffs/:handoff/export.json
GET /api/po-handoffs/:handoff/export.csv
```

Handlers must simulate:

- `409` before approved recommendation
- lock conflict when submitted `lockVersion` mismatches
- ready transition
- exported transition
- cancelled terminal behavior

- [x] **Step 5: Update global search web contract**

Update `GLOBAL_SEARCH_TYPES` in `apps/web/features/search/search-contract.ts` to include `ListGlobalSearchTypesItem.po_handoff`.

Add fixture in `search-fixtures.ts`:

```ts
{
  type: "po_handoff",
  id: "po-handoff-1",
  title: "POH-2026-000001",
  subtitle: "Northwind Traders",
  status: "ready",
  href: "/quotations/awards/rfq-approved-recommendation",
  updatedAt: "2026-05-26T12:00:00.000000Z",
}
```

- [x] **Step 6: Run web API/search tests**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-award-recommendation-api
pnpm --filter @cognify/web test -- command-palette
```

Expected: wrappers and command palette tests pass.

- [ ] **Step 7: Commit Task 5**

```bash
git add apps/web/features/quotations apps/web/features/search
git commit -m "feat: add purchase order handoff web data layer"
```

---

## Task 6: Award Workspace PO Handoff Panel

**Files:**

- Create: `apps/web/features/quotations/components/rfq-award-po-handoff-panel.tsx`
- Modify: `apps/web/features/quotations/workflows/rfq-award-recommendation-workspace.tsx`
- Modify: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`

- [x] **Step 1: Write workspace tests**

Add tests:

```ts
it("shows draft PO handoff review controls for an approved recommendation", async () => {});
it("marks a draft PO handoff ready and shows export actions", async () => {});
it("downloads JSON and CSV exports for a ready handoff", async () => {});
it("shows exported metadata and repeat export actions", async () => {});
it("hides export actions for cancelled handoffs", async () => {});
it("does not show PO handoff actions before award approval", async () => {});
it("shows stale lock conflict errors from PO handoff mutations", async () => {});
```

Assert visible text:

```txt
PO request handoff
POH-2026-000001
Mark ready
Download JSON
Download CSV
Last exported
Cancelled
The PO handoff has changed. Reload and try again.
```

Assert absent text on non-approved recommendations:

```txt
PO request handoff
Download JSON
Download CSV
```

- [x] **Step 2: Create panel component**

Props:

```ts
type RfqAwardPoHandoffPanelProps = {
  handoff: PurchaseOrderRequestHandoff | null;
  isLoading: boolean;
  error: unknown;
  onGenerate: () => void;
  onUpdate: (payload: UpdatePurchaseOrderRequestHandoffRequest) => void;
  onReady: (payload: MarkPurchaseOrderRequestHandoffReadyRequest) => void;
  onCancel: (payload: CancelPurchaseOrderRequestHandoffRequest) => void;
  onDownloadJson: () => void;
  onDownloadCsv: () => void;
  isMutating: boolean;
};
```

Use `Button`, `Input`, `Textarea`, and existing app styling. Keep cards at small radius and do not create shared primitives.

Behavior:

- `null` handoff: show `Generate PO handoff`.
- `draft`: show editable review fields and `Mark ready`.
- `ready`: show download buttons and cancel.
- `exported`: show last export and download buttons.
- `cancelled`: show cancellation reason only.

- [x] **Step 3: Wire workspace**

In `rfq-award-recommendation-workspace.tsx`:

- Import `RfqAwardPoHandoffPanel`.
- Query handoff only when `recommendationStatus === "approved"`.
- Add section id `po-handoff` to `RecordWorkspaceLayout` sections only for approved recommendations.
- Render panel after approval panel.
- Invalidate handoff query on mutations.

- [x] **Step 4: Implement browser download**

For JSON export, use generated JSON endpoint and create a Blob client-side:

```ts
const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
```

For CSV export, use the CSV Blob helper. Use an anchor with `download` to trigger the browser download. Keep this logic in the panel or a small feature-local utility under `apps/web/features/quotations/utils/download-file.ts`.

- [x] **Step 5: Run workspace tests**

Run:

```bash
pnpm --filter @cognify/web test -- rfq-award-recommendation-workspace
```

Expected: all award recommendation workspace tests pass.

- [ ] **Step 6: Commit Task 6**

```bash
git add apps/web/features/quotations
git commit -m "feat: add award workspace po handoff panel"
```

---

## Task 7: Final API/Web Hardening, Roadmap Update, And Scope Audit

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-26-purchase-order-request-handoff.md`
- Review generated files under `packages/api-client/src/generated/**`

- [x] **Step 1: Run focused API verification**

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=SearchApiTest
php artisan route:list --path=po-handoffs
```

Expected:

- PO handoff tests pass.
- Award approval tests still pass.
- Search tests pass with `po_handoff`.
- Routes include show/create/update/ready/cancel/export JSON/export CSV.

- [x] **Step 2: Run contract verification**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: no contract drift after generation.

- [x] **Step 3: Run focused web verification**

```bash
pnpm --filter @cognify/web test -- quotation-award-recommendation-api
pnpm --filter @cognify/web test -- rfq-award-recommendation-workspace
pnpm --filter @cognify/web test -- command-palette
```

Expected: tests pass.

- [x] **Step 4: Run root verification**

```bash
pnpm lint
pnpm typecheck
pnpm build
git diff --check
```

Expected: all commands exit 0.

- [x] **Step 5: Run scope audit**

Run:

```bash
rg -n "ERP|status sync|vendor notification|split award|procurement calendar|finance queue|official PO|purchase order status" apps/api apps/web docs/superpowers/plans/2026-05-26-purchase-order-request-handoff.md
```

Expected: matches are limited to explicit non-goals, scope boundaries, and test labels. There must be no ERP adapter, vendor notification workflow, split-award implementation, finance queue, procurement calendar, or PO status sync.

- [x] **Step 6: Update roadmap**

Change P1-34 in `docs/01-product/feature-roadmap.md` after all verification passes:

```md
| P1-34 | Purchase Order Request Handoff | Generate a structured handoff for ERP or finance systems after award approval. Even before direct ERP integration, Cognify should make the next operational step clear. | Fully Implemented | `docs/superpowers/specs/2026-05-26-purchase-order-request-handoff-design.md` | `docs/superpowers/plans/2026-05-26-purchase-order-request-handoff.md` |  | Implemented as an approved-award PO request handoff package with buyer/admin review, ready/export/cancel states, CSV/JSON export, audit events, and global search. Real ERP integration, PO number sync, vendor notifications, split awards, and procurement calendar remain downstream. |
```

- [x] **Step 7: Mark plan checkboxes**

Update this plan's checkboxes for completed tasks. Do not mark a task complete before its verification command has passed.

- [ ] **Step 8: Final commit**

```bash
git add docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-05-26-purchase-order-request-handoff.md
git commit -m "docs: mark purchase order handoff implemented"
```

---

## Final Verification Checklist

Before claiming the implementation is complete, run and confirm:

```bash
php artisan test --filter=PurchaseOrderRequestHandoffApiTest
php artisan test --filter=RfqAwardApprovalApiTest
php artisan test --filter=SearchApiTest
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/web test -- quotation-award-recommendation-api
pnpm --filter @cognify/web test -- rfq-award-recommendation-workspace
pnpm --filter @cognify/web test -- command-palette
pnpm lint
pnpm typecheck
pnpm build
git diff --check
```

All commands must exit 0 before the roadmap row is marked `Fully Implemented`.

## Self-Review Notes

- Spec coverage: covered automatic draft creation, dedicated PurchaseOrder domain, source snapshots, review/ready/cancel/export states, CSV/JSON export, audit, generated client, web panel, search, tenant/role policy, and explicit non-goals.
- Type consistency: use `PurchaseOrderRequestHandoff`, `PurchaseOrderRequestHandoffStatus`, route segment `po-handoffs`, search type `po_handoff`, and operation names from the design spec consistently.
- Scope check: the plan adds global search because the user explicitly required grounding in `RequisitionSearchProvider.php`; it does not add a standalone PO list, finance queue, ERP adapter, vendor communications, split awards, PO status sync, or procurement calendar.

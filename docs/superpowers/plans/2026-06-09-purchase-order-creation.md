# Purchase Order Creation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-36 so buyers/admins can convert a ready/exported PO request handoff into a durable Cognify purchase order, edit draft operational fields, and mark it ready for the future PO review workflow.

**Architecture:** Extend the existing `apps/api/Domains/PurchaseOrder` domain with a real `PurchaseOrder` aggregate and line rows sourced from `PurchaseOrderRequestHandoff`. Keep handoff review, award approval, supplier issue, receiving, invoice matching, and payment workflows separate; P1-36 ends at `ready_for_review`. Expose OpenAPI-backed endpoints, regenerate `@cognify/api-client`, add a focused `apps/web/features/purchase-orders` web surface, and connect creation from the existing RFQ award PO handoff panel.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-06-09-purchase-order-creation-design.md`.
- Roadmap row: `docs/01-product/feature-roadmap.md` feature `P1-36`.
- Runbook: `docs/05-runbooks/feature-development.md`.
- Architecture: `ARCHITECTURE.md`.
- Predecessor domain: `apps/api/Domains/PurchaseOrder`.
- Predecessor web panel: `apps/web/features/quotations/components/rfq-award-po-handoff-panel.tsx`.
- Existing handoff routes: `apps/api/routes/api.php` lines around the `/api/po-handoffs/*` routes.

## Scope Boundaries

Implement:

- `PurchaseOrder` and `PurchaseOrderLine` models, migrations, state enum, policy, actions, resources, controller, routes, and audit events.
- Idempotent conversion from ready/exported `PurchaseOrderRequestHandoff` to one draft PO.
- Draft PO update, ready-for-review transition, and draft cancellation.
- PO list/detail API endpoints with tenant filtering and generated-client contract.
- Purchase order list and workspace routes under `apps/web/app/(workspace)/purchase-orders`.
- Source-handoff creation action in the existing RFQ award PO handoff panel.
- Search/navigation discovery for purchase orders.
- Focused API and web tests plus contract/typecheck verification.

Do not implement:

- P1-37 PO approval, P1-38 supplier issue, P1-39 change orders, P1-40 receiving, P1-42 invoices, matching, payment readiness, ERP sync, budget encumbrance, vendor master enrichment, PO PDFs, or calendar source expansion.

## File Map

### API Domain

- Create: `apps/api/database/migrations/2026_06_09_000000_create_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`
- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderNumber.php`
- Create: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CreatePurchaseOrderFromHandoff.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/UpdatePurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderReadyForReview.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CancelPurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderController.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/UpdatePurchaseOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/MarkPurchaseOrderReadyForReviewRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/CancelPurchaseOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderLineResource.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/app/Audit/AuditSubject.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`

### API Tests

- Create: `apps/api/tests/Feature/PurchaseOrderCreationApiTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` by running `pnpm generate:api`.

### Web

- Create: `apps/web/app/(workspace)/purchase-orders/page.tsx`
- Create: `apps/web/app/(workspace)/purchase-orders/[purchaseOrderId]/page.tsx`
- Create: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Create: `apps/web/features/purchase-orders/components/purchase-order-actions.tsx`
- Create: `apps/web/features/purchase-orders/components/purchase-order-detail-card.tsx`
- Create: `apps/web/features/purchase-orders/components/purchase-order-lines-table.tsx`
- Create: `apps/web/features/purchase-orders/hooks/use-purchase-order.ts`
- Create: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Create: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Create: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Create: `apps/web/features/purchase-orders/tables/purchase-order-list-table.tsx`
- Create: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`
- Create: `apps/web/features/purchase-orders/workflows/purchase-order-list-page.tsx`
- Create: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/components/rfq-award-po-handoff-panel.tsx`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-fixtures.ts`
- Modify: `apps/web/features/quotations/mocks/quotation-award-recommendation-handlers.ts`
- Modify: `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`
- Modify: `apps/web/components/default-shell/navigation.tsx`
- Modify: `apps/web/features/search/search-contract.ts`
- Modify: `apps/web/features/search/search-commands.ts`
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
- Modify: `apps/web/features/search/tests/search-commands.test.ts`

### Docs

- Modify: `docs/01-product/feature-roadmap.md` after implementation passes to mark P1-36 implemented and link this plan.

---

## Task 1: API Red Tests For Purchase Order Creation

**Files:**

- Create: `apps/api/tests/Feature/PurchaseOrderCreationApiTest.php`

- [x] **Step 1: Create the failing test file**

Create `apps/api/tests/Feature/PurchaseOrderCreationApiTest.php` with this test scaffold. Reuse concrete fixture-building patterns from `apps/api/tests/Feature/PurchaseOrderRequestHandoffApiTest.php`.

```php
<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderCreationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_draft_purchase_order_from_ready_handoff(): void {}
    public function test_exported_handoff_can_create_purchase_order(): void {}
    public function test_draft_and_cancelled_handoffs_cannot_create_purchase_order(): void {}
    public function test_duplicate_creation_reveals_existing_purchase_order(): void {}
    public function test_creation_persists_lines_from_handoff_snapshot(): void {}
    public function test_cross_tenant_handoff_access_is_denied(): void {}
    public function test_requester_cannot_create_or_update_purchase_order(): void {}
    public function test_draft_operational_fields_update_with_lock_version(): void {}
    public function test_stale_update_returns_conflict(): void {}
    public function test_ready_for_review_validates_required_fields_and_changes_status(): void {}
    public function test_cancelled_purchase_order_cannot_be_updated_or_marked_ready(): void {}
}
```

- [x] **Step 2: Add the ready-handoff creation assertion**

Fill `test_buyer_can_create_draft_purchase_order_from_ready_handoff` with this assertion shape:

```php
$handoff = $this->readyPurchaseOrderHandoff();
$buyer = $this->tenantUser($handoff->tenant, TenantRole::Buyer->value);

$this->actingAsTenant($handoff->tenant, $buyer)
    ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
    ->assertCreated()
    ->assertJsonPath('data.status', 'draft')
    ->assertJsonPath('data.source.handoffId', (string) $handoff->id)
    ->assertJsonPath('data.vendor.id', (string) $handoff->vendor_id)
    ->assertJsonPath('data.currency', 'MYR')
    ->assertJsonPath('data.totalAmount', '131100.00')
    ->assertJsonPath('data.lines.0.description', 'Pallet rack bay')
    ->assertJsonPath('data.permissions.canUpdate', true)
    ->assertJsonPath('data.permissions.canMarkReadyForReview', true);

$this->assertDatabaseHas('purchase_orders', [
    'tenant_id' => $handoff->tenant_id,
    'purchase_order_request_handoff_id' => $handoff->id,
    'status' => 'draft',
    'currency' => 'MYR',
    'total_amount' => '131100.00',
]);

$this->assertDatabaseHas('audit_events', [
    'tenant_id' => $handoff->tenant_id,
    'action' => 'purchase_order.created',
]);
```

- [x] **Step 3: Add state and idempotency assertions**

Use these exact status expectations:

```php
$draft = $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Draft);
$cancelled = $this->purchaseOrderHandoffWithStatus(PurchaseOrderRequestHandoffStatus::Cancelled);
$buyer = $this->tenantUser($draft->tenant, TenantRole::Buyer->value);

$this->actingAsTenant($draft->tenant, $buyer)
    ->postJson("/api/po-handoffs/{$draft->id}/purchase-order")
    ->assertConflict()
    ->assertJsonPath('message', 'PO handoff must be ready or exported before creating a purchase order.');

$this->actingAsTenant($cancelled->tenant, $buyer)
    ->postJson("/api/po-handoffs/{$cancelled->id}/purchase-order")
    ->assertConflict()
    ->assertJsonPath('message', 'Cancelled PO handoffs cannot create purchase orders.');
```

For idempotency:

```php
$first = $this->actingAsTenant($handoff->tenant, $buyer)
    ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
    ->assertCreated()
    ->json('data.id');

$second = $this->actingAsTenant($handoff->tenant, $buyer)
    ->postJson("/api/po-handoffs/{$handoff->id}/purchase-order")
    ->assertOk()
    ->json('data.id');

$this->assertSame($first, $second);
$this->assertSame(1, PurchaseOrder::query()->where('purchase_order_request_handoff_id', $handoff->id)->count());
```

- [x] **Step 4: Add update, stale lock, ready, and cancel assertions**

Use these endpoint assertions:

```php
$po = $this->draftPurchaseOrder();
$buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

$this->actingAsTenant($po->tenant, $buyer)
    ->patchJson("/api/purchase-orders/{$po->id}", [
        'lockVersion' => $po->lock_version,
        'requestedPoDate' => '2026-06-18',
        'expectedDeliveryDate' => '2026-07-02',
        'billingName' => 'Acme Finance',
        'billingAddress' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
        'shippingName' => 'Acme Warehouse',
        'shippingAddress' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
        'deliveryAttention' => 'Warehouse receiving',
        'paymentTerms' => 'Net 30',
        'deliveryTerms' => 'DAP',
        'buyerNote' => 'Confirm delivery slot before dispatch.',
        'financeNote' => 'Charge to expansion budget.',
    ])
    ->assertOk()
    ->assertJsonPath('data.requestedPoDate', '2026-06-18')
    ->assertJsonPath('data.shippingAddress.city', 'Shah Alam')
    ->assertJsonPath('data.lockVersion', $po->lock_version + 1);

$this->actingAsTenant($po->tenant, $buyer)
    ->patchJson("/api/purchase-orders/{$po->id}", ['lockVersion' => 1, 'buyerNote' => 'stale'])
    ->assertConflict();

$this->actingAsTenant($po->tenant, $buyer)
    ->postJson("/api/purchase-orders/{$po->fresh()->id}/ready-for-review", [
        'lockVersion' => $po->fresh()->lock_version,
    ])
    ->assertOk()
    ->assertJsonPath('data.status', 'ready_for_review');
```

- [x] **Step 5: Run the narrow red test**

Run:

```bash
cd apps/api && php artisan test --filter PurchaseOrderCreationApiTest
```

Expected: tests fail because `PurchaseOrder`, routes, and tables do not exist.

- [x] **Step 6: Commit the red test**

```bash
git add apps/api/tests/Feature/PurchaseOrderCreationApiTest.php
git commit -m "test: cover purchase order creation workflow"
```

## Task 2: API Purchase Order Data Model

**Files:**

- Create: `apps/api/database/migrations/2026_06_09_000000_create_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`
- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`
- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderNumber.php`
- Modify: `apps/api/app/Audit/AuditSubject.php`

- [x] **Step 1: Add the migration**

Create `apps/api/database/migrations/2026_06_09_000000_create_purchase_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('purchase_order_request_handoff_id');
            $table->uuid('rfq_award_recommendation_id');
            $table->uuid('approval_instance_id')->nullable();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('requisition_id')->nullable()->constrained('requisitions')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('procurement_projects')->nullOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->restrictOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->uuid('quotation_version_id')->nullable();
            $table->string('number');
            $table->string('status');
            $table->string('currency', 3);
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->date('requested_po_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('billing_name')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('shipping_name')->nullable();
            $table->json('shipping_address')->nullable();
            $table->string('delivery_attention')->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->text('buyer_note')->nullable();
            $table->text('finance_note')->nullable();
            $table->json('source_snapshot');
            $table->json('approval_snapshot');
            $table->json('evidence_snapshot');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('ready_for_review_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ready_for_review_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'purchase_order_request_handoff_id']);
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'updated_at']);
            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['tenant_id', 'rfq_id']);
            $table->index(['tenant_id', 'requisition_id']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('purchase_order_id');
            $table->string('source_line_id')->nullable();
            $table->unsignedInteger('line_number');
            $table->text('description');
            $table->string('category')->nullable();
            $table->string('sku')->nullable();
            $table->string('unit');
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('currency', 3);
            $table->date('needed_by_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->string('delivery_location')->nullable();
            $table->text('notes')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->index(['tenant_id', 'purchase_order_id']);
            $table->unique(['purchase_order_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
```

- [x] **Step 2: Add `PurchaseOrderStatus`**

Create `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`:

```php
<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::ReadyForReview, self::Cancelled], true);
    }
}
```

- [x] **Step 3: Add `PurchaseOrder` model**

Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php` following the tenant assertion pattern in `PurchaseOrderRequestHandoff.php`. Include these key members:

```php
<?php

namespace Domains\PurchaseOrder\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrder extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_request_handoff_id',
        'rfq_award_recommendation_id',
        'approval_instance_id',
        'rfq_id',
        'requisition_id',
        'project_id',
        'vendor_id',
        'quotation_id',
        'quotation_version_id',
        'number',
        'status',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
        'requested_po_date',
        'expected_delivery_date',
        'billing_name',
        'billing_address',
        'shipping_name',
        'shipping_address',
        'delivery_attention',
        'payment_terms',
        'delivery_terms',
        'buyer_note',
        'finance_note',
        'source_snapshot',
        'approval_snapshot',
        'evidence_snapshot',
        'created_by_user_id',
        'ready_for_review_by_user_id',
        'ready_for_review_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancelled_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'requested_po_date' => 'immutable_date',
            'expected_delivery_date' => 'immutable_date',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'source_snapshot' => 'array',
            'approval_snapshot' => 'array',
            'evidence_snapshot' => 'array',
            'ready_for_review_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): PurchaseOrderStatus
    {
        return $this->status instanceof PurchaseOrderStatus
            ? $this->status
            : PurchaseOrderStatus::from((string) $this->getAttribute('status'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The purchase order has changed. Reload and try again.');
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderRequestHandoff::class, 'purchase_order_request_handoff_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number');
    }
}
```

Add `booted()` tenant assertions after matching the exact helper methods available in `PurchaseOrderRequestHandoff.php`.

- [x] **Step 4: Add `PurchaseOrderLine` model**

Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`:

```php
<?php

namespace Domains\PurchaseOrder\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'source_line_id',
        'line_number',
        'description',
        'category',
        'sku',
        'unit',
        'quantity',
        'unit_price',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'needed_by_date',
        'expected_delivery_date',
        'delivery_location',
        'notes',
        'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'needed_by_date' => 'immutable_date',
            'expected_delivery_date' => 'immutable_date',
            'source_snapshot' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
```

- [x] **Step 5: Add PO number generator**

Create `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderNumber.php`:

```php
<?php

namespace Domains\PurchaseOrder\Support;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;

class PurchaseOrderNumber
{
    public static function next(Tenant $tenant): string
    {
        $year = now()->format('Y');
        $count = PurchaseOrder::query()
            ->where('tenant_id', $tenant->id)
            ->where('number', 'like', "PO-{$year}-%")
            ->count() + 1;

        return sprintf('PO-%s-%06d', $year, $count);
    }
}
```

- [x] **Step 6: Register audit subject**

Modify `apps/api/app/Audit/AuditSubject.php` so the model map includes:

```php
PurchaseOrder::class => 'purchase_order',
```

Add the import:

```php
use Domains\PurchaseOrder\Models\PurchaseOrder;
```

- [x] **Step 7: Run the API red test again**

Run:

```bash
cd apps/api && php artisan test --filter PurchaseOrderCreationApiTest
```

Expected: migration/model errors are resolved; failures now point to missing policy, routes, resources, and actions.

- [x] **Step 8: Commit the data model**

```bash
git add apps/api/database/migrations/2026_06_09_000000_create_purchase_orders_table.php apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php apps/api/Domains/PurchaseOrder/Support/PurchaseOrderNumber.php apps/api/app/Audit/AuditSubject.php
git commit -m "feat: add purchase order data model"
```

## Task 3: API Actions, Policy, Resources, And Routes

**Files:**

- Create API files listed in the API Domain file map.
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/routes/api.php`

- [x] **Step 1: Add `PurchaseOrderPolicy`**

Create `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`:

```php
<?php

namespace Domains\PurchaseOrder\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->buyerOrAdmin($user);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->isTenantScoped($purchaseOrder->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function createFromHandoff(User $user, PurchaseOrderRequestHandoff $handoff): bool
    {
        return $this->isTenantScoped($handoff->tenant_id) && $this->buyerOrAdmin($user);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function markReadyForReview(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->view($user, $purchaseOrder);
    }

    private function buyerOrAdmin(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function isTenantScoped(int|string $tenantId): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $tenant->id === (int) $tenantId;
    }
}
```

Register it in `apps/api/app/Providers/AppServiceProvider.php`:

```php
Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
```

- [x] **Step 2: Add resources**

Create `PurchaseOrderLineResource`:

```php
return [
    'id' => (string) $line->id,
    'lineNumber' => $line->line_number,
    'description' => $line->description,
    'category' => $line->category,
    'sku' => $line->sku,
    'unit' => $line->unit,
    'quantity' => (string) $line->quantity,
    'unitPrice' => (string) $line->unit_price,
    'subtotalAmount' => (string) $line->subtotal_amount,
    'taxAmount' => $line->tax_amount !== null ? (string) $line->tax_amount : null,
    'freightAmount' => $line->freight_amount !== null ? (string) $line->freight_amount : null,
    'discountAmount' => $line->discount_amount !== null ? (string) $line->discount_amount : null,
    'totalAmount' => (string) $line->total_amount,
    'currency' => $line->currency,
    'neededByDate' => $line->needed_by_date?->toDateString(),
    'expectedDeliveryDate' => $line->expected_delivery_date?->toDateString(),
    'deliveryLocation' => $line->delivery_location,
    'notes' => $line->notes,
    'source' => $line->source_snapshot ?? [],
];
```

Create `PurchaseOrderResource` with:

```php
return [
    'id' => (string) $purchaseOrder->id,
    'number' => $purchaseOrder->number,
    'status' => $purchaseOrder->statusState()->value,
    'currency' => $purchaseOrder->currency,
    'subtotalAmount' => (string) $purchaseOrder->subtotal_amount,
    'taxAmount' => $purchaseOrder->tax_amount !== null ? (string) $purchaseOrder->tax_amount : null,
    'freightAmount' => $purchaseOrder->freight_amount !== null ? (string) $purchaseOrder->freight_amount : null,
    'discountAmount' => $purchaseOrder->discount_amount !== null ? (string) $purchaseOrder->discount_amount : null,
    'totalAmount' => (string) $purchaseOrder->total_amount,
    'requestedPoDate' => $purchaseOrder->requested_po_date?->toDateString(),
    'expectedDeliveryDate' => $purchaseOrder->expected_delivery_date?->toDateString(),
    'billingName' => $purchaseOrder->billing_name,
    'billingAddress' => $purchaseOrder->billing_address,
    'shippingName' => $purchaseOrder->shipping_name,
    'shippingAddress' => $purchaseOrder->shipping_address,
    'deliveryAttention' => $purchaseOrder->delivery_attention,
    'paymentTerms' => $purchaseOrder->payment_terms,
    'deliveryTerms' => $purchaseOrder->delivery_terms,
    'buyerNote' => $purchaseOrder->buyer_note,
    'financeNote' => $purchaseOrder->finance_note,
    'source' => [
        'handoffId' => (string) $purchaseOrder->purchase_order_request_handoff_id,
        'recommendationId' => (string) $purchaseOrder->rfq_award_recommendation_id,
        'rfqId' => (string) $purchaseOrder->rfq_id,
        'requisitionId' => $purchaseOrder->requisition_id !== null ? (string) $purchaseOrder->requisition_id : null,
        'projectId' => $purchaseOrder->project_id !== null ? (string) $purchaseOrder->project_id : null,
        'quotationId' => $purchaseOrder->quotation_id !== null ? (string) $purchaseOrder->quotation_id : null,
        'quotationVersionId' => $purchaseOrder->quotation_version_id !== null ? (string) $purchaseOrder->quotation_version_id : null,
        'snapshot' => $purchaseOrder->source_snapshot ?? [],
    ],
    'vendor' => data_get($purchaseOrder->source_snapshot, 'vendor', ['id' => (string) $purchaseOrder->vendor_id]),
    'approval' => $purchaseOrder->approval_snapshot ?? [],
    'evidence' => $purchaseOrder->evidence_snapshot ?? [],
    'lines' => PurchaseOrderLineResource::collection($purchaseOrder->whenLoaded('lines'))->resolve(),
    'lockVersion' => $purchaseOrder->lock_version,
    'permissions' => [
        'canUpdate' => $user !== null && Gate::forUser($user)->check('update', $purchaseOrder),
        'canMarkReadyForReview' => $user !== null && Gate::forUser($user)->check('markReadyForReview', $purchaseOrder),
        'canCancel' => $user !== null && Gate::forUser($user)->check('cancel', $purchaseOrder),
    ],
];
```

- [x] **Step 3: Add `CreatePurchaseOrderFromHandoff`**

Create `apps/api/Domains/PurchaseOrder/Actions/CreatePurchaseOrderFromHandoff.php`. The core transaction must match this behavior:

```php
$handoff = PurchaseOrderRequestHandoff::query()
    ->whereKey($handoff->id)
    ->lockForUpdate()
    ->firstOrFail();

if ($handoff->statusState() === PurchaseOrderRequestHandoffStatus::Cancelled) {
    throw new ConflictHttpException('Cancelled PO handoffs cannot create purchase orders.');
}

if (! in_array($handoff->statusState(), [PurchaseOrderRequestHandoffStatus::Ready, PurchaseOrderRequestHandoffStatus::Exported], true)) {
    throw new ConflictHttpException('PO handoff must be ready or exported before creating a purchase order.');
}

$existing = PurchaseOrder::query()
    ->where('tenant_id', $handoff->tenant_id)
    ->where('purchase_order_request_handoff_id', $handoff->id)
    ->with('lines')
    ->first();

if ($existing !== null) {
    return $existing;
}
```

Create the PO with `PurchaseOrderNumber::next($handoff->tenant)`, source ids copied from the handoff, snapshots copied from the handoff, `status => PurchaseOrderStatus::Draft`, `created_by_user_id => $actor->id`, and `lock_version => 1`.

Create lines from `$handoff->line_snapshot`:

```php
foreach (array_values($handoff->line_snapshot ?? []) as $index => $line) {
    PurchaseOrderLine::query()->create([
        'tenant_id' => $handoff->tenant_id,
        'purchase_order_id' => $purchaseOrder->id,
        'source_line_id' => data_get($line, 'id'),
        'line_number' => (int) (data_get($line, 'lineNumber') ?? $index + 1),
        'description' => (string) data_get($line, 'description', 'Purchase order line'),
        'category' => data_get($line, 'category'),
        'sku' => data_get($line, 'sku'),
        'unit' => (string) data_get($line, 'unit', 'each'),
        'quantity' => (string) data_get($line, 'quantity', '1'),
        'unit_price' => (string) data_get($line, 'unitPrice', '0.00'),
        'subtotal_amount' => (string) data_get($line, 'subtotalAmount', data_get($line, 'lineTotal', '0.00')),
        'tax_amount' => data_get($line, 'taxAmount'),
        'freight_amount' => data_get($line, 'freightAmount'),
        'discount_amount' => data_get($line, 'discountAmount'),
        'total_amount' => (string) data_get($line, 'totalAmount', data_get($line, 'lineTotal', '0.00')),
        'currency' => $handoff->currency,
        'needed_by_date' => data_get($line, 'neededByDate'),
        'delivery_location' => data_get($handoff->source_snapshot, 'requisition.deliveryLocation'),
        'source_snapshot' => $line,
    ]);
}
```

Record `purchase_order.created` with source handoff id and number.

- [x] **Step 4: Add update, ready, and cancel actions**

`UpdatePurchaseOrder` must:

- lock the row
- require `status === Draft`
- assert `lockVersion`
- update only draft operational fields
- increment `lock_version`
- record `purchase_order.updated`

Allowed update fields:

```php
[
    'requested_po_date',
    'expected_delivery_date',
    'billing_name',
    'billing_address',
    'shipping_name',
    'shipping_address',
    'delivery_attention',
    'payment_terms',
    'delivery_terms',
    'buyer_note',
    'finance_note',
]
```

`MarkPurchaseOrderReadyForReview` must require:

```php
$required = [
    'billing_name' => $purchaseOrder->billing_name,
    'billing_address' => $purchaseOrder->billing_address,
    'shipping_name' => $purchaseOrder->shipping_name,
    'shipping_address' => $purchaseOrder->shipping_address,
    'payment_terms' => $purchaseOrder->payment_terms,
];
```

If any required value is empty, throw `ConflictHttpException('Purchase order requires billing, shipping, and payment terms before review.')`.

`CancelPurchaseOrder` must require draft status, lock version, and a non-empty reason, then set `cancelled`.

- [x] **Step 5: Add form requests**

`UpdatePurchaseOrderRequest` rules:

```php
return [
    'lockVersion' => ['required', 'integer', 'min:1'],
    'requestedPoDate' => ['nullable', 'date'],
    'expectedDeliveryDate' => ['nullable', 'date'],
    'billingName' => ['nullable', 'string', 'max:255'],
    'billingAddress' => ['nullable', 'array'],
    'shippingName' => ['nullable', 'string', 'max:255'],
    'shippingAddress' => ['nullable', 'array'],
    'deliveryAttention' => ['nullable', 'string', 'max:255'],
    'paymentTerms' => ['nullable', 'string', 'max:255'],
    'deliveryTerms' => ['nullable', 'string', 'max:255'],
    'buyerNote' => ['nullable', 'string', 'max:2000'],
    'financeNote' => ['nullable', 'string', 'max:2000'],
];
```

`MarkPurchaseOrderReadyForReviewRequest` rules:

```php
return ['lockVersion' => ['required', 'integer', 'min:1']];
```

`CancelPurchaseOrderRequest` rules:

```php
return [
    'lockVersion' => ['required', 'integer', 'min:1'],
    'reason' => ['required', 'string', 'min:3', 'max:1000'],
];
```

- [x] **Step 6: Add controller and routes**

Create `PurchaseOrderController` with `index`, `show`, `createFromHandoff`, `update`, `readyForReview`, and `cancel`. Use tenant-filtered queries only:

```php
PurchaseOrder::query()
    ->where('tenant_id', $tenant->id)
    ->with('lines')
```

Add routes inside the existing tenant-scoped route group:

```php
Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
Route::post('/po-handoffs/{handoff}/purchase-order', [PurchaseOrderController::class, 'createFromHandoff']);
Route::patch('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
Route::post('/purchase-orders/{purchaseOrder}/ready-for-review', [PurchaseOrderController::class, 'readyForReview']);
Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
```

- [x] **Step 7: Run the focused API test**

Run:

```bash
cd apps/api && php artisan test --filter PurchaseOrderCreationApiTest
```

Expected: all `PurchaseOrderCreationApiTest` tests pass.

- [x] **Step 8: Commit API behavior**

```bash
git add apps/api/Domains/PurchaseOrder apps/api/app/Providers/AppServiceProvider.php apps/api/routes/api.php apps/api/tests/Feature/PurchaseOrderCreationApiTest.php
git commit -m "feat: create purchase orders from handoffs"
```

## Task 4: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated files under: `packages/api-client/src/generated/**`

- [x] **Step 1: Add OpenAPI paths and schemas**

Add schemas for `PurchaseOrder`, `PurchaseOrderLine`, `PurchaseOrderListResponse`, `PurchaseOrderResponse`, `UpdatePurchaseOrderRequest`, `MarkPurchaseOrderReadyForReviewRequest`, and `CancelPurchaseOrderRequest`.

Use operation IDs:

```txt
listPurchaseOrders
showPurchaseOrder
createPurchaseOrderFromHandoff
updatePurchaseOrder
markPurchaseOrderReadyForReview
cancelPurchaseOrder
```

Make `PurchaseOrder.status` enum:

```json
["draft", "ready_for_review", "cancelled"]
```

- [x] **Step 2: Generate client**

Run:

```bash
pnpm generate:api
```

Expected: generated endpoint helpers and schemas appear under `packages/api-client/src/generated`.

- [x] **Step 3: Verify contract**

Run:

```bash
pnpm check:api-contract
```

Expected: command exits 0.

- [x] **Step 4: Commit contract changes**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add purchase order API contract"
```

## Task 5: Web API, MSW, And Purchase Order Workspace Tests

**Files:**

- Create all `apps/web/features/purchase-orders/**` files listed in the file map.
- Modify quotation handoff mocks and tests.

- [x] **Step 1: Add web red test**

Create `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx` with tests:

```tsx
it("renders the purchase order list", async () => {
  renderWithProviders(<PurchaseOrderListPage />);

  expect(await screen.findByRole("heading", { name: "Purchase orders" })).toBeInTheDocument();
  expect(await screen.findByRole("link", { name: /PO-2026-000001/ })).toHaveAttribute(
    "href",
    "/purchase-orders/po-1",
  );
});

it("renders the purchase order workspace and ready action", async () => {
  renderWithProviders(<PurchaseOrderWorkspacePage purchaseOrderId="po-1" />);

  expect(await screen.findByRole("heading", { name: "PO-2026-000001" })).toBeInTheDocument();
  expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
  expect(screen.getByRole("table", { name: "Purchase order lines" })).toBeInTheDocument();
  expect(screen.getByRole("button", { name: "Mark ready for review" })).toBeEnabled();
});
```

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: fails because the web feature does not exist.

- [x] **Step 2: Add API wrappers**

Create `apps/web/features/purchase-orders/api/purchase-order-api.ts` using generated functions:

```ts
import {
  cancelPurchaseOrder,
  listPurchaseOrders,
  markPurchaseOrderReadyForReview,
  showPurchaseOrder,
  updatePurchaseOrder,
} from "@cognify/api-client";
import type {
  CancelPurchaseOrderRequest,
  MarkPurchaseOrderReadyForReviewRequest,
  PurchaseOrder,
  PurchaseOrderListResponse,
  UpdatePurchaseOrderRequest,
} from "@cognify/api-client/schemas";
import { withActiveTenantHeader } from "@/features/identity/api/tenant-headers";
import { unwrapOk } from "@/features/identity/api/api-response";

export async function fetchPurchaseOrders(tenantId: string): Promise<PurchaseOrderListResponse> {
  return unwrapOk(await listPurchaseOrders(withActiveTenantHeader(tenantId))) as PurchaseOrderListResponse;
}

export async function fetchPurchaseOrder(id: string, tenantId: string): Promise<PurchaseOrder> {
  return unwrapOk(await showPurchaseOrder(id, withActiveTenantHeader(tenantId))) as PurchaseOrder;
}

export async function savePurchaseOrder(id: string, payload: UpdatePurchaseOrderRequest, tenantId: string): Promise<PurchaseOrder> {
  return unwrapOk(await updatePurchaseOrder(id, payload, withActiveTenantHeader(tenantId))) as PurchaseOrder;
}

export async function readyPurchaseOrder(id: string, payload: MarkPurchaseOrderReadyForReviewRequest, tenantId: string): Promise<PurchaseOrder> {
  return unwrapOk(await markPurchaseOrderReadyForReview(id, payload, withActiveTenantHeader(tenantId))) as PurchaseOrder;
}

export async function cancelDraftPurchaseOrder(id: string, payload: CancelPurchaseOrderRequest, tenantId: string): Promise<PurchaseOrder> {
  return unwrapOk(await cancelPurchaseOrder(id, payload, withActiveTenantHeader(tenantId))) as PurchaseOrder;
}
```

Adjust imports to the actual generated function names if Orval adds a suffix.

- [x] **Step 3: Add hooks**

Create `use-purchase-order.ts` and `use-purchase-order-actions.ts` with TanStack Query keys:

```ts
export const purchaseOrderKeys = {
  all: ["purchase-orders"] as const,
  list: (tenantId: string) => [...purchaseOrderKeys.all, "list", tenantId] as const,
  detail: (tenantId: string, id: string) => [...purchaseOrderKeys.all, "detail", tenantId, id] as const,
};
```

Mutations must invalidate both `list(tenantId)` and `detail(tenantId, id)`.

- [x] **Step 4: Add MSW fixtures and handlers**

Create a fixture with one draft PO:

```ts
export const purchaseOrderFixture: PurchaseOrder = {
  id: "po-1",
  number: "PO-2026-000001",
  status: "draft",
  currency: "MYR",
  subtotalAmount: "120000.00",
  taxAmount: "7200.00",
  freightAmount: "3900.00",
  discountAmount: "0.00",
  totalAmount: "131100.00",
  requestedPoDate: "2026-06-18",
  expectedDeliveryDate: "2026-07-02",
  billingName: "Acme Finance",
  billingAddress: { line1: "Level 10", city: "Kuala Lumpur", country: "MY" },
  shippingName: "Acme Warehouse",
  shippingAddress: { line1: "Dock 4", city: "Shah Alam", country: "MY" },
  deliveryAttention: "Warehouse receiving",
  paymentTerms: "Net 30",
  deliveryTerms: "DAP",
  buyerNote: "Confirm delivery slot before dispatch.",
  financeNote: "Charge to expansion budget.",
  source: { handoffId: "po-handoff-1", recommendationId: "award-1", rfqId: "1", snapshot: {} },
  vendor: { id: "vendor-1", name: "Northwind Traders" },
  approval: { finalDecision: "approved" },
  evidence: [],
  lines: [
    {
      id: "po-line-1",
      lineNumber: 1,
      description: "Pallet rack bay",
      unit: "each",
      quantity: "10.0000",
      unitPrice: "12000.00",
      subtotalAmount: "120000.00",
      totalAmount: "120000.00",
      currency: "MYR",
      source: {},
    },
  ],
  lockVersion: 1,
  permissions: { canUpdate: true, canMarkReadyForReview: true, canCancel: true },
};
```

Handlers:

```ts
http.get("/api/purchase-orders", () => HttpResponse.json({ data: [purchaseOrderFixture], meta: { total: 1 } })),
http.get("/api/purchase-orders/:purchaseOrder", ({ params }) => HttpResponse.json({ data: purchaseOrderFixture })),
http.patch("/api/purchase-orders/:purchaseOrder", async ({ request }) => {
  const payload = await request.json();
  return HttpResponse.json({ data: { ...purchaseOrderFixture, ...payload, lockVersion: 2 } });
}),
http.post("/api/purchase-orders/:purchaseOrder/ready-for-review", () =>
  HttpResponse.json({ data: { ...purchaseOrderFixture, status: "ready_for_review", lockVersion: 2 } }),
),
```

- [x] **Step 5: Add list and workspace pages**

Create routes that delegate to workflow components:

```tsx
import { PurchaseOrderListPage } from "@/features/purchase-orders/workflows/purchase-order-list-page";

export default function Page() {
  return <PurchaseOrderListPage />;
}
```

```tsx
import { PurchaseOrderWorkspacePage } from "@/features/purchase-orders/workflows/purchase-order-workspace-page";

export default async function Page({ params }: { params: Promise<{ purchaseOrderId: string }> }) {
  const { purchaseOrderId } = await params;
  return <PurchaseOrderWorkspacePage purchaseOrderId={purchaseOrderId} />;
}
```

The workspace must render an `h1` with the PO number, source/vendor summary, line table, draft fields, and action buttons.

- [x] **Step 6: Run web focused test**

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: test passes.

- [x] **Step 7: Commit web PO workspace foundation**

```bash
git add apps/web/app/(workspace)/purchase-orders apps/web/features/purchase-orders
git commit -m "feat: add purchase order workspace"
```

## Task 6: Create PO From Existing Handoff Panel

**Files:**

- Modify: `apps/web/features/quotations/api/quotation-award-recommendation-api.ts`
- Modify: `apps/web/features/quotations/hooks/use-rfq-award-recommendation-actions.ts`
- Modify: `apps/web/features/quotations/components/rfq-award-po-handoff-panel.tsx`
- Modify: quotation mocks/tests listed in file map.

- [x] **Step 1: Add API wrapper and hook**

Add a generated-client-backed wrapper:

```ts
export async function createPurchaseOrderFromPoHandoff(handoffId: string, tenantId: string): Promise<PurchaseOrder> {
  const response = await createPurchaseOrderFromHandoff(handoffId, withActiveTenantHeader(tenantId));
  return unwrapOk(response) as PurchaseOrder;
}
```

Add a mutation hook:

```ts
export function useCreatePurchaseOrderFromRfqAwardHandoff(rfqId: string, handoffId: string | undefined) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const tenantId = useActiveTenantId();

  return useMutation({
    mutationFn: () => {
      if (!tenantId || !handoffId) throw new Error("PO handoff is not available.");
      return createPurchaseOrderFromPoHandoff(handoffId, tenantId);
    },
    onSuccess: (purchaseOrder) => {
      void queryClient.invalidateQueries({ queryKey: rfqAwardRecommendationKeys.detail(tenantId, rfqId) });
      router.push(`/purchase-orders/${purchaseOrder.id}`);
    },
  });
}
```

Use the project’s actual active-tenant hook names.

- [x] **Step 2: Update PO handoff panel**

Show `Create purchase order` when:

```ts
const canCreatePurchaseOrder =
  handoff?.permissions.canExport &&
  (handoff.status === "ready" || handoff.status === "exported") &&
  !sourceValue(handoff.source, "purchaseOrderId");
```

Add the button beside export actions:

```tsx
<Button
  disabled={createPurchaseOrder.isPending}
  onClick={() => {
    setLastAction("createPurchaseOrder");
    createPurchaseOrder.mutate();
  }}
>
  Create purchase order
</Button>
```

- [x] **Step 3: Extend quotation MSW handlers**

Add:

```ts
http.post("/api/po-handoffs/:handoff/purchase-order", ({ params }) => {
  return HttpResponse.json({ data: purchaseOrderFixture }, { status: 201 });
});
```

- [x] **Step 4: Extend workspace test**

In `apps/web/features/quotations/tests/rfq-award-recommendation-workspace.test.tsx`, assert:

```tsx
await user.click(screen.getByRole("button", { name: "Create purchase order" }));
expect(router.push).toHaveBeenCalledWith("/purchase-orders/po-1");
```

- [x] **Step 5: Run focused quotation test**

Run:

```bash
pnpm --dir apps/web exec vitest run features/quotations/tests/rfq-award-recommendation-workspace.test.tsx
```

Expected: passes.

- [x] **Step 6: Commit handoff-to-PO UI**

```bash
git add apps/web/features/quotations apps/web/features/purchase-orders
git commit -m "feat: connect PO handoff to purchase order creation"
```

## Task 7: Navigation And Search Discovery

**Files:**

- Modify: `apps/web/components/default-shell/navigation.tsx`
- Modify: `apps/web/features/search/search-contract.ts`
- Modify: `apps/web/features/search/search-commands.ts`
- Modify: `apps/web/features/search/mocks/search-fixtures.ts`
- Modify: `apps/web/features/search/tests/search-commands.test.ts`

- [x] **Step 1: Add purchase orders navigation**

In `finalNavigationItems`, add a Procurement sub-item:

```ts
{
  title: "Purchase orders",
  url: "/purchase-orders",
  implemented: true,
  permission: canUseRequisitions,
}
```

Also add a secondary shortcut only if the shell remains visually balanced after adding it; otherwise leave discovery through Procurement and search.

- [x] **Step 2: Add search command**

In `getSearchCommands`, add:

```ts
{
  id: "navigate:/purchase-orders",
  group: "Navigation",
  label: "Open purchase orders",
  description: "Go to purchase orders",
  href: "/purchase-orders",
  keywords: ["purchase orders", "po", "procurement"],
  icon: ReceiptText,
  enabled: canUseRequisitions(permissions),
}
```

- [x] **Step 3: Add search tests**

In `search-commands.test.ts`, assert:

```ts
expect(commands.some((command) => command.label === "Open purchase orders")).toBe(true);
```

- [x] **Step 4: Run navigation/search tests**

Run:

```bash
pnpm --dir apps/web exec vitest run components/default-shell/navigation.test.tsx features/search/tests/search-commands.test.ts
```

Expected: passes.

- [x] **Step 5: Commit discovery changes**

```bash
git add apps/web/components/default-shell/navigation.tsx apps/web/features/search
git commit -m "feat: surface purchase order navigation"
```

## Task 8: Final Contract, Typecheck, Roadmap, And Verification

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: this plan by checking completed boxes.

- [x] **Step 1: Run API verification**

Run:

```bash
cd apps/api && php artisan test --filter PurchaseOrderCreationApiTest
```

Expected: all tests pass.

- [x] **Step 2: Run existing handoff regression**

Run:

```bash
cd apps/api && php artisan test --filter PurchaseOrderRequestHandoffApiTest
```

Expected: all tests pass.

- [x] **Step 3: Run contract verification**

Run:

```bash
pnpm check:api-contract
```

Expected: exits 0.

- [x] **Step 4: Run web focused tests**

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx features/quotations/tests/rfq-award-recommendation-workspace.test.tsx components/default-shell/navigation.test.tsx features/search/tests/search-commands.test.ts
```

Expected: all listed tests pass.

- [x] **Step 5: Run web typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: exits 0.

- [x] **Step 6: Update roadmap row**

After all verification passes, update P1-36 in `docs/01-product/feature-roadmap.md`:

```md
| P1-36 | Purchase Order Creation | ... | Fully Implemented | `docs/superpowers/specs/2026-06-09-purchase-order-creation-design.md` | `docs/superpowers/plans/2026-06-09-purchase-order-creation.md` |  | Implemented as ready/exported PO handoff conversion into a durable draft purchase order with line rows, buyer/admin draft update, ready-for-review state, audit events, generated-client web workspace, and navigation/search discovery. |
```

- [x] **Step 7: Run whitespace diff check**

Run:

```bash
git diff --check
```

Expected: no output.

- [x] **Step 8: Commit completion docs**

```bash
git add docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-06-09-purchase-order-creation.md
git commit -m "docs: mark purchase order creation implemented"
```

## Plan Self-Review Checklist

- P1-36 source boundary is ready/exported PO handoff, not direct award conversion.
- P1-37 PO approval is deferred and starts from `ready_for_review`.
- P1-38 supplier issue is deferred.
- Source price, quantity, currency, vendor, and line identity are locked in P1-36.
- API tests cover tenant isolation, role denial, idempotency, lock conflicts, ready transition, and cancellation.
- Web tests cover list, workspace, source handoff action, navigation, and search command discovery.
- OpenAPI regeneration and contract checks are explicit and sequenced before web generated-client use.

# Receiving and Goods Receipt Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-40 so issued, acknowledged, and change-pending purchase orders can receive goods receipt records with partial receipt support, tolerance checks, optional requester/buyer confirmation, and audit events.

**Architecture:** Add a `Receiving` domain under `apps/api/Domains/Receiving/` with `GoodsReceipt` and `GoodsReceiptLine` models, actions for recording and confirming receipts, and OpenAPI-generated client endpoints. Extend `PurchaseOrderLine` with cumulative received/accepted quantity fields. Insert a goods-receipt panel in the existing PO workspace page. Keep the slice thin but end-to-end usable for real procurement receiving operations.

**Tech Stack:** Laravel 12, Eloquent, Laravel Sanctum route-stack tests, OpenAPI JSON, Orval `@cognify/api-client`, Next.js App Router, React, TanStack Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## File Map

Backend files:

- Create `apps/api/Domains/Receiving/States/GoodsReceiptStatus.php`
- Create `apps/api/Domains/Receiving/Models/GoodsReceipt.php`
- Create `apps/api/Domains/Receiving/Models/GoodsReceiptLine.php`
- Create `apps/api/Domains/Receiving/Actions/RecordGoodsReceipt.php`
- Create `apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByRequester.php`
- Create `apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByBuyer.php`
- Create `apps/api/Domains/Receiving/Support/ReceivingNumber.php`
- Create `apps/api/Domains/Receiving/Policies/GoodsReceiptPolicy.php`
- Create `apps/api/Domains/Receiving/Http/Controllers/GoodsReceiptController.php`
- Create `apps/api/Domains/Receiving/Http/Requests/RecordGoodsReceiptRequest.php`
- Create `apps/api/Domains/Receiving/Http/Requests/ConfirmGoodsReceiptRequest.php`
- Create `apps/api/Domains/Receiving/Http/Resources/GoodsReceiptResource.php`
- Create `apps/api/Domains/Receiving/Http/Resources/GoodsReceiptLineResource.php`
- Create `apps/api/database/migrations/2026_06_11_010000_create_goods_receipts_table.php`
- Create `apps/api/database/migrations/2026_06_11_010100_add_receiving_fields_to_purchase_order_lines_table.php`
- Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`
- Modify `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderLineResource.php`
- Modify `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify `apps/api/routes/api.php`
- Create `apps/api/tests/Feature/GoodsReceiptApiTest.php`
- Modify `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify `apps/api/tests/Feature/DemoSeederTest.php`
- Modify `apps/api/storage/openapi/openapi.json`

Contract and web files:

- Regenerate `packages/api-client/src/generated/*`
- Create `apps/web/features/receiving/api/receiving-api.ts`
- Create `apps/web/features/receiving/hooks/use-goods-receipts.ts`
- Create `apps/web/features/receiving/components/goods-receipt-panel.tsx`
- Create `apps/web/features/receiving/components/goods-receipt-form.tsx`
- Create `apps/web/features/receiving/mocks/receiving-fixtures.ts`
- Create `apps/web/features/receiving/mocks/receiving-handlers.ts`
- Create `apps/web/features/receiving/tests/receiving-workflow.test.tsx`
- Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify `docs/01-product/feature-roadmap.md` after PR number is known

## Task 1: Backend Red Tests For Goods Receipt Workflow

**Files:**

- Create: `apps/api/tests/Feature/GoodsReceiptApiTest.php`

- [ ] **Step 1: Create the failing API test file**

Use the existing `PurchaseOrderChangeOrderApiTest` helper style. Start with tests that define the intended API behavior before creating any production models or routes.

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoodsReceiptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_record_full_goods_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'receiptReference' => 'D/O 98765',
                'notes' => 'Delivered on time.',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '10.0000',
                    'quantityAccepted' => '10.0000',
                    'notes' => 'All items in good condition.',
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.lines.0.quantityReceived', '10.0000')
            ->assertJsonPath('data.lines.0.quantityAccepted', '10.0000');

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '10.0000',
            'cumulative_quantity_accepted' => '10.0000',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'goods_receipt.recorded',
        ]);
    }

    public function test_partial_receipts_accumulate(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '4.0000',
                    'quantityAccepted' => '4.0000',
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '4.0000',
        ]);

        $po->refresh();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-14',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '6.0000',
                    'quantityAccepted' => '5.0000',
                ]],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'cumulative_quantity_received' => '10.0000',
            'cumulative_quantity_accepted' => '9.0000',
        ]);
    }

    public function test_over_receipt_beyond_tolerance_is_rejected(): void
    {
        $po = $this->issuedPurchaseOrder(lockVersion: 1);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $line->forceFill(['over_receipt_tolerance_percent' => '10.00'])->save();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '12.0000',
                    'quantityAccepted' => '12.0000',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.quantityReceived']);
    }

    public function test_receipt_against_cancelled_line_is_rejected(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $line->forceFill(['status' => 'cancelled'])->save();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.purchaseOrderLineId']);
    }

    public function test_requester_and_buyer_can_confirm_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $response = $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '10.0000',
                    'quantityAccepted' => '10.0000',
                ]],
            ]);

        $receiptId = $response->json('data.id');

        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertForbidden();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-requester", [
                'lockVersion' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'requester_confirmed');

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/goods-receipts/{$receiptId}/confirm-buyer", [
                'lockVersion' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'buyer_confirmed');
    }

    public function test_lock_version_conflict_on_stale_receipt(): void
    {
        $po = $this->issuedPurchaseOrder(lockVersion: 4);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => 3,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertStatus(409);
    }

    public function test_cross_tenant_po_access_is_denied(): void
    {
        $po = $this->issuedPurchaseOrder();
        $otherTenant = Tenant::factory()->create();
        $buyer = $this->tenantUser($otherTenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($otherTenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertNotFound();
    }

    public function test_non_buyer_cannot_record_receipt(): void
    {
        $po = $this->issuedPurchaseOrder();
        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/goods-receipts", [
                'lockVersion' => $po->lock_version,
                'receiptDate' => '2026-06-12',
                'lines' => [[
                    'purchaseOrderLineId' => (string) $line->id,
                    'quantityReceived' => '1.0000',
                    'quantityAccepted' => '1.0000',
                ]],
            ])
            ->assertForbidden();
    }

    private function issuedPurchaseOrder(int $lockVersion = 1): PurchaseOrder
    {
        $po = $this->purchaseOrder(status: 'issued', lockVersion: $lockVersion);

        $po->forceFill([
            'issued_by_user_id' => $this->tenantUser($po->tenant, TenantRole::Buyer->value)->id,
            'issued_at' => now(),
            'issue_method' => 'manual_email',
            'supplier_contact_name' => 'Priya Supplier',
            'supplier_contact_email' => 'priya.supplier@example.com',
            'supplier_version_number' => 1,
            'supplier_version' => ['versionNumber' => 1, 'purchaseOrder' => ['number' => $po->number]],
        ])->save();

        return $po->fresh('lines');
    }

    private function purchaseOrder(string $status = 'draft', int $lockVersion = 1): PurchaseOrder
    {
        $tenant = Tenant::factory()->create();
        $vendor = Vendor::factory()->for($tenant)->create(['name' => 'Northwind Traders']);
        $rfq = Rfq::factory()->for($tenant)->create(['status' => RfqStatus::Awarded]);
        $invitation = RfqInvitation::factory()->for($tenant)->for($rfq)->for($vendor)->create(['status' => RfqInvitationStatus::Awarded]);
        $quotation = Quotation::factory()->for($tenant)->for($rfq)->for($invitation)->for($vendor)->create(['status' => QuotationStatus::Awarded]);
        $version = QuotationVersion::factory()->for($tenant)->for($quotation)->create(['version_number' => 1]);
        $recommendation = RfqAwardRecommendation::factory()->for($tenant)->for($rfq)->for($vendor, 'recommendedVendor')->create();
        $handoff = PurchaseOrderRequestHandoff::factory()->for($tenant)->for($recommendation, 'awardRecommendation')->create([
            'status' => PurchaseOrderRequestHandoffStatus::Ready,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
        ]);
        $buyer = $this->tenantUser($tenant, TenantRole::Buyer->value);

        $po = PurchaseOrder::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_request_handoff_id' => $handoff->id,
            'rfq_award_recommendation_id' => $recommendation->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'number' => 'PO-2026-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => $status,
            'currency' => 'MYR',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'expected_delivery_date' => '2026-07-02',
            'billing_name' => 'Acme Finance',
            'billing_address' => ['line1' => 'Level 10', 'city' => 'Kuala Lumpur', 'country' => 'MY'],
            'shipping_name' => 'Acme Warehouse',
            'shipping_address' => ['line1' => 'Dock 4', 'city' => 'Shah Alam', 'country' => 'MY'],
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'source_snapshot' => ['vendor' => ['id' => (string) $vendor->id, 'name' => $vendor->name]],
            'approval_snapshot' => [],
            'evidence_snapshot' => [],
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $buyer->id,
            'approved_at' => now(),
            'lock_version' => $lockVersion,
        ]);

        PurchaseOrderLine::query()->create([
            'tenant_id' => $tenant->id,
            'purchase_order_id' => $po->id,
            'line_number' => 1,
            'description' => 'Pallet rack bay',
            'category' => 'Warehouse',
            'unit' => 'each',
            'quantity' => '10.0000',
            'unit_price' => '12000.0000',
            'subtotal_amount' => '120000.00',
            'tax_amount' => '7200.00',
            'freight_amount' => '3900.00',
            'discount_amount' => '0.00',
            'total_amount' => '131100.00',
            'currency' => 'MYR',
            'expected_delivery_date' => '2026-07-02',
            'delivery_location' => 'Dock 4',
            'source_snapshot' => [],
            'status' => 'open',
        ]);

        return $po->fresh('lines');
    }

    private function tenantUser(Tenant $tenant, string $role): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role]);

        return $user;
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(CurrentTenant::class)->set($tenant);
        $this->withHeader('X-Tenant-Id', (string) $tenant->id);

        return $this;
    }
}
```

- [ ] **Step 2: Run red test**

Run:
```bash
php artisan test --filter=GoodsReceiptApiTest
```

Expected: FAIL because routes, models, migrations, and controllers do not exist.

- [ ] **Step 3: Commit red test**

```bash
git add apps/api/tests/Feature/GoodsReceiptApiTest.php
git commit -m "test: define goods receipt workflow"
```

## Task 2: Database, Models, States

**Files:**

- Create: `apps/api/Domains/Receiving/States/GoodsReceiptStatus.php`
- Create: `apps/api/Domains/Receiving/Models/GoodsReceipt.php`
- Create: `apps/api/Domains/Receiving/Models/GoodsReceiptLine.php`
- Create: `apps/api/Domains/Receiving/Support/ReceivingNumber.php`
- Create: `apps/api/database/migrations/2026_06_11_010000_create_goods_receipts_table.php`
- Create: `apps/api/database/migrations/2026_06_11_010100_add_receiving_fields_to_purchase_order_lines_table.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`

- [ ] **Step 1: Create domain directory structure**

```bash
mkdir -p apps/api/Domains/Receiving/States apps/api/Domains/Receiving/Models apps/api/Domains/Receiving/Actions apps/api/Domains/Receiving/Support apps/api/Domains/Receiving/Policies apps/api/Domains/Receiving/Http/Controllers apps/api/Domains/Receiving/Http/Requests apps/api/Domains/Receiving/Http/Resources
```

- [ ] **Step 2: Add status enum**

`apps/api/Domains/Receiving/States/GoodsReceiptStatus.php`:

```php
<?php

namespace Domains\Receiving\States;

enum GoodsReceiptStatus: string
{
    case Completed = 'completed';
    case RequesterConfirmed = 'requester_confirmed';
    case BuyerConfirmed = 'buyer_confirmed';
}
```

- [ ] **Step 3: Add migrations**

`apps/api/database/migrations/2026_06_11_010000_create_goods_receipts_table.php`:

```php
<?php

use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->string('number');
            $table->string('status');
            $table->date('receipt_date');
            $table->string('receipt_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignIdFor(User::class, 'requester_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requester_confirmed_at')->nullable();
            $table->foreignIdFor(User::class, 'buyer_confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_order_id'], 'goods_receipts_tenant_po_idx');
            $table->index(['tenant_id', 'status', 'recorded_at'], 'goods_receipts_tenant_status_recorded_idx');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->decimal('quantity_ordered', 18, 4);
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_accepted', 18, 4);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'purchase_order_line_id'], 'goods_receipt_line_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'goods_receipt_lines_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
    }
};
```

`apps/api/database/migrations/2026_06_11_010100_add_receiving_fields_to_purchase_order_lines_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('cumulative_quantity_received', 18, 4)->default(0)->after('cancelled_reason');
            $table->decimal('cumulative_quantity_accepted', 18, 4)->default(0)->after('cumulative_quantity_received');
            $table->decimal('over_receipt_tolerance_percent', 5, 2)->default(10.00)->after('cumulative_quantity_accepted');
            $table->timestamp('last_receipt_at')->nullable()->after('over_receipt_tolerance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropColumn([
                'cumulative_quantity_received',
                'cumulative_quantity_accepted',
                'over_receipt_tolerance_percent',
                'last_receipt_at',
            ]);
        });
    }
};
```

- [ ] **Step 4: Add model classes**

`apps/api/Domains/Receiving/Models/GoodsReceipt.php`:

```php
<?php

namespace Domains\Receiving\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class GoodsReceipt extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'number',
        'status',
        'receipt_date',
        'receipt_reference',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
        'requester_confirmed_by_user_id',
        'requester_confirmed_at',
        'buyer_confirmed_by_user_id',
        'buyer_confirmed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'receipt_date' => 'immutable_date',
            'recorded_at' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $receipt): void {
            if ($receipt->purchase_order_id !== null && $receipt->isDirty(['purchase_order_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($receipt->purchase_order_id)
                    ->where('tenant_id', $receipt->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt purchase order must belong to the same tenant.');
                }
            }

            if ($receipt->recorded_by_user_id !== null && $receipt->isDirty(['recorded_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($receipt->recorded_by_user_id)
                    ->whereHas('tenants', fn ($q) => $q->whereKey($receipt->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt recorder must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class)->orderBy('line_number');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function requesterConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_confirmed_by_user_id');
    }

    public function buyerConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_confirmed_by_user_id');
    }

    public function statusState(): GoodsReceiptStatus
    {
        return $this->status instanceof GoodsReceiptStatus
            ? $this->status
            : GoodsReceiptStatus::from((string) $this->getAttribute('status'));
    }
}
```

`apps/api/Domains/Receiving/Models/GoodsReceiptLine.php`:

```php
<?php

namespace Domains\Receiving\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class GoodsReceiptLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'purchase_order_line_id',
        'line_number',
        'quantity_ordered',
        'quantity_received',
        'quantity_accepted',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_accepted' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->isDirty(['goods_receipt_id', 'purchase_order_line_id', 'tenant_id'])) {
                if ($line->tenant_id === null && $line->goods_receipt_id !== null) {
                    $line->tenant_id = GoodsReceipt::query()
                        ->whereKey($line->goods_receipt_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = PurchaseOrderLine::query()
                    ->whereKey($line->purchase_order_line_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt line PO line must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}
```

- [ ] **Step 5: Add receiving number generator**

`apps/api/Domains/Receiving/Support/ReceivingNumber.php`:

```php
<?php

namespace Domains\Receiving\Support;

use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class ReceivingNumber
{
    public static function nextFor(PurchaseOrder $purchaseOrder): string
    {
        $year = now()->format('Y');

        $sequence = DB::transaction(function () use ($purchaseOrder, $year): int {
            $row = DB::table('goods_receipts')
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->where('number', 'like', "GR-{$year}-%")
                ->lockForUpdate()
                ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(number, LENGTH(?) + 2) AS UNSIGNED)), 0) + 1 AS next_seq', ["GR-{$year}-"])
                ->first();

            return (int) $row->next_seq;
        });

        return sprintf('GR-%s-%06d', $year, $sequence);
    }
}
```

- [ ] **Step 6: Extend PurchaseOrderLine**

Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`:

Add fillable fields at the end of `$fillable` array:
```php
'cumulative_quantity_received',
'cumulative_quantity_accepted',
'over_receipt_tolerance_percent',
'last_receipt_at',
```

Add casts in `casts()`:
```php
'cumulative_quantity_received' => 'decimal:4',
'cumulative_quantity_accepted' => 'decimal:4',
'over_receipt_tolerance_percent' => 'decimal:2',
'last_receipt_at' => 'datetime',
```

- [ ] **Step 7: Run migration and model test**

Run:
```bash
php artisan migrate
php artisan test --filter=GoodsReceiptApiTest
```

Expected: failures move from missing classes/tables to missing routes/actions (should see 404 or missing controller errors).

- [ ] **Step 8: Commit schema foundation**

```bash
git add apps/api/Domains/Receiving/States apps/api/Domains/Receiving/Models apps/api/Domains/Receiving/Support apps/api/database/migrations apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php
git commit -m "feat: add goods receipt schema and models"
```

## Task 3: Backend Actions

**Files:**

- Create: `apps/api/Domains/Receiving/Actions/RecordGoodsReceipt.php`
- Create: `apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByRequester.php`
- Create: `apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByBuyer.php`

- [ ] **Step 1: Implement RecordGoodsReceipt action**

`apps/api/Domains/Receiving/Actions/RecordGoodsReceipt.php`:

```php
<?php

namespace Domains\Receiving\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\Models\GoodsReceiptLine;
use Domains\Receiving\States\GoodsReceiptStatus;
use Domains\Receiving\Support\ReceivingNumber;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordGoodsReceipt
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $payload): GoodsReceipt {
            $po = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($po->status, [PurchaseOrderStatus::Issued, PurchaseOrderStatus::Acknowledged, PurchaseOrderStatus::ChangePending], true)) {
                throw new InvalidArgumentException('Goods receipt can only be recorded for issued, acknowledged, or change-pending purchase orders.');
            }

            $po->assertLockVersion((int) $payload['lockVersion']);

            $receiptNumber = ReceivingNumber::nextFor($po);

            $receipt = GoodsReceipt::query()->create([
                'tenant_id' => $po->tenant_id,
                'purchase_order_id' => $po->id,
                'number' => $receiptNumber,
                'status' => GoodsReceiptStatus::Completed,
                'receipt_date' => $payload['receiptDate'],
                'receipt_reference' => $payload['receiptReference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'recorded_by_user_id' => $actor->id,
                'recorded_at' => now(),
                'lock_version' => 1,
            ]);

            $linesData = [];

            foreach ($payload['lines'] as $linePayload) {
                $poLine = PurchaseOrderLine::query()
                    ->whereKey($linePayload['purchaseOrderLineId'])
                    ->where('tenant_id', $po->tenant_id)
                    ->where('purchase_order_id', $po->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($poLine->status === 'cancelled') {
                    throw new InvalidArgumentException("Line {$poLine->line_number} is cancelled and cannot receive goods.");
                }

                $quantityReceived = (string) $linePayload['quantityReceived'];
                $quantityAccepted = (string) ($linePayload['quantityAccepted'] ?? $quantityReceived);
                $newCumulativeReceived = bcadd((string) $poLine->cumulative_quantity_received, $quantityReceived, 4);
                $orderedQuantity = (string) $poLine->quantity;
                $tolerancePercent = (string) $poLine->over_receipt_tolerance_percent;
                $maxReceivable = bcadd($orderedQuantity, bcmul($orderedQuantity, bcdiv($tolerancePercent, '100', 4), 4), 4);

                if (bccomp($newCumulativeReceived, $maxReceivable, 4) > 0) {
                    throw new InvalidArgumentException(
                        "Line {$poLine->line_number}: cumulative received quantity {$newCumulativeReceived} exceeds tolerance limit of {$maxReceivable}."
                    );
                }

                $newCumulativeAccepted = bcadd((string) $poLine->cumulative_quantity_accepted, $quantityAccepted, 4);

                $linesData[] = [
                    'tenant_id' => $po->tenant_id,
                    'goods_receipt_id' => $receipt->id,
                    'purchase_order_line_id' => $poLine->id,
                    'line_number' => $poLine->line_number,
                    'quantity_ordered' => $orderedQuantity,
                    'quantity_received' => $quantityReceived,
                    'quantity_accepted' => $quantityAccepted,
                    'rejection_reason' => $linePayload['rejectionReason'] ?? null,
                    'notes' => $linePayload['notes'] ?? null,
                ];

                $poLine->forceFill([
                    'cumulative_quantity_received' => $newCumulativeReceived,
                    'cumulative_quantity_accepted' => $newCumulativeAccepted,
                    'last_receipt_at' => now(),
                ])->save();
            }

            foreach ($linesData as $lineData) {
                GoodsReceiptLine::query()->create($lineData);
            }

            $po->forceFill(['lock_version' => $po->lock_version + 1])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $po->tenant,
                actor: $actor,
                action: 'goods_receipt.recorded',
                subject: $receipt,
                metadata: [
                    'purchaseOrderId' => (string) $po->id,
                    'purchaseOrderNumber' => $po->number,
                    'receiptNumber' => $receiptNumber,
                    'lineCount' => count($linesData),
                    'totalQuantityReceived' => array_sum(array_map(fn ($l) => (float) $l['quantity_received'], $linesData)),
                ],
            ));

            return $receipt->fresh('lines');
        });
    }
}
```

- [ ] **Step 2: Implement confirmation actions**

`apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByRequester.php`:

```php
<?php

namespace Domains\Receiving\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Support\Facades\DB;

class ConfirmGoodsReceiptByRequester
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->whereKey($receipt->id)
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::RequesterConfirmed,
                'requester_confirmed_by_user_id' => $actor->id,
                'requester_confirmed_at' => now(),
                'lock_version' => $receipt->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $receipt->tenant,
                actor: $actor,
                action: 'goods_receipt.requester_confirmed',
                subject: $receipt,
                metadata: [
                    'purchaseOrderId' => (string) $receipt->purchase_order_id,
                    'receiptNumber' => $receipt->number,
                ],
            ));

            return $receipt->fresh('lines');
        });
    }
}
```

`apps/api/Domains/Receiving/Actions/ConfirmGoodsReceiptByBuyer.php`:

```php
<?php

namespace Domains\Receiving\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Support\Facades\DB;

class ConfirmGoodsReceiptByBuyer
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(GoodsReceipt $receipt, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): GoodsReceipt {
            $receipt = GoodsReceipt::query()
                ->whereKey($receipt->id)
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt->forceFill([
                'status' => GoodsReceiptStatus::BuyerConfirmed,
                'buyer_confirmed_by_user_id' => $actor->id,
                'buyer_confirmed_at' => now(),
                'lock_version' => $receipt->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $receipt->tenant,
                actor: $actor,
                action: 'goods_receipt.buyer_confirmed',
                subject: $receipt,
                metadata: [
                    'purchaseOrderId' => (string) $receipt->purchase_order_id,
                    'receiptNumber' => $receipt->number,
                ],
            ));

            return $receipt->fresh('lines');
        });
    }
}
```

- [ ] **Step 3: Run backend action test**

Run:
```bash
php artisan test --filter=GoodsReceiptApiTest
```

Expected: failures should now be route/resource/request related (404/405), not action internals.

- [ ] **Step 4: Commit domain actions**

```bash
git add apps/api/Domains/Receiving/Actions
git commit -m "feat: add goods receipt domain actions"
```

## Task 4: API Requests, Resources, Controller, Routes, Policy

**Files:**

- Create: `apps/api/Domains/Receiving/Http/Controllers/GoodsReceiptController.php`
- Create: `apps/api/Domains/Receiving/Http/Requests/RecordGoodsReceiptRequest.php`
- Create: `apps/api/Domains/Receiving/Http/Requests/ConfirmGoodsReceiptRequest.php`
- Create: `apps/api/Domains/Receiving/Http/Resources/GoodsReceiptResource.php`
- Create: `apps/api/Domains/Receiving/Http/Resources/GoodsReceiptLineResource.php`
- Create: `apps/api/Domains/Receiving/Policies/GoodsReceiptPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderLineResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add request validation**

`apps/api/Domains/Receiving/Http/Requests/RecordGoodsReceiptRequest.php`:

```php
<?php

namespace Domains\Receiving\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('recordGoodsReceipt', [$this->route('purchaseOrder')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'receiptDate' => ['required', 'date', 'before_or_equal:today'],
            'receiptReference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchaseOrderLineId' => ['required', 'string', 'uuid'],
            'lines.*.quantityReceived' => ['required', 'numeric', 'gt:0'],
            'lines.*.quantityAccepted' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.rejectionReason' => ['nullable', 'string', 'max:1000'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

`apps/api/Domains/Receiving/Http/Requests/ConfirmGoodsReceiptRequest.php`:

```php
<?php

namespace Domains\Receiving\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
```

- [ ] **Step 2: Add resources**

`apps/api/Domains/Receiving/Http/Resources/GoodsReceiptLineResource.php`:

```php
<?php

namespace Domains\Receiving\Http\Resources;

use Domains\Receiving\Models\GoodsReceiptLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceiptLine
 */
class GoodsReceiptLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderLineId' => (string) $this->purchase_order_line_id,
            'lineNumber' => $this->line_number,
            'quantityOrdered' => (string) $this->quantity_ordered,
            'quantityReceived' => (string) $this->quantity_received,
            'quantityAccepted' => (string) $this->quantity_accepted,
            'rejectionReason' => $this->rejection_reason,
            'notes' => $this->notes,
        ];
    }
}
```

`apps/api/Domains/Receiving/Http/Resources/GoodsReceiptResource.php`:

```php
<?php

namespace Domains\Receiving\Http\Resources;

use Domains\Receiving\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceipt
 */
class GoodsReceiptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'purchaseOrderId' => (string) $this->purchase_order_id,
            'number' => $this->number,
            'status' => $this->statusState()->value,
            'receiptDate' => $this->receipt_date?->toDateString(),
            'receiptReference' => $this->receipt_reference,
            'notes' => $this->notes,
            'recordedByUserId' => $this->recorded_by_user_id !== null ? (string) $this->recorded_by_user_id : null,
            'recordedAt' => $this->recorded_at?->toISOString(),
            'requesterConfirmedByUserId' => $this->requester_confirmed_by_user_id !== null ? (string) $this->requester_confirmed_by_user_id : null,
            'requesterConfirmedAt' => $this->requester_confirmed_at?->toISOString(),
            'buyerConfirmedByUserId' => $this->buyer_confirmed_by_user_id !== null ? (string) $this->buyer_confirmed_by_user_id : null,
            'buyerConfirmedAt' => $this->buyer_confirmed_at?->toISOString(),
            'lines' => $this->relationLoaded('lines')
                ? GoodsReceiptLineResource::collection($this->lines)->resolve()
                : [],
            'lockVersion' => $this->lock_version,
        ];
    }
}
```

- [ ] **Step 3: Add policy**

`apps/api/Domains/Receiving/Policies/GoodsReceiptPolicy.php`:

```php
<?php

namespace Domains\Receiving\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use Domains\Receiving\Models\GoodsReceipt;

class GoodsReceiptPolicy
{
    public function confirmRequester(GoodsReceipt $goodsReceipt, User $user): bool
    {
        return $user->hasTenantRole($goodsReceipt->tenant_id, TenantRole::Buyer)
            || $user->hasTenantRole($goodsReceipt->tenant_id, TenantRole::Admin);
    }

    public function confirmBuyer(GoodsReceipt $goodsReceipt, User $user): bool
    {
        return $user->hasTenantRole($goodsReceipt->tenant_id, TenantRole::Buyer)
            || $user->hasTenantRole($goodsReceipt->tenant_id, TenantRole::Admin);
    }
}
```

- [ ] **Step 4: Update PurchaseOrderPolicy**

Add method `recordGoodsReceipt` to `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`:

```php
use App\Auth\TenantRole;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;

public function recordGoodsReceipt(User $user, PurchaseOrder $purchaseOrder): bool
{
    return in_array($purchaseOrder->statusState(), [
        PurchaseOrderStatus::Issued,
        PurchaseOrderStatus::Acknowledged,
        PurchaseOrderStatus::ChangePending,
    ], true)
        && ($user->hasTenantRole($purchaseOrder->tenant_id, TenantRole::Buyer)
            || $user->hasTenantRole($purchaseOrder->tenant_id, TenantRole::Admin));
}
```

- [ ] **Step 5: Update PurchaseOrderResource with receivingSummary**

Add to the return array in `PurchaseOrderResource::toArray()`:

```php
'receivingSummary' => [
    'totalReceiptCount' => $purchaseOrder->relationLoaded('goodsReceipts')
        ? $purchaseOrder->goodsReceipts->count()
        : 0,
    'latestReceiptDate' => $purchaseOrder->lines->max('last_receipt_at')?->toDateString(),
],
```

Also add a `goodsReceipts` relation to the `PurchaseOrder` model:

```php
/**
 * @return HasMany<GoodsReceipt, $this>
 */
public function goodsReceipts(): HasMany
{
    return $this->hasMany(\Domains\Receiving\Models\GoodsReceipt::class);
}
```

- [ ] **Step 6: Update PurchaseOrderLineResource**

Add receiving fields to the resource's return array:

```php
'cumulativeQuantityReceived' => (string) ($this->cumulative_quantity_received ?? '0'),
'cumulativeQuantityAccepted' => (string) ($this->cumulative_quantity_accepted ?? '0'),
'overReceiptTolerancePercent' => (string) ($this->over_receipt_tolerance_percent ?? '10.00'),
'lastReceiptAt' => $this->last_receipt_at?->toISOString(),
```

- [ ] **Step 7: Add controller and routes**

`apps/api/Domains/Receiving/Http/Controllers/GoodsReceiptController.php`:

```php
<?php

namespace Domains\Receiving\Http\Controllers;

use App\Audit\AuditRecorder;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Receiving\Actions\ConfirmGoodsReceiptByBuyer;
use Domains\Receiving\Actions\ConfirmGoodsReceiptByRequester;
use Domains\Receiving\Actions\RecordGoodsReceipt;
use Domains\Receiving\Http\Requests\ConfirmGoodsReceiptRequest;
use Domains\Receiving\Http\Requests\RecordGoodsReceiptRequest;
use Domains\Receiving\Http\Resources\GoodsReceiptResource;
use Domains\Receiving\Models\GoodsReceipt;
use Illuminate\Http\Resources\Json\ResourceCollection;

class GoodsReceiptController
{
    public function __construct(
        private readonly RecordGoodsReceipt $recordGoodsReceipt,
        private readonly ConfirmGoodsReceiptByRequester $confirmByRequester,
        private readonly ConfirmGoodsReceiptByBuyer $confirmByBuyer,
    ) {}

    public function index(PurchaseOrder $purchaseOrder): ResourceCollection
    {
        $receipts = GoodsReceipt::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->with('lines')
            ->orderByDesc('recorded_at')
            ->get();

        return GoodReceiptResource::collection($receipts);
    }

    public function store(RecordGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): GoodsReceiptResource
    {
        $receipt = $this->recordGoodsReceipt->handle(
            purchaseOrder: $purchaseOrder,
            actor: $request->user(),
            payload: $request->validated(),
        );

        return new GoodsReceiptResource($receipt);
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $goodsReceipt->load('lines');

        return new GoodsReceiptResource($goodsReceipt);
    }

    public function confirmRequester(ConfirmGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirmRequester', $goodsReceipt);

        $receipt = $this->confirmByRequester->handle(
            receipt: $goodsReceipt,
            actor: $request->user(),
        );

        return new GoodsReceiptResource($receipt);
    }

    public function confirmBuyer(ConfirmGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        $this->authorize('confirmBuyer', $goodsReceipt);

        $receipt = $this->confirmByBuyer->handle(
            receipt: $goodsReceipt,
            actor: $request->user(),
        );

        return new GoodsReceiptResource($receipt);
    }
}
```

Add routes to `apps/api/routes/api.php` inside the existing purchase-order and tenant-middleware group:

```php
Route::get('/purchase-orders/{purchaseOrder}/goods-receipts', [\Domains\Receiving\Http\Controllers\GoodsReceiptController::class, 'index']);
Route::post('/purchase-orders/{purchaseOrder}/goods-receipts', [\Domains\Receiving\Http\Controllers\GoodsReceiptController::class, 'store']);
Route::get('/goods-receipts/{goodsReceipt}', [\Domains\Receiving\Http\Controllers\GoodsReceiptController::class, 'show']);
Route::post('/goods-receipts/{goodsReceipt}/confirm-requester', [\Domains\Receiving\Http\Controllers\GoodsReceiptController::class, 'confirmRequester']);
Route::post('/goods-receipts/{goodsReceipt}/confirm-buyer', [\Domains\Receiving\Http\Controllers\GoodsReceiptController::class, 'confirmBuyer']);
```

Register the GoodsReceiptPolicy in `App\Providers\AuthServiceProvider` if using auto-discovery, or explicitly in `boot()`:

```php
Gate::policy(\Domains\Receiving\Models\GoodsReceipt::class, \Domains\Receiving\Policies\GoodsReceiptPolicy::class);
```

- [ ] **Step 8: Run API test**

Run:
```bash
php artisan test --filter=GoodsReceiptApiTest
```

Expected: all backend goods receipt tests pass.

- [ ] **Step 9: Commit API surface**

```bash
git add apps/api/Domains/Receiving/Http apps/api/Domains/Receiving/Policies apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderLineResource.php apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php apps/api/routes/api.php
git commit -m "feat: expose goods receipt API"
```

## Task 5: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Regenerate: `packages/api-client/src/generated/*`

- [ ] **Step 1: Update OpenAPI manually**

Open `apps/api/storage/openapi/openapi.json` and add:

Paths:
- `GET /api/purchase-orders/{purchaseOrderId}/goods-receipts` → list receipts for PO
- `POST /api/purchase-orders/{purchaseOrderId}/goods-receipts` → record receipt
- `GET /api/goods-receipts/{id}` → show receipt
- `POST /api/goods-receipts/{id}/confirm-requester` → confirm as requester
- `POST /api/goods-receipts/{id}/confirm-buyer` → confirm as buyer

Schemas:
- `GoodsReceipt`
- `GoodsReceiptLine`
- `RecordGoodsReceiptRequest`
- `ConfirmGoodsReceiptRequest`
- `GoodsReceiptStatus` enum

Update:
- `PurchaseOrderStatus` (no change needed)
- `PurchaseOrderLine` with `cumulativeQuantityReceived`, `cumulativeQuantityAccepted`, `overReceiptTolerancePercent`, `lastReceiptAt`
- `PurchaseOrder` with `receivingSummary` object

- [ ] **Step 2: Regenerate client**

Run:
```bash
pnpm generate:api
```

Expected: Orval succeeds and generates endpoint/schema files for goods receipt operations.

- [ ] **Step 3: Check API contract**

Run:
```bash
pnpm check:api-contract
```

Expected: exits 0 with no generated diff.

- [ ] **Step 4: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add goods receipt contract"
```

## Task 6: Web API Hooks, MSW, And Fixtures

**Files:**

- Create: `apps/web/features/receiving/api/receiving-api.ts`
- Create: `apps/web/features/receiving/hooks/use-goods-receipts.ts`
- Create: `apps/web/features/receiving/mocks/receiving-fixtures.ts`
- Create: `apps/web/features/receiving/mocks/receiving-handlers.ts`
- Create: `apps/web/features/receiving/tests/receiving-workflow.test.tsx`

- [ ] **Step 1: Add failing web tests**

`apps/web/features/receiving/tests/receiving-workflow.test.tsx`:

```tsx
import { describe, it, expect } from "vitest";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { HttpResponse, http } from "msw";
import { setupServer } from "msw/node";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { GoodsReceiptPanel } from "../components/goods-receipt-panel";

const server = setupServer();

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function createWrapper() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}

describe("GoodsReceiptPanel", () => {
  it("shows record receipt action for issued PO", async () => {
    server.use(
      http.get("*/api/purchase-orders/:id", () =>
        HttpResponse.json({
          data: {
            id: "po-1",
            number: "PO-2026-000001",
            status: "issued",
            receivingSummary: { totalReceiptCount: 0, latestReceiptDate: null },
            lines: [{ id: "line-1", lineNumber: 1, description: "Test item", quantity: "10.0000", cumulativeQuantityReceived: "0", cumulativeQuantityAccepted: "0", overReceiptTolerancePercent: "10.00" }],
            permissions: { canRecordGoodsReceipt: true },
          },
        })
      ),
      http.get("*/api/purchase-orders/:id/goods-receipts", () =>
        HttpResponse.json({ data: [] })
      ),
    );

    render(<GoodsReceiptPanel purchaseOrderId="po-1" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText("Record receipt")).toBeInTheDocument();
    });
  });

  it("validates and submits a goods receipt", async () => {
    server.use(
      http.get("*/api/purchase-orders/:id", () =>
        HttpResponse.json({
          data: {
            id: "po-1",
            number: "PO-2026-000001",
            status: "issued",
            receivingSummary: { totalReceiptCount: 0, latestReceiptDate: null },
            lines: [{ id: "line-1", lineNumber: 1, description: "Test item", quantity: "10.0000", cumulativeQuantityReceived: "0", cumulativeQuantityAccepted: "0", overReceiptTolerancePercent: "10.00" }],
            permissions: { canRecordGoodsReceipt: true },
          },
        })
      ),
      http.get("*/api/purchase-orders/:id/goods-receipts", () =>
        HttpResponse.json({ data: [] })
      ),
      http.post("*/api/purchase-orders/:id/goods-receipts", () =>
        HttpResponse.json({
          data: {
            id: "gr-1",
            number: "GR-2026-000001",
            status: "completed",
            receiptDate: "2026-06-12",
            receiptReference: "D/O 98765",
            lines: [{ id: "grl-1", purchaseOrderLineId: "line-1", quantityReceived: "10.0000", quantityAccepted: "10.0000" }],
            lockVersion: 1,
          },
        }, { status: 201 })
      ),
    );

    render(<GoodsReceiptPanel purchaseOrderId="po-1" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText("Record receipt")).toBeInTheDocument();
    });

    await userEvent.click(screen.getByText("Record receipt"));

    await waitFor(() => {
      expect(screen.getByText("Record goods receipt")).toBeInTheDocument();
    });

    await userEvent.type(screen.getByLabelText("Receipt reference"), "D/O 98765");
    await userEvent.type(screen.getByLabelText("Quantity received"), "10");

    await userEvent.click(screen.getByText("Save receipt"));

    await waitFor(() => {
      expect(screen.getByText("GR-2026-000001")).toBeInTheDocument();
    });
  });
});
```

Run:
```bash
pnpm --dir apps/web exec vitest run features/receiving/tests/receiving-workflow.test.tsx
```

Expected: FAIL because imports and components do not exist.

- [ ] **Step 2: Add API wrappers**

`apps/web/features/receiving/api/receiving-api.ts`:

```ts
"use client";

import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export interface GoodsReceiptLine {
  id: string;
  purchaseOrderLineId: string;
  lineNumber: number;
  quantityOrdered: string;
  quantityReceived: string;
  quantityAccepted: string;
  rejectionReason: string | null;
  notes: string | null;
}

export interface GoodsReceipt {
  id: string;
  purchaseOrderId: string;
  number: string;
  status: string;
  receiptDate: string;
  receiptReference: string | null;
  notes: string | null;
  recordedByUserId: string | null;
  recordedAt: string | null;
  requesterConfirmedByUserId: string | null;
  requesterConfirmedAt: string | null;
  buyerConfirmedByUserId: string | null;
  buyerConfirmedAt: string | null;
  lines: GoodsReceiptLine[];
  lockVersion: number;
}

export interface RecordGoodsReceiptPayload {
  lockVersion: number;
  receiptDate: string;
  receiptReference?: string | null;
  notes?: string | null;
  lines: Array<{
    purchaseOrderLineId: string;
    quantityReceived: string;
    quantityAccepted?: string;
    rejectionReason?: string | null;
    notes?: string | null;
  }>;
}

async function apiFetch<T>(path: string, options?: RequestInit): Promise<T> {
  const tenantId = getStoredActiveTenantId();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
    ...(tenantId ? { "X-Tenant-Id": tenantId } : {}),
  };

  const response = await fetch(`${BASE_URL}${path}`, {
    ...options,
    headers: { ...headers, ...options?.headers },
    credentials: "include",
  });

  if (!response.ok) {
    const body = await response.json().catch(() => ({}));
    throw new ApiError(response.status, body.message ?? body.error ?? "Request failed", body);
  }

  return response.json();
}

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public body: unknown,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export async function fetchGoodsReceipts(purchaseOrderId: string): Promise<{ data: GoodsReceipt[] }> {
  return apiFetch(`/api/purchase-orders/${purchaseOrderId}/goods-receipts`);
}

export async function fetchGoodsReceipt(goodsReceiptId: string): Promise<{ data: GoodsReceipt }> {
  return apiFetch(`/api/goods-receipts/${goodsReceiptId}`);
}

export async function recordGoodsReceipt(
  purchaseOrderId: string,
  payload: RecordGoodsReceiptPayload,
): Promise<{ data: GoodsReceipt }> {
  return apiFetch(`/api/purchase-orders/${purchaseOrderId}/goods-receipts`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function confirmGoodsReceiptAsRequester(
  goodsReceiptId: string,
  lockVersion: number,
): Promise<{ data: GoodsReceipt }> {
  return apiFetch(`/api/goods-receipts/${goodsReceiptId}/confirm-requester`, {
    method: "POST",
    body: JSON.stringify({ lockVersion }),
  });
}

export async function confirmGoodsReceiptAsBuyer(
  goodsReceiptId: string,
  lockVersion: number,
): Promise<{ data: GoodsReceipt }> {
  return apiFetch(`/api/goods-receipts/${goodsReceiptId}/confirm-buyer`, {
    method: "POST",
    body: JSON.stringify({ lockVersion }),
  });
}
```

- [ ] **Step 3: Add hooks**

`apps/web/features/receiving/hooks/use-goods-receipts.ts`:

```ts
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  confirmGoodsReceiptAsBuyer,
  confirmGoodsReceiptAsRequester,
  fetchGoodsReceipts,
  recordGoodsReceipt,
  type RecordGoodsReceiptPayload,
} from "../api/receiving-api";

export const goodsReceiptKeys = {
  all: ["goods-receipts"] as const,
  list: (tenantId: string, purchaseOrderId: string) =>
    [...goodsReceiptKeys.all, "list", tenantId, purchaseOrderId] as const,
};

export function useGoodsReceipts(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPoId = purchaseOrderId || "no-po";

  return useQuery({
    queryKey: goodsReceiptKeys.list(queryTenantId, queryPoId),
    queryFn: () => fetchGoodsReceipts(purchaseOrderId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function useRecordGoodsReceipt(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: RecordGoodsReceiptPayload) =>
      recordGoodsReceipt(purchaseOrderId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: goodsReceiptKeys.list(tenantId ?? "no-tenant", purchaseOrderId),
      });
    },
  });
}

export function useConfirmGoodsReceiptAsRequester() {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({ goodsReceiptId, lockVersion }: { goodsReceiptId: string; lockVersion: number }) =>
      confirmGoodsReceiptAsRequester(goodsReceiptId, lockVersion),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: goodsReceiptKeys.all });
    },
  });
}

export function useConfirmGoodsReceiptAsBuyer() {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({ goodsReceiptId, lockVersion }: { goodsReceiptId: string; lockVersion: number }) =>
      confirmGoodsReceiptAsBuyer(goodsReceiptId, lockVersion),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: goodsReceiptKeys.all });
    },
  });
}
```

- [ ] **Step 4: Add fixtures**

`apps/web/features/receiving/mocks/receiving-fixtures.ts`:

```ts
export function goodsReceiptFixture(overrides?: Record<string, unknown>) {
  return {
    id: "gr-1",
    purchaseOrderId: "po-1",
    number: "GR-2026-000001",
    status: "completed",
    receiptDate: "2026-06-12",
    receiptReference: null,
    notes: null,
    recordedByUserId: "user-1",
    recordedAt: "2026-06-12T10:00:00Z",
    requesterConfirmedByUserId: null,
    requesterConfirmedAt: null,
    buyerConfirmedByUserId: null,
    buyerConfirmedAt: null,
    lines: [
      {
        id: "grl-1",
        purchaseOrderLineId: "po-line-1",
        lineNumber: 1,
        quantityOrdered: "10.0000",
        quantityReceived: "10.0000",
        quantityAccepted: "10.0000",
        rejectionReason: null,
        notes: null,
      },
    ],
    lockVersion: 1,
    ...overrides,
  };
}

export function partialReceiptFixture() {
  return {
    ...goodsReceiptFixture({
      id: "gr-2",
      number: "GR-2026-000002",
      receiptDate: "2026-06-10",
      lines: [
        {
          id: "grl-2",
          purchaseOrderLineId: "po-line-1",
          lineNumber: 1,
          quantityOrdered: "10.0000",
          quantityReceived: "4.0000",
          quantityAccepted: "4.0000",
          rejectionReason: null,
          notes: "First partial shipment",
        },
      ],
    }),
  };
}

export function receiptWithRejectionFixture() {
  return {
    ...goodsReceiptFixture({
      id: "gr-3",
      number: "GR-2026-000003",
      lines: [
        {
          id: "grl-3",
          purchaseOrderLineId: "po-line-1",
          lineNumber: 1,
          quantityOrdered: "10.0000",
          quantityReceived: "10.0000",
          quantityAccepted: "8.0000",
          rejectionReason: "Two units damaged in transit",
          notes: null,
        },
      ],
    }),
  };
}

export function requesterConfirmedReceiptFixture() {
  return {
    ...goodsReceiptFixture({
      id: "gr-4",
      number: "GR-2026-000004",
      status: "requester_confirmed",
      requesterConfirmedByUserId: "user-2",
      requesterConfirmedAt: "2026-06-13T08:00:00Z",
    }),
  };
}

export function buyerConfirmedReceiptFixture() {
  return {
    ...goodsReceiptFixture({
      id: "gr-5",
      number: "GR-2026-000005",
      status: "buyer_confirmed",
      requesterConfirmedByUserId: "user-2",
      requesterConfirmedAt: "2026-06-13T08:00:00Z",
      buyerConfirmedByUserId: "user-1",
      buyerConfirmedAt: "2026-06-13T09:00:00Z",
    }),
  };
}
```

- [ ] **Step 5: Add MSW handlers**

`apps/web/features/receiving/mocks/receiving-handlers.ts`:

```ts
import { HttpResponse, http } from "msw";
import {
  goodsReceiptFixture,
  partialReceiptFixture,
  receiptWithRejectionFixture,
  requesterConfirmedReceiptFixture,
  buyerConfirmedReceiptFixture,
} from "./receiving-fixtures";

const receipts = [
  goodsReceiptFixture(),
  partialReceiptFixture(),
  receiptWithRejectionFixture(),
  requesterConfirmedReceiptFixture(),
  buyerConfirmedReceiptFixture(),
];

export const receivingHandlers = [
  http.get("*/api/purchase-orders/:purchaseOrderId/goods-receipts", () => {
    return HttpResponse.json({ data: receipts });
  }),

  http.post("*/api/purchase-orders/:purchaseOrderId/goods-receipts", async ({ request }) => {
    const body = await request.json() as Record<string, unknown>;

    if (body && typeof body === "object" && "lockVersion" in body && typeof body.lockVersion === "number") {
      return HttpResponse.json(
        { data: goodsReceiptFixture() },
        { status: 201 },
      );
    }

    return HttpResponse.json(
      { message: "Validation failed", errors: { lockVersion: ["Required"] } },
      { status: 422 },
    );
  }),

  http.get("*/api/goods-receipts/:id", ({ params }) => {
    const receipt = receipts.find((r) => r.id === params.id);
    if (!receipt) {
      return HttpResponse.json({ message: "Not found" }, { status: 404 });
    }
    return HttpResponse.json({ data: receipt });
  }),

  http.post("*/api/goods-receipts/:id/confirm-requester", () => {
    return HttpResponse.json({ data: requesterConfirmedReceiptFixture() });
  }),

  http.post("*/api/goods-receipts/:id/confirm-buyer", () => {
    return HttpResponse.json({ data: buyerConfirmedReceiptFixture() });
  }),
];
```

- [ ] **Step 6: Run web test check**

Run:
```bash
pnpm --dir apps/web exec vitest run features/receiving/tests/receiving-workflow.test.tsx
```

Expected: tests fail because `GoodsReceiptPanel` component doesn't exist yet, but API/hook/import errors should be resolved.

- [ ] **Step 7: Commit web plumbing**

```bash
git add apps/web/features/receiving/api apps/web/features/receiving/hooks apps/web/features/receiving/mocks apps/web/features/receiving/tests
git commit -m "feat: add goods receipt web data plumbing"
```

## Task 7: Web Goods Receipt Panel And Form

**Files:**

- Create: `apps/web/features/receiving/components/goods-receipt-panel.tsx`
- Create: `apps/web/features/receiving/components/goods-receipt-form.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`

- [ ] **Step 1: Build receipt panel**

`apps/web/features/receiving/components/goods-receipt-panel.tsx`:

```tsx
"use client";

import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle, Button } from "@cognify/ui";
import { useGoodsReceipts } from "../hooks/use-goods-receipts";
import { GoodsReceiptForm } from "./goods-receipt-form";
import type { GoodsReceipt } from "../api/receiving-api";

export function GoodsReceiptPanel({ purchaseOrderId }: { purchaseOrderId: string }) {
  const { data, isLoading, isError } = useGoodsReceipts(purchaseOrderId);
  const [showForm, setShowForm] = useState(false);

  if (isLoading) {
    return (
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Goods receipts</CardTitle>
        </CardHeader>
        <CardContent className="py-4 text-sm text-muted-foreground">Loading receipts...</CardContent>
      </Card>
    );
  }

  if (isError) {
    return (
      <Card className="py-0">
        <CardHeader className="border-b bg-muted/30">
          <CardTitle>Goods receipts</CardTitle>
        </CardHeader>
        <CardContent className="py-4 text-sm text-red-600">Could not load goods receipts.</CardContent>
      </Card>
    );
  }

  const receipts = data?.data ?? [];

  return (
    <Card className="py-0">
      <CardHeader className="flex flex-row items-center justify-between border-b bg-muted/30">
        <CardTitle>Goods receipts ({receipts.length})</CardTitle>
        <Button size="sm" onClick={() => setShowForm(true)}>
          Record receipt
        </Button>
      </CardHeader>
      <CardContent className="space-y-3 py-4 text-sm">
        {receipts.length === 0 && !showForm && (
          <p className="text-muted-foreground">No goods receipts recorded yet.</p>
        )}

        {showForm && (
          <GoodsReceiptForm
            purchaseOrderId={purchaseOrderId}
            onComplete={() => setShowForm(false)}
            onCancel={() => setShowForm(false)}
          />
        )}

        {receipts.map((receipt) => (
          <GoodsReceiptRow key={receipt.id} receipt={receipt} />
        ))}
      </CardContent>
    </Card>
  );
}

function GoodsReceiptRow({ receipt }: { receipt: GoodsReceipt }) {
  const totalReceived = receipt.lines.reduce(
    (sum, l) => sum + Number(l.quantityReceived),
    0,
  );
  const statusLabel =
    receipt.status === "buyer_confirmed"
      ? "Buyer confirmed"
      : receipt.status === "requester_confirmed"
        ? "Requester confirmed"
        : "Completed";

  return (
    <div className="rounded-md border p-3">
      <div className="mb-2 flex items-center justify-between">
        <span className="font-mono text-sm font-medium">{receipt.number}</span>
        <span className="rounded-full border px-2 py-0.5 text-xs capitalize text-muted-foreground">
          {statusLabel}
        </span>
      </div>
      <div className="space-y-1 text-xs text-muted-foreground">
        <p>Date: {receipt.receiptDate}</p>
        {receipt.receiptReference && <p>Ref: {receipt.receiptReference}</p>}
        <p>Lines: {receipt.lines.length} ({totalReceived} total received)</p>
        {receipt.notes && <p className="italic">{receipt.notes}</p>}
      </div>
      {receipt.lines.length > 0 && (
        <div className="mt-2 space-y-1 border-t pt-2">
          {receipt.lines.map((line) => (
            <div key={line.id} className="flex items-center justify-between text-xs">
              <span>Line {line.lineNumber}</span>
              <span>
                {line.quantityReceived} rec'd / {line.quantityAccepted} acc'd
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
```

- [ ] **Step 2: Build receipt form**

`apps/web/features/receiving/components/goods-receipt-form.tsx`:

```tsx
"use client";

import { useState } from "react";
import { Button, Input, Textarea, Alert } from "@cognify/ui";
import { useRecordGoodsReceipt } from "../hooks/use-goods-receipts";
import { usePurchaseOrder } from "../../purchase-orders/hooks/use-purchase-order";
import type { RecordGoodsReceiptPayload } from "../api/receiving-api";

interface GoodsReceiptFormProps {
  purchaseOrderId: string;
  onComplete: () => void;
  onCancel: () => void;
}

export function GoodsReceiptForm({ purchaseOrderId, onComplete, onCancel }: GoodsReceiptFormProps) {
  const { data: poData } = usePurchaseOrder(purchaseOrderId);
  const recordMutation = useRecordGoodsReceipt(purchaseOrderId);
  const purchaseOrder = poData?.data;

  const today = new Date().toISOString().split("T")[0];
  const [receiptDate, setReceiptDate] = useState(today);
  const [receiptReference, setReceiptReference] = useState("");
  const [notes, setNotes] = useState("");
  const [lineStates, setLineStates] = useState<Record<string, { received: string; accepted: string; rejectionReason: string; notes: string }>>({});
  const [error, setError] = useState<string | null>(null);

  const lines = purchaseOrder?.lines ?? [];

  function getLineState(lineId: string) {
    return (
      lineStates[lineId] ?? {
        received: "",
        accepted: "",
        rejectionReason: "",
        notes: "",
      }
    );
  }

  function updateLineState(lineId: string, field: string, value: string) {
    setLineStates((prev) => ({
      ...prev,
      [lineId]: { ...getLineState(lineId), [field]: value },
    }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);

    if (!purchaseOrder) return;

    const payload: RecordGoodsReceiptPayload = {
      lockVersion: purchaseOrder.lockVersion,
      receiptDate,
      receiptReference: receiptReference || null,
      notes: notes || null,
      lines: lines
        .filter((l) => Number(getLineState(l.id).received) > 0)
        .map((l) => {
          const state = getLineState(l.id);
          return {
            purchaseOrderLineId: l.id,
            quantityReceived: state.received,
            quantityAccepted: state.accepted || state.received,
            rejectionReason: state.rejectionReason || null,
            notes: state.notes || null,
          };
        }),
    };

    if (payload.lines.length === 0) {
      setError("At least one line must have a received quantity.");
      return;
    }

    try {
      await recordMutation.mutateAsync(payload);
      onComplete();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to record receipt.");
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-md border p-4">
      <h4 className="text-sm font-medium">Record goods receipt</h4>

      {error && (
        <Alert variant="destructive" role="alert">
          {error}
        </Alert>
      )}

      <div className="grid grid-cols-2 gap-3">
        <div className="space-y-1">
          <label className="text-xs font-medium" htmlFor="receipt-date">
            Receipt date
          </label>
          <Input
            id="receipt-date"
            type="date"
            value={receiptDate}
            onChange={(e) => setReceiptDate(e.target.value)}
            required
          />
        </div>
        <div className="space-y-1">
          <label className="text-xs font-medium" htmlFor="receipt-ref">
            Receipt reference
          </label>
          <Input
            id="receipt-ref"
            placeholder="D/O number"
            value={receiptReference}
            onChange={(e) => setReceiptReference(e.target.value)}
          />
        </div>
      </div>

      <div className="space-y-1">
        <label className="text-xs font-medium" htmlFor="receipt-notes">
          Notes
        </label>
        <Textarea
          id="receipt-notes"
          placeholder="Optional receiving notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          rows={2}
        />
      </div>

      <div className="space-y-3">
        <h5 className="text-xs font-medium text-muted-foreground">Line items</h5>
        {lines.map((line) => {
          const state = getLineState(line.id);
          const remaining = Number(line.quantity) - Number(line.cumulativeQuantityReceived ?? "0");
          const tolerancePercent = Number(line.overReceiptTolerancePercent ?? "10");
          const maxReceivable = Number(line.quantity) * (1 + tolerancePercent / 100);

          return (
            <div key={line.id} className="rounded-md border bg-muted/20 p-3">
              <div className="mb-2 flex items-center justify-between text-xs">
                <span className="font-medium">{line.description || `Line ${line.lineNumber}`}</span>
                <span className="text-muted-foreground">
                  Ordered: {line.quantity} | Received: {line.cumulativeQuantityReceived ?? "0"} | Remaining: {Math.max(0, remaining).toFixed(4)}
                </span>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <label className="text-xs text-muted-foreground" htmlFor={`qty-rec-${line.id}`}>
                    Quantity received
                  </label>
                  <Input
                    id={`qty-rec-${line.id}`}
                    type="number"
                    step="0.0001"
                    min="0"
                    max={String(maxReceivable)}
                    placeholder="0"
                    value={state.received}
                    onChange={(e) => {
                      updateLineState(line.id, "received", e.target.value);
                      if (!state.accepted || state.accepted === state.received) {
                        updateLineState(line.id, "accepted", e.target.value);
                      }
                    }}
                  />
                  {Number(state.received) > Math.max(0, remaining) && (
                    <p className="text-xs text-amber-600">
                      Over ordered quantity — tolerance: {tolerancePercent}%
                    </p>
                  )}
                </div>
                <div className="space-y-1">
                  <label className="text-xs text-muted-foreground" htmlFor={`qty-acc-${line.id}`}>
                    Quantity accepted
                  </label>
                  <Input
                    id={`qty-acc-${line.id}`}
                    type="number"
                    step="0.0001"
                    min="0"
                    max={state.received || "0"}
                    placeholder="0"
                    value={state.accepted}
                    onChange={(e) => updateLineState(line.id, "accepted", e.target.value)}
                  />
                </div>
              </div>
              {Number(state.accepted) < Number(state.received) && (
                <div className="mt-2 space-y-1">
                  <label className="text-xs text-muted-foreground" htmlFor={`rejection-${line.id}`}>
                    Rejection reason
                  </label>
                  <Textarea
                    id={`rejection-${line.id}`}
                    placeholder="Why were items rejected?"
                    value={state.rejectionReason}
                    onChange={(e) => updateLineState(line.id, "rejectionReason", e.target.value)}
                    rows={1}
                  />
                </div>
              )}
              <div className="mt-2 space-y-1">
                <label className="text-xs text-muted-foreground" htmlFor={`line-notes-${line.id}`}>
                  Line notes
                </label>
                <Input
                  id={`line-notes-${line.id}`}
                  placeholder="Optional line notes"
                  value={state.notes}
                  onChange={(e) => updateLineState(line.id, "notes", e.target.value)}
                />
              </div>
            </div>
          );
        })}
      </div>

      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={recordMutation.isPending}>
          Cancel
        </Button>
        <Button type="submit" size="sm" disabled={recordMutation.isPending}>
          {recordMutation.isPending ? "Saving..." : "Save receipt"}
        </Button>
      </div>
    </form>
  );
}
```

- [ ] **Step 3: Insert panel in PO workspace**

Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`:

Add import at top:
```tsx
import { GoodsReceiptPanel } from "@/features/receiving/components/goods-receipt-panel";
```

After the change-orders panel section (or wherever suitable in the sections array), add the panel conditionally for issued/acknowledged/change-pending POs. Replace the current sections array to include:

```tsx
const canShowReceiving = ["issued", "acknowledged", "change_pending"].includes(purchaseOrder.status);
```

And add to `sections`:
```tsx
...(canShowReceiving ? [{ id: "receiving", label: "Goods receipts" }] : []),
```

And in the body, before `<PurchaseOrderActions>`:
```tsx
{canShowReceiving ? <GoodsReceiptPanel purchaseOrderId={purchaseOrder.id} /> : null}
```

- [ ] **Step 4: Run web tests**

Run:
```bash
pnpm --dir apps/web exec vitest run features/receiving/tests/receiving-workflow.test.tsx
```

Expected: all receiving workflow tests pass.

Also run:
```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: all purchase-order workflow tests still pass.

- [ ] **Step 5: Run web typecheck**

```bash
pnpm --filter @cognify/web typecheck
```

Expected: exits 0.

- [ ] **Step 6: Commit panel**

```bash
git add apps/web/features/receiving/components apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx apps/web/features/receiving/tests/receiving-workflow.test.tsx
git commit -m "feat: add goods receipt workspace panel"
```

## Task 8: Demo Seed Data And Roadmap Loopback

**Files:**

- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`
- Modify later: `docs/01-product/feature-roadmap.md`

- [ ] **Step 1: Seed goods receipt examples**

Add to `DemoProcurementLifecycleSeeder.php`:

```php
// Goods receipt examples
$this->seedGoodsReceipts();
```

Implement `seedGoodsReceipts()` to create:

- issued PO with one completed full goods receipt
- issued PO with two partial receipts (cumulative tracking)
- issued PO with a receipt containing rejected quantity
- issued PO with requester-confirmed receipt
- issued PO with buyer-confirmed receipt
- issued PO with no receipts yet

- [ ] **Step 2: Run demo seeder test**

```bash
php artisan test --filter=DemoSeederTest
```

Expected: passes with updated counts and assertions.

- [ ] **Step 3: Commit demo seed**

```bash
git add apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php apps/api/tests/Feature/DemoSeederTest.php
git commit -m "feat: seed goods receipt demo states"
```

- [ ] **Step 4: Roadmap update after PR number exists**

After the PR is opened and merged, update `docs/01-product/feature-roadmap.md`:
- Set P1-40 status to "Fully Implemented"
- Add design spec path
- Add implementation plan path
- Add PR number

## Task 9: Final Verification

**Commands to run:**

- [ ] Run PHP tests:
```bash
php artisan test --filter=GoodsReceiptApiTest
php artisan test --filter=DemoSeederTest
```

- [ ] Run API generation and contract checks:
```bash
pnpm generate:api
pnpm check:api-contract
```

- [ ] Run web tests:
```bash
pnpm --dir apps/web exec vitest run features/receiving/tests/receiving-workflow.test.tsx
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

- [ ] Run typecheck:
```bash
pnpm --filter @cognify/web typecheck
```

- [ ] Run lint:
```bash
pnpm lint
```

- [ ] Run git diff check:
```bash
git diff --check
```

## PR Completion Checklist

- [ ] All backend tests pass
- [ ] All web tests pass
- [ ] Typecheck exits 0
- [ ] Lint exits 0
- [ ] OpenAPI contract is generated and checked
- [ ] Git diff has no whitespace errors
- [ ] Demo seeder creates realistic goods receipt states
- [ ] PO workspace shows goods receipt panel for issued/acknowledged/change-pending POs
- [ ] Record receipt form validates and submits
- [ ] Over-receipt tolerance is enforced
- [ ] Requester and buyer confirmation work
- [ ] All audit events are recorded
- [ ] Roadmap P1-40 marked Fully Implemented

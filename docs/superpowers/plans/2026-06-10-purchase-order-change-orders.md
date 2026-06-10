# Purchase Order Change Orders Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-39 so issued and acknowledged purchase orders can be revised through auditable change orders, with material changes routed through the existing Approval domain before the current PO commitment mutates.

**Architecture:** Add `PurchaseOrderChangeOrder` and `PurchaseOrderChangeOrderLine` as durable child records under `apps/api/Domains/PurchaseOrder`. Keep `PurchaseOrder` as the current operational commitment, apply approved change orders transactionally, and reuse the existing `purchase_order` Approval subject for material re-approval. Expose the workflow through OpenAPI-generated clients consumed by the existing PO workspace and MSW tests.

**Tech Stack:** Laravel 12, Eloquent, Laravel Sanctum route-stack tests, shared Approval domain, OpenAPI JSON, Orval `@cognify/api-client`, Next.js App Router, React, TanStack Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## File Map

Backend files:

- Create `apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderStatus.php` for change-order lifecycle states.
- Create `apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderType.php` for `amendment`, `partial_cancellation`, and `full_cancellation`.
- Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderChangeOrder.php` for the parent change-order aggregate.
- Create `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderChangeOrderLine.php` for line-level deltas.
- Create `apps/api/database/migrations/2026_06_10_010000_create_purchase_order_change_orders_table.php`.
- Create `apps/api/database/migrations/2026_06_10_010100_add_change_order_fields_to_purchase_orders_table.php`.
- Create `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderNumber.php` for tenant-scoped `CO-###` numbering.
- Create `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderDelta.php` for deterministic before/after/delta calculation and materiality.
- Create `apps/api/Domains/PurchaseOrder/Actions/CreateOrUpdatePurchaseOrderChangeOrder.php`.
- Create `apps/api/Domains/PurchaseOrder/Actions/SubmitPurchaseOrderChangeOrder.php`.
- Create `apps/api/Domains/PurchaseOrder/Actions/ApplyPurchaseOrderChangeOrder.php`.
- Create `apps/api/Domains/PurchaseOrder/Actions/CancelPurchaseOrderChangeOrder.php`.
- Modify `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApprovalRouted.php`.
- Modify `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApproved.php`.
- Modify `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderRejected.php`.
- Modify `apps/api/Domains/PurchaseOrder/Actions/RequestPurchaseOrderChanges.php`.
- Modify `apps/api/Domains/Approval/SubjectHandlers/PurchaseOrderApprovalSubjectHandler.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderChangeOrderController.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Requests/SavePurchaseOrderChangeOrderRequest.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Requests/SubmitPurchaseOrderChangeOrderRequest.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Requests/CancelPurchaseOrderChangeOrderRequest.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderChangeOrderResource.php`.
- Create `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderChangeOrderLineResource.php`.
- Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`.
- Modify `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`.
- Modify `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`.
- Modify `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`.
- Modify `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`.
- Modify `apps/api/routes/api.php`.
- Modify `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`.
- Test with `apps/api/tests/Feature/PurchaseOrderChangeOrderApiTest.php`.
- Update `apps/api/tests/Feature/DemoSeederTest.php` expectations.

Contract and web files:

- Modify `apps/api/storage/openapi/openapi.json`.
- Regenerate `packages/api-client/src/generated/*`.
- Modify `apps/web/features/purchase-orders/api/purchase-order-api.ts`.
- Modify `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`.
- Create `apps/web/features/purchase-orders/components/purchase-order-change-order-panel.tsx`.
- Modify `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`.
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`.
- Modify `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`.
- Modify `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`.
- Modify `docs/01-product/feature-roadmap.md` after PR number is known.

## Task 1: Backend Red Tests For Change-Order Workflow

**Files:**

- Create: `apps/api/tests/Feature/PurchaseOrderChangeOrderApiTest.php`

- [ ] **Step 1: Create the failing API test file**

Use the existing `PurchaseOrderIssueToSupplierApiTest` helper style. Start with tests that define the intended API behavior before creating any production models or routes.

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

class PurchaseOrderChangeOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_material_change_order_applies_immediately(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'expectedDeliveryDate' => '2026-07-15',
                'lines' => [[
                    'lineId' => (string) $po->lines->first()->id,
                    'action' => 'update',
                    'expectedDeliveryDate' => '2026-07-16',
                    'deliveryLocation' => 'Dock 7',
                    'notes' => 'Supplier confirmed revised dock booking.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.materialChange', false);

        $changeOrderId = $this->latestChangeOrderId($po);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.purchaseOrder.status', 'issued')
            ->assertJsonPath('data.purchaseOrder.expectedDeliveryDate', '2026-07-15')
            ->assertJsonPath('data.purchaseOrder.supplierIssue.supplierVersionNumber', 2);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $po->tenant_id,
            'action' => 'purchase_order.change_order.applied',
        ]);
    }

    public function test_material_change_order_routes_for_approval_without_mutating_commitment(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'update',
                    'quantity' => '8.0000',
                    'unitPrice' => '12500.0000',
                    'notes' => 'Reduced scope and revised supplier price.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.materialChange', true)
            ->assertJsonPath('data.requiresApproval', true)
            ->assertJsonPath('data.delta.totalAmount.after', '100000.00');

        $changeOrderId = $this->latestChangeOrderId($po);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_approval')
            ->assertJsonPath('data.purchaseOrder.status', 'change_pending');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'change_pending',
            'total_amount' => '131100.00',
        ]);
        $this->assertDatabaseHas('approval_instances', [
            'tenant_id' => $po->tenant_id,
            'subject_type' => PurchaseOrder::class,
            'subject_id' => $po->id,
        ]);
    }

    public function test_approval_applies_material_change_order(): void
    {
        $po = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $approver = $this->tenantUser($po->tenant, TenantRole::Admin->value);
        $line = $po->lines->first();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, [
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'update',
                    'quantity' => '8.0000',
                    'unitPrice' => '12500.0000',
                ]],
            ]))
            ->assertCreated();

        $changeOrderId = $this->latestChangeOrderId($po);
        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-order-change-orders/{$changeOrderId}/submit", ['lockVersion' => 1])
            ->assertOk();

        $taskId = $this->approvalTaskId($po);
        $this->actingAsTenant($po->tenant, $approver)
            ->postJson("/api/approval-tasks/{$taskId}/approve", ['lockVersion' => 1])
            ->assertOk();

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'issued',
            'total_amount' => '100000.00',
            'supplier_version_number' => 2,
        ]);
        $this->assertDatabaseHas('purchase_order_lines', [
            'id' => $line->id,
            'quantity' => '8.0000',
            'unit_price' => '12500.0000',
            'total_amount' => '100000.00',
        ]);
    }

    public function test_full_and_partial_cancellations_are_change_orders(): void
    {
        $partial = $this->issuedPurchaseOrder();
        $buyer = $this->tenantUser($partial->tenant, TenantRole::Buyer->value);
        $line = $partial->lines->first();

        $this->actingAsTenant($partial->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$partial->id}/change-orders", $this->changePayload($partial, [
                'changeType' => 'partial_cancellation',
                'lines' => [[
                    'lineId' => (string) $line->id,
                    'action' => 'cancel',
                    'notes' => 'Supplier cannot fulfill this line.',
                ]],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.changeType', 'partial_cancellation')
            ->assertJsonPath('data.materialChange', true);

        $full = $this->issuedPurchaseOrder();
        $fullBuyer = $this->tenantUser($full->tenant, TenantRole::Buyer->value);

        $this->actingAsTenant($full->tenant, $fullBuyer)
            ->postJson("/api/purchase-orders/{$full->id}/change-orders", $this->changePayload($full, [
                'changeType' => 'full_cancellation',
                'lines' => [],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.changeType', 'full_cancellation')
            ->assertJsonPath('data.materialChange', true);
    }

    public function test_change_orders_enforce_state_lock_tenant_role_and_single_active_order(): void
    {
        $po = $this->issuedPurchaseOrder(lockVersion: 4);
        $buyer = $this->tenantUser($po->tenant, TenantRole::Buyer->value);
        $requester = $this->tenantUser($po->tenant, TenantRole::Requester->value);

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po, ['lockVersion' => 3]))
            ->assertConflict();

        $this->actingAsTenant($po->tenant, $requester)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertForbidden();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertCreated();

        $this->actingAsTenant($po->tenant, $buyer)
            ->postJson("/api/purchase-orders/{$po->id}/change-orders", $this->changePayload($po))
            ->assertConflict();

        $draft = $this->purchaseOrder(status: 'draft');
        $draftBuyer = $this->tenantUser($draft->tenant, TenantRole::Buyer->value);
        $this->actingAsTenant($draft->tenant, $draftBuyer)
            ->postJson("/api/purchase-orders/{$draft->id}/change-orders", $this->changePayload($draft))
            ->assertConflict();
    }

    private function changePayload(PurchaseOrder $po, array $overrides = []): array
    {
        return array_replace_recursive([
            'lockVersion' => $po->lock_version,
            'reason' => 'Supplier confirmed updated delivery commitment.',
            'changeType' => 'amendment',
            'expectedDeliveryDate' => '2026-07-15',
            'buyerNote' => 'Updated through controlled change order.',
            'financeNote' => null,
            'lines' => [],
        ], $overrides);
    }

    private function latestChangeOrderId(PurchaseOrder $po): string
    {
        return (string) $po->changeOrders()->latest()->valueOrFail('id');
    }

    private function approvalTaskId(PurchaseOrder $po): string
    {
        return (string) \Domains\Approval\Models\ApprovalTask::query()
            ->where('subject_type', PurchaseOrder::class)
            ->where('subject_id', $po->id)
            ->latest()
            ->valueOrFail('id');
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
php artisan test --filter=PurchaseOrderChangeOrderApiTest
```

Expected: FAIL because routes, models, statuses, and relationships do not exist.

- [ ] **Step 3: Commit red test**

```bash
git add apps/api/tests/Feature/PurchaseOrderChangeOrderApiTest.php
git commit -m "test: define purchase order change order workflow"
```

## Task 2: Database, Models, States, And Numbering

**Files:**

- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderStatus.php`
- Create: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderType.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderChangeOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderChangeOrderLine.php`
- Create: `apps/api/database/migrations/2026_06_10_010000_create_purchase_order_change_orders_table.php`
- Create: `apps/api/database/migrations/2026_06_10_010100_add_change_order_fields_to_purchase_orders_table.php`
- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderNumber.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`
- Modify: `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`

- [ ] **Step 1: Add state enums**

`apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderStatus.php`:

```php
<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderChangeOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval, self::ChangesRequested], true);
    }
}
```

`apps/api/Domains/PurchaseOrder/States/PurchaseOrderChangeOrderType.php`:

```php
<?php

namespace Domains\PurchaseOrder\States;

enum PurchaseOrderChangeOrderType: string
{
    case Amendment = 'amendment';
    case PartialCancellation = 'partial_cancellation';
    case FullCancellation = 'full_cancellation';
}
```

Modify `apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php`:

```php
case ChangePending = 'change_pending';
```

Keep `isTerminal()` limited to `Rejected` and `Cancelled`; `Approved`, `Issued`, and `Acknowledged` are operational states after P1-39.

- [ ] **Step 2: Add migrations**

`apps/api/database/migrations/2026_06_10_010000_create_purchase_order_change_orders_table.php`:

```php
<?php

use App\Models\User;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_change_orders', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignIdFor(ApprovalInstance::class, 'approval_instance_id')->nullable()->constrained('approval_instances')->nullOnDelete();
            $table->string('number');
            $table->string('status');
            $table->string('change_type');
            $table->string('from_purchase_order_status');
            $table->string('to_purchase_order_status')->nullable();
            $table->text('reason');
            $table->boolean('material_change')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->foreignIdFor(User::class, 'requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignIdFor(User::class, 'rejected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->foreignIdFor(User::class, 'changes_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changes_requested_at')->nullable();
            $table->text('changes_requested_reason')->nullable();
            $table->foreignIdFor(User::class, 'cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->json('delta_snapshot');
            $table->unsignedInteger('supplier_version_number')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'purchase_order_id', 'status'], 'po_change_orders_tenant_po_status_idx');
            $table->index(['tenant_id', 'status', 'updated_at'], 'po_change_orders_tenant_status_updated_idx');
        });

        Schema::create('purchase_order_change_order_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_change_order_id')->constrained('purchase_order_change_orders')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->constrained('purchase_order_lines')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('change_action');
            $table->decimal('quantity_before', 18, 4)->nullable();
            $table->decimal('quantity_after', 18, 4)->nullable();
            $table->decimal('unit_price_before', 18, 4)->nullable();
            $table->decimal('unit_price_after', 18, 4)->nullable();
            $table->decimal('subtotal_amount_before', 18, 2)->nullable();
            $table->decimal('subtotal_amount_after', 18, 2)->nullable();
            $table->decimal('tax_amount_before', 18, 2)->nullable();
            $table->decimal('tax_amount_after', 18, 2)->nullable();
            $table->decimal('freight_amount_before', 18, 2)->nullable();
            $table->decimal('freight_amount_after', 18, 2)->nullable();
            $table->decimal('discount_amount_before', 18, 2)->nullable();
            $table->decimal('discount_amount_after', 18, 2)->nullable();
            $table->decimal('total_amount_before', 18, 2)->nullable();
            $table->decimal('total_amount_after', 18, 2)->nullable();
            $table->date('expected_delivery_date_before')->nullable();
            $table->date('expected_delivery_date_after')->nullable();
            $table->string('delivery_location_before')->nullable();
            $table->string('delivery_location_after')->nullable();
            $table->text('notes_before')->nullable();
            $table->text('notes_after')->nullable();
            $table->json('delta_snapshot');
            $table->timestamps();

            $table->unique(['purchase_order_change_order_id', 'purchase_order_line_id'], 'po_change_order_line_unique');
            $table->index(['tenant_id', 'purchase_order_line_id'], 'po_change_order_lines_tenant_line_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_change_order_lines');
        Schema::dropIfExists('purchase_order_change_orders');
    }
};
```

`apps/api/database/migrations/2026_06_10_010100_add_change_order_fields_to_purchase_orders_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->foreignUuid('current_change_order_id')->nullable()->after('acknowledgement_note')->constrained('purchase_order_change_orders')->nullOnDelete();
            $table->unsignedInteger('current_supplier_version_number')->default(1)->after('current_change_order_id');
            $table->unsignedInteger('change_order_count')->default(0)->after('current_supplier_version_number');
            $table->index(['tenant_id', 'current_change_order_id'], 'purchase_orders_tenant_current_change_order_idx');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->string('status')->default('open')->after('source_snapshot');
            $table->unsignedInteger('current_version_number')->default(1)->after('status');
            $table->foreignUuid('cancelled_by_change_order_id')->nullable()->after('current_version_number')->constrained('purchase_order_change_orders')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by_change_order_id');
            $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            $table->index(['tenant_id', 'purchase_order_id', 'status'], 'purchase_order_lines_tenant_po_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->dropIndex('purchase_order_lines_tenant_po_status_idx');
            $table->dropConstrainedForeignId('cancelled_by_change_order_id');
            $table->dropColumn(['status', 'current_version_number', 'cancelled_at', 'cancelled_reason']);
        });

        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purchase_orders_tenant_current_change_order_idx');
            $table->dropConstrainedForeignId('current_change_order_id');
            $table->dropColumn(['current_supplier_version_number', 'change_order_count']);
        });
    }
};
```

- [ ] **Step 3: Add models and relationships**

`PurchaseOrderChangeOrder` should use `HasUuids`, cast status/type enums, cast JSON snapshots, and validate tenant ownership for `purchase_order_id`, `approval_instance_id`, and actor user IDs in `booted()`.

`PurchaseOrderChangeOrderLine` should use `HasUuids`, decimal/date casts, and validate tenant ownership for `purchase_order_change_order_id` and `purchase_order_line_id`.

Modify `PurchaseOrder`:

- add fillable fields `current_change_order_id`, `current_supplier_version_number`, `change_order_count`
- cast `current_supplier_version_number` and `change_order_count` to integer
- add `currentChangeOrder()` belongsTo relation
- add `changeOrders()` hasMany relation ordered latest
- add tenant validation for `current_change_order_id`

Modify `PurchaseOrderLine`:

- add fillable fields `status`, `current_version_number`, `cancelled_by_change_order_id`, `cancelled_at`, `cancelled_reason`
- cast `current_version_number` integer and `cancelled_at` datetime
- add `cancelledByChangeOrder()` belongsTo relation

- [ ] **Step 4: Add change-order number generator**

Create `PurchaseOrderChangeOrderNumber::nextFor(PurchaseOrder $purchaseOrder): string` that locks tenant PO change orders for the PO and returns `{$purchaseOrder->number}-CO-###`.

- [ ] **Step 5: Run migration/model test**

Run:

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
```

Expected: failures move from missing classes/tables to missing routes/actions.

- [ ] **Step 6: Commit schema foundation**

```bash
git add apps/api/Domains/PurchaseOrder/States apps/api/Domains/PurchaseOrder/Models apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderNumber.php apps/api/database/migrations apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php apps/api/Domains/PurchaseOrder/States/PurchaseOrderStatus.php
git commit -m "feat: add purchase order change order schema"
```

## Task 3: Delta Calculation And Backend Actions

**Files:**

- Create: `apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderDelta.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CreateOrUpdatePurchaseOrderChangeOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/SubmitPurchaseOrderChangeOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/ApplyPurchaseOrderChangeOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/CancelPurchaseOrderChangeOrder.php`

- [ ] **Step 1: Implement delta calculator**

`PurchaseOrderChangeOrderDelta` owns all server-side materiality and totals. It accepts a locked PO with lines plus validated payload and returns:

```php
[
    'before' => [...],
    'after' => [...],
    'delta' => [
        'changedFields' => [...],
        'materialFields' => [...],
        'subtotalAmount' => ['before' => '120000.00', 'after' => '100000.00'],
        'totalAmount' => ['before' => '131100.00', 'after' => '100000.00'],
        'lines' => [...],
    ],
    'materialChange' => true,
    'lineChanges' => [...],
]
```

Calculation rules:

- For unchanged fields, copy current PO/line value into `after`.
- Recalculate line subtotal as `quantity_after * unit_price_after`.
- Default tax, freight, and discount to existing line values unless provided.
- Recalculate line total as `subtotal + tax + freight - discount`.
- Sum line values into PO subtotal/tax/freight/discount/total.
- Mark material when payment terms, delivery terms, quantity, unit price, line action `cancel`, total amount, or full cancellation changes.
- Treat delivery date, delivery location, buyer note, finance note, and line notes as non-material.

- [ ] **Step 2: Implement create/update action**

`CreateOrUpdatePurchaseOrderChangeOrder::handle(PurchaseOrder $purchaseOrder, User $actor, array $payload): PurchaseOrderChangeOrder`:

- lock PO with lines and active change order
- require `issued` or `acknowledged`, or `change_pending` only when active change order is `changes_requested`
- assert PO `lockVersion`
- block creating a second active change order
- calculate delta
- create or update parent and line rows
- record `purchase_order.change_order.drafted` or `purchase_order.change_order.updated`

- [ ] **Step 3: Implement submit action**

`SubmitPurchaseOrderChangeOrder::handle(PurchaseOrderChangeOrder $changeOrder, User $actor, int $lockVersion): PurchaseOrderChangeOrder`:

- lock change order and PO
- require `draft` or `changes_requested`
- assert change-order lock version
- require effective changes
- if non-material, call `ApplyPurchaseOrderChangeOrder`
- if material, set `pending_approval`, set PO `change_pending`, increment both lock versions, call `RouteSubjectForApproval`, record audit

- [ ] **Step 4: Implement apply action**

`ApplyPurchaseOrderChangeOrder::handle(PurchaseOrderChangeOrder $changeOrder, User $actor, ?ApprovalInstance $instance = null): PurchaseOrderChangeOrder`:

- lock change order, PO, and lines
- update PO header, totals, `current_change_order_id`, `change_order_count`, `current_supplier_version_number`, `supplier_version_number`, `supplier_version`, and `lock_version`
- update line quantities/prices/statuses and line `current_version_number`
- set PO status to `cancelled` for full cancellation, otherwise restore `from_purchase_order_status`
- set change order `approved`, `approved_by_user_id`, `approved_at`, `supplier_version_number`
- record `purchase_order.change_order.applied`
- record `purchase_order.supplier_version.superseded`

- [ ] **Step 5: Implement cancel action**

`CancelPurchaseOrderChangeOrder::handle(PurchaseOrderChangeOrder $changeOrder, User $actor, int $lockVersion, string $reason): PurchaseOrderChangeOrder`:

- lock change order and PO
- require `draft` or `changes_requested`
- assert lock version
- set change order `cancelled`
- restore PO from `change_pending` to `from_purchase_order_status` if needed
- record `purchase_order.change_order.cancelled`

- [ ] **Step 6: Run backend red/green check**

Run:

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
```

Expected: failures should now be route/resource/request related, not action internals.

- [ ] **Step 7: Commit domain actions**

```bash
git add apps/api/Domains/PurchaseOrder/Support/PurchaseOrderChangeOrderDelta.php apps/api/Domains/PurchaseOrder/Actions
git commit -m "feat: add purchase order change order actions"
```

## Task 4: API Requests, Resources, Controller, Routes, And Policy

**Files:**

- Create: `apps/api/Domains/PurchaseOrder/Http/Controllers/PurchaseOrderChangeOrderController.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/SavePurchaseOrderChangeOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/SubmitPurchaseOrderChangeOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Requests/CancelPurchaseOrderChangeOrderRequest.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderChangeOrderResource.php`
- Create: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderChangeOrderLineResource.php`
- Modify: `apps/api/Domains/PurchaseOrder/Http/Resources/PurchaseOrderResource.php`
- Modify: `apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add request validation**

`SavePurchaseOrderChangeOrderRequest`:

- authorize via route PO or change order and `saveChangeOrder`
- `lockVersion`: required integer min 1
- `reason`: required string min 5 max 2000
- `changeType`: required enum `amendment`, `partial_cancellation`, `full_cancellation`
- header fields: nullable date/string with max lengths matching PO fields
- `lines`: array max 100
- `lines.*.lineId`: required uuid
- `lines.*.action`: required enum `update`, `cancel`
- quantities/prices: decimal/min constraints
- line text fields: nullable strings with max lengths

`SubmitPurchaseOrderChangeOrderRequest` and `CancelPurchaseOrderChangeOrderRequest` validate lock versions, and cancel requires reason min 5.

- [ ] **Step 2: Add resources**

`PurchaseOrderChangeOrderResource` returns:

```php
[
    'id' => (string) $changeOrder->id,
    'purchaseOrderId' => (string) $changeOrder->purchase_order_id,
    'number' => $changeOrder->number,
    'status' => $changeOrder->statusState()->value,
    'changeType' => $changeOrder->typeState()->value,
    'reason' => $changeOrder->reason,
    'materialChange' => $changeOrder->material_change,
    'requiresApproval' => $changeOrder->requires_approval,
    'fromPurchaseOrderStatus' => $changeOrder->from_purchase_order_status,
    'toPurchaseOrderStatus' => $changeOrder->to_purchase_order_status,
    'delta' => $changeOrder->delta_snapshot,
    'before' => $changeOrder->before_snapshot,
    'after' => $changeOrder->after_snapshot,
    'supplierVersionNumber' => $changeOrder->supplier_version_number,
    'approvalInstanceId' => $changeOrder->approval_instance_id ? (string) $changeOrder->approval_instance_id : null,
    'timestamps' => [...],
    'lines' => PurchaseOrderChangeOrderLineResource::collection($changeOrder->lines)->resolve(),
    'lockVersion' => $changeOrder->lock_version,
    'permissions' => [...],
]
```

Include nested `purchaseOrder` only on submit responses where the UI needs a refreshed PO.

- [ ] **Step 3: Add controller and routes**

Routes inside the existing tenant-required purchase-order group:

```php
Route::get('/purchase-orders/{purchaseOrder}/change-orders', [PurchaseOrderChangeOrderController::class, 'index']);
Route::post('/purchase-orders/{purchaseOrder}/change-orders', [PurchaseOrderChangeOrderController::class, 'store']);
Route::get('/purchase-order-change-orders/{changeOrder}', [PurchaseOrderChangeOrderController::class, 'show']);
Route::patch('/purchase-order-change-orders/{changeOrder}', [PurchaseOrderChangeOrderController::class, 'update']);
Route::post('/purchase-order-change-orders/{changeOrder}/submit', [PurchaseOrderChangeOrderController::class, 'submit']);
Route::post('/purchase-order-change-orders/{changeOrder}/cancel', [PurchaseOrderChangeOrderController::class, 'cancel']);
```

The controller must resolve change orders by tenant and eager-load `purchaseOrder.lines` and `lines`.

- [ ] **Step 4: Update policy and PO resource permissions**

Add policy methods:

- `viewChangeOrder`
- `saveChangeOrder`
- `submitChangeOrder`
- `cancelChangeOrder`

Buyer/admin only. Resource permissions:

- `canCreateChangeOrder`: PO status `issued` or `acknowledged`, no active change order
- `canUpdateChangeOrder`: active change order `draft` or `changes_requested`
- `canSubmitChangeOrder`: active change order `draft` or `changes_requested`
- `canCancelChangeOrder`: active change order `draft` or `changes_requested`

Add `changeOrdersSummary` to `PurchaseOrderResource` with active change-order summary and latest applied change order.

- [ ] **Step 5: Run API test**

Run:

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
```

Expected: the non-approval tests should pass or fail only on approval integration.

- [ ] **Step 6: Commit API surface**

```bash
git add apps/api/Domains/PurchaseOrder/Http apps/api/Domains/PurchaseOrder/Policies/PurchaseOrderPolicy.php apps/api/routes/api.php
git commit -m "feat: expose purchase order change order API"
```

## Task 5: Approval Integration For Material Change Orders

**Files:**

- Modify: `apps/api/Domains/Approval/SubjectHandlers/PurchaseOrderApprovalSubjectHandler.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApprovalRouted.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderApproved.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/MarkPurchaseOrderRejected.php`
- Modify: `apps/api/Domains/PurchaseOrder/Actions/RequestPurchaseOrderChanges.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/RejectPurchaseOrderChangeOrder.php`
- Create: `apps/api/Domains/PurchaseOrder/Actions/RequestPurchaseOrderChangeOrderChanges.php`

- [ ] **Step 1: Detect active change order in approval subject handler**

Load `currentChangeOrder.lines` when building context and task summaries. If PO status is `change_pending`, add metadata:

```php
'changeOrderId' => (string) $changeOrder->id,
'changeOrderNumber' => $changeOrder->number,
'changeType' => $changeOrder->typeState()->value,
'materialChange' => $changeOrder->material_change,
'totalDelta' => data_get($changeOrder->delta_snapshot, 'totalAmount'),
'reason' => $changeOrder->reason,
```

Task title should become `Review change order {number} for purchase order {po}` when change pending.

- [ ] **Step 2: Route outcomes to change-order actions**

In `MarkPurchaseOrderApprovalRouted`, when status is `change_pending`, mark the active change order with `approval_instance_id` and record `purchase_order.change_order.approval_routed` without changing to `in_review`.

In `MarkPurchaseOrderApproved`, if the locked PO status is `change_pending` and has an active current change order, call `ApplyPurchaseOrderChangeOrder`.

In `MarkPurchaseOrderRejected`, if change pending, call `RejectPurchaseOrderChangeOrder`.

In `RequestPurchaseOrderChanges`, if change pending, call `RequestPurchaseOrderChangeOrderChanges`.

- [ ] **Step 3: Implement rejected and changes-requested actions**

`RejectPurchaseOrderChangeOrder`:

- set change order `rejected`
- restore PO status to `from_purchase_order_status`
- record `purchase_order.change_order.rejected`

`RequestPurchaseOrderChangeOrderChanges`:

- set change order `changes_requested`
- keep PO `change_pending`
- record `purchase_order.change_order.changes_requested`

- [ ] **Step 4: Run approval path test**

Run:

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
```

Expected: all backend change-order tests pass.

- [ ] **Step 5: Commit approval integration**

```bash
git add apps/api/Domains/Approval/SubjectHandlers/PurchaseOrderApprovalSubjectHandler.php apps/api/Domains/PurchaseOrder/Actions
git commit -m "feat: route material purchase order change orders for approval"
```

## Task 6: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Regenerate: `packages/api-client/src/generated/*`

- [ ] **Step 1: Update OpenAPI manually**

Add paths for all change-order endpoints. Add schemas:

- `PurchaseOrderChangeOrder`
- `PurchaseOrderChangeOrderLine`
- `SavePurchaseOrderChangeOrderRequest`
- `SubmitPurchaseOrderChangeOrderRequest`
- `CancelPurchaseOrderChangeOrderRequest`
- `PurchaseOrderChangeOrdersSummary`

Update:

- `PurchaseOrderStatus` enum with `change_pending`
- `PurchaseOrder.permissions` with change-order permission booleans
- `PurchaseOrder` with `changeOrdersSummary`
- `PurchaseOrderLine` with line `status`, `currentVersionNumber`, and cancellation fields

- [ ] **Step 2: Regenerate client**

Run:

```bash
pnpm generate:api
```

Expected: Orval succeeds and generated endpoint/schema files include change-order operations.

- [ ] **Step 3: Check API contract**

Run:

```bash
pnpm check:api-contract
```

Expected: exits 0 with no generated diff.

- [ ] **Step 4: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: add purchase order change order contract"
```

## Task 7: Web API Hooks, MSW, And Fixtures

**Files:**

- Modify: `apps/web/features/purchase-orders/api/purchase-order-api.ts`
- Modify: `apps/web/features/purchase-orders/hooks/use-purchase-order-actions.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-fixtures.ts`
- Modify: `apps/web/features/purchase-orders/mocks/purchase-order-handlers.ts`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`

- [ ] **Step 1: Add failing web tests**

Add tests to `purchase-order-workflow.test.tsx`:

- issued PO shows change-order panel and create action
- non-material change order applies and updates expected delivery date
- material change order moves PO to pending approval state
- history shows approved and pending change orders

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: FAIL because hooks/components do not exist.

- [ ] **Step 2: Add API wrappers and hooks**

Use generated endpoints from `@cognify/api-client`. Add wrappers:

- `listPurchaseOrderChangeOrders`
- `savePurchaseOrderChangeOrder`
- `submitPurchaseOrderChangeOrder`
- `cancelPurchaseOrderChangeOrder`

Add TanStack mutation hooks that invalidate purchase-order list/detail queries and change-order list queries.

- [ ] **Step 3: Add fixtures**

Extend `PurchaseOrder` fixtures with `changeOrdersSummary` and line status fields. Add:

- `issuedPurchaseOrderWithAppliedChangeOrderFixture`
- `issuedPurchaseOrderWithPendingChangeOrderFixture`
- `purchaseOrderChangeOrderFixture`
- `pendingPurchaseOrderChangeOrderFixture`

- [ ] **Step 4: Add MSW handlers**

Handlers must enforce:

- tenant header required
- lock version conflicts
- only issued/acknowledged POs can create change orders
- one active change order at a time
- non-material submit applies immediately
- material submit sets PO `change_pending`

- [ ] **Step 5: Run web red/green check**

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: tests still fail until component task is complete, but API/hook import errors should be resolved.

- [ ] **Step 6: Commit web plumbing**

```bash
git add apps/web/features/purchase-orders/api apps/web/features/purchase-orders/hooks apps/web/features/purchase-orders/mocks apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx
git commit -m "feat: add purchase order change order web data plumbing"
```

## Task 8: Web Change-Order Panel

**Files:**

- Create: `apps/web/features/purchase-orders/components/purchase-order-change-order-panel.tsx`
- Modify: `apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx`
- Modify: `apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx`

- [ ] **Step 1: Build panel**

The panel should:

- render only when change-order summary exists or PO is issued/acknowledged/change_pending
- show active change order number/status/type/reason/materiality
- show total delta and changed line count
- provide draft form for reason, change type, header delivery fields, and first line quantity/unit price/date/location/notes/cancel action
- show history table for latest change orders
- surface validation/conflict errors through `role="alert"`

Use existing `Button`, `Input`, and `Textarea` from `@cognify/ui`. Keep the panel dense and operational, matching the current PO workspace.

- [ ] **Step 2: Insert panel in workspace**

Place the panel after supplier issue and before line details so buyers see current supplier state before editing commitment deltas.

- [ ] **Step 3: Run web tests**

Run:

```bash
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
```

Expected: all purchase-order workflow tests pass.

- [ ] **Step 4: Run web typecheck**

Run:

```bash
pnpm --filter @cognify/web typecheck
```

Expected: exits 0.

- [ ] **Step 5: Commit panel**

```bash
git add apps/web/features/purchase-orders/components/purchase-order-change-order-panel.tsx apps/web/features/purchase-orders/workflows/purchase-order-workspace-page.tsx apps/web/features/purchase-orders/tests/purchase-order-workflow.test.tsx
git commit -m "feat: add purchase order change order workspace panel"
```

## Task 9: Demo Seed Data And Roadmap Loopback

**Files:**

- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`
- Modify: `apps/api/tests/Feature/DemoSeederTest.php`
- Modify later: `docs/01-product/feature-roadmap.md`

- [ ] **Step 1: Seed change-order examples**

Add demo POs for:

- issued without change orders
- acknowledged with approved material change order
- issued with pending approval change order
- issued with non-material applied delivery-date change

Keep approval-task counts updated in `DemoSeederTest`.

- [ ] **Step 2: Run demo seeder test**

Run:

```bash
php artisan test --filter=DemoSeederTest
```

Expected: passes with updated counts and assertions.

- [ ] **Step 3: Commit demo seed**

```bash
git add apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php apps/api/tests/Feature/DemoSeederTest.php
git commit -m "feat: seed purchase order change order demo states"
```

- [ ] **Step 4: Roadmap update after PR number exists**

After opening the PR, update P1-39 in `docs/01-product/feature-roadmap.md` to `Fully Implemented`, with this spec path, this plan path, PR number, and implementation notes.

## Task 10: Full Verification, Desktop Visual Gate, CodeRabbit, PR, And Merge

**Files:**

- No production files unless verification finds issues.

- [ ] **Step 1: Run focused backend checks**

```bash
php artisan test --filter=PurchaseOrderChangeOrderApiTest
php artisan test --filter=PurchaseOrderIssueToSupplierApiTest
php artisan test --filter=DemoSeederTest
```

Expected: all pass.

- [ ] **Step 2: Run contract and web checks**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --dir apps/web exec vitest run features/purchase-orders/tests/purchase-order-workflow.test.tsx
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
git diff --check
```

Expected: all exit 0. Existing lint warnings may remain warnings only.

- [ ] **Step 3: Desktop visual verification**

Start the real API-backed app:

```bash
pnpm dev:reset
```

Use Playwright against the desktop viewport only. Mobile screenshots are skipped because mobile support was dropped from Cognify. Capture representative screenshots:

- issued PO with create change-order panel
- active pending approval change order
- applied change-order history

Critique density, labels, disabled states, deltas, approval state visibility, and overlap. Fix relevant issues and rerun focused checks.

- [ ] **Step 4: CodeRabbit review**

Run one CodeRabbit review cycle before PR if available:

```bash
coderabbit review --agent -t uncommitted
```

Apply valid findings, skip stale or incorrect findings with a reason, and rerun relevant verification.

- [ ] **Step 5: Push and open PR**

```bash
git push -u origin goal-feature/p1-39-po-change-orders
```

Open a ready-for-review PR titled:

```txt
Implement P1-39 purchase order change orders
```

PR body must include:

- spec path
- plan path
- verification commands and outcomes
- desktop visual evidence
- CodeRabbit summary

- [ ] **Step 6: PR review loop**

Wait for CI and CodeRabbit. Resolve valid review threads. Commit/push fixes. Wait until:

- CodeRabbit success
- `verify` success
- `preview` success
- Socket checks success
- no unresolved review comments
- merge state clean

- [ ] **Step 7: Merge and reset main**

Merge PR using the repo's merge strategy, then:

```bash
git checkout main
git pull --ff-only
git status --short --branch
```

Confirm P1-39 is merged and roadmap row is updated on `main`.

## Plan Self-Review

- Spec coverage: tasks cover schema, domain actions, approval routing, OpenAPI, generated client, web workspace, MSW, demo data, roadmap, visual verification, CodeRabbit, PR, and merge loop.
- Vague-language scan: no deferred or intentionally vague implementation steps remain.
- Type consistency: statuses use `change_pending`, `pending_approval`, `changes_requested`, `approved`, `rejected`, and `cancelled`; request/resource names are consistent across backend, OpenAPI, and web tasks.

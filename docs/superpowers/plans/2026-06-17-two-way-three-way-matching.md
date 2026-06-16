# Two-Way and Three-Way Matching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement two-way and three-way matching for supplier invoices against purchase orders and goods receipts, with configurable tolerances, cumulative over-billing protection, event-driven re-trigger on receipt, and matching UI in the AP queue.

**Architecture:** New `Domains/Invoice` domain objects (`SupplierInvoiceMatchResult` model, `RunInvoiceMatching` action, `InvoiceMatchingService`, `ToleranceService`). Extends existing `SupplierInvoice` with `matching_status`. Adds `matching_policy` and `cumulative_quantity_invoiced` to purchase order and line models. Introduces a `GoodsReceiptLinePosted` domain event for automatic re-triggering. Frontend extends the accounts-payable feature with matching results panel, status badge, and queue filters.

**Tech Stack:** Laravel 12 (PHP 8.3), Next.js 15 (React 19, TypeScript), PostgreSQL, Orval-generated API client, TanStack Query, MSW

---

### Task 1: Database Migrations

**Files:**
- Create: `apps/api/database/migrations/2026_06_17_000001_add_matching_fields_to_supplier_invoices.php`
- Create: `apps/api/database/migrations/2026_06_17_000002_add_matching_policy_to_purchase_orders.php`
- Create: `apps/api/database/migrations/2026_06_17_000003_add_cumulative_invoiced_to_purchase_order_lines.php`
- Create: `apps/api/database/migrations/2026_06_17_000004_create_supplier_invoice_match_results_table.php`

- [ ] **Step 1: Create migration for supplier_invoices matching_status**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('matching_status')->nullable()->after('review_blockers');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('matching_status');
        });
    }
};
```

- [ ] **Step 2: Create migration for purchase_orders matching_policy**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('matching_policy')->default('three_way')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('matching_policy');
        });
    }
};
```

- [ ] **Step 3: Create migration for purchase_order_lines cumulative_quantity_invoiced**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('cumulative_quantity_invoiced', 18, 4)->default(0)->after('cumulative_quantity_accepted');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn('cumulative_quantity_invoiced');
        });
    }
};
```

- [ ] **Step 4: Create migration for supplier_invoice_match_results table**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_match_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('supplier_invoice_id')->constrained('supplier_invoices');
            $table->foreignUuid('supplier_invoice_line_id')->nullable()->constrained('supplier_invoice_lines');
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained('purchase_order_lines');
            $table->string('match_type'); // two_way, three_way
            $table->string('match_level'); // header, line
            $table->string('dimension'); // vendor_identity, quantity, unit_price, line_total, tax, freight, invoice_total
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('actual_value', 18, 4)->nullable();
            $table->decimal('tolerance_percent_applied', 6, 4)->nullable();
            $table->decimal('tolerance_floor_applied', 18, 4)->nullable();
            $table->decimal('tolerance_cap_applied', 18, 4)->nullable();
            $table->string('result'); // pass, fail, not_applicable
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_invoice_id', 'dimension']);
            $table->index(['supplier_invoice_id', 'result']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoice_match_results');
    }
};
```

- [ ] **Step 5: Run migrations**

```bash
cd apps/api && php artisan migrate
```

- [ ] **Step 6: Commit**

```bash
git add apps/api/database/migrations/
git commit -m "feat(db): add matching tables and fields for P1-44 two-way/three-way matching"
```

---

### Task 2: Models — SupplierInvoiceMatchResult and extend existing models

**Files:**
- Create: `apps/api/Domains/Invoice/Models/SupplierInvoiceMatchResult.php`
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoice.php`
- Modify: `apps/api/Domains/Invoice/Models/SupplierInvoiceLine.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php`
- Modify: `apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php`

- [ ] **Step 1: Create SupplierInvoiceMatchResult model**

```php
<?php

namespace Domains\Invoice\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceMatchResult extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'supplier_invoice_id',
        'supplier_invoice_line_id',
        'purchase_order_line_id',
        'match_type',
        'match_level',
        'dimension',
        'expected_value',
        'actual_value',
        'tolerance_percent_applied',
        'tolerance_floor_applied',
        'tolerance_cap_applied',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'tolerance_percent_applied' => 'decimal:4',
            'tolerance_floor_applied' => 'decimal:4',
            'tolerance_cap_applied' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceLine::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(\Domains\PurchaseOrder\Models\PurchaseOrderLine::class);
    }
}
```

- [ ] **Step 2: Add matching_status fillable and relationship to SupplierInvoice**

```php
// In $fillable, add:
'matching_status',

// In casts, add:
'matching_status' => 'string',

// Add relationship:
public function matchResults(): HasMany
{
    return $this->hasMany(SupplierInvoiceMatchResult::class)->orderBy('created_at');
}
```

Add import: `use Illuminate\Database\Eloquent\Relations\HasMany;`

- [ ] **Step 3: Add matching_policy to PurchaseOrder**

```php
// In $fillable, add:
'matching_policy',

// In casts, add:
'matching_policy' => 'string',
```

- [ ] **Step 4: Add cumulative_quantity_invoiced to PurchaseOrderLine**

```php
// In $fillable, add:
'cumulative_quantity_invoiced',

// In casts, add:
'cumulative_quantity_invoiced' => 'decimal:4',
```

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/Invoice/Models/SupplierInvoiceMatchResult.php \
       apps/api/Domains/Invoice/Models/SupplierInvoice.php \
       apps/api/Domains/Invoice/Models/SupplierInvoiceLine.php \
       apps/api/Domains/PurchaseOrder/Models/PurchaseOrder.php \
       apps/api/Domains/PurchaseOrder/Models/PurchaseOrderLine.php
git commit -m "feat(models): add matching models and field extensions for P1-44"
```

---

### Task 3: Matching Status — Add Matched/Mismatch to SupplierInvoiceStatus

**Files:**
- Modify: `apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php`

- [ ] **Step 1: Add Matched and Mismatch statuses**

```php
<?php

namespace Domains\Invoice\States;

enum SupplierInvoiceStatus: string
{
    case Captured = 'captured';
    case InReview = 'in_review';
    case NeedsInformation = 'needs_information';
    case Reviewed = 'reviewed';
    case Matched = 'matched';
    case Mismatch = 'mismatch';
}
```

- [ ] **Step 2: Update generated TypeScript constant**

Run after OpenAPI regeneration (Task 11). The generated `SupplierInvoiceStatus` constant will pick up the new enum cases.

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Invoice/States/SupplierInvoiceStatus.php
git commit -m "feat(state): add matched and mismatch statuses to SupplierInvoiceStatus"
```

---

### Task 4: Data Classes for Tolerance and Match Results

**Files:**
- Create: `apps/api/Domains/Invoice/Data/MatchingToleranceConfigData.php`
- Create: `apps/api/Domains/Invoice/Data/InvoiceMatchResultData.php`

- [ ] **Step 1: Create MatchingToleranceConfigData**

```php
<?php

namespace Domains\Invoice\Data;

class MatchingToleranceConfigData
{
    private const DEFAULTS = [
        'unit_price' => ['percent' => 5.0, 'floor' => 2.00, 'cap' => 250.00],
        'line_total' => ['percent' => 5.0, 'floor' => 10.00, 'cap' => 500.00],
        'tax' => ['percent' => 2.0, 'floor' => 5.00, 'cap' => 100.00],
        'freight' => ['percent' => 5.0, 'floor' => 5.00, 'cap' => 100.00],
        'invoice_total' => ['percent' => 2.0, 'floor' => 25.00, 'cap' => 1000.00],
        'quantity_over' => ['percent' => 0.0, 'floor' => 0, 'cap' => 0],
    ];

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function forDimension(string $dimension, ?array $tenantConfig = null): array
    {
        $default = self::DEFAULTS[$dimension] ?? self::DEFAULTS['unit_price'];

        if ($tenantConfig === null || ! isset($tenantConfig[$dimension])) {
            return $default;
        }

        $tenantDim = $tenantConfig[$dimension];

        return [
            'percent' => (float) ($tenantDim['percent'] ?? $default['percent']),
            'floor' => (float) ($tenantDim['floor'] ?? $default['floor']),
            'cap' => (float) ($tenantDim['cap'] ?? $default['cap']),
        ];
    }
}
```

- [ ] **Step 2: Create InvoiceMatchResultData**

```php
<?php

namespace Domains\Invoice\Data;

class InvoiceMatchResultData
{
    public function __construct(
        public readonly string $dimension,
        public readonly string $matchType,
        public readonly string $matchLevel,
        public readonly ?string $supplierInvoiceLineId,
        public readonly ?string $purchaseOrderLineId,
        public readonly ?string $expectedValue,
        public readonly ?string $actualValue,
        public readonly ?float $tolerancePercentApplied,
        public readonly ?float $toleranceFloorApplied,
        public readonly ?float $toleranceCapApplied,
        public readonly string $result,
        public readonly ?string $notes = null,
    ) {}
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/api/Domains/Invoice/Data/
git commit -m "feat(data): add matching tolerance and result data classes"
```

---

### Task 5: ToleranceService

**Files:**
- Create: `apps/api/Domains/Invoice/Services/ToleranceService.php`

- [ ] **Step 1: Write the ToleranceService with three-threshold comparison**

```php
<?php

namespace Domains\Invoice\Services;

use Domains\Invoice\Data\MatchingToleranceConfigData;

class ToleranceService
{
    private ?array $tenantConfig;

    public function __construct(?array $tenantConfig = null)
    {
        $this->tenantConfig = $tenantConfig;
    }

    public function compare(
        string $expected,
        string $actual,
        string $dimension,
    ): array {
        $tolerance = MatchingToleranceConfigData::forDimension($dimension, $this->tenantConfig);

        $expectedFloat = (float) $expected;
        $actualFloat = (float) $actual;
        $variance = abs($expectedFloat - $actualFloat);

        if ($expectedFloat === 0.0 && $actualFloat === 0.0) {
            return $this->passResult($tolerance);
        }

        if ($expectedFloat === 0.0 && $actualFloat !== 0.0) {
            return $this->failResult($tolerance, 'Expected value is zero but actual is non-zero.');
        }

        $percentageTolerance = $expectedFloat * ($tolerance['percent'] / 100);
        $effectiveTolerance = max($percentageTolerance, $tolerance['floor']);

        $passesEffective = bccomp((string) $variance, (string) $effectiveTolerance, 4) <= 0;
        $passesCap = $tolerance['cap'] === 0.0 || bccomp((string) $variance, (string) $tolerance['cap'], 4) <= 0;

        if ($passesEffective && $passesCap) {
            return $this->passResult($tolerance);
        }

        $notes = [];
        if (! $passesEffective) {
            $notes[] = sprintf('Variance %.4f exceeds effective tolerance %.4f', $variance, $effectiveTolerance);
        }
        if (! $passesCap) {
            $notes[] = sprintf('Variance %.4f exceeds hard cap %.4f', $variance, $tolerance['cap']);
        }

        return $this->failResult($tolerance, implode('; ', $notes));
    }

    public function compareQuantity(
        string $cumulativeInvoiced,
        string $currentInvoiceQty,
        string $effectivePoQty,
    ): array {
        $totalInvoiced = bcadd($cumulativeInvoiced, $currentInvoiceQty, 4);

        if (bccomp($totalInvoiced, $effectivePoQty, 4) <= 0) {
            return [
                'result' => 'pass',
                'notes' => null,
            ];
        }

        $excess = bcsub($totalInvoiced, $effectivePoQty, 4);

        return [
            'result' => 'fail',
            'notes' => sprintf(
                'Cumulative invoiced quantity %s exceeds PO quantity %s by %s',
                $totalInvoiced,
                $effectivePoQty,
                $excess,
            ),
        ];
    }

    private function passResult(array $tolerance): array
    {
        return [
            'result' => 'pass',
            'tolerance_percent_applied' => $tolerance['percent'],
            'tolerance_floor_applied' => $tolerance['floor'],
            'tolerance_cap_applied' => $tolerance['cap'],
            'notes' => null,
        ];
    }

    private function failResult(array $tolerance, string $notes): array
    {
        return [
            'result' => 'fail',
            'tolerance_percent_applied' => $tolerance['percent'],
            'tolerance_floor_applied' => $tolerance['floor'],
            'tolerance_cap_applied' => $tolerance['cap'],
            'notes' => $notes,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Invoice/Services/ToleranceService.php
git commit -m "feat(service): add ToleranceService with three-threshold comparison"
```

---

### Task 6: InvoiceMatchingService

**Files:**
- Create: `apps/api/Domains/Invoice/Services/InvoiceMatchingService.php`

- [ ] **Step 1: Write InvoiceMatchingService**

```php
<?php

namespace Domains\Invoice\Services;

use Domains\Invoice\Data\InvoiceMatchResultData;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Collection;

class InvoiceMatchingService
{
    public function __construct(
        private readonly ToleranceService $toleranceService,
    ) {}

    /**
     * @param SupplierInvoice $invoice
     * @param Collection<int, PurchaseOrderLine> $poLines keyed by id
     * @return array{results: InvoiceMatchResultData[], cumulativeInvoicedUpdates: array<string, string>}
     */
    public function match(
        SupplierInvoice $invoice,
        Collection $poLines,
    ): array {
        $results = [];
        $cumulativeInvoicedUpdates = [];
        $matchingPolicy = $invoice->purchaseOrder->matching_policy ?? 'three_way';

        // Header-level: vendor identity
        $results[] = $this->matchVendorIdentity($invoice);

        // Header-level: invoice total
        $results[] = $this->matchInvoiceTotal($invoice, $poLines);

        foreach ($invoice->lines as $line) {
            /** @var SupplierInvoiceLine $line */
            $pol = $poLines->get($line->purchase_order_line_id);
            if ($pol === null) {
                $results[] = new InvoiceMatchResultData(
                    dimension: 'quantity',
                    matchType: 'two_way',
                    matchLevel: 'line',
                    supplierInvoiceLineId: $line->id,
                    purchaseOrderLineId: $line->purchase_order_line_id,
                    expectedValue: null,
                    actualValue: $line->quantity_invoiced,
                    tolerancePercentApplied: null,
                    toleranceFloorApplied: null,
                    toleranceCapApplied: null,
                    result: 'not_applicable',
                    notes: 'PO line not found',
                );
                continue;
            }

            // Two-way price dimensions
            $results[] = $this->matchUnitPrice($line, $pol);
            $results[] = $this->matchLineTotal($line, $pol);
            $results[] = $this->matchTax($line, $pol);
            $results[] = $this->matchFreight($line, $pol);

            // Two-way quantity with cumulative over-billing protection
            $cumulativeInvoiced = $pol->cumulative_quantity_invoiced ?? '0.0000';
            $effectivePoQty = $pol->quantity;

            if ($pol->cancelled_by_change_order_id !== null) {
                $effectivePoQty = bcsub((string) $pol->quantity, (string) $pol->quantity, 4);
            }

            $qtyResult = $this->toleranceService->compareQuantity(
                $cumulativeInvoiced,
                $line->quantity_invoiced,
                (string) $effectivePoQty,
            );

            $qtyNote = $qtyResult['notes'];
            $passesQty = $qtyResult['result'] === 'pass';

            // Three-way quantity (only if policy is three_way)
            if ($matchingPolicy === 'three_way') {
                $acceptedQty = $pol->cumulative_quantity_accepted ?? '0.0000';
                $receiptResult = $this->toleranceService->compareQuantity(
                    $cumulativeInvoiced,
                    $line->quantity_invoiced,
                    (string) $acceptedQty,
                );

                if ($receiptResult['result'] !== 'pass') {
                    $passesQty = false;
                    $qtyNote = $qtyNote
                        ? $qtyNote . '; ' . $receiptResult['notes']
                        : $receiptResult['notes'];
                }

                $results[] = new InvoiceMatchResultData(
                    dimension: 'quantity',
                    matchType: 'three_way',
                    matchLevel: 'line',
                    supplierInvoiceLineId: $line->id,
                    purchaseOrderLineId: $line->purchase_order_line_id,
                    expectedValue: (string) $acceptedQty,
                    actualValue: $line->quantity_invoiced,
                    tolerancePercentApplied: 0.0,
                    toleranceFloorApplied: 0.0,
                    toleranceCapApplied: 0.0,
                    result: $receiptResult['result'],
                    notes: $receiptResult['notes'],
                );
            }

            // Two-way quantity result
            $results[] = new InvoiceMatchResultData(
                dimension: 'quantity',
                matchType: 'two_way',
                matchLevel: 'line',
                supplierInvoiceLineId: $line->id,
                purchaseOrderLineId: $line->purchase_order_line_id,
                expectedValue: (string) $effectivePoQty,
                actualValue: $line->quantity_invoiced,
                tolerancePercentApplied: 0.0,
                toleranceFloorApplied: 0.0,
                toleranceCapApplied: 0.0,
                result: $passesQty ? 'pass' : 'fail',
                notes: $qtyNote,
            );

            // Track cumulative update for this PO line
            $currentCumulative = $cumulativeInvoicedUpdates[$line->purchase_order_line_id] ?? $cumulativeInvoiced;
            $cumulativeInvoicedUpdates[$line->purchase_order_line_id] = bcadd(
                $currentCumulative,
                $line->quantity_invoiced,
                4,
            );
        }

        return [
            'results' => $results,
            'cumulativeInvoicedUpdates' => $cumulativeInvoicedUpdates,
        ];
    }

    private function matchVendorIdentity(SupplierInvoice $invoice): InvoiceMatchResultData
    {
        $pass = $invoice->vendor_id !== null
            && $invoice->purchaseOrder->vendor_id !== null
            && $invoice->vendor_id === $invoice->purchaseOrder->vendor_id;

        return new InvoiceMatchResultData(
            dimension: 'vendor_identity',
            matchType: 'two_way',
            matchLevel: 'header',
            supplierInvoiceLineId: null,
            purchaseOrderLineId: null,
            expectedValue: null,
            actualValue: null,
            tolerancePercentApplied: null,
            toleranceFloorApplied: null,
            toleranceCapApplied: null,
            result: $pass ? 'pass' : 'fail',
            notes: $pass ? null : sprintf(
                'Invoice vendor %s does not match PO vendor %s',
                $invoice->vendor_id ?? 'none',
                $invoice->purchaseOrder->vendor_id ?? 'none',
            ),
        );
    }

    private function matchInvoiceTotal(SupplierInvoice $invoice, Collection $poLines): InvoiceMatchResultData
    {
        $poTotal = '0.0000';
        foreach ($poLines as $pol) {
            $poTotal = bcadd($poTotal, (string) ($pol->total_amount ?? '0.0000'), 4);
        }

        $comparison = $this->toleranceService->compare(
            $poTotal,
            $invoice->total_amount ?? '0.0000',
            'invoice_total',
        );

        return new InvoiceMatchResultData(
            dimension: 'invoice_total',
            matchType: 'two_way',
            matchLevel: 'header',
            supplierInvoiceLineId: null,
            purchaseOrderLineId: null,
            expectedValue: $poTotal,
            actualValue: $invoice->total_amount,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchUnitPrice(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $comparison = $this->toleranceService->compare(
            (string) ($pol->unit_price ?? '0.0000'),
            $line->unit_price,
            'unit_price',
        );

        return new InvoiceMatchResultData(
            dimension: 'unit_price',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->unit_price ?? '0.0000'),
            actualValue: $line->unit_price,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchLineTotal(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $expected = $pol->subtotal_amount ?? bcadd(
            bcmul((string) ($pol->unit_price ?? '0'), (string) ($pol->quantity ?? '0'), 4),
            '0',
            4,
        );

        $comparison = $this->toleranceService->compare(
            (string) $expected,
            $line->line_subtotal,
            'line_total',
        );

        return new InvoiceMatchResultData(
            dimension: 'line_total',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) $expected,
            actualValue: $line->line_subtotal,
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: $comparison['result'],
            notes: $comparison['notes'],
        );
    }

    private function matchTax(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $comparison = $this->toleranceService->compare(
            (string) ($pol->tax_amount ?? '0.0000'),
            '0.0000',
            'tax',
        );

        return new InvoiceMatchResultData(
            dimension: 'tax',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->tax_amount ?? '0.0000'),
            actualValue: '0.0000',
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: 'not_applicable',
            notes: 'Tax matching at line level not yet supported; tax is typically header-level',
        );
    }

    private function matchFreight(SupplierInvoiceLine $line, PurchaseOrderLine $pol): InvoiceMatchResultData
    {
        $comparison = $this->toleranceService->compare(
            (string) ($pol->freight_amount ?? '0.0000'),
            '0.0000',
            'freight',
        );

        return new InvoiceMatchResultData(
            dimension: 'freight',
            matchType: 'two_way',
            matchLevel: 'line',
            supplierInvoiceLineId: $line->id,
            purchaseOrderLineId: $line->purchase_order_line_id,
            expectedValue: (string) ($pol->freight_amount ?? '0.0000'),
            actualValue: '0.0000',
            tolerancePercentApplied: $comparison['tolerance_percent_applied'],
            toleranceFloorApplied: $comparison['tolerance_floor_applied'],
            toleranceCapApplied: $comparison['tolerance_cap_applied'],
            result: 'not_applicable',
            notes: 'Freight matching at line level not yet supported; freight is typically header-level',
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Invoice/Services/InvoiceMatchingService.php
git commit -m "feat(service): add InvoiceMatchingService with two-way and three-way matching logic"
```

---

### Task 7: RunInvoiceMatching Action

**Files:**
- Create: `apps/api/Domains/Invoice/Actions/RunInvoiceMatching.php`

- [ ] **Step 1: Write RunInvoiceMatching action**

```php
<?php

namespace Domains\Invoice\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\Services\InvoiceMatchingService;
use Domains\Invoice\Services\ToleranceService;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RunInvoiceMatching
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(SupplierInvoice $supplierInvoice, User $actor, int $lockVersion, string $triggerSource = 'manual'): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor, $lockVersion, $triggerSource) {
            $invoice = SupplierInvoice::query()
                ->whereKey($supplierInvoice->id)
                ->where('tenant_id', $supplierInvoice->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $invoice->assertLockVersion($lockVersion);

            if ($invoice->statusState() !== SupplierInvoiceStatus::Reviewed) {
                throw new ConflictHttpException('Matching can only be run on reviewed invoices.');
            }

            $invoice->load(['lines', 'purchaseOrder.lines']);

            $poLines = $invoice->purchaseOrder->lines->keyBy('id');

            $toleranceService = new ToleranceService();
            $matchingService = new InvoiceMatchingService($toleranceService);

            $matchResult = $matchingService->match($invoice, $poLines);

            // Delete prior results for this invoice (supports re-run)
            SupplierInvoiceMatchResult::query()
                ->where('supplier_invoice_id', $invoice->id)
                ->delete();

            // Persist new results
            $hasFailures = false;
            foreach ($matchResult['results'] as $result) {
                SupplierInvoiceMatchResult::create([
                    'tenant_id' => $invoice->tenant_id,
                    'supplier_invoice_id' => $invoice->id,
                    'supplier_invoice_line_id' => $result->supplierInvoiceLineId,
                    'purchase_order_line_id' => $result->purchaseOrderLineId,
                    'match_type' => $result->matchType,
                    'match_level' => $result->matchLevel,
                    'dimension' => $result->dimension,
                    'expected_value' => $result->expectedValue,
                    'actual_value' => $result->actualValue,
                    'tolerance_percent_applied' => $result->tolerancePercentApplied,
                    'tolerance_floor_applied' => $result->toleranceFloorApplied,
                    'tolerance_cap_applied' => $result->toleranceCapApplied,
                    'result' => $result->result,
                    'notes' => $result->notes,
                ]);

                if ($result->result === 'fail') {
                    $hasFailures = true;
                }
            }

            // Update cumulative invoiced on PO lines
            foreach ($matchResult['cumulativeInvoicedUpdates'] as $poLineId => $newCumulative) {
                PurchaseOrderLine::query()
                    ->whereKey($poLineId)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->update(['cumulative_quantity_invoiced' => $newCumulative]);
            }

            // Set matching status
            $before = $invoice->only(['matching_status', 'lock_version']);
            $invoice->forceFill([
                'matching_status' => $hasFailures ? SupplierInvoiceStatus::Mismatch->value : SupplierInvoiceStatus::Matched->value,
                'lock_version' => $invoice->lock_version + 1,
            ])->save();
            $after = $invoice->only(['matching_status', 'lock_version']);

            // Audit event
            $totalResults = count($matchResult['results']);
            $failResults = collect($matchResult['results'])->where('result', 'fail')->count();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $invoice->tenant,
                actor: $actor,
                action: 'supplier_invoice.matching_completed',
                subject: $invoice,
                metadata: [
                    'matching_status' => $invoice->matching_status,
                    'total_results' => $totalResults,
                    'fail_results' => $failResults,
                    'trigger_source' => $triggerSource,
                    'matching_policy' => $invoice->purchaseOrder->matching_policy,
                    'dimensions_with_issues' => collect($matchResult['results'])
                        ->where('result', 'fail')
                        ->pluck('dimension')
                        ->unique()
                        ->values()
                        ->toArray(),
                ],
                before: $before,
                after: $after,
            ));

            $invoice->refresh();

            return $invoice;
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/Domains/Invoice/Actions/RunInvoiceMatching.php
git commit -m "feat(action): add RunInvoiceMatching action with full matching orchestration"
```

---

### Task 8: Domain Event and Listener for Goods Receipt Re-Trigger

**Files:**
- Create: `apps/api/Domains/Receiving/Events/GoodsReceiptLinePosted.php`
- Create: `apps/api/Domains/Invoice/Listeners/ReRunMatchingOnGoodsReceipt.php`
- Create: `apps/api/app/Providers/EventServiceProvider.php`

- [ ] **Step 1: Create GoodsReceiptLinePosted event**

```php
<?php

namespace Domains\Receiving\Events;

use Domains\Receiving\Models\GoodsReceiptLine;
use Illuminate\Foundation\Events\Dispatchable;

class GoodsReceiptLinePosted
{
    use Dispatchable;

    public function __construct(
        public readonly GoodsReceiptLine $goodsReceiptLine,
    ) {}
}
```

- [ ] **Step 2: Create ReRunMatchingOnGoodsReceipt listener**

```php
<?php

namespace Domains\Invoice\Listeners;

use App\Models\User;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\Receiving\Events\GoodsReceiptLinePosted;

class ReRunMatchingOnGoodsReceipt
{
    public function handle(GoodsReceiptLinePosted $event): void
    {
        $receiptLine = $event->goodsReceiptLine;
        $purchaseOrderId = $receiptLine->goodsReceipt->purchase_order_id;

        if ($purchaseOrderId === null) {
            return;
        }

        $pendingInvoices = SupplierInvoice::query()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereIn('matching_status', [
                SupplierInvoiceStatus::Reviewed->value,
                null,
            ])
            ->orWhere(function ($query) use ($purchaseOrderId) {
                $query->where('purchase_order_id', $purchaseOrderId)
                    ->where('matching_status', SupplierInvoiceStatus::Mismatch->value);
            })
            ->get();

        foreach ($pendingInvoices as $invoice) {
            try {
                $invoice->refresh();
                $systemUser = User::query()->where('email', 'system@cognify.local')->first();
                if ($systemUser === null) {
                    continue;
                }

                $action = app(\Domains\Invoice\Actions\RunInvoiceMatching::class);
                $action->handle($invoice, $systemUser, $invoice->lock_version, 'automatic');
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
```

- [ ] **Step 3: Create EventServiceProvider**

```php
<?php

namespace App\Providers;

use Domains\Invoice\Listeners\ReRunMatchingOnGoodsReceipt;
use Domains\Receiving\Events\GoodsReceiptLinePosted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        GoodsReceiptLinePosted::class => [
            ReRunMatchingOnGoodsReceipt::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
```

- [ ] **Step 4: Register EventServiceProvider in config/app.php**

```php
// Add to 'providers' array:
App\Providers\EventServiceProvider::class,
```

- [ ] **Step 5: Update RecordGoodsReceipt to dispatch event**

In `apps/api/Domains/Receiving/Actions/RecordGoodsReceipt.php`, after saving receipt lines:

```php
use Domains\Receiving\Events\GoodsReceiptLinePosted;

// After each line is created inside the transaction:
foreach ($lines as $line) {
    GoodsReceiptLinePosted::dispatch($line);
}
```

- [ ] **Step 6: Commit**

```bash
git add apps/api/Domains/Receiving/Events/ \
       apps/api/Domains/Invoice/Listeners/ \
       apps/api/app/Providers/EventServiceProvider.php
git commit -m "feat(events): add goods receipt event and matching re-trigger listener"
```

---

### Task 9: Register SupplierInvoice in AuditSubject

**Files:**
- Modify: `apps/api/app/Audit/AuditSubject.php`

- [ ] **Step 1: Add SupplierInvoice to the type map**

```php
use Domains\Invoice\Models\SupplierInvoice;

// In $typeMap, add:
SupplierInvoice::class => 'supplier_invoice',
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/app/Audit/AuditSubject.php
git commit -m "fix(audit): register SupplierInvoice in AuditSubject type map"
```

---

### Task 10: OpenAPI Spec Update

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`

- [ ] **Step 1: Add matching schemas and routes to openapi.json**

Add to `components/schemas`:

```json
"RunInvoiceMatchingRequest": {
  "type": "object",
  "required": ["lockVersion"],
  "properties": {
    "lockVersion": { "type": "integer", "minimum": 1 }
  }
},
"SupplierInvoiceMatchResult": {
  "type": "object",
  "properties": {
    "id": { "type": "string", "format": "uuid" },
    "lineNumber": { "type": "integer", "nullable": true },
    "matchLevel": { "type": "string", "enum": ["header", "line"] },
    "matchType": { "type": "string", "enum": ["two_way", "three_way"] },
    "dimension": { "type": "string", "enum": ["vendor_identity", "quantity", "unit_price", "line_total", "tax", "freight", "invoice_total"] },
    "expectedValue": { "type": "string", "nullable": true },
    "actualValue": { "type": "string", "nullable": true },
    "tolerancePercentApplied": { "type": "number", "nullable": true },
    "toleranceFloorApplied": { "type": "number", "nullable": true },
    "toleranceCapApplied": { "type": "number", "nullable": true },
    "result": { "type": "string", "enum": ["pass", "fail", "not_applicable"] },
    "notes": { "type": "string", "nullable": true }
  }
},
"SupplierInvoiceMatchResultListResponse": {
  "type": "object",
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/SupplierInvoiceMatchResult" }
    }
  }
},
"SupplierInvoiceMatchingStatus": {
  "type": "string",
  "enum": ["pending", "matched", "mismatch"],
  "nullable": true
},
"SupplierInvoiceMatchSummary": {
  "type": "object",
  "properties": {
    "totalLines": { "type": "integer" },
    "matchedLines": { "type": "integer" },
    "mismatchLines": { "type": "integer" },
    "dimensionsWithIssues": { "type": "array", "items": { "type": "string" } }
  }
}
```

Add to SupplierInvoice schema:
```json
"matchingStatus": { "$ref": "#/components/schemas/SupplierInvoiceMatchingStatus" },
"matchSummary": { "$ref": "#/components/schemas/SupplierInvoiceMatchSummary" }
```

Add to SupplierInvoiceQueueItem schema:
```json
"matchingStatus": { "$ref": "#/components/schemas/SupplierInvoiceMatchingStatus" }
```

Add to listSupplierInvoiceQueue params:
```json
"matchingStatus": { "type": "string", "enum": ["pending", "matched", "mismatch"] },
"hasMismatch": { "type": "boolean" }
```

Add new paths:
```json
"/api/supplier-invoices/{supplierInvoice}/run-matching": {
  "post": {
    "tags": ["Accounts Payable"],
    "operationId": "runSupplierInvoiceMatching",
    "parameters": [
      { "$ref": "#/components/parameters/TenantId" },
      { "name": "supplierInvoice", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/RunInvoiceMatchingRequest" }
        }
      }
    },
    "responses": {
      "200": { "$ref": "#/components/responses/SupplierInvoiceResponse" },
      "409": { "$ref": "#/components/responses/Conflict" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
},
"/api/supplier-invoices/{supplierInvoice}/match-results": {
  "get": {
    "tags": ["Accounts Payable"],
    "operationId": "listSupplierInvoiceMatchResults",
    "parameters": [
      { "$ref": "#/components/parameters/TenantId" },
      { "name": "supplierInvoice", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "$ref": "#/components/responses/SupplierInvoiceMatchResultListResponse" }
    }
  }
}
```

Add response ref:
```json
"SupplierInvoiceMatchResultListResponse": {
  "description": "List of match results",
  "content": {
    "application/json": {
      "schema": { "$ref": "#/components/schemas/SupplierInvoiceMatchResultListResponse" }
    }
  }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/api/storage/openapi/openapi.json
git commit -m "feat(openapi): add matching routes and schemas to OpenAPI spec"
```

---

### Task 11: HTTP Layer — Controller, Request, Resource

**Files:**
- Create: `apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceMatchingController.php`
- Create: `apps/api/Domains/Invoice/Http/Requests/RunInvoiceMatchingRequest.php`
- Create: `apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceMatchResultResource.php`

- [ ] **Step 1: Create RunInvoiceMatchingRequest**

```php
<?php

namespace Domains\Invoice\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class RunInvoiceMatchingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('supplierInvoice');

        if ($invoice === null) {
            throw new AuthorizationException('Supplier invoice not found.');
        }

        return $this->user() !== null
            && $this->user()->can('review', $invoice);
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

- [ ] **Step 2: Create SupplierInvoiceMatchResultResource**

```php
<?php

namespace Domains\Invoice\Http\Resources;

use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SupplierInvoiceMatchResult */
class SupplierInvoiceMatchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lineNumber = null;
        if ($this->supplier_invoice_line_id !== null) {
            $line = SupplierInvoiceLine::find($this->supplier_invoice_line_id);
            $lineNumber = $line?->line_number;
        }

        return [
            'id' => $this->id,
            'lineNumber' => $lineNumber,
            'matchLevel' => $this->match_level,
            'matchType' => $this->match_type,
            'dimension' => $this->dimension,
            'expectedValue' => $this->expected_value !== null ? (string) $this->expected_value : null,
            'actualValue' => $this->actual_value !== null ? (string) $this->actual_value : null,
            'tolerancePercentApplied' => $this->tolerance_percent_applied !== null ? (float) $this->tolerance_percent_applied : null,
            'toleranceFloorApplied' => $this->tolerance_floor_applied !== null ? (float) $this->tolerance_floor_applied : null,
            'toleranceCapApplied' => $this->tolerance_cap_applied !== null ? (float) $this->tolerance_cap_applied : null,
            'result' => $this->result,
            'notes' => $this->notes,
        ];
    }
}
```

- [ ] **Step 3: Create SupplierInvoiceMatchingController**

```php
<?php

namespace Domains\Invoice\Http\Controllers;

use App\Tenancy\CurrentTenant;
use Domains\Invoice\Actions\RunInvoiceMatching;
use Domains\Invoice\Http\Requests\RunInvoiceMatchingRequest;
use Domains\Invoice\Http\Resources\SupplierInvoiceMatchResultResource;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SupplierInvoiceMatchingController extends Controller
{
    public function __construct(
        private readonly RunInvoiceMatching $runInvoiceMatching,
    ) {}

    public function run(
        RunInvoiceMatchingRequest $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse {
        $this->tenantOrAbort($currentTenant, $supplierInvoice);

        $actor = $request->user();
        $invoice = $this->runInvoiceMatching->handle(
            $supplierInvoice,
            $actor,
            (int) $request->input('lockVersion'),
            'manual',
        );

        return response()->json(
            $invoice->load(['lines', 'purchaseOrder', 'vendor', 'matchResults'])->resolve()
        );
    }

    public function results(
        Request $request,
        CurrentTenant $currentTenant,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse {
        $this->tenantOrAbort($currentTenant, $supplierInvoice);

        if ($request->user()?->can('review', $supplierInvoice) !== true) {
            abort(403, 'You do not have permission to view match results.');
        }

        $results = SupplierInvoiceMatchResult::query()
            ->where('supplier_invoice_id', $supplierInvoice->id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => SupplierInvoiceMatchResultResource::collection($results),
        ]);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant, SupplierInvoice $invoice): void
    {
        if ($invoice->tenant_id !== $currentTenant->tenantId()) {
            abort(404);
        }
    }
}
```

- [ ] **Step 4: Register routes in api.php**

```php
use Domains\Invoice\Http\Controllers\SupplierInvoiceMatchingController;

// Inside the RequireTenantHeader group, add:
Route::post('/supplier-invoices/{supplierInvoice}/run-matching', [SupplierInvoiceMatchingController::class, 'run']);
Route::get('/supplier-invoices/{supplierInvoice}/match-results', [SupplierInvoiceMatchingController::class, 'results']);
```

- [ ] **Step 5: Commit**

```bash
git add apps/api/Domains/Invoice/Http/Controllers/SupplierInvoiceMatchingController.php \
       apps/api/Domains/Invoice/Http/Requests/RunInvoiceMatchingRequest.php \
       apps/api/Domains/Invoice/Http/Resources/SupplierInvoiceMatchResultResource.php \
       apps/api/routes/api.php
git commit -m "feat(http): add matching controller, request, resource, and routes"
```

---

### Task 12: Regenerate API Client

**Files:**
- Generated: `packages/api-client/src/generated/`

- [ ] **Step 1: Generate API client**

```bash
pnpm generate:api
```

- [ ] **Step 2: Verify typecheck passes**

```bash
pnpm typecheck
```

- [ ] **Step 3: Check contract**

```bash
pnpm check:api-contract
```

- [ ] **Step 4: Commit**

```bash
git add packages/api-client/src/generated/
git commit -m "feat(api-client): regenerate after matching OpenAPI changes"
```

---

### Task 13: Frontend — API Layer and Hooks

**Files:**
- Modify: `apps/web/features/accounts-payable/api/accounts-payable-invoices-api.ts`
- Create: `apps/web/features/accounts-payable/hooks/use-invoice-matching.ts`

- [ ] **Step 1: Extend accounts-payable API with matching endpoints**

In `apps/web/features/accounts-payable/api/accounts-payable-invoices-api.ts`, add:

```typescript
import {
  runSupplierInvoiceMatching,
  listSupplierInvoiceMatchResults,
} from "@cognify/api-client";

export interface MatchSummary {
  totalLines: number;
  matchedLines: number;
  mismatchLines: number;
  dimensionsWithIssues: string[];
}

export interface MatchResult {
  id: string;
  lineNumber: number | null;
  matchLevel: "header" | "line";
  matchType: "two_way" | "three_way";
  dimension: string;
  expectedValue: string | null;
  actualValue: string | null;
  tolerancePercentApplied: number | null;
  toleranceFloorApplied: number | null;
  toleranceCapApplied: number | null;
  result: "pass" | "fail" | "not_applicable";
  notes: string | null;
}

export async function triggerInvoiceMatching(
  invoiceId: string,
  lockVersion: number,
): Promise<any> {
  const { data } = await runSupplierInvoiceMatching(invoiceId, {
    lockVersion,
  });
  return data;
}

export async function fetchInvoiceMatchResults(
  invoiceId: string,
): Promise<MatchResult[]> {
  const { data } = await listSupplierInvoiceMatchResults(invoiceId);
  return data ?? [];
}

export function buildMatchSummary(results: MatchResult[]): MatchSummary {
  const lineResults = results.filter((r) => r.matchLevel === "line");
  const lineNumbers = [...new Set(lineResults.map((r) => r.lineNumber).filter((n): n is number => n !== null))];
  const mismatchLines = [...new Set(lineResults.filter((r) => r.result === "fail").map((r) => r.lineNumber))];
  const dimensionsWithIssues = [...new Set(results.filter((r) => r.result === "fail").map((r) => r.dimension))];

  return {
    totalLines: lineNumbers.length,
    matchedLines: lineNumbers.length - mismatchLines.length,
    mismatchLines: mismatchLines.length,
    dimensionsWithIssues,
  };
}
```

- [ ] **Step 2: Create use-invoice-matching hook**

```typescript
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  triggerInvoiceMatching,
  fetchInvoiceMatchResults,
  buildMatchSummary,
  MatchResult,
  MatchSummary,
} from "../api/accounts-payable-invoices-api";

export function useInvoiceMatchResults(invoiceId: string | null) {
  return useQuery<MatchResult[]>({
    queryKey: ["supplier-invoice", "match-results", invoiceId],
    queryFn: () => fetchInvoiceMatchResults(invoiceId!),
    enabled: !!invoiceId,
  });
}

export function useRunInvoiceMatching(invoiceId: string | null) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ lockVersion }: { lockVersion: number }) =>
      triggerInvoiceMatching(invoiceId!, lockVersion),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: ["supplier-invoice", "match-results", invoiceId],
      });
      queryClient.invalidateQueries({
        queryKey: ["supplier-invoice", "detail", invoiceId],
      });
      queryClient.invalidateQueries({
        queryKey: ["supplier-invoice-queue"],
      });
    },
  });
}

export function useInvoiceMatchSummary(invoiceId: string | null) {
  const { data: results, isLoading, isError } = useInvoiceMatchResults(invoiceId);

  return {
    summary: results ? buildMatchSummary(results) : null,
    results,
    isLoading,
    isError,
  };
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/web/features/accounts-payable/api/accounts-payable-invoices-api.ts \
       apps/web/features/accounts-payable/hooks/use-invoice-matching.ts
git commit -m "feat(web): add matching API layer and hooks"
```

---

### Task 14: Frontend — Match Results Components

**Files:**
- Create: `apps/web/features/accounts-payable/components/invoice-matching-status-badge.tsx`
- Create: `apps/web/features/accounts-payable/components/invoice-match-results-panel.tsx`

- [ ] **Step 1: Create InvoiceMatchingStatusBadge**

```tsx
import { SupplierInvoiceStatus } from "@cognify/api-client";
import { Badge } from "@cognify/ui/badge";
import { cn } from "@cognify/ui/lib/utils";

interface Props {
  matchingStatus: string | null | undefined;
}

export function InvoiceMatchingStatusBadge({ matchingStatus }: Props) {
  if (!matchingStatus || matchingStatus === "pending") {
    return (
      <Badge variant="outline" className="text-muted-foreground">
        Pending
      </Badge>
    );
  }

  if (matchingStatus === SupplierInvoiceStatus.matched) {
    return (
      <Badge className={cn("bg-green-100 text-green-800 hover:bg-green-100")}>
        Matched
      </Badge>
    );
  }

  if (matchingStatus === SupplierInvoiceStatus.mismatch) {
    return (
      <Badge className={cn("bg-red-100 text-red-800 hover:bg-red-100")}>
        Mismatch
      </Badge>
    );
  }

  return null;
}
```

- [ ] **Step 2: Create InvoiceMatchResultsPanel**

```tsx
"use client";

import { useState } from "react";
import { Button } from "@cognify/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@cognify/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui/table";
import { Skeleton } from "@cognify/ui/skeleton";
import { AlertCircle, CheckCircle2, AlertTriangle } from "lucide-react";
import { useInvoiceMatchSummary, useRunInvoiceMatching } from "../hooks/use-invoice-matching";
import { InvoiceMatchingStatusBadge } from "./invoice-matching-status-badge";
import { MatchResult } from "../api/accounts-payable-invoices-api";

interface Props {
  invoiceId: string;
  lockVersion: number;
  invoiceStatus: string;
  matchingStatus: string | null | undefined;
}

export function InvoiceMatchResultsPanel({
  invoiceId,
  lockVersion,
  invoiceStatus,
  matchingStatus,
}: Props) {
  const { summary, results, isLoading, isError } = useInvoiceMatchSummary(invoiceId);
  const runMatching = useRunInvoiceMatching(invoiceId);
  const [isRunning, setIsRunning] = useState(false);

  const canRunMatching =
    invoiceStatus === "reviewed" &&
    (!matchingStatus || matchingStatus === "mismatch" || matchingStatus === "pending" || matchingStatus === null);

  const handleRunMatching = async () => {
    setIsRunning(true);
    try {
      await runMatching.mutateAsync({ lockVersion });
    } finally {
      setIsRunning(false);
    }
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="text-lg">Matching Results</CardTitle>
          <CardDescription>
            Two-way and three-way invoice matching status
          </CardDescription>
        </div>
        <div className="flex items-center gap-3">
          <InvoiceMatchingStatusBadge matchingStatus={matchingStatus} />
          {canRunMatching && (
            <Button
              size="sm"
              onClick={handleRunMatching}
              disabled={isRunning || runMatching.isPending}
            >
              {isRunning || runMatching.isPending ? "Running..." : "Run Matching"}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {isLoading && (
          <div className="space-y-2">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </div>
        )}

        {isError && (
          <div className="flex items-center gap-2 text-red-600">
            <AlertCircle className="h-4 w-4" />
            <p className="text-sm">Failed to load match results.</p>
          </div>
        )}

        {!isLoading && !isError && summary && (
          <>
            <div className="mb-3 flex gap-4 text-sm">
              <span className="text-muted-foreground">
                {summary.matchedLines} of {summary.totalLines} lines matched
              </span>
              {summary.mismatchLines > 0 && (
                <span className="text-red-600 font-medium">
                  Issues in: {summary.dimensionsWithIssues.join(", ")}
                </span>
              )}
            </div>

            {results && results.length > 0 && (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Line</TableHead>
                    <TableHead>Level</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Dimension</TableHead>
                    <TableHead>Expected</TableHead>
                    <TableHead>Actual</TableHead>
                    <TableHead>Result</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {results.map((r: MatchResult) => (
                    <TableRow key={r.id} className={r.result === "fail" ? "bg-red-50" : ""}>
                      <TableCell>{r.lineNumber ?? "—"}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{r.matchLevel}</TableCell>
                      <TableCell className="text-xs">{r.matchType === "two_way" ? "2W" : "3W"}</TableCell>
                      <TableCell className="font-medium">{r.dimension}</TableCell>
                      <TableCell className="font-mono text-xs">{r.expectedValue ?? "—"}</TableCell>
                      <TableCell className="font-mono text-xs">{r.actualValue ?? "—"}</TableCell>
                      <TableCell>
                        {r.result === "pass" && (
                          <CheckCircle2 className="h-4 w-4 text-green-600" />
                        )}
                        {r.result === "fail" && (
                          <AlertTriangle className="h-4 w-4 text-red-600" title={r.notes ?? ""} />
                        )}
                        {r.result === "not_applicable" && (
                          <span className="text-xs text-muted-foreground">N/A</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </>
        )}

        {!isLoading && !isError && !summary && (
          <p className="text-sm text-muted-foreground">
            No match results yet. Run matching to compare this invoice against the purchase order and receipts.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
```

- [ ] **Step 3: Commit**

```bash
git add apps/web/features/accounts-payable/components/
git commit -m "feat(web): add matching status badge and results panel components"
```

---

### Task 15: Frontend — Integrate Matching into Invoice Review Panel and Queue

**Files:**
- Modify: `apps/web/features/accounts-payable/components/invoice-review-panel.tsx`
- Modify: `apps/web/features/accounts-payable/tables/accounts-payable-invoice-queue-table.tsx`
- Modify: `apps/web/features/accounts-payable/workflows/accounts-payable-invoice-queue-page.tsx`
- Modify: `apps/web/features/accounts-payable/components/invoice-queue-summary.tsx`

- [ ] **Step 1: Add match results panel to invoice review panel**

In `invoice-review-panel.tsx`, import and render `InvoiceMatchResultsPanel` below the review checklist section:

```tsx
import { InvoiceMatchResultsPanel } from "./invoice-match-results-panel";

// In the render, after the review section:
{invoice.matchingStatus !== undefined && (
  <div className="mt-6">
    <InvoiceMatchResultsPanel
      invoiceId={invoice.id}
      lockVersion={invoice.lockVersion}
      invoiceStatus={invoice.status}
      matchingStatus={invoice.matchingStatus}
    />
  </div>
)}
```

- [ ] **Step 2: Add matching status column to queue table**

In `accounts-payable-invoice-queue-table.tsx`, import and add a column:

```tsx
import { InvoiceMatchingStatusBadge } from "../components/invoice-matching-status-badge";

// Add column definition after status column:
{
  accessorKey: "matchingStatus",
  header: "Matching",
  cell: ({ row }) => (
    <InvoiceMatchingStatusBadge matchingStatus={row.original.matchingStatus} />
  ),
}
```

- [ ] **Step 3: Add matching filter to queue page**

In `accounts-payable-invoice-queue-page.tsx`, extend the filter tabs:

```tsx
// Add matchingStatus to the filter state:
const [matchingFilter, setMatchingFilter] = useState<string | undefined>();

// Add filter tabs row below existing status tabs:
<div className="flex gap-2">
  <Button
    variant={!matchingFilter ? "default" : "outline"}
    size="sm"
    onClick={() => setMatchingFilter(undefined)}
  >
    All
  </Button>
  <Button
    variant={matchingFilter === "pending" ? "default" : "outline"}
    size="sm"
    onClick={() => setMatchingFilter("pending")}
  >
    Pending
  </Button>
  <Button
    variant={matchingFilter === "matched" ? "default" : "outline"}
    size="sm"
    onClick={() => setMatchingFilter("matched")}
  >
    Matched
  </Button>
  <Button
    variant={matchingFilter === "mismatch" ? "default" : "outline"}
    size="sm"
    onClick={() => setMatchingFilter("mismatch")}
  >
    Mismatch
  </Button>
</div>

// Pass to API call params:
const params = { ...baseParams, matchingStatus: matchingFilter };
```

- [ ] **Step 4: Commit**

```bash
git add apps/web/features/accounts-payable/components/invoice-review-panel.tsx \
       apps/web/features/accounts-payable/tables/ \
       apps/web/features/accounts-payable/workflows/
git commit -m "feat(web): integrate matching panel and queue filters into AP workspace"
```

---

### Task 16: Frontend — MSW Fixtures and Handlers

**Files:**
- Create: `apps/web/features/accounts-payable/mocks/invoice-matching-fixtures.ts`
- Modify: `apps/web/features/accounts-payable/mocks/accounts-payable-invoice-handlers.ts`

- [ ] **Step 1: Create matching fixtures**

```typescript
import type { MatchResult } from "../api/accounts-payable-invoices-api";

export const mockMatchedResults: MatchResult[] = [
  {
    id: "mr-header-1",
    lineNumber: null,
    matchLevel: "header",
    matchType: "two_way",
    dimension: "vendor_identity",
    expectedValue: null,
    actualValue: null,
    tolerancePercentApplied: null,
    toleranceFloorApplied: null,
    toleranceCapApplied: null,
    result: "pass",
    notes: null,
  },
  {
    id: "mr-header-2",
    lineNumber: null,
    matchLevel: "header",
    matchType: "two_way",
    dimension: "invoice_total",
    expectedValue: "1500.0000",
    actualValue: "1498.5000",
    tolerancePercentApplied: 2.0,
    toleranceFloorApplied: 25.0,
    toleranceCapApplied: 1000.0,
    result: "pass",
    notes: null,
  },
  {
    id: "mr-line-1-qty",
    lineNumber: 1,
    matchLevel: "line",
    matchType: "two_way",
    dimension: "quantity",
    expectedValue: "10.0000",
    actualValue: "10.0000",
    tolerancePercentApplied: 0.0,
    toleranceFloorApplied: 0.0,
    toleranceCapApplied: 0.0,
    result: "pass",
    notes: null,
  },
  {
    id: "mr-line-1-price",
    lineNumber: 1,
    matchLevel: "line",
    matchType: "two_way",
    dimension: "unit_price",
    expectedValue: "100.0000",
    actualValue: "100.0000",
    tolerancePercentApplied: 5.0,
    toleranceFloorApplied: 2.0,
    toleranceCapApplied: 250.0,
    result: "pass",
    notes: null,
  },
];

export const mockMismatchedResults: MatchResult[] = [
  {
    id: "mr-mm-header-1",
    lineNumber: null,
    matchLevel: "header",
    matchType: "two_way",
    dimension: "vendor_identity",
    expectedValue: null,
    actualValue: null,
    tolerancePercentApplied: null,
    toleranceFloorApplied: null,
    toleranceCapApplied: null,
    result: "pass",
    notes: null,
  },
  {
    id: "mr-mm-line-1-qty",
    lineNumber: 1,
    matchLevel: "line",
    matchType: "two_way",
    dimension: "quantity",
    expectedValue: "10.0000",
    actualValue: "12.0000",
    tolerancePercentApplied: 0.0,
    toleranceFloorApplied: 0.0,
    toleranceCapApplied: 0.0,
    result: "fail",
    notes: "Cumulative invoiced quantity 12.0000 exceeds PO quantity 10.0000 by 2.0000",
  },
  {
    id: "mr-mm-line-1-price",
    lineNumber: 1,
    matchLevel: "line",
    matchType: "two_way",
    dimension: "unit_price",
    expectedValue: "100.0000",
    actualValue: "115.0000",
    tolerancePercentApplied: 5.0,
    toleranceFloorApplied: 2.0,
    toleranceCapApplied: 250.0,
    result: "fail",
    notes: "Variance 15.0000 exceeds effective tolerance 5.0000",
  },
];
```

- [ ] **Step 2: Add MSW handlers for matching endpoints**

In `accounts-payable-invoice-handlers.ts`, add:

```typescript
import { mockMatchedResults, mockMismatchedResults } from "./invoice-matching-fixtures";

// After existing handlers:
http.post(
  "*/api/supplier-invoices/:id/run-matching",
  async ({ params, request }) => {
    const body = await request.json();
    const { lockVersion } = body as { lockVersion: number };

    const invoice = mockInvoices.find((inv) => inv.id === params.id);
    if (!invoice) {
      return HttpResponse.json({ error: { code: "not_found", message: "Invoice not found" } }, { status: 404 });
    }

    if (invoice.status !== "reviewed") {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Matching can only be run on reviewed invoices." } },
        { status: 409 },
      );
    }

    return HttpResponse.json({
      data: { ...invoice, matchingStatus: "mismatch", lockVersion: lockVersion + 1 },
    });
  },
),

http.get("*/api/supplier-invoices/:id/match-results", ({ params }) => {
  const invoice = mockInvoices.find((inv) => inv.id === params.id);
  if (!invoice) {
    return HttpResponse.json({ error: { code: "not_found", message: "Invoice not found" } }, { status: 404 });
  }

  const results = invoice.id === "invoice-4" ? mockMatchedResults : mockMismatchedResults;
  return HttpResponse.json({ data: results });
}),
```

- [ ] **Step 3: Commit**

```bash
git add apps/web/features/accounts-payable/mocks/
git commit -m "feat(web): add MSW fixtures and handlers for matching endpoints"
```

---

### Task 17: Seeder Updates — Add Matched, Mismatched, and Pending Demo Invoices

**Files:**
- Modify: `apps/api/database/seeders/Demo/DemoProcurementLifecycleSeeder.php`

- [ ] **Step 1: Add matching-related seeded invoices**

In `seedSupplierInvoices()`, after the existing 4 seeded invoices, add:

```php
// Matched invoice (two-way + three-way pass)
$this->seedMatchedInvoice($tenantId, $buyerUser, $issuedPo);

// Mismatch invoice (unit price exceeds tolerance)
$this->seedMismatchInvoice($tenantId, $buyerUser, $issuedPo);

// Pending matching invoice (reviewed but matching not run)
$this->seedPendingMatchingInvoice($tenantId, $buyerUser, $issuedPo);
```

- [ ] **Step 2: Create seeder helper methods**

```php
private function seedMatchedInvoice(string $tenantId, User $buyerUser, PurchaseOrder $po): void
{
    $invoice = $this->seedCapturedInvoice($tenantId, $buyerUser, $po);
    $invoice->forceFill([
        'matching_status' => SupplierInvoiceStatus::Matched->value,
    ])->save();

    // Create match results showing all pass
    foreach ($invoice->lines as $line) {
        SupplierInvoiceMatchResult::create([
            'tenant_id' => $tenantId,
            'supplier_invoice_id' => $invoice->id,
            'supplier_invoice_line_id' => $line->id,
            'purchase_order_line_id' => $line->purchase_order_line_id,
            'match_type' => 'two_way',
            'match_level' => 'line',
            'dimension' => 'quantity',
            'expected_value' => 10,
            'actual_value' => 10,
            'tolerance_percent_applied' => 0,
            'tolerance_floor_applied' => 0,
            'tolerance_cap_applied' => 0,
            'result' => 'pass',
        ]);
    }
}

private function seedMismatchInvoice(string $tenantId, User $buyerUser, PurchaseOrder $po): void
{
    $invoice = $this->seedCapturedInvoice($tenantId, $buyerUser, $po);
    $invoice->forceFill([
        'matching_status' => SupplierInvoiceStatus::Mismatch->value,
    ])->save();

    foreach ($invoice->lines as $line) {
        SupplierInvoiceMatchResult::create([
            'tenant_id' => $tenantId,
            'supplier_invoice_id' => $invoice->id,
            'supplier_invoice_line_id' => $line->id,
            'purchase_order_line_id' => $line->purchase_order_line_id,
            'match_type' => 'two_way',
            'match_level' => 'line',
            'dimension' => 'unit_price',
            'expected_value' => 100,
            'actual_value' => 120,
            'tolerance_percent_applied' => 5,
            'tolerance_floor_applied' => 2,
            'tolerance_cap_applied' => 250,
            'result' => 'fail',
            'notes' => 'Unit price variance exceeds tolerance',
        ]);
    }
}

private function seedPendingMatchingInvoice(string $tenantId, User $buyerUser, PurchaseOrder $po): void
{
    $invoice = $this->seedCapturedInvoice($tenantId, $buyerUser, $po);
    // matching_status stays null — pending
}
```

Add imports:
```php
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
```

- [ ] **Step 3: Run seeder to verify**

```bash
cd apps/api && php artisan db:seed --class=DemoProcurementLifecycleSeeder
```

- [ ] **Step 4: Commit**

```bash
git add apps/api/database/seeders/Demo/
git commit -m "feat(seeder): add matched, mismatched, and pending matching demo invoices"
```

---

### Task 18: Backend Tests

**Files:**
- Create: `apps/api/tests/Feature/InvoiceMatchingTest.php`

- [ ] **Step 1: Write full test suite**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\Models\GoodsReceiptLine;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceMatchingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private PurchaseOrder $po;
    private PurchaseOrderLine $poLine;
    private SupplierInvoice $invoice;
    private SupplierInvoiceLine $invoiceLine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant, ['role' => 'buyer']);
        $this->actingAs($this->user);

        // Create a PO with two-way policy
        $this->po = PurchaseOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'vendor_id' => Vendor::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'status' => PurchaseOrderStatus::Issued,
            'matching_policy' => 'three_way',
        ]);

        $this->poLine = PurchaseOrderLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'line_number' => 1,
            'quantity' => 10,
            'unit_price' => 100.0000,
            'subtotal_amount' => 1000.0000,
            'total_amount' => 1000.0000,
            'cumulative_quantity_invoiced' => 0,
        ]);

        // Create a reviewed invoice
        $this->invoice = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Reviewed,
            'total_amount' => 1000.0000,
            'lock_version' => 1,
        ]);

        $this->invoiceLine = SupplierInvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_invoice_id' => $this->invoice->id,
            'purchase_order_line_id' => $this->poLine->id,
            'line_number' => 1,
            'quantity_invoiced' => 10,
            'unit_price' => 100.0000,
            'line_subtotal' => 1000.0000,
        ]);
    }

    public function test_matching_auto_triggers_when_invoice_transitions_to_reviewed(): void
    {
        // Create a captured invoice and transition to reviewed
        $capturedInvoice = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Captured,
            'total_amount' => 1000.0000,
            'lock_version' => 1,
        ]);

        SupplierInvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_invoice_id' => $capturedInvoice->id,
            'purchase_order_line_id' => $this->poLine->id,
            'line_number' => 1,
            'quantity_invoiced' => 10,
            'unit_price' => 100.0000,
            'line_subtotal' => 1000.0000,
        ]);

        // Transition to reviewed via API
        $response = $this->postJson("/api/supplier-invoices/{$capturedInvoice->id}/complete-review", [
            'lockVersion' => 1,
            'checklist' => [
                'completeness' => ['status' => 'pass'],
                'coding' => ['status' => 'pass'],
                'attachment' => ['status' => 'pass'],
                'vendorIdentity' => ['status' => 'pass'],
                'poLinkage' => ['status' => 'pass'],
            ],
        ]);

        $response->assertStatus(200);
        $capturedInvoice->refresh();

        // Matching should have auto-run
        $this->assertNotNull($capturedInvoice->matching_status);
        $this->assertContains($capturedInvoice->matching_status, ['matched', 'mismatch']);
    }

    public function test_manual_matching_returns_updated_results(): void
    {
        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'matchingStatus',
                'lockVersion',
            ],
        ]);

        $this->invoice->refresh();
        $this->assertNotNull($this->invoice->matching_status);
    }

    public function test_matching_passes_when_all_dimensions_within_tolerance(): void
    {
        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(200);
        $this->invoice->refresh();
        $this->assertEquals('matched', $this->invoice->matching_status);
    }

    public function test_matching_fails_when_unit_price_exceeds_tolerance(): void
    {
        $this->invoiceLine->forceFill(['unit_price' => 200.0000, 'line_subtotal' => 2000.0000])->save();
        $this->invoice->forceFill(['total_amount' => 2000.0000, 'lock_version' => 2])->save();

        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 2,
        ]);

        $response->assertStatus(200);
        $this->invoice->refresh();
        $this->assertEquals('mismatch', $this->invoice->matching_status);
    }

    public function test_cumulative_over_billing_detected(): void
    {
        // First invoice: 6 units (within 10)
        $invoice1 = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Reviewed,
            'total_amount' => 600.0000,
            'lock_version' => 1,
        ]);

        SupplierInvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_invoice_id' => $invoice1->id,
            'purchase_order_line_id' => $this->poLine->id,
            'line_number' => 1,
            'quantity_invoiced' => 6,
            'unit_price' => 100.0000,
            'line_subtotal' => 600.0000,
        ]);

        $this->postJson("/api/supplier-invoices/{$invoice1->id}/run-matching", ['lockVersion' => 1])
            ->assertStatus(200);

        // Second invoice: 6 more units (cumulative 12 > 10)
        $invoice2 = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Reviewed,
            'total_amount' => 600.0000,
            'lock_version' => 1,
        ]);

        SupplierInvoiceLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'supplier_invoice_id' => $invoice2->id,
            'purchase_order_line_id' => $this->poLine->id,
            'line_number' => 1,
            'quantity_invoiced' => 6,
            'unit_price' => 100.0000,
            'line_subtotal' => 600.0000,
        ]);

        $response = $this->postJson("/api/supplier-invoices/{$invoice2->id}/run-matching", ['lockVersion' => 1]);
        $response->assertStatus(200);

        $invoice2->refresh();
        $this->assertEquals('mismatch', $invoice2->matching_status);
    }

    public function test_vendor_identity_mismatch_fails(): void
    {
        $differentVendor = Vendor::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->invoice->forceFill(['vendor_id' => $differentVendor->id, 'lock_version' => 2])->save();

        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 2,
        ]);

        $response->assertStatus(200);
        $this->invoice->refresh();
        $this->assertEquals('mismatch', $this->invoice->matching_status);
    }

    public function test_three_way_matching_fails_when_invoiced_exceeds_received(): void
    {
        // Record goods receipt for 5 units
        $receipt = GoodsReceipt::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'status' => GoodsReceiptStatus::Completed,
        ]);

        GoodsReceiptLine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'goods_receipt_id' => $receipt->id,
            'purchase_order_line_id' => $this->poLine->id,
            'line_number' => 1,
            'quantity_received' => 5,
            'quantity_accepted' => 5,
        ]);

        // Update PO line cumulative receipt
        $this->poLine->forceFill(['cumulative_quantity_accepted' => 5])->save();

        // Invoice for 10 units (exceeds 5 received)
        $this->invoice->forceFill(['lock_version' => 2])->save();

        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 2,
        ]);

        $response->assertStatus(200);
        $this->invoice->refresh();
        $this->assertEquals('mismatch', $this->invoice->matching_status);
    }

    public function test_two_way_policy_skips_three_way_matching(): void
    {
        $this->po->forceFill(['matching_policy' => 'two_way'])->save();
        $this->invoice->forceFill(['lock_version' => 2])->save();

        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 2,
        ]);

        $response->assertStatus(200);

        // Should have no three_way results
        $results = SupplierInvoiceMatchResult::where('supplier_invoice_id', $this->invoice->id)->get();
        $this->assertCount(0, $results->where('match_type', 'three_way'));
    }

    public function test_matching_on_non_reviewed_invoice_returns_conflict(): void
    {
        $capturedInvoice = SupplierInvoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Captured,
            'total_amount' => 1000.0000,
            'lock_version' => 1,
        ]);

        $response = $this->postJson("/api/supplier-invoices/{$capturedInvoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(409);
    }

    public function test_stale_lock_version_returns_conflict(): void
    {
        $response = $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 999,
        ]);

        $response->assertStatus(409);
    }

    public function test_cross_tenant_invoice_matching_is_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherInvoice = SupplierInvoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Reviewed,
            'lock_version' => 1,
        ]);

        $response = $this->postJson("/api/supplier-invoices/{$otherInvoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response->assertStatus(404);
    }

    public function test_match_results_list(): void
    {
        // Run matching first
        $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $response = $this->getJson("/api/supplier-invoices/{$this->invoice->id}/match-results");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'lineNumber', 'matchLevel', 'matchType', 'dimension',
                    'expectedValue', 'actualValue', 'result',
                ],
            ],
        ]);

        $results = $response->json('data');
        $this->assertNotEmpty($results);
    }

    public function test_match_results_tenant_scoped(): void
    {
        $this->postJson("/api/supplier-invoices/{$this->invoice->id}/run-matching", [
            'lockVersion' => 1,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherInvoice = SupplierInvoice::factory()->create([
            'tenant_id' => $otherTenant->id,
            'purchase_order_id' => $this->po->id,
            'vendor_id' => $this->po->vendor_id,
            'status' => SupplierInvoiceStatus::Reviewed,
            'lock_version' => 1,
        ]);

        $response = $this->getJson("/api/supplier-invoices/{$otherInvoice->id}/match-results");
        $response->assertStatus(404);
    }
}
```

- [ ] **Step 2: Run tests**

```bash
cd apps/api && php artisan test --filter=InvoiceMatching
```

- [ ] **Step 3: Commit**

```bash
git add apps/api/tests/Feature/InvoiceMatchingTest.php
git commit -m "test: add comprehensive matching test suite"
```

---

### Task 19: Frontend Tests

**Files:**
- Create: `apps/web/features/accounts-payable/tests/invoice-matching.test.tsx`

- [ ] **Step 1: Write frontend test for matching panel**

```tsx
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { setupServer } from "msw/node";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { InvoiceMatchResultsPanel } from "../components/invoice-match-results-panel";
import { mockMatchedResults, mockMismatchedResults } from "../mocks/invoice-matching-fixtures";

const server = setupServer(
  http.get("*/api/supplier-invoices/:id/match-results", () => {
    return HttpResponse.json({ data: mockMatchedResults });
  }),
  http.post("*/api/supplier-invoices/:id/run-matching", () => {
    return HttpResponse.json({ data: { matchingStatus: "matched", lockVersion: 2 } });
  }),
);

beforeAll(() => server.listen());
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

function renderWithQuery(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

describe("InvoiceMatchResultsPanel", () => {
  it("shows run matching button when status is pending", () => {
    renderWithQuery(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-1"
        lockVersion={1}
        invoiceStatus="reviewed"
        matchingStatus={null}
      />,
    );

    expect(screen.getByText("Run Matching")).toBeInTheDocument();
  });

  it("shows run matching button when status is mismatch", () => {
    renderWithQuery(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-1"
        lockVersion={1}
        invoiceStatus="reviewed"
        matchingStatus="mismatch"
      />,
    );

    expect(screen.getByText("Run Matching")).toBeInTheDocument();
  });

  it("hides run matching button when invoice is not reviewed", () => {
    renderWithQuery(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-1"
        lockVersion={1}
        invoiceStatus="captured"
        matchingStatus={null}
      />,
    );

    expect(screen.queryByText("Run Matching")).not.toBeInTheDocument();
  });

  it("displays match results after loading", async () => {
    renderWithQuery(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-1"
        lockVersion={1}
        invoiceStatus="reviewed"
        matchingStatus="matched"
      />,
    );

    await waitFor(() => {
      expect(screen.getByText("vendor_identity")).toBeInTheDocument();
    });

    expect(screen.getByText("invoice_total")).toBeInTheDocument();
  });

  it("shows mismatch rows in red", async () => {
    server.use(
      http.get("*/api/supplier-invoices/:id/match-results", () => {
        return HttpResponse.json({ data: mockMismatchedResults });
      }),
    );

    renderWithQuery(
      <InvoiceMatchResultsPanel
        invoiceId="invoice-2"
        lockVersion={1}
        invoiceStatus="reviewed"
        matchingStatus="mismatch"
      />,
    );

    await waitFor(() => {
      expect(screen.getByText("fail")).toBeInTheDocument();
    });
  });
});
```

- [ ] **Step 2: Run frontend tests**

```bash
pnpm --filter @cognify/web test -- invoice-matching
```

- [ ] **Step 3: Commit**

```bash
git add apps/web/features/accounts-payable/tests/invoice-matching.test.tsx
git commit -m "test(web): add matching panel frontend tests"
```

---

### Verification

Run all relevant verification commands:

```bash
cd apps/api && php artisan test --filter=InvoiceMatching
pnpm --filter @cognify/web test -- accounts-payable
pnpm --filter @cognify/web typecheck
pnpm lint
```

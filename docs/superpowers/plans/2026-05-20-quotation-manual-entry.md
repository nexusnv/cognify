# Quotation Manual Entry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Implement P1-27 so buyers and token-authenticated vendors can save structured quotation header terms and line items against the current quotation for an RFQ invitation.

**Architecture:** Extend the P1-26 quotation upload foundation instead of creating a parallel response model. Store header terms on `quotations`, store current structured lines in `quotation_line_items`, and keep all writes behind shared domain actions so tenant, invitation, actor, audit, and portal-token behavior stay consistent. This slice edits the current quotation capture only; revision history, comparison snapshots, normalization, and award decisions remain outside this plan.

**Tech Stack:** Laravel 12 API, Sanctum tenant middleware, OpenAPI 3.1, Orval generated TypeScript client, Next.js App Router, React Query, MSW, Vitest, shadcn/Radix primitives from `@cognify/ui`.

---

## Source Documents

- `docs/superpowers/specs/2026-05-20-quotation-manual-entry-design.md`
- `docs/superpowers/plans/2026-05-20-quotation-upload.md`
- `docs/superpowers/specs/2026-05-20-quotation-upload-design.md`
- `docs/superpowers/plans/2026-05-20-vendor-portal-baseline.md`
- `docs/01-product/feature-roadmap.md`

## Scope Boundaries

In scope:

- Buyer structured quotation entry from the sourcing RFQ invitation panel.
- Vendor structured quotation entry from the vendor portal quotation response section.
- Current quotation header terms, commercial notes, completeness summary, and quoted line items.
- Quoted line items linked to RFQ line items where possible, plus ad hoc quoted lines.
- Manual entry with or without uploaded quotation evidence.
- Field-level redaction so buyer notes are never returned to vendor portal responses.
- Audit events for manual entry saves, line item saves, and completeness transitions.
- OpenAPI/generated client updates and MSW-backed UI tests.

Out of scope:

- Quotation version history and immutable evaluation snapshots.
- Normalization, side-by-side comparison, scoring, recommendations, awards, purchase orders, OCR, extraction, currency conversion, and vendor account authentication.
- Deleting uploaded quotation evidence.
- Vendor editing of buyer notes or internal cleanup fields.

## File Map

Backend files to add:

- `apps/api/database/migrations/2026_05_20_030000_extend_quotations_for_manual_entry.php`
- `apps/api/database/migrations/2026_05_20_031000_create_quotation_line_items_table.php`
- `apps/api/Domains/Quotation/Models/QuotationLineItem.php`
- `apps/api/Domains/Quotation/Actions/SaveQuotationManualEntry.php`
- `apps/api/Domains/Quotation/Data/QuotationCompletenessData.php`
- `apps/api/Domains/Quotation/Http/Requests/SaveQuotationManualEntryRequest.php`
- `apps/api/Domains/Quotation/Http/Resources/QuotationLineItemResource.php`
- `apps/api/tests/Feature/QuotationManualEntryApiTest.php`

Backend files to modify:

- `apps/api/Domains/Quotation/Models/Quotation.php`
- `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`
- `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationQuotationController.php`
- `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationController.php`
- `apps/api/routes/api.php`
- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/endpoints.ts`
- `packages/api-client/src/generated/schemas/*`

Frontend files to add:

- `apps/web/features/sourcing/components/quotation-manual-entry-panel.tsx`
- `apps/web/features/sourcing/components/quotation-line-items-editor.tsx`
- `apps/web/features/sourcing/hooks/use-quotation-manual-entry.ts`
- `apps/web/features/sourcing/schemas/quotation-manual-entry-schema.ts`
- `apps/web/features/vendor-portal/components/vendor-quotation-manual-entry-panel.tsx`
- `apps/web/features/vendor-portal/components/vendor-quotation-line-items-editor.tsx`

Frontend files to modify:

- `apps/web/features/sourcing/api/quotation-api.ts`
- `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`
- `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`
- `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`
- `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`
- `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`

## API Shape

Manual entry request:

```json
{
  "quotationReference": "NW-Q-2026-041",
  "quotedAt": "2026-05-20",
  "validUntil": "2026-06-20",
  "currency": "USD",
  "subtotalAmount": "12000.00",
  "taxAmount": "720.00",
  "freightAmount": "250.00",
  "discountAmount": "500.00",
  "totalAmount": "12470.00",
  "paymentTerms": "Net 30",
  "deliveryTerms": "Delivered to site",
  "leadTimeDays": 21,
  "warrantyTerms": "3 years onsite",
  "exclusions": "Installation not included",
  "complianceNotes": "Meets requested hardware specification",
  "buyerNotes": "Confirmed by email with vendor",
  "vendorNotes": "Subject to stock availability",
  "lineItems": [
    {
      "rfqLineItemId": "rfq-line-1",
      "description": "Developer laptop",
      "quantity": "10.0000",
      "unit": "each",
      "unitPrice": "1200.00",
      "subtotalAmount": "12000.00",
      "taxAmount": "720.00",
      "totalAmount": "12720.00",
      "leadTimeDays": 21,
      "manufacturer": "Lenovo",
      "modelNumber": "ThinkPad T-series",
      "alternateOffered": false,
      "complianceStatus": "compliant",
      "notes": "Quoted as requested"
    }
  ]
}
```

Quotation response extension:

```json
{
  "data": {
    "id": "1",
    "number": "QUOTE-RFQ-2026-000001-1",
    "status": "received",
    "manualEntry": {
      "quotationReference": "NW-Q-2026-041",
      "quotedAt": "2026-05-20",
      "validUntil": "2026-06-20",
      "currency": "USD",
      "subtotalAmount": "12000.00",
      "taxAmount": "720.00",
      "freightAmount": "250.00",
      "discountAmount": "500.00",
      "totalAmount": "12470.00",
      "paymentTerms": "Net 30",
      "deliveryTerms": "Delivered to site",
      "leadTimeDays": 21,
      "warrantyTerms": "3 years onsite",
      "exclusions": "Installation not included",
      "complianceNotes": "Meets requested hardware specification",
      "buyerNotes": "Confirmed by email with vendor",
      "vendorNotes": "Subject to stock availability"
    },
    "lineItems": [
      {
        "id": "1",
        "rfqLineItemId": "rfq-line-1",
        "description": "Developer laptop",
        "quantity": "10.0000",
        "unit": "each",
        "unitPrice": "1200.00",
        "subtotalAmount": "12000.00",
        "taxAmount": "720.00",
        "totalAmount": "12720.00",
        "leadTimeDays": 21,
        "manufacturer": "Lenovo",
        "modelNumber": "ThinkPad T-series",
        "alternateOffered": false,
        "complianceStatus": "compliant",
        "notes": "Quoted as requested"
      }
    ],
    "completeness": {
      "isComplete": true,
      "missingFields": [],
      "lineItemCount": 1
    },
    "permissions": {
      "canUploadAttachment": true,
      "canViewAttachments": true,
      "canEditManualEntry": true
    }
  }
}
```

Vendor portal response must return `manualEntry.buyerNotes: null` and `permissions.canEditManualEntry` from token/invitation eligibility, not from Sanctum.

## Task 1: Backend Contract Tests First

**Files:**

- Create: `apps/api/tests/Feature/QuotationManualEntryApiTest.php`
- Reference: `apps/api/tests/Feature/QuotationUploadApiTest.php`
- Reference: `apps/api/tests/Feature/RfqInvitationPortalApiTest.php`

- [x] Add `QuotationManualEntryApiTest` with `RefreshDatabase` and helpers copied from `QuotationUploadApiTest` for tenant users, RFQ drafts, vendors, invitations, portal tokens, and quotation creation.

Use this test class skeleton:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationManualEntryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_save_structured_quotation_terms_and_line_items(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer, [
            'line_items' => [
                [
                    'id' => 'rfq-line-1',
                    'name' => 'Developer laptop',
                    'description' => 'Developer laptop',
                    'quantity' => '10.0000',
                    'unit' => 'each',
                    'estimated_unit_price' => '1100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);
        $vendor = $this->vendor($tenant, ['name' => 'Northwind Traders']);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload([
                'buyerNotes' => 'Buyer confirmed totals by email.',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $quotation->id)
            ->assertJsonPath('data.manualEntry.quotationReference', 'NW-Q-2026-041')
            ->assertJsonPath('data.manualEntry.buyerNotes', 'Buyer confirmed totals by email.')
            ->assertJsonPath('data.lineItems.0.rfqLineItemId', 'rfq-line-1')
            ->assertJsonPath('data.lineItems.0.description', 'Developer laptop')
            ->assertJsonPath('data.completeness.isComplete', true)
            ->assertJsonPath('data.completeness.lineItemCount', 1)
            ->assertJsonPath('data.permissions.canEditManualEntry', true);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'quotation_reference' => 'NW-Q-2026-041',
            'currency' => 'USD',
            'total_amount' => '12470.00',
            'buyer_notes' => 'Buyer confirmed totals by email.',
        ]);
        $this->assertDatabaseHas('quotation_line_items', [
            'quotation_id' => $quotation->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Developer laptop',
            'compliance_status' => 'compliant',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.manual_entry_saved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.line_items_saved',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.completeness_changed',
        ]);
    }
}
```

- [x] Add buyer test coverage for manual entry without uploaded files.

Use this test method:

```php
public function test_buyer_manual_entry_does_not_require_uploaded_files(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

    $this->actingAsTenant($tenant, $buyer)
        ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload())
        ->assertOk()
        ->assertJsonPath('data.fileCount', 0)
        ->assertJsonPath('data.manualEntry.totalAmount', '12470.00');
}
```

- [x] Add vendor portal save test coverage.

Use this test method:

```php
public function test_vendor_can_save_structured_quotation_terms_through_portal_token(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $token = $this->issuePortalToken($tenant, $buyer, $invitation);

    $payload = $this->validManualEntryPayload([
        'buyerNotes' => 'This must be ignored for vendor portal saves.',
        'vendorNotes' => 'Vendor entered this through the portal.',
    ]);

    $response = $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $payload);

    $response->assertOk()
        ->assertJsonPath('data.submissionSource', 'vendor_portal')
        ->assertJsonPath('data.manualEntry.vendorNotes', 'Vendor entered this through the portal.')
        ->assertJsonPath('data.manualEntry.buyerNotes', null)
        ->assertJsonPath('data.submittedByVendorContact.email', $invitation->contact_email);

    $quotationId = $response->json('data.id');
    $this->assertDatabaseHas('quotations', [
        'id' => $quotationId,
        'buyer_notes' => null,
        'vendor_notes' => 'Vendor entered this through the portal.',
    ]);
}
```

- [x] Add validation and authorization tests.

Use these test methods:

```php
public function test_vendor_cannot_save_buyer_only_fields(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $token = $this->issuePortalToken($tenant, $buyer, $invitation);

    $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $this->validManualEntryPayload([
        'buyerNotes' => 'Hidden internal buyer note',
    ]))->assertOk();

    $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
    $this->assertNull($quotation->buyer_notes);
}

public function test_terminal_invitation_cannot_save_vendor_manual_entry(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor, ['status' => RfqInvitationStatus::Declined->value]);
    $token = $this->issuePortalToken($tenant, $buyer, $invitation);

    $this->putJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/manual-entry", $this->validManualEntryPayload())
        ->assertConflict();
}

public function test_cross_tenant_buyer_cannot_save_manual_entry(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

    [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

    $this->actingAsTenant($otherTenant, $otherBuyer)
        ->putJson("/api/quotations/{$quotation->id}/manual-entry", $this->validManualEntryPayload())
        ->assertNotFound();
}

public function test_manual_entry_validation_returns_field_errors(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);
    $invitation = $this->invitation($tenant, $rfq, $vendor);
    $quotation = $this->quotation($tenant, $rfq, $vendor, $invitation);

    $payload = $this->validManualEntryPayload([
        'currency' => 'US',
        'totalAmount' => '-1.00',
        'leadTimeDays' => -5,
        'lineItems' => [
            [
                'description' => '',
                'quantity' => '0',
                'unitPrice' => '-10.00',
                'complianceStatus' => 'unknown',
            ],
        ],
    ]);

    $this->actingAsTenant($tenant, $buyer)
        ->putJson("/api/quotations/{$quotation->id}/manual-entry", $payload)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonValidationErrors([
            'currency',
            'totalAmount',
            'leadTimeDays',
            'lineItems.0.description',
            'lineItems.0.quantity',
            'lineItems.0.unitPrice',
            'lineItems.0.complianceStatus',
        ]);
}
```

- [x] Add the helper payload method to the test class.

Use this helper:

```php
/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
private function validManualEntryPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'quotationReference' => 'NW-Q-2026-041',
        'quotedAt' => '2026-05-20',
        'validUntil' => '2026-06-20',
        'currency' => 'USD',
        'subtotalAmount' => '12000.00',
        'taxAmount' => '720.00',
        'freightAmount' => '250.00',
        'discountAmount' => '500.00',
        'totalAmount' => '12470.00',
        'paymentTerms' => 'Net 30',
        'deliveryTerms' => 'Delivered to site',
        'leadTimeDays' => 21,
        'warrantyTerms' => '3 years onsite',
        'exclusions' => 'Installation not included',
        'complianceNotes' => 'Meets requested hardware specification',
        'buyerNotes' => null,
        'vendorNotes' => 'Subject to stock availability',
        'lineItems' => [
            [
                'rfqLineItemId' => 'rfq-line-1',
                'description' => 'Developer laptop',
                'quantity' => '10.0000',
                'unit' => 'each',
                'unitPrice' => '1200.00',
                'subtotalAmount' => '12000.00',
                'taxAmount' => '720.00',
                'totalAmount' => '12720.00',
                'leadTimeDays' => 21,
                'manufacturer' => 'Lenovo',
                'modelNumber' => 'ThinkPad T-series',
                'alternateOffered' => false,
                'complianceStatus' => 'compliant',
                'notes' => 'Quoted as requested',
            ],
        ],
    ], $overrides);
}
```

- [x] Run the backend test before implementation.

Run:

```bash
php artisan test --filter=QuotationManualEntryApiTest
```

Expected result: failures for missing routes, request class, tables, columns, model, action, and resource fields.

## Task 2: Backend Data Model

**Files:**

- Create: `apps/api/database/migrations/2026_05_20_030000_extend_quotations_for_manual_entry.php`
- Create: `apps/api/database/migrations/2026_05_20_031000_create_quotation_line_items_table.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationLineItem.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`

- [x] Add quotation header fields migration.

Use this migration body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->string('quotation_reference')->nullable()->after('number');
            $table->date('quoted_at')->nullable()->after('submitted_at');
            $table->date('valid_until')->nullable()->after('quoted_at');
            $table->decimal('subtotal_amount', 14, 2)->nullable()->after('currency');
            $table->decimal('tax_amount', 14, 2)->nullable()->after('subtotal_amount');
            $table->decimal('freight_amount', 14, 2)->nullable()->after('tax_amount');
            $table->decimal('discount_amount', 14, 2)->nullable()->after('freight_amount');
            $table->string('payment_terms')->nullable()->after('latest_received_at');
            $table->string('delivery_terms')->nullable()->after('payment_terms');
            $table->unsignedInteger('lead_time_days')->nullable()->after('delivery_terms');
            $table->text('warranty_terms')->nullable()->after('lead_time_days');
            $table->text('exclusions')->nullable()->after('warranty_terms');
            $table->text('compliance_notes')->nullable()->after('exclusions');
            $table->text('buyer_notes')->nullable()->after('compliance_notes');
            $table->text('vendor_notes')->nullable()->after('buyer_notes');
            $table->boolean('manual_entry_complete')->default(false)->after('vendor_notes');
            $table->json('manual_entry_missing_fields')->nullable()->after('manual_entry_complete');
            $table->timestamp('manual_entry_saved_at')->nullable()->after('manual_entry_missing_fields');
            $table->string('manual_entry_saved_source')->nullable()->after('manual_entry_saved_at');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropColumn([
                'quotation_reference',
                'quoted_at',
                'valid_until',
                'subtotal_amount',
                'tax_amount',
                'freight_amount',
                'discount_amount',
                'payment_terms',
                'delivery_terms',
                'lead_time_days',
                'warranty_terms',
                'exclusions',
                'compliance_notes',
                'buyer_notes',
                'vendor_notes',
                'manual_entry_complete',
                'manual_entry_missing_fields',
                'manual_entry_saved_at',
                'manual_entry_saved_source',
            ]);
        });
    }
};
```

- [x] Add quotation line items migration.

Use this migration body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->string('rfq_line_item_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 14, 4);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->boolean('alternate_offered')->default(false);
            $table->string('compliance_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'quotation_id'], 'quotation_line_items_tenant_quotation_index');
            $table->index(['tenant_id', 'rfq_line_item_id'], 'quotation_line_items_tenant_rfq_line_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_line_items');
    }
};
```

- [x] Add `QuotationLineItem` model.

Use this file:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationLineItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'rfq_line_item_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'lead_time_days',
        'manufacturer',
        'model_number',
        'alternate_offered',
        'compliance_status',
        'notes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'lead_time_days' => 'integer',
            'alternate_offered' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lineItem): void {
            DB::transaction(function () use ($lineItem): void {
                $belongsToTenant = Quotation::query()
                    ->whereKey($lineItem->quotation_id)
                    ->where('tenant_id', $lineItem->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Quotation line item must belong to a quotation in the same tenant.');
                }
            });
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
```

- [x] Extend `Quotation` fillable, casts, and relationships.

Add these fillable entries:

```php
'quotation_reference',
'quoted_at',
'valid_until',
'subtotal_amount',
'tax_amount',
'freight_amount',
'discount_amount',
'payment_terms',
'delivery_terms',
'lead_time_days',
'warranty_terms',
'exclusions',
'compliance_notes',
'buyer_notes',
'vendor_notes',
'manual_entry_complete',
'manual_entry_missing_fields',
'manual_entry_saved_at',
'manual_entry_saved_source',
```

Add these casts:

```php
'quoted_at' => 'immutable_date',
'valid_until' => 'immutable_date',
'subtotal_amount' => 'decimal:2',
'tax_amount' => 'decimal:2',
'freight_amount' => 'decimal:2',
'discount_amount' => 'decimal:2',
'lead_time_days' => 'integer',
'manual_entry_complete' => 'boolean',
'manual_entry_missing_fields' => 'array',
'manual_entry_saved_at' => 'immutable_datetime',
```

Add this relationship:

```php
/**
 * @return HasMany<QuotationLineItem, $this>
 */
public function lineItems(): HasMany
{
    return $this->hasMany(QuotationLineItem::class)->orderBy('position');
}
```

Add imports:

```php
use Domains\Quotation\Models\QuotationLineItem;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

- [x] Run the backend test again.

Run:

```bash
php artisan test --filter=QuotationManualEntryApiTest
```

Expected result: failures move from missing schema/model to missing request, action, routes, and resource fields.

## Task 3: Backend Request, Completeness, Action, Resources, Routes

**Files:**

- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationManualEntryRequest.php`
- Create: `apps/api/Domains/Quotation/Data/QuotationCompletenessData.php`
- Create: `apps/api/Domains/Quotation/Actions/SaveQuotationManualEntry.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationLineItemResource.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`
- Modify: `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationQuotationController.php`
- Modify: `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationController.php`
- Modify: `apps/api/routes/api.php`

- [x] Add request validation.

Use this file:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationManualEntryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quotationReference' => ['nullable', 'string', 'max:120'],
            'quotedAt' => ['nullable', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:quotedAt'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'taxAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'freightAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'discountAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'totalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'paymentTerms' => ['nullable', 'string', 'max:255'],
            'deliveryTerms' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'warrantyTerms' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'complianceNotes' => ['nullable', 'string', 'max:5000'],
            'buyerNotes' => ['nullable', 'string', 'max:5000'],
            'vendorNotes' => ['nullable', 'string', 'max:5000'],
            'lineItems' => ['present', 'array', 'max:200'],
            'lineItems.*.rfqLineItemId' => ['nullable', 'string', 'max:120'],
            'lineItems.*.description' => ['required', 'string', 'max:1000'],
            'lineItems.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'lineItems.*.unit' => ['nullable', 'string', 'max:80'],
            'lineItems.*.unitPrice' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.subtotalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.taxAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.totalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.leadTimeDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lineItems.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'lineItems.*.modelNumber' => ['nullable', 'string', 'max:255'],
            'lineItems.*.alternateOffered' => ['boolean'],
            'lineItems.*.complianceStatus' => ['nullable', Rule::in(['compliant', 'partial', 'non_compliant', 'alternate'])],
            'lineItems.*.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
```

- [x] Add completeness data object.

Use this file:

```php
<?php

namespace Domains\Quotation\Data;

class QuotationCompletenessData
{
    /**
     * @param  array<int, string>  $missingFields
     */
    public function __construct(
        public readonly bool $isComplete,
        public readonly array $missingFields,
        public readonly int $lineItemCount,
    ) {
    }

    /**
     * @return array{isComplete: bool, missingFields: array<int, string>, lineItemCount: int}
     */
    public function toArray(): array
    {
        return [
            'isComplete' => $this->isComplete,
            'missingFields' => $this->missingFields,
            'lineItemCount' => $this->lineItemCount,
        ];
    }
}
```

- [x] Add `SaveQuotationManualEntry` action.

Use this implementation:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Data\QuotationCompletenessData;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Illuminate\Support\Facades\DB;

class SaveQuotationManualEntry
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly CreateOrRevealQuotationForInvitation $createOrRevealQuotationForInvitation,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Tenant $tenant, ?User $actor, RfqInvitation $invitation, array $payload, QuotationSubmissionSource $source): Quotation
    {
        return DB::transaction(function () use ($tenant, $actor, $invitation, $payload, $source): Quotation {
            $result = $this->createOrRevealQuotationForInvitation->handle($tenant, $invitation, $actor);
            $quotation = $result['quotation'];
            $previousComplete = (bool) $quotation->manual_entry_complete;
            $lineItems = collect($payload['lineItems'] ?? [])->values();
            $completeness = $this->completeness($payload, $lineItems->count());

            $quotation->forceFill([
                'quotation_reference' => $payload['quotationReference'] ?? null,
                'status' => QuotationStatus::Received->value,
                'submission_source' => $quotation->submission_source ?? $source->value,
                'submitted_at' => $quotation->submitted_at ?? now(),
                'submitted_by_user_id' => $quotation->submitted_by_user_id ?? $actor?->id,
                'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact
                    ?? ($source === QuotationSubmissionSource::VendorPortal ? [
                        'name' => $invitation->contact_name,
                        'email' => $invitation->contact_email,
                    ] : null),
                'quoted_at' => $payload['quotedAt'] ?? null,
                'valid_until' => $payload['validUntil'] ?? null,
                'currency' => $payload['currency'] ?? null,
                'subtotal_amount' => $payload['subtotalAmount'] ?? null,
                'tax_amount' => $payload['taxAmount'] ?? null,
                'freight_amount' => $payload['freightAmount'] ?? null,
                'discount_amount' => $payload['discountAmount'] ?? null,
                'total_amount' => $payload['totalAmount'] ?? null,
                'payment_terms' => $payload['paymentTerms'] ?? null,
                'delivery_terms' => $payload['deliveryTerms'] ?? null,
                'lead_time_days' => $payload['leadTimeDays'] ?? null,
                'warranty_terms' => $payload['warrantyTerms'] ?? null,
                'exclusions' => $payload['exclusions'] ?? null,
                'compliance_notes' => $payload['complianceNotes'] ?? null,
                'buyer_notes' => $source === QuotationSubmissionSource::BuyerUpload ? ($payload['buyerNotes'] ?? null) : $quotation->buyer_notes,
                'vendor_notes' => $payload['vendorNotes'] ?? null,
                'manual_entry_complete' => $completeness->isComplete,
                'manual_entry_missing_fields' => $completeness->missingFields,
                'manual_entry_saved_at' => now(),
                'manual_entry_saved_source' => $source->value,
                'latest_received_at' => now(),
            ])->save();

            QuotationLineItem::query()
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $quotation->id)
                ->delete();

            $lineItems->each(function (array $lineItem, int $index) use ($tenant, $quotation): void {
                QuotationLineItem::query()->create([
                    'tenant_id' => $tenant->id,
                    'quotation_id' => $quotation->id,
                    'rfq_line_item_id' => $lineItem['rfqLineItemId'] ?? null,
                    'description' => $lineItem['description'],
                    'quantity' => $lineItem['quantity'],
                    'unit' => $lineItem['unit'] ?? null,
                    'unit_price' => $lineItem['unitPrice'] ?? null,
                    'subtotal_amount' => $lineItem['subtotalAmount'] ?? null,
                    'tax_amount' => $lineItem['taxAmount'] ?? null,
                    'total_amount' => $lineItem['totalAmount'] ?? null,
                    'lead_time_days' => $lineItem['leadTimeDays'] ?? null,
                    'manufacturer' => $lineItem['manufacturer'] ?? null,
                    'model_number' => $lineItem['modelNumber'] ?? null,
                    'alternate_offered' => $lineItem['alternateOffered'] ?? false,
                    'compliance_status' => $lineItem['complianceStatus'] ?? null,
                    'notes' => $lineItem['notes'] ?? null,
                    'position' => $index + 1,
                ]);
            });

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.manual_entry_saved',
                subject: $quotation,
                metadata: [
                    'source' => $source->value,
                    'rfqInvitationId' => (string) $invitation->id,
                    'rfqId' => (string) $invitation->rfq_id,
                    'vendorId' => (string) $invitation->vendor_id,
                    'changedFieldGroups' => ['header', 'commercial_terms', 'notes'],
                ],
                subjectDisplay: $quotation->number,
            ));

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.line_items_saved',
                subject: $quotation,
                metadata: [
                    'source' => $source->value,
                    'lineItemCount' => $lineItems->count(),
                ],
                subjectDisplay: $quotation->number,
            ));

            if ($previousComplete !== $completeness->isComplete) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation.completeness_changed',
                    subject: $quotation,
                    metadata: $completeness->toArray() + ['source' => $source->value],
                    subjectDisplay: $quotation->number,
                ));
            }

            return $quotation->refresh()->load([
                'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
                'lineItems',
                'submittedByUser',
                'rfq',
                'vendor',
                'rfqInvitation',
            ]);
        });
    }

    private function completeness(array $payload, int $lineItemCount): QuotationCompletenessData
    {
        $missing = [];

        foreach (['currency', 'totalAmount'] as $field) {
            if (blank($payload[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        if ($lineItemCount === 0) {
            $missing[] = 'lineItems';
        }

        return new QuotationCompletenessData($missing === [], $missing, $lineItemCount);
    }
}
```

- [x] Add `QuotationLineItemResource`.

Use this file:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationLineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationLineItem
 */
class QuotationLineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'rfqLineItemId' => $this->rfq_line_item_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->unit_price,
            'subtotalAmount' => $this->subtotal_amount,
            'taxAmount' => $this->tax_amount,
            'totalAmount' => $this->total_amount,
            'leadTimeDays' => $this->lead_time_days,
            'manufacturer' => $this->manufacturer,
            'modelNumber' => $this->model_number,
            'alternateOffered' => $this->alternate_offered,
            'complianceStatus' => $this->compliance_status,
            'notes' => $this->notes,
        ];
    }
}
```

- [x] Extend `QuotationResource`.

Add `lineItems`, `manualEntry`, `completeness`, and `canEditManualEntry`:

```php
$vendorPortal = (bool) $request->attributes->get('vendor_portal', false);
$redactInternalIdentity = $vendorPortal || ! $request->user();
$canEditManualEntry = $vendorPortal
    ? (bool) $request->attributes->get('vendor_portal_can_edit_quotation', false)
    : ($request->user()?->can('view', $quotation->rfq) ?? false);

'manualEntry' => [
    'quotationReference' => $quotation->quotation_reference,
    'quotedAt' => $quotation->quoted_at?->toDateString(),
    'validUntil' => $quotation->valid_until?->toDateString(),
    'currency' => $quotation->currency,
    'subtotalAmount' => $quotation->subtotal_amount,
    'taxAmount' => $quotation->tax_amount,
    'freightAmount' => $quotation->freight_amount,
    'discountAmount' => $quotation->discount_amount,
    'totalAmount' => $quotation->total_amount,
    'paymentTerms' => $quotation->payment_terms,
    'deliveryTerms' => $quotation->delivery_terms,
    'leadTimeDays' => $quotation->lead_time_days,
    'warrantyTerms' => $quotation->warranty_terms,
    'exclusions' => $quotation->exclusions,
    'complianceNotes' => $quotation->compliance_notes,
    'buyerNotes' => $vendorPortal ? null : $quotation->buyer_notes,
    'vendorNotes' => $quotation->vendor_notes,
],
'lineItems' => $quotation->relationLoaded('lineItems')
    ? QuotationLineItemResource::collection($quotation->lineItems)
    : [],
'completeness' => [
    'isComplete' => (bool) $quotation->manual_entry_complete,
    'missingFields' => $quotation->manual_entry_missing_fields ?? [],
    'lineItemCount' => $quotation->relationLoaded('lineItems') ? $quotation->lineItems->count() : 0,
],
'permissions' => [
    'canUploadAttachment' => $request->user()?->can('view', $quotation->rfq) ?? false,
    'canViewAttachments' => $request->user()?->can('view', $quotation->rfq) ?? false,
    'canEditManualEntry' => $canEditManualEntry,
],
```

- [x] Add authenticated buyer route handler to `RfqInvitationQuotationController`.

Add this method:

```php
public function saveManualEntry(
    SaveQuotationManualEntryRequest $request,
    CurrentTenant $currentTenant,
    int $quotation,
    SaveQuotationManualEntry $saveQuotationManualEntry,
): QuotationResource {
    $tenant = $this->tenantOrAbort($currentTenant);
    $model = $this->findTenantQuotation($tenant, $quotation);
    $model->loadMissing(['rfqInvitation', 'rfq', 'vendor']);
    $this->authorize('view', $model->rfq);
    $this->ensureInvitationAcceptsQuotation($model->rfqInvitation);

    return new QuotationResource($saveQuotationManualEntry->handle(
        $tenant,
        $request->user(),
        $model->rfqInvitation,
        $request->validated(),
        QuotationSubmissionSource::BuyerUpload,
    ));
}
```

Add imports:

```php
use Domains\Quotation\Actions\SaveQuotationManualEntry;
use Domains\Quotation\Http\Requests\SaveQuotationManualEntryRequest;
```

Update `findTenantQuotation()` eager loading:

```php
->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems'])
```

- [x] Add vendor portal route handler to `VendorPortalQuotationController`.

Add this method:

```php
public function saveManualEntry(
    string $token,
    SaveQuotationManualEntryRequest $request,
    ResolveRfqInvitationPortalAccess $resolve,
    SaveQuotationManualEntry $saveQuotationManualEntry,
): QuotationResource {
    $request->attributes->set('vendor_portal', true);
    $request->attributes->set('vendor_portal_can_edit_quotation', true);
    $invitation = $resolve->handle($token, $request);

    return new QuotationResource($saveQuotationManualEntry->handle(
        $invitation->tenant,
        null,
        $invitation,
        $request->validated(),
        QuotationSubmissionSource::VendorPortal,
    ));
}
```

Add imports:

```php
use Domains\Quotation\Actions\SaveQuotationManualEntry;
use Domains\Quotation\Http\Requests\SaveQuotationManualEntryRequest;
```

Update `findTenantQuotationByInvitation()` eager loading:

```php
->with(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'lineItems', 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation'])
```

- [x] Register routes in `apps/api/routes/api.php`.

Add vendor portal route near existing vendor quotation routes:

```php
Route::put('/vendor-portal/rfq-invitations/{token}/quotation/manual-entry', [VendorPortalQuotationController::class, 'saveManualEntry'])
    ->middleware('throttle:vendor-portal');
```

Add authenticated route inside the tenant group:

```php
Route::put('/quotations/{quotation}/manual-entry', [RfqInvitationQuotationController::class, 'saveManualEntry']);
```

- [x] Run backend focused tests.

Run:

```bash
php artisan test --filter=QuotationManualEntryApiTest
php artisan test --filter=QuotationUploadApiTest
php artisan test --filter=SearchApiTest
```

Expected result: manual entry tests pass, upload tests still pass, and search still serializes quotation status as a string.

## Task 4: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Regenerate: `packages/api-client/src/generated/endpoints.ts`
- Regenerate: `packages/api-client/src/generated/schemas/*`

- [x] Add OpenAPI operations:

```text
PUT /api/quotations/{quotation}/manual-entry
PUT /api/vendor-portal/rfq-invitations/{token}/quotation/manual-entry
```

- [x] Add request schema `SaveQuotationManualEntryRequest`.

Use these required properties:

```json
{
  "type": "object",
  "required": ["lineItems"],
  "properties": {
    "quotationReference": { "type": ["string", "null"], "maxLength": 120 },
    "quotedAt": { "type": ["string", "null"], "format": "date" },
    "validUntil": { "type": ["string", "null"], "format": "date" },
    "currency": { "type": ["string", "null"], "minLength": 3, "maxLength": 3 },
    "subtotalAmount": { "type": ["string", "null"] },
    "taxAmount": { "type": ["string", "null"] },
    "freightAmount": { "type": ["string", "null"] },
    "discountAmount": { "type": ["string", "null"] },
    "totalAmount": { "type": ["string", "null"] },
    "paymentTerms": { "type": ["string", "null"] },
    "deliveryTerms": { "type": ["string", "null"] },
    "leadTimeDays": { "type": ["integer", "null"], "minimum": 0 },
    "warrantyTerms": { "type": ["string", "null"] },
    "exclusions": { "type": ["string", "null"] },
    "complianceNotes": { "type": ["string", "null"] },
    "buyerNotes": { "type": ["string", "null"] },
    "vendorNotes": { "type": ["string", "null"] },
    "lineItems": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/SaveQuotationLineItemRequest" }
    }
  }
}
```

- [x] Add response schemas:

```text
QuotationManualEntry
QuotationLineItem
QuotationCompleteness
SaveQuotationManualEntryRequest
SaveQuotationLineItemRequest
```

- [x] Extend `Quotation` schema with:

```text
manualEntry
lineItems
completeness
permissions.canEditManualEntry
```

- [x] Generate the client and verify contract output.

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected result: generated endpoint helpers include:

```text
saveQuotationManualEntry
saveVendorPortalQuotationManualEntry
```

## Task 5: Buyer API, Hooks, MSW, And UI

**Files:**

- Modify: `apps/web/features/sourcing/api/quotation-api.ts`
- Add: `apps/web/features/sourcing/hooks/use-quotation-manual-entry.ts`
- Add: `apps/web/features/sourcing/schemas/quotation-manual-entry-schema.ts`
- Add: `apps/web/features/sourcing/components/quotation-line-items-editor.tsx`
- Add: `apps/web/features/sourcing/components/quotation-manual-entry-panel.tsx`
- Modify: `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`
- Modify: `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- Modify: `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`

- [x] Add buyer API client function.

Append to `apps/web/features/sourcing/api/quotation-api.ts`:

```ts
import {
  saveQuotationManualEntry as saveQuotationManualEntryEndpoint,
} from "@cognify/api-client/endpoints";
import type { Quotation, SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";

export async function saveQuotationManualEntry(
  quotationId: string,
  payload: SaveQuotationManualEntryRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Quotation> {
  const response = await saveQuotationManualEntryEndpoint(quotationId, payload, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}
```

- [x] Add buyer manual entry hook.

Use this file:

```ts
"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type { SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";
import { saveQuotationManualEntry } from "../api/quotation-api";
import { quotationKeys } from "./use-quotation-upload";

export function useSaveQuotationManualEntry(invitationId: string, quotationId: string | null | undefined) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SaveQuotationManualEntryRequest) => {
      if (!quotationId) throw new Error("Quotation must exist before saving manual entry.");
      return saveQuotationManualEntry(quotationId, payload, tenantId);
    },
    onSuccess: (quotation) => {
      queryClient.setQueryData(quotationKeys.byInvitation(invitationId, tenantId), quotation);
      queryClient.setQueryData(quotationKeys.attachments(quotation.id, tenantId), quotation.attachments);
    },
  });
}
```

- [x] Add form schema and mapping helpers.

Use this file:

```ts
import { z } from "zod";
import type { Quotation, SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";

export const quotationLineItemSchema = z.object({
  rfqLineItemId: z.string().optional().nullable(),
  description: z.string().min(1, "Description is required."),
  quantity: z.string().min(1, "Quantity is required."),
  unit: z.string().optional().nullable(),
  unitPrice: z.string().optional().nullable(),
  subtotalAmount: z.string().optional().nullable(),
  taxAmount: z.string().optional().nullable(),
  totalAmount: z.string().optional().nullable(),
  leadTimeDays: z.coerce.number().int().min(0).optional().nullable(),
  manufacturer: z.string().optional().nullable(),
  modelNumber: z.string().optional().nullable(),
  alternateOffered: z.boolean().default(false),
  complianceStatus: z.enum(["compliant", "partial", "non_compliant", "alternate"]).optional().nullable(),
  notes: z.string().optional().nullable(),
});

export const quotationManualEntrySchema = z.object({
  quotationReference: z.string().optional().nullable(),
  quotedAt: z.string().optional().nullable(),
  validUntil: z.string().optional().nullable(),
  currency: z.string().length(3, "Currency must use a 3-letter code.").optional().nullable(),
  subtotalAmount: z.string().optional().nullable(),
  taxAmount: z.string().optional().nullable(),
  freightAmount: z.string().optional().nullable(),
  discountAmount: z.string().optional().nullable(),
  totalAmount: z.string().optional().nullable(),
  paymentTerms: z.string().optional().nullable(),
  deliveryTerms: z.string().optional().nullable(),
  leadTimeDays: z.coerce.number().int().min(0).optional().nullable(),
  warrantyTerms: z.string().optional().nullable(),
  exclusions: z.string().optional().nullable(),
  complianceNotes: z.string().optional().nullable(),
  buyerNotes: z.string().optional().nullable(),
  vendorNotes: z.string().optional().nullable(),
  lineItems: z.array(quotationLineItemSchema),
});

export type QuotationManualEntryFormValues = z.infer<typeof quotationManualEntrySchema>;

export function formValuesFromQuotation(quotation: Quotation | null): QuotationManualEntryFormValues {
  return {
    quotationReference: quotation?.manualEntry?.quotationReference ?? "",
    quotedAt: quotation?.manualEntry?.quotedAt ?? "",
    validUntil: quotation?.manualEntry?.validUntil ?? "",
    currency: quotation?.manualEntry?.currency ?? "USD",
    subtotalAmount: quotation?.manualEntry?.subtotalAmount ?? "",
    taxAmount: quotation?.manualEntry?.taxAmount ?? "",
    freightAmount: quotation?.manualEntry?.freightAmount ?? "",
    discountAmount: quotation?.manualEntry?.discountAmount ?? "",
    totalAmount: quotation?.manualEntry?.totalAmount ?? "",
    paymentTerms: quotation?.manualEntry?.paymentTerms ?? "",
    deliveryTerms: quotation?.manualEntry?.deliveryTerms ?? "",
    leadTimeDays: quotation?.manualEntry?.leadTimeDays ?? null,
    warrantyTerms: quotation?.manualEntry?.warrantyTerms ?? "",
    exclusions: quotation?.manualEntry?.exclusions ?? "",
    complianceNotes: quotation?.manualEntry?.complianceNotes ?? "",
    buyerNotes: quotation?.manualEntry?.buyerNotes ?? "",
    vendorNotes: quotation?.manualEntry?.vendorNotes ?? "",
    lineItems: quotation?.lineItems?.length ? quotation.lineItems.map((line) => ({
      rfqLineItemId: line.rfqLineItemId,
      description: line.description,
      quantity: line.quantity,
      unit: line.unit,
      unitPrice: line.unitPrice,
      subtotalAmount: line.subtotalAmount,
      taxAmount: line.taxAmount,
      totalAmount: line.totalAmount,
      leadTimeDays: line.leadTimeDays,
      manufacturer: line.manufacturer,
      modelNumber: line.modelNumber,
      alternateOffered: line.alternateOffered,
      complianceStatus: line.complianceStatus,
      notes: line.notes,
    })) : [],
  };
}

export function payloadFromFormValues(values: QuotationManualEntryFormValues): SaveQuotationManualEntryRequest {
  return values;
}
```

- [x] Add buyer line items editor.

Use a compact table with labeled inputs for `description`, `quantity`, `unit`, `unitPrice`, `totalAmount`, `complianceStatus`, and `notes`. The component props must be:

```ts
export function QuotationLineItemsEditor({
  lineItems,
  onChange,
  disabled,
}: {
  lineItems: QuotationManualEntryFormValues["lineItems"];
  onChange: (lineItems: QuotationManualEntryFormValues["lineItems"]) => void;
  disabled?: boolean;
}) {
  // Render "Add quoted line" button, "Remove line" buttons, and controlled inputs.
}
```

- [x] Add buyer manual entry panel.

Use this component contract:

```ts
export function QuotationManualEntryPanel({
  invitationId,
  invitationStatus,
  quotation,
}: {
  invitationId: string;
  invitationStatus: string;
  quotation: Quotation | null;
}) {
  // Show "Structured quotation entry".
  // Show completeness: "Ready for evaluation" or "Incomplete quotation data".
  // Save through useSaveQuotationManualEntry(invitationId, quotation?.id).
  // Disable save when invitation status is not sent or acknowledged.
}
```

The panel must render these labels for tests:

```text
Structured quotation entry
Quotation reference
Currency
Total amount
Buyer notes
Vendor notes
Add quoted line
Save structured quotation
```

- [x] Render manual entry panel from `QuotationEvidencePanel`.

Pass the loaded `quotation` to `QuotationManualEntryPanel` after the evidence section. If `quotation` is `null`, show this copy:

```text
Upload a quotation file or create structured quotation data to start the response record.
```

Add a button:

```text
Create structured quotation
```

The button should call a minimal manual-entry save with `lineItems: []`, `currency: "USD"`, and then show the full panel from the returned quotation.

- [x] Extend sourcing MSW handlers.

Add `PUT /api/quotations/:quotationId/manual-entry` to `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`. It should update the in-memory quotation:

```ts
http.put("/api/quotations/:quotationId/manual-entry", async ({ params, request }) => {
  const quotation = findQuotationById(String(params.quotationId));
  if (!quotation) return notFound();

  const payload = await request.json() as SaveQuotationManualEntryRequest;
  const updated = updateQuotationManualEntry(quotation, payload, "buyer_upload");
  quotationByInvitationId.set(updated.rfqInvitationId, updated);

  return HttpResponse.json({ data: structuredClone(updated) });
});
```

The mock `updateQuotationManualEntry` must update `manualEntry`, `lineItems`, `completeness`, `status: "received"`, `submissionSource`, `submittedAt`, and `latestReceivedAt`.

- [x] Add buyer workflow test.

Append this test to `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`:

```ts
it("lets a buyer save structured quotation terms and line items", async () => {
  const user = userEvent.setup();

  render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

  const invitationCard = (await screen.findByText("Northwind Traders")).closest(
    "[data-testid='rfq-invitation-card']",
  );
  expect(invitationCard).toBeTruthy();
  const card = within(invitationCard as HTMLElement);

  await user.click(card.getByRole("button", { name: "Create structured quotation" }));
  await user.clear(await card.findByLabelText("Quotation reference"));
  await user.type(card.getByLabelText("Quotation reference"), "NW-Q-2026-041");
  await user.clear(card.getByLabelText("Currency"));
  await user.type(card.getByLabelText("Currency"), "USD");
  await user.clear(card.getByLabelText("Total amount"));
  await user.type(card.getByLabelText("Total amount"), "12470.00");
  await user.type(card.getByLabelText("Buyer notes"), "Buyer confirmed totals by email.");
  await user.click(card.getByRole("button", { name: "Add quoted line" }));
  await user.type(card.getByLabelText("Line 1 description"), "Developer laptop");
  await user.type(card.getByLabelText("Line 1 quantity"), "10");
  await user.type(card.getByLabelText("Line 1 unit price"), "1200.00");
  await user.click(card.getByRole("button", { name: "Save structured quotation" }));

  expect(await card.findByText("Structured quotation saved.")).toBeInTheDocument();
  expect(card.getByText("Ready for evaluation")).toBeInTheDocument();
  expect(card.getByDisplayValue("NW-Q-2026-041")).toBeInTheDocument();
});
```

- [x] Run buyer frontend test.

Run:

```bash
pnpm --dir apps/web exec vitest run features/sourcing/tests/rfq-invitations-workflow.test.tsx --reporter=dot
```

Expected result: all sourcing RFQ invitation workflow tests pass.

## Task 6: Vendor Portal API, MSW, And UI

**Files:**

- Modify: `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- Modify: `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`
- Add: `apps/web/features/vendor-portal/components/vendor-quotation-manual-entry-panel.tsx`
- Add: `apps/web/features/vendor-portal/components/vendor-quotation-line-items-editor.tsx`
- Modify: `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- Modify: `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`

- [x] Add vendor portal API client function.

Append to `apps/web/features/vendor-portal/api/vendor-portal-api.ts`:

```ts
import {
  saveVendorPortalQuotationManualEntry as saveVendorPortalQuotationManualEntryEndpoint,
} from "@cognify/api-client/endpoints";
import type { Quotation, SaveQuotationManualEntryRequest } from "@cognify/api-client/schemas";

export async function saveVendorPortalQuotationManualEntry(
  token: string,
  payload: SaveQuotationManualEntryRequest,
): Promise<Quotation> {
  const response = await saveVendorPortalQuotationManualEntryEndpoint(token, payload);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}
```

- [x] Extend vendor quotation hook.

Add this mutation to `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`:

```ts
export function useVendorQuotationManualEntry(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SaveQuotationManualEntryRequest) => saveVendorPortalQuotationManualEntry(token, payload),
    onSuccess: (quotation) => {
      queryClient.setQueryData(vendorPortalKeys.quotation(token), quotation);
    },
  });
}
```

Add imports for `SaveQuotationManualEntryRequest` and `saveVendorPortalQuotationManualEntry`.

- [x] Add vendor manual entry panel.

Use this component contract:

```ts
export function VendorQuotationManualEntryPanel({
  token,
  quotation,
}: {
  token: string;
  quotation: Quotation | null;
}) {
  // Render vendor-editable fields only.
  // Do not render buyer notes.
  // Use "Vendor notes" for vendor-facing notes.
  // Save through useVendorQuotationManualEntry(token).
}
```

The panel must render these labels:

```text
Structured quotation response
Quotation reference
Currency
Total amount
Vendor notes
Add quoted line
Save quotation details
```

- [x] Render vendor manual entry panel inside `VendorQuotationUploadPanel`.

Place the structured response panel below the uploaded files section. Use the same `quotation` object returned by `useVendorQuotation(token)` so file evidence and structured details share one response summary.

- [x] Extend vendor portal MSW fixtures and handlers.

Add `manualEntry`, `lineItems`, `completeness`, and `permissions.canEditManualEntry` to vendor quotation fixture. Add this handler:

```ts
http.put("/api/vendor-portal/rfq-invitations/:token/quotation/manual-entry", async ({ request, params }) => {
  const token = String(params.token);

  if (token !== validVendorPortalToken) {
    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }

  const payload = await request.json() as SaveQuotationManualEntryRequest;
  const quotation = updateVendorPortalQuotationManualEntry(payload);

  return HttpResponse.json({ data: structuredClone(quotation) });
});
```

The mock updater must force `manualEntry.buyerNotes` to `null`.

- [x] Add vendor portal workflow test.

Append this test to `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`:

```ts
it("lets a vendor save structured quotation details from the RFQ portal", async () => {
  const user = userEvent.setup();
  render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

  expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
  await user.clear(screen.getByLabelText("Quotation reference"));
  await user.type(screen.getByLabelText("Quotation reference"), "NW-Q-2026-041");
  await user.clear(screen.getByLabelText("Currency"));
  await user.type(screen.getByLabelText("Currency"), "USD");
  await user.clear(screen.getByLabelText("Total amount"));
  await user.type(screen.getByLabelText("Total amount"), "12470.00");
  await user.type(screen.getByLabelText("Vendor notes"), "Subject to stock availability.");
  await user.click(screen.getByRole("button", { name: "Add quoted line" }));
  await user.type(screen.getByLabelText("Line 1 description"), "Developer laptop");
  await user.type(screen.getByLabelText("Line 1 quantity"), "10");
  await user.click(screen.getByRole("button", { name: "Save quotation details" }));

  expect(await screen.findByText("Quotation details saved.")).toBeInTheDocument();
  expect(screen.getByText("Ready for evaluation")).toBeInTheDocument();
  expect(screen.queryByLabelText("Buyer notes")).not.toBeInTheDocument();
});
```

- [x] Run vendor portal frontend test.

Run:

```bash
pnpm --dir apps/web exec vitest run features/vendor-portal/tests/vendor-rfq-portal.test.tsx --reporter=dot
```

Expected result: all vendor RFQ portal tests pass.

## Task 7: Integration Verification

**Files:**

- Verify all touched API, generated-client, and web surfaces.

- [x] Run focused backend tests.

Run:

```bash
php artisan test --filter=QuotationManualEntryApiTest
php artisan test --filter=QuotationUploadApiTest
php artisan test --filter=RfqInvitationPortalApiTest
php artisan test --filter=SearchApiTest
```

Expected result: all listed tests pass.

- [x] Run focused frontend tests.

Run:

```bash
pnpm --dir apps/web exec vitest run features/sourcing/tests/rfq-invitations-workflow.test.tsx --reporter=dot
pnpm --dir apps/web exec vitest run features/vendor-portal/tests/vendor-rfq-portal.test.tsx --reporter=dot
```

Expected result: sourcing and vendor portal suites pass.

- [x] Run contract, type, lint, build, and whitespace checks.

Run:

```bash
pnpm check:api-contract
pnpm --filter @cognify/web typecheck
pnpm lint
pnpm build
git diff --check
```

Expected result: all commands exit successfully. If `pnpm build` fails in the sandbox with a Turbopack process or port permission error, rerun the same command with required sandbox escalation and record both outputs.

- [x] Run placeholder scan.

Run:

```bash
terms='T''ODO|T''BD|implement[ ]later|Similar[ ]to|edge[ ]cases|as[ ]appropriate'
rg -n "$terms" apps/api apps/web packages docs/superpowers/plans/2026-05-20-quotation-manual-entry.md
```

Expected result: no matches introduced by this implementation.

## Task 8: Roadmap Loopback

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-20-quotation-manual-entry.md`

- [x] Confirm P1-27 links to this implementation plan.

Expected P1-27 row values:

```markdown
| P1-27 | Quotation Manual Entry | Support structured quotation entry when a vendor responds outside the portal or submits incomplete documents. Manual entry keeps the workflow usable before full OCR automation exists. | Fully Implemented | 2026-05-20-quotation-manual-entry-design.md | 2026-05-20-quotation-manual-entry.md |  | Implemented as Epic 6 slice 3 with buyer and vendor structured quotation entry. |
```

- [x] Leave P1-28 as `Planned`.

Do not update P1-28 implementation plan or status during this slice.

- [x] Mark all completed plan checkboxes after verification passes.

Use only `- [x]` for tasks actually completed and verified in the current implementation session.

## Task 9: Self-Review Checklist

- [x] Manual entry saves reuse the P1-26 quotation record created by `CreateOrRevealQuotationForInvitation`.
- [x] Buyer and vendor saves use the same `SaveQuotationManualEntry` action.
- [x] Buyer routes require `auth:sanctum` and `ResolveCurrentTenant`.
- [x] Vendor routes use portal token resolution and do not rely on `X-Tenant-Id`.
- [x] Vendor portal responses never expose buyer notes or buyer identity.
- [x] Buyer saves do not erase uploaded attachments.
- [x] Line item replacement happens in one transaction with the header save.
- [x] Completeness is explicit and does not trigger comparison behavior.
- [x] Audit events include `quotation.manual_entry_saved`, `quotation.line_items_saved`, and `quotation.completeness_changed`.
- [x] Generated client endpoints and schemas are used by frontend code.
- [x] UI components do not import mock fixtures directly.
- [x] Quotation versioning, normalization, comparison, and award flows remain outside this slice.

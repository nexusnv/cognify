# Quotation Comparison Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-30 as an RFQ-level buyer comparison workspace that reads approved quotation normalization revisions and persists comparison notes with no scoring, award, RFQ, quotation, or normalization side effects.

**Architecture:** Keep durable comparison note state and comparison read-model assembly inside `apps/api/Domains/Quotation`. Expose the comparison through OpenAPI-generated endpoints consumed by `apps/web/features/quotations`, with `RecordWorkspaceLayout`, TanStack Query hooks, MSW-shaped fixtures, and shadcn/Radix primitives. The comparison matrix is computed from approved normalization data on read; only notes are persisted.

**Tech Stack:** Laravel 12, Eloquent, Sanctum session auth, tenant-scoped policies/actions/resources, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix via `packages/ui`.

---

## Grounding

- Design spec: `docs/superpowers/specs/2026-05-22-quotation-comparison-table-design.md`.
- Roadmap: `docs/01-product/feature-roadmap.md` feature `P1-30`.
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md` Epic 7 slice 2.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, backend-owned workflow behavior, OpenAPI/generated clients, and feature UI in `apps/web`.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices.
- Existing implementation context: P1-29 normalization already owns approved normalization revisions under `apps/api/Domains/Quotation` and `apps/web/features/quotations`.

## Scope Boundaries

Implement:

- RFQ-level comparison read endpoint.
- Soft-deletable buyer/admin comparison notes.
- Buyer/admin-only comparison page at `/quotations/comparisons/[rfqId]`.
- Link from RFQ workspace to comparison.
- OpenAPI/client/schema alignment.
- Tests for tenant, permission, readiness, bundle, mixed currency, notes, audit, and no workflow side effects.

Do not implement:

- Scoring, ranking, shortlist, exclusion, recommendation, award, award approval, PO handoff, AI narrative, OCR, extraction, currency conversion, export, or standalone comparison queue.
- Any mutation to RFQ, quotation, or normalization state from comparison note actions.

## File Map

### API

- Create: `apps/api/database/migrations/2026_05_22_000000_create_quotation_comparison_notes_table.php`
- Create: `apps/api/Domains/Quotation/States/QuotationComparisonNoteSection.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Policies/QuotationComparisonNotePolicy.php`
- Create: `apps/api/Domains/Quotation/Actions/BuildQuotationComparison.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Actions/DeleteQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationComparisonNoteRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonNoteResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationComparisonController.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/storage/openapi/openapi.json`
- Test: `apps/api/tests/Feature/QuotationComparisonApiTest.php`
- Test: `apps/api/tests/Unit/QuotationComparisonBuilderTest.php`

### Generated Client

- Modify generated files under `packages/api-client/src/generated/**` via `pnpm generate:api` and `pnpm check:api-contract`.

Expected generated endpoint names from OpenAPI operation IDs:

- `showQuotationComparison`
- `createQuotationComparisonNote`
- `updateQuotationComparisonNote`
- `deleteQuotationComparisonNote`

Expected generated schemas:

- `QuotationComparisonResponse`
- `QuotationComparisonRfq`
- `QuotationComparisonReadiness`
- `QuotationComparisonVendor`
- `QuotationComparisonLineRow`
- `QuotationComparisonVendorCell`
- `QuotationComparisonCommercialTerm`
- `QuotationComparisonNote`
- `QuotationComparisonPermissions`
- `SaveQuotationComparisonNoteRequest`

### Web

- Create: `apps/web/app/(workspace)/quotations/comparisons/[rfqId]/page.tsx`
- Create: `apps/web/features/quotations/api/quotation-comparison-api.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-comparison.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-comparison-notes.ts`
- Create: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-readiness-banner.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-vendor-summary.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-table.tsx`
- Create: `apps/web/features/quotations/components/quotation-commercial-terms-table.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-notes-panel.tsx`
- Create: `apps/web/features/quotations/mocks/quotation-comparison-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-comparison-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-comparison-api.test.ts`
- Create: `apps/web/features/quotations/tests/quotation-comparison-workspace.test.tsx`
- Modify: `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`
- Modify MSW test setup if handlers are centrally registered.

### Docs

- Modify: `docs/01-product/feature-roadmap.md`
- Modify this plan during execution by checking completed boxes.

---

## Task 1: API Regression Tests For Comparison Boundaries

**Files:**

- Create: `apps/api/tests/Feature/QuotationComparisonApiTest.php`

- [x] **Step 1: Write failing feature tests**

Create `apps/api/tests/Feature/QuotationComparisonApiTest.php` with focused tests before production code exists. Reuse helper patterns from `QuotationNormalizationApiTest`.

```php
<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationComparisonApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_view_rfq_comparison_from_approved_normalizations(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Acme Supply', 'USD', '12500.00');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.rfq.id', (string) $rfq->id)
            ->assertJsonPath('data.readiness.responseCount', 1)
            ->assertJsonPath('data.readiness.approvedNormalizationCount', 1)
            ->assertJsonPath('data.readiness.mixedCurrency', false)
            ->assertJsonPath('data.vendors.0.vendorName', 'Acme Supply')
            ->assertJsonPath('data.vendors.0.totalAmount', '12500.00')
            ->assertJsonPath('data.permissions.canManageComparisonNotes', true);
    }

    public function test_requester_approver_and_cross_tenant_users_cannot_view_comparison(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        [, $requester] = $this->tenantUser('requester', $tenant);
        [, $approver] = $this->tenantUser('approver', $tenant);
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($tenant, $requester)->getJson("/api/rfqs/{$rfq->id}/comparison")->assertForbidden();
        $this->actingAsTenant($tenant, $approver)->getJson("/api/rfqs/{$rfq->id}/comparison")->assertForbidden();
        $this->actingAsTenant($otherTenant, $otherBuyer)->getJson("/api/rfqs/{$rfq->id}/comparison")->assertNotFound();
    }

    public function test_comparison_marks_missing_approved_normalization_without_falling_back_to_raw_fields(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithQuotation($tenant, $buyer, 'Raw Vendor', 'USD', '9999.99', false);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.readiness.responseCount', 1)
            ->assertJsonPath('data.readiness.approvedNormalizationCount', 0)
            ->assertJsonPath('data.vendors.0.readiness', 'normalization_required')
            ->assertJsonPath('data.vendors.0.totalAmount', null)
            ->assertJsonPath('data.commercialTerms.0.vendorValues.0.value', null);
    }

    public function test_comparison_flags_mixed_currencies_and_preserves_bundle_pricing(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Acme Supply', 'USD', '12500.00', 'bundle');
        $this->addApprovedQuotation($tenant, $buyer, $rfq, 'Beta Trading', 'MYR', '57000.00', 'per_line');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/comparison")
            ->assertOk()
            ->assertJsonPath('data.readiness.mixedCurrency', true)
            ->assertJsonPath('data.lineRows.0.vendorCells.0.pricingMode', 'bundle')
            ->assertJsonPath('data.lineRows.0.vendorCells.0.bundleTotalAmount', '12500.00');
    }

    public function test_buyer_can_create_update_and_soft_delete_comparison_notes_with_audit(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);

        $create = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'overall',
                'note' => 'Acme is cheaper but delivery is longer.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.section', 'overall')
            ->assertJsonPath('data.note', 'Acme is cheaper but delivery is longer.');

        $noteId = $create->json('data.id');

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfqs/{$rfq->id}/comparison/notes/{$noteId}", [
                'section' => 'delivery',
                'note' => 'Delivery risk needs buyer follow-up.',
            ])
            ->assertOk()
            ->assertJsonPath('data.section', 'delivery');

        $this->actingAsTenant($tenant, $buyer)
            ->deleteJson("/api/rfqs/{$rfq->id}/comparison/notes/{$noteId}")
            ->assertNoContent();

        $this->assertSoftDeleted('quotation_comparison_notes', ['id' => $noteId, 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->id, 'event_type' => 'quotation_comparison.note_created']);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->id, 'event_type' => 'quotation_comparison.note_updated']);
        $this->assertDatabaseHas('audit_events', ['tenant_id' => $tenant->id, 'event_type' => 'quotation_comparison.note_deleted']);
    }

    public function test_note_targets_must_belong_to_same_rfq_and_tenant(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $otherRfq = $this->rfqWithApprovedQuotation($tenant, $buyer, 'Other Vendor');
        $otherQuotation = Quotation::query()->where('rfq_id', $otherRfq->id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'price',
                'quotationId' => (string) $otherQuotation->id,
                'note' => 'Cross-RFQ target should fail.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quotationId']);
    }

    public function test_note_actions_do_not_change_rfq_quotation_or_normalization_state(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $rfq = $this->rfqWithApprovedQuotation($tenant, $buyer);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $normalization = QuotationNormalization::query()->where('quotation_id', $quotation->id)->firstOrFail();

        $originalRfqStatus = $rfq->status;
        $originalQuotationStatus = $quotation->status;
        $originalNormalizationStatus = $normalization->status;

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/comparison/notes", [
                'section' => 'overall',
                'note' => 'Non-decision annotation.',
            ])
            ->assertCreated();

        $this->assertSame($originalRfqStatus, $rfq->refresh()->status);
        $this->assertSame($originalQuotationStatus, $quotation->refresh()->status);
        $this->assertSame($originalNormalizationStatus->value, $normalization->refresh()->status->value);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);
        app(\App\Tenancy\CurrentTenant::class)->set($tenant, $user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $user = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($user->id, ['role' => TenantRole::from($role)->value]);

        return [$tenant, $user];
    }

    private function rfqWithApprovedQuotation(Tenant $tenant, User $buyer, string $vendorName = 'Acme Supply', string $currency = 'USD', string $total = '12500.00', string $pricingMode = 'per_line'): Rfq
    {
        return $this->rfqWithQuotation($tenant, $buyer, $vendorName, $currency, $total, true, $pricingMode);
    }

    private function rfqWithQuotation(Tenant $tenant, User $buyer, string $vendorName, string $currency, string $total, bool $approved, string $pricingMode = 'per_line'): Rfq
    {
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::upper(Str::random(6)),
            'title' => 'Laptop refresh',
            'status' => RfqStatus::Draft->value,
            'response_due_at' => now()->addDays(7),
            'scope_summary' => 'Purchase laptops',
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Laptop',
                'description' => 'Business laptop',
                'quantity' => '10',
                'unit_of_measure' => 'each',
                'currency' => $currency,
            ]],
        ]);

        $this->addQuotation($tenant, $buyer, $rfq, $vendorName, $currency, $total, $approved, $pricingMode);

        return $rfq;
    }

    private function addApprovedQuotation(Tenant $tenant, User $buyer, Rfq $rfq, string $vendorName, string $currency, string $total, string $pricingMode): void
    {
        $this->addQuotation($tenant, $buyer, $rfq, $vendorName, $currency, $total, true, $pricingMode);
    }

    private function addQuotation(Tenant $tenant, User $buyer, Rfq $rfq, string $vendorName, string $currency, string $total, bool $approved, string $pricingMode): void
    {
        $vendor = Vendor::query()->create(['tenant_id' => $tenant->id, 'name' => $vendorName, 'status' => 'active']);
        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Responded->value,
            'contact_email' => Str::slug($vendorName).'@example.com',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => 'submitted',
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'manual_entry_complete' => true,
        ]);
        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'source' => 'buyer_manual_entry',
            'status' => 'submitted',
            'currency' => $currency,
            'total_amount' => $total,
            'lead_time_days' => 14,
            'payment_terms' => 'Net 30',
            'delivery_terms' => 'DAP',
            'warranty_terms' => '12 months',
            'compliance_notes' => 'Compliant',
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);
        $quotation->forceFill(['current_version_id' => $version->id])->save();

        if (! $approved) {
            return;
        }

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => QuotationNormalizationStatus::Approved->value,
            'is_current_for_version' => true,
            'approved_at' => now(),
            'approved_by_user_id' => $buyer->id,
            'algorithm_version' => 'deterministic-v1',
        ]);
        $normalization->fields()->createMany([
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.currency', 'normalized_value' => $currency, 'data_type' => 'currency', 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.totalAmount', 'normalized_value' => $total, 'data_type' => 'money', 'currency' => $currency, 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.leadTimeDays', 'normalized_value' => '14', 'data_type' => 'integer', 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.paymentTerms', 'normalized_value' => 'Net 30', 'data_type' => 'text', 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.deliveryTerms', 'normalized_value' => 'DAP', 'data_type' => 'text', 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.warrantyTerms', 'normalized_value' => '12 months', 'data_type' => 'text', 'source' => 'manual_entry'],
            ['tenant_id' => $tenant->id, 'field_path' => 'manualEntry.complianceNotes', 'normalized_value' => 'Compliant', 'data_type' => 'text', 'source' => 'manual_entry'],
        ]);
        $lineGroup = $normalization->lineGroups()->create([
            'tenant_id' => $tenant->id,
            'group_number' => 1,
            'pricing_mode' => $pricingMode,
            'description' => 'Laptop',
            'currency' => $currency,
            'bundle_total_amount' => $pricingMode === 'bundle' ? $total : null,
        ]);
        $lineGroup->mappings()->create([
            'tenant_id' => $tenant->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'mapping_type' => $pricingMode === 'bundle' ? 'bundled' : 'full',
            'quantity' => '10',
            'unit' => 'each',
            'line_total' => $pricingMode === 'bundle' ? null : $total,
        ]);
    }
}
```

- [x] **Step 2: Run tests to verify they fail**

Run:

```bash
php artisan test --filter=QuotationComparisonApiTest
```

Expected: fails because comparison routes, model, actions, and resources do not exist.

- [x] **Step 3: Commit failing tests**

```bash
git add apps/api/tests/Feature/QuotationComparisonApiTest.php
git commit -m "test: cover quotation comparison API boundaries"
```

---

## Task 2: Comparison Note Persistence And Policies

**Files:**

- Create: `apps/api/database/migrations/2026_05_22_000000_create_quotation_comparison_notes_table.php`
- Create: `apps/api/Domains/Quotation/States/QuotationComparisonNoteSection.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Policies/QuotationComparisonNotePolicy.php`
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`

- [x] **Step 1: Create note section enum**

Create `apps/api/Domains/Quotation/States/QuotationComparisonNoteSection.php`:

```php
<?php

namespace Domains\Quotation\States;

enum QuotationComparisonNoteSection: string
{
    case Overall = 'overall';
    case Price = 'price';
    case Delivery = 'delivery';
    case Terms = 'terms';
    case Compliance = 'compliance';
    case Risk = 'risk';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $section): string => $section->value, self::cases());
    }
}
```

- [x] **Step 2: Create migration**

Create `apps/api/database/migrations/2026_05_22_000000_create_quotation_comparison_notes_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quotation_comparison_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->string('rfq_line_item_id')->nullable();
            $table->string('section', 32);
            $table->text('note');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'rfq_id', 'section']);
            $table->index(['tenant_id', 'quotation_id']);
            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['tenant_id', 'rfq_line_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_comparison_notes');
    }
};
```

- [x] **Step 3: Create model with tenant invariants**

Create `apps/api/Domains/Quotation/Models/QuotationComparisonNote.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationComparisonNoteSection;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class QuotationComparisonNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'quotation_id',
        'vendor_id',
        'rfq_line_item_id',
        'section',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'section' => QuotationComparisonNoteSection::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $note): void {
            $rfq = Rfq::query()->whereKey($note->rfq_id)->first();
            if ($rfq === null) {
                throw new InvalidArgumentException('Comparison note RFQ is required.');
            }

            $note->tenant_id ??= $rfq->tenant_id;

            if ((int) $note->tenant_id !== (int) $rfq->tenant_id) {
                throw new InvalidArgumentException('Comparison note RFQ must belong to the same tenant.');
            }

            if ($note->quotation_id !== null) {
                $quotation = Quotation::query()->whereKey($note->quotation_id)->first();
                if ($quotation === null || (int) $quotation->tenant_id !== (int) $note->tenant_id || (int) $quotation->rfq_id !== (int) $note->rfq_id) {
                    throw new InvalidArgumentException('Comparison note quotation must belong to the same RFQ and tenant.');
                }
            }

            if ($note->vendor_id !== null) {
                $vendorExists = Vendor::query()
                    ->whereKey($note->vendor_id)
                    ->where('tenant_id', $note->tenant_id)
                    ->exists();
                if (! $vendorExists) {
                    throw new InvalidArgumentException('Comparison note vendor must belong to the same tenant.');
                }
            }

            if ($note->rfq_line_item_id !== null) {
                $lineItemIds = collect($rfq->line_items ?? [])->map(fn ($lineItem) => (string) data_get($lineItem, 'id'))->filter();
                if (! $lineItemIds->contains((string) $note->rfq_line_item_id)) {
                    throw new InvalidArgumentException('Comparison note line item must belong to the RFQ.');
                }
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Rfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /** @return BelongsTo<Quotation, $this> */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
```

- [x] **Step 4: Add relationships**

Modify `apps/api/Domains/Quotation/Models/Rfq.php`:

```php
/**
 * @return HasMany<QuotationComparisonNote, $this>
 */
public function comparisonNotes(): HasMany
{
    return $this->hasMany(QuotationComparisonNote::class);
}
```

Modify `apps/api/Domains/Quotation/Models/Quotation.php`:

```php
/**
 * @return HasMany<QuotationComparisonNote, $this>
 */
public function comparisonNotes(): HasMany
{
    return $this->hasMany(QuotationComparisonNote::class);
}
```

- [x] **Step 5: Add policy**

Create `apps/api/Domains/Quotation/Policies/QuotationComparisonNotePolicy.php`:

```php
<?php

namespace Domains\Quotation\Policies;

use App\Models\User;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class QuotationComparisonNotePolicy
{
    public function create(User $user, Rfq $rfq): bool
    {
        return $user->can('view', $rfq);
    }

    public function update(User $user, QuotationComparisonNote $note): bool
    {
        return $note->relationLoaded('rfq')
            ? $user->can('view', $note->rfq)
            : $user->can('view', Rfq::query()->findOrFail($note->rfq_id));
    }

    public function delete(User $user, QuotationComparisonNote $note): bool
    {
        return $this->update($user, $note);
    }
}
```

- [x] **Step 6: Run migration and test command**

Run:

```bash
php artisan test --filter=QuotationComparisonApiTest
```

Expected: still fails because routes/actions/resources are not implemented, but migration/model errors are gone.

- [x] **Step 7: Commit persistence layer**

```bash
git add apps/api/database/migrations/2026_05_22_000000_create_quotation_comparison_notes_table.php apps/api/Domains/Quotation/States/QuotationComparisonNoteSection.php apps/api/Domains/Quotation/Models/QuotationComparisonNote.php apps/api/Domains/Quotation/Policies/QuotationComparisonNotePolicy.php apps/api/Domains/Quotation/Models/Rfq.php apps/api/Domains/Quotation/Models/Quotation.php
git commit -m "feat: add quotation comparison notes model"
```

---

## Task 3: Comparison Read Model, Note Actions, Resources, Controller

**Files:**

- Create: `apps/api/Domains/Quotation/Actions/BuildQuotationComparison.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Actions/DeleteQuotationComparisonNote.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/SaveQuotationComparisonNoteRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonNoteResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationComparisonController.php`
- Modify: `apps/api/routes/api.php`

- [x] **Step 1: Create request validation**

Create `apps/api/Domains/Quotation/Http/Requests/SaveQuotationComparisonNoteRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\QuotationComparisonNoteSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuotationComparisonNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'section' => ['required', 'string', Rule::in(QuotationComparisonNoteSection::values())],
            'note' => ['required', 'string', 'max:2000'],
            'quotationId' => ['nullable', 'integer'],
            'vendorId' => ['nullable', 'integer'],
            'rfqLineItemId' => ['nullable', 'string', 'max:120'],
        ];
    }
}
```

- [x] **Step 2: Create note resource**

Create `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonNoteResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationComparisonNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuotationComparisonNote */
class QuotationComparisonNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'rfqId' => (string) $this->rfq_id,
            'quotationId' => $this->quotation_id !== null ? (string) $this->quotation_id : null,
            'vendorId' => $this->vendor_id !== null ? (string) $this->vendor_id : null,
            'rfqLineItemId' => $this->rfq_line_item_id,
            'section' => $this->section?->value ?? $this->section,
            'note' => $this->note,
            'createdByUserId' => (string) $this->created_by_user_id,
            'updatedByUserId' => $this->updated_by_user_id !== null ? (string) $this->updated_by_user_id : null,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
```

- [x] **Step 3: Create read-model builder**

Create `apps/api/Domains/Quotation/Actions/BuildQuotationComparison.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Tenancy\Tenant;
use Domains\Quotation\Http\Resources\QuotationComparisonNoteResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Support\Collection;

class BuildQuotationComparison
{
    public function handle(Tenant $tenant, Rfq $rfq): array
    {
        $rfq->loadMissing(['requisition.requester', 'project', 'invitations.vendor']);

        $quotations = Quotation::query()
            ->with([
                'vendor',
                'currentVersion',
                'currentVersion.currentNormalization.fields',
                'currentVersion.currentNormalization.lineGroups.mappings',
                'currentVersion.currentNormalization.issues',
            ])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->orderBy('vendor_id')
            ->get();

        $notes = $rfq->comparisonNotes()
            ->with('createdBy')
            ->where('tenant_id', $tenant->id)
            ->latest('updated_at')
            ->get();

        $vendors = $quotations->map(fn (Quotation $quotation): array => $this->vendorColumn($quotation, $notes))->values();
        $currencies = $vendors->pluck('currency')->filter()->unique()->values();

        return [
            'rfq' => $this->rfqSummary($rfq),
            'readiness' => [
                'responseCount' => $quotations->count(),
                'approvedNormalizationCount' => $vendors->where('readiness', 'ready')->count(),
                'pendingNormalizationCount' => $vendors->where('readiness', 'normalization_required')->count(),
                'missingResponseCount' => max(0, $rfq->invitations->count() - $quotations->count()),
                'mixedCurrency' => $currencies->count() > 1,
            ],
            'vendors' => $vendors->all(),
            'lineRows' => $this->lineRows($rfq, $quotations),
            'commercialTerms' => $this->commercialTerms($quotations),
            'notes' => QuotationComparisonNoteResource::collection($notes)->resolve(),
            'permissions' => [
                'canViewComparison' => true,
                'canManageComparisonNotes' => true,
            ],
        ];
    }

    private function rfqSummary(Rfq $rfq): array
    {
        return [
            'id' => (string) $rfq->id,
            'number' => $rfq->number,
            'title' => $rfq->title,
            'status' => $rfq->status,
            'responseDueAt' => $rfq->response_due_at?->toISOString(),
            'scopeSummary' => $rfq->scope_summary,
            'requisition' => $rfq->requisition ? [
                'id' => (string) $rfq->requisition->id,
                'number' => $rfq->requisition->number,
                'title' => $rfq->requisition->title,
            ] : null,
            'project' => $rfq->project ? [
                'id' => (string) $rfq->project->id,
                'number' => $rfq->project->number,
                'name' => $rfq->project->name,
            ] : null,
        ];
    }

    private function vendorColumn(Quotation $quotation, Collection $notes): array
    {
        $normalization = $this->approvedNormalization($quotation);
        $fields = $normalization?->fields?->keyBy('field_path') ?? collect();

        return [
            'vendorId' => (string) $quotation->vendor_id,
            'vendorName' => $quotation->vendor?->name ?? 'Unknown vendor',
            'quotationId' => (string) $quotation->id,
            'quotationNumber' => $quotation->number,
            'quotationVersionId' => $quotation->current_version_id !== null ? (string) $quotation->current_version_id : null,
            'normalizationId' => $normalization !== null ? (string) $normalization->id : null,
            'normalizationRevision' => $normalization?->normalization_revision,
            'readiness' => $normalization === null ? 'normalization_required' : 'ready',
            'currency' => $this->field($fields, 'manualEntry.currency'),
            'totalAmount' => $this->field($fields, 'manualEntry.totalAmount'),
            'leadTimeDays' => $this->field($fields, 'manualEntry.leadTimeDays'),
            'paymentTerms' => $this->field($fields, 'manualEntry.paymentTerms'),
            'deliveryTerms' => $this->field($fields, 'manualEntry.deliveryTerms'),
            'warrantyTerms' => $this->field($fields, 'manualEntry.warrantyTerms'),
            'complianceNotes' => $this->field($fields, 'manualEntry.complianceNotes'),
            'issueCounts' => $this->issueCounts($normalization),
            'noteCount' => $notes->where('vendor_id', $quotation->vendor_id)->count() + $notes->where('quotation_id', $quotation->id)->count(),
            'links' => [
                'quotationVersion' => $quotation->current_version_id !== null ? "/quotations/{$quotation->id}/versions/{$quotation->current_version_id}" : null,
                'normalization' => $normalization !== null ? "/quotations/normalizations/{$normalization->id}" : null,
            ],
        ];
    }

    private function lineRows(Rfq $rfq, Collection $quotations): array
    {
        return collect($rfq->line_items ?? [])->map(function (array $lineItem) use ($quotations): array {
            $lineItemId = (string) data_get($lineItem, 'id');

            return [
                'rfqLineItemId' => $lineItemId,
                'name' => data_get($lineItem, 'name'),
                'description' => data_get($lineItem, 'description') ?? data_get($lineItem, 'name'),
                'quantity' => data_get($lineItem, 'quantity'),
                'unit' => data_get($lineItem, 'unit_of_measure') ?? data_get($lineItem, 'unit'),
                'vendorCells' => $quotations->map(fn (Quotation $quotation): array => $this->vendorCell($quotation, $lineItemId))->values()->all(),
            ];
        })->values()->all();
    }

    private function vendorCell(Quotation $quotation, string $lineItemId): array
    {
        $normalization = $this->approvedNormalization($quotation);
        if ($normalization === null) {
            return ['vendorId' => (string) $quotation->vendor_id, 'readiness' => 'normalization_required', 'value' => null];
        }

        $mapping = $normalization->lineGroups
            ->flatMap(fn ($group) => $group->mappings->map(fn ($mapping) => [$group, $mapping]))
            ->first(fn ($pair) => (string) $pair[1]->rfq_line_item_id === $lineItemId);

        if ($mapping === null) {
            return ['vendorId' => (string) $quotation->vendor_id, 'readiness' => 'unmapped', 'value' => null];
        }

        [$group, $lineMapping] = $mapping;

        return [
            'vendorId' => (string) $quotation->vendor_id,
            'readiness' => 'ready',
            'description' => $group->description,
            'pricingMode' => $group->pricing_mode?->value ?? $group->pricing_mode,
            'currency' => $group->currency,
            'quantity' => $lineMapping->quantity,
            'unit' => $lineMapping->unit,
            'unitPrice' => $lineMapping->unit_price,
            'lineTotal' => $lineMapping->line_total,
            'bundleTotalAmount' => $group->bundle_total_amount,
            'buyerNote' => $lineMapping->buyer_note,
        ];
    }

    private function commercialTerms(Collection $quotations): array
    {
        $terms = [
            ['id' => 'totalAmount', 'label' => 'Total', 'fieldPath' => 'manualEntry.totalAmount'],
            ['id' => 'leadTimeDays', 'label' => 'Lead time', 'fieldPath' => 'manualEntry.leadTimeDays'],
            ['id' => 'paymentTerms', 'label' => 'Payment terms', 'fieldPath' => 'manualEntry.paymentTerms'],
            ['id' => 'deliveryTerms', 'label' => 'Delivery terms', 'fieldPath' => 'manualEntry.deliveryTerms'],
            ['id' => 'warrantyTerms', 'label' => 'Warranty', 'fieldPath' => 'manualEntry.warrantyTerms'],
            ['id' => 'complianceNotes', 'label' => 'Compliance notes', 'fieldPath' => 'manualEntry.complianceNotes'],
        ];

        return collect($terms)->map(fn (array $term): array => [
            'id' => $term['id'],
            'label' => $term['label'],
            'vendorValues' => $quotations->map(function (Quotation $quotation) use ($term): array {
                $normalization = $this->approvedNormalization($quotation);
                $fields = $normalization?->fields?->keyBy('field_path') ?? collect();

                return [
                    'vendorId' => (string) $quotation->vendor_id,
                    'value' => $normalization === null ? null : $this->field($fields, $term['fieldPath']),
                    'readiness' => $normalization === null ? 'normalization_required' : 'ready',
                ];
            })->values()->all(),
        ])->all();
    }

    private function approvedNormalization(Quotation $quotation): ?QuotationNormalization
    {
        $normalization = $quotation->currentVersion?->currentNormalization;

        if ($normalization?->status === QuotationNormalizationStatus::Approved || $normalization?->status === QuotationNormalizationStatus::ApprovedWithWarnings) {
            return $normalization;
        }

        return null;
    }

    private function field(Collection $fields, string $path): ?string
    {
        return $fields->get($path)?->normalized_value;
    }

    private function issueCounts(?QuotationNormalization $normalization): array
    {
        $issues = $normalization?->issues ?? collect();

        return [
            'blocking' => $issues->where('severity', 'blocking')->count(),
            'warning' => $issues->where('severity', 'warning')->count(),
            'info' => $issues->where('severity', 'info')->count(),
        ];
    }
}
```

- [x] **Step 4: Create note actions with audit**

Create `CreateQuotationComparisonNote`, `UpdateQuotationComparisonNote`, and `DeleteQuotationComparisonNote` actions. Use this shape:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class CreateQuotationComparisonNote
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): QuotationComparisonNote
    {
        $note = QuotationComparisonNote::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $data['quotationId'] ?? null,
            'vendor_id' => $data['vendorId'] ?? null,
            'rfq_line_item_id' => $data['rfqLineItemId'] ?? null,
            'section' => $data['section'],
            'note' => trim($data['note']),
            'created_by_user_id' => $actor->id,
        ]);

        $this->audit->record(
            tenant: $tenant,
            actor: $actor,
            eventType: 'quotation_comparison.note_created',
            action: 'quotation_comparison.note_created',
            subject: $rfq,
            metadata: ['noteId' => (string) $note->id, 'section' => $note->section?->value ?? $note->section],
            subjectDisplay: $rfq->number,
        );

        return $note->refresh()->load('createdBy');
    }
}
```

For update, capture `before` and `after` in audit metadata. For delete, set `deleted_by_user_id`, save, then soft delete and audit `quotation_comparison.note_deleted`.

- [x] **Step 5: Create response resource**

Create `apps/api/Domains/Quotation/Http/Resources/QuotationComparisonResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationComparisonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
```

- [x] **Step 6: Create controller**

Create `apps/api/Domains/Quotation/Http/Controllers/QuotationComparisonController.php`:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\BuildQuotationComparison;
use Domains\Quotation\Actions\CreateQuotationComparisonNote;
use Domains\Quotation\Actions\DeleteQuotationComparisonNote;
use Domains\Quotation\Actions\UpdateQuotationComparisonNote;
use Domains\Quotation\Http\Requests\SaveQuotationComparisonNoteRequest;
use Domains\Quotation\Http\Resources\QuotationComparisonNoteResource;
use Domains\Quotation\Http\Resources\QuotationComparisonResource;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;
use Illuminate\Http\JsonResponse;

class QuotationComparisonController extends Controller
{
    public function show(CurrentTenant $currentTenant, int $rfq, BuildQuotationComparison $action): QuotationComparisonResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('view', $model);

        return new QuotationComparisonResource($action->handle($tenant, $model));
    }

    public function storeNote(SaveQuotationComparisonNoteRequest $request, CurrentTenant $currentTenant, int $rfq, CreateQuotationComparisonNote $action): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $this->authorize('create', [QuotationComparisonNote::class, $model]);

        return (new QuotationComparisonNoteResource($action->handle($tenant, $request->user(), $model, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function updateNote(SaveQuotationComparisonNoteRequest $request, CurrentTenant $currentTenant, int $rfq, int $note, UpdateQuotationComparisonNote $action): QuotationComparisonNoteResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $comparisonNote = $this->findTenantNote($tenant, $model, $note);
        $this->authorize('update', $comparisonNote);

        return new QuotationComparisonNoteResource($action->handle($tenant, $request->user(), $model, $comparisonNote, $request->validated()));
    }

    public function deleteNote(CurrentTenant $currentTenant, int $rfq, int $note, DeleteQuotationComparisonNote $action): JsonResponse
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantRfq($tenant, $rfq);
        $comparisonNote = $this->findTenantNote($tenant, $model, $note);
        $this->authorize('delete', $comparisonNote);

        $action->handle($tenant, request()->user(), $model, $comparisonNote);

        return response()->json(null, 204);
    }

    private function findTenantRfq(Tenant $tenant, int $id): Rfq
    {
        return Rfq::query()->where('tenant_id', $tenant->id)->findOrFail($id);
    }

    private function findTenantNote(Tenant $tenant, Rfq $rfq, int $id): QuotationComparisonNote
    {
        return QuotationComparisonNote::query()
            ->where('tenant_id', $tenant->id)
            ->where('rfq_id', $rfq->id)
            ->findOrFail($id);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
```

- [x] **Step 7: Register routes**

Modify `apps/api/routes/api.php` imports:

```php
use Domains\Quotation\Http\Controllers\QuotationComparisonController;
```

Add routes inside the `ResolveCurrentTenant` group near RFQ routes:

```php
Route::get('/rfqs/{rfq}/comparison', [QuotationComparisonController::class, 'show']);
Route::post('/rfqs/{rfq}/comparison/notes', [QuotationComparisonController::class, 'storeNote']);
Route::patch('/rfqs/{rfq}/comparison/notes/{note}', [QuotationComparisonController::class, 'updateNote']);
Route::delete('/rfqs/{rfq}/comparison/notes/{note}', [QuotationComparisonController::class, 'deleteNote']);
```

- [x] **Step 8: Run API tests**

Run:

```bash
php artisan test --filter=QuotationComparisonApiTest
```

Expected: tests pass except any OpenAPI contract assertions that are not added yet.

- [x] **Step 9: Commit API behavior**

```bash
git add apps/api/Domains/Quotation/Actions/BuildQuotationComparison.php apps/api/Domains/Quotation/Actions/CreateQuotationComparisonNote.php apps/api/Domains/Quotation/Actions/UpdateQuotationComparisonNote.php apps/api/Domains/Quotation/Actions/DeleteQuotationComparisonNote.php apps/api/Domains/Quotation/Http/Requests/SaveQuotationComparisonNoteRequest.php apps/api/Domains/Quotation/Http/Resources/QuotationComparisonNoteResource.php apps/api/Domains/Quotation/Http/Resources/QuotationComparisonResource.php apps/api/Domains/Quotation/Http/Controllers/QuotationComparisonController.php apps/api/routes/api.php apps/api/tests/Feature/QuotationComparisonApiTest.php
git commit -m "feat: add quotation comparison API"
```

---

## Task 4: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated: `packages/api-client/src/generated/**`

- [x] **Step 1: Add OpenAPI paths**

Add path entries to `apps/api/storage/openapi/openapi.json` for:

```json
"/api/rfqs/{rfq}/comparison": {
  "get": {
    "operationId": "showQuotationComparison",
    "tags": ["RFQs"],
    "parameters": [
      { "name": "rfq", "in": "path", "required": true, "schema": { "type": "integer" } },
      { "name": "X-Tenant-Id", "in": "header", "required": false, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": { "description": "Quotation comparison", "content": { "application/json": { "schema": { "$ref": "#/components/schemas/QuotationComparisonResponse" } } } },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  }
}
```

Add note mutation paths with operation IDs `createQuotationComparisonNote`, `updateQuotationComparisonNote`, and `deleteQuotationComparisonNote`.

- [x] **Step 2: Add OpenAPI schemas**

Add schemas for `QuotationComparisonResponse`, `QuotationComparison`, `QuotationComparisonRfq`, `QuotationComparisonReadiness`, `QuotationComparisonVendor`, `QuotationComparisonLineRow`, `QuotationComparisonVendorCell`, `QuotationComparisonCommercialTerm`, `QuotationComparisonNote`, `QuotationComparisonPermissions`, and `SaveQuotationComparisonNoteRequest`.

Use this response envelope shape:

```json
"QuotationComparisonResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/QuotationComparison" }
  }
}
```

Use this note request shape:

```json
"SaveQuotationComparisonNoteRequest": {
  "type": "object",
  "required": ["section", "note"],
  "properties": {
    "section": { "type": "string", "enum": ["overall", "price", "delivery", "terms", "compliance", "risk"] },
    "note": { "type": "string", "maxLength": 2000 },
    "quotationId": { "type": "string", "nullable": true },
    "vendorId": { "type": "string", "nullable": true },
    "rfqLineItemId": { "type": "string", "nullable": true }
  }
}
```

- [x] **Step 3: Generate client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated endpoint and schema files are created under `packages/api-client/src/generated`.

- [x] **Step 4: Inspect generated names**

Run:

```bash
rg -n "showQuotationComparison|createQuotationComparisonNote|QuotationComparison" packages/api-client/src/generated packages/api-client/src/index.ts
```

Expected: generated endpoint and schema exports exist.

- [x] **Step 5: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated packages/api-client/src/index.ts
git commit -m "feat: add quotation comparison contract"
```

---

## Task 5: Web API Wrappers, Hooks, And MSW Fixtures

**Files:**

- Create: `apps/web/features/quotations/api/quotation-comparison-api.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-comparison.ts`
- Create: `apps/web/features/quotations/hooks/use-quotation-comparison-notes.ts`
- Create: `apps/web/features/quotations/mocks/quotation-comparison-fixtures.ts`
- Create: `apps/web/features/quotations/mocks/quotation-comparison-handlers.ts`
- Create: `apps/web/features/quotations/tests/quotation-comparison-api.test.ts`

- [x] **Step 1: Write API wrapper tests**

Create `apps/web/features/quotations/tests/quotation-comparison-api.test.ts`:

```ts
import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import {
  createQuotationComparisonNote,
  deleteQuotationComparisonNote,
  showQuotationComparison,
  updateQuotationComparisonNote,
} from "../api/quotation-comparison-api";

describe("quotation comparison api", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it("sends the active tenant header when loading comparison", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/rfqs/100/comparison", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: { rfq: { id: "100" }, readiness: {}, vendors: [], lineRows: [], commercialTerms: [], notes: [], permissions: { canViewComparison: true, canManageComparisonNotes: true } } });
      }),
    );

    const comparison = await showQuotationComparison("100");

    expect(comparison.rfq.id).toBe("100");
    expect(tenantHeader).toBe("tenant-1");
  });

  it("throws backend payload for failed comparison requests", async () => {
    server.use(
      http.get("*/api/rfqs/100/comparison", () =>
        HttpResponse.json({ error: { code: "forbidden", message: "Forbidden." } }, { status: 403 }),
      ),
    );

    await expect(showQuotationComparison("100")).rejects.toEqual({
      error: { code: "forbidden", message: "Forbidden." },
    });
  });

  it("wraps note create update and delete endpoints", async () => {
    server.use(
      http.post("*/api/rfqs/100/comparison/notes", () => HttpResponse.json({ data: { id: "note-1", section: "overall", note: "Initial note" } }, { status: 201 })),
      http.patch("*/api/rfqs/100/comparison/notes/note-1", () => HttpResponse.json({ data: { id: "note-1", section: "price", note: "Updated note" } })),
      http.delete("*/api/rfqs/100/comparison/notes/note-1", () => new HttpResponse(null, { status: 204 })),
    );

    await expect(createQuotationComparisonNote("100", { section: "overall", note: "Initial note" })).resolves.toMatchObject({ id: "note-1" });
    await expect(updateQuotationComparisonNote("100", "note-1", { section: "price", note: "Updated note" })).resolves.toMatchObject({ section: "price" });
    await expect(deleteQuotationComparisonNote("100", "note-1")).resolves.toBeUndefined();
  });
});
```

- [x] **Step 2: Run failing web API tests**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-comparison-api
```

Expected: fails because wrapper module does not exist.

- [x] **Step 3: Create API wrapper**

Create `apps/web/features/quotations/api/quotation-comparison-api.ts`:

```ts
import {
  createQuotationComparisonNote as createNoteEndpoint,
  deleteQuotationComparisonNote as deleteNoteEndpoint,
  showQuotationComparison as showEndpoint,
  updateQuotationComparisonNote as updateNoteEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  QuotationComparison,
  QuotationComparisonNote,
  SaveQuotationComparisonNoteRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  return { headers: { "X-Tenant-Id": tenantId } };
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

export async function showQuotationComparison(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparison> {
  const response = await showEndpoint(Number(rfqId), withActiveTenantHeader(tenantId)).catch(throwResponseData);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createQuotationComparisonNote(
  rfqId: string,
  payload: SaveQuotationComparisonNoteRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparisonNote> {
  const response = await createNoteEndpoint(Number(rfqId), payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  if (response.status !== 201) throw response.data;

  return response.data.data;
}

export async function updateQuotationComparisonNote(
  rfqId: string,
  noteId: string,
  payload: SaveQuotationComparisonNoteRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<QuotationComparisonNote> {
  const response = await updateNoteEndpoint(Number(rfqId), Number(noteId), payload, withActiveTenantHeader(tenantId)).catch(throwResponseData);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function deleteQuotationComparisonNote(
  rfqId: string,
  noteId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<void> {
  const response = await deleteNoteEndpoint(Number(rfqId), Number(noteId), withActiveTenantHeader(tenantId)).catch(throwResponseData);
  if (response.status !== 204) throw response.data;
}
```

- [x] **Step 4: Create hooks**

Create `apps/web/features/quotations/hooks/use-quotation-comparison.ts`:

```ts
"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { showQuotationComparison } from "../api/quotation-comparison-api";

export const quotationComparisonKeys = {
  all: (tenantId: string | null) => ["quotation-comparisons", tenantId] as const,
  detail: (rfqId: string, tenantId: string | null) => [...quotationComparisonKeys.all(tenantId), rfqId] as const,
};

export function useQuotationComparison(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: quotationComparisonKeys.detail(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) throw new Error("Cannot load a quotation comparison without an RFQ id.");

      return showQuotationComparison(rfqId, tenantId);
    },
    enabled: Boolean(rfqId),
  });
}
```

Create `apps/web/features/quotations/hooks/use-quotation-comparison-notes.ts` with three mutations that call note wrappers and invalidate `quotationComparisonKeys.detail(rfqId, tenantId)`.

- [x] **Step 5: Create MSW fixtures and handlers**

Create `apps/web/features/quotations/mocks/quotation-comparison-fixtures.ts` exporting at least:

- `readyQuotationComparisonFixture`
- `mixedReadinessQuotationComparisonFixture`
- `mixedCurrencyQuotationComparisonFixture`

Create `apps/web/features/quotations/mocks/quotation-comparison-handlers.ts` with handlers for GET/POST/PATCH/DELETE and an exported `resetQuotationComparisonMockState()`.

- [x] **Step 6: Run web API tests**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-comparison-api
```

Expected: pass.

- [x] **Step 7: Commit web API layer**

```bash
git add apps/web/features/quotations/api/quotation-comparison-api.ts apps/web/features/quotations/hooks/use-quotation-comparison.ts apps/web/features/quotations/hooks/use-quotation-comparison-notes.ts apps/web/features/quotations/mocks/quotation-comparison-fixtures.ts apps/web/features/quotations/mocks/quotation-comparison-handlers.ts apps/web/features/quotations/tests/quotation-comparison-api.test.ts
git commit -m "feat: add quotation comparison web API hooks"
```

---

## Task 6: Comparison Workspace UI

**Files:**

- Create: `apps/web/app/(workspace)/quotations/comparisons/[rfqId]/page.tsx`
- Create: `apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-readiness-banner.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-vendor-summary.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-table.tsx`
- Create: `apps/web/features/quotations/components/quotation-commercial-terms-table.tsx`
- Create: `apps/web/features/quotations/components/quotation-comparison-notes-panel.tsx`
- Create: `apps/web/features/quotations/tests/quotation-comparison-workspace.test.tsx`

- [x] **Step 1: Write failing workspace tests**

Create tests covering:

```ts
it("renders vendor summaries and line comparison for ready RFQ", async () => {});
it("marks vendors that require approved normalization", async () => {});
it("shows mixed currency warning without converting values", async () => {});
it("creates edits and deletes non-decision comparison notes", async () => {});
it("hides note controls when canManageComparisonNotes is false", async () => {});
```

Use MSW fixtures from Task 5 and assert text such as `Comparison notes are annotations only`, `Normalization required`, `Mixed currencies`, `Risk scoring not configured`, and `Bundle total`.

- [x] **Step 2: Run failing workspace tests**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-comparison-workspace
```

Expected: fails because route and components do not exist.

- [x] **Step 3: Create route**

Create `apps/web/app/(workspace)/quotations/comparisons/[rfqId]/page.tsx`:

```tsx
import { QuotationComparisonWorkspace } from "@/features/quotations/workflows/quotation-comparison-workspace";

export default async function QuotationComparisonPage({
  params,
}: {
  params: Promise<{ rfqId: string }>;
}) {
  const { rfqId } = await params;

  return <QuotationComparisonWorkspace rfqId={rfqId} />;
}
```

- [x] **Step 4: Create workspace shell**

Create `quotation-comparison-workspace.tsx` using `RecordWorkspaceLayout`, `useQuotationComparison`, `getApiErrorCode`, and the component files. It must show explicit forbidden/not-found messages and no decision language.

Include this static copy in the notes sidebar:

```tsx
<p className="text-xs text-muted-foreground">
  Comparison notes are annotations only. They do not score vendors, recommend awards, or change RFQ status.
</p>
```

- [x] **Step 5: Create comparison components**

Create components with focused responsibilities:

- `QuotationComparisonReadinessBanner`: response count, approved normalization count, mixed currency warning, missing response count.
- `QuotationComparisonVendorSummary`: cards for vendor name, readiness, total, lead time, terms, issue counts, note count.
- `QuotationComparisonTable`: desktop table and responsive stacked sections; preserve `bundleTotalAmount`.
- `QuotationCommercialTermsTable`: commercial terms rows by vendor.
- `QuotationComparisonNotesPanel`: controlled form for section/note, edit/delete buttons, disabled state when permission is false.

- [x] **Step 6: Run workspace tests**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-comparison-workspace
```

Expected: pass.

- [x] **Step 7: Commit workspace UI**

```bash
git add apps/web/app/\(workspace\)/quotations/comparisons/\[rfqId\]/page.tsx apps/web/features/quotations/workflows/quotation-comparison-workspace.tsx apps/web/features/quotations/components/quotation-comparison-readiness-banner.tsx apps/web/features/quotations/components/quotation-comparison-vendor-summary.tsx apps/web/features/quotations/components/quotation-comparison-table.tsx apps/web/features/quotations/components/quotation-commercial-terms-table.tsx apps/web/features/quotations/components/quotation-comparison-notes-panel.tsx apps/web/features/quotations/tests/quotation-comparison-workspace.test.tsx
git commit -m "feat: add quotation comparison workspace"
```

---

## Task 7: RFQ Link, Shell Breadcrumbs, And Route Visibility

**Files:**

- Modify: `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`
- Modify: `apps/web/components/shell/shell-route-config.ts`
- Modify: `apps/web/components/shell/shell-route-config.test.tsx`

- [x] **Step 1: Write/update shell tests**

Add expectations that `/quotations/comparisons/123` breadcrumbs are:

```ts
[
  { label: "Quotations", href: "/quotations/normalizations" },
  { label: "Comparison workspace" },
]
```

Assert the sourcing nav still points quotations to `/quotations/normalizations`; do not create a standalone comparison queue nav item.

- [x] **Step 2: Add breadcrumb pattern**

Modify `shell-route-config.ts`:

```ts
const QUOTATION_COMPARISON_WORKSPACE_PATH = /^\/quotations\/comparisons\/[^/]+$/;
```

Add breadcrumb branch:

```ts
if (QUOTATION_COMPARISON_WORKSPACE_PATH.test(normalizedPathname)) {
  return [
    { label: "Quotations", href: "/quotations/normalizations" },
    { label: "Comparison workspace" },
  ];
}
```

- [x] **Step 3: Add RFQ workspace action link**

Modify `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx` primary actions to include:

```tsx
<Link
  className="inline-flex items-center rounded-md border px-3 py-2 text-sm font-medium underline-offset-4 hover:underline"
  href={`/quotations/comparisons/${rfq.id}`}
>
  Open comparison
</Link>
```

Keep it a link only; do not add readiness queue logic.

- [x] **Step 4: Run shell and sourcing tests**

Run:

```bash
pnpm --filter @cognify/web test -- shell-route-config
pnpm --filter @cognify/web test -- rfq-draft-workflow
```

Expected: pass.

- [x] **Step 5: Commit navigation**

```bash
git add apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx apps/web/components/shell/shell-route-config.ts apps/web/components/shell/shell-route-config.test.tsx
git commit -m "feat: link RFQs to quotation comparison"
```

---

## Task 8: Roadmap Closeout And Verification

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-22-quotation-comparison-table.md`

- [x] **Step 1: Update roadmap after implementation passes**

Set P1-30:

```md
| P1-30 | Quotation Comparison Table | Compare vendors side by side by price, delivery, terms, compliance, risk, and qualitative notes. This should be a central buyer workspace rather than an exported spreadsheet. | Fully Implemented | 2026-05-22-quotation-comparison-table-design.md | 2026-05-22-quotation-comparison-table.md |  | Implemented as an RFQ-level buyer comparison workspace using approved normalization revisions, mixed-readiness indicators, bundle-aware line comparison, commercial terms comparison, and non-decision comparison notes. |
```

Keep P1-31 through P1-34 as `Not Implemented`.

- [x] **Step 2: Run API verification**

Run:

```bash
php artisan test --filter=QuotationComparisonApiTest
php artisan test --filter=QuotationComparisonBuilderTest
php artisan test --filter=QuotationNormalizationApiTest
php artisan route:list --path=api/rfqs
```

Expected: all tests pass; route list includes `/api/rfqs/{rfq}/comparison` and note mutation routes.

- [x] **Step 3: Run contract verification**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm typecheck
```

Expected: generated client is aligned with OpenAPI and TypeScript passes.

- [x] **Step 4: Run web verification**

Run:

```bash
pnpm --filter @cognify/web test -- quotation-comparison
pnpm --filter @cognify/web test -- quotation-normalization
pnpm --filter @cognify/web test -- shell-route-config
pnpm --filter @cognify/web test -- rfq-draft-workflow
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: all focused web checks pass.

- [x] **Step 5: Run final hygiene checks**

Run:

```bash
git diff --check
rg -n "shortlist|winner|recommended|award decision|score vendor|ranking" apps/api/Domains/Quotation apps/web/features/quotations apps/web/features/sourcing docs/01-product/feature-roadmap.md
```

Expected: `git diff --check` has no output. The grep should only find non-goal/spec copy or explicit "not implemented/not scored" user-facing copy, not production decisioning behavior.

- [x] **Step 6: Commit closeout**

```bash
git add docs/01-product/feature-roadmap.md docs/superpowers/plans/2026-05-22-quotation-comparison-table.md
git commit -m "docs: mark quotation comparison implemented"
```

---

## Self-Review Checklist

- Spec coverage: RFQ-only workspace, approved normalization inputs, comparison notes only, no scoring/award state, tenant-policy checks, OpenAPI/client, shadcn/Radix-first web UI, RFQ link, audit, and verification are covered.
- Placeholder scan: This plan names concrete files, commands, operation IDs, schema names, and route paths.
- Type consistency: API names use `QuotationComparison*`; note section enum values match web/API request values; generated wrapper names match operation IDs.
- Scope check: P1-31 scoring, P1-32 recommendation, P1-33 award approval, and P1-34 PO handoff remain out of scope.

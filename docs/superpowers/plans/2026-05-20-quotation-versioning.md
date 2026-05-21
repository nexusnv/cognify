# Quotation Versioning Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement P1-28 so each quotation preserves immutable submitted versions while the quotation record remains the current response container for buyer and vendor workflows.

**Architecture:** Add `QuotationVersion` and `QuotationVersionLineItem` as immutable snapshot records owned by `apps/api/Domains/Quotation`, with `Quotation.current_version_id` and `Quotation.version_count` pointing at the current snapshot. Existing upload and manual-entry mutations continue to update the current quotation summary, then create a new immutable version in the same transaction so revised data never destroys the previous submitted version. Buyer and vendor UI surfaces read version history through generated OpenAPI clients and keep prior versions read-only.

**Tech Stack:** Laravel 12, Eloquent, Sanctum, tenant-scoped policies/actions, OpenAPI, Orval-generated TypeScript client, Next.js App Router, TanStack Query, MSW, Vitest, shadcn/Radix primitives through `packages/ui`.

---

## Grounding

- Product roadmap: `docs/01-product/feature-roadmap.md` keeps P1-28 as Epic 6 slice 4 before P1-29 quotation normalization.
- Design spec: `docs/superpowers/specs/2026-05-20-quotation-versioning-design.md`.
- Architecture: `ARCHITECTURE.md` requires tenant isolation, OpenAPI as contract, backend workflow actions, and generated frontend clients.
- Runbook: `docs/05-runbooks/feature-development.md` requires workflow-first, contract-first vertical slices, feature-local web code, and domain behavior in `apps/api/Domains/Quotation`.
- Predecessors already in this branch: quotation upload and manual entry under `apps/api/Domains/Quotation`, `apps/web/features/sourcing`, and `apps/web/features/vendor-portal`.

## Workflow Map

Actors:

- Buyer: authenticated tenant user with RFQ view/manage access.
- Vendor: token-authenticated external actor through vendor portal routes.
- System: creates immutable version snapshots and audit events.

States:

- `Quotation` remains the durable vendor response container and current summary.
- `QuotationVersion.status` mirrors the submitted quotation status at snapshot time.
- Exactly one version per quotation has `is_current = true`.
- Superseded versions have `is_current = false` and `superseded_at` set.

Transitions:

- First accepted upload or manual entry creates version 1.
- Any accepted buyer or vendor upload after version 1 creates version N+1.
- Any accepted buyer or vendor manual-entry save after version 1 creates version N+1.
- Listing or showing prior versions is read-only.

Side effects:

- Audit events: `quotation.version_created`, `quotation.version_superseded`, `quotation.current_version_changed`.
- No notification work in this slice. Vendor revision notifications remain outside this implementation unless a separate notification slice explicitly scopes them.

Tenant and permission rules:

- Buyer version endpoints require `auth:sanctum` and `ResolveCurrentTenant`.
- Vendor version endpoints derive tenant access from `ResolveRfqInvitationPortalAccess`.
- Every version query filters by `tenant_id`, `quotation_id`, and invitation/vendor ownership.
- Vendor resources redact buyer notes and internal user identity.

---

## File Map

### API: Data Model

- Create: `apps/api/database/migrations/2026_05_20_040000_create_quotation_versions_table.php`
  - Creates immutable version header snapshots.
- Create: `apps/api/database/migrations/2026_05_20_041000_create_quotation_version_line_items_table.php`
  - Creates immutable version line item snapshots.
- Create: `apps/api/database/migrations/2026_05_20_042000_add_current_version_to_quotations_table.php`
  - Adds `quotations.current_version_id` and `quotations.version_count`.
- Create: `apps/api/Domains/Quotation/Models/QuotationVersion.php`
  - Eloquent model and same-tenant invariants for version snapshots.
- Create: `apps/api/Domains/Quotation/Models/QuotationVersionLineItem.php`
  - Eloquent model and same-tenant invariants for version line item snapshots.
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`
  - Adds `currentVersion()` and `versions()` relationships plus fillable/casts.

### API: Actions, Requests, Resources, Controllers

- Create: `apps/api/Domains/Quotation/Data/QuotationVersionAttachmentSnapshotData.php`
  - Normalizes attachment snapshot arrays for resources.
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationVersionSnapshot.php`
  - Transactional version creation from current quotation state.
- Create: `apps/api/Domains/Quotation/Http/Requests/CreateQuotationRevisionRequest.php`
  - Validates revision payload using the manual-entry shape plus optional `attachmentIds`.
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationVersionLineItemResource.php`
  - Serializes immutable line item snapshots.
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationVersionResource.php`
  - Serializes buyer and vendor-safe version snapshots.
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionController.php`
  - Authenticated buyer list/show/create endpoints.
- Create: `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationVersionController.php`
  - Vendor portal list/create endpoints.
- Modify: `apps/api/Domains/Quotation/Actions/StoreQuotationAttachment.php`
  - Creates a version snapshot after upload mutation.
- Modify: `apps/api/Domains/Quotation/Actions/SaveQuotationManualEntry.php`
  - Creates a version snapshot after manual-entry mutation.
- Modify: `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`
  - Adds `currentVersion` and version permissions to current quotation response.
- Modify: `apps/api/routes/api.php`
  - Registers authenticated and vendor portal version routes.

### API: Tests And Contract

- Create: `apps/api/tests/Feature/QuotationVersionApiTest.php`
  - Covers initial version, buyer revision, vendor revision, read-only prior versions, tenant guards, terminal states, and audit.
- Modify: `apps/api/storage/openapi/openapi.json`
  - Adds version endpoints and schemas.
- Modify generated: `packages/api-client/src/generated/**`
  - Regenerated by `pnpm check:api-contract`.

### Web: Buyer Workspace

- Modify: `apps/web/features/sourcing/api/quotation-api.ts`
  - Adds generated-client wrappers for quotation versions.
- Create: `apps/web/features/sourcing/hooks/use-quotation-versions.ts`
  - Query/mutation hooks and cache updates.
- Create: `apps/web/features/sourcing/components/quotation-version-history.tsx`
  - Buyer current/prior version history list.
- Create: `apps/web/features/sourcing/components/quotation-version-detail.tsx`
  - Read-only selected version snapshot.
- Modify: `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`
  - Shows current version, history, and selected version details.
- Modify: `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
  - Adds version state and handlers.
- Modify: `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`
  - Adds buyer version history workflow coverage.

### Web: Vendor Portal

- Modify: `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
  - Adds generated-client wrappers for vendor portal versions.
- Modify: `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`
  - Adds version query/mutation hooks.
- Create: `apps/web/features/vendor-portal/components/vendor-quotation-version-history.tsx`
  - Vendor-safe current/prior version history.
- Modify: `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`
  - Shows version history below structured response.
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
  - Adds vendor-safe version state.
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
  - Adds vendor portal version handlers.
- Modify: `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`
  - Adds vendor version workflow coverage.

### Docs

- Modify: `docs/01-product/feature-roadmap.md`
  - Mark P1-28 fully implemented after verification.
- Modify: `docs/superpowers/plans/2026-05-20-quotation-versioning.md`
  - Mark completed checkboxes after implementation and verification.

---

## Task 1: Backend Version Contract Tests First

**Files:**

- Create: `apps/api/tests/Feature/QuotationVersionApiTest.php`

- [x] Add the backend feature test class.

Create `apps/api/tests/Feature/QuotationVersionApiTest.php` with this starting content. The helper block for this test class is included in the next step and must be appended before the final class closing brace.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuotationVersionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_manual_entry_creates_version_one(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
                'vendorNotes' => 'Initial submitted quotation.',
            ]));

        $response->assertOk()
            ->assertJsonPath('data.currentVersion.versionNumber', 1)
            ->assertJsonPath('data.currentVersion.isCurrent', true)
            ->assertJsonPath('data.versionCount', 1);

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'quotation_reference' => 'NW-Q-2026-041',
            'total_amount' => '12470.00',
            'vendor_notes' => 'Initial submitted quotation.',
        ]);
        $this->assertDatabaseHas('quotation_version_line_items', [
            'tenant_id' => $tenant->id,
            'quotation_version_id' => $quotation->current_version_id,
            'description' => 'Developer laptop',
            'quantity' => '10.0000',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.current_version_changed',
        ]);
    }

    public function test_upload_creates_attachment_snapshot_on_current_version(): void
    {
        Storage::fake('local');

        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $response = $this->actingAsTenant($tenant, $buyer)
            ->post("/api/rfq-invitations/{$invitation->id}/quotation/attachments", [
                'file' => UploadedFile::fake()->create('vendor-quotation.pdf', 24, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.currentVersion.versionNumber', 1)
            ->assertJsonPath('data.currentVersion.attachmentCount', 1);

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->whereKey($quotation->current_version_id)->firstOrFail();

        $this->assertSame('vendor-quotation.pdf', $version->attachment_snapshots[0]['filename']);
        $this->assertSame('application/pdf', $version->attachment_snapshots[0]['mimeType']);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_created',
        ]);
    }

    public function test_buyer_revision_creates_next_current_version_and_supersedes_previous(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $firstVersionId = $quotation->current_version_id;

        $response = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload([
                'quotationReference' => 'NW-Q-2026-041-R2',
                'totalAmount' => '11990.00',
                'buyerNotes' => 'Buyer corrected totals from revised quote.',
                'vendorNotes' => 'Vendor revised pricing after stock check.',
            ]));

        $response->assertCreated()
            ->assertJsonPath('data.versionNumber', 2)
            ->assertJsonPath('data.isCurrent', true)
            ->assertJsonPath('data.manualEntry.quotationReference', 'NW-Q-2026-041-R2')
            ->assertJsonPath('data.manualEntry.totalAmount', '11990.00')
            ->assertJsonPath('data.previousVersionId', (string) $firstVersionId);

        $this->assertDatabaseHas('quotation_versions', [
            'id' => $firstVersionId,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 2,
            'is_current' => true,
            'total_amount' => '11990.00',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'event_type' => 'quotation.version_superseded',
        ]);
    }

    public function test_buyer_can_list_and_show_read_only_versions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'quotationReference' => 'NW-Q-2026-041',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload([
                'quotationReference' => 'NW-Q-2026-041-R2',
                'totalAmount' => '11990.00',
            ]))
            ->assertCreated();

        $versions = $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotations/{$quotation->id}/versions");

        $versions->assertOk()
            ->assertJsonPath('data.0.versionNumber', 2)
            ->assertJsonPath('data.0.isCurrent', true)
            ->assertJsonPath('data.1.versionNumber', 1)
            ->assertJsonPath('data.1.isCurrent', false);

        $firstVersion = QuotationVersion::query()
            ->where('quotation_id', $quotation->id)
            ->where('version_number', 1)
            ->firstOrFail();

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$firstVersion->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $firstVersion->id)
            ->assertJsonPath('data.isCurrent', false)
            ->assertJsonPath('data.permissions.canEdit', false)
            ->assertJsonPath('data.manualEntry.totalAmount', '12470.00');
    }

    public function test_vendor_portal_revision_creates_vendor_safe_current_version(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload([
                'buyerNotes' => 'Internal buyer note.',
                'totalAmount' => '12470.00',
            ]))
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();

        $response = $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload([
            'buyerNotes' => 'Attempted hidden note.',
            'vendorNotes' => 'Revised from vendor portal.',
            'totalAmount' => '11990.00',
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.versionNumber', 2)
            ->assertJsonPath('data.source', 'vendor_portal')
            ->assertJsonPath('data.manualEntry.buyerNotes', null)
            ->assertJsonPath('data.manualEntry.vendorNotes', 'Revised from vendor portal.');

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions")
            ->assertOk()
            ->assertJsonPath('data.0.versionNumber', 2)
            ->assertJsonPath('data.0.manualEntry.buyerNotes', null);

        $this->assertDatabaseHas('quotation_versions', [
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 2,
            'vendor_notes' => 'Revised from vendor portal.',
            'buyer_notes' => 'Internal buyer note.',
        ]);
    }

    public function test_cross_tenant_buyer_cannot_read_or_create_versions(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);

        $this->actingAsTenant($tenant, $buyer)
            ->putJson("/api/rfq-invitations/{$invitation->id}/quotation/manual-entry", $this->validManualEntryPayload())
            ->assertOk();

        $quotation = Quotation::query()->where('rfq_invitation_id', $invitation->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        [$otherTenant, $otherBuyer] = $this->tenantUser('buyer');

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotations/{$quotation->id}/versions")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->getJson("/api/quotations/{$quotation->id}/versions/{$version->id}")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherBuyer)
            ->postJson("/api/quotations/{$quotation->id}/versions", $this->validRevisionPayload())
            ->assertNotFound();
    }

    public function test_terminal_invitation_blocks_vendor_revision(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor, [
            'status' => RfqInvitationStatus::Declined->value,
        ]);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload())
            ->assertConflict();
    }

    public function test_revision_requires_existing_quotation(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);
        $invitation = $this->invitation($tenant, $rfq, $vendor);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->postJson("/api/vendor-portal/rfq-invitations/{$token}/quotation/versions", $this->validRevisionPayload())
            ->assertNotFound();
    }

    private function validRevisionPayload(array $overrides = []): array
    {
        return array_replace_recursive($this->validManualEntryPayload(), [
            'attachmentIds' => [],
        ], $overrides);
    }
}
```

- [x] Append helper methods to `QuotationVersionApiTest`.

Append these helper methods below `validRevisionPayload()` and before the class closing brace:

```php
private function issuePortalToken(Tenant $tenant, User $buyer, RfqInvitation $invitation): string
{
    $this->assertSame($tenant->id, $invitation->tenant_id);
    $this->assertTrue($tenant->users()->whereKey($buyer->id)->exists());

    $token = Str::random(64);

    $invitation->forceFill([
        'portal_token_hash' => hash('sha256', $token),
        'portal_token_created_at' => now(),
        'portal_token_expires_at' => now()->addDays(14),
    ])->save();

    return $token;
}

private function actingAsTenant(Tenant $tenant, User $user): self
{
    Sanctum::actingAs($user);

    return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
}

/**
 * @return array{Tenant, User}
 */
private function tenantUser(string $role, ?Tenant $tenant = null): array
{
    $tenant ??= Tenant::query()->create(['name' => 'Tenant ' . Str::uuid()]);
    $user = User::factory()->create(['password' => Hash::make('secret123')]);
    $tenant->users()->attach($user->id, ['role' => $role]);

    return [$tenant, $user];
}

private function vendor(Tenant $tenant, array $overrides = []): Vendor
{
    return Vendor::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Vendor ' . Str::uuid(),
        'status' => 'active',
        'category' => 'IT Hardware',
        'risk_rating' => 'low',
        'metadata' => [
            'contactName' => 'Vendor Contact',
            'contactEmail' => 'vendor@example.test',
        ],
    ], $overrides));
}

private function draftRfq(Tenant $tenant, User $requester, User $buyer, array $overrides = []): Rfq
{
    $requisition = Requisition::query()->create([
        'tenant_id' => $tenant->id,
        'requester_id' => $requester->id,
        'number' => 'REQ-' . Str::random(8),
        'title' => 'Laptop refresh',
        'status' => RequisitionStatus::Approved,
        'currency' => 'USD',
    ]);

    $review = SourcingIntakeReview::query()->create([
        'tenant_id' => $tenant->id,
        'requisition_id' => $requisition->id,
        'assigned_buyer_id' => $buyer->id,
        'status' => SourcingIntakeStatus::ReadyForRfq,
        'sourcing_path' => SourcingPath::NeedsRfq,
        'decision_reason' => 'Competitive sourcing required.',
    ]);

    return Rfq::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'sourcing_intake_review_id' => $review->id,
        'requisition_id' => $requisition->id,
        'number' => 'RFQ-' . Str::random(8),
        'title' => 'Laptop refresh RFQ',
        'status' => RfqStatus::Draft,
        'required_documents' => [],
        'line_items' => [],
        'response_due_at' => now()->addDays(14),
        'response_instructions' => 'Submit pricing and delivery terms.',
    ], $overrides));
}

private function invitation(Tenant $tenant, Rfq $rfq, Vendor $vendor, array $overrides = []): RfqInvitation
{
    return RfqInvitation::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'rfq_id' => $rfq->id,
        'vendor_id' => $vendor->id,
        'status' => RfqInvitationStatus::Sent,
        'contact_name' => 'Vendor Contact',
        'contact_email' => 'vendor@example.test',
        'message' => 'Please respond.',
        'response_due_at' => now()->addDays(14),
        'sent_at' => now(),
    ], $overrides));
}

private function quotation(Tenant $tenant, Rfq $rfq, Vendor $vendor, RfqInvitation $invitation): Quotation
{
    return Quotation::query()->create([
        'tenant_id' => $tenant->id,
        'rfq_id' => $rfq->id,
        'vendor_id' => $vendor->id,
        'rfq_invitation_id' => $invitation->id,
        'number' => 'QUO-' . Str::random(8),
        'status' => 'received',
        'submission_source' => 'buyer_upload',
        'file_count' => 0,
    ]);
}

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
php artisan test --filter=QuotationVersionApiTest
```

Expected result: tests fail because `QuotationVersion`, version routes, version fields, and version resources do not exist.

---

## Task 2: Backend Data Model

**Files:**

- Create: `apps/api/database/migrations/2026_05_20_040000_create_quotation_versions_table.php`
- Create: `apps/api/database/migrations/2026_05_20_041000_create_quotation_version_line_items_table.php`
- Create: `apps/api/database/migrations/2026_05_20_042000_add_current_version_to_quotations_table.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationVersion.php`
- Create: `apps/api/Domains/Quotation/Models/QuotationVersionLineItem.php`
- Modify: `apps/api/Domains/Quotation/Models/Quotation.php`

- [x] Add the quotation versions migration.

Create `apps/api/database/migrations/2026_05_20_040000_create_quotation_versions_table.php`:

```php
<?php

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 32);
            $table->string('submission_source', 32)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignIdFor(User::class, 'submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('submitted_by_vendor_contact')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('superseded_at')->nullable();
            $table->string('quotation_reference')->nullable();
            $table->date('quoted_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->char('currency', 3)->nullable();
            $table->decimal('subtotal_amount', 14, 2)->nullable();
            $table->decimal('tax_amount', 14, 2)->nullable();
            $table->decimal('freight_amount', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->nullable();
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('delivery_terms')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->text('warranty_terms')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('compliance_notes')->nullable();
            $table->text('buyer_notes')->nullable();
            $table->text('vendor_notes')->nullable();
            $table->boolean('manual_entry_complete')->default(false);
            $table->json('manual_entry_missing_fields')->nullable();
            $table->json('attachment_snapshots')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'quotation_id', 'version_number'], 'quotation_versions_number_unique');
            $table->index(['tenant_id', 'quotation_id', 'is_current'], 'quotation_versions_current_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_versions');
    }
};
```

- [x] Add the quotation version line items migration.

Create `apps/api/database/migrations/2026_05_20_041000_create_quotation_version_line_items_table.php`:

```php
<?php

use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_version_line_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Tenant::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(QuotationVersion::class)->constrained()->cascadeOnDelete();
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
            $table->string('compliance_status', 32)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'quotation_version_id'], 'quotation_version_line_items_version_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_version_line_items');
    }
};
```

- [x] Add current version fields to quotations.

Create `apps/api/database/migrations/2026_05_20_042000_add_current_version_to_quotations_table.php`:

```php
<?php

use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->foreignIdFor(QuotationVersion::class, 'current_version_id')
                ->nullable()
                ->after('rfq_invitation_id')
                ->constrained('quotation_versions')
                ->nullOnDelete();
            $table->unsignedInteger('version_count')->default(0)->after('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
            $table->dropColumn('version_count');
        });
    }
};
```

- [x] Add the `QuotationVersion` model.

Create `apps/api/Domains/Quotation/Models/QuotationVersion.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationVersion extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'version_number',
        'status',
        'submission_source',
        'submitted_at',
        'submitted_by_user_id',
        'submitted_by_vendor_contact',
        'is_current',
        'superseded_at',
        'quotation_reference',
        'quoted_at',
        'valid_until',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
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
        'attachment_snapshots',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'submission_source' => QuotationSubmissionSource::class,
            'submitted_at' => 'immutable_datetime',
            'submitted_by_vendor_contact' => 'array',
            'is_current' => 'boolean',
            'superseded_at' => 'immutable_datetime',
            'quoted_at' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'lead_time_days' => 'integer',
            'manual_entry_complete' => 'boolean',
            'manual_entry_missing_fields' => 'array',
            'attachment_snapshots' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            DB::transaction(function () use ($version): void {
                $belongsToTenant = Quotation::query()
                    ->whereKey($version->quotation_id)
                    ->where('tenant_id', $version->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Quotation version must belong to the same tenant as the quotation.');
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<QuotationVersionLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuotationVersionLineItem::class)->orderBy('position');
    }
}
```

- [x] Add the `QuotationVersionLineItem` model.

Create `apps/api/Domains/Quotation/Models/QuotationVersionLineItem.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationVersionLineItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_version_id',
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
                $belongsToTenant = QuotationVersion::query()
                    ->whereKey($lineItem->quotation_version_id)
                    ->where('tenant_id', $lineItem->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Quotation version line item must belong to the same tenant as the version.');
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
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }
}
```

- [x] Extend `Quotation`.

In `apps/api/Domains/Quotation/Models/Quotation.php`, add these imports:

```php
use Domains\Quotation\Models\QuotationVersion;
```

Add these fillable fields:

```php
'current_version_id',
'version_count',
```

Add this cast:

```php
'version_count' => 'integer',
```

Add these relationships:

```php
/**
 * @return BelongsTo<QuotationVersion, $this>
 */
public function currentVersion(): BelongsTo
{
    return $this->belongsTo(QuotationVersion::class, 'current_version_id');
}

/**
 * @return HasMany<QuotationVersion, $this>
 */
public function versions(): HasMany
{
    return $this->hasMany(QuotationVersion::class)->orderByDesc('version_number');
}
```

- [x] Run backend tests for the data model milestone.

Run:

```bash
php artisan test --filter=QuotationVersionApiTest
```

Expected result: tests still fail because version actions, resources, and routes are not implemented, but migration/model class errors should be gone.

---

## Task 3: Backend Version Snapshot Action

**Files:**

- Create: `apps/api/Domains/Quotation/Data/QuotationVersionAttachmentSnapshotData.php`
- Create: `apps/api/Domains/Quotation/Actions/CreateQuotationVersionSnapshot.php`
- Modify: `apps/api/Domains/Quotation/Actions/StoreQuotationAttachment.php`
- Modify: `apps/api/Domains/Quotation/Actions/SaveQuotationManualEntry.php`

- [x] Add attachment snapshot data.

Create `apps/api/Domains/Quotation/Data/QuotationVersionAttachmentSnapshotData.php`:

```php
<?php

namespace Domains\Quotation\Data;

use Domains\Attachment\Models\Attachment;

class QuotationVersionAttachmentSnapshotData
{
    /**
     * @return array<string, mixed>
     */
    public static function fromAttachment(Attachment $attachment): array
    {
        return [
            'id' => (string) $attachment->id,
            'filename' => $attachment->original_filename,
            'mimeType' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'sizeBytes' => $attachment->size_bytes,
            'checksumSha256' => $attachment->checksum_sha256,
            'previewable' => (bool) $attachment->previewable,
            'uploadedBy' => $attachment->uploader ? [
                'id' => (string) $attachment->uploader->id,
                'name' => $attachment->uploader->name,
            ] : null,
            'createdAt' => $attachment->created_at?->toISOString(),
            'available' => true,
        ];
    }
}
```

- [x] Add the version snapshot action.

Create `apps/api/Domains/Quotation/Actions/CreateQuotationVersionSnapshot.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Data\QuotationVersionAttachmentSnapshotData;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateQuotationVersionSnapshot
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    /**
     * @param  array<int, int|string>|null  $attachmentIds
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Tenant $tenant,
        Quotation $quotation,
        ?User $actor,
        QuotationSubmissionSource $source,
        ?array $attachmentIds = null,
        array $metadata = [],
    ): QuotationVersion {
        return DB::transaction(function () use ($tenant, $quotation, $actor, $source, $attachmentIds, $metadata): QuotationVersion {
            $lockedQuotation = Quotation::query()
                ->with([
                    'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
                    'lineItems',
                    'rfq',
                    'vendor',
                    'rfqInvitation',
                    'currentVersion',
                ])
                ->where('tenant_id', $tenant->id)
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousVersion = $lockedQuotation->currentVersion;
            $nextVersionNumber = ((int) $lockedQuotation->version_count) + 1;

            if ($previousVersion) {
                $previousVersion->forceFill([
                    'is_current' => false,
                    'superseded_at' => now(),
                ])->save();
            }

            $attachments = $this->attachmentsForSnapshot($lockedQuotation, $attachmentIds);

            $version = QuotationVersion::query()->create([
                'tenant_id' => $tenant->id,
                'quotation_id' => $lockedQuotation->id,
                'version_number' => $nextVersionNumber,
                'status' => $lockedQuotation->status?->value ?? $lockedQuotation->status,
                'submission_source' => $source->value,
                'submitted_at' => $lockedQuotation->submitted_at,
                'submitted_by_user_id' => $lockedQuotation->submitted_by_user_id,
                'submitted_by_vendor_contact' => $lockedQuotation->submitted_by_vendor_contact,
                'is_current' => true,
                'quotation_reference' => $lockedQuotation->quotation_reference,
                'quoted_at' => $lockedQuotation->quoted_at,
                'valid_until' => $lockedQuotation->valid_until,
                'currency' => $lockedQuotation->currency,
                'subtotal_amount' => $lockedQuotation->subtotal_amount,
                'tax_amount' => $lockedQuotation->tax_amount,
                'freight_amount' => $lockedQuotation->freight_amount,
                'discount_amount' => $lockedQuotation->discount_amount,
                'total_amount' => $lockedQuotation->total_amount,
                'payment_terms' => $lockedQuotation->payment_terms,
                'delivery_terms' => $lockedQuotation->delivery_terms,
                'lead_time_days' => $lockedQuotation->lead_time_days,
                'warranty_terms' => $lockedQuotation->warranty_terms,
                'exclusions' => $lockedQuotation->exclusions,
                'compliance_notes' => $lockedQuotation->compliance_notes,
                'buyer_notes' => $lockedQuotation->buyer_notes,
                'vendor_notes' => $lockedQuotation->vendor_notes,
                'manual_entry_complete' => $lockedQuotation->manual_entry_complete,
                'manual_entry_missing_fields' => $lockedQuotation->manual_entry_missing_fields,
                'attachment_snapshots' => $attachments->map(
                    fn ($attachment) => QuotationVersionAttachmentSnapshotData::fromAttachment($attachment),
                )->values()->all(),
                'metadata' => $metadata + [
                    'source' => $source->value,
                    'previousVersionId' => $previousVersion?->id === null ? null : (string) $previousVersion->id,
                ],
            ]);

            $lockedQuotation->lineItems->values()->each(function ($lineItem, int $index) use ($tenant, $version): void {
                QuotationVersionLineItem::query()->create([
                    'tenant_id' => $tenant->id,
                    'quotation_version_id' => $version->id,
                    'rfq_line_item_id' => $lineItem->rfq_line_item_id,
                    'description' => $lineItem->description,
                    'quantity' => $lineItem->quantity,
                    'unit' => $lineItem->unit,
                    'unit_price' => $lineItem->unit_price,
                    'subtotal_amount' => $lineItem->subtotal_amount,
                    'tax_amount' => $lineItem->tax_amount,
                    'total_amount' => $lineItem->total_amount,
                    'lead_time_days' => $lineItem->lead_time_days,
                    'manufacturer' => $lineItem->manufacturer,
                    'model_number' => $lineItem->model_number,
                    'alternate_offered' => $lineItem->alternate_offered,
                    'compliance_status' => $lineItem->compliance_status,
                    'notes' => $lineItem->notes,
                    'position' => $index + 1,
                ]);
            });

            $lockedQuotation->forceFill([
                'current_version_id' => $version->id,
                'version_count' => $nextVersionNumber,
            ])->save();

            $this->recordAuditEvents($tenant, $actor, $lockedQuotation, $version, $previousVersion, $source);

            return $version->refresh()->load(['lineItems', 'submittedByUser', 'quotation']);
        });
    }

    /**
     * @param  array<int, int|string>|null  $attachmentIds
     * @return Collection<int, \Domains\Attachment\Models\Attachment>
     */
    private function attachmentsForSnapshot(Quotation $quotation, ?array $attachmentIds): Collection
    {
        if ($attachmentIds === null || $attachmentIds === []) {
            return $quotation->attachments;
        }

        $allowedIds = collect($attachmentIds)->map(fn ($id) => (string) $id)->all();

        return $quotation->attachments
            ->filter(fn ($attachment) => in_array((string) $attachment->id, $allowedIds, true))
            ->values();
    }

    private function recordAuditEvents(
        Tenant $tenant,
        ?User $actor,
        Quotation $quotation,
        QuotationVersion $version,
        ?QuotationVersion $previousVersion,
        QuotationSubmissionSource $source,
    ): void {
        $baseMetadata = [
            'quotationId' => (string) $quotation->id,
            'versionId' => (string) $version->id,
            'versionNumber' => $version->version_number,
            'previousCurrentVersionId' => $previousVersion?->id === null ? null : (string) $previousVersion->id,
            'rfqId' => (string) $quotation->rfq_id,
            'rfqInvitationId' => (string) $quotation->rfq_invitation_id,
            'vendorId' => (string) $quotation->vendor_id,
            'source' => $source->value,
            'actor' => [
                'type' => $actor === null ? 'vendor_portal' : 'user',
                'id' => $actor?->id === null ? null : (string) $actor->id,
            ],
        ];

        if ($previousVersion) {
            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.version_superseded',
                subject: $previousVersion,
                metadata: $baseMetadata,
                subjectDisplay: $quotation->number,
            ));
        }

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation.version_created',
            subject: $version,
            metadata: $baseMetadata,
            subjectDisplay: $quotation->number,
        ));

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation.current_version_changed',
            subject: $quotation,
            metadata: $baseMetadata,
            subjectDisplay: $quotation->number,
        ));
    }
}
```

- [x] Wire snapshots into quotation uploads.

Modify the constructor of `apps/api/Domains/Quotation/Actions/StoreQuotationAttachment.php`:

```php
public function __construct(
    private readonly AuditRecorder $auditRecorder,
    private readonly AttachmentStorage $storage,
    private readonly CreateOrRevealQuotationForInvitation $createOrRevealQuotationForInvitation,
    private readonly CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
) {
}
```

Inside the existing database transaction, after the existing `rfq_invitation.quotation_received` audit block and before `return $quotation;`, add:

```php
$version = $this->createQuotationVersionSnapshot->handle(
    $tenant,
    $quotation,
    $actor,
    $source,
    [(string) $attachment->id],
    ['trigger' => 'attachment_upload'],
);

$quotation->forceFill([
    'current_version_id' => $version->id,
    'version_count' => $version->version_number,
])->save();
$quotation->refresh()->load([
    'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
    'submittedByUser',
    'rfq',
    'vendor',
    'rfqInvitation',
    'currentVersion.lineItems',
]);
```

- [x] Wire snapshots into manual entry.

Modify the constructor of `apps/api/Domains/Quotation/Actions/SaveQuotationManualEntry.php`:

```php
public function __construct(
    private readonly AuditRecorder $auditRecorder,
    private readonly CreateOrRevealQuotationForInvitation $createOrRevealQuotationForInvitation,
    private readonly CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
) {
}
```

Inside the transaction, after the manual-entry audit events and before the final `$quotation = $quotation->refresh()->load([...]);`, add:

```php
$version = $this->createQuotationVersionSnapshot->handle(
    $tenant,
    $quotation,
    $actor,
    $source,
    null,
    ['trigger' => 'manual_entry_save'],
);

$quotation->forceFill([
    'current_version_id' => $version->id,
    'version_count' => $version->version_number,
])->save();
```

Add `currentVersion.lineItems` to the final relation load list.

- [x] Run backend tests for automatic snapshot creation.

Run:

```bash
php artisan test --filter=QuotationVersionApiTest
php artisan test --filter=QuotationManualEntryApiTest
php artisan test --filter=QuotationUploadApiTest
```

Expected result: version tests should pass for initial upload/manual entry paths and still fail for missing explicit version endpoints. Manual-entry and upload regression tests should pass.

---

## Task 4: Backend Revision Request, Resources, And Controllers

**Files:**

- Create: `apps/api/Domains/Quotation/Http/Requests/CreateQuotationRevisionRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationVersionLineItemResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/QuotationVersionResource.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionController.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationVersionController.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`
- Modify: `apps/api/routes/api.php`

- [x] Add the revision request.

Create `apps/api/Domains/Quotation/Http/Requests/CreateQuotationRevisionRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateQuotationRevisionRequest extends FormRequest
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
            'quotationReference' => ['nullable', 'string', 'max:120'],
            'quotedAt' => ['nullable', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:quotedAt'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotalAmount' => ['nullable', 'numeric', 'min:0'],
            'taxAmount' => ['nullable', 'numeric', 'min:0'],
            'freightAmount' => ['nullable', 'numeric', 'min:0'],
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'totalAmount' => ['nullable', 'numeric', 'min:0'],
            'paymentTerms' => ['nullable', 'string', 'max:255'],
            'deliveryTerms' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'warrantyTerms' => ['nullable', 'string', 'max:2000'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
            'complianceNotes' => ['nullable', 'string', 'max:2000'],
            'buyerNotes' => ['nullable', 'string', 'max:2000'],
            'vendorNotes' => ['nullable', 'string', 'max:2000'],
            'attachmentIds' => ['nullable', 'array'],
            'attachmentIds.*' => ['integer', 'distinct'],
            'lineItems' => ['required', 'array'],
            'lineItems.*.rfqLineItemId' => ['nullable', 'string', 'max:120'],
            'lineItems.*.description' => ['required', 'string', 'max:500'],
            'lineItems.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lineItems.*.unit' => ['nullable', 'string', 'max:50'],
            'lineItems.*.unitPrice' => ['nullable', 'numeric', 'min:0'],
            'lineItems.*.subtotalAmount' => ['nullable', 'numeric', 'min:0'],
            'lineItems.*.taxAmount' => ['nullable', 'numeric', 'min:0'],
            'lineItems.*.totalAmount' => ['nullable', 'numeric', 'min:0'],
            'lineItems.*.leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'lineItems.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'lineItems.*.modelNumber' => ['nullable', 'string', 'max:255'],
            'lineItems.*.alternateOffered' => ['nullable', 'boolean'],
            'lineItems.*.complianceStatus' => ['nullable', 'string', 'in:compliant,partial,non_compliant,alternate'],
            'lineItems.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => 'validation_failed',
                'message' => 'The submitted quotation revision is invalid.',
                'details' => ['fields' => $validator->errors()->toArray()],
                'requestId' => null,
            ],
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
```

- [x] Add version line item resource.

Create `apps/api/Domains/Quotation/Http/Resources/QuotationVersionLineItemResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationVersionLineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationVersionLineItem
 */
class QuotationVersionLineItemResource extends JsonResource
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
            'alternateOffered' => (bool) $this->alternate_offered,
            'complianceStatus' => $this->compliance_status,
            'notes' => $this->notes,
        ];
    }
}
```

- [x] Add version resource.

Create `apps/api/Domains/Quotation/Http/Resources/QuotationVersionResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuotationVersion
 */
class QuotationVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vendorPortal = (bool) $request->attributes->get('vendor_portal', false);

        return [
            'id' => (string) $this->id,
            'quotationId' => (string) $this->quotation_id,
            'versionNumber' => $this->version_number,
            'status' => $this->status?->value ?? $this->status,
            'source' => $this->submission_source?->value ?? $this->submission_source,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'submittedByUser' => $vendorPortal || ! $this->submittedByUser ? null : [
                'id' => (string) $this->submittedByUser->id,
                'name' => $this->submittedByUser->name,
            ],
            'submittedByVendorContact' => $this->submitted_by_vendor_contact,
            'isCurrent' => (bool) $this->is_current,
            'supersededAt' => $this->superseded_at?->toISOString(),
            'previousVersionId' => data_get($this->metadata, 'previousVersionId'),
            'manualEntry' => [
                'quotationReference' => $this->quotation_reference,
                'quotedAt' => $this->quoted_at?->toDateString(),
                'validUntil' => $this->valid_until?->toDateString(),
                'currency' => $this->currency,
                'subtotalAmount' => $this->subtotal_amount,
                'taxAmount' => $this->tax_amount,
                'freightAmount' => $this->freight_amount,
                'discountAmount' => $this->discount_amount,
                'totalAmount' => $this->total_amount,
                'paymentTerms' => $this->payment_terms,
                'deliveryTerms' => $this->delivery_terms,
                'leadTimeDays' => $this->lead_time_days,
                'warrantyTerms' => $this->warranty_terms,
                'exclusions' => $this->exclusions,
                'complianceNotes' => $this->compliance_notes,
                'buyerNotes' => $vendorPortal ? null : $this->buyer_notes,
                'vendorNotes' => $this->vendor_notes,
            ],
            'lineItems' => $this->relationLoaded('lineItems')
                ? QuotationVersionLineItemResource::collection($this->lineItems)
                : [],
            'attachments' => $this->attachment_snapshots ?? [],
            'attachmentCount' => count($this->attachment_snapshots ?? []),
            'completeness' => [
                'isComplete' => (bool) $this->manual_entry_complete,
                'missingFields' => $this->manual_entry_missing_fields ?? [],
                'lineItemCount' => $this->relationLoaded('lineItems') ? $this->lineItems->count() : 0,
            ],
            'permissions' => [
                'canEdit' => false,
                'canCreateRevision' => $vendorPortal
                    ? (bool) $request->attributes->get('vendor_portal_can_edit_quotation', false)
                    : ($request->user()?->can('view', $this->quotation?->rfq) ?? false),
            ],
        ];
    }
}
```

- [x] Add buyer version controller.

Create `apps/api/Domains/Quotation/Http/Controllers/QuotationVersionController.php`:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class QuotationVersionController extends Controller
{
    public function index(CurrentTenant $currentTenant, int $quotation): AnonymousResourceCollection
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);

        return QuotationVersionResource::collection(
            QuotationVersion::query()
                ->with(['lineItems', 'submittedByUser', 'quotation.rfq'])
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $model->id)
                ->orderByDesc('version_number')
                ->get()
        );
    }

    public function show(CurrentTenant $currentTenant, int $quotation, int $version): QuotationVersionResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);

        return new QuotationVersionResource(
            QuotationVersion::query()
                ->with(['lineItems', 'submittedByUser', 'quotation.rfq'])
                ->where('tenant_id', $tenant->id)
                ->where('quotation_id', $model->id)
                ->findOrFail($version)
        );
    }

    public function store(
        CreateQuotationRevisionRequest $request,
        CurrentTenant $currentTenant,
        int $quotation,
        CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
    ): JsonResponse {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = $this->findTenantQuotation($tenant, $quotation);
        $this->authorize('view', $model->rfq);
        $this->ensureInvitationAcceptsQuotation($model);

        $version = DB::transaction(function () use ($tenant, $model, $request, $createQuotationVersionSnapshot): QuotationVersion {
            $this->applyRevisionPayload($tenant, $model, $request->validated(), QuotationSubmissionSource::BuyerUpload);

            return $createQuotationVersionSnapshot->handle(
                $tenant,
                $model,
                $request->user(),
                QuotationSubmissionSource::BuyerUpload,
                $request->validated('attachmentIds'),
                ['trigger' => 'buyer_revision'],
            );
        });

        return (new QuotationVersionResource($version))->response()->setStatusCode(201);
    }

    private function findTenantQuotation(Tenant $tenant, int $id): Quotation
    {
        return Quotation::query()
            ->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems', 'attachments'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($id);
    }

    private function ensureInvitationAcceptsQuotation(Quotation $quotation): void
    {
        $status = $quotation->rfqInvitation?->status;
        $value = $status instanceof RfqInvitationStatus ? $status->value : $status;

        if (! in_array($value, [RfqInvitationStatus::Sent->value, RfqInvitationStatus::Acknowledged->value], true)) {
            throw new ConflictHttpException('This invitation is not accepting quotation revisions.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyRevisionPayload(Tenant $tenant, Quotation $quotation, array $payload, QuotationSubmissionSource $source): void
    {
        $quotation->forceFill([
            'status' => QuotationStatus::Received->value,
            'submission_source' => $source->value,
            'quotation_reference' => $payload['quotationReference'] ?? null,
            'quoted_at' => $payload['quotedAt'] ?? null,
            'valid_until' => $payload['validUntil'] ?? null,
            'currency' => isset($payload['currency']) ? strtoupper($payload['currency']) : null,
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
            'buyer_notes' => $payload['buyerNotes'] ?? null,
            'vendor_notes' => $payload['vendorNotes'] ?? null,
            'manual_entry_complete' => filled($payload['currency'] ?? null) && filled($payload['totalAmount'] ?? null) && count($payload['lineItems'] ?? []) > 0,
            'manual_entry_missing_fields' => $this->missingFields($payload),
            'manual_entry_saved_at' => now(),
            'manual_entry_saved_source' => $source->value,
            'latest_received_at' => now(),
        ])->save();

        QuotationLineItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_id', $quotation->id)
            ->delete();

        collect($payload['lineItems'] ?? [])->values()->each(function (array $lineItem, int $index) use ($tenant, $quotation): void {
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

        $quotation->load(['lineItems', 'attachments' => fn ($query) => $query->with('uploader')->latest('created_at')]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function missingFields(array $payload): array
    {
        return collect([
            blank($payload['currency'] ?? null) ? 'currency' : null,
            blank($payload['totalAmount'] ?? null) ? 'totalAmount' : null,
            count($payload['lineItems'] ?? []) === 0 ? 'lineItems' : null,
        ])->filter()->values()->all();
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        return $currentTenant->get();
    }
}
```

- [x] Add vendor portal version controller.

Create `apps/api/Domains/Quotation/Http/Controllers/VendorPortalQuotationVersionController.php` using the buyer controller patterns, with these required differences:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Http\Requests\CreateQuotationRevisionRequest;
use Domains\Quotation\Http\Resources\QuotationVersionResource;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use App\Tenancy\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VendorPortalQuotationVersionController extends Controller
{
    public function index(string $token, ResolveRfqInvitationPortalAccess $resolve): AnonymousResourceCollection|JsonResponse
    {
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, request());
        request()->attributes->set('vendor_portal_can_edit_quotation', $invitation->canBeViewedInPortal());

        $quotation = $this->findQuotation($invitation->tenant, $invitation->id);
        if (! $quotation) {
            return response()->json(['data' => []]);
        }

        return QuotationVersionResource::collection(
            QuotationVersion::query()
                ->with(['lineItems', 'quotation.rfq'])
                ->where('tenant_id', $invitation->tenant_id)
                ->where('quotation_id', $quotation->id)
                ->orderByDesc('version_number')
                ->get()
        );
    }

    public function store(
        string $token,
        CreateQuotationRevisionRequest $request,
        ResolveRfqInvitationPortalAccess $resolve,
        CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
    ): JsonResponse {
        $request->attributes->set('vendor_portal', true);
        request()->attributes->set('vendor_portal', true);
        $invitation = $resolve->handle($token, $request);
        $request->attributes->set('vendor_portal_can_edit_quotation', true);
        request()->attributes->set('vendor_portal_can_edit_quotation', true);

        $quotation = $this->findQuotation($invitation->tenant, $invitation->id);
        if (! $quotation) {
            throw new NotFoundHttpException('A quotation must exist before a revision can be submitted.');
        }

        $version = DB::transaction(function () use ($invitation, $quotation, $request, $createQuotationVersionSnapshot): QuotationVersion {
            $payload = $request->validated();
            $this->applyVendorRevisionPayload($invitation->tenant, $quotation, $payload);

            return $createQuotationVersionSnapshot->handle(
                $invitation->tenant,
                $quotation,
                null,
                QuotationSubmissionSource::VendorPortal,
                $payload['attachmentIds'] ?? null,
                ['trigger' => 'vendor_portal_revision'],
            );
        });

        return (new QuotationVersionResource($version))->response()->setStatusCode(201);
    }

    private function findQuotation(Tenant $tenant, int $invitationId): ?Quotation
    {
        return Quotation::query()
            ->with(['rfq', 'vendor', 'rfqInvitation', 'lineItems', 'attachments' => fn ($query) => $query->with('uploader')->latest('created_at')])
            ->where('tenant_id', $tenant->id)
            ->where('rfq_invitation_id', $invitationId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyVendorRevisionPayload(Tenant $tenant, Quotation $quotation, array $payload): void
    {
        $quotation->forceFill([
            'status' => QuotationStatus::Received->value,
            'submission_source' => QuotationSubmissionSource::VendorPortal->value,
            'quotation_reference' => $payload['quotationReference'] ?? null,
            'quoted_at' => $payload['quotedAt'] ?? null,
            'valid_until' => $payload['validUntil'] ?? null,
            'currency' => isset($payload['currency']) ? strtoupper($payload['currency']) : null,
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
            'vendor_notes' => $payload['vendorNotes'] ?? null,
            'manual_entry_complete' => filled($payload['currency'] ?? null) && filled($payload['totalAmount'] ?? null) && count($payload['lineItems'] ?? []) > 0,
            'manual_entry_missing_fields' => collect([
                blank($payload['currency'] ?? null) ? 'currency' : null,
                blank($payload['totalAmount'] ?? null) ? 'totalAmount' : null,
                count($payload['lineItems'] ?? []) === 0 ? 'lineItems' : null,
            ])->filter()->values()->all(),
            'manual_entry_saved_at' => now(),
            'manual_entry_saved_source' => QuotationSubmissionSource::VendorPortal->value,
            'latest_received_at' => now(),
        ])->save();

        QuotationLineItem::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_id', $quotation->id)
            ->delete();

        collect($payload['lineItems'] ?? [])->values()->each(function (array $lineItem, int $index) use ($tenant, $quotation): void {
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

        $quotation->load(['lineItems', 'attachments' => fn ($query) => $query->with('uploader')->latest('created_at')]);
    }
}
```

- [x] Extend `QuotationResource`.

Add `currentVersion` and `versionCount` to the response in `apps/api/Domains/Quotation/Http/Resources/QuotationResource.php`:

```php
'versionCount' => $quotation->version_count,
'currentVersion' => $quotation->relationLoaded('currentVersion') && $quotation->currentVersion
    ? [
        'id' => (string) $quotation->currentVersion->id,
        'versionNumber' => $quotation->currentVersion->version_number,
        'isCurrent' => (bool) $quotation->currentVersion->is_current,
        'attachmentCount' => count($quotation->currentVersion->attachment_snapshots ?? []),
    ]
    : null,
```

Add `canCreateRevision` to the `permissions` object:

```php
'canCreateRevision' => $canEditManualEntry,
```

Update quotation loads in `RfqInvitationQuotationController` and `VendorPortalQuotationController` to include `currentVersion.lineItems`.

- [x] Register routes.

In `apps/api/routes/api.php`, add the vendor portal route near existing vendor portal quotation routes:

```php
Route::get('/vendor-portal/rfq-invitations/{token}/quotation/versions', [VendorPortalQuotationVersionController::class, 'index'])
    ->middleware('throttle:60,1');
Route::post('/vendor-portal/rfq-invitations/{token}/quotation/versions', [VendorPortalQuotationVersionController::class, 'store'])
    ->middleware('throttle:60,1');
```

Add imports:

```php
use Domains\Quotation\Http\Controllers\QuotationVersionController;
use Domains\Quotation\Http\Controllers\VendorPortalQuotationVersionController;
```

Inside the authenticated tenant route group, add:

```php
Route::get('/quotations/{quotation}/versions', [QuotationVersionController::class, 'index']);
Route::get('/quotations/{quotation}/versions/{version}', [QuotationVersionController::class, 'show']);
Route::post('/quotations/{quotation}/versions', [QuotationVersionController::class, 'store']);
```

- [x] Run backend tests for endpoint implementation.

Run:

```bash
php artisan test --filter=QuotationVersionApiTest
php artisan test --filter=QuotationManualEntryApiTest
php artisan test --filter=QuotationUploadApiTest
php artisan test --filter=RfqInvitationPortalApiTest
```

Expected result: all listed backend suites pass.

---

## Task 5: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/**`

- [x] Add OpenAPI schemas.

Add these component schemas to `apps/api/storage/openapi/openapi.json`:

```json
"QuotationVersion": {
  "type": "object",
  "required": [
    "id",
    "quotationId",
    "versionNumber",
    "status",
    "source",
    "submittedAt",
    "submittedByUser",
    "submittedByVendorContact",
    "isCurrent",
    "supersededAt",
    "previousVersionId",
    "manualEntry",
    "lineItems",
    "attachments",
    "attachmentCount",
    "completeness",
    "permissions"
  ],
  "properties": {
    "id": { "type": "string" },
    "quotationId": { "type": "string" },
    "versionNumber": { "type": "integer", "minimum": 1 },
    "status": { "type": "string", "enum": ["draft", "received", "withdrawn", "superseded"] },
    "source": { "type": ["string", "null"], "enum": ["vendor_portal", "buyer_upload", null] },
    "submittedAt": { "type": ["string", "null"], "format": "date-time" },
    "submittedByUser": {
      "oneOf": [
        { "$ref": "#/components/schemas/UserSummary" },
        { "type": "null" }
      ]
    },
    "submittedByVendorContact": {
      "oneOf": [
        { "$ref": "#/components/schemas/QuotationVendorContact" },
        { "type": "null" }
      ]
    },
    "isCurrent": { "type": "boolean" },
    "supersededAt": { "type": ["string", "null"], "format": "date-time" },
    "previousVersionId": { "type": ["string", "null"] },
    "manualEntry": { "$ref": "#/components/schemas/QuotationManualEntry" },
    "lineItems": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/QuotationLineItem" }
    },
    "attachments": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/QuotationVersionAttachment" }
    },
    "attachmentCount": { "type": "integer", "minimum": 0 },
    "completeness": { "$ref": "#/components/schemas/QuotationCompleteness" },
    "permissions": { "$ref": "#/components/schemas/QuotationVersionPermissions" }
  }
},
"QuotationVersionAttachment": {
  "type": "object",
  "required": [
    "id",
    "filename",
    "mimeType",
    "extension",
    "sizeBytes",
    "checksumSha256",
    "previewable",
    "uploadedBy",
    "createdAt",
    "available"
  ],
  "properties": {
    "id": { "type": "string" },
    "filename": { "type": "string" },
    "mimeType": { "type": "string" },
    "extension": { "type": ["string", "null"] },
    "sizeBytes": { "type": "integer", "minimum": 0 },
    "checksumSha256": { "type": ["string", "null"] },
    "previewable": { "type": "boolean" },
    "uploadedBy": {
      "oneOf": [
        { "$ref": "#/components/schemas/UserSummary" },
        { "type": "null" }
      ]
    },
    "createdAt": { "type": ["string", "null"], "format": "date-time" },
    "available": { "type": "boolean" }
  }
},
"QuotationVersionPermissions": {
  "type": "object",
  "required": ["canEdit", "canCreateRevision"],
  "properties": {
    "canEdit": { "type": "boolean" },
    "canCreateRevision": { "type": "boolean" }
  }
},
"QuotationVersionResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/QuotationVersion" }
  }
},
"QuotationVersionListResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/QuotationVersion" }
    }
  }
},
"CreateQuotationRevisionRequest": {
  "allOf": [
    { "$ref": "#/components/schemas/SaveQuotationManualEntryRequest" },
    {
      "type": "object",
      "properties": {
        "attachmentIds": {
          "type": "array",
          "items": { "type": "integer" }
        }
      }
    }
  ]
}
```

Extend `Quotation` and `QuotationVendorPortal` schemas with:

```json
"versionCount": { "type": "integer", "minimum": 0 },
"currentVersion": {
  "oneOf": [
    { "$ref": "#/components/schemas/QuotationCurrentVersionSummary" },
    { "type": "null" }
  ]
}
```

Add `QuotationCurrentVersionSummary`:

```json
"QuotationCurrentVersionSummary": {
  "type": "object",
  "required": ["id", "versionNumber", "isCurrent", "attachmentCount"],
  "properties": {
    "id": { "type": "string" },
    "versionNumber": { "type": "integer", "minimum": 1 },
    "isCurrent": { "type": "boolean" },
    "attachmentCount": { "type": "integer", "minimum": 0 }
  }
}
```

Add `canCreateRevision` to `QuotationPermissions`.

- [x] Add OpenAPI operations.

Add these paths:

```json
"/api/quotations/{quotation}/versions": {
  "get": {
    "operationId": "listQuotationVersions",
    "tags": ["Quotations"],
    "parameters": [
      { "name": "quotation", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "responses": {
      "200": {
        "description": "Quotation versions",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/QuotationVersionListResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  },
  "post": {
    "operationId": "createQuotationVersion",
    "tags": ["Quotations"],
    "parameters": [
      { "name": "quotation", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/CreateQuotationRevisionRequest" }
        }
      }
    },
    "responses": {
      "201": {
        "description": "Created quotation version",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/QuotationVersionResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
},
"/api/quotations/{quotation}/versions/{version}": {
  "get": {
    "operationId": "showQuotationVersion",
    "tags": ["Quotations"],
    "parameters": [
      { "name": "quotation", "in": "path", "required": true, "schema": { "type": "integer" } },
      { "name": "version", "in": "path", "required": true, "schema": { "type": "integer" } }
    ],
    "responses": {
      "200": {
        "description": "Quotation version",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/QuotationVersionResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "404": { "$ref": "#/components/responses/NotFound" }
    }
  }
},
"/api/vendor-portal/rfq-invitations/{token}/quotation/versions": {
  "get": {
    "operationId": "listVendorPortalQuotationVersions",
    "tags": ["Vendor Portal"],
    "parameters": [
      { "name": "token", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "responses": {
      "200": {
        "description": "Vendor-safe quotation versions",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/QuotationVersionListResponse" }
          }
        }
      },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" }
    }
  },
  "post": {
    "operationId": "createVendorPortalQuotationVersion",
    "tags": ["Vendor Portal"],
    "parameters": [
      { "name": "token", "in": "path", "required": true, "schema": { "type": "string" } }
    ],
    "requestBody": {
      "required": true,
      "content": {
        "application/json": {
          "schema": { "$ref": "#/components/schemas/CreateQuotationRevisionRequest" }
        }
      }
    },
    "responses": {
      "201": {
        "description": "Created vendor quotation version",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/QuotationVersionResponse" }
          }
        }
      },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/Conflict" },
      "422": { "$ref": "#/components/responses/ValidationError" }
    }
  }
}
```

- [x] Generate and verify API client.

Run:

```bash
pnpm check:api-contract
```

Expected result on the first run: generated client files may be left in the working tree with a drift message. Keep the generated files, inspect new endpoint and schema names, then rerun:

```bash
pnpm check:api-contract
```

Expected final result: exits successfully with no generated-client drift.

---

## Task 6: Buyer Version UI, MSW, And Tests

**Files:**

- Modify: `apps/web/features/sourcing/api/quotation-api.ts`
- Create: `apps/web/features/sourcing/hooks/use-quotation-versions.ts`
- Create: `apps/web/features/sourcing/components/quotation-version-history.tsx`
- Create: `apps/web/features/sourcing/components/quotation-version-detail.tsx`
- Modify: `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`
- Modify: `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- Modify: `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`

- [x] Add buyer API wrappers.

In `apps/web/features/sourcing/api/quotation-api.ts`, import generated endpoints and schemas:

```ts
import {
  createQuotationVersion as createQuotationVersionEndpoint,
  listQuotationVersions as listQuotationVersionsEndpoint,
  showQuotationVersion as showQuotationVersionEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  CreateQuotationRevisionRequest,
  QuotationVersion,
} from "@cognify/api-client/schemas";
```

Add wrappers:

```ts
export async function listQuotationVersions(quotationId: string, tenantId?: string | null): Promise<QuotationVersion[]> {
  const response = await listQuotationVersionsEndpoint(Number(quotationId), withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function showQuotationVersion(
  quotationId: string,
  versionId: string,
  tenantId?: string | null,
): Promise<QuotationVersion> {
  const response = await showQuotationVersionEndpoint(
    Number(quotationId),
    Number(versionId),
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createQuotationVersion(
  quotationId: string,
  payload: CreateQuotationRevisionRequest,
  tenantId?: string | null,
): Promise<QuotationVersion> {
  const response = await createQuotationVersionEndpoint(
    Number(quotationId),
    payload,
    withActiveTenantHeader(tenantId),
  );
  if (response.status !== 201) throw response.data;

  return response.data.data;
}
```

- [x] Add buyer version hooks.

Create `apps/web/features/sourcing/hooks/use-quotation-versions.ts`:

```ts
"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useActiveTenantId } from "../../auth/hooks/use-active-tenant-id";
import type { CreateQuotationRevisionRequest } from "@cognify/api-client/schemas";
import {
  createQuotationVersion,
  listQuotationVersions,
  showQuotationVersion,
} from "../api/quotation-api";
import { quotationKeys } from "./use-quotation-upload";

export const quotationVersionKeys = {
  all: ["quotation-versions"] as const,
  list: (quotationId: string | null | undefined) => [...quotationVersionKeys.all, quotationId, "list"] as const,
  detail: (quotationId: string | null | undefined, versionId: string | null | undefined) =>
    [...quotationVersionKeys.all, quotationId, "detail", versionId] as const,
};

export function useQuotationVersions(quotationId: string | null | undefined) {
  const tenantId = useActiveTenantId();

  return useQuery({
    queryKey: quotationVersionKeys.list(quotationId),
    queryFn: () => listQuotationVersions(quotationId as string, tenantId),
    enabled: Boolean(quotationId),
    retry: false,
  });
}

export function useQuotationVersion(quotationId: string | null | undefined, versionId: string | null | undefined) {
  const tenantId = useActiveTenantId();

  return useQuery({
    queryKey: quotationVersionKeys.detail(quotationId, versionId),
    queryFn: () => showQuotationVersion(quotationId as string, versionId as string, tenantId),
    enabled: Boolean(quotationId && versionId),
    retry: false,
  });
}

export function useCreateQuotationVersion(quotationId: string | null | undefined, invitationId: string) {
  const tenantId = useActiveTenantId();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateQuotationRevisionRequest) =>
      createQuotationVersion(quotationId as string, payload, tenantId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: quotationVersionKeys.list(quotationId) });
      queryClient.invalidateQueries({ queryKey: quotationKeys.byInvitation(invitationId) });
    },
  });
}
```

- [x] Add buyer version history component.

Create `apps/web/features/sourcing/components/quotation-version-history.tsx`:

```tsx
"use client";

import type { QuotationVersion } from "@cognify/api-client/schemas";
import { Button } from "@cognify/ui";

export function QuotationVersionHistory({
  versions,
  selectedVersionId,
  onSelectVersion,
}: {
  versions: QuotationVersion[];
  selectedVersionId: string | null;
  onSelectVersion: (versionId: string) => void;
}) {
  if (versions.length === 0) {
    return <p className="text-sm text-muted-foreground">No quotation versions recorded yet.</p>;
  }

  return (
    <div className="space-y-2">
      <h4 className="text-sm font-semibold">Version history</h4>
      <div className="flex flex-wrap gap-2">
        {versions.map((version) => (
          <Button
            key={version.id}
            type="button"
            variant={selectedVersionId === version.id ? "default" : "outline"}
            size="sm"
            onClick={() => onSelectVersion(version.id)}
          >
            Version {version.versionNumber}
            {version.isCurrent ? " current" : ""}
          </Button>
        ))}
      </div>
    </div>
  );
}
```

- [x] Add buyer selected version detail component.

Create `apps/web/features/sourcing/components/quotation-version-detail.tsx`:

```tsx
import type { QuotationVersion } from "@cognify/api-client/schemas";

export function QuotationVersionDetail({ version }: { version: QuotationVersion | null }) {
  if (!version) {
    return null;
  }

  return (
    <section className="rounded-md border p-3">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h4 className="text-sm font-semibold">Version {version.versionNumber}</h4>
          <p className="text-sm text-muted-foreground">
            {version.isCurrent ? "Current quotation version" : "Previous quotation version"}
          </p>
        </div>
        <p className="text-sm font-medium">{version.manualEntry.totalAmount ?? "No total recorded"}</p>
      </div>

      <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
        <div>
          <dt className="text-muted-foreground">Reference</dt>
          <dd>{version.manualEntry.quotationReference ?? "Not recorded"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Currency</dt>
          <dd>{version.manualEntry.currency ?? "Not recorded"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Attachments</dt>
          <dd>{version.attachmentCount}</dd>
        </div>
      </dl>

      <div className="mt-3 overflow-x-auto rounded-md border">
        <table className="min-w-[42rem] w-full text-sm">
          <thead className="bg-muted/40">
            <tr>
              <th className="border-b px-3 py-2 text-left font-medium">Description</th>
              <th className="border-b px-3 py-2 text-left font-medium">Quantity</th>
              <th className="border-b px-3 py-2 text-left font-medium">Unit price</th>
              <th className="border-b px-3 py-2 text-left font-medium">Total</th>
            </tr>
          </thead>
          <tbody>
            {version.lineItems.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-3 py-4 text-muted-foreground">
                  No line items captured for this version.
                </td>
              </tr>
            ) : null}
            {version.lineItems.map((line) => (
              <tr key={line.id}>
                <td className="border-b px-3 py-2">{line.description}</td>
                <td className="border-b px-3 py-2">{line.quantity}</td>
                <td className="border-b px-3 py-2">{line.unitPrice ?? "-"}</td>
                <td className="border-b px-3 py-2">{line.totalAmount ?? "-"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
```

- [x] Render buyer version history from `QuotationEvidencePanel`.

In `apps/web/features/sourcing/components/quotation-evidence-panel.tsx`, import:

```tsx
import { useEffect, useMemo, useState } from "react";
import { useQuotationVersion, useQuotationVersions } from "../hooks/use-quotation-versions";
import { QuotationVersionDetail } from "./quotation-version-detail";
import { QuotationVersionHistory } from "./quotation-version-history";
```

If the file already imports `useState`, merge imports into one React import.

Inside the component, after `const quotation = quotationQuery.data ?? null;`, add:

```tsx
const versionsQuery = useQuotationVersions(quotation?.id);
const versions = useMemo(() => versionsQuery.data ?? [], [versionsQuery.data]);
const [selectedVersionId, setSelectedVersionId] = useState<string | null>(null);
const selectedVersionQuery = useQuotationVersion(quotation?.id, selectedVersionId);

useEffect(() => {
  if (!selectedVersionId && versions[0]) {
    setSelectedVersionId(versions[0].id);
  }
}, [selectedVersionId, versions]);
```

Render this block below `QuotationManualEntryPanel` when `quotation` exists:

```tsx
<QuotationVersionHistory
  versions={versions}
  selectedVersionId={selectedVersionId}
  onSelectVersion={setSelectedVersionId}
/>
<QuotationVersionDetail version={selectedVersionQuery.data ?? versions.find((version) => version.id === selectedVersionId) ?? null} />
```

- [x] Extend sourcing MSW handlers with version state.

In `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`, add version arrays to the quotation fixture shape. Add handlers:

```ts
http.get("/api/quotations/:quotationId/versions", ({ params }) => {
  const quotation = findQuotationFixture(String(params.quotationId));
  if (!quotation) {
    return HttpResponse.json({ error: { code: "not_found", message: "Quotation not found." } }, { status: 404 });
  }

  return HttpResponse.json({ data: structuredClone(quotation.versions ?? []) });
}),

http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
  const quotation = findQuotationFixture(String(params.quotationId));
  const version = quotation?.versions?.find((candidate) => candidate.id === String(params.versionId));
  if (!version) {
    return HttpResponse.json({ error: { code: "not_found", message: "Quotation version not found." } }, { status: 404 });
  }

  return HttpResponse.json({ data: structuredClone(version) });
}),
```

Ensure `updateQuotationManualEntry()` appends a new version object with:

```ts
{
  id: `quotation-version-${nextVersionNumber}`,
  quotationId: quotation.id,
  versionNumber: nextVersionNumber,
  status: "received",
  source,
  submittedAt: new Date().toISOString(),
  submittedByUser: source === "buyer_upload" ? { id: "2", name: "Buyer User" } : null,
  submittedByVendorContact: null,
  isCurrent: true,
  supersededAt: null,
  previousVersionId: previousVersion?.id ?? null,
  manualEntry,
  lineItems,
  attachments: quotation.attachments.map(toVersionAttachmentSnapshot),
  attachmentCount: quotation.attachments.length,
  completeness,
  permissions: {
    canEdit: false,
    canCreateRevision: true,
  },
}
```

- [x] Add buyer workflow test.

Append to `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`:

```tsx
it("shows quotation version history after structured buyer revisions", async () => {
  const user = userEvent.setup();
  render(<RfqInvitationPanel rfqId="rfq-1" />, { wrapper: TestProviders });

  await user.click(await screen.findByRole("button", { name: "View quotations" }));
  await user.click(screen.getByRole("button", { name: "Create structured quotation" }));
  await user.clear(await screen.findByLabelText("Quotation reference"));
  await user.type(screen.getByLabelText("Quotation reference"), "NW-Q-2026-041");
  await user.clear(screen.getByLabelText("Currency"));
  await user.type(screen.getByLabelText("Currency"), "USD");
  await user.clear(screen.getByLabelText("Total amount"));
  await user.type(screen.getByLabelText("Total amount"), "12470.00");
  await user.click(screen.getByRole("button", { name: "Add quoted line" }));
  await user.type(screen.getByLabelText("Line 1 description"), "Developer laptop");
  await user.type(screen.getByLabelText("Line 1 quantity"), "10");
  await user.click(screen.getByRole("button", { name: "Save structured quotation" }));

  expect(await screen.findByText("Structured quotation saved.")).toBeInTheDocument();
  expect(await screen.findByRole("button", { name: "Version 1 current" })).toBeInTheDocument();

  await user.clear(screen.getByLabelText("Total amount"));
  await user.type(screen.getByLabelText("Total amount"), "11990.00");
  await user.click(screen.getByRole("button", { name: "Save structured quotation" }));

  expect(await screen.findByRole("button", { name: "Version 2 current" })).toBeInTheDocument();
  await user.click(screen.getByRole("button", { name: "Version 1" }));
  expect(await screen.findByText("12470.00")).toBeInTheDocument();
  await user.click(screen.getByRole("button", { name: "Version 2 current" }));
  expect(await screen.findByText("11990.00")).toBeInTheDocument();
});
```

- [x] Run buyer frontend tests.

Run:

```bash
pnpm --dir apps/web exec vitest run features/sourcing/tests/rfq-invitations-workflow.test.tsx --reporter=dot
```

Expected result: sourcing workflow tests pass.

---

## Task 7: Vendor Portal Version UI, MSW, And Tests

**Files:**

- Modify: `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- Modify: `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`
- Create: `apps/web/features/vendor-portal/components/vendor-quotation-version-history.tsx`
- Modify: `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- Modify: `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- Modify: `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`

- [x] Add vendor portal API wrappers.

In `apps/web/features/vendor-portal/api/vendor-portal-api.ts`, import generated endpoints and schemas:

```ts
import {
  createVendorPortalQuotationVersion as createVendorPortalQuotationVersionEndpoint,
  listVendorPortalQuotationVersions as listVendorPortalQuotationVersionsEndpoint,
} from "@cognify/api-client/endpoints";
import type { CreateQuotationRevisionRequest, QuotationVersion } from "@cognify/api-client/schemas";
```

Add:

```ts
export async function listVendorPortalQuotationVersions(token: string): Promise<QuotationVersion[]> {
  const response = await listVendorPortalQuotationVersionsEndpoint(token);
  if (response.status !== 200) throw response.data;

  return response.data.data;
}

export async function createVendorPortalQuotationVersion(
  token: string,
  payload: CreateQuotationRevisionRequest,
): Promise<QuotationVersion> {
  const response = await createVendorPortalQuotationVersionEndpoint(token, payload);
  if (response.status !== 201) throw response.data;

  return response.data.data;
}
```

- [x] Add vendor portal version hooks.

In `apps/web/features/vendor-portal/hooks/use-vendor-quotation.ts`, add:

```ts
import type { CreateQuotationRevisionRequest } from "@cognify/api-client/schemas";
import {
  createVendorPortalQuotationVersion,
  listVendorPortalQuotationVersions,
} from "../api/vendor-portal-api";
```

Add:

```ts
export function useVendorQuotationVersions(token: string) {
  return useQuery({
    queryKey: [...vendorPortalKeys.quotation(token), "versions"],
    queryFn: () => listVendorPortalQuotationVersions(token),
    enabled: token.length > 0,
    retry: false,
  });
}

export function useCreateVendorQuotationVersion(token: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: CreateQuotationRevisionRequest) => createVendorPortalQuotationVersion(token, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: vendorPortalKeys.quotation(token) });
      queryClient.invalidateQueries({ queryKey: [...vendorPortalKeys.quotation(token), "versions"] });
    },
  });
}
```

- [x] Add vendor version history component.

Create `apps/web/features/vendor-portal/components/vendor-quotation-version-history.tsx`:

```tsx
"use client";

import type { QuotationVersion } from "@cognify/api-client/schemas";

export function VendorQuotationVersionHistory({ versions }: { versions: QuotationVersion[] }) {
  return (
    <section className="rounded-md border p-4">
      <h3 className="text-base font-semibold">Quotation versions</h3>
      {versions.length === 0 ? (
        <p className="mt-2 text-sm text-muted-foreground">No quotation versions have been submitted yet.</p>
      ) : (
        <ul className="mt-3 space-y-2 text-sm">
          {versions.map((version) => (
            <li key={version.id} className="rounded-md border px-3 py-2">
              <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <span className="font-medium">
                  Version {version.versionNumber}
                  {version.isCurrent ? " current" : ""}
                </span>
                <span>{version.manualEntry.totalAmount ?? "No total recorded"}</span>
              </div>
              <p className="mt-1 text-muted-foreground">
                {version.manualEntry.quotationReference ?? "No reference recorded"}
              </p>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
```

- [x] Render vendor version history.

In `apps/web/features/vendor-portal/components/vendor-quotation-upload-panel.tsx`, import:

```tsx
import { useVendorQuotationVersions } from "../hooks/use-vendor-quotation";
import { VendorQuotationVersionHistory } from "./vendor-quotation-version-history";
```

Inside the component:

```tsx
const versionsQuery = useVendorQuotationVersions(token);
const versions = versionsQuery.data ?? [];
```

Render below `VendorQuotationManualEntryPanel`:

```tsx
<VendorQuotationVersionHistory versions={versions} />
```

- [x] Extend vendor portal mocks.

In `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`, add `vendorPortalQuotationVersions` state and reset it in `resetVendorPortalMockState()`. Update `updateVendorPortalQuotationManualEntry()` so each save appends a vendor-safe `QuotationVersion` with `buyerNotes: null`.

Add exported helpers:

```ts
export function getVendorPortalQuotationVersionsFixture() {
  return vendorPortalQuotationVersions;
}

export function appendVendorPortalQuotationVersion(payload: CreateQuotationRevisionRequest) {
  const quotation = updateVendorPortalQuotationManualEntry({
    ...payload,
    buyerNotes: null,
  });
  const previousVersion = vendorPortalQuotationVersions[0] ?? null;
  vendorPortalQuotationVersions = [
    {
      id: `vendor-quotation-version-${vendorPortalQuotationVersions.length + 1}`,
      quotationId: quotation.id,
      versionNumber: vendorPortalQuotationVersions.length + 1,
      status: "received",
      source: "vendor_portal",
      submittedAt: new Date().toISOString(),
      submittedByUser: null,
      submittedByVendorContact: quotation.submittedByVendorContact,
      isCurrent: true,
      supersededAt: null,
      previousVersionId: previousVersion?.id ?? null,
      manualEntry: {
        ...quotation.manualEntry,
        buyerNotes: null,
      },
      lineItems: quotation.lineItems,
      attachments: quotation.attachments.map((attachment) => ({
        id: attachment.id,
        filename: attachment.filename,
        mimeType: attachment.mimeType,
        extension: attachment.extension,
        sizeBytes: attachment.sizeBytes,
        checksumSha256: null,
        previewable: attachment.previewable,
        uploadedBy: null,
        createdAt: attachment.createdAt,
        available: true,
      })),
      attachmentCount: quotation.attachments.length,
      completeness: quotation.completeness,
      permissions: {
        canEdit: false,
        canCreateRevision: true,
      },
    },
    ...vendorPortalQuotationVersions.map((version) => ({
      ...version,
      isCurrent: false,
      supersededAt: new Date().toISOString(),
    })),
  ];

  return vendorPortalQuotationVersions[0];
}
```

- [x] Add vendor portal version handlers.

In `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`, add:

```ts
http.get("/api/vendor-portal/rfq-invitations/:token/quotation/versions", ({ params }) => {
  const token = String(params.token);

  if (token !== validVendorPortalToken) {
    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }

  return HttpResponse.json({ data: structuredClone(getVendorPortalQuotationVersionsFixture()) });
}),

http.post("/api/vendor-portal/rfq-invitations/:token/quotation/versions", async ({ request, params }) => {
  const token = String(params.token);

  if (token !== validVendorPortalToken) {
    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }

  const payload = (await request.json()) as CreateQuotationRevisionRequest;
  const version = appendVendorPortalQuotationVersion(payload);

  return HttpResponse.json({ data: structuredClone(version) }, { status: 201 });
}),
```

- [x] Add vendor portal workflow test.

Append to `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`:

```tsx
it("shows vendor-safe quotation version history after vendor revisions", async () => {
  const user = userEvent.setup();
  render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

  expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
  await user.clear(await screen.findByLabelText("Quotation reference"));
  await user.type(screen.getByLabelText("Quotation reference"), "NW-Q-2026-041");
  await user.clear(screen.getByLabelText("Currency"));
  await user.type(screen.getByLabelText("Currency"), "USD");
  await user.clear(screen.getByLabelText("Total amount"));
  await user.type(screen.getByLabelText("Total amount"), "12470.00");
  await user.type(screen.getByLabelText("Vendor notes"), "Initial vendor submission.");
  await user.click(screen.getByRole("button", { name: "Add quoted line" }));
  await user.type(screen.getByLabelText("Line 1 description"), "Developer laptop");
  await user.type(screen.getByLabelText("Line 1 quantity"), "10");
  await user.click(screen.getByRole("button", { name: "Save quotation details" }));

  expect(await screen.findByText("Version 1 current")).toBeInTheDocument();
  expect(screen.queryByLabelText("Buyer notes")).not.toBeInTheDocument();

  await user.clear(screen.getByLabelText("Total amount"));
  await user.type(screen.getByLabelText("Total amount"), "11990.00");
  await user.click(screen.getByRole("button", { name: "Save quotation details" }));

  expect(await screen.findByText("Version 2 current")).toBeInTheDocument();
  expect(screen.getByText("Version 1")).toBeInTheDocument();
  expect(screen.queryByText("Internal buyer note")).not.toBeInTheDocument();
});
```

- [x] Run vendor portal frontend tests.

Run:

```bash
pnpm --dir apps/web exec vitest run features/vendor-portal/tests/vendor-rfq-portal.test.tsx --reporter=dot
```

Expected result: vendor RFQ portal tests pass.

---

## Task 8: Integration Verification

**Files:**

- Verify all touched API, generated-client, web, and docs surfaces.

- [x] Run focused backend tests.

Run:

```bash
php artisan test --filter=QuotationVersionApiTest
php artisan test --filter=QuotationManualEntryApiTest
php artisan test --filter=QuotationUploadApiTest
php artisan test --filter=RfqInvitationPortalApiTest
php artisan test --filter=SearchApiTest
```

Expected result: all listed backend tests pass.

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

Expected result: all commands exit successfully. If `pnpm build` fails with the known Turbopack `binding to a port` sandbox error, rerun the same command with sandbox escalation and record both outputs.

- [x] Run placeholder scan.

Run:

```bash
terms='T''ODO|T''BD|implement[ ]later|Similar[ ]to|edge[ ]cases|as[ ]appropriate'
rg -n "$terms" apps/api apps/web packages docs/superpowers/plans/2026-05-20-quotation-versioning.md
```

Expected result: no matches introduced by this implementation.

---

## Task 9: Roadmap Loopback

**Files:**

- Modify: `docs/01-product/feature-roadmap.md`
- Modify: `docs/superpowers/plans/2026-05-20-quotation-versioning.md`

- [x] Confirm P1-28 links to this implementation plan.

Expected P1-28 row values:

```markdown
| P1-28 | Quotation Versioning | Track revised quotations and preserve prior versions. Procurement comparisons must show which vendor price and terms were evaluated at decision time. | Fully Implemented | 2026-05-20-quotation-versioning-design.md | 2026-05-20-quotation-versioning.md |  | Implemented as Epic 6 slice 4 with immutable buyer and vendor quotation version history. |
```

- [x] Leave P1-29 as `Not Implemented`.

Do not update P1-29 implementation plan or status during this slice.

- [x] Mark completed plan checkboxes after verification passes.

Use only `- [x]` for tasks actually completed and verified in the implementation session.

---

## Task 10: Self-Review Checklist

- [x] `Quotation` remains the current response container and `QuotationVersion` owns immutable snapshots.
- [x] First upload or manual entry creates version 1.
- [x] Buyer and vendor revisions create version N+1 and supersede the prior current version in one transaction.
- [x] Exactly one version per quotation is current after each mutation.
- [x] Previous versions have no mutation endpoints and resources return `permissions.canEdit: false`.
- [x] Attachment snapshots preserve historical metadata even though files remain owned by the attachment domain.
- [x] Vendor portal version responses redact buyer notes and internal user identity.
- [x] Buyer routes require `auth:sanctum` and `ResolveCurrentTenant`.
- [x] Vendor routes use portal token resolution and do not rely on `X-Tenant-Id`.
- [x] Cross-tenant version list/show/create requests fail.
- [x] Terminal invitation states block vendor-created versions.
- [x] Audit events include `quotation.version_created`, `quotation.version_superseded`, and `quotation.current_version_changed`.
- [x] Generated client endpoints and schemas are used by frontend code.
- [x] UI components do not import mock fixtures directly.
- [x] Quotation normalization, comparison, scoring, award, OCR, and AI extraction remain outside this slice.

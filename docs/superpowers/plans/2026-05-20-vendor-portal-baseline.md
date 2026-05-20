# Vendor Portal Baseline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build P1 Epic 6 slice 1: a secure token-based vendor portal baseline that lets an invited vendor view a read-only RFQ package without vendor accounts or quotation submission.

**Architecture:** `apps/api/Domains/Quotation` owns RFQ invitation portal token state, token issuance, token resolution, vendor-safe resources, policies, audit events, and public/internal controllers. `apps/web/features/vendor-portal` owns the external read-only RFQ package UI, while `apps/web/features/sourcing` keeps the buyer-side RFQ invitation management and gets only a narrow generate/copy portal-link action. OpenAPI remains the source contract and `packages/api-client` is regenerated after contract changes.

**Tech Stack:** Laravel 12, Sanctum for internal buyer/admin routes, public token route without `SessionGate`, Eloquent, OpenAPI/Orval, Next.js App Router, TanStack Query, MSW, Vitest, Testing Library, shadcn/Radix primitives via `@cognify/ui`.

---

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-20-vendor-portal-baseline-design.md`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Architecture: `ARCHITECTURE.md`
- Runbook: `docs/05-runbooks/feature-development.md`
- Predecessor spec: `docs/superpowers/specs/2026-05-19-vendor-invitation-to-rfq-design.md`
- Agent guidance: `AGENTS.md`

## Scope Boundaries

Implement:

- Opaque portal token issuance and hashed token storage on `rfq_invitations`.
- Internal buyer/admin portal-link regeneration endpoint for one invitation.
- Public token endpoint that returns a vendor-safe RFQ invitation package.
- Read-only vendor portal page at `/vendor/rfq-invitations/[token]`.
- Buyer RFQ invitation panel action to generate and copy a portal link.
- Audit events for token creation/regeneration and portal view.
- OpenAPI-generated client usage, MSW fixtures, and focused tests.

Do not implement:

- Vendor accounts, vendor login, vendor registration, or vendor password reset.
- Email delivery of portal links.
- Quotation upload, manual quotation entry, quotation versioning, normalization, comparison, scoring, award, award approval, or PO handoff.
- Vendor profile management or a vendor directory.
- Two-way vendor messaging, Q&A, AI extraction, OCR, or recommendations.

## Design Decisions Locked For Implementation

- Portal tokens are raw random strings returned only by the portal-link regeneration response. Store only `hash('sha256', $rawToken)` in the database.
- Portal access does not use `X-Tenant-Id`; token resolution determines tenant and invitation context.
- Portal read is allowed for `sent` and `acknowledged` invitations until `portal_token_expires_at`. It is unavailable for `cancelled`, `declined`, and `expired` invitations.
- Token expiry is `response_due_at` when present; otherwise `now()->addDays(30)`.
- Unknown tokens return `404` using the existing not-found shape. Expired or unavailable invitations return `409` using the existing invalid-state/conflict shape.
- The vendor-safe payload excludes internal notes, evaluation notes, requester approval context, competing vendors, other invitations, audit timeline, and internal permission maps.
- The buyer-side copy action is a narrow manual handoff because email delivery is out of scope.

## File Map

Backend create:

- `apps/api/database/migrations/2026_05_20_010000_add_portal_access_to_rfq_invitations_table.php`
- `apps/api/Domains/Quotation/Actions/EnsureRfqInvitationPortalToken.php`
- `apps/api/Domains/Quotation/Actions/RegenerateRfqInvitationPortalToken.php`
- `apps/api/Domains/Quotation/Actions/ResolveRfqInvitationPortalAccess.php`
- `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationPortalController.php`
- `apps/api/Domains/Quotation/Http/Requests/ResolveRfqInvitationPortalRequest.php`
- `apps/api/Domains/Quotation/Http/Resources/RfqInvitationPortalLinkResource.php`
- `apps/api/Domains/Quotation/Http/Resources/VendorPortalRfqInvitationResource.php`
- `apps/api/tests/Feature/RfqInvitationPortalApiTest.php`

Backend modify:

- `apps/api/Domains/Quotation/Models/RfqInvitation.php`
- `apps/api/Domains/Quotation/Policies/RfqInvitationPolicy.php`
- `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`
- `apps/api/Domains/Quotation/Actions/ResendRfqInvitation.php`
- `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationController.php`
- `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php`
- `apps/api/routes/api.php`
- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/**` after `pnpm check:api-contract`

Frontend create:

- `apps/web/app/vendor/rfq-invitations/[token]/page.tsx`
- `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- `apps/web/features/vendor-portal/components/vendor-rfq-package.tsx`
- `apps/web/features/vendor-portal/hooks/use-vendor-rfq-invitation.ts`
- `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`
- `apps/web/features/vendor-portal/types/vendor-rfq-portal-view-model.ts`
- `apps/web/features/vendor-portal/workflows/vendor-rfq-invitation-page.tsx`

Frontend modify:

- `apps/web/features/sourcing/api/rfq-invitation-api.ts`
- `apps/web/features/sourcing/hooks/use-rfq-invitation-actions.ts`
- `apps/web/features/sourcing/components/rfq-invitation-panel.tsx`
- `apps/web/features/sourcing/types/rfq-invitation-view-model.ts`
- `apps/web/features/sourcing/mocks/rfq-invitation-fixtures.ts`
- `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`
- `apps/web/tests/msw/handlers.ts`

## Task 1: Backend Failing Tests For Portal Token Access

**Files:**

- Create: `apps/api/tests/Feature/RfqInvitationPortalApiTest.php`
- Modify: `apps/api/tests/Feature/RfqInvitationApiTest.php`

- [ ] **Step 1: Create portal API feature tests**

Create `apps/api/tests/Feature/RfqInvitationPortalApiTest.php` with these tests and local helpers copied from `RfqInvitationApiTest` so the file is self-contained:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqInvitationPortalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_regenerate_portal_link_and_normal_invitation_list_hides_raw_token(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $token = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertOk()
            ->assertJsonPath('data.invitationId', (string) $invitation->id)
            ->assertJsonPath('data.portalUrl', fn (string $url): bool => str_contains($url, '/vendor/rfq-invitations/'))
            ->assertJsonPath('data.expiresAt', fn (?string $value): bool => $value !== null)
            ->json('data.token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);
        $this->assertDatabaseHas('rfq_invitations', [
            'id' => $invitation->id,
            'portal_token_hash' => hash('sha256', $token),
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertOk()
            ->assertJsonMissing(['token' => $token])
            ->assertJsonPath('data.0.portalAccess.hasToken', true);
    }

    public function test_valid_portal_token_returns_vendor_safe_rfq_package_and_records_audit(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer, [
            'scope_summary' => 'Supply laptops for field teams.',
            'response_instructions' => 'Submit pricing and delivery terms.',
            'required_documents' => [
                ['key' => 'quote_pdf', 'label' => 'Quotation PDF', 'required' => true],
            ],
            'line_items' => [
                ['description' => 'Laptop', 'quantity' => 10, 'unit' => 'each', 'notes' => '16GB RAM minimum'],
            ],
            'evaluation_notes' => 'Internal scoring notes.',
            'internal_notes' => 'Buyer-only notes.',
        ]);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant, ['name' => 'Northwind Traders']));
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.id', (string) $invitation->id)
            ->assertJsonPath('data.invitation.status', RfqInvitationStatus::Sent->value)
            ->assertJsonPath('data.vendor.name', 'Northwind Traders')
            ->assertJsonPath('data.rfq.title', 'Laptop refresh RFQ')
            ->assertJsonPath('data.rfq.scopeSummary', 'Supply laptops for field teams.')
            ->assertJsonPath('data.rfq.responseInstructions', 'Submit pricing and delivery terms.')
            ->assertJsonPath('data.rfq.requiredDocuments.0.key', 'quote_pdf')
            ->assertJsonPath('data.rfq.lineItems.0.description', 'Laptop')
            ->assertJsonMissing(['evaluationNotes' => 'Internal scoring notes.'])
            ->assertJsonMissing(['internalNotes' => 'Buyer-only notes.'])
            ->assertJsonMissing(['permissions']);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => null,
            'event_type' => 'rfq_invitation.portal_viewed',
            'subject_type' => RfqInvitation::class,
            'subject_id' => $invitation->id,
        ]);
        $this->assertSame(1, $invitation->refresh()->portal_view_count);
        $this->assertNotNull($invitation->portal_last_viewed_at);
    }

    public function test_invalid_expired_cancelled_declined_and_expired_invitation_tokens_do_not_expose_rfq_details(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);

        $this->getJson('/api/vendor-portal/rfq-invitations/not-a-real-token')
            ->assertNotFound()
            ->assertJsonMissing(['Laptop refresh RFQ']);

        $expired = $this->invitation($tenant, $rfq, $this->vendor($tenant));
        $expiredToken = $this->issuePortalToken($tenant, $buyer, $expired);
        $expired->forceFill(['portal_token_expires_at' => now()->subMinute()])->save();

        $this->getJson("/api/vendor-portal/rfq-invitations/{$expiredToken}")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict')
            ->assertJsonMissing(['Laptop refresh RFQ']);

        foreach ([RfqInvitationStatus::Cancelled, RfqInvitationStatus::Declined, RfqInvitationStatus::Expired] as $status) {
            $blocked = $this->invitation($tenant, $rfq, $this->vendor($tenant), ['status' => $status]);
            $blockedToken = $this->issuePortalToken($tenant, $buyer, $blocked);

            $this->getJson("/api/vendor-portal/rfq-invitations/{$blockedToken}")
                ->assertStatus(409)
                ->assertJsonPath('error.code', 'conflict')
                ->assertJsonMissing(['Laptop refresh RFQ']);
        }
    }

    public function test_acknowledged_invitation_remains_portal_readable_for_future_quotation_upload(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant), [
            'status' => RfqInvitationStatus::Acknowledged,
            'acknowledged_at' => now(),
        ]);
        $token = $this->issuePortalToken($tenant, $buyer, $invitation);

        $this->getJson("/api/vendor-portal/rfq-invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.invitation.status', RfqInvitationStatus::Acknowledged->value);
    }

    public function test_requester_cannot_regenerate_portal_link(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $invitation = $this->invitation($tenant, $rfq, $this->vendor($tenant));

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertForbidden();
    }

    private function issuePortalToken(Tenant $tenant, User $buyer, RfqInvitation $invitation): string
    {
        return (string) $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$invitation->id}/portal-link")
            ->assertOk()
            ->json('data.token');
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /** @return array{Tenant, User} */
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
}
```

- [ ] **Step 2: Run portal tests and verify they fail**

```bash
cd apps/api && php artisan test --filter=RfqInvitationPortalApiTest
```

Expected: failures because the portal-link route, public token route, portal token columns, actions, and resources do not exist yet.

- [ ] **Step 3: Add regression test to existing invitation suite for token creation on create/resend**

Append this method to `apps/api/tests/Feature/RfqInvitationApiTest.php` before helper methods:

```php
public function test_create_and_resend_ensure_portal_token_metadata_without_exposing_raw_token(): void
{
    [$tenant, $requester] = $this->tenantUser('requester');
    [, $buyer] = $this->tenantUser('buyer', $tenant);
    $rfq = $this->draftRfq($tenant, $requester, $buyer);
    $vendor = $this->vendor($tenant);

    $createdId = $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/rfqs/{$rfq->id}/invitations", [
            'vendorIds' => [(string) $vendor->id],
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.portalAccess.hasToken', true)
        ->assertJsonMissing(['token'])
        ->json('data.0.id');

    $created = RfqInvitation::query()->findOrFail((int) $createdId);
    $this->assertNotNull($created->portal_token_hash);
    $this->assertNotNull($created->portal_token_expires_at);

    $created->forceFill(['portal_token_hash' => null, 'portal_token_expires_at' => null])->save();

    $this->actingAsTenant($tenant, $buyer)
        ->postJson("/api/rfq-invitations/{$created->id}/resend")
        ->assertOk()
        ->assertJsonPath('data.portalAccess.hasToken', true)
        ->assertJsonMissing(['token']);

    $this->assertNotNull($created->refresh()->portal_token_hash);
}
```

- [ ] **Step 4: Run invitation tests and verify the new test fails**

```bash
cd apps/api && php artisan test --filter=RfqInvitationApiTest
```

Expected: failure on missing `portalAccess` fields and missing portal token columns.

- [ ] **Step 5: Commit failing tests**

```bash
git add apps/api/tests/Feature/RfqInvitationPortalApiTest.php apps/api/tests/Feature/RfqInvitationApiTest.php
git commit -m "test: define vendor portal baseline workflow"
```

## Task 2: Backend Portal Token Model, Actions, Resources, And Routes

**Files:**

- Create: `apps/api/database/migrations/2026_05_20_010000_add_portal_access_to_rfq_invitations_table.php`
- Create: `apps/api/Domains/Quotation/Actions/EnsureRfqInvitationPortalToken.php`
- Create: `apps/api/Domains/Quotation/Actions/RegenerateRfqInvitationPortalToken.php`
- Create: `apps/api/Domains/Quotation/Actions/ResolveRfqInvitationPortalAccess.php`
- Create: `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationPortalController.php`
- Create: `apps/api/Domains/Quotation/Http/Requests/ResolveRfqInvitationPortalRequest.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqInvitationPortalLinkResource.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/VendorPortalRfqInvitationResource.php`
- Modify: `apps/api/Domains/Quotation/Models/RfqInvitation.php`
- Modify: `apps/api/Domains/Quotation/Policies/RfqInvitationPolicy.php`
- Modify: `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`
- Modify: `apps/api/Domains/Quotation/Actions/ResendRfqInvitation.php`
- Modify: `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationController.php`
- Modify: `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add portal access columns**

Create `apps/api/database/migrations/2026_05_20_010000_add_portal_access_to_rfq_invitations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table): void {
            $table->string('portal_token_hash', 64)->nullable()->unique()->after('metadata');
            $table->timestamp('portal_token_created_at')->nullable()->after('portal_token_hash');
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token_created_at');
            $table->timestamp('portal_last_viewed_at')->nullable()->after('portal_token_expires_at');
            $table->unsignedInteger('portal_view_count')->default(0)->after('portal_last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_invitations', function (Blueprint $table): void {
            $table->dropUnique(['portal_token_hash']);
            $table->dropColumn([
                'portal_token_hash',
                'portal_token_created_at',
                'portal_token_expires_at',
                'portal_last_viewed_at',
                'portal_view_count',
            ]);
        });
    }
};
```

- [ ] **Step 2: Extend `RfqInvitation` model**

Modify `apps/api/Domains/Quotation/Models/RfqInvitation.php`:

- Add fillable fields:

```php
'portal_token_hash',
'portal_token_created_at',
'portal_token_expires_at',
'portal_last_viewed_at',
'portal_view_count',
```

- Add casts:

```php
'portal_token_created_at' => 'datetime',
'portal_token_expires_at' => 'datetime',
'portal_last_viewed_at' => 'datetime',
'portal_view_count' => 'integer',
```

- Add methods after `canUpdateStatusTo()`:

```php
public function canBeViewedInPortal(): bool
{
    return in_array($this->statusState(), [
        RfqInvitationStatus::Sent,
        RfqInvitationStatus::Acknowledged,
    ], true);
}

public function portalTokenExpired(): bool
{
    return $this->portal_token_expires_at !== null && $this->portal_token_expires_at->isPast();
}

public function defaultPortalTokenExpiry(): \Illuminate\Support\Carbon
{
    return $this->response_due_at ?? $this->rfq?->response_due_at ?? now()->addDays(30);
}
```

- [ ] **Step 3: Add policy method for portal-link regeneration**

Modify `apps/api/Domains/Quotation/Policies/RfqInvitationPolicy.php` and add:

```php
public function regeneratePortalLink(User $user, RfqInvitation $invitation): bool
{
    return $this->canManageInvitation($user, $invitation) && $invitation->canBeViewedInPortal();
}
```

- [ ] **Step 4: Create token ensure action**

Create `apps/api/Domains/Quotation/Actions/EnsureRfqInvitationPortalToken.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class EnsureRfqInvitationPortalToken
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return array{invitation: RfqInvitation, token: string|null, created: bool}
     */
    public function handle(Tenant $tenant, ?User $actor, RfqInvitation $invitation, bool $forceRegenerate = false): array
    {
        if (! $invitation->canBeViewedInPortal()) {
            throw new ConflictHttpException('This invitation is not available in the vendor portal.');
        }

        if (! $forceRegenerate && $invitation->portal_token_hash !== null && ! $invitation->portalTokenExpired()) {
            return ['invitation' => $invitation->refresh()->load(['vendor', 'rfq.tenant']), 'token' => null, 'created' => false];
        }

        $token = Str::random(64);
        $expiresAt = $invitation->defaultPortalTokenExpiry();

        $invitation->forceFill([
            'portal_token_hash' => hash('sha256', $token),
            'portal_token_created_at' => now(),
            'portal_token_expires_at' => $expiresAt,
        ])->save();

        $this->audit->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: $forceRegenerate ? 'rfq_invitation.portal_token_regenerated' : 'rfq_invitation.portal_token_created',
            subject: $invitation,
            metadata: [
                'rfqId' => (string) $invitation->rfq_id,
                'vendorId' => (string) $invitation->vendor_id,
                'expiresAt' => $expiresAt->toISOString(),
            ],
            subjectDisplay: $invitation->vendor?->name,
        ));

        return ['invitation' => $invitation->refresh()->load(['vendor', 'rfq.tenant']), 'token' => $token, 'created' => true];
    }
}
```

- [ ] **Step 5: Create portal-link regeneration action**

Create `apps/api/Domains/Quotation/Actions/RegenerateRfqInvitationPortalToken.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RegenerateRfqInvitationPortalToken
{
    public function __construct(private readonly EnsureRfqInvitationPortalToken $tokens) {}

    /**
     * @return array{invitation: RfqInvitation, token: string}
     */
    public function handle(Tenant $tenant, User $actor, RfqInvitation $invitation): array
    {
        Gate::forUser($actor)->authorize('regeneratePortalLink', $invitation);

        return DB::transaction(function () use ($tenant, $actor, $invitation): array {
            $lockedInvitation = RfqInvitation::query()
                ->with(['vendor', 'rfq.tenant'])
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $result = $this->tokens->handle($tenant, $actor, $lockedInvitation, true);

            return ['invitation' => $result['invitation'], 'token' => (string) $result['token']];
        });
    }
}
```

- [ ] **Step 6: Create portal access resolver action**

Create `apps/api/Domains/Quotation/Actions/ResolveRfqInvitationPortalAccess.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ResolveRfqInvitationPortalAccess
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(string $token, Request $request): RfqInvitation
    {
        $hash = hash('sha256', $token);

        return DB::transaction(function () use ($hash, $request): RfqInvitation {
            $invitation = RfqInvitation::query()
                ->with(['tenant', 'vendor', 'rfq.tenant'])
                ->where('portal_token_hash', $hash)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invitation->portalTokenExpired() || ! $invitation->canBeViewedInPortal()) {
                throw new ConflictHttpException('This vendor portal link is no longer available.');
            }

            $invitation->forceFill([
                'portal_last_viewed_at' => now(),
                'portal_view_count' => (int) $invitation->portal_view_count + 1,
            ])->save();

            $this->audit->record(new AuditEventData(
                tenant: $invitation->tenant,
                actor: null,
                action: 'rfq_invitation.portal_viewed',
                subject: $invitation,
                metadata: [
                    'rfqId' => (string) $invitation->rfq_id,
                    'vendorId' => (string) $invitation->vendor_id,
                    'userAgent' => substr((string) $request->userAgent(), 0, 255),
                ],
                subjectDisplay: $invitation->vendor?->name,
            ));

            return $invitation->refresh()->load(['tenant', 'vendor', 'rfq.tenant']);
        });
    }
}
```

- [ ] **Step 7: Create portal token request**

Create `apps/api/Domains/Quotation/Http/Requests/ResolveRfqInvitationPortalRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveRfqInvitationPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:32', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['token' => $this->route('token')]);
    }
}
```

- [ ] **Step 8: Create internal portal-link resource**

Create `apps/api/Domains/Quotation/Http/Resources/RfqInvitationPortalLinkResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqInvitationPortalLinkResource extends JsonResource
{
    public function __construct(private readonly RfqInvitation $invitation, private readonly string $token)
    {
        parent::__construct($invitation);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'invitationId' => (string) $this->invitation->id,
            'token' => $this->token,
            'portalUrl' => "/vendor/rfq-invitations/{$this->token}",
            'expiresAt' => $this->invitation->portal_token_expires_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 9: Create vendor portal resource**

Create `apps/api/Domains/Quotation/Http/Resources/VendorPortalRfqInvitationResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPortalRfqInvitationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rfq = $this->rfq;

        return [
            'invitation' => [
                'id' => (string) $this->id,
                'status' => $this->status->value,
                'responseDueAt' => $this->response_due_at?->toISOString(),
                'message' => $this->message,
                'portalExpiresAt' => $this->portal_token_expires_at?->toISOString(),
            ],
            'tenant' => [
                'name' => $this->tenant?->name,
            ],
            'vendor' => [
                'id' => (string) $this->vendor->id,
                'name' => $this->vendor->name,
                'contactName' => $this->contact_name,
                'contactEmail' => $this->contact_email,
            ],
            'rfq' => [
                'id' => (string) $rfq->id,
                'number' => $rfq->number,
                'title' => $rfq->title,
                'scopeSummary' => $rfq->scope_summary,
                'responseDueAt' => $rfq->response_due_at?->toISOString(),
                'responseInstructions' => $rfq->response_instructions,
                'requiredDocuments' => $rfq->required_documents ?? [],
                'lineItems' => $rfq->line_items ?? [],
            ],
        ];
    }
}
```

- [ ] **Step 10: Create portal controller**

Create `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationPortalController.php`:

```php
<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\RegenerateRfqInvitationPortalToken;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Http\Requests\ResolveRfqInvitationPortalRequest;
use Domains\Quotation\Http\Resources\RfqInvitationPortalLinkResource;
use Domains\Quotation\Http\Resources\VendorPortalRfqInvitationResource;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\Request;

class RfqInvitationPortalController extends Controller
{
    public function show(ResolveRfqInvitationPortalRequest $request, ResolveRfqInvitationPortalAccess $action): VendorPortalRfqInvitationResource
    {
        return new VendorPortalRfqInvitationResource(
            $action->handle((string) $request->validated('token'), $request)
        );
    }

    public function regenerate(Request $request, CurrentTenant $currentTenant, int $invitation, RegenerateRfqInvitationPortalToken $action): RfqInvitationPortalLinkResource
    {
        $tenant = $this->tenantOrAbort($currentTenant);
        $model = RfqInvitation::query()
            ->with(['vendor', 'rfq.tenant'])
            ->where('tenant_id', $tenant->id)
            ->findOrFail($invitation);

        $result = $action->handle($tenant, $request->user(), $model);

        return new RfqInvitationPortalLinkResource($result['invitation'], $result['token']);
    }

    private function tenantOrAbort(CurrentTenant $currentTenant): Tenant
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');

        return $tenant;
    }
}
```

- [ ] **Step 11: Wire routes**

Modify `apps/api/routes/api.php`:

- Add import:

```php
use Domains\Quotation\Http\Controllers\RfqInvitationPortalController;
```

- Add public route before the protected routes group:

```php
Route::get('/vendor-portal/rfq-invitations/{token}', [RfqInvitationPortalController::class, 'show']);
```

- Add internal route inside the existing `auth:sanctum` + `ResolveCurrentTenant` group next to invitation routes:

```php
Route::post('/rfq-invitations/{invitation}/portal-link', [RfqInvitationPortalController::class, 'regenerate']);
```

- [ ] **Step 12: Ensure tokens on create and resend**

Modify `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`:

- Change constructor to inject `EnsureRfqInvitationPortalToken`:

```php
public function __construct(
    private readonly AuditRecorder $audit,
    private readonly EnsureRfqInvitationPortalToken $portalTokens,
) {}
```

- After creating and auditing each invitation, before returning it, call:

```php
$this->portalTokens->handle($tenant, $actor, $invitation);

return $invitation->refresh()->load('vendor');
```

Modify `apps/api/Domains/Quotation/Actions/ResendRfqInvitation.php`:

- Change constructor:

```php
public function __construct(
    private readonly AuditRecorder $audit,
    private readonly EnsureRfqInvitationPortalToken $portalTokens,
) {}
```

- Before returning from the transaction, call:

```php
$this->portalTokens->handle($tenant, $actor, $lockedInvitation);

return $lockedInvitation->refresh()->load('vendor');
```

- [ ] **Step 13: Expose portal metadata in internal invitation resource**

Modify `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php` and add this top-level field before `permissions`:

```php
'portalAccess' => [
    'hasToken' => $this->portal_token_hash !== null,
    'expiresAt' => $this->portal_token_expires_at?->toISOString(),
    'lastViewedAt' => $this->portal_last_viewed_at?->toISOString(),
    'viewCount' => (int) $this->portal_view_count,
],
```

Never add raw token to `RfqInvitationResource`.

- [ ] **Step 14: Run backend tests**

```bash
cd apps/api && php artisan test --filter=RfqInvitationPortalApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
```

Expected: all tests pass.

- [ ] **Step 15: Run route list checks**

```bash
cd apps/api && php artisan route:list --path=api/vendor-portal
cd apps/api && php artisan route:list --path=api/rfq-invitations
```

Expected: public vendor portal route and internal portal-link route are listed.

- [ ] **Step 16: Commit backend implementation**

```bash
git add apps/api/database/migrations/2026_05_20_010000_add_portal_access_to_rfq_invitations_table.php apps/api/Domains/Quotation apps/api/routes/api.php apps/api/tests/Feature/RfqInvitationPortalApiTest.php apps/api/tests/Feature/RfqInvitationApiTest.php
git commit -m "feat: add RFQ invitation vendor portal backend"
```

## Task 3: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify generated: `packages/api-client/src/generated/**`

- [ ] **Step 1: Add OpenAPI schemas**

Modify `apps/api/storage/openapi/openapi.json` and add schemas under `components.schemas`:

```json
"RfqInvitationPortalAccess": {
  "type": "object",
  "required": ["hasToken", "expiresAt", "lastViewedAt", "viewCount"],
  "properties": {
    "hasToken": { "type": "boolean" },
    "expiresAt": { "type": ["string", "null"], "format": "date-time" },
    "lastViewedAt": { "type": ["string", "null"], "format": "date-time" },
    "viewCount": { "type": "integer", "minimum": 0 }
  }
},
"RfqInvitationPortalLink": {
  "type": "object",
  "required": ["invitationId", "token", "portalUrl", "expiresAt"],
  "properties": {
    "invitationId": { "type": "string" },
    "token": { "type": "string" },
    "portalUrl": { "type": "string" },
    "expiresAt": { "type": ["string", "null"], "format": "date-time" }
  }
},
"RfqInvitationPortalLinkResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/RfqInvitationPortalLink" }
  }
},
"VendorPortalRfqInvitation": {
  "type": "object",
  "required": ["invitation", "tenant", "vendor", "rfq"],
  "properties": {
    "invitation": {
      "type": "object",
      "required": ["id", "status", "responseDueAt", "message", "portalExpiresAt"],
      "properties": {
        "id": { "type": "string" },
        "status": { "$ref": "#/components/schemas/RfqInvitationStatus" },
        "responseDueAt": { "type": ["string", "null"], "format": "date-time" },
        "message": { "type": ["string", "null"] },
        "portalExpiresAt": { "type": ["string", "null"], "format": "date-time" }
      }
    },
    "tenant": {
      "type": "object",
      "required": ["name"],
      "properties": { "name": { "type": ["string", "null"] } }
    },
    "vendor": {
      "type": "object",
      "required": ["id", "name", "contactName", "contactEmail"],
      "properties": {
        "id": { "type": "string" },
        "name": { "type": "string" },
        "contactName": { "type": ["string", "null"] },
        "contactEmail": { "type": ["string", "null"], "format": "email" }
      }
    },
    "rfq": {
      "type": "object",
      "required": ["id", "number", "title", "scopeSummary", "responseDueAt", "responseInstructions", "requiredDocuments", "lineItems"],
      "properties": {
        "id": { "type": "string" },
        "number": { "type": "string" },
        "title": { "type": "string" },
        "scopeSummary": { "type": ["string", "null"] },
        "responseDueAt": { "type": ["string", "null"], "format": "date-time" },
        "responseInstructions": { "type": ["string", "null"] },
        "requiredDocuments": { "type": "array", "items": { "type": "object", "additionalProperties": true } },
        "lineItems": { "type": "array", "items": { "type": "object", "additionalProperties": true } }
      }
    }
  }
},
"VendorPortalRfqInvitationResponse": {
  "type": "object",
  "required": ["data"],
  "properties": {
    "data": { "$ref": "#/components/schemas/VendorPortalRfqInvitation" }
  }
}
```

Also add `portalAccess` to `RfqInvitation` schema:

```json
"portalAccess": { "$ref": "#/components/schemas/RfqInvitationPortalAccess" }
```

and include `portalAccess` in the `required` array.

- [ ] **Step 2: Add OpenAPI paths**

Add these paths:

```json
"/api/vendor-portal/rfq-invitations/{token}": {
  "get": {
    "operationId": "showVendorPortalRfqInvitation",
    "summary": "Show vendor portal RFQ invitation",
    "tags": ["Vendor Portal"],
    "parameters": [
      {
        "name": "token",
        "in": "path",
        "required": true,
        "schema": { "type": "string" }
      }
    ],
    "responses": {
      "200": {
        "description": "Vendor-safe RFQ invitation package",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/VendorPortalRfqInvitationResponse" }
          }
        }
      },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/InvalidState" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
},
"/api/rfq-invitations/{invitation}/portal-link": {
  "post": {
    "operationId": "regenerateRfqInvitationPortalLink",
    "summary": "Regenerate RFQ invitation vendor portal link",
    "tags": ["RFQ Invitations"],
    "parameters": [
      {
        "name": "invitation",
        "in": "path",
        "required": true,
        "schema": { "type": "string" }
      }
    ],
    "responses": {
      "200": {
        "description": "RFQ invitation portal link regenerated",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/RfqInvitationPortalLinkResponse" }
          }
        }
      },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "404": { "$ref": "#/components/responses/NotFound" },
      "409": { "$ref": "#/components/responses/InvalidState" }
    }
  }
}
```

- [ ] **Step 3: Validate JSON syntax before generation**

```bash
node -e "JSON.parse(require('fs').readFileSync('apps/api/storage/openapi/openapi.json','utf8')); console.log('openapi ok')"
```

Expected: `openapi ok`.

- [ ] **Step 4: Regenerate and check generated client**

```bash
pnpm check:api-contract
```

Expected on first run: may report generated drift and leave generated files.

If it reports drift, run it once more:

```bash
pnpm check:api-contract
```

Expected on second run: exits 0.

- [ ] **Step 5: Commit contract update**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat: expose vendor portal API contract"
```

## Task 4: Frontend Vendor Portal API, MSW, And View Model

**Files:**

- Create: `apps/web/features/vendor-portal/api/vendor-portal-api.ts`
- Create: `apps/web/features/vendor-portal/types/vendor-rfq-portal-view-model.ts`
- Create: `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`
- Create: `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`
- Modify: `apps/web/tests/msw/handlers.ts`
- Modify: `apps/web/features/sourcing/mocks/rfq-invitation-fixtures.ts`
- Modify: `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`

- [ ] **Step 1: Create vendor portal view model mapper**

Create `apps/web/features/vendor-portal/types/vendor-rfq-portal-view-model.ts`:

```ts
import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";

export type VendorRfqPortalViewModel = {
  invitation: VendorPortalRfqInvitation["invitation"];
  tenant: VendorPortalRfqInvitation["tenant"];
  vendor: VendorPortalRfqInvitation["vendor"];
  rfq: VendorPortalRfqInvitation["rfq"];
  deadlineSummary: string;
};

export function toVendorRfqPortalViewModel(payload: VendorPortalRfqInvitation): VendorRfqPortalViewModel {
  return {
    invitation: payload.invitation,
    tenant: payload.tenant,
    vendor: payload.vendor,
    rfq: payload.rfq,
    deadlineSummary: payload.invitation.portalExpiresAt
      ? `Portal access expires ${formatDateTime(payload.invitation.portalExpiresAt)}`
      : "Portal access expiry has not been recorded.",
  };
}

export function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
```

- [ ] **Step 2: Create vendor portal API wrapper**

Create `apps/web/features/vendor-portal/api/vendor-portal-api.ts`:

```ts
import { showVendorPortalRfqInvitation } from "@cognify/api-client/endpoints";
import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";
import {
  toVendorRfqPortalViewModel,
  type VendorRfqPortalViewModel,
} from "../types/vendor-rfq-portal-view-model";

export async function fetchVendorPortalRfqInvitation(token: string): Promise<VendorRfqPortalViewModel> {
  const response = await showVendorPortalRfqInvitation(token);
  if (response.status !== 200) throw response.data;

  return toVendorRfqPortalViewModel(response.data.data as VendorPortalRfqInvitation);
}
```

- [ ] **Step 3: Create vendor portal MSW fixtures**

Create `apps/web/features/vendor-portal/mocks/vendor-portal-fixtures.ts`:

```ts
import type { VendorPortalRfqInvitation } from "@cognify/api-client/schemas";

export const validVendorPortalToken = "vendor-portal-valid-token";
export const expiredVendorPortalToken = "vendor-portal-expired-token";
export const unavailableVendorPortalToken = "vendor-portal-unavailable-token";

export const vendorPortalRfqInvitationFixture: VendorPortalRfqInvitation = {
  invitation: {
    id: "1",
    status: "sent",
    responseDueAt: "2026-06-30T17:00:00.000000Z",
    message: "Please review the RFQ package and confirm your interest.",
    portalExpiresAt: "2026-06-30T17:00:00.000000Z",
  },
  tenant: {
    name: "Acme Procurement",
  },
  vendor: {
    id: "1",
    name: "Northwind Traders",
    contactName: "Nina Northwind",
    contactEmail: "nina@northwind.test",
  },
  rfq: {
    id: "rfq-1",
    number: "RFQ-2026-000001",
    title: "Field laptop refresh RFQ",
    scopeSummary: "Supply and deliver laptops for field teams.",
    responseDueAt: "2026-06-30T17:00:00.000000Z",
    responseInstructions: "Submit pricing, warranty, and delivery terms.",
    requiredDocuments: [
      { key: "quote_pdf", label: "Quotation PDF", required: true },
      { key: "company_profile", label: "Company profile", required: false },
    ],
    lineItems: [
      { description: "Developer laptop", quantity: 10, unit: "each", notes: "16GB RAM minimum" },
    ],
  },
};
```

- [ ] **Step 4: Create vendor portal MSW handlers**

Create `apps/web/features/vendor-portal/mocks/vendor-portal-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";
import {
  expiredVendorPortalToken,
  unavailableVendorPortalToken,
  validVendorPortalToken,
  vendorPortalRfqInvitationFixture,
} from "./vendor-portal-fixtures";

export const vendorPortalHandlers = [
  http.get("/api/vendor-portal/rfq-invitations/:token", ({ params }) => {
    const token = String(params.token);

    if (token === validVendorPortalToken) {
      return HttpResponse.json({ data: structuredClone(vendorPortalRfqInvitationFixture) });
    }

    if (token === expiredVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link has expired." } },
        { status: 409 },
      );
    }

    if (token === unavailableVendorPortalToken) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "This vendor portal link is no longer available." } },
        { status: 409 },
      );
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "This vendor portal link could not be found." } },
      { status: 404 },
    );
  }),
];
```

- [ ] **Step 5: Register MSW handlers**

Modify `apps/web/tests/msw/handlers.ts`:

- Add import:

```ts
import { vendorPortalHandlers } from "@/features/vendor-portal/mocks/vendor-portal-handlers";
```

- Add `...vendorPortalHandlers` before sourcing handlers in `handlers`:

```ts
  ...vendorPortalHandlers,
```

- [ ] **Step 6: Extend sourcing invitation fixtures and handlers for portal link action**

Modify `apps/web/features/sourcing/mocks/rfq-invitation-fixtures.ts` and add to each invitation:

```ts
portalAccess: {
  hasToken: true,
  expiresAt: "2026-06-30T17:00:00.000000Z",
  lastViewedAt: null,
  viewCount: 0,
},
```

Modify `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`:

- Ensure `buildInvitation()` sets `portalAccess` with `hasToken: true`.
- Add handler:

```ts
http.post("/api/rfq-invitations/:invitationId/portal-link", ({ params }) => {
  const existing = rfqInvitations.find((invitation) => invitation.id === params.invitationId);
  if (!existing) return notFound();
  if (!["sent", "acknowledged"].includes(existing.status)) {
    return conflict("This invitation is not available in the vendor portal.");
  }

  const expiresAt = existing.portalAccess?.expiresAt ?? existing.responseDueAt ?? "2026-06-30T17:00:00.000000Z";
  const updated = {
    ...existing,
    portalAccess: {
      hasToken: true,
      expiresAt,
      lastViewedAt: existing.portalAccess?.lastViewedAt ?? null,
      viewCount: existing.portalAccess?.viewCount ?? 0,
    },
  };

  rfqInvitations = rfqInvitations.map((invitation) =>
    invitation.id === updated.id ? updated : invitation,
  );

  return HttpResponse.json({
    data: {
      invitationId: updated.id,
      token: "vendor-portal-valid-token",
      portalUrl: "/vendor/rfq-invitations/vendor-portal-valid-token",
      expiresAt,
    },
  });
}),
```

- [ ] **Step 7: Commit frontend API/MSW groundwork**

```bash
git add apps/web/features/vendor-portal apps/web/features/sourcing/mocks apps/web/tests/msw/handlers.ts
git commit -m "feat: add vendor portal frontend contract mocks"
```

## Task 5: Vendor Portal UI And Buyer Copy-Link Workflow

**Files:**

- Create: `apps/web/features/vendor-portal/hooks/use-vendor-rfq-invitation.ts`
- Create: `apps/web/features/vendor-portal/components/vendor-rfq-package.tsx`
- Create: `apps/web/features/vendor-portal/workflows/vendor-rfq-invitation-page.tsx`
- Create: `apps/web/app/vendor/rfq-invitations/[token]/page.tsx`
- Create: `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`
- Modify: `apps/web/features/sourcing/api/rfq-invitation-api.ts`
- Modify: `apps/web/features/sourcing/hooks/use-rfq-invitation-actions.ts`
- Modify: `apps/web/features/sourcing/components/rfq-invitation-panel.tsx`
- Modify: `apps/web/features/sourcing/types/rfq-invitation-view-model.ts`
- Modify: `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`

- [ ] **Step 1: Create hook for vendor portal query**

Create `apps/web/features/vendor-portal/hooks/use-vendor-rfq-invitation.ts`:

```ts
import { useQuery } from "@tanstack/react-query";
import { fetchVendorPortalRfqInvitation } from "../api/vendor-portal-api";

export const vendorPortalKeys = {
  invitation: (token: string) => ["vendor-portal", "rfq-invitation", token] as const,
};

export function useVendorRfqInvitation(token: string) {
  return useQuery({
    queryKey: vendorPortalKeys.invitation(token),
    queryFn: () => fetchVendorPortalRfqInvitation(token),
    enabled: token.length > 0,
    retry: false,
  });
}
```

- [ ] **Step 2: Create read-only RFQ package component**

Create `apps/web/features/vendor-portal/components/vendor-rfq-package.tsx`:

```tsx
import type { VendorRfqPortalViewModel } from "../types/vendor-rfq-portal-view-model";
import { formatDateTime } from "../types/vendor-rfq-portal-view-model";

export function VendorRfqPackage({ invitation }: { invitation: VendorRfqPortalViewModel }) {
  const requiredDocuments = invitation.rfq.requiredDocuments as Array<{ key?: string; label?: string; required?: boolean }>;
  const lineItems = invitation.rfq.lineItems as Array<{ description?: string; quantity?: number; unit?: string; notes?: string }>;

  return (
    <article className="mx-auto max-w-5xl space-y-6 px-4 py-8">
      <header className="rounded-lg border bg-background p-6 shadow-sm">
        <p className="text-sm font-medium text-muted-foreground">{invitation.tenant.name ?? "Cognify"}</p>
        <h1 className="mt-2 text-3xl font-semibold">{invitation.rfq.title}</h1>
        <p className="mt-2 font-mono text-sm text-muted-foreground">{invitation.rfq.number}</p>
        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
          {invitation.deadlineSummary}
        </div>
      </header>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Invitation</h2>
        <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div>
            <dt className="text-muted-foreground">Vendor</dt>
            <dd className="font-medium">{invitation.vendor.name}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Contact</dt>
            <dd>{invitation.vendor.contactName ?? "Not recorded"}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Status</dt>
            <dd className="capitalize">{invitation.invitation.status}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Response due</dt>
            <dd>{invitation.rfq.responseDueAt ? formatDateTime(invitation.rfq.responseDueAt) : "Not set"}</dd>
          </div>
        </dl>
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Scope</h2>
        <p className="mt-3 text-sm text-muted-foreground">{invitation.rfq.scopeSummary ?? "No scope summary was provided."}</p>
        <h3 className="mt-6 text-base font-semibold">Response instructions</h3>
        <p className="mt-2 text-sm text-muted-foreground">{invitation.rfq.responseInstructions ?? "No response instructions were provided."}</p>
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Line items</h2>
        {lineItems.length > 0 ? (
          <div className="mt-4 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b text-muted-foreground">
                <tr>
                  <th className="py-2 pr-3 font-medium">Description</th>
                  <th className="py-2 pr-3 font-medium">Quantity</th>
                  <th className="py-2 pr-3 font-medium">Unit</th>
                  <th className="py-2 font-medium">Notes</th>
                </tr>
              </thead>
              <tbody>
                {lineItems.map((item, index) => (
                  <tr key={`${item.description ?? "item"}-${index}`} className="border-b last:border-b-0">
                    <td className="py-3 pr-3 font-medium">{item.description ?? "Untitled item"}</td>
                    <td className="py-3 pr-3">{item.quantity ?? "-"}</td>
                    <td className="py-3 pr-3">{item.unit ?? "-"}</td>
                    <td className="py-3">{item.notes ?? "-"}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No line items were provided.</p>
        )}
      </section>

      <section className="rounded-lg border p-6">
        <h2 className="text-lg font-semibold">Required documents</h2>
        {requiredDocuments.length > 0 ? (
          <ul className="mt-4 space-y-2 text-sm">
            {requiredDocuments.map((document) => (
              <li key={document.key ?? document.label} className="flex items-center justify-between rounded-md border p-3">
                <span>{document.label ?? document.key ?? "Required document"}</span>
                <span className="text-muted-foreground">{document.required ? "Required" : "Optional"}</span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="mt-3 text-sm text-muted-foreground">No required documents were listed.</p>
        )}
      </section>

      <section className="rounded-lg border border-blue-300 bg-blue-50 p-4 text-sm text-blue-950">
        Quotation submission will be available in a later Cognify workflow. Use the buyer instructions above to prepare your response.
      </section>
    </article>
  );
}
```

- [ ] **Step 3: Create vendor portal workflow page**

Create `apps/web/features/vendor-portal/workflows/vendor-rfq-invitation-page.tsx`:

```tsx
"use client";

import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { VendorRfqPackage } from "../components/vendor-rfq-package";
import { useVendorRfqInvitation } from "../hooks/use-vendor-rfq-invitation";

export function VendorRfqInvitationPage({ token }: { token: string }) {
  const invitationQuery = useVendorRfqInvitation(token);

  if (invitationQuery.isLoading) {
    return <StatusPanel title="Loading RFQ package" message="Preparing your invitation details." />;
  }

  if (invitationQuery.isError || !invitationQuery.data) {
    const code = getApiErrorCode(invitationQuery.error);
    const title = code === "not_found" ? "Invitation link not found" : "Invitation link unavailable";
    const message =
      code === "not_found"
        ? "This link is invalid or has already been replaced. Contact the buyer for a new invitation link."
        : code === "conflict"
          ? "This invitation is expired, cancelled, or no longer available. Contact the buyer if you believe this is incorrect."
          : getApiErrorMessage(invitationQuery.error);

    return <StatusPanel role="alert" title={title} message={message} />;
  }

  return <VendorRfqPackage invitation={invitationQuery.data} />;
}

function StatusPanel({ title, message, role }: { title: string; message: string; role?: "alert" }) {
  return (
    <main className="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
      <section role={role} className="w-full max-w-xl rounded-lg border bg-background p-6 text-center shadow-sm">
        <p className="text-sm font-medium text-muted-foreground">Cognify vendor portal</p>
        <h1 className="mt-2 text-2xl font-semibold">{title}</h1>
        <p className="mt-3 text-sm text-muted-foreground">{message}</p>
      </section>
    </main>
  );
}
```

- [ ] **Step 4: Create app route**

Create `apps/web/app/vendor/rfq-invitations/[token]/page.tsx`:

```tsx
import { VendorRfqInvitationPage } from "@/features/vendor-portal/workflows/vendor-rfq-invitation-page";

export default async function Page({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;

  return <VendorRfqInvitationPage token={token} />;
}
```

- [ ] **Step 5: Add buyer-side API wrapper and hook**

Modify `apps/web/features/sourcing/api/rfq-invitation-api.ts`:

- Import endpoint and type:

```ts
regenerateRfqInvitationPortalLink as regenerateRfqInvitationPortalLinkEndpoint,
```

```ts
RfqInvitationPortalLink,
```

- Add function:

```ts
export async function regenerateRfqInvitationPortalLink(
  invitationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqInvitationPortalLink> {
  const response = await regenerateRfqInvitationPortalLinkEndpoint(invitationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return response.data.data;
}
```

Modify `apps/web/features/sourcing/hooks/use-rfq-invitation-actions.ts`:

- Import wrapper:

```ts
regenerateRfqInvitationPortalLink,
```

- Add hook:

```ts
export function useRegenerateRfqInvitationPortalLink(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (invitationId: string) => regenerateRfqInvitationPortalLink(invitationId, tenantId),
    onSuccess: (portalLink, invitationId) => {
      queryClient.setQueryData(rfqInvitationKeys.list(rfqId, tenantId), (current: unknown) => {
        if (!Array.isArray(current)) return current;

        return current.map((item) =>
          (item as { id: string }).id === invitationId
            ? {
                ...(item as Record<string, unknown>),
                portalAccess: {
                  hasToken: true,
                  expiresAt: portalLink.expiresAt,
                  lastViewedAt: (item as { portalAccess?: { lastViewedAt?: string | null } }).portalAccess?.lastViewedAt ?? null,
                  viewCount: (item as { portalAccess?: { viewCount?: number } }).portalAccess?.viewCount ?? 0,
                },
              }
            : item,
        );
      });
    },
  });
}
```

- [ ] **Step 6: Extend invitation view model**

Modify `apps/web/features/sourcing/types/rfq-invitation-view-model.ts`:

- Add type field:

```ts
portalAccess: {
  hasToken: boolean;
  expiresAt: string | null;
  lastViewedAt: string | null;
  viewCount: number;
};
```

- In mapper, add:

```ts
portalAccess: {
  hasToken: invitation.portalAccess.hasToken,
  expiresAt: invitation.portalAccess.expiresAt,
  lastViewedAt: invitation.portalAccess.lastViewedAt,
  viewCount: invitation.portalAccess.viewCount,
},
```

- [ ] **Step 7: Add buyer copy-link action to invitation panel**

Modify `apps/web/features/sourcing/components/rfq-invitation-panel.tsx`:

- Import hook:

```ts
useRegenerateRfqInvitationPortalLink,
```

- Initialize mutation:

```ts
const portalLinkMutation = useRegenerateRfqInvitationPortalLink(rfqId);
const [portalLinkByInvitationId, setPortalLinkByInvitationId] = useState<Record<string, string>>({});
```

- Add function near other action handlers:

```ts
async function generatePortalLink(invitationId: string) {
  setInvitationActionErrors((current) => {
    if (!current[invitationId]) return current;
    const next = { ...current };
    delete next[invitationId];
    return next;
  });

  try {
    const portalLink = await portalLinkMutation.mutateAsync(invitationId);
    setPortalLinkByInvitationId((current) => ({ ...current, [invitationId]: portalLink.portalUrl }));
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(`${window.location.origin}${portalLink.portalUrl}`);
    }
  } catch (error) {
    setInvitationActionErrors((current) => ({
      ...current,
      [invitationId]: getApiErrorMessage(error),
    }));
  }
}
```

- In each invitation card metadata, render:

```tsx
<p className="text-sm text-muted-foreground">
  {invitation.portalAccess.hasToken
    ? `Portal access expires ${formatDateTime(invitation.portalAccess.expiresAt ?? invitation.responseDueAt ?? invitation.createdAt)}`
    : "Portal access has not been generated."}
</p>
{portalLinkByInvitationId[invitation.id] ? (
  <p className="text-sm text-green-700">Portal link copied. Manual sharing only; email delivery is not enabled.</p>
) : null}
```

- In action buttons, add for cancellable/readable statuses:

```tsx
{canInvite && ["sent", "acknowledged"].includes(invitation.status) ? (
  <Button
    variant="outline"
    size="sm"
    onClick={() => void generatePortalLink(invitation.id)}
    disabled={portalLinkMutation.isPending}
  >
    Generate portal link
  </Button>
) : null}
```

- [ ] **Step 8: Write vendor portal UI tests**

Create `apps/web/features/vendor-portal/tests/vendor-rfq-portal.test.tsx`:

```tsx
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import {
  expiredVendorPortalToken,
  unavailableVendorPortalToken,
  validVendorPortalToken,
} from "../mocks/vendor-portal-fixtures";
import { VendorRfqInvitationPage } from "../workflows/vendor-rfq-invitation-page";

function TestProviders({ children }: { children: React.ReactNode }) {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  });

  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

describe("vendor RFQ portal", () => {
  it("renders the invited RFQ package for a valid token", async () => {
    render(<VendorRfqInvitationPage token={validVendorPortalToken} />, { wrapper: TestProviders });

    expect(await screen.findByRole("heading", { name: "Field laptop refresh RFQ" })).toBeInTheDocument();
    expect(screen.getByText("Northwind Traders")).toBeInTheDocument();
    expect(screen.getByText("Supply and deliver laptops for field teams.")).toBeInTheDocument();
    expect(screen.getByText("Developer laptop")).toBeInTheDocument();
    expect(screen.getByText("Quotation PDF")).toBeInTheDocument();
    expect(screen.getByText(/Quotation submission will be available in a later Cognify workflow/)).toBeInTheDocument();
  });

  it("shows a safe invalid link state", async () => {
    render(<VendorRfqInvitationPage token="invalid-token" />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link not found");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });

  it("shows a safe expired link state", async () => {
    render(<VendorRfqInvitationPage token={expiredVendorPortalToken} />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link unavailable");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });

  it("shows a safe unavailable invitation state", async () => {
    render(<VendorRfqInvitationPage token={unavailableVendorPortalToken} />, { wrapper: TestProviders });

    expect(await screen.findByRole("alert")).toHaveTextContent("Invitation link unavailable");
    expect(screen.queryByText("Field laptop refresh RFQ")).not.toBeInTheDocument();
  });
});
```

- [ ] **Step 9: Extend sourcing workflow test for portal link action**

Modify `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx` and add this test:

```tsx
it("lets a buyer generate a manual vendor portal link without email delivery", async () => {
  const user = userEvent.setup();
  const clipboardWrite = vi.fn().mockResolvedValue(undefined);
  Object.assign(navigator, { clipboard: { writeText: clipboardWrite } });

  render(<RfqDraftWorkspace rfqId="rfq-1" />, { wrapper: TestAppProviders });

  await screen.findByText("Northwind Traders");
  await user.click(screen.getByRole("button", { name: "Generate portal link" }));

  expect(await screen.findByText("Portal link copied. Manual sharing only; email delivery is not enabled.")).toBeInTheDocument();
  expect(clipboardWrite).toHaveBeenCalledWith(expect.stringContaining("/vendor/rfq-invitations/vendor-portal-valid-token"));
});
```

- [ ] **Step 10: Run focused web tests**

```bash
pnpm --filter @cognify/web test -- features/vendor-portal/tests/vendor-rfq-portal.test.tsx
pnpm --filter @cognify/web test -- features/sourcing/tests/rfq-invitations-workflow.test.tsx
```

Expected: all tests pass.

- [ ] **Step 11: Commit frontend UI**

```bash
git add apps/web/app/vendor apps/web/features/vendor-portal apps/web/features/sourcing apps/web/tests/msw/handlers.ts
git commit -m "feat: add vendor RFQ portal workspace"
```

## Task 6: Full Verification And Scope Drift Check

**Files:** all touched files

- [ ] **Step 1: Run backend verification**

```bash
cd apps/api && php artisan test --filter=RfqInvitationPortalApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
cd apps/api && php artisan route:list --path=api/vendor-portal
cd apps/api && php artisan route:list --path=api/rfq-invitations
```

Expected: tests pass; route lists include the public vendor portal show route and internal portal-link route.

- [ ] **Step 2: Run contract verification**

```bash
pnpm check:api-contract
```

Expected: exits 0 with no generated drift.

- [ ] **Step 3: Run focused frontend verification**

```bash
pnpm --filter @cognify/web test -- features/vendor-portal/tests/vendor-rfq-portal.test.tsx
pnpm --filter @cognify/web test -- features/sourcing/tests/rfq-invitations-workflow.test.tsx
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: all commands exit 0.

- [ ] **Step 4: Run build**

```bash
pnpm build
```

Expected: exits 0. If Turbopack fails because the sandbox blocks local port binding, rerun with elevated permissions and record the sandbox failure separately.

- [ ] **Step 5: Run scope drift grep**

```bash
rg -n "quotation upload|manual quotation|quotation version|normalization|comparison|scoring|award decision|award approval|purchase order|vendor login|vendor account|email delivery|OCR|AI extraction" apps/api apps/web/features/vendor-portal apps/web/features/sourcing docs/superpowers/specs/2026-05-20-vendor-portal-baseline-design.md docs/superpowers/plans/2026-05-20-vendor-portal-baseline.md
```

Expected: matches are limited to explicit non-goals, future-slice copy, or safe UX copy. No quotation submission, vendor auth, email delivery, AI, comparison, award, or PO implementation exists.

- [ ] **Step 6: Run whitespace check**

```bash
git diff --check
```

Expected: no whitespace errors.

- [ ] **Step 7: Final commit if verification required fixes**

If verification required fixes after earlier commits:

```bash
git add apps/api apps/web packages/api-client docs/superpowers/plans/2026-05-20-vendor-portal-baseline.md
git commit -m "fix: harden vendor portal baseline"
```

## Self-Review

Spec coverage:

- Secure token generation and hashed storage are covered by Task 2.
- Public vendor-safe RFQ viewing is covered by Task 2 and Task 5.
- Internal buyer/admin regeneration and manual copy-link action are covered by Task 2 and Task 5.
- Audit events are covered by Task 2 and API tests in Task 1.
- OpenAPI and generated client workflow are covered by Task 3.
- Invalid, expired, cancelled, declined, and expired invitation access denial are covered by Task 1.
- Quotation upload, manual entry, versioning, comparison, scoring, awards, PO handoff, vendor accounts, and email delivery remain non-goals and are checked in Task 6.

Placeholder scan:

- No unresolved placeholders are present.
- All file paths are explicit.
- Every task includes focused verification commands.

Type consistency:

- Backend uses `RfqInvitationPortalLinkResource`, `VendorPortalRfqInvitationResource`, `EnsureRfqInvitationPortalToken`, `RegenerateRfqInvitationPortalToken`, and `ResolveRfqInvitationPortalAccess` consistently.
- OpenAPI operation IDs match frontend wrapper names: `showVendorPortalRfqInvitation` and `regenerateRfqInvitationPortalLink`.
- Frontend view model uses generated `VendorPortalRfqInvitation` and `RfqInvitationPortalLink` types.

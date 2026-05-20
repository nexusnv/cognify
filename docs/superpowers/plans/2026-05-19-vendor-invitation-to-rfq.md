# Vendor Invitation To RFQ Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build P1 Epic 5 slice 3: buyer/admin RFQ vendor invitations using existing tenant vendors, tracked inside the RFQ workspace without vendor portal, quotation capture, or email delivery.

**Architecture:** `apps/api/Domains/Quotation` owns RFQ invitation state, policies, actions, requests, resources, and controllers. `apps/api/Domains/Vendor` remains a lightweight supporting data source through a tenant-scoped picker endpoint. The Next.js sourcing feature owns RFQ invitation UI under `apps/web/features/sourcing`, using OpenAPI-generated endpoints from `@cognify/api-client`; shared packages remain primitive or generated-contract only.

**Tech Stack:** Laravel 12, Sanctum tenant middleware, Eloquent, OpenAPI/Orval, Next.js App Router, TanStack Query, React Hook Form where useful, Zod, MSW, Vitest, Testing Library, shadcn/Radix primitives via `@cognify/ui`.

---

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-19-vendor-invitation-to-rfq-design.md`
- Release epic: `docs/02-release-management/2026-05-15-P1-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Architecture: `ARCHITECTURE.md`
- Runbook: `docs/05-runbooks/feature-development.md`
- Previous RFQ plan: `docs/superpowers/plans/2026-05-19-rfq-draft-creation.md`
- Agent guidance: `AGENTS.md`

## Scope Boundaries

Implement:

- Tenant-scoped vendor picker endpoint backed by existing `Domains\Vendor\Models\Vendor` records.
- RFQ invitation model, states, policy, actions, controller, requests, resources, routes, and tests.
- Invitation statuses: `pending`, `sent`, `acknowledged`, `declined`, `expired`, `cancelled`.
- Buyer/admin create, list, resend, cancel, and internal status update workflows.
- Duplicate active invitation prevention for the same RFQ/vendor.
- Audit events for create, sent, resend, cancel, acknowledged, declined, and expired.
- OpenAPI-generated client usage.
- RFQ workspace invitation panel, vendor picker, dialog, hooks, MSW handlers, and tests.

Do not implement:

- Standalone vendor directory or vendor profile management.
- Creating vendors from the RFQ invitation workflow.
- Vendor portal, vendor auth, invitation token links, external sessions, or email delivery.
- Quotation upload/manual entry/versioning, comparison, scoring, awards, award approvals, or PO handoff.
- AI vendor recommendations.

## File Map

Backend create:

- `apps/api/database/migrations/2026_05_19_020000_create_rfq_invitations_table.php`
- `apps/api/Domains/Quotation/States/RfqInvitationStatus.php`
- `apps/api/Domains/Quotation/Models/RfqInvitation.php`
- `apps/api/Domains/Quotation/Policies/RfqInvitationPolicy.php`
- `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`
- `apps/api/Domains/Quotation/Actions/ResendRfqInvitation.php`
- `apps/api/Domains/Quotation/Actions/CancelRfqInvitation.php`
- `apps/api/Domains/Quotation/Actions/UpdateRfqInvitationStatus.php`
- `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationController.php`
- `apps/api/Domains/Quotation/Http/Requests/CreateRfqInvitationsRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/CancelRfqInvitationRequest.php`
- `apps/api/Domains/Quotation/Http/Requests/UpdateRfqInvitationStatusRequest.php`
- `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php`
- `apps/api/Domains/Vendor/Http/Controllers/VendorPickerController.php`
- `apps/api/Domains/Vendor/Http/Requests/ListVendorsRequest.php`
- `apps/api/Domains/Vendor/Http/Resources/VendorPickerResource.php`
- `apps/api/tests/Feature/RfqInvitationApiTest.php`
- `apps/api/tests/Feature/VendorPickerApiTest.php`

Backend modify:

- `apps/api/Domains/Quotation/Models/Rfq.php`
- `apps/api/Domains/Vendor/Models/Vendor.php`
- `apps/api/app/Providers/AppServiceProvider.php`
- `apps/api/routes/api.php`
- `apps/api/storage/openapi/openapi.json`
- `packages/api-client/src/generated/**` after `pnpm generate:api`

Frontend create:

- `apps/web/features/sourcing/api/vendor-api.ts`
- `apps/web/features/sourcing/api/rfq-invitation-api.ts`
- `apps/web/features/sourcing/components/rfq-invitation-panel.tsx`
- `apps/web/features/sourcing/components/rfq-invitation-dialog.tsx`
- `apps/web/features/sourcing/components/rfq-invitation-status-badge.tsx`
- `apps/web/features/sourcing/components/vendor-picker.tsx`
- `apps/web/features/sourcing/hooks/use-rfq-invitations.ts`
- `apps/web/features/sourcing/hooks/use-rfq-invitation-actions.ts`
- `apps/web/features/sourcing/hooks/use-vendor-picker.ts`
- `apps/web/features/sourcing/mocks/rfq-invitation-fixtures.ts`
- `apps/web/features/sourcing/mocks/rfq-invitation-handlers.ts`
- `apps/web/features/sourcing/mocks/vendor-fixtures.ts`
- `apps/web/features/sourcing/mocks/vendor-handlers.ts`
- `apps/web/features/sourcing/schemas/rfq-invitation-schema.ts`
- `apps/web/features/sourcing/types/rfq-invitation-view-model.ts`
- `apps/web/features/sourcing/types/vendor-view-model.ts`
- `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx`

Frontend modify:

- `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx`
- `apps/web/features/sourcing/hooks/use-rfq-draft.ts`
- `apps/web/features/sourcing/mocks/rfq-handlers.ts`
- `apps/web/tests/msw/handlers.ts`

## Task 1: Backend Failing Tests For Vendor Picker And RFQ Invitations

**Files:**

- Create: `apps/api/tests/Feature/VendorPickerApiTest.php`
- Create: `apps/api/tests/Feature/RfqInvitationApiTest.php`

- [ ] **Step 1: Write vendor picker feature tests**

Create `apps/api/tests/Feature/VendorPickerApiTest.php` with tests for tenant scoping, active filtering, and role access.

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VendorPickerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_list_active_tenant_vendors_for_picker(): void
    {
        [$tenant, $buyer] = $this->tenantUser('buyer');
        $active = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Supplies',
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Ada Buyer',
                'contactEmail' => 'ada@example.test',
            ],
        ]);
        Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Dormant Vendor',
            'status' => 'inactive',
        ]);
        [$otherTenant] = $this->tenantUser('buyer');
        Vendor::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Tenant Vendor',
            'status' => 'active',
        ]);

        $this->actingAsTenant($tenant, $buyer)
            ->getJson('/api/vendors?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $active->id)
            ->assertJsonPath('data.0.name', 'Acme Supplies')
            ->assertJsonPath('data.0.defaultContact.name', 'Ada Buyer')
            ->assertJsonPath('data.0.defaultContact.email', 'ada@example.test')
            ->assertJsonMissing(['name' => 'Dormant Vendor'])
            ->assertJsonMissing(['name' => 'Other Tenant Vendor']);
    }

    public function test_requester_cannot_use_vendor_picker(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/vendors?status=active')
            ->assertForbidden();
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
        $tenant ??= Tenant::query()->create(['name' => fake()->company(), 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role]);

        return [$tenant, $user];
    }
}
```

- [ ] **Step 2: Write RFQ invitation feature tests**

Create `apps/api/tests/Feature/RfqInvitationApiTest.php`. Copy helper methods from `RfqDraftApiTest` where needed so this file is self-contained.

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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RfqInvitationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_list_resend_and_cancel_rfq_invitation(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant, ['name' => 'Northwind Traders']);

        $create = $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", [
                'vendorIds' => [(string) $vendor->id],
                'message' => 'Please respond with pricing and delivery details.',
                'responseDueAt' => '2026-06-30T17:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('data.0.vendor.id', (string) $vendor->id)
            ->assertJsonPath('data.0.status', RfqInvitationStatus::Sent->value)
            ->assertJsonPath('data.0.permissions.canResend', true)
            ->json('data.0.id');

        $this->actingAsTenant($tenant, $buyer)
            ->getJson("/api/rfqs/{$rfq->id}/invitations")
            ->assertOk()
            ->assertJsonPath('data.0.id', $create)
            ->assertJsonPath('data.0.vendor.name', 'Northwind Traders');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$create}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Sent->value);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$create}/cancel", ['cancelReason' => 'Vendor no longer in scope.'])
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Cancelled->value)
            ->assertJsonPath('data.cancelReason', 'Vendor no longer in scope.');

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.created',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.resent',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $buyer->id,
            'event_type' => 'rfq_invitation.cancelled',
        ]);
    }

    public function test_duplicate_active_invitation_returns_conflict(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $vendor->id]])
            ->assertCreated();

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $vendor->id]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_requester_cannot_manage_rfq_invitations(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $vendor = $this->vendor($tenant);

        $this->actingAsTenant($tenant, $requester)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $vendor->id]])
            ->assertForbidden();
    }

    public function test_invitation_is_tenant_scoped(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        [$otherTenant, $otherRequester] = $this->tenantUser('requester');
        [, $otherBuyer] = $this->tenantUser('buyer', $otherTenant);
        $otherRfq = $this->draftRfq($otherTenant, $otherRequester, $otherBuyer);
        $otherInvitation = $this->invitation($otherTenant, $otherRfq, $this->vendor($otherTenant));

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfq-invitations/{$otherInvitation->id}/resend")
            ->assertNotFound();
    }

    public function test_cannot_invite_inactive_or_cross_tenant_vendor_or_non_draft_rfq(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $rfq = $this->draftRfq($tenant, $requester, $buyer);
        $inactive = $this->vendor($tenant, ['status' => 'inactive']);
        [$otherTenant] = $this->tenantUser('buyer');
        $otherVendor = $this->vendor($otherTenant);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $inactive->id]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $otherVendor->id]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        $rfq->forceFill(['status' => RfqStatus::Cancelled->value])->save();
        $active = $this->vendor($tenant, ['name' => 'Active Vendor']);

        $this->actingAsTenant($tenant, $buyer)
            ->postJson("/api/rfqs/{$rfq->id}/invitations", ['vendorIds' => [(string) $active->id]])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_status_update_supports_internal_handoff_states(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        [, $buyer] = $this->tenantUser('buyer', $tenant);
        $invitation = $this->invitation($tenant, $this->draftRfq($tenant, $requester, $buyer), $this->vendor($tenant));

        $this->actingAsTenant($tenant, $buyer)
            ->patchJson("/api/rfq-invitations/{$invitation->id}/status", ['status' => RfqInvitationStatus::Acknowledged->value])
            ->assertOk()
            ->assertJsonPath('data.status', RfqInvitationStatus::Acknowledged->value);
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company(), 'slug' => fake()->unique()->slug()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['role' => $role]);

        return [$tenant, $user];
    }

    private function vendor(Tenant $tenant, array $overrides = []): Vendor
    {
        return Vendor::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => fake()->unique()->company(),
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => ['contactName' => 'Vendor Contact', 'contactEmail' => fake()->unique()->safeEmail()],
        ], $overrides));
    }

    private function draftRfq(Tenant $tenant, User $requester, User $buyer): Rfq
    {
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-' . fake()->unique()->numerify('####'),
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

        return Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'sourcing_intake_review_id' => $review->id,
            'requisition_id' => $requisition->id,
            'number' => 'RFQ-' . fake()->unique()->numerify('####'),
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'required_documents' => [],
            'line_items' => [],
        ]);
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

- [ ] **Step 3: Run the failing API tests**

Run:

```bash
cd apps/api && php artisan test --filter=VendorPickerApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
```

Expected before implementation: failures for missing routes/classes such as `VendorPickerController`, `RfqInvitation`, `RfqInvitationStatus`, and invitation endpoints.

- [ ] **Step 4: Commit failing tests**

```bash
git add apps/api/tests/Feature/VendorPickerApiTest.php apps/api/tests/Feature/RfqInvitationApiTest.php
git commit -m "test: define RFQ invitation workflow"
```

## Task 2: Backend Data Model, States, Policies, And Routes

**Files:**

- Create: backend model/state/policy/request/resource/controller/action files listed in File Map
- Modify: `apps/api/Domains/Quotation/Models/Rfq.php`
- Modify: `apps/api/Domains/Vendor/Models/Vendor.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Create RFQ invitation migration**

Create `apps/api/database/migrations/2026_05_19_020000_create_rfq_invitations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfq_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('status');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'rfq_id']);
            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['rfq_id', 'vendor_id', 'status'], 'rfq_invitation_rfq_vendor_status_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_invitations');
    }
};
```

- [ ] **Step 2: Create invitation status enum**

Create `apps/api/Domains/Quotation/States/RfqInvitationStatus.php`:

```php
<?php

namespace Domains\Quotation\States;

enum RfqInvitationStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Declined = 'declined';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Sent, self::Acknowledged], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Declined, self::Expired, self::Cancelled], true);
    }
}
```

- [ ] **Step 3: Create invitation model and relationships**

Create `apps/api/Domains/Quotation/Models/RfqInvitation.php`:

```php
<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfqInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'vendor_id',
        'status',
        'contact_name',
        'contact_email',
        'message',
        'response_due_at',
        'sent_at',
        'acknowledged_at',
        'declined_at',
        'expired_at',
        'cancelled_at',
        'cancel_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqInvitationStatus::class,
            'response_due_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
```

Modify `apps/api/Domains/Quotation/Models/Rfq.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function invitations(): HasMany
{
    return $this->hasMany(RfqInvitation::class);
}
```

Modify `apps/api/Domains/Vendor/Models/Vendor.php`:

```php
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Database\Eloquent\Relations\HasMany;

public function rfqInvitations(): HasMany
{
    return $this->hasMany(RfqInvitation::class);
}
```

- [ ] **Step 4: Create policies**

Create `apps/api/Domains/Quotation/Policies/RfqInvitationPolicy.php`:

```php
<?php

namespace Domains\Quotation\Policies;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;

class RfqInvitationPolicy
{
    public function viewAny(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user) && $this->rfqInCurrentTenant($rfq);
    }

    public function create(User $user, Rfq $rfq): bool
    {
        return $this->canManageSourcing($user) && $this->rfqInCurrentTenant($rfq) && $rfq->isEditable();
    }

    public function resend(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && ! $invitation->status->isTerminal();
    }

    public function cancel(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && $invitation->status !== \Domains\Quotation\States\RfqInvitationStatus::Cancelled;
    }

    public function updateStatus(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageInvitation($user, $invitation) && ! $invitation->status->isTerminal();
    }

    private function canManageInvitation(User $user, RfqInvitation $invitation): bool
    {
        return $this->canManageSourcing($user) && $this->invitationInCurrentTenant($invitation);
    }

    private function canManageSourcing(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true);
    }

    private function rfqInCurrentTenant(Rfq $rfq): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $rfq->tenant_id === (int) $tenant->id;
    }

    private function invitationInCurrentTenant(RfqInvitation $invitation): bool
    {
        $tenant = app(CurrentTenant::class)->nullable();

        return $tenant !== null && (int) $invitation->tenant_id === (int) $tenant->id;
    }
}
```

Register in `apps/api/app/Providers/AppServiceProvider.php` next to the RFQ policy:

```php
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Policies\RfqInvitationPolicy;

Gate::policy(RfqInvitation::class, RfqInvitationPolicy::class);
```

- [ ] **Step 5: Add controllers, requests, and routes**

Create request classes with exact rules:

`apps/api/Domains/Quotation/Http/Requests/CreateRfqInvitationsRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRfqInvitationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'vendorIds' => ['required', 'array', 'min:1', 'max:25'],
            'vendorIds.*' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:5000'],
            'responseDueAt' => ['nullable', 'date'],
            'contactOverrides' => ['nullable', 'array'],
            'contactOverrides.*.vendorId' => ['required_with:contactOverrides', 'integer'],
            'contactOverrides.*.contactName' => ['nullable', 'string', 'max:255'],
            'contactOverrides.*.contactEmail' => ['nullable', 'email', 'max:255'],
        ];
    }
}
```

`apps/api/Domains/Quotation/Http/Requests/CancelRfqInvitationRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelRfqInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return ['cancelReason' => ['required', 'string', 'max:5000']];
    }
}
```

`apps/api/Domains/Quotation/Http/Requests/UpdateRfqInvitationStatusRequest.php`:

```php
<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfqInvitationStatusRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                RfqInvitationStatus::Acknowledged->value,
                RfqInvitationStatus::Declined->value,
                RfqInvitationStatus::Expired->value,
            ])],
        ];
    }
}
```

Add routes in the tenant-resolved API route group in `apps/api/routes/api.php`:

```php
use Domains\Quotation\Http\Controllers\RfqInvitationController;
use Domains\Vendor\Http\Controllers\VendorPickerController;

Route::get('/vendors', [VendorPickerController::class, 'index']);
Route::get('/rfqs/{rfq}/invitations', [RfqInvitationController::class, 'index']);
Route::post('/rfqs/{rfq}/invitations', [RfqInvitationController::class, 'store']);
Route::post('/rfq-invitations/{invitation}/resend', [RfqInvitationController::class, 'resend']);
Route::post('/rfq-invitations/{invitation}/cancel', [RfqInvitationController::class, 'cancel']);
Route::patch('/rfq-invitations/{invitation}/status', [RfqInvitationController::class, 'status']);
```

- [ ] **Step 6: Run model/route test subset**

```bash
cd apps/api && php artisan test --filter=VendorPickerApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
cd apps/api && php artisan route:list --path=api/rfq
cd apps/api && php artisan route:list --path=api/vendors
```

Expected after Task 2: routes exist. Some invitation tests may still fail until actions/resources are complete.

## Task 3: Backend Actions And Resources

**Files:**

- Create: `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`
- Create: `apps/api/Domains/Quotation/Actions/ResendRfqInvitation.php`
- Create: `apps/api/Domains/Quotation/Actions/CancelRfqInvitation.php`
- Create: `apps/api/Domains/Quotation/Actions/UpdateRfqInvitationStatus.php`
- Create: `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php`
- Create: `apps/api/Domains/Vendor/Http/Resources/VendorPickerResource.php`
- Create: controllers listed above

- [ ] **Step 1: Implement invitation resource shape**

Create `apps/api/Domains/Quotation/Http/Resources/RfqInvitationResource.php`:

```php
<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'rfqId' => (string) $this->rfq_id,
            'status' => $this->status->value,
            'vendor' => [
                'id' => (string) $this->vendor->id,
                'name' => $this->vendor->name,
                'category' => $this->vendor->category,
                'status' => $this->vendor->status,
                'riskRating' => $this->vendor->risk_rating,
            ],
            'contactName' => $this->contact_name,
            'contactEmail' => $this->contact_email,
            'message' => $this->message,
            'responseDueAt' => $this->response_due_at?->toISOString(),
            'sentAt' => $this->sent_at?->toISOString(),
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'declinedAt' => $this->declined_at?->toISOString(),
            'expiredAt' => $this->expired_at?->toISOString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelReason' => $this->cancel_reason,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'permissions' => [
                'canResend' => $user?->can('resend', $this->resource) ?? false,
                'canCancel' => $user?->can('cancel', $this->resource) ?? false,
                'canUpdateStatus' => $user?->can('updateStatus', $this->resource) ?? false,
            ],
        ];
    }
}
```

- [ ] **Step 2: Implement vendor picker resource and controller**

Create `apps/api/Domains/Vendor/Http/Resources/VendorPickerResource.php`:

```php
<?php

namespace Domains\Vendor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPickerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status,
            'riskRating' => $this->risk_rating,
            'defaultContact' => [
                'name' => data_get($this->metadata, 'contactName'),
                'email' => data_get($this->metadata, 'contactEmail'),
            ],
        ];
    }
}
```

Create `apps/api/Domains/Vendor/Http/Controllers/VendorPickerController.php`:

```php
<?php

namespace Domains\Vendor\Http\Controllers;

use App\Auth\TenantRole;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Vendor\Http\Resources\VendorPickerResource;
use Domains\Vendor\Models\Vendor;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class VendorPickerController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant): AnonymousResourceCollection
    {
        $tenant = $currentTenant->get();
        abort_if($tenant === null, 403, 'Tenant context missing.');
        abort_unless(in_array($currentTenant->roleFor($request->user()), [TenantRole::Buyer->value, TenantRole::Admin->value], true), 403);

        $query = Vendor::query()
            ->where('tenant_id', $tenant->id)
            ->when($request->query('status', 'active'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderBy('name')
            ->limit(50);

        return VendorPickerResource::collection($query->get());
    }
}
```

- [ ] **Step 3: Implement create action with duplicate prevention**

Create `apps/api/Domains/Quotation/Actions/CreateRfqInvitations.php`:

```php
<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateRfqInvitations
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): array
    {
        Gate::forUser($actor)->authorize('create', [RfqInvitation::class, $rfq]);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $data): array {
            $vendorIds = collect($data['vendorIds'])->map(fn ($id) => (int) $id)->unique()->values();
            $vendors = Vendor::query()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->whereIn('id', $vendorIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($vendors->count() !== $vendorIds->count()) {
                throw ValidationException::withMessages(['vendorIds' => ['All vendors must be active vendors in the current tenant.']]);
            }

            $existing = RfqInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereIn('vendor_id', $vendorIds)
                ->whereIn('status', [
                    RfqInvitationStatus::Pending->value,
                    RfqInvitationStatus::Sent->value,
                    RfqInvitationStatus::Acknowledged->value,
                ])
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                throw new ConflictHttpException('An active invitation already exists for one or more selected vendors.');
            }

            $overrides = collect($data['contactOverrides'] ?? [])->keyBy(fn ($item) => (int) $item['vendorId']);

            return $vendorIds->map(function (int $vendorId) use ($tenant, $actor, $rfq, $vendors, $overrides, $data): RfqInvitation {
                $vendor = $vendors->get($vendorId);
                $override = $overrides->get($vendorId, []);
                $invitation = RfqInvitation::query()->create([
                    'tenant_id' => $tenant->id,
                    'rfq_id' => $rfq->id,
                    'vendor_id' => $vendor->id,
                    'status' => RfqInvitationStatus::Sent,
                    'contact_name' => $override['contactName'] ?? data_get($vendor->metadata, 'contactName'),
                    'contact_email' => $override['contactEmail'] ?? data_get($vendor->metadata, 'contactEmail'),
                    'message' => $data['message'] ?? null,
                    'response_due_at' => $data['responseDueAt'] ?? null,
                    'sent_at' => now(),
                ]);

                foreach (['rfq_invitation.created', 'rfq_invitation.sent'] as $event) {
                    $this->audit->record(new AuditEventData(
                        tenant: $tenant,
                        actor: $actor,
                        action: $event,
                        subject: $invitation,
                        metadata: ['rfqId' => (string) $rfq->id, 'vendorId' => (string) $vendor->id],
                        subjectDisplay: $vendor->name,
                    ));
                }

                return $invitation->load('vendor');
            })->all();
        });
    }
}
```

- [ ] **Step 4: Implement resend, cancel, and status actions**

Create the remaining actions using the same `AuditRecorder` pattern:

```php
// ResendRfqInvitation::handle(...)
Gate::forUser($actor)->authorize('resend', $invitation);
return DB::transaction(function () use ($tenant, $actor, $invitation): RfqInvitation {
    $lockedInvitation = RfqInvitation::query()
        ->where('tenant_id', $tenant->id)
        ->whereKey($invitation->id)
        ->lockForUpdate()
        ->firstOrFail();

    $lockedInvitation->forceFill(['status' => RfqInvitationStatus::Sent, 'sent_at' => now()])->save();
    $this->audit->record(new AuditEventData($tenant, $actor, 'rfq_invitation.resent', $lockedInvitation, ['rfqId' => (string) $lockedInvitation->rfq_id], $lockedInvitation->vendor?->name));

    return $lockedInvitation->refresh()->load('vendor');
});

// CancelRfqInvitation::handle(...)
Gate::forUser($actor)->authorize('cancel', $invitation);
return DB::transaction(function () use ($tenant, $actor, $invitation, $data): RfqInvitation {
    $lockedInvitation = RfqInvitation::query()
        ->where('tenant_id', $tenant->id)
        ->whereKey($invitation->id)
        ->lockForUpdate()
        ->firstOrFail();

    $lockedInvitation->forceFill(['status' => RfqInvitationStatus::Cancelled, 'cancel_reason' => $data['cancelReason'], 'cancelled_at' => now()])->save();
    $this->audit->record(new AuditEventData($tenant, $actor, 'rfq_invitation.cancelled', $lockedInvitation, ['rfqId' => (string) $lockedInvitation->rfq_id], $lockedInvitation->vendor?->name));

    return $lockedInvitation->refresh()->load('vendor');
});

// UpdateRfqInvitationStatus::handle(...)
Gate::forUser($actor)->authorize('updateStatus', $invitation);
return DB::transaction(function () use ($tenant, $actor, $invitation, $data): RfqInvitation {
    $lockedInvitation = RfqInvitation::query()
        ->where('tenant_id', $tenant->id)
        ->whereKey($invitation->id)
        ->lockForUpdate()
        ->firstOrFail();
    $status = RfqInvitationStatus::from($data['status']);
    $timestampColumn = match ($status) {
        RfqInvitationStatus::Acknowledged => 'acknowledged_at',
        RfqInvitationStatus::Declined => 'declined_at',
        RfqInvitationStatus::Expired => 'expired_at',
        default => null,
    };
    $attributes = ['status' => $status];
    if ($timestampColumn !== null) $attributes[$timestampColumn] = now();
    $lockedInvitation->forceFill($attributes)->save();
    $this->audit->record(new AuditEventData($tenant, $actor, 'rfq_invitation.' . $status->value, $lockedInvitation, ['rfqId' => (string) $lockedInvitation->rfq_id], $lockedInvitation->vendor?->name));

    return $lockedInvitation->refresh()->load('vendor');
});
```

When writing these files, use named arguments for `AuditEventData` if the constructor requires them in this repo.

- [ ] **Step 5: Implement controller**

Create `apps/api/Domains/Quotation/Http/Controllers/RfqInvitationController.php` with thin methods:

```php
public function index(CurrentTenant $currentTenant, int $rfq): AnonymousResourceCollection
{
    $tenant = $this->tenantOrAbort($currentTenant);
    $model = $this->findTenantRfq($tenant, $rfq);
    $this->authorize('viewAny', [RfqInvitation::class, $model]);

    return RfqInvitationResource::collection(
        RfqInvitation::query()->with('vendor')->where('tenant_id', $tenant->id)->where('rfq_id', $model->id)->latest()->get()
    );
}
```

Add `store`, `resend`, `cancel`, and `status` methods that call the action classes and return `RfqInvitationResource` or a collection. Reuse private `findTenantRfq`, `findTenantInvitation`, and `tenantOrAbort` helpers matching `RfqController` style.

- [ ] **Step 6: Run backend tests**

```bash
cd apps/api && php artisan test --filter=VendorPickerApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
cd apps/api && php artisan test --filter=RfqDraftApiTest
```

Expected: all three filters pass.

- [ ] **Step 7: Commit backend implementation**

```bash
git add apps/api/database/migrations/2026_05_19_020000_create_rfq_invitations_table.php apps/api/Domains/Quotation apps/api/Domains/Vendor apps/api/app/Providers/AppServiceProvider.php apps/api/routes/api.php apps/api/tests/Feature/VendorPickerApiTest.php apps/api/tests/Feature/RfqInvitationApiTest.php
git commit -m "feat: add RFQ invitation backend workflow"
```

## Task 4: OpenAPI And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Update: `packages/api-client/src/generated/**`
- Modify if needed: `packages/api-client/src/index.ts`

- [ ] **Step 1: Add OpenAPI paths**

Add operations with these operation IDs:

- `listVendors`
- `listRfqInvitations`
- `createRfqInvitations`
- `resendRfqInvitation`
- `cancelRfqInvitation`
- `updateRfqInvitationStatus`

Required schemas:

- `VendorPickerItem`
- `VendorPickerListResponse`
- `RfqInvitationStatus`
- `RfqInvitation`
- `RfqInvitationListResponse`
- `CreateRfqInvitationsRequest`
- `CancelRfqInvitationRequest`
- `UpdateRfqInvitationStatusRequest`

Use existing error schemas for `401`, `403`, `404`, `409`, and `422`.

- [ ] **Step 2: Generate and check client**

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: Orval updates generated endpoints and schemas, and the contract check exits 0.

- [ ] **Step 3: Commit contract changes**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated packages/api-client/src/index.ts
git commit -m "feat: expose RFQ invitation API contract"
```

## Task 5: Frontend API Wrappers, Hooks, Schemas, And MSW

**Files:** frontend create/modify files listed in File Map

- [ ] **Step 1: Create view models and schemas**

Create `apps/web/features/sourcing/types/rfq-invitation-view-model.ts`:

```ts
import type { RfqInvitation as ApiRfqInvitation, RfqInvitationStatus as ApiRfqInvitationStatus } from "@cognify/api-client/schemas";

export type RfqInvitationStatus = ApiRfqInvitationStatus;
export type RfqInvitation = ApiRfqInvitation;

export function toRfqInvitationViewModel(invitation: ApiRfqInvitation): RfqInvitation {
  return invitation;
}
```

Create `apps/web/features/sourcing/types/vendor-view-model.ts`:

```ts
import type { VendorPickerItem as ApiVendorPickerItem } from "@cognify/api-client/schemas";

export type VendorPickerItem = ApiVendorPickerItem;
```

Create `apps/web/features/sourcing/schemas/rfq-invitation-schema.ts`:

```ts
import { z } from "zod";

export const createRfqInvitationsSchema = z.object({
  vendorIds: z.array(z.string().regex(/^\d+$/, "Vendor id must be numeric.")).min(1, "Select at least one vendor."),
  message: z.string().max(5000).nullable().optional(),
  responseDueAt: z.string().nullable().optional(),
});

export const cancelRfqInvitationSchema = z.object({
  cancelReason: z.string().min(1, "Enter a cancellation reason.").max(5000),
});

export type CreateRfqInvitationsValues = z.infer<typeof createRfqInvitationsSchema>;
export type CancelRfqInvitationValues = z.infer<typeof cancelRfqInvitationSchema>;
```

- [ ] **Step 2: Create API wrappers**

Create `apps/web/features/sourcing/api/vendor-api.ts` and `apps/web/features/sourcing/api/rfq-invitation-api.ts` using the `withActiveTenantHeader` pattern from `rfq-api.ts`. Throw `response.data` when status is not expected.

- [ ] **Step 3: Create hooks**

Create hooks:

```ts
export const rfqInvitationKeys = {
  list: (rfqId: string, tenantId: string | null = getStoredActiveTenantId()) => ["sourcing", "rfq", tenantId ?? "no-tenant", rfqId, "invitations"] as const,
};
```

Use `useQuery` for list/vendor picker and `useMutation` for create/resend/cancel/status update. On mutation success, invalidate `rfqInvitationKeys.list(rfqId)` and `rfqDraftKeys.detail(rfqId)`.

- [ ] **Step 4: Create MSW fixtures and handlers**

Create fixtures with at least:

- two active vendors;
- one inactive vendor omitted from picker;
- one sent invitation;
- conflict response for duplicate create;
- non-draft/read-only behavior.

Register handlers in `apps/web/tests/msw/handlers.ts`.

- [ ] **Step 5: Run frontend typecheck for wrappers**

```bash
pnpm --filter @cognify/web typecheck
```

Expected: typecheck passes or only fails on UI files not yet created; fix generated type names before moving on.

## Task 6: RFQ Workspace Invitation UI

**Files:** frontend components/workflow/test files listed in File Map

- [ ] **Step 1: Write failing web workflow test**

Create `apps/web/features/sourcing/tests/rfq-invitations-workflow.test.tsx` with tests for panel render, create, conflict, cancel, and read-only states. Reuse `TestAppProviders` from `rfq-draft-workflow.test.tsx`.

Key expectations:

```ts
expect(await screen.findByRole("heading", { name: "Vendor invitations" })).toBeInTheDocument();
await user.click(screen.getByRole("button", { name: "Invite vendors" }));
await user.click(screen.getByRole("checkbox", { name: /Acme Supplies/ }));
await user.click(screen.getByRole("button", { name: "Create invitations" }));
expect(await screen.findByText("Acme Supplies")).toBeInTheDocument();
```

- [ ] **Step 2: Implement status badge**

Create `apps/web/features/sourcing/components/rfq-invitation-status-badge.tsx` using the same badge style pattern as `rfq-status-badge.tsx` and `sourcing-intake-status-badge.tsx`.

- [ ] **Step 3: Implement vendor picker and dialog**

Create `vendor-picker.tsx` and `rfq-invitation-dialog.tsx` with accessible labels:

- `Search vendors`
- checkbox label containing vendor name;
- `Response due date`;
- `Message`;
- `Create invitations`.

The dialog must show the empty vendor state: `No active vendors are available for invitation.`

- [ ] **Step 4: Implement invitation panel**

Create `rfq-invitation-panel.tsx` with props:

```ts
type RfqInvitationPanelProps = {
  rfqId: string;
  canInvite: boolean;
  readOnlyReason?: string;
};
```

Panel behavior:

- load invitations via `useRfqInvitations(rfqId)`;
- show `Invite vendors` when `canInvite` is true;
- show read-only copy when RFQ is not draft;
- render table on desktop and cards on small screens using existing CSS conventions;
- provide `Resend` and `Cancel` actions based on permissions;
- require cancel reason before calling mutation.

- [ ] **Step 5: Replace RFQ workspace placeholder**

Modify `apps/web/features/sourcing/workflows/rfq-draft-workspace.tsx` by replacing the placeholder section with:

```tsx
<RfqInvitationPanel
  rfqId={rfq.id}
  canInvite={rfq.status === "draft" && rfq.permissions.canInviteVendors}
  readOnlyReason={rfq.status === "draft" ? undefined : "Vendor invitations are read-only because this RFQ is not a draft."}
/>
```

If the generated `Rfq` permission remains `canInviteVendors: false`, update `RfqResource` and OpenAPI in Task 4 to return `true` for draft RFQs where the actor can manage invitations.

- [ ] **Step 6: Run web tests**

```bash
pnpm --filter @cognify/web test -- sourcing
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: sourcing tests, typecheck, and lint pass.

- [ ] **Step 7: Commit frontend implementation**

```bash
git add apps/web/features/sourcing apps/web/tests/msw/handlers.ts
git commit -m "feat: add RFQ invitation workspace"
```

## Task 7: Full Verification And Architecture Drift Check

**Files:** all touched files

- [ ] **Step 1: Run contract and API checks**

```bash
pnpm check:api-contract
cd apps/api && php artisan test --filter=VendorPickerApiTest
cd apps/api && php artisan test --filter=RfqInvitationApiTest
cd apps/api && php artisan test --filter=RfqDraftApiTest
cd apps/api && php artisan route:list --path=api/vendors
cd apps/api && php artisan route:list --path=api/rfq
```

Expected: all commands exit 0; route list includes vendor picker and RFQ invitation endpoints.

- [ ] **Step 2: Run frontend checks**

```bash
pnpm --filter @cognify/web test -- sourcing
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
pnpm build
```

Expected: all commands exit 0.

- [ ] **Step 3: Run scope drift grep**

```bash
rg -n "vendor portal|invitation token|quotation upload|manual quotation|comparison|scoring|award decision|purchase order|email delivery" apps/api apps/web/features/sourcing docs/superpowers/plans/2026-05-19-vendor-invitation-to-rfq.md
```

Expected: matches are limited to explicit non-goal/future-state copy and comments; no vendor portal, quotation capture, award, PO handoff, or email delivery implementation exists.

- [ ] **Step 4: Run whitespace check**

```bash
git diff --check
```

Expected: no whitespace errors.

- [ ] **Step 5: Final commit if needed**

If verification required fixes after the previous commits:

```bash
git add <fixed-files>
git commit -m "fix: harden RFQ invitation workflow"
```

## Self-Review

Spec coverage:

- Existing tenant vendors are used through the vendor picker task.
- RFQ invitation records, statuses, permissions, audit, and duplicate prevention are covered by backend tasks.
- RFQ workspace panel, vendor picker, create/resend/cancel UI, read-only state, and MSW tests are covered by frontend tasks.
- Vendor portal, email delivery, quotation capture, scoring, award, and PO handoff remain explicit non-goals and are checked by scope grep.

Placeholder scan:

- No unresolved placeholders are present.
- Steps name exact files and commands.
- Where full generated OpenAPI JSON is not embedded, the operation IDs and schema names are exact because generated output must be produced by `pnpm generate:api`.

Type consistency:

- Backend names use `RfqInvitation`, `RfqInvitationStatus`, and `RfqInvitationResource` consistently.
- Frontend names use `RfqInvitation`, `VendorPickerItem`, and generated API wrapper names from the OpenAPI operation IDs.

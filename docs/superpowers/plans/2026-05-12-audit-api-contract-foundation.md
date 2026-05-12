# Audit And API Contract Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's P0 audit, API error, and generated-client foundation so future procurement workflows share one tenant-safe audit trail and one predictable API contract.

**Architecture:** Keep audit and API errors as cross-cutting Laravel infrastructure under `apps/api/app/*`, while domain actions record audit events through a small recorder API. OpenAPI remains the contract source in `apps/api/storage/openapi/openapi.json`; Orval regenerates `packages/api-client`; frontend feature hooks and MSW handlers consume generated types and shared error helpers. The epic intentionally avoids a full audit UI and focuses on infrastructure, contract, and tests.

**Tech Stack:** Laravel 12, Sanctum, PHPUnit, Eloquent resources, Next.js App Router, TanStack Query, MSW, Vitest, Orval-generated TypeScript fetch client.

---

## Runbook Alignment

Follow `docs/05-runbooks/feature-development.md`:

1. Start from the audit workflow and tenant/security rules.
2. Write regression tests before editing production code.
3. Update backend infrastructure and API resource shape.
4. Update OpenAPI before consuming new frontend types.
5. Regenerate `@cognify/api-client`.
6. Add typed frontend helpers and MSW fixtures using OpenAPI-shaped responses.
7. Run narrow checks first, then broader root checks.

## File Structure

Create:

- `apps/api/app/Audit/AuditEventData.php`: immutable DTO for recorder input.
- `apps/api/app/Audit/AuditEventResource.php`: shared API shape for audit events and activity timelines.
- `apps/api/app/Audit/AuditSubject.php`: maps Eloquent classes to stable API subject types.
- `apps/api/app/Audit/Http/Controllers/AuditEventController.php`: tenant audit feed endpoint.
- `apps/api/app/Audit/Policies/AuditEventPolicy.php`: P0 role gate for platform audit feed.
- `apps/api/app/Exceptions/ApiErrorCode.php`: enum of public error codes.
- `apps/api/app/Exceptions/ApiErrorResponse.php`: stable error envelope builder.
- `apps/api/app/Http/Middleware/AssignRequestId.php`: request ID generation and response header.
- `apps/api/database/migrations/2026_05_12_010000_harden_audit_events_table.php`: additive audit columns.
- `apps/api/tests/Feature/AuditApiTest.php`: audit write/read/query/append-only tests.
- `apps/api/tests/Feature/ApiErrorContractTest.php`: shared API error envelope tests.
- `packages/api-client/src/errors.ts`: typed API error helper functions.
- `apps/web/features/audit/api/audit-api.ts`: typed hook-facing API wrapper.
- `apps/web/features/audit/hooks/use-audit-events.ts`: TanStack Query hook.
- `apps/web/features/audit/mocks/audit-fixtures.ts`: OpenAPI-shaped audit fixture data.
- `apps/web/features/audit/mocks/audit-handlers.ts`: MSW handler for audit feed.
- `apps/web/features/audit/tests/audit-api.test.ts`: hook/helper and error parsing coverage.
- `apps/web/features/audit/types/audit-view-model.ts`: feature-local view model aliases only when generated types need UI adaptation.

Modify:

- `apps/api/app/Audit/AuditEvent.php`: fillable/casts/append-only guards.
- `apps/api/app/Audit/AuditRecorder.php`: accept DTOs and enrich request context.
- `apps/api/app/Providers/AppServiceProvider.php`: register audit policy if needed.
- `apps/api/bootstrap/app.php`: register request ID middleware and API exception rendering.
- `apps/api/routes/api.php`: add protected `GET /audit/events`.
- `apps/api/Domains/Requisition/Actions/CreateRequisitionDraft.php`: call new recorder shape.
- `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`: record before/after context.
- `apps/api/Domains/Requisition/Actions/SubmitRequisition.php`: record before/after context.
- `apps/api/Domains/Requisition/Http/Controllers/RequisitionActivityController.php`: use `AuditEventResource`.
- `apps/api/Domains/Requisition/Http/Resources/RequisitionActivityResource.php`: remove after callers migrate.
- `apps/api/storage/openapi/openapi.json`: add audit endpoint and normalized error schemas/responses.
- `packages/api-client/src/generated/*`: regenerate with Orval.
- `packages/api-client/src/index.ts`: export error helpers.
- `apps/web/tests/msw/handlers.ts`: register audit handler.

Do not modify `packages/ui`; this epic adds no reusable UI primitives.

## Workflow Map

```txt
Actors:
  requester, buyer, approver, admin, system

Tenant context:
  resolved by auth:sanctum + ResolveCurrentTenant
  never accepted as an audit query parameter

Audit write transition:
  domain action begins transaction
  domain state changes
  domain action calls AuditRecorder::record()
  AuditRecorder writes append-only AuditEvent in same transaction
  transaction commits state and audit together

Audit read transition:
  user requests /api/audit/events
  ResolveCurrentTenant validates tenant membership
  AuditEventPolicy allows buyer, approver, admin
  controller filters tenant events and returns paginated AuditEventResource

Domain activity read transition:
  user requests /api/requisitions/{id}/activity
  requisition policy validates record access
  controller returns subject-scoped AuditEventResource records

Failure paths:
  unauthenticated -> 401 unauthenticated
  missing tenant for multi-tenant user -> 400 ambiguous_tenant
  unauthorized audit feed -> 403 forbidden
  invalid filters -> 422 validation_failed
  stale or invalid workflow transition -> 409 conflict
  missing record -> 404 not_found
```

## Task 1: Baseline Contract And Audit Regression Tests

**Files:**
- Create: `apps/api/tests/Feature/AuditApiTest.php`
- Create: `apps/api/tests/Feature/ApiErrorContractTest.php`
- Read: `apps/api/app/Audit/AuditRecorder.php`
- Read: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`
- Read: `apps/api/bootstrap/app.php`

- [ ] **Step 1: Confirm baseline**

Run:

```bash
git status --short --branch
```

Expected: no unrelated modified files. If unrelated files exist, do not edit or revert them.

- [ ] **Step 2: Add failing audit contract tests**

Create `apps/api/tests/Feature/AuditApiTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorder_creates_append_only_tenant_scoped_event_with_context(): void
    {
        [$tenant, $actor] = $this->tenantUser('buyer');
        $requisition = $this->requisition($tenant, $actor);

        $event = app(AuditRecorder::class)->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'requisition.submitted',
            subject: $requisition,
            metadata: ['status' => 'submitted'],
            before: ['status' => 'draft'],
            after: ['status' => 'submitted'],
            subjectDisplay: $requisition->number,
        ));

        $this->assertDatabaseHas('audit_events', [
            'id' => $event->id,
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'requisition.submitted',
            'action' => 'requisition.submitted',
            'subject_display' => $requisition->number,
        ]);

        $this->assertNotEmpty($event->event_id);
        $this->assertSame(['status' => 'draft'], $event->before);
        $this->assertSame(['status' => 'submitted'], $event->after);
    }

    public function test_audit_events_cannot_be_updated_or_deleted_through_model(): void
    {
        [$tenant, $actor] = $this->tenantUser('admin');
        $event = AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => 'tenant.updated',
            'action' => 'tenant.updated',
            'subject_type' => Tenant::class,
            'subject_id' => $tenant->id,
            'subject_display' => $tenant->name,
            'metadata' => ['field' => 'name'],
            'occurred_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $event->forceFill(['metadata' => ['field' => 'changed']])->save();
    }

    public function test_admin_can_query_tenant_audit_feed(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        $requisition = $this->requisition($tenant, $admin);
        $this->audit($tenant, $admin, $requisition, 'requisition.created');
        $this->audit($tenant, $admin, $requisition, 'requisition.submitted');

        $response = $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/audit/events?action=requisition.submitted&subjectType=requisition&perPage=50');

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'requisition.submitted')
            ->assertJsonPath('data.0.subject.type', 'requisition')
            ->assertJsonPath('data.0.subject.id', (string) $requisition->id)
            ->assertJsonPath('meta.perPage', 50);
    }

    public function test_requester_cannot_query_platform_audit_feed(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $requester)
            ->getJson('/api/audit/events')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_audit_feed_is_tenant_scoped(): void
    {
        [$tenant, $admin] = $this->tenantUser('admin');
        [$otherTenant, $otherAdmin] = $this->tenantUser('admin');
        $this->audit($tenant, $admin, $this->requisition($tenant, $admin), 'requisition.created');
        $this->audit($otherTenant, $otherAdmin, $this->requisition($otherTenant, $otherAdmin), 'requisition.submitted');

        $this->actingAsTenant($tenant, $admin)
            ->getJson('/api/audit/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'requisition.created');
    }

    public function test_requisition_activity_uses_shared_audit_resource_shape(): void
    {
        [$tenant, $requester] = $this->tenantUser('requester');
        $requisition = $this->requisition($tenant, $requester);
        $this->audit($tenant, $requester, $requisition, 'requisition.updated');

        $this->actingAsTenant($tenant, $requester)
            ->getJson("/api/requisitions/{$requisition->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'requisition.updated')
            ->assertJsonPath('data.0.subject.type', 'requisition')
            ->assertJsonPath('data.0.actor.id', (string) $requester->id);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    private function requisition(Tenant $tenant, User $requester): Requisition
    {
        return Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $requester->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Laptop refresh',
            'business_justification' => 'Replace aging laptops.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'USD',
            'status' => RequisitionStatus::Draft,
        ]);
    }

    private function audit(Tenant $tenant, User $actor, Requisition $subject, string $action): AuditEvent
    {
        return AuditEvent::query()->create([
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'event_type' => $action,
            'action' => $action,
            'subject_type' => Requisition::class,
            'subject_id' => $subject->id,
            'subject_display' => $subject->number,
            'metadata' => ['status' => $subject->status->value],
            'occurred_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Add failing API error contract tests**

Create `apps/api/tests/Feature/ApiErrorContractTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiErrorContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_error_uses_normalized_envelope(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonPath('error.message', 'Authentication is required.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details', 'requestId']]);
    }

    public function test_validation_error_uses_field_detail_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->postJson('/api/requisitions', [
                'title' => '',
                'lineItems' => [
                    [
                        'name' => '',
                        'quantity' => -1,
                        'unit' => '',
                        'estimatedUnitPrice' => '-10.00',
                        'currency' => 'US',
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['details' => ['fields' => ['title', 'lineItems.0.quantity']]]]);
    }

    public function test_not_found_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisitions/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_conflict_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = Requisition::query()->create([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => 'REQ-2026-000001',
            'title' => 'Submitted requisition',
            'business_justification' => 'Already submitted.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'USD',
            'status' => RequisitionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAsTenant($tenant, $user)
            ->patchJson("/api/requisitions/{$requisition->id}", ['title' => 'Changed'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict');
    }

    public function test_ambiguous_tenant_error_uses_normalized_envelope(): void
    {
        [$tenant, $user] = $this->tenantUser('requester');
        $secondTenant = Tenant::query()->create(['name' => 'Second tenant']);
        $secondTenant->users()->attach($user->id, ['role' => 'requester']);

        Sanctum::actingAs($user);

        $this->getJson('/api/requisitions')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'ambiguous_tenant');
    }

    public function test_response_includes_request_id_header_and_error_request_id(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req_test_123')
            ->getJson('/api/me');

        $response->assertHeader('X-Request-Id', 'req_test_123')
            ->assertJsonPath('error.requestId', 'req_test_123');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }
}
```

- [ ] **Step 4: Run tests and verify they fail for missing foundation**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
cd apps/api && php artisan test --filter=ApiErrorContractTest
```

Expected: failures mention missing `AuditEventData`, missing audit route/resource, and default Laravel error shapes.

## Task 2: Harden Audit Storage And Model

**Files:**
- Create: `apps/api/database/migrations/2026_05_12_010000_harden_audit_events_table.php`
- Modify: `apps/api/app/Audit/AuditEvent.php`

- [ ] **Step 1: Add additive audit migration**

Create `apps/api/database/migrations/2026_05_12_010000_harden_audit_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->uuid('event_id')->nullable()->after('id');
            $table->string('action')->nullable()->after('event_type');
            $table->string('subject_display')->nullable()->after('subject_id');
            $table->json('before')->nullable()->after('metadata');
            $table->json('after')->nullable()->after('before');
            $table->string('ip_address', 45)->nullable()->after('after');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('request_id')->nullable()->after('user_agent');

            $table->unique('event_id');
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'occurred_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropUnique(['event_id']);
            $table->dropIndex(['tenant_id', 'action']);
            $table->dropIndex(['tenant_id', 'occurred_at']);
            $table->dropIndex(['tenant_id', 'subject_type', 'subject_id']);
            $table->dropIndex(['request_id']);
            $table->dropColumn([
                'event_id',
                'action',
                'subject_display',
                'before',
                'after',
                'ip_address',
                'user_agent',
                'request_id',
            ]);
        });
    }
};
```

- [ ] **Step 2: Update AuditEvent model**

Replace `apps/api/app/Audit/AuditEvent.php` with:

```php
<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

class AuditEvent extends Model
{
    protected $fillable = [
        'event_id',
        'tenant_id',
        'actor_id',
        'event_type',
        'action',
        'subject_type',
        'subject_id',
        'subject_display',
        'metadata',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'request_id',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditEvent $event): void {
            $event->event_id ??= (string) Str::uuid();
            $event->action ??= $event->event_type;
            $event->event_type ??= $event->action;
        });

        static::updating(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
```

- [ ] **Step 3: Run audit tests for storage failure progress**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
```

Expected: storage/model assertions pass farther; failures remain for missing `AuditEventData`, route, resource, and recorder shape.

## Task 3: Add Audit Recorder DTO, Subject Mapping, And Resource

**Files:**
- Create: `apps/api/app/Audit/AuditEventData.php`
- Create: `apps/api/app/Audit/AuditSubject.php`
- Create: `apps/api/app/Audit/AuditEventResource.php`
- Modify: `apps/api/app/Audit/AuditRecorder.php`

- [ ] **Step 1: Add recorder input DTO**

Create `apps/api/app/Audit/AuditEventData.php`:

```php
<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditEventData
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function __construct(
        public readonly Tenant $tenant,
        public readonly ?User $actor,
        public readonly string $action,
        public readonly Model $subject,
        public readonly array $metadata = [],
        public readonly ?array $before = null,
        public readonly ?array $after = null,
        public readonly ?string $subjectDisplay = null,
    ) {
    }
}
```

- [ ] **Step 2: Add stable subject mapper**

Create `apps/api/app/Audit/AuditSubject.php`:

```php
<?php

namespace App\Audit;

use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditSubject
{
    public static function typeFor(Model|string $subject): string
    {
        $class = is_string($subject) ? $subject : $subject::class;

        return match ($class) {
            Requisition::class => 'requisition',
            default => Str::of($class)->classBasename()->snake()->toString(),
        };
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $type): ?string
    {
        return match ($type) {
            'requisition' => Requisition::class,
            default => null,
        };
    }

    public static function displayFor(Model $subject): ?string
    {
        if (property_exists($subject, 'number') || isset($subject->number)) {
            return (string) $subject->number;
        }

        if (property_exists($subject, 'name') || isset($subject->name)) {
            return (string) $subject->name;
        }

        if (property_exists($subject, 'title') || isset($subject->title)) {
            return (string) $subject->title;
        }

        return null;
    }
}
```

- [ ] **Step 3: Add shared audit resource**

Create `apps/api/app/Audit/AuditEventResource.php`:

```php
<?php

namespace App\Audit;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->event_id ?? (string) $this->id,
            'action' => $this->action ?? $this->event_type,
            'message' => $this->message(),
            'actor' => $this->actor ? [
                'id' => (string) $this->actor->id,
                'name' => $this->actor->name,
                'email' => $this->actor->email,
            ] : null,
            'subject' => [
                'type' => AuditSubject::typeFor($this->subject_type),
                'id' => (string) $this->subject_id,
                'display' => $this->subject_display,
            ],
            'metadata' => $this->metadata ?? [],
            'before' => $this->before,
            'after' => $this->after,
            'occurredAt' => $this->occurred_at?->toISOString(),
            'requestId' => $this->request_id,
        ];
    }

    private function message(): string
    {
        return match ($this->action ?? $this->event_type) {
            'requisition.created' => 'Draft created',
            'requisition.updated' => 'Draft updated',
            'requisition.submitted' => 'Submitted for review',
            default => $this->action ?? $this->event_type,
        };
    }
}
```

- [ ] **Step 4: Update AuditRecorder**

Replace `apps/api/app/Audit/AuditRecorder.php` with:

```php
<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function record(
        AuditEventData|Tenant $data,
        ?User $actor = null,
        ?string $eventType = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditEvent {
        $eventData = $data instanceof AuditEventData
            ? $data
            : new AuditEventData(
                tenant: $data,
                actor: $actor,
                action: (string) $eventType,
                subject: $subject,
                metadata: $metadata,
            );

        $request = request();

        return AuditEvent::query()->create([
            'tenant_id' => $eventData->tenant->id,
            'actor_id' => $eventData->actor?->id,
            'event_type' => $eventData->action,
            'action' => $eventData->action,
            'subject_type' => $eventData->subject::class,
            'subject_id' => $eventData->subject->getKey(),
            'subject_display' => $eventData->subjectDisplay ?? AuditSubject::displayFor($eventData->subject),
            'metadata' => $eventData->metadata,
            'before' => $eventData->before,
            'after' => $eventData->after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->attributes->get('request_id'),
            'occurred_at' => now(),
        ]);
    }
}
```

- [ ] **Step 5: Run audit tests**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
```

Expected: recorder test passes; route and policy tests still fail.

## Task 4: Add Audit Feed Endpoint And Migrate Activity Resource

**Files:**
- Create: `apps/api/app/Audit/Http/Controllers/AuditEventController.php`
- Create: `apps/api/app/Audit/Policies/AuditEventPolicy.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/Domains/Requisition/Http/Controllers/RequisitionActivityController.php`
- Delete after migration: `apps/api/Domains/Requisition/Http/Resources/RequisitionActivityResource.php`

- [ ] **Step 1: Add audit event policy**

Create `apps/api/app/Audit/Policies/AuditEventPolicy.php`:

```php
<?php

namespace App\Audit\Policies;

use App\Models\User;
use App\Tenancy\CurrentTenant;

class AuditEventPolicy
{
    public function viewAny(User $user): bool
    {
        $role = app(CurrentTenant::class)->roleFor($user);

        return in_array($role, ['buyer', 'approver', 'admin'], true);
    }
}
```

- [ ] **Step 2: Register policy**

Modify `apps/api/app/Providers/AppServiceProvider.php` to preserve the existing scoped tenant binding and requisition policy while adding the audit policy:

```php
<?php

namespace App\Providers;

use App\Audit\AuditEvent;
use App\Audit\Policies\AuditEventPolicy;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Policies\RequisitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    public function boot(): void
    {
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
    }
}
```

- [ ] **Step 3: Add controller**

Create `apps/api/app/Audit/Http/Controllers/AuditEventController.php`:

```php
<?php

namespace App\Audit\Http\Controllers;

use App\Audit\AuditEvent;
use App\Audit\AuditEventResource;
use App\Audit\AuditSubject;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditEventController extends Controller
{
    public function index(Request $request, CurrentTenant $currentTenant)
    {
        $this->authorize('viewAny', AuditEvent::class);

        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:120'],
            'actorId' => ['sometimes', 'integer'],
            'subjectType' => ['sometimes', 'string', Rule::in(['requisition'])],
            'subjectId' => ['sometimes', 'integer'],
            'occurredFrom' => ['sometimes', 'date'],
            'occurredTo' => ['sometimes', 'date', 'after_or_equal:occurredFrom'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditEvent::query()
            ->with('actor')
            ->where('tenant_id', $currentTenant->get()->id)
            ->latest('occurred_at')
            ->latest('id');

        $query->when($validated['action'] ?? null, fn ($query, string $action) => $query->where('action', $action));
        $query->when($validated['actorId'] ?? null, fn ($query, int $actorId) => $query->where('actor_id', $actorId));
        $query->when($validated['subjectType'] ?? null, function ($query, string $subjectType): void {
            $subjectClass = AuditSubject::classFor($subjectType);
            $query->where('subject_type', $subjectClass);
        });
        $query->when($validated['subjectId'] ?? null, fn ($query, int $subjectId) => $query->where('subject_id', $subjectId));
        $query->when($validated['occurredFrom'] ?? null, fn ($query, string $date) => $query->where('occurred_at', '>=', $date));
        $query->when($validated['occurredTo'] ?? null, fn ($query, string $date) => $query->where('occurred_at', '<=', $date));

        $perPage = max(1, min((int) ($validated['perPage'] ?? 25), 100));
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => AuditEventResource::collection($paginator->getCollection())->resolve(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }
}
```

- [ ] **Step 4: Register route**

Modify `apps/api/routes/api.php` inside the `ResolveCurrentTenant` group:

```php
use App\Audit\Http\Controllers\AuditEventController;

Route::get('/audit/events', [AuditEventController::class, 'index']);
```

- [ ] **Step 5: Migrate requisition activity controller to shared resource**

Replace the resource import and return in `apps/api/Domains/Requisition/Http/Controllers/RequisitionActivityController.php`:

```php
use App\Audit\AuditEventResource;

return AuditEventResource::collection($events);
```

Remove the import for `Domains\Requisition\Http\Resources\RequisitionActivityResource`.

- [ ] **Step 6: Delete old domain-specific activity resource**

Delete:

```txt
apps/api/Domains/Requisition/Http/Resources/RequisitionActivityResource.php
```

- [ ] **Step 7: Run audit tests**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
```

Expected: audit feed and activity resource tests pass; any remaining failures should be limited to error envelope shape.

## Task 5: Update Requisition Audit Calls To Use Before/After Context

**Files:**
- Modify: `apps/api/Domains/Requisition/Actions/CreateRequisitionDraft.php`
- Modify: `apps/api/Domains/Requisition/Actions/UpdateRequisitionDraft.php`
- Modify: `apps/api/Domains/Requisition/Actions/SubmitRequisition.php`

- [ ] **Step 1: Update creation audit call**

In `CreateRequisitionDraft`, import `AuditEventData` and replace the recorder call:

```php
use App\Audit\AuditEventData;

$this->auditRecorder->record(new AuditEventData(
    tenant: $tenant,
    actor: $actor,
    action: 'requisition.created',
    subject: $requisition,
    metadata: ['status' => RequisitionStatus::Draft->value],
    after: [
        'status' => RequisitionStatus::Draft->value,
        'title' => $requisition->title,
        'lineItemCount' => $requisition->lineItems()->count(),
    ],
    subjectDisplay: $requisition->number,
));
```

- [ ] **Step 2: Update draft update audit call**

In `UpdateRequisitionDraft`, capture the previous state before mutation:

```php
$before = [
    'title' => $requisition->title,
    'businessJustification' => $requisition->business_justification,
    'neededByDate' => $requisition->needed_by_date?->toDateString(),
    'status' => $requisition->status->value,
    'lineItemCount' => $requisition->lineItems()->count(),
];
```

After saving and replacing line items, record:

```php
use App\Audit\AuditEventData;

$this->auditRecorder->record(new AuditEventData(
    tenant: $tenant,
    actor: $actor,
    action: 'requisition.updated',
    subject: $requisition,
    metadata: ['status' => $requisition->status->value],
    before: $before,
    after: [
        'title' => $requisition->title,
        'businessJustification' => $requisition->business_justification,
        'neededByDate' => $requisition->needed_by_date?->toDateString(),
        'status' => $requisition->status->value,
        'lineItemCount' => $requisition->lineItems()->count(),
    ],
    subjectDisplay: $requisition->number,
));
```

- [ ] **Step 3: Update submission audit call**

In `SubmitRequisition`, import `AuditEventData` and replace the recorder call:

```php
use App\Audit\AuditEventData;

$this->auditRecorder->record(new AuditEventData(
    tenant: $tenant,
    actor: $actor,
    action: 'requisition.submitted',
    subject: $requisition,
    metadata: ['status' => RequisitionStatus::Submitted->value],
    before: ['status' => RequisitionStatus::Draft->value],
    after: [
        'status' => RequisitionStatus::Submitted->value,
        'submittedAt' => $requisition->submitted_at?->toISOString(),
    ],
    subjectDisplay: $requisition->number,
));
```

- [ ] **Step 4: Run requisition and audit tests**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
cd apps/api && php artisan test --filter=RequisitionApiTest
```

Expected: audit tests pass except normalized error assertions if Task 6 is not complete; existing requisition audit assertions still pass because `event_type` remains populated.

## Task 6: Add Request ID Middleware And API Error Envelope

**Files:**
- Create: `apps/api/app/Exceptions/ApiErrorCode.php`
- Create: `apps/api/app/Exceptions/ApiErrorResponse.php`
- Create: `apps/api/app/Http/Middleware/AssignRequestId.php`
- Modify: `apps/api/bootstrap/app.php`

- [ ] **Step 1: Add public error code enum**

Create `apps/api/app/Exceptions/ApiErrorCode.php`:

```php
<?php

namespace App\Exceptions;

enum ApiErrorCode: string
{
    case ValidationFailed = 'validation_failed';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case AmbiguousTenant = 'ambiguous_tenant';
    case TooManyRequests = 'too_many_requests';
    case ServerError = 'server_error';
}
```

- [ ] **Step 2: Add error response builder**

Create `apps/api/app/Exceptions/ApiErrorResponse.php`:

```php
<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiErrorResponse
{
    /**
     * @param array<string, mixed> $details
     */
    public static function make(
        Request $request,
        ApiErrorCode $code,
        string $message,
        int $status,
        array $details = [],
        array $headers = [],
    ): JsonResponse {
        $requestId = $request->attributes->get('request_id') ?? $request->headers->get('X-Request-Id');

        return response()
            ->json([
                'error' => [
                    'code' => $code->value,
                    'message' => $message,
                    'details' => (object) $details,
                    'requestId' => $requestId,
                ],
            ], $status, $headers)
            ->withHeaders(['X-Request-Id' => $requestId]);
    }
}
```

- [ ] **Step 3: Add request ID middleware**

Create `apps/api/app/Http/Middleware/AssignRequestId.php`:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->headers->get('X-Request-Id') ?: 'req_'.Str::uuid()->toString();
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
```

- [ ] **Step 4: Register middleware and exception rendering**

Modify `apps/api/bootstrap/app.php`:

```php
<?php

use App\Exceptions\ApiErrorCode;
use App\Exceptions\ApiErrorResponse;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->api(prepend: [AssignRequestId::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                ApiErrorCode::ValidationFailed,
                'The given data was invalid.',
                422,
                ['fields' => $exception->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Unauthenticated, 'Authentication is required.', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Forbidden, 'You are not allowed to perform this action.', 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::NotFound, 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (ConflictHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::Conflict, $exception->getMessage(), 409);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make(
                $request,
                ApiErrorCode::TooManyRequests,
                'Too many requests.',
                429,
                [],
                $exception->getHeaders(),
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*') || ! str_contains($exception->getMessage(), 'X-Tenant-Id')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::AmbiguousTenant, $exception->getMessage(), 400);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiErrorResponse::make($request, ApiErrorCode::ServerError, 'An unexpected server error occurred.', 500);
        });
    })->create();
```

- [ ] **Step 5: Run API error tests**

Run:

```bash
cd apps/api && php artisan test --filter=ApiErrorContractTest
```

Expected: all normalized error envelope tests pass. If validation `details` serializes as an array instead of an object for empty details, adjust `ApiErrorResponse::make()` to keep empty details as `{}` and field details as an object with `fields`.

## Task 7: Update OpenAPI For Audit And Error Contract

**Files:**
- Modify: `apps/api/storage/openapi/openapi.json`
- Generated: `packages/api-client/src/generated/*`

- [ ] **Step 1: Add audit endpoint path**

In `apps/api/storage/openapi/openapi.json`, add:

```json
"/api/audit/events": {
  "get": {
    "operationId": "listAuditEvents",
    "summary": "List tenant audit events",
    "parameters": [
      { "name": "action", "in": "query", "required": false, "schema": { "type": "string" } },
      { "name": "actorId", "in": "query", "required": false, "schema": { "type": "integer" } },
      { "name": "subjectType", "in": "query", "required": false, "schema": { "type": "string", "enum": ["requisition"] } },
      { "name": "subjectId", "in": "query", "required": false, "schema": { "type": "integer" } },
      { "name": "occurredFrom", "in": "query", "required": false, "schema": { "type": "string", "format": "date-time" } },
      { "name": "occurredTo", "in": "query", "required": false, "schema": { "type": "string", "format": "date-time" } },
      { "name": "page", "in": "query", "required": false, "schema": { "type": "integer", "minimum": 1 } },
      { "name": "perPage", "in": "query", "required": false, "schema": { "type": "integer", "minimum": 1, "maximum": 100 } }
    ],
    "responses": {
      "200": {
        "description": "Tenant audit event feed",
        "content": {
          "application/json": {
            "schema": { "$ref": "#/components/schemas/AuditEventListResponse" }
          }
        }
      },
      "400": { "$ref": "#/components/responses/AmbiguousTenant" },
      "401": { "$ref": "#/components/responses/Unauthenticated" },
      "403": { "$ref": "#/components/responses/Forbidden" },
      "422": { "$ref": "#/components/responses/ValidationFailed" }
    }
  }
}
```

- [ ] **Step 2: Add audit schemas**

Add these component schemas:

```json
"AuditEventListResponse": {
  "type": "object",
  "required": ["data", "meta"],
  "properties": {
    "data": {
      "type": "array",
      "items": { "$ref": "#/components/schemas/AuditEvent" }
    },
    "meta": { "$ref": "#/components/schemas/PaginationMeta" }
  }
},
"AuditEvent": {
  "type": "object",
  "required": ["id", "action", "message", "actor", "subject", "metadata", "before", "after", "occurredAt", "requestId"],
  "properties": {
    "id": { "type": "string" },
    "action": { "type": "string" },
    "message": { "type": "string" },
    "actor": {
      "oneOf": [
        { "$ref": "#/components/schemas/UserSummary" },
        { "type": "null" }
      ]
    },
    "subject": { "$ref": "#/components/schemas/AuditSubject" },
    "metadata": { "type": "object", "additionalProperties": true },
    "before": {
      "oneOf": [
        { "type": "object", "additionalProperties": true },
        { "type": "null" }
      ]
    },
    "after": {
      "oneOf": [
        { "type": "object", "additionalProperties": true },
        { "type": "null" }
      ]
    },
    "occurredAt": { "type": "string", "format": "date-time" },
    "requestId": {
      "oneOf": [
        { "type": "string" },
        { "type": "null" }
      ]
    }
  }
},
"AuditSubject": {
  "type": "object",
  "required": ["type", "id", "display"],
  "properties": {
    "type": { "type": "string", "enum": ["requisition"] },
    "id": { "type": "string" },
    "display": {
      "oneOf": [
        { "type": "string" },
        { "type": "null" }
      ]
    }
  }
},
"PaginationMeta": {
  "type": "object",
  "required": ["currentPage", "perPage", "total", "lastPage"],
  "properties": {
    "currentPage": { "type": "integer" },
    "perPage": { "type": "integer" },
    "total": { "type": "integer" },
    "lastPage": { "type": "integer" }
  }
}
```

- [ ] **Step 3: Replace shared error response schemas with normalized envelope**

Ensure `components.schemas.ApiError` is:

```json
"ApiError": {
  "type": "object",
  "required": ["error"],
  "properties": {
    "error": {
      "type": "object",
      "required": ["code", "message", "details", "requestId"],
      "properties": {
        "code": {
          "type": "string",
          "enum": [
            "validation_failed",
            "unauthenticated",
            "forbidden",
            "not_found",
            "conflict",
            "ambiguous_tenant",
            "too_many_requests",
            "server_error"
          ]
        },
        "message": { "type": "string" },
        "details": { "type": "object", "additionalProperties": true },
        "requestId": {
          "oneOf": [
            { "type": "string" },
            { "type": "null" }
          ]
        }
      }
    }
  }
}
```

Ensure `ValidationError` extends the envelope with `details.fields`:

```json
"ValidationError": {
  "allOf": [
    { "$ref": "#/components/schemas/ApiError" },
    {
      "type": "object",
      "properties": {
        "error": {
          "type": "object",
          "properties": {
            "details": {
              "type": "object",
              "required": ["fields"],
              "properties": {
                "fields": {
                  "type": "object",
                  "additionalProperties": {
                    "type": "array",
                    "items": { "type": "string" }
                  }
                }
              }
            }
          }
        }
      }
    }
  ]
}
```

Use `Forbidden` as the public response name for 403. Keep the older `Unauthorized` response name only if existing OpenAPI references still use it, and point it at the same `forbidden` payload.

- [ ] **Step 4: Regenerate API client**

Run:

```bash
pnpm generate:api
```

Expected: new generated files appear for audit schemas and `listAuditEvents`; error schemas update to the normalized envelope.

- [ ] **Step 5: Run contract drift check**

Run:

```bash
pnpm check:api-contract
```

Expected: pass with no generated-client diff after regeneration.

## Task 8: Add API Client Error Helpers

**Files:**
- Create: `packages/api-client/src/errors.ts`
- Modify: `packages/api-client/src/index.ts`

- [ ] **Step 1: Add typed error helpers**

Create `packages/api-client/src/errors.ts`:

```ts
import type { ApiClientError } from "./client";
import type { ApiError, ValidationError } from "./generated/schemas";

type ErrorCode = NonNullable<ApiError["error"]>["code"];

export function isApiClientError(value: unknown): value is ApiClientError<ApiError> {
  return (
    typeof value === "object" &&
    value !== null &&
    "status" in value &&
    "data" in value &&
    typeof (value as { status?: unknown }).status === "number"
  );
}

export function getApiErrorCode(value: unknown): ErrorCode | null {
  if (!isApiClientError(value)) {
    return null;
  }

  return value.data?.error?.code ?? null;
}

export function getApiErrorMessage(value: unknown): string {
  if (!isApiClientError(value)) {
    return "Something went wrong.";
  }

  return value.data?.error?.message ?? "Something went wrong.";
}

export function getApiValidationErrors(value: unknown): Record<string, string[]> {
  if (!isApiClientError(value) || value.data?.error?.code !== "validation_failed") {
    return {};
  }

  const validation = value.data as ValidationError;
  const fields = validation.error?.details?.fields;

  return fields && typeof fields === "object" ? fields : {};
}
```

- [ ] **Step 2: Export helpers**

Modify `packages/api-client/src/index.ts`:

```ts
export * from "./client";
export * from "./errors";
export * from "./generated/endpoints";
export * from "./generated/schemas";
```

- [ ] **Step 3: Typecheck api-client**

Run:

```bash
pnpm --filter @cognify/api-client typecheck
```

Expected: pass.

## Task 9: Add Frontend Audit API Hook And MSW Handler

**Files:**
- Create: `apps/web/features/audit/api/audit-api.ts`
- Create: `apps/web/features/audit/hooks/use-audit-events.ts`
- Create: `apps/web/features/audit/mocks/audit-fixtures.ts`
- Create: `apps/web/features/audit/mocks/audit-handlers.ts`
- Create: `apps/web/features/audit/types/audit-view-model.ts`
- Create: `apps/web/features/audit/tests/audit-api.test.ts`
- Modify: `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Add audit API wrapper**

Create `apps/web/features/audit/api/audit-api.ts`:

```ts
import {
  listAuditEvents,
  type AuditEventListResponse,
  type ListAuditEventsParams,
} from "@cognify/api-client";

import { getApiClientConfig } from "@/features/identity/api/identity-api";

export function fetchAuditEvents(params: ListAuditEventsParams = {}): Promise<AuditEventListResponse> {
  return listAuditEvents(params, getApiClientConfig()).then((response) => response.data);
}
```

If the generated Orval function signature differs, adapt only the argument order while preserving generated types and `getApiClientConfig()`.

- [ ] **Step 2: Add query hook**

Create `apps/web/features/audit/hooks/use-audit-events.ts`:

```ts
"use client";

import { useQuery } from "@tanstack/react-query";
import type { ListAuditEventsParams } from "@cognify/api-client";

import { fetchAuditEvents } from "../api/audit-api";

export function useAuditEvents(params: ListAuditEventsParams = {}) {
  return useQuery({
    queryKey: ["audit-events", params],
    queryFn: () => fetchAuditEvents(params),
  });
}
```

- [ ] **Step 3: Add feature-local view model aliases**

Create `apps/web/features/audit/types/audit-view-model.ts`:

```ts
import type { AuditEvent, AuditEventListResponse } from "@cognify/api-client";

export type AuditEventViewModel = AuditEvent;
export type AuditEventListViewModel = AuditEventListResponse;
```

- [ ] **Step 4: Add MSW fixture**

Create `apps/web/features/audit/mocks/audit-fixtures.ts`:

```ts
import type { AuditEventListResponse } from "@cognify/api-client";

export const auditEventsFixture: AuditEventListResponse = {
  data: [
    {
      id: "9bfb4c6f-17a7-48c1-a4cc-0fba43b3f8f3",
      action: "requisition.submitted",
      message: "Submitted for review",
      actor: {
        id: "1",
        name: "Aisha Tan",
        email: "aisha@example.com",
      },
      subject: {
        type: "requisition",
        id: "42",
        display: "REQ-2026-000042",
      },
      metadata: {
        status: "submitted",
      },
      before: {
        status: "draft",
      },
      after: {
        status: "submitted",
      },
      occurredAt: "2026-05-12T08:20:00.000000Z",
      requestId: "req_test_123",
    },
  ],
  meta: {
    currentPage: 1,
    perPage: 25,
    total: 1,
    lastPage: 1,
  },
};
```

- [ ] **Step 5: Add MSW handler**

Create `apps/web/features/audit/mocks/audit-handlers.ts`:

```ts
import { http, HttpResponse } from "msw";

import { auditEventsFixture } from "./audit-fixtures";

export const auditHandlers = [
  http.get("*/api/audit/events", ({ request }) => {
    const url = new URL(request.url);
    const action = url.searchParams.get("action");

    if (action && action !== "requisition.submitted") {
      return HttpResponse.json({
        data: [],
        meta: {
          currentPage: 1,
          perPage: 25,
          total: 0,
          lastPage: 1,
        },
      });
    }

    return HttpResponse.json(auditEventsFixture);
  }),
];
```

- [ ] **Step 6: Register handler**

Modify `apps/web/tests/msw/handlers.ts`:

```ts
import { auditHandlers } from "@/features/audit/mocks/audit-handlers";
import { identityHandlers } from "@/features/identity/mocks/identity-handlers";
import { requisitionHandlers } from "@/features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  ...identityHandlers,
  ...requisitionHandlers,
  ...auditHandlers,
];
```

Preserve any existing handlers in this file and add `auditHandlers` to the exported array.

- [ ] **Step 7: Add frontend tests**

Create `apps/web/features/audit/tests/audit-api.test.ts`:

```ts
import { describe, expect, it } from "vitest";

import {
  getApiErrorCode,
  getApiErrorMessage,
  getApiValidationErrors,
  type ApiClientError,
} from "@cognify/api-client";

import { fetchAuditEvents } from "../api/audit-api";

describe("audit api", () => {
  it("fetches OpenAPI-shaped audit events", async () => {
    const response = await fetchAuditEvents({ action: "requisition.submitted" });

    expect(response.data).toHaveLength(1);
    expect(response.data[0]?.action).toBe("requisition.submitted");
    expect(response.data[0]?.subject.type).toBe("requisition");
  });

  it("parses normalized validation errors", () => {
    const error: ApiClientError = {
      status: 422,
      headers: new Headers(),
      data: {
        error: {
          code: "validation_failed",
          message: "The given data was invalid.",
          details: {
            fields: {
              title: ["The title field is required."],
            },
          },
          requestId: "req_test_123",
        },
      },
    };

    expect(getApiErrorCode(error)).toBe("validation_failed");
    expect(getApiErrorMessage(error)).toBe("The given data was invalid.");
    expect(getApiValidationErrors(error)).toEqual({
      title: ["The title field is required."],
    });
  });
});
```

- [ ] **Step 8: Run web audit tests**

Run:

```bash
pnpm --filter @cognify/web test -- audit-api
```

Expected: pass.

## Task 10: Contract And Regression Verification

**Files:**
- Verify: all files touched above

- [ ] **Step 1: Run backend audit tests**

Run:

```bash
cd apps/api && php artisan test --filter=AuditApiTest
```

Expected: pass.

- [ ] **Step 2: Run backend API error tests**

Run:

```bash
cd apps/api && php artisan test --filter=ApiErrorContractTest
```

Expected: pass.

- [ ] **Step 3: Run requisition regression tests**

Run:

```bash
cd apps/api && php artisan test --filter=RequisitionApiTest
```

Expected: pass, including tenant isolation and existing audit assertions.

- [ ] **Step 4: Regenerate and check API contract**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
```

Expected: generated client is current and contract check passes.

- [ ] **Step 5: Typecheck generated client**

Run:

```bash
pnpm --filter @cognify/api-client typecheck
```

Expected: pass.

- [ ] **Step 6: Run frontend audit tests**

Run:

```bash
pnpm --filter @cognify/web test -- audit-api
```

Expected: pass.

- [ ] **Step 7: Run relevant broader checks**

Run:

```bash
pnpm lint
pnpm typecheck
pnpm test
```

Expected: pass. If a command fails because of an unrelated existing issue, capture the failing command, failing file, and why it is unrelated before handing off.

## Task 11: Documentation And PR Notes For Future Implementation

**Files:**
- Modify if implementation changes behavior beyond this plan: `docs/05-runbooks/feature-development.md`
- Modify if API error contract needs a durable reference: `docs/04-engineering/standards/testing-strategy.md` or a new `docs/04-engineering/standards/api-contracts.md`

- [ ] **Step 1: Decide whether docs need updates**

If the implementation follows this plan exactly, no additional docs are required because this plan and the design spec capture the foundation. If implementation discovers a durable rule such as a new API contract command or a new audit security rule, update the relevant engineering standard.

- [ ] **Step 2: Prepare implementation PR summary**

Use this PR summary:

```markdown
## Summary

- hardened audit events with append-only metadata, before/after context, request IDs, and stable subject shaping
- added tenant-scoped audit feed endpoint and reused the audit resource for requisition activity
- normalized Laravel API errors and updated OpenAPI/Orval-generated client types
- added frontend audit API hook, MSW handler, and API error helpers

## Verification

- `cd apps/api && php artisan test --filter=AuditApiTest`
- `cd apps/api && php artisan test --filter=ApiErrorContractTest`
- `cd apps/api && php artisan test --filter=RequisitionApiTest`
- `pnpm generate:api`
- `pnpm check:api-contract`
- `pnpm --filter @cognify/api-client typecheck`
- `pnpm --filter @cognify/web test -- audit-api`
- `pnpm lint`
- `pnpm typecheck`
- `pnpm test`
```

- [ ] **Step 3: Commit implementation when approved**

When the user approves implementation work, use focused commits:

```bash
git add apps/api/app/Audit apps/api/database/migrations apps/api/tests/Feature/AuditApiTest.php
git commit -m "feat: harden audit event foundation"

git add apps/api/app/Exceptions apps/api/app/Http/Middleware/AssignRequestId.php apps/api/bootstrap/app.php apps/api/tests/Feature/ApiErrorContractTest.php
git commit -m "feat: standardize API error contract"

git add apps/api/storage/openapi/openapi.json packages/api-client apps/web/features/audit apps/web/tests/msw/handlers.ts
git commit -m "feat: add generated audit API client workflow"
```

Do not commit the design spec or this plan until the user has reviewed them.

## Self-Review Checklist

- The plan implements every scope item in `docs/superpowers/specs/2026-05-12-audit-api-contract-foundation-design.md`.
- Audit infrastructure stays in `apps/api/app/Audit`, not in a premature business domain.
- Domain actions still own workflow behavior and call the recorder only for side effects.
- Tenant context is validated by middleware and never accepted as an audit query parameter.
- Error responses use one envelope across validation, auth, authorization, not found, conflict, ambiguous tenant, throttling, and server failures.
- OpenAPI changes are paired with Orval regeneration and typechecking.
- Frontend code uses generated types and feature-local hooks; production code does not import MSW fixtures.
- No `packages/ui` changes are planned.

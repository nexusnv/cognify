# File Attachment Baseline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build Cognify's P0 tenant-scoped requisition evidence attachment workflow with private storage, upload/list/preview/download/delete, audit events, OpenAPI, generated client usage, and MSW-backed web coverage.

**Architecture:** Implement the durable attachment domain in `apps/api/Domains/Attachment`, expose requisition-owned routes through the existing tenant/auth middleware, and keep app-specific evidence UI in `apps/web/features/attachments` plus requisition composition. Use backend-mediated `multipart/form-data` upload on a private Laravel disk for P0, then consume the exported OpenAPI contract through `@cognify/api-client`.

**Tech Stack:** Laravel 12, Sanctum, Eloquent, private filesystem disk, PHPUnit, OpenAPI JSON, Orval, Next.js App Router, React 19, TanStack Query, MSW, Vitest, Testing Library, lucide-react.

---

## Runbook Alignment

Follow `docs/05-runbooks/feature-development.md` in this order:

1. Workflow map: requisition evidence upload and correction.
2. API contract: attachment resources, upload, preview, download, delete, errors.
3. Mocked frontend workflow: MSW handlers and requisition workspace UI.
4. Backend domain behavior: storage, metadata, policies, actions, audit.
5. Real API integration: regenerate `@cognify/api-client`, swap hooks to generated endpoints.
6. Hardening: tenant isolation, authorization, validation, audit, accessibility, and narrow checks.

## Source Documents

- Spec: `docs/superpowers/specs/2026-05-14-file-attachment-baseline-design.md`
- P0 epic list: `docs/02-release-management/2026-05-12-P0-Epics.md`
- Roadmap: `docs/01-product/feature-roadmap.md`
- Feature runbook: `docs/05-runbooks/feature-development.md`
- Architecture baseline: `docs/superpowers/specs/2026-05-09-cognify-greenfield-saas-runbook-design.md`

## File Structure

Create:

- `apps/api/Domains/Attachment/Actions/DeleteAttachment.php`: soft-delete and audit authorized attachment removals.
- `apps/api/Domains/Attachment/Actions/StoreRequisitionAttachment.php`: validate parent access, store file bytes, create metadata, audit upload.
- `apps/api/Domains/Attachment/Http/Controllers/AttachmentFileController.php`: preview/download streams for existing attachments.
- `apps/api/Domains/Attachment/Http/Controllers/RequisitionAttachmentController.php`: list/upload attachments under a requisition.
- `apps/api/Domains/Attachment/Http/Requests/StoreAttachmentRequest.php`: MIME, extension, size, and empty-file validation.
- `apps/api/Domains/Attachment/Http/Resources/AttachmentResource.php`: stable API resource shape.
- `apps/api/Domains/Attachment/Models/Attachment.php`: tenant-scoped attachment metadata model.
- `apps/api/Domains/Attachment/Policies/AttachmentPolicy.php`: view, preview, download, delete rules through parent record access.
- `apps/api/Domains/Attachment/Support/AttachmentStorage.php`: private disk pathing, safe filename, checksum, previewable logic.
- `apps/api/database/migrations/2026_05_14_000000_create_attachments_table.php`: metadata table and indexes.
- `apps/api/tests/Feature/AttachmentApiTest.php`: upload/list/preview/download/delete/isolation/validation/audit tests.
- `apps/web/features/attachments/api/attachments-api.ts`: generated-client wrappers and active-tenant header behavior.
- `apps/web/features/attachments/components/attachment-list.tsx`: list rows and file actions.
- `apps/web/features/attachments/components/attachment-preview-panel.tsx`: right-panel preview host for PDF/images.
- `apps/web/features/attachments/components/attachment-uploader.tsx`: accessible upload control and validation display.
- `apps/web/features/attachments/hooks/use-attachments.ts`: query/mutation hooks and invalidation.
- `apps/web/features/attachments/mocks/attachments-fixtures.ts`: OpenAPI-shaped fixture data.
- `apps/web/features/attachments/mocks/attachments-handlers.ts`: MSW handlers for list/upload/preview/download/delete.
- `apps/web/features/attachments/tests/attachments-workflow.test.tsx`: UI workflow tests.
- `apps/web/features/attachments/types/attachment-view-model.ts`: view model aliases only where generated types need UI adaptation.

Modify:

- `apps/api/Domains/Requisition/Models/Requisition.php`: add `attachments()` morph relation.
- `apps/api/app/Audit/AuditSubject.php`: map `Attachment` to stable subject type `attachment`.
- `apps/api/app/Providers/AppServiceProvider.php`: register `AttachmentPolicy`.
- `apps/api/config/filesystems.php`: add or document private `attachments` disk using local storage for P0.
- `apps/api/routes/api.php`: add protected attachment routes inside `ResolveCurrentTenant`.
- `apps/api/storage/openapi/openapi.json`: add attachment schemas, endpoints, upload request body, binary responses, and error responses.
- `packages/api-client/src/generated/*`: regenerate with Orval.
- `packages/api-client/src/index.ts`: export generated attachment schemas automatically through existing exports.
- `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`: add Evidence section and sidebar affordance.
- `apps/web/tests/msw/handlers.ts`: register attachment handlers.

Do not modify `packages/ui`. Attachment workflow UI is Cognify-specific.

## Workflow Map

```txt
Actors:
  requester, buyer, approver, admin

Upload:
  user opens authorized requisition workspace
  -> Evidence section lists current attachments
  -> user selects file
  -> frontend posts multipart form data
  -> backend validates tenant, parent requisition access, file type, size, and content
  -> backend stores bytes on private disk
  -> backend creates attachment metadata
  -> backend records attachment.uploaded audit event
  -> frontend invalidates attachment list and shows the new file

Preview/download:
  user clicks preview or download
  -> backend resolves attachment by tenant
  -> policy checks parent requisition access
  -> preview streams inline only for PDF, PNG, JPEG, WebP
  -> download streams attachment disposition for all allowed files
  -> backend records attachment.previewed or attachment.downloaded audit event

Delete:
  user clicks delete
  -> backend checks policy
  -> metadata is soft-deleted
  -> file bytes remain private for P0 retention
  -> backend records attachment.deleted audit event
```

Failure paths:

- Missing authentication: `401 unauthenticated`.
- Missing or ambiguous tenant: existing tenant error contract.
- Unauthorized parent or attachment: `403 forbidden` or parent-scoped `404` where the record must not be disclosed.
- Invalid file type, size, or empty file: `422 validation_failed`.
- Non-previewable file preview: `422 validation_failed` with a field or detail message saying preview is unsupported.
- Missing file bytes for existing metadata: `404 not_found` and an error log entry.

## Task 1: Backend Regression Tests First

**Files:**

- Create: `apps/api/tests/Feature/AttachmentApiTest.php`
- Read: `apps/api/tests/Feature/RequisitionApiTest.php`
- Read: `apps/api/Domains/Requisition/Policies/RequisitionPolicy.php`

- [ ] **Step 1: Confirm baseline**

Run:

```bash
git status --short --branch
```

Expected: current branch only. If unrelated modified files exist, do not edit or revert them.

- [ ] **Step 2: Add failing attachment API tests**

Create `apps/api/tests/Feature/AttachmentApiTest.php` with these concrete test cases:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_upload_and_list_requisition_attachment(): void
    {
        Storage::fake('attachments');
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $response = $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/attachments", [
                'file' => UploadedFile::fake()->create('quote.pdf', 64, 'application/pdf'),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.parentType', 'requisition')
            ->assertJsonPath('data.parentId', (string) $requisition->id)
            ->assertJsonPath('data.filename', 'quote.pdf')
            ->assertJsonPath('data.mimeType', 'application/pdf')
            ->assertJsonPath('data.previewable', true);

        $attachmentId = $response->json('data.id');
        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'tenant_id' => $tenant->id,
            'attachable_type' => Requisition::class,
            'attachable_id' => $requisition->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'quote.pdf',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'attachment.uploaded',
        ]);

        $this->actingAsTenant($tenant, $user)
            ->getJson("/api/requisitions/{$requisition->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $attachmentId);
    }

    public function test_attachment_bytes_and_metadata_are_tenant_scoped(): void
    {
        Storage::fake('attachments');
        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant, $otherUser] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);
        $attachmentId = $this->uploadAttachment($tenant, $user, $requisition);

        $this->actingAsTenant($otherTenant, $otherUser)
            ->getJson("/api/attachments/{$attachmentId}/download")
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherUser)
            ->deleteJson("/api/attachments/{$attachmentId}")
            ->assertNotFound();
    }

    public function test_upload_rejects_unsupported_file_type_and_empty_files(): void
    {
        Storage::fake('attachments');
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/attachments", [
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/attachments", [
                'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_preview_download_and_delete_are_authorized_and_audited(): void
    {
        Storage::fake('attachments');
        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);
        $attachmentId = $this->uploadAttachment($tenant, $user, $requisition);

        $this->actingAsTenant($tenant, $user)
            ->get("/api/attachments/{$attachmentId}/preview")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAsTenant($tenant, $user)
            ->get("/api/attachments/{$attachmentId}/download")
            ->assertOk()
            ->assertDownload('quote.pdf');

        $this->actingAsTenant($tenant, $user)
            ->deleteJson("/api/attachments/{$attachmentId}")
            ->assertNoContent();

        $this->assertSoftDeleted('attachments', ['id' => $attachmentId]);
        foreach (['attachment.previewed', 'attachment.downloaded', 'attachment.deleted'] as $eventType) {
            $this->assertDatabaseHas('audit_events', [
                'tenant_id' => $tenant->id,
                'actor_id' => $user->id,
                'event_type' => $eventType,
            ]);
        }
    }

    private function uploadAttachment(Tenant $tenant, User $user, Requisition $requisition): int
    {
        return (int) $this->actingAsTenant($tenant, $user)
            ->postJson("/api/requisitions/{$requisition->id}/attachments", [
                'file' => UploadedFile::fake()->create('quote.pdf', 64, 'application/pdf'),
            ])
            ->json('data.id');
    }

    private function createDraft(Tenant $tenant, User $user, array $overrides = []): Requisition
    {
        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => 'REQ-2026-ATTACH',
            'title' => 'Attachment test requisition',
            'business_justification' => 'Evidence is required for this request.',
            'needed_by_date' => '2026-07-15',
            'currency' => 'MYR',
            'status' => RequisitionStatus::Draft,
        ], $overrides));
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

- [ ] **Step 3: Run tests and verify failure**

Run:

```bash
cd apps/api
php artisan test --filter=AttachmentApiTest
```

Expected: FAIL because routes, model, and domain classes do not exist.

- [ ] **Step 4: Commit test scaffold**

```bash
git add apps/api/tests/Feature/AttachmentApiTest.php
git commit -m "test(api): add attachment workflow regressions"
```

## Task 2: Backend Attachment Domain And Routes

**Files:**

- Create all backend attachment files listed in File Structure.
- Modify `apps/api/Domains/Requisition/Models/Requisition.php`
- Modify `apps/api/app/Audit/AuditSubject.php`
- Modify `apps/api/app/Providers/AppServiceProvider.php`
- Modify `apps/api/config/filesystems.php`
- Modify `apps/api/routes/api.php`

- [ ] **Step 1: Create metadata migration**

Create `apps/api/database/migrations/2026_05_14_000000_create_attachments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->morphs('attachable');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('extension', 20)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('storage_disk');
            $table->string('storage_path');
            $table->string('checksum_sha256', 64);
            $table->boolean('previewable')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'attachable_type', 'attachable_id']);
            $table->unique(['storage_disk', 'storage_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
```

- [ ] **Step 2: Create model and relations**

Create `apps/api/Domains/Attachment/Models/Attachment.php`:

```php
<?php

namespace Domains\Attachment\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'attachable_type',
        'attachable_id',
        'uploaded_by',
        'original_filename',
        'mime_type',
        'extension',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'checksum_sha256',
        'previewable',
    ];

    protected function casts(): array
    {
        return [
            'previewable' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

Modify `apps/api/Domains/Requisition/Models/Requisition.php`:

```php
use Domains\Attachment\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable');
}
```

- [ ] **Step 3: Create storage support**

Create `apps/api/Domains/Attachment/Support/AttachmentStorage.php`:

```php
<?php

namespace Domains\Attachment\Support;

use Domains\Attachment\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentStorage
{
    public const DISK = 'attachments';

    public const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    public function store(int $tenantId, UploadedFile $file, int $attachmentId): array
    {
        $safeFilename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = trim($safeFilename, '-') ?: 'attachment';
        $path = "tenants/{$tenantId}/attachments/{$attachmentId}/{$filename}.{$extension}";

        Storage::disk(self::DISK)->put($path, $file->getContent());

        return [
            'disk' => self::DISK,
            'path' => $path,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'previewable' => in_array($file->getMimeType(), self::PREVIEWABLE_MIME_TYPES, true),
        ];
    }

    public function stream(Attachment $attachment)
    {
        return Storage::disk($attachment->storage_disk)->readStream($attachment->storage_path);
    }

    public function exists(Attachment $attachment): bool
    {
        return Storage::disk($attachment->storage_disk)->exists($attachment->storage_path);
    }
}
```

- [ ] **Step 4: Add validation request**

Create `apps/api/Domains/Attachment/Http/Requests/StoreAttachmentRequest.php`:

```php
<?php

namespace Domains\Attachment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'min:1',
                'max:25600',
                'mimetypes:application/pdf,image/png,image/jpeg,image/webp,text/plain,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
        ];
    }
}
```

- [ ] **Step 5: Add resource**

Create `apps/api/Domains/Attachment/Http/Resources/AttachmentResource.php`:

```php
<?php

namespace Domains\Attachment\Http\Resources;

use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Attachment $attachment */
        $attachment = $this->resource;

        return [
            'id' => (string) $attachment->id,
            'parentType' => $attachment->attachable_type === Requisition::class ? 'requisition' : 'unknown',
            'parentId' => (string) $attachment->attachable_id,
            'filename' => $attachment->original_filename,
            'mimeType' => $attachment->mime_type,
            'extension' => $attachment->extension,
            'sizeBytes' => $attachment->size_bytes,
            'previewable' => $attachment->previewable,
            'uploadedBy' => $attachment->uploader ? [
                'id' => (string) $attachment->uploader->id,
                'name' => $attachment->uploader->name,
            ] : null,
            'createdAt' => $attachment->created_at?->toJSON(),
            'permissions' => [
                'canPreview' => $attachment->previewable && $request->user()?->can('view', $attachment),
                'canDownload' => $request->user()?->can('view', $attachment) ?? false,
                'canDelete' => $request->user()?->can('delete', $attachment) ?? false,
            ],
        ];
    }
}
```

- [ ] **Step 6: Add policy and actions**

Create `AttachmentPolicy`, `StoreRequisitionAttachment`, and `DeleteAttachment` so all access flows through tenant and parent requisition policy:

```php
public function view(User $user, Attachment $attachment): bool
{
    if ((int) $attachment->tenant_id !== app(CurrentTenant::class)->get()->id) {
        return false;
    }

    $parent = $attachment->attachable;

    return $parent instanceof Requisition && $user->can('view', $parent);
}

public function delete(User $user, Attachment $attachment): bool
{
    return $this->view($user, $attachment) && $user->can('update', $attachment->attachable);
}
```

`StoreRequisitionAttachment::handle()` must:

1. Authorize `view` on the requisition.
2. Create an attachment metadata row with temporary storage fields.
3. Store the file through `AttachmentStorage`.
4. Update disk/path/checksum/previewable.
5. Record `attachment.uploaded` through `AuditRecorder` using `AuditEventData`.

`DeleteAttachment::handle()` must soft-delete the attachment and record `attachment.deleted`.

- [ ] **Step 7: Add controllers and routes**

Add routes inside the existing protected tenant middleware group in `apps/api/routes/api.php`:

```php
Route::get('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'index']);
Route::post('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'store']);
Route::get('/attachments/{attachment}/preview', [AttachmentFileController::class, 'preview']);
Route::get('/attachments/{attachment}/download', [AttachmentFileController::class, 'download']);
Route::delete('/attachments/{attachment}', [AttachmentFileController::class, 'destroy']);
```

Controllers stay thin. Use actions for writes and `AttachmentResource` for JSON.

- [ ] **Step 8: Register policy and disk**

Modify `AppServiceProvider`:

```php
Gate::policy(Attachment::class, AttachmentPolicy::class);
```

Modify `config/filesystems.php`:

```php
'attachments' => [
    'driver' => 'local',
    'root' => storage_path('app/private/attachments'),
    'throw' => true,
],
```

- [ ] **Step 9: Run backend tests**

Run:

```bash
cd apps/api
php artisan test --filter=AttachmentApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api
```

Expected: PASS, and attachment routes appear under the API route list.

- [ ] **Step 10: Commit backend domain**

```bash
git add apps/api/Domains/Attachment apps/api/Domains/Requisition/Models/Requisition.php apps/api/app/Audit/AuditSubject.php apps/api/app/Providers/AppServiceProvider.php apps/api/config/filesystems.php apps/api/database/migrations/2026_05_14_000000_create_attachments_table.php apps/api/routes/api.php apps/api/tests/Feature/AttachmentApiTest.php
git commit -m "feat(api): add tenant scoped attachments"
```

## Task 3: OpenAPI Contract And Generated Client

**Files:**

- Modify: `apps/api/storage/openapi/openapi.json`
- Modify: `packages/api-client/src/generated/*`

- [ ] **Step 1: Add attachment schemas to OpenAPI**

Add schemas:

- `Attachment`
- `AttachmentPermissions`
- `AttachmentUser`
- `AttachmentListResponse`
- `AttachmentResponse`
- `AttachmentUploadRequest`
- Binary preview/download responses with `application/octet-stream`, `application/pdf`, and image content types.

Endpoint operation IDs:

- `listRequisitionAttachments`
- `uploadRequisitionAttachment`
- `previewAttachment`
- `downloadAttachment`
- `deleteAttachment`

- [ ] **Step 2: Validate and regenerate client**

Run:

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: generated endpoints and schemas include attachment operations and typecheck passes.

- [ ] **Step 3: Commit contract**

```bash
git add apps/api/storage/openapi/openapi.json packages/api-client/src/generated
git commit -m "feat(api-client): add attachment contract"
```

## Task 4: MSW-Backed Attachment Feature Hooks And UI

**Files:**

- Create all `apps/web/features/attachments/*` files listed in File Structure.
- Modify `apps/web/tests/msw/handlers.ts`

- [ ] **Step 1: Add attachment API wrappers**

Create `apps/web/features/attachments/api/attachments-api.ts`:

```ts
import {
  deleteAttachment as deleteAttachmentEndpoint,
  listRequisitionAttachments,
  uploadRequisitionAttachment,
} from "@cognify/api-client/endpoints";
import type { Attachment } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "../../identity/api/identity-api";

export async function listAttachments(requisitionId: string) {
  const response = await listRequisitionAttachments(requisitionId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return response.data.data as Attachment[];
}

export async function uploadAttachment(requisitionId: string, file: File) {
  const formData = new FormData();
  formData.append("file", file);
  const response = await uploadRequisitionAttachment(requisitionId, formData, withActiveTenantHeader());
  if (response.status !== 201) throw response.data;
  return response.data.data as Attachment;
}

export async function deleteAttachment(attachmentId: string) {
  const response = await deleteAttachmentEndpoint(attachmentId, withActiveTenantHeader());
  if (response.status !== 204) throw response.data;
}

export function attachmentPreviewUrl(attachmentId: string) {
  return `/api/attachments/${attachmentId}/preview`;
}

export function attachmentDownloadUrl(attachmentId: string) {
  return `/api/attachments/${attachmentId}/download`;
}

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  return tenantId ? { headers: { "X-Tenant-Id": tenantId } } : undefined;
}
```

- [ ] **Step 2: Add hooks**

Create `apps/web/features/attachments/hooks/use-attachments.ts`:

```ts
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { deleteAttachment, listAttachments, uploadAttachment } from "../api/attachments-api";

export function useAttachments(requisitionId: string) {
  return useQuery({
    queryKey: ["attachments", "requisition", requisitionId],
    queryFn: () => listAttachments(requisitionId),
  });
}

export function useAttachmentUpload(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (file: File) => uploadAttachment(requisitionId, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["attachments", "requisition", requisitionId] }),
  });
}

export function useAttachmentDelete(requisitionId: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (attachmentId: string) => deleteAttachment(attachmentId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["attachments", "requisition", requisitionId] }),
  });
}
```

- [ ] **Step 3: Add MSW fixtures and handlers**

Create fixture records matching the OpenAPI shape. Handlers must cover:

- `GET /api/requisitions/:requisitionId/attachments`
- `POST /api/requisitions/:requisitionId/attachments`
- `GET /api/attachments/:attachmentId/preview`
- `GET /api/attachments/:attachmentId/download`
- `DELETE /api/attachments/:attachmentId`

Register in `apps/web/tests/msw/handlers.ts`:

```ts
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
  ...identityHandlers,
  ...auditHandlers,
  ...attachmentHandlers,
];
```

- [ ] **Step 4: Add failing UI workflow tests**

Create `apps/web/features/attachments/tests/attachments-workflow.test.tsx` with tests for:

- Empty evidence state.
- Populated attachment row metadata.
- Upload success invalidates and renders the new file.
- Upload validation error renders an alert.
- Preview opens the right panel for previewable files.
- Delete removes the file.

Use `QueryClientProvider`, `RightPanelProvider`, and `RightPanelRoot`, matching existing requisition workflow tests.

- [ ] **Step 5: Build UI components**

Create:

- `AttachmentUploader`: label `Upload evidence`, file input, selected-file submit button, mutation error alert.
- `AttachmentList`: loading/empty/error/populated states, preview/download/delete actions.
- `AttachmentPreviewPanel`: iframe for PDF, img for image MIME types, fallback text for unsupported preview.

The list action labels must be accessible:

```tsx
aria-label={`Preview ${attachment.filename}`}
aria-label={`Download ${attachment.filename}`}
aria-label={`Delete ${attachment.filename}`}
```

- [ ] **Step 6: Run frontend tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/attachments/tests/attachments-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 7: Commit frontend feature scaffolding**

```bash
git add apps/web/features/attachments apps/web/tests/msw/handlers.ts
git commit -m "feat(web): add attachment workflow UI"
```

## Task 5: Requisition Workspace Integration

**Files:**

- Modify: `apps/web/features/requisitions/workflows/requisition-detail-page.tsx`
- Modify: `apps/web/features/requisitions/tests/requisitions-workflow.test.tsx`

- [ ] **Step 1: Add requisition detail regression test**

Extend `requisitions-workflow.test.tsx`:

```tsx
it("renders the requisition evidence attachment workflow", async () => {
  renderWithQuery(<RequisitionDetailPage requisitionId="req-1" />);

  expect(await screen.findByRole("heading", { name: "Evidence" })).toBeInTheDocument();
  expect(screen.getByLabelText("Upload evidence")).toBeInTheDocument();
  expect(await screen.findByText("supplier-quote.pdf")).toBeInTheDocument();
});
```

- [ ] **Step 2: Add Evidence section to the workspace**

Modify `RequisitionDetailPage`:

- Add `{ id: "evidence", label: "Evidence" }` to `sections`.
- Render an `Evidence` section between line items and activity.
- Use `AttachmentUploader` and `AttachmentList` with `requisition.id`.

Example section shape:

```tsx
<section id="evidence" className="rounded-md border p-4">
  <h2 className="text-base font-semibold">Evidence</h2>
  <div className="mt-3 space-y-3">
    <AttachmentUploader requisitionId={requisition.id} />
    <AttachmentList requisitionId={requisition.id} />
  </div>
</section>
```

- [ ] **Step 3: Run requisition and attachment tests**

Run:

```bash
pnpm --filter @cognify/web test -- features/requisitions/tests/requisitions-workflow.test.tsx features/attachments/tests/attachments-workflow.test.tsx
```

Expected: PASS.

- [ ] **Step 4: Commit integration**

```bash
git add apps/web/features/requisitions/workflows/requisition-detail-page.tsx apps/web/features/requisitions/tests/requisitions-workflow.test.tsx
git commit -m "feat(web): attach evidence to requisition workspace"
```

## Task 6: Hardening And Final Verification

**Files:**

- Review all touched files.
- No new files unless validation exposes a defect.

- [ ] **Step 1: Run backend checks**

```bash
cd apps/api
php artisan test --filter=AttachmentApiTest
php artisan test --filter=RequisitionApiTest
php artisan route:list --path=api
```

Expected: PASS.

- [ ] **Step 2: Run contract checks**

```bash
pnpm generate:api
pnpm check:api-contract
pnpm --filter @cognify/api-client typecheck
```

Expected: PASS and no unexpected generated diff after the second run.

- [ ] **Step 3: Run web checks**

```bash
pnpm --filter @cognify/web test
pnpm --filter @cognify/web typecheck
pnpm --filter @cognify/web lint
```

Expected: PASS.

- [ ] **Step 4: Review architecture boundaries**

Confirm:

- No Cognify attachment workflow code in `packages/ui`.
- No mock fixtures imported by production components.
- No client-provided storage path, disk, tenant ID, or uploader trusted by backend.
- Attachment routes live behind `auth:sanctum` and `ResolveCurrentTenant`.
- Attachment file bytes are never publicly linked.

- [ ] **Step 5: Final commit if hardening changed files**

If checks required fixes:

```bash
git add apps/api/Domains/Attachment apps/api/Domains/Requisition/Models/Requisition.php apps/api/app/Audit/AuditSubject.php apps/api/app/Providers/AppServiceProvider.php apps/api/config/filesystems.php apps/api/database/migrations/2026_05_14_000000_create_attachments_table.php apps/api/routes/api.php apps/api/storage/openapi/openapi.json apps/api/tests/Feature/AttachmentApiTest.php apps/web/features/attachments apps/web/features/requisitions/workflows/requisition-detail-page.tsx apps/web/features/requisitions/tests/requisitions-workflow.test.tsx apps/web/tests/msw/handlers.ts packages/api-client/src/generated
git commit -m "fix: harden attachment workflow"
```

If no fixes were needed, do not create an empty commit.

## Plan Self-Review

- Spec coverage: upload, list, preview, download, delete, metadata, validation, tenant isolation, audit, MSW, OpenAPI, generated client, and requisition workspace integration are each assigned to tasks.
- Scope check: OCR, classification, annotation, public links, direct object storage, versioning, semantic search, and standalone vault are not included.
- Architecture check: backend domain code lives under `apps/api/Domains/Attachment`; app workflow code lives under `apps/web/features/attachments`; no `packages/ui` changes are planned.
- Verification check: API, contract, generated client, web tests, typecheck, and lint are explicitly included.

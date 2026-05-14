<?php

namespace Tests\Feature;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_upload_list_preview_download_and_delete_a_requisition_attachment(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $uploadResponse = $this->actingAsTenant($tenant, $user)
            ->post('/api/requisitions/'.$requisition->id.'/attachments', [
                'file' => UploadedFile::fake()->create('quote.pdf', 64, 'application/pdf'),
            ]);

        $uploadResponse->assertCreated()
            ->assertJsonPath('data.parentType', 'requisition')
            ->assertJsonPath('data.parentId', (string) $requisition->id)
            ->assertJsonPath('data.filename', 'quote.pdf')
            ->assertJsonPath('data.mimeType', 'application/pdf')
            ->assertJsonPath('data.previewable', true)
            ->assertJsonPath('data.permissions.canPreview', true)
            ->assertJsonPath('data.permissions.canDownload', true)
            ->assertJsonPath('data.permissions.canDelete', true);

        $attachmentId = (string) $uploadResponse->json('data.id');
        $attachment = Attachment::query()->findOrFail($attachmentId);

        Storage::disk('attachments')->assertExists($attachment->storage_path);

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'tenant_id' => $tenant->id,
            'attachable_type' => Requisition::class,
            'attachable_id' => $requisition->id,
            'uploaded_by' => $user->id,
            'original_filename' => 'quote.pdf',
            'mime_type' => 'application/pdf',
            'previewable' => 1,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'attachment.uploaded',
        ]);

        $this->actingAsTenant($tenant, $user)
            ->getJson('/api/requisitions/'.$requisition->id.'/attachments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attachmentId)
            ->assertJsonPath('data.0.filename', 'quote.pdf');

        $previewResponse = $this->actingAsTenant($tenant, $user)
            ->get('/api/attachments/'.$attachmentId.'/preview')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $previewResponse->headers->get('content-disposition'));

        $this->actingAsTenant($tenant, $user)
            ->get('/api/attachments/'.$attachmentId.'/download')
            ->assertOk()
            ->assertDownload('quote.pdf');

        $this->actingAsTenant($tenant, $user)
            ->deleteJson('/api/attachments/'.$attachmentId)
            ->assertNoContent();

        Storage::disk('attachments')->assertMissing($attachment->storage_path);

        $this->assertSoftDeleted('attachments', [
            'id' => $attachmentId,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'attachment.previewed',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'attachment.downloaded',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'tenant_id' => $tenant->id,
            'actor_id' => $user->id,
            'event_type' => 'attachment.deleted',
        ]);
    }

    public function test_attachment_metadata_and_bytes_are_tenant_scoped(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        [$otherTenant, $otherUser] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);
        $attachmentId = $this->uploadAttachment($tenant, $user, $requisition);

        $this->actingAsTenant($otherTenant, $otherUser)
            ->getJson('/api/requisitions/'.$requisition->id.'/attachments')
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherUser)
            ->get('/api/attachments/'.$attachmentId.'/preview')
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherUser)
            ->get('/api/attachments/'.$attachmentId.'/download')
            ->assertNotFound();

        $this->actingAsTenant($otherTenant, $otherUser)
            ->deleteJson('/api/attachments/'.$attachmentId)
            ->assertNotFound();
    }

    public function test_upload_rejects_unsupported_and_empty_files(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->post('/api/requisitions/'.$requisition->id.'/attachments', [
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');

        $this->actingAsTenant($tenant, $user)
            ->post('/api/requisitions/'.$requisition->id.'/attachments', [
                'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_upload_rejects_extension_and_mime_type_mismatches(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);

        $this->actingAsTenant($tenant, $user)
            ->post('/api/requisitions/'.$requisition->id.'/attachments', [
                'file' => UploadedFile::fake()->create('quote.pdf', 10, 'image/png'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_non_previewable_attachment_rejects_preview_request(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);
        $attachmentId = $this->uploadAttachment($tenant, $user, $requisition, 'notes.txt', 'text/plain');

        $this->actingAsTenant($tenant, $user)
            ->get('/api/attachments/'.$attachmentId.'/preview')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_missing_attachment_bytes_are_logged_before_not_found_on_preview_and_download(): void
    {
        Storage::fake('attachments');

        [$tenant, $user] = $this->tenantUser('requester');
        $requisition = $this->createDraft($tenant, $user);
        $attachmentId = $this->uploadAttachment($tenant, $user, $requisition);
        $attachment = Attachment::query()->findOrFail($attachmentId);

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Attachment bytes are missing.',
                \Mockery::on(fn (array $context): bool => $context['action'] === 'preview' && (string) $context['attachment_id'] === (string) $attachmentId),
            );

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Attachment bytes are missing.',
                \Mockery::on(fn (array $context): bool => $context['action'] === 'download' && (string) $context['attachment_id'] === (string) $attachmentId),
            );

        Storage::disk('attachments')->delete($attachment->storage_path);

        $this->actingAsTenant($tenant, $user)
            ->get('/api/attachments/'.$attachmentId.'/preview')
            ->assertNotFound();

        $this->actingAsTenant($tenant, $user)
            ->get('/api/attachments/'.$attachmentId.'/download')
            ->assertNotFound();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create(['name' => fake()->company()]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => $role]);

        return [$tenant, $user];
    }

    private function actingAsTenant(Tenant $tenant, User $user): self
    {
        Sanctum::actingAs($user);

        return $this->withHeader('X-Tenant-Id', (string) $tenant->id);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createDraft(Tenant $tenant, User $user, array $attributes = []): Requisition
    {
        return Requisition::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'requester_id' => $user->id,
            'number' => $attributes['number'] ?? sprintf(
                'REQ-2026-%06d',
                Requisition::query()->where('tenant_id', $tenant->id)->count() + 1,
            ),
            'title' => $attributes['title'] ?? 'Attachment test requisition',
            'business_justification' => $attributes['business_justification'] ?? 'Evidence is required for this request.',
            'needed_by_date' => $attributes['needed_by_date'] ?? '2026-07-15',
            'currency' => $attributes['currency'] ?? 'MYR',
            'status' => $attributes['status'] ?? RequisitionStatus::Draft,
        ], $attributes));
    }

    private function uploadAttachment(
        Tenant $tenant,
        User $user,
        Requisition $requisition,
        string $filename = 'quote.pdf',
        string $mimeType = 'application/pdf',
    ): string {
        return (string) $this->actingAsTenant($tenant, $user)
            ->post('/api/requisitions/'.$requisition->id.'/attachments', [
                'file' => UploadedFile::fake()->create($filename, 64, $mimeType),
            ])
            ->json('data.id');
    }
}

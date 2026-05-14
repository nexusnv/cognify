<?php

namespace Domains\Attachment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Attachment\Support\AttachmentStorage;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StoreRequisitionAttachment
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly AttachmentStorage $storage,
    ) {
    }

    public function handle(Tenant $tenant, User $actor, Requisition $requisition, UploadedFile $file): Attachment
    {
        if (! $actor->can('view', $requisition)) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        $stored = $this->storage->store($tenant, $requisition, $file);

        try {
            $attachment = DB::transaction(function () use ($tenant, $actor, $requisition, $stored): Attachment {
                $attachment = Attachment::query()->create([
                    'tenant_id' => $tenant->id,
                    'attachable_type' => Requisition::class,
                    'attachable_id' => $requisition->id,
                    'uploaded_by' => $actor->id,
                    'original_filename' => $stored['originalFilename'],
                    'mime_type' => $stored['mimeType'],
                    'extension' => $stored['extension'],
                    'size_bytes' => $stored['sizeBytes'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'checksum_sha256' => $stored['checksum'],
                    'previewable' => $stored['previewable'],
                ]);

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'attachment.uploaded',
                    subject: $attachment,
                    metadata: [
                        'parentType' => 'requisition',
                        'parentId' => (string) $requisition->id,
                        'mimeType' => $attachment->mime_type,
                        'sizeBytes' => $attachment->size_bytes,
                    ],
                    after: [
                        'filename' => $attachment->original_filename,
                        'previewable' => $attachment->previewable,
                    ],
                    subjectDisplay: $attachment->original_filename,
                ));

                return $attachment->load(['uploader', 'attachable']);
            });
        } catch (Throwable $throwable) {
            Storage::disk($stored['disk'])->delete($stored['path']);

            throw $throwable;
        }

        return $attachment;
    }
}

<?php

namespace Domains\Attachment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteAttachment
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, Attachment $attachment): void
    {
        if (! $actor->can('delete', $attachment)) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        if ((int) $attachment->tenant_id !== (int) $tenant->id) {
            throw new NotFoundHttpException('Attachment not found.');
        }

        DB::transaction(function () use ($tenant, $actor, $attachment): void {
            $disk = Storage::disk($attachment->storage_disk);

            if ($disk->exists($attachment->storage_path) && ! $disk->delete($attachment->storage_path)) {
                throw new RuntimeException('Unable to delete attachment bytes from storage.');
            }

            $attachment->delete();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'attachment.deleted',
                subject: $attachment,
                metadata: [
                    'parentType' => $attachment->attachable_type,
                    'parentId' => (string) $attachment->attachable_id,
                ],
                after: [
                    'filename' => $attachment->original_filename,
                ],
                subjectDisplay: $attachment->original_filename,
            ));
        });
    }
}

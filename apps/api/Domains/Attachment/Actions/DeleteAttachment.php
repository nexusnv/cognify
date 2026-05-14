<?php

namespace Domains\Attachment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DeleteAttachment
{
    public function __construct(private readonly AuditRecorder $auditRecorder)
    {
    }

    public function handle(Tenant $tenant, User $actor, Attachment $attachment): void
    {
        if (! $actor->can('delete', $attachment)) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        DB::transaction(function () use ($tenant, $actor, $attachment): void {
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

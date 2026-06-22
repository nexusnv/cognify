<?php

namespace Domains\Attachment\Http\Controllers;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Attachment\Actions\DeleteAttachment;
use Domains\Attachment\Models\Attachment;
use Domains\Attachment\Support\AttachmentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class AttachmentFileController extends Controller
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function preview(Request $request, CurrentTenant $currentTenant, AttachmentStorage $storage, int $attachment)
    {
        $attachment = $this->findTenantAttachment($currentTenant, $attachment);

        $this->authorize('preview', $attachment);

        if (! $attachment->previewable) {
            throw ValidationException::withMessages([
                'file' => ['Preview is unsupported for this file type.'],
            ]);
        }

        if (! $storage->exists($attachment)) {
            $this->logMissingAttachmentBytes($attachment, 'preview');
            abort(404);
        }

        $this->auditRecorder->record(new AuditEventData(
            tenant: $currentTenant->get(),
            actor: $request->user(),
            action: 'attachment.previewed',
            subject: $attachment,
            metadata: [
                'mimeType' => $attachment->mime_type,
                'sizeBytes' => $attachment->size_bytes,
            ],
            subjectDisplay: $attachment->original_filename,
        ));

        $filenameFallback = Str::ascii($attachment->original_filename) ?: 'attachment';

        return response()->file(Storage::disk($attachment->storage_disk)->path($attachment->storage_path), [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $attachment->original_filename,
                $filenameFallback,
            ),
        ]);
    }

    public function download(Request $request, CurrentTenant $currentTenant, AttachmentStorage $storage, int $attachment)
    {
        $attachment = $this->findTenantAttachment($currentTenant, $attachment);

        $this->authorize('download', $attachment);

        if (! $storage->exists($attachment)) {
            $this->logMissingAttachmentBytes($attachment, 'download');
            abort(404);
        }

        $this->auditRecorder->record(new AuditEventData(
            tenant: $currentTenant->get(),
            actor: $request->user(),
            action: 'attachment.downloaded',
            subject: $attachment,
            metadata: [
                'mimeType' => $attachment->mime_type,
                'sizeBytes' => $attachment->size_bytes,
            ],
            subjectDisplay: $attachment->original_filename,
        ));

        return Storage::disk($attachment->storage_disk)->download(
            $attachment->storage_path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function destroy(Request $request, CurrentTenant $currentTenant, DeleteAttachment $deleteAttachment, int $attachment)
    {
        $attachment = $this->findTenantAttachment($currentTenant, $attachment);

        $deleteAttachment->handle($currentTenant->get(), $request->user(), $attachment);

        return response()->noContent();
    }

    private function findTenantAttachment(CurrentTenant $currentTenant, int $id): Attachment
    {
        return Attachment::query()
            ->with(['uploader', 'attachable'])
            ->where('tenant_id', $currentTenant->get()->id)
            ->findOrFail($id);
    }

    private function logMissingAttachmentBytes(Attachment $attachment, string $action): void
    {
        Log::warning('Attachment bytes are missing.', [
            'action' => $action,
            'attachment_id' => $attachment->id,
            'tenant_id' => $attachment->tenant_id,
            'storage_disk' => $attachment->storage_disk,
            'storage_path' => $attachment->storage_path,
        ]);
    }
}

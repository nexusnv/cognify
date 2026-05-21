<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Attachment\Support\AttachmentStorage;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\States\QuotationStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StoreQuotationAttachment
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly AttachmentStorage $storage,
        private readonly CreateOrRevealQuotationForInvitation $createOrRevealQuotationForInvitation,
        private readonly CreateQuotationVersionSnapshot $createQuotationVersionSnapshot,
    ) {
    }

    public function handle(
        Tenant $tenant,
        ?User $actor,
        RfqInvitation $invitation,
        UploadedFile $file,
        QuotationSubmissionSource $source,
    ): Quotation {
        $receivedAt = now();
        $stored = null;

        try {
            $quotation = DB::transaction(function () use ($tenant, $actor, $invitation, $file, $source, $receivedAt, &$stored): Quotation {
                $result = $this->createOrRevealQuotationForInvitation->handle($tenant, $invitation, $actor);
                $quotation = Quotation::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($result['quotation']->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $stored = $this->storage->store($tenant, $quotation, $file);

                $attachment = Attachment::query()->create([
                    'tenant_id' => $tenant->id,
                    'attachable_type' => Quotation::class,
                    'attachable_id' => $quotation->id,
                    'uploaded_by' => $actor?->id,
                    'original_filename' => $stored['originalFilename'],
                    'mime_type' => $stored['mimeType'],
                    'extension' => $stored['extension'],
                    'size_bytes' => $stored['sizeBytes'],
                    'storage_disk' => $stored['disk'],
                    'storage_path' => $stored['path'],
                    'checksum_sha256' => $stored['checksum'],
                    'previewable' => $stored['previewable'],
                ]);

                $fileCount = Attachment::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('attachable_type', Quotation::class)
                    ->where('attachable_id', $quotation->id)
                    ->count();

                $quotation->forceFill([
                    'status' => QuotationStatus::Received->value,
                    'submission_source' => $quotation->submission_source ?? $source->value,
                    'submitted_at' => $quotation->submitted_at ?? $receivedAt,
                    'submitted_by_user_id' => $quotation->submitted_by_user_id ?? $actor?->id,
                    'submitted_by_vendor_contact' => $quotation->submitted_by_vendor_contact
                        ?? ($source === QuotationSubmissionSource::VendorPortal ? [
                            'name' => $invitation->contact_name,
                            'email' => $invitation->contact_email,
                        ] : null),
                    'file_count' => $fileCount,
                    'latest_received_at' => $receivedAt,
                ])->save();

                $quotation->refresh()->load(['attachments' => fn ($query) => $query->with('uploader')->latest('created_at'), 'submittedByUser', 'rfq', 'vendor', 'rfqInvitation']);

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation.attachment_uploaded',
                    subject: $attachment,
                    metadata: [
                        'source' => $source->value,
                        'rfqInvitationId' => (string) $invitation->id,
                        'rfqId' => (string) $invitation->rfq_id,
                        'vendorId' => (string) $invitation->vendor_id,
                    ],
                    subjectDisplay: $attachment->original_filename,
                ));

                if ($fileCount === 1) {
                    $this->auditRecorder->record(new AuditEventData(
                        tenant: $tenant,
                        actor: $actor,
                        action: 'rfq_invitation.quotation_received',
                        subject: $invitation,
                        metadata: [
                            'quotationId' => (string) $quotation->id,
                            'source' => $source->value,
                        ],
                        subjectDisplay: $quotation->number,
                    ));
                }

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
                $quotation = $quotation->refresh()->load([
                    'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
                    'submittedByUser',
                    'rfq',
                    'vendor',
                    'rfqInvitation',
                    'currentVersion.lineItems',
                ]);

                return $quotation;
            });
        } catch (Throwable $throwable) {
            if (isset($stored)) {
                Storage::disk($stored['disk'])->delete($stored['path']);
            }

            throw $throwable;
        }

        return $quotation;
    }
}

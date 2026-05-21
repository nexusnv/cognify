<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Data\QuotationVersionAttachmentSnapshotData;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateQuotationVersionSnapshot
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {
    }

    /**
     * @param  array<int, int|string>|null  $attachmentIds
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        Tenant $tenant,
        Quotation $quotation,
        ?User $actor,
        QuotationSubmissionSource $source,
        ?array $attachmentIds = null,
        array $metadata = [],
    ): QuotationVersion {
        $version = DB::transaction(function () use ($tenant, $quotation, $actor, $source, $attachmentIds, $metadata): QuotationVersion {
            $lockedQuotation = Quotation::query()
                ->with([
                    'attachments' => fn ($query) => $query->with('uploader')->latest('created_at'),
                    'lineItems',
                    'rfq',
                    'vendor',
                    'rfqInvitation',
                    'currentVersion',
                ])
                ->where('tenant_id', $tenant->id)
                ->whereKey($quotation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousVersion = $lockedQuotation->currentVersion;
            $nextVersionNumber = ((int) $lockedQuotation->version_count) + 1;
            $attachments = $this->attachmentsForSnapshot($lockedQuotation, $attachmentIds);

            if ($previousVersion !== null) {
                $previousVersion->forceFill([
                    'is_current' => false,
                    'superseded_at' => now(),
                ])->save();
            }

            $version = QuotationVersion::query()->create([
                'tenant_id' => $tenant->id,
                'quotation_id' => $lockedQuotation->id,
                'version_number' => $nextVersionNumber,
                'status' => $lockedQuotation->status?->value ?? $lockedQuotation->status,
                'submission_source' => $source->value,
                'submitted_at' => $lockedQuotation->submitted_at,
                'submitted_by_user_id' => $lockedQuotation->submitted_by_user_id,
                'submitted_by_vendor_contact' => $lockedQuotation->submitted_by_vendor_contact,
                'is_current' => true,
                'quotation_reference' => $lockedQuotation->quotation_reference,
                'quoted_at' => $lockedQuotation->quoted_at,
                'valid_until' => $lockedQuotation->valid_until,
                'currency' => $lockedQuotation->currency,
                'subtotal_amount' => $lockedQuotation->subtotal_amount,
                'tax_amount' => $lockedQuotation->tax_amount,
                'freight_amount' => $lockedQuotation->freight_amount,
                'discount_amount' => $lockedQuotation->discount_amount,
                'total_amount' => $lockedQuotation->total_amount,
                'payment_terms' => $lockedQuotation->payment_terms,
                'delivery_terms' => $lockedQuotation->delivery_terms,
                'lead_time_days' => $lockedQuotation->lead_time_days,
                'warranty_terms' => $lockedQuotation->warranty_terms,
                'exclusions' => $lockedQuotation->exclusions,
                'compliance_notes' => $lockedQuotation->compliance_notes,
                'buyer_notes' => $lockedQuotation->buyer_notes,
                'vendor_notes' => $lockedQuotation->vendor_notes,
                'manual_entry_complete' => $lockedQuotation->manual_entry_complete,
                'manual_entry_missing_fields' => $lockedQuotation->manual_entry_missing_fields,
                'attachment_snapshots' => $attachments->map(
                    fn (Attachment $attachment) => QuotationVersionAttachmentSnapshotData::fromAttachment($attachment),
                )->values()->all(),
                'metadata' => array_merge($metadata, [
                    'source' => $source->value,
                    'previousVersionId' => $previousVersion?->id === null ? null : (string) $previousVersion->id,
                ]),
            ]);

            $lockedQuotation->lineItems->values()->each(function ($lineItem, int $index) use ($tenant, $version): void {
                QuotationVersionLineItem::query()->create([
                    'tenant_id' => $tenant->id,
                    'quotation_version_id' => $version->id,
                    'rfq_line_item_id' => $lineItem->rfq_line_item_id,
                    'description' => $lineItem->description,
                    'quantity' => $lineItem->quantity,
                    'unit' => $lineItem->unit,
                    'unit_price' => $lineItem->unit_price,
                    'subtotal_amount' => $lineItem->subtotal_amount,
                    'tax_amount' => $lineItem->tax_amount,
                    'total_amount' => $lineItem->total_amount,
                    'lead_time_days' => $lineItem->lead_time_days,
                    'manufacturer' => $lineItem->manufacturer,
                    'model_number' => $lineItem->model_number,
                    'alternate_offered' => $lineItem->alternate_offered,
                    'compliance_status' => $lineItem->compliance_status,
                    'notes' => $lineItem->notes,
                    'position' => $index + 1,
                ]);
            });

            $lockedQuotation->forceFill([
                'current_version_id' => $version->id,
                'version_count' => $nextVersionNumber,
            ])->save();

            $this->recordAuditEvents($tenant, $actor, $lockedQuotation, $version, $previousVersion, $source);

            return $version->refresh()->load(['lineItems', 'submittedByUser', 'quotation']);
        });

        $this->dispatchNormalizationJob($tenant, $version);

        return $version;
    }

    protected function dispatchNormalizationJob(Tenant $tenant, QuotationVersion $version): void
    {
        try {
            $this->queueNormalizationJob($tenant, $version);
        } catch (\Throwable $throwable) {
            report($throwable);
            $this->runNormalizationSynchronously($tenant, $version);
        }
    }

    protected function queueNormalizationJob(Tenant $tenant, QuotationVersion $version): void
    {
        NormalizeQuotationVersion::dispatch($tenant->id, $version->id)->afterCommit();
    }

    protected function runNormalizationSynchronously(Tenant $tenant, QuotationVersion $version): void
    {
        app()->call(function (
            \Domains\Quotation\Actions\StartQuotationNormalization $starter,
            \Domains\Quotation\Actions\RunDeterministicQuotationNormalizer $normalizer,
            \App\Audit\AuditRecorder $auditRecorder,
            \App\Notifications\NotificationRecorder $notificationRecorder,
        ) use ($tenant, $version): void {
            (new NormalizeQuotationVersion($tenant->id, $version->id))->handle(
                $starter,
                $normalizer,
                $auditRecorder,
                $notificationRecorder,
            );
        });
    }

    /**
     * @param  array<int, int|string>|null  $attachmentIds
     * @return Collection<int, Attachment>
     */
    private function attachmentsForSnapshot(Quotation $quotation, ?array $attachmentIds): Collection
    {
        if ($attachmentIds === null || $attachmentIds === []) {
            return $quotation->attachments;
        }

        $allowedIds = collect($attachmentIds)->map(fn ($id) => (string) $id)->all();

        return $quotation->attachments
            ->filter(fn (Attachment $attachment) => in_array((string) $attachment->id, $allowedIds, true))
            ->values();
    }

    private function recordAuditEvents(
        Tenant $tenant,
        ?User $actor,
        Quotation $quotation,
        QuotationVersion $version,
        ?QuotationVersion $previousVersion,
        QuotationSubmissionSource $source,
    ): void {
        $baseMetadata = [
            'quotationId' => (string) $quotation->id,
            'versionId' => (string) $version->id,
            'versionNumber' => $version->version_number,
            'previousCurrentVersionId' => $previousVersion?->id === null ? null : (string) $previousVersion->id,
            'rfqId' => (string) $quotation->rfq_id,
            'rfqInvitationId' => (string) $quotation->rfq_invitation_id,
            'vendorId' => (string) $quotation->vendor_id,
            'source' => $source->value,
            'actor' => [
                'type' => $actor === null ? 'vendor_portal' : 'user',
                'id' => $actor?->id === null ? null : (string) $actor->id,
            ],
        ];

        if ($previousVersion !== null) {
            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation.version_superseded',
                subject: $previousVersion,
                metadata: $baseMetadata,
                subjectDisplay: $quotation->number,
            ));
        }

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation.version_created',
            subject: $version,
            metadata: $baseMetadata,
            subjectDisplay: $quotation->number,
        ));

        $this->auditRecorder->record(new AuditEventData(
            tenant: $tenant,
            actor: $actor,
            action: 'quotation.current_version_changed',
            subject: $quotation,
            metadata: $baseMetadata,
            subjectDisplay: $quotation->number,
        ));
    }
}

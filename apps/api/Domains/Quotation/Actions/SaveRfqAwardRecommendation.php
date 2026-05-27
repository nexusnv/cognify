<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqAwardRecommendationEvidence;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SaveRfqAwardRecommendation
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, array $data): RfqAwardRecommendation
    {
        Gate::forUser($actor)->authorize('manage', [RfqAwardRecommendation::class, $rfq]);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $data): RfqAwardRecommendation {
            $lockedRfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($rfq->id)
                ->lockForUpdate()
                ->firstOrFail();

            $recommendation = RfqAwardRecommendation::query()
                ->with('evidenceReferences')
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $lockedRfq->id)
                ->lockForUpdate()
                ->latest('updated_at')
                ->latest('id')
                ->first();

            if ($recommendation !== null && ! $recommendation->statusState()->isEditable()) {
                throw new ConflictHttpException('Only draft award recommendations can be edited.');
            }

            $before = $recommendation !== null ? $this->auditSnapshot($recommendation) : null;
            $validated = $this->validateSelection($tenant, $lockedRfq, $data);

            if ($recommendation === null) {
                $recommendation = new RfqAwardRecommendation;
                $recommendation->forceFill([
                    'tenant_id' => $tenant->id,
                    'rfq_id' => $lockedRfq->id,
                    'status' => RfqAwardRecommendationStatus::Draft->value,
                    'created_by_user_id' => $actor->id,
                ]);
            }

            $recommendation->forceFill([
                'recommended_vendor_id' => $validated['recommendedVendorId'],
                'recommended_quotation_id' => $validated['recommendedQuotationId'],
                'recommended_quotation_version_id' => $validated['recommendedQuotationVersionId'],
                'scorecard_id' => $validated['scorecardId'],
                'rationale' => $validated['rationale'],
                'tradeoff_summary' => $validated['tradeoffSummary'],
                'risk_summary' => $validated['riskSummary'],
                'exception_summary' => $validated['exceptionSummary'],
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->replaceEvidenceReferences(
                $tenant,
                $lockedRfq,
                $recommendation,
                $validated['evidenceReferences'],
            );

            $recommendation = $recommendation->refresh()->load('evidenceReferences');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'rfq_award_recommendation.saved',
                subject: $lockedRfq,
                metadata: ['recommendationId' => (string) $recommendation->id],
                before: $before,
                after: $this->auditSnapshot($recommendation),
                subjectDisplay: $lockedRfq->number,
            ));

            return $recommendation;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     recommendedVendorId: ?int,
     *     recommendedQuotationId: ?int,
     *     recommendedQuotationVersionId: ?int,
     *     scorecardId: ?string,
     *     rationale: ?string,
     *     tradeoffSummary: ?string,
     *     riskSummary: ?string,
     *     exceptionSummary: ?string,
     *     evidenceReferences: array<int, array{type: RfqAwardRecommendationEvidenceType, id: string, label: ?string}>
     * }
     */
    private function validateSelection(Tenant $tenant, Rfq $rfq, array $data): array
    {
        $recommendedVendorId = array_key_exists('recommendedVendorId', $data) && $data['recommendedVendorId'] !== null
            ? (int) $data['recommendedVendorId']
            : null;
        $recommendedQuotationId = array_key_exists('recommendedQuotationId', $data) && $data['recommendedQuotationId'] !== null
            ? (int) $data['recommendedQuotationId']
            : null;
        $recommendedQuotationVersionId = array_key_exists('recommendedQuotationVersionId', $data) && $data['recommendedQuotationVersionId'] !== null
            ? (int) $data['recommendedQuotationVersionId']
            : null;
        $scorecardId = $this->trimToNull($data['scorecardId'] ?? null);

        if ($recommendedQuotationId !== null && $recommendedVendorId === null) {
            throw ValidationException::withMessages([
                'recommendedVendorId' => ['A recommended vendor is required when a quotation is selected.'],
            ]);
        }

        if ($recommendedQuotationVersionId !== null && $recommendedQuotationId === null) {
            throw ValidationException::withMessages([
                'recommendedQuotationId' => ['A recommended quotation is required when a quotation version is selected.'],
            ]);
        }

        $invitation = null;

        if ($recommendedVendorId !== null) {
            $invitation = RfqInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->where('vendor_id', $recommendedVendorId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw ValidationException::withMessages([
                    'recommendedVendorId' => ['The recommended vendor must belong to this RFQ and tenant.'],
                ]);
            }
        }

        $quotation = null;

        if ($recommendedQuotationId !== null) {
            $quotation = Quotation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($recommendedQuotationId)
                ->lockForUpdate()
                ->first();

            if ($quotation === null) {
                throw ValidationException::withMessages([
                    'recommendedQuotationId' => ['The recommended quotation must belong to this RFQ and tenant.'],
                ]);
            }

            if ($recommendedVendorId !== null && (int) $quotation->vendor_id !== $recommendedVendorId) {
                throw ValidationException::withMessages([
                    'recommendedQuotationId' => ['The recommended quotation must belong to the selected vendor.'],
                ]);
            }
        }

        if ($recommendedQuotationVersionId !== null) {
            $version = QuotationVersion::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($recommendedQuotationVersionId)
                ->lockForUpdate()
                ->first();

            if ($version === null) {
                throw ValidationException::withMessages([
                    'recommendedQuotationVersionId' => ['The recommended quotation version must belong to this tenant.'],
                ]);
            }

            if ($quotation === null || (int) $version->quotation_id !== (int) $quotation->id) {
                throw ValidationException::withMessages([
                    'recommendedQuotationVersionId' => ['The recommended quotation version must belong to the selected quotation.'],
                ]);
            }
        }

        if ($scorecardId !== null) {
            $scorecard = RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($scorecardId)
                ->lockForUpdate()
                ->first();

            if ($scorecard === null) {
                throw ValidationException::withMessages([
                    'scorecardId' => ['The selected scorecard must belong to this RFQ and tenant.'],
                ]);
            }
        }

        return [
            'recommendedVendorId' => $recommendedVendorId,
            'recommendedQuotationId' => $recommendedQuotationId,
            'recommendedQuotationVersionId' => $recommendedQuotationVersionId,
            'scorecardId' => $scorecardId,
            'rationale' => $this->trimToNull($data['rationale'] ?? null),
            'tradeoffSummary' => $this->trimToNull($data['tradeoffSummary'] ?? null),
            'riskSummary' => $this->trimToNull($data['riskSummary'] ?? null),
            'exceptionSummary' => $this->trimToNull($data['exceptionSummary'] ?? null),
            'evidenceReferences' => $this->normalizeEvidenceReferences($tenant, $rfq, $data['evidenceReferences'] ?? []),
        ];
    }

    /**
     * @param  array<int, mixed>  $references
     * @return array<int, array{type: RfqAwardRecommendationEvidenceType, id: string, label: ?string}>
     */
    private function normalizeEvidenceReferences(Tenant $tenant, Rfq $rfq, array $references): array
    {
        $normalized = [];

        foreach (array_values($references) as $index => $reference) {
            if (! is_array($reference)) {
                throw ValidationException::withMessages([
                    "evidenceReferences.{$index}" => ['Each evidence reference must be an object.'],
                ]);
            }

            $type = $reference['type'] instanceof RfqAwardRecommendationEvidenceType
                ? $reference['type']
                : RfqAwardRecommendationEvidenceType::tryFrom((string) ($reference['type'] ?? ''));

            if (! $type instanceof RfqAwardRecommendationEvidenceType) {
                throw ValidationException::withMessages([
                    "evidenceReferences.{$index}.type" => ['The selected evidence reference type is invalid.'],
                ]);
            }

            $id = trim((string) ($reference['id'] ?? ''));

            if ($id === '') {
                throw ValidationException::withMessages([
                    "evidenceReferences.{$index}.id" => ['Each evidence reference requires an id.'],
                ]);
            }

            $this->assertEvidenceReferenceExists($tenant, $rfq, $type, $id, $index);

            $normalized[] = [
                'type' => $type,
                'id' => $id,
                'label' => $this->trimToNull($reference['label'] ?? null),
            ];
        }

        return $normalized;
    }

    private function assertEvidenceReferenceExists(
        Tenant $tenant,
        Rfq $rfq,
        RfqAwardRecommendationEvidenceType $type,
        string $id,
        int $index,
    ): void {
        $valid = match ($type) {
            RfqAwardRecommendationEvidenceType::QuotationVersion => QuotationVersion::query()
                ->join('quotations', 'quotations.id', '=', 'quotation_versions.quotation_id')
                ->where('quotation_versions.tenant_id', $tenant->id)
                ->where('quotations.rfq_id', $rfq->id)
                ->where('quotations.tenant_id', $tenant->id)
                ->where('quotation_versions.id', $id)
                ->lockForUpdate()
                ->exists(),
            RfqAwardRecommendationEvidenceType::QuotationAttachment => Attachment::query()
                ->join('quotations', 'quotations.id', '=', 'attachments.attachable_id')
                ->where('attachments.tenant_id', $tenant->id)
                ->where('attachments.attachable_type', Quotation::class)
                ->where('quotations.rfq_id', $rfq->id)
                ->where('quotations.tenant_id', $tenant->id)
                ->where('attachments.id', $id)
                ->lockForUpdate()
                ->exists(),
            RfqAwardRecommendationEvidenceType::ComparisonNote => QuotationComparisonNote::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->exists(),
            RfqAwardRecommendationEvidenceType::Scorecard => RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->exists(),
        };

        if (! $valid) {
            throw ValidationException::withMessages([
                "evidenceReferences.{$index}.id" => ['The evidence reference must belong to the same RFQ and tenant.'],
            ]);
        }
    }

    public function assertPersistedEvidenceReferencesBelongToRfq(
        Tenant $tenant,
        Rfq $rfq,
        RfqAwardRecommendation $recommendation,
    ): void {
        $recommendation->loadMissing('evidenceReferences');

        foreach ($recommendation->evidenceReferences as $index => $evidence) {
            $type = $evidence->evidence_type instanceof RfqAwardRecommendationEvidenceType
                ? $evidence->evidence_type
                : RfqAwardRecommendationEvidenceType::tryFrom((string) $evidence->evidence_type);

            if (! $type instanceof RfqAwardRecommendationEvidenceType) {
                throw ValidationException::withMessages([
                    "evidenceReferences.{$index}.type" => ['The selected evidence reference type is invalid.'],
                ]);
            }

            $this->assertEvidenceReferenceExists(
                $tenant,
                $rfq,
                $type,
                (string) $evidence->evidence_id,
                $index,
            );
        }
    }

    /**
     * @param  array<int, array{type: RfqAwardRecommendationEvidenceType, id: string, label: ?string}>  $references
     */
    private function replaceEvidenceReferences(
        Tenant $tenant,
        Rfq $rfq,
        RfqAwardRecommendation $recommendation,
        array $references,
    ): void {
        RfqAwardRecommendationEvidence::query()
            ->where('tenant_id', $tenant->id)
            ->where('recommendation_id', $recommendation->id)
            ->delete();

        foreach ($references as $reference) {
            RfqAwardRecommendationEvidence::query()->create([
                'tenant_id' => $tenant->id,
                'recommendation_id' => $recommendation->id,
                'evidence_type' => $reference['type']->value,
                'evidence_id' => $reference['id'],
                'label' => $reference['label'],
            ]);
        }
    }

    /**
     * @return array{status: string, evidenceCount: int}
     */
    private function auditSnapshot(RfqAwardRecommendation $recommendation): array
    {
        return [
            'status' => $recommendation->statusState()->value,
            'evidenceCount' => $recommendation->relationLoaded('evidenceReferences')
                ? $recommendation->evidenceReferences->count()
                : $recommendation->evidenceReferences()->count(),
        ];
    }

    private function trimToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

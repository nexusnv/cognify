<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Domains\Quotation\Models\RfqScorecardEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateRfqScorecardScores
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, Rfq $rfq, RfqScorecard $scorecard, array $data): RfqScorecard
    {
        Gate::forUser($actor)->authorize('update', $scorecard);

        return DB::transaction(function () use ($tenant, $actor, $rfq, $scorecard, $data): RfqScorecard {
            $lockedScorecard = RfqScorecard::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereKey($scorecard->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedScorecard->statusState()->isEditable()) {
                throw new ConflictHttpException('Completed scorecards cannot be edited.');
            }

            $entries = $this->entriesPayload($data);
            $criteria = RfqScorecardCriterion::query()
                ->where('tenant_id', $tenant->id)
                ->where('scorecard_id', $lockedScorecard->id)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RfqScorecardCriterion $criterion): string => (string) $criterion->id);
            $criteriaIds = collect($entries)->pluck('criterionId')->unique()->values();

            if ($criteria->only($criteriaIds->all())->count() !== $criteriaIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Each score entry must reference a criterion on this scorecard.'],
                ]);
            }

            $vendorIds = collect($entries)->pluck('vendorId')->unique()->map(static fn ($id): int => (int) $id)->values();
            $vendors = RfqInvitation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereIn('vendor_id', $vendorIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RfqInvitation $invitation): string => (string) $invitation->vendor_id);

            if ($vendors->count() !== $vendorIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Each score entry must reference a vendor invited to this RFQ.'],
                ]);
            }

            $quotationIds = collect($entries)
                ->pluck('quotationId')
                ->filter()
                ->unique()
                ->map(static fn ($id): int => (int) $id)
                ->values();
            $quotations = Quotation::query()
                ->where('tenant_id', $tenant->id)
                ->where('rfq_id', $rfq->id)
                ->whereIn('id', $quotationIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Quotation $quotation): string => (string) $quotation->id);

            if ($quotations->count() !== $quotationIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Each score entry must reference a quotation on this RFQ.'],
                ]);
            }

            $versionIds = collect($entries)
                ->pluck('quotationVersionId')
                ->filter()
                ->unique()
                ->map(static fn ($id): int => (int) $id)
                ->values();
            $versions = QuotationVersion::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('id', $versionIds)
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (QuotationVersion $version): string => (string) $version->id);

            if ($versions->count() !== $versionIds->count()) {
                throw ValidationException::withMessages([
                    'entries' => ['Each score entry must reference a quotation version in this tenant.'],
                ]);
            }

            $existingEntries = RfqScorecardEntry::query()
                ->where('tenant_id', $tenant->id)
                ->where('scorecard_id', $lockedScorecard->id)
                ->whereIn('scorecard_criterion_id', $criteriaIds->all())
                ->whereIn('vendor_id', $vendorIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RfqScorecardEntry $entry): string => $this->entryKey(
                    (string) $entry->scorecard_criterion_id,
                    (string) $entry->vendor_id,
                ));

            foreach ($entries as $payload) {
                /** @var RfqScorecardCriterion $criterion */
                $criterion = $criteria->get($payload['criterionId']);
                $quotation = $payload['quotationId'] !== null ? $quotations->get($payload['quotationId']) : null;
                $version = $payload['quotationVersionId'] !== null ? $versions->get($payload['quotationVersionId']) : null;

                if ($quotation !== null && (string) $quotation->vendor_id !== $payload['vendorId']) {
                    throw ValidationException::withMessages([
                        'entries' => ['Each score entry quotation must belong to the selected vendor.'],
                    ]);
                }

                if ($version !== null && $quotation !== null && (int) $version->quotation_id !== (int) $quotation->id) {
                    throw ValidationException::withMessages([
                        'entries' => ['Each score entry quotation version must belong to the selected quotation.'],
                    ]);
                }

                $entry = $existingEntries->get($this->entryKey($payload['criterionId'], $payload['vendorId']))
                    ?? new RfqScorecardEntry();
                $before = $entry->exists ? $this->snapshot($entry) : ['score' => null, 'note' => null];

                $entry->forceFill([
                    'tenant_id' => $tenant->id,
                    'scorecard_id' => $lockedScorecard->id,
                    'scorecard_criterion_id' => $criterion->id,
                    'vendor_id' => (int) $payload['vendorId'],
                    'quotation_id' => $quotation?->id,
                    'quotation_version_id' => $version?->id,
                    'score' => $payload['score'],
                    'note' => $payload['note'],
                    'scored_by_user_id' => $actor->id,
                    'scored_at' => now(),
                ])->save();

                $entry = $entry->refresh();

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation_scorecard.score_updated',
                    subject: $rfq,
                    metadata: [
                        'scorecardId' => (string) $lockedScorecard->id,
                        'criterionId' => (string) $criterion->id,
                        'vendorId' => $payload['vendorId'],
                    ],
                    before: $before,
                    after: $this->snapshot($entry),
                    subjectDisplay: $rfq->number,
                ));
            }

            return $lockedScorecard->refresh()->load('criteria', 'entries');
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{
     *     criterionId: string,
     *     vendorId: string,
     *     quotationId: ?string,
     *     quotationVersionId: ?string,
     *     score: mixed,
     *     note: ?string
     * }>
     */
    private function entriesPayload(array $data): array
    {
        $entries = $data['entries'] ?? null;

        if (! is_array($entries) || $entries === []) {
            throw ValidationException::withMessages([
                'entries' => ['At least one score entry is required.'],
            ]);
        }

        return array_map(function (array $entry): array {
            return [
                'criterionId' => (string) $entry['criterionId'],
                'vendorId' => (string) $entry['vendorId'],
                'quotationId' => array_key_exists('quotationId', $entry) && $entry['quotationId'] !== null && $entry['quotationId'] !== ''
                    ? (string) $entry['quotationId']
                    : null,
                'quotationVersionId' => array_key_exists('quotationVersionId', $entry) && $entry['quotationVersionId'] !== null
                    ? (string) $entry['quotationVersionId']
                    : null,
                'score' => $entry['score'],
                'note' => array_key_exists('note', $entry) && $entry['note'] !== null
                    ? trim((string) $entry['note'])
                    : null,
            ];
        }, array_values($entries));
    }

    private function entryKey(string $criterionId, string $vendorId): string
    {
        return $criterionId.'::'.$vendorId;
    }

    /**
     * @return array{score: ?string, note: ?string}
     */
    private function snapshot(RfqScorecardEntry $entry): array
    {
        return [
            'score' => $entry->score !== null ? number_format((float) $entry->score, 2, '.', '') : null,
            'note' => $entry->note,
        ];
    }
}

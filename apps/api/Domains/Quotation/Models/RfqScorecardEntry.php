<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RfqScorecardEntry extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'scorecard_id',
        'scorecard_criterion_id',
        'vendor_id',
        'quotation_id',
        'quotation_version_id',
        'score',
        'note',
        'scored_by_user_id',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'scored_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $entry): void {
            DB::transaction(function () use ($entry): void {
                $scorecard = null;

                if ($entry->tenant_id === null && $entry->scorecard_id !== null) {
                    $scorecard = RfqScorecard::query()->whereKey($entry->scorecard_id)->first();
                    $entry->tenant_id = $scorecard?->tenant_id;
                }

                if ($entry->scorecard_id !== null && ($entry->isDirty('scorecard_id') || $entry->isDirty('tenant_id'))) {
                    $scorecard = RfqScorecard::query()
                        ->whereKey($entry->scorecard_id)
                        ->where('tenant_id', $entry->tenant_id)
                        ->lockForUpdate()
                        ->first();

                    if ($scorecard === null) {
                        throw new InvalidArgumentException('RFQ scorecard entry must belong to the same tenant as the scorecard.');
                    }
                }

                $scorecard ??= RfqScorecard::query()
                    ->whereKey($entry->scorecard_id)
                    ->where('tenant_id', $entry->tenant_id)
                    ->first();

                if ($scorecard === null) {
                    throw new InvalidArgumentException('RFQ scorecard entry scorecard is required.');
                }

                if ($entry->scorecard_criterion_id !== null && ($entry->isDirty('scorecard_criterion_id') || $entry->isDirty('scorecard_id') || $entry->isDirty('tenant_id'))) {
                    $belongsToScorecard = RfqScorecardCriterion::query()
                        ->whereKey($entry->scorecard_criterion_id)
                        ->where('tenant_id', $entry->tenant_id)
                        ->where('scorecard_id', $entry->scorecard_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToScorecard) {
                        throw new InvalidArgumentException('RFQ scorecard entry criterion must belong to the same scorecard and tenant.');
                    }
                }

                if ($entry->vendor_id !== null && ($entry->isDirty('vendor_id') || $entry->isDirty('tenant_id') || $entry->isDirty('scorecard_id'))) {
                    $vendorIsInvited = RfqInvitation::query()
                        ->where('tenant_id', $entry->tenant_id)
                        ->where('rfq_id', $scorecard->rfq_id)
                        ->where('vendor_id', $entry->vendor_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $vendorIsInvited) {
                        throw new InvalidArgumentException('RFQ scorecard entry vendor must belong to the scorecard RFQ and tenant.');
                    }
                }

                if ($entry->quotation_id !== null && ($entry->isDirty('quotation_id') || $entry->isDirty('tenant_id') || $entry->isDirty('scorecard_id') || $entry->isDirty('vendor_id'))) {
                    $quotationBelongsToRfq = Quotation::query()
                        ->whereKey($entry->quotation_id)
                        ->where('tenant_id', $entry->tenant_id)
                        ->where('rfq_id', $scorecard->rfq_id)
                        ->where('vendor_id', $entry->vendor_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $quotationBelongsToRfq) {
                        throw new InvalidArgumentException('RFQ scorecard entry quotation must belong to the same RFQ, vendor, and tenant.');
                    }
                }

                if ($entry->quotation_version_id !== null && ($entry->isDirty('quotation_version_id') || $entry->isDirty('tenant_id') || $entry->isDirty('quotation_id'))) {
                    $versionBelongsToQuotation = QuotationVersion::query()
                        ->whereKey($entry->quotation_version_id)
                        ->where('tenant_id', $entry->tenant_id)
                        ->where('quotation_id', $entry->quotation_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $versionBelongsToQuotation) {
                        throw new InvalidArgumentException('RFQ scorecard entry quotation version must belong to the same quotation and tenant.');
                    }
                }
            });
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<RfqScorecard, $this>
     */
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(RfqScorecard::class, 'scorecard_id');
    }

    /**
     * @return BelongsTo<RfqScorecardCriterion, $this>
     */
    public function scorecardCriterion(): BelongsTo
    {
        return $this->belongsTo(RfqScorecardCriterion::class, 'scorecard_criterion_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by_user_id');
    }
}

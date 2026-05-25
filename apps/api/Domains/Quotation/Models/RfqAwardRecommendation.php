<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqAwardRecommendationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RfqAwardRecommendation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'recommended_vendor_id',
        'recommended_quotation_id',
        'recommended_quotation_version_id',
        'scorecard_id',
        'status',
        'rationale',
        'tradeoff_summary',
        'risk_summary',
        'exception_summary',
        'withdrawal_reason',
        'created_by_user_id',
        'updated_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'withdrawn_by_user_id',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqAwardRecommendationStatus::class,
            'submitted_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function statusState(): RfqAwardRecommendationStatus
    {
        return $this->status instanceof RfqAwardRecommendationStatus
            ? $this->status
            : RfqAwardRecommendationStatus::from((string) $this->getAttribute('status'));
    }

    protected static function booted(): void
    {
        static::saving(function (self $recommendation): void {
            DB::transaction(function () use ($recommendation): void {
                if ($recommendation->tenant_id === null && $recommendation->rfq_id !== null) {
                    $recommendation->tenant_id = Rfq::query()
                        ->whereKey($recommendation->rfq_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = Rfq::query()
                    ->whereKey($recommendation->rfq_id)
                    ->where('tenant_id', $recommendation->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Award recommendation RFQ must belong to the same tenant.');
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
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function recommendedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'recommended_vendor_id');
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function recommendedQuotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'recommended_quotation_id');
    }

    /**
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function recommendedQuotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'recommended_quotation_version_id');
    }

    /**
     * @return BelongsTo<RfqScorecard, $this>
     */
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(RfqScorecard::class, 'scorecard_id');
    }

    /**
     * @return HasMany<RfqAwardRecommendationEvidence, $this>
     */
    public function evidenceReferences(): HasMany
    {
        return $this->hasMany(RfqAwardRecommendationEvidence::class, 'recommendation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_user_id');
    }
}

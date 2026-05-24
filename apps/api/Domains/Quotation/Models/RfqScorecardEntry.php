<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

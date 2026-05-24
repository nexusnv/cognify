<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqScorecardStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqScorecard extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'template_id',
        'template_name',
        'template_description',
        'status',
        'applied_by_user_id',
        'applied_at',
        'completed_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqScorecardStatus::class,
            'applied_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function statusState(): RfqScorecardStatus
    {
        return $this->status instanceof RfqScorecardStatus
            ? $this->status
            : RfqScorecardStatus::from((string) $this->getAttribute('status'));
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
     * @return BelongsTo<QuotationScoringTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationScoringTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    /**
     * @return HasMany<RfqScorecardCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(RfqScorecardCriterion::class)->orderBy('display_order');
    }

    /**
     * @return HasMany<RfqScorecardEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RfqScorecardEntry::class);
    }
}

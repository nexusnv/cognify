<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfqScorecardCriterion extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'scorecard_id',
        'source_template_criterion_id',
        'category',
        'label',
        'guidance',
        'weight',
        'max_score',
        'is_required',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => QuotationScoringCriterionCategory::class,
            'weight' => 'decimal:2',
            'max_score' => 'integer',
            'is_required' => 'boolean',
            'display_order' => 'integer',
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
     * @return BelongsTo<QuotationScoringTemplateCriterion, $this>
     */
    public function sourceTemplateCriterion(): BelongsTo
    {
        return $this->belongsTo(QuotationScoringTemplateCriterion::class, 'source_template_criterion_id');
    }

    /**
     * @return HasMany<RfqScorecardEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RfqScorecardEntry::class, 'scorecard_criterion_id');
    }
}

<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationScoringTemplateCriterion extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'template_id',
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
     * @return BelongsTo<QuotationScoringTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(QuotationScoringTemplate::class, 'template_id');
    }

    /**
     * @return HasMany<RfqScorecardCriterion, $this>
     */
    public function scorecardCriteria(): HasMany
    {
        return $this->hasMany(RfqScorecardCriterion::class, 'source_template_criterion_id');
    }
}

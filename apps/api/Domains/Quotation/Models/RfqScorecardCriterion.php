<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (self $criterion): void {
            DB::transaction(function () use ($criterion): void {
                if ($criterion->tenant_id === null && $criterion->scorecard_id !== null) {
                    $criterion->tenant_id = RfqScorecard::query()
                        ->whereKey($criterion->scorecard_id)
                        ->value('tenant_id');
                }

                if ($criterion->scorecard_id !== null && ($criterion->isDirty('scorecard_id') || $criterion->isDirty('tenant_id'))) {
                    $belongsToTenant = RfqScorecard::query()
                        ->whereKey($criterion->scorecard_id)
                        ->where('tenant_id', $criterion->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('RFQ scorecard criterion must belong to the same tenant as the scorecard.');
                    }
                }

                if ($criterion->source_template_criterion_id !== null && ($criterion->isDirty('source_template_criterion_id') || $criterion->isDirty('tenant_id'))) {
                    $belongsToTenant = QuotationScoringTemplateCriterion::query()
                        ->whereKey($criterion->source_template_criterion_id)
                        ->where('tenant_id', $criterion->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('RFQ scorecard criterion source template criterion must belong to the same tenant.');
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

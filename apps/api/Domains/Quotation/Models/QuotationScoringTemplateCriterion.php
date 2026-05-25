<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationScoringTemplateCriterion extends Model
{
    use HasUuids;
    use SoftDeletes;

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

    protected static function booted(): void
    {
        static::saving(function (self $criterion): void {
            if (! $criterion->isDirty('template_id') && ! $criterion->isDirty('tenant_id')) {
                return;
            }

            DB::transaction(function () use ($criterion): void {
                if ($criterion->tenant_id === null && $criterion->template_id !== null) {
                    $criterion->tenant_id = QuotationScoringTemplate::query()
                        ->whereKey($criterion->template_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = QuotationScoringTemplate::query()
                    ->whereKey($criterion->template_id)
                    ->where('tenant_id', $criterion->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Scoring template criterion must belong to the same tenant as the template.');
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

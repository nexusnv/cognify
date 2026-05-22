<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationNormalizationPricingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationNormalizationLineGroup extends Model
{
    protected $fillable = [
        'tenant_id',
        'normalization_id',
        'group_number',
        'pricing_mode',
        'description',
        'currency',
        'bundle_total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'pricing_mode' => QuotationNormalizationPricingMode::class,
            'bundle_total_amount' => 'decimal:2',
            'group_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lineGroup): void {
            if ($lineGroup->exists && ! $lineGroup->isDirty('tenant_id') && ! $lineGroup->isDirty('normalization_id')) {
                return;
            }

            DB::transaction(function () use ($lineGroup): void {
                $normalization = QuotationNormalization::query()
                    ->whereKey($lineGroup->normalization_id)
                    ->lockForUpdate()
                    ->first();

                if ($normalization === null) {
                    throw new InvalidArgumentException('Quotation normalization line group must belong to the same tenant as the normalization.');
                }

                if ($lineGroup->tenant_id === null) {
                    $lineGroup->tenant_id = $normalization->tenant_id;
                }

                if ($lineGroup->tenant_id !== $normalization->tenant_id) {
                    throw new InvalidArgumentException('Quotation normalization line group must belong to the same tenant as the normalization.');
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
     * @return BelongsTo<QuotationNormalization, $this>
     */
    public function normalization(): BelongsTo
    {
        return $this->belongsTo(QuotationNormalization::class, 'normalization_id');
    }

    /**
     * @return HasMany<QuotationNormalizationLineMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(QuotationNormalizationLineMapping::class, 'quotation_normalization_line_group_id');
    }
}

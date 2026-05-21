<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationNormalizationField extends Model
{
    protected $fillable = [
        'tenant_id',
        'normalization_id',
        'field_path',
        'raw_value',
        'normalized_value',
        'data_type',
        'currency',
        'confidence',
        'source',
        'provenance',
    ];

    protected function casts(): array
    {
        return [
            'raw_value' => 'array',
            'normalized_value' => 'array',
            'provenance' => 'array',
            'confidence' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $field): void {
            if ($field->exists && ! $field->isDirty('tenant_id') && ! $field->isDirty('normalization_id')) {
                return;
            }

            DB::transaction(function () use ($field): void {
                $normalization = QuotationNormalization::query()
                    ->whereKey($field->normalization_id)
                    ->lockForUpdate()
                    ->first();

                if ($normalization === null) {
                    throw new InvalidArgumentException('Quotation normalization field must belong to the same tenant as the normalization.');
                }

                if ($field->tenant_id === null) {
                    $field->tenant_id = $normalization->tenant_id;
                }

                if ($field->tenant_id !== $normalization->tenant_id) {
                    throw new InvalidArgumentException('Quotation normalization field must belong to the same tenant as the normalization.');
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
}

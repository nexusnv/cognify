<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationNormalizationCorrection extends Model
{
    protected $fillable = [
        'tenant_id',
        'normalization_id',
        'issue_id',
        'field_path',
        'original_raw_value',
        'previous_normalized_value',
        'corrected_value',
        'corrected_by_user_id',
        'correction_note',
    ];

    protected function casts(): array
    {
        return [
            'original_raw_value' => 'array',
            'previous_normalized_value' => 'array',
            'corrected_value' => 'array',
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

    /**
     * @return BelongsTo<QuotationNormalizationIssue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(QuotationNormalizationIssue::class, 'issue_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }
}

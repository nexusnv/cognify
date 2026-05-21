<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationIssueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationNormalizationIssue extends Model
{
    protected $fillable = [
        'tenant_id',
        'normalization_id',
        'severity',
        'field_path',
        'issue_code',
        'message',
        'raw_value',
        'suggested_value',
        'status',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'severity' => QuotationNormalizationIssueSeverity::class,
            'status' => QuotationNormalizationIssueStatus::class,
            'raw_value' => 'array',
            'suggested_value' => 'array',
            'resolved_at' => 'immutable_datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * @return HasMany<QuotationNormalizationCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(QuotationNormalizationCorrection::class, 'issue_id');
    }
}

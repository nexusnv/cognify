<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationNormalization extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'quotation_version_id',
        'normalization_revision',
        'status',
        'is_current_for_version',
        'superseded_at',
        'normalized_at',
        'approved_at',
        'approved_by_user_id',
        'approval_note',
        'algorithm_version',
        'job_attempt_count',
        'last_job_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationNormalizationStatus::class,
            'is_current_for_version' => 'boolean',
            'superseded_at' => 'immutable_datetime',
            'normalized_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'metadata' => 'array',
            'normalization_revision' => 'integer',
            'job_attempt_count' => 'integer',
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }

    /**
     * @return HasMany<QuotationNormalizationField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(QuotationNormalizationField::class, 'normalization_id');
    }

    /**
     * @return HasMany<QuotationNormalizationLineGroup, $this>
     */
    public function lineGroups(): HasMany
    {
        return $this->hasMany(QuotationNormalizationLineGroup::class, 'normalization_id')->orderBy('group_number');
    }

    /**
     * @return HasMany<QuotationNormalizationAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(QuotationNormalizationAttachment::class, 'normalization_id');
    }

    /**
     * @return HasMany<QuotationNormalizationIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(QuotationNormalizationIssue::class, 'normalization_id');
    }

    /**
     * @return HasMany<QuotationNormalizationCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(QuotationNormalizationCorrection::class, 'normalization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isMutable(): bool
    {
        return in_array($this->status, [
            QuotationNormalizationStatus::Pending,
            QuotationNormalizationStatus::Processing,
            QuotationNormalizationStatus::NeedsReview,
            QuotationNormalizationStatus::ReadyForApproval,
            QuotationNormalizationStatus::Failed,
        ], true);
    }
}

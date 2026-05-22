<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (self $correction): void {
            if ($correction->exists
                && ! $correction->isDirty('tenant_id')
                && ! $correction->isDirty('normalization_id')
                && ! $correction->isDirty('issue_id')) {
                return;
            }

            DB::transaction(function () use ($correction): void {
                $normalization = QuotationNormalization::query()
                    ->whereKey($correction->normalization_id)
                    ->lockForUpdate()
                    ->first();

                if ($normalization === null) {
                    throw new InvalidArgumentException('Quotation normalization correction must belong to the same tenant as the normalization.');
                }

                if ($correction->tenant_id === null) {
                    $correction->tenant_id = $normalization->tenant_id;
                }

                if ($correction->tenant_id !== $normalization->tenant_id) {
                    throw new InvalidArgumentException('Quotation normalization correction must belong to the same tenant as the normalization.');
                }

                if ($correction->issue_id !== null) {
                    $issue = QuotationNormalizationIssue::query()
                        ->whereKey($correction->issue_id)
                        ->lockForUpdate()
                        ->first();

                    if ($issue === null || $issue->tenant_id !== $correction->tenant_id || $issue->normalization_id !== $correction->normalization_id) {
                        throw new InvalidArgumentException('Quotation normalization correction issue must belong to the same tenant and normalization.');
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

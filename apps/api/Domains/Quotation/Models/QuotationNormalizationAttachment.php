<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationNormalizationAttachment extends Model
{
    protected $fillable = [
        'tenant_id',
        'normalization_id',
        'quotation_version_attachment_id',
        'filename',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'available',
        'source',
        'uploaded_at',
        'evidence_role',
        'issue_summary',
    ];

    protected function casts(): array
    {
        return [
            'available' => 'boolean',
            'uploaded_at' => 'immutable_datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $attachment): void {
            if ($attachment->exists && ! $attachment->isDirty('tenant_id') && ! $attachment->isDirty('normalization_id')) {
                return;
            }

            DB::transaction(function () use ($attachment): void {
                $normalization = QuotationNormalization::query()
                    ->whereKey($attachment->normalization_id)
                    ->lockForUpdate()
                    ->first();

                if ($normalization === null) {
                    throw new InvalidArgumentException('Quotation normalization attachment must belong to the same tenant as the normalization.');
                }

                if ($attachment->tenant_id === null) {
                    $attachment->tenant_id = $normalization->tenant_id;
                }

                if ($attachment->tenant_id !== $normalization->tenant_id) {
                    throw new InvalidArgumentException('Quotation normalization attachment must belong to the same tenant as the normalization.');
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

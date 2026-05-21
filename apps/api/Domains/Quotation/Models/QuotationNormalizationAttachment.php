<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'vendor_id',
        'number',
        'status',
        'total_amount',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
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
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}

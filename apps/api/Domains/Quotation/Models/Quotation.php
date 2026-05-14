<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (self $quotation): void {
            if ($quotation->rfq_id !== null) {
                $rfq = Rfq::query()->find($quotation->rfq_id);

                if ($rfq !== null && (int) $rfq->tenant_id !== (int) $quotation->tenant_id) {
                    throw new InvalidArgumentException('Quotation RFQ must belong to the same tenant.');
                }
            }

            if ($quotation->vendor_id !== null) {
                $vendor = Vendor::query()->find($quotation->vendor_id);

                if ($vendor !== null && (int) $vendor->tenant_id !== (int) $quotation->tenant_id) {
                    throw new InvalidArgumentException('Quotation vendor must belong to the same tenant.');
                }
            }
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

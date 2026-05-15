<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
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
            DB::transaction(function () use ($quotation): void {
                if ($quotation->rfq_id !== null && ($quotation->isDirty('rfq_id') || $quotation->isDirty('tenant_id'))) {
                    $belongsToTenant = Rfq::query()
                        ->whereKey($quotation->rfq_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Quotation RFQ must belong to the same tenant.');
                    }
                }

                if ($quotation->vendor_id !== null && ($quotation->isDirty('vendor_id') || $quotation->isDirty('tenant_id'))) {
                    $belongsToTenant = Vendor::query()
                        ->whereKey($quotation->vendor_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Quotation vendor must belong to the same tenant.');
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

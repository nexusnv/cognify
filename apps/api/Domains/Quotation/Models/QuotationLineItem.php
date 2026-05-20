<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationLineItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'rfq_line_item_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'lead_time_days',
        'manufacturer',
        'model_number',
        'alternate_offered',
        'compliance_status',
        'notes',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'lead_time_days' => 'integer',
            'alternate_offered' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $lineItem): void {
            DB::transaction(function () use ($lineItem): void {
                if ($lineItem->tenant_id === null && $lineItem->quotation_id !== null) {
                    $lineItem->tenant_id = Quotation::query()
                        ->whereKey($lineItem->quotation_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = Quotation::query()
                    ->whereKey($lineItem->quotation_id)
                    ->where('tenant_id', $lineItem->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Quotation line item must belong to a quotation in the same tenant.');
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}

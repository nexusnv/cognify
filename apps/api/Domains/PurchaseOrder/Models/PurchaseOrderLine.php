<?php

namespace Domains\PurchaseOrder\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'source_line_id',
        'line_number',
        'description',
        'category',
        'sku',
        'unit',
        'quantity',
        'unit_price',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'needed_by_date',
        'expected_delivery_date',
        'delivery_location',
        'notes',
        'source_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'needed_by_date' => 'immutable_date',
            'expected_delivery_date' => 'immutable_date',
            'source_snapshot' => 'array',
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}

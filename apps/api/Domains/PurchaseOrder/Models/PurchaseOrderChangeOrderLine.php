<?php

namespace Domains\PurchaseOrder\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class PurchaseOrderChangeOrderLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_change_order_id',
        'purchase_order_line_id',
        'line_number',
        'change_action',
        'quantity_before',
        'quantity_after',
        'unit_price_before',
        'unit_price_after',
        'subtotal_amount_before',
        'subtotal_amount_after',
        'tax_amount_before',
        'tax_amount_after',
        'freight_amount_before',
        'freight_amount_after',
        'discount_amount_before',
        'discount_amount_after',
        'total_amount_before',
        'total_amount_after',
        'expected_delivery_date_before',
        'expected_delivery_date_after',
        'delivery_location_before',
        'delivery_location_after',
        'notes_before',
        'notes_after',
        'delta_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity_before' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'unit_price_before' => 'decimal:4',
            'unit_price_after' => 'decimal:4',
            'subtotal_amount_before' => 'decimal:2',
            'subtotal_amount_after' => 'decimal:2',
            'tax_amount_before' => 'decimal:2',
            'tax_amount_after' => 'decimal:2',
            'freight_amount_before' => 'decimal:2',
            'freight_amount_after' => 'decimal:2',
            'discount_amount_before' => 'decimal:2',
            'discount_amount_after' => 'decimal:2',
            'total_amount_before' => 'decimal:2',
            'total_amount_after' => 'decimal:2',
            'expected_delivery_date_before' => 'immutable_date',
            'expected_delivery_date_after' => 'immutable_date',
            'delta_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->isDirty(['purchase_order_change_order_id', 'tenant_id'])) {
                $changeOrderExists = PurchaseOrderChangeOrder::query()
                    ->whereKey($line->purchase_order_change_order_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->exists();

                if (! $changeOrderExists) {
                    throw new InvalidArgumentException('Purchase order change order line must belong to the same tenant as the change order.');
                }
            }

            if ($line->isDirty(['purchase_order_line_id', 'tenant_id'])) {
                $purchaseOrderLineExists = PurchaseOrderLine::query()
                    ->whereKey($line->purchase_order_line_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->exists();

                if (! $purchaseOrderLineExists) {
                    throw new InvalidArgumentException('Purchase order change order line must belong to the same tenant as the purchase order line.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderChangeOrder::class, 'purchase_order_change_order_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}

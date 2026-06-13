<?php

namespace Domains\Fulfillment\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ShipmentLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'shipment_id',
        'purchase_order_line_id',
        'line_number',
        'quantity_shipped',
        'quantity_delivered',
        'backorder_quantity',
        'backorder_expected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_shipped' => 'decimal:4',
            'quantity_delivered' => 'decimal:4',
            'backorder_quantity' => 'decimal:4',
            'backorder_expected_at' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->shipment_id !== null && $line->isDirty(['shipment_id', 'tenant_id'])) {
                $belongsToTenant = Shipment::query()
                    ->whereKey($line->shipment_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Shipment line shipment must belong to the same tenant.');
                }
            }

            if ($line->purchase_order_line_id !== null && $line->isDirty(['purchase_order_line_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrderLine::query()
                    ->whereKey($line->purchase_order_line_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Shipment line purchase order line must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}

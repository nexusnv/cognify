<?php

namespace Domains\PurchaseOrder\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
        'status',
        'current_version_number',
        'cancelled_by_change_order_id',
        'cancelled_at',
        'cancelled_reason',
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
            'current_version_number' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if (! $line->isDirty('purchase_order_id') && ! $line->isDirty('tenant_id')) {
                return;
            }

            DB::transaction(function () use ($line): void {
                if ($line->tenant_id === null && $line->purchase_order_id !== null) {
                    $line->tenant_id = PurchaseOrder::query()
                        ->whereKey($line->purchase_order_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($line->purchase_order_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Purchase order line must belong to the same tenant as the purchase order.');
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function cancelledByChangeOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderChangeOrder::class, 'cancelled_by_change_order_id');
    }
}

<?php

namespace Domains\Receiving\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class GoodsReceiptLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'purchase_order_line_id',
        'line_number',
        'quantity_ordered',
        'quantity_received',
        'quantity_accepted',
        'rejection_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'quantity_accepted' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->isDirty(['goods_receipt_id', 'purchase_order_line_id', 'tenant_id'])) {
                if ($line->tenant_id === null && $line->goods_receipt_id !== null) {
                    $line->tenant_id = GoodsReceipt::query()
                        ->whereKey($line->goods_receipt_id)
                        ->value('tenant_id');
                }

                if ($line->purchase_order_line_id === null) {
                    throw new InvalidArgumentException('Goods receipt line must reference a purchase order line.');
                }

                $belongsToTenant = PurchaseOrderLine::query()
                    ->whereKey($line->purchase_order_line_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt line PO line must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}

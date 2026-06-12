<?php

namespace Domains\Invoice\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class SupplierInvoiceLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'supplier_invoice_id',
        'purchase_order_line_id',
        'line_number',
        'quantity_ordered',
        'quantity_invoiced',
        'unit_price',
        'line_subtotal_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:4',
            'quantity_invoiced' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_subtotal_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            if ($line->isDirty(['supplier_invoice_id', 'purchase_order_line_id', 'tenant_id'])) {
                if ($line->tenant_id === null && $line->supplier_invoice_id !== null) {
                    $line->tenant_id = SupplierInvoice::query()
                        ->whereKey($line->supplier_invoice_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = PurchaseOrderLine::query()
                    ->whereKey($line->purchase_order_line_id)
                    ->where('tenant_id', $line->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice line PO line must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}

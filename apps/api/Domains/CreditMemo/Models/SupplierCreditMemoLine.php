<?php

namespace Domains\CreditMemo\Models;

use App\Tenancy\Tenant;
use Domains\Invoice\Models\SupplierInvoiceLine;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCreditMemoLine extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memo_lines';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'purchase_order_line_id',
        'original_invoice_line_id',
        'line_number',
        'description_snapshot',
        'quantity',
        'unit_price',
        'line_subtotal',
        'tax_code',
        'tax_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(SupplierCreditMemo::class, 'supplier_credit_memo_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function originalInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceLine::class, 'original_invoice_line_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $tenantId = (int) $line->tenant_id;

            $creditMemo = $line->creditMemo;
            $poLine = $line->purchaseOrderLine;
            $invoiceLine = $line->originalInvoiceLine;

            if ($creditMemo !== null && (int) $creditMemo->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Credit memo does not belong to the line tenant.');
            }

            if ($poLine !== null && (int) $poLine->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Purchase order line does not belong to the line tenant.');
            }

            if ($invoiceLine !== null && (int) $invoiceLine->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Original invoice line does not belong to the line tenant.');
            }
        });
    }
}

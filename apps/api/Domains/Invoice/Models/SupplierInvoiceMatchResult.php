<?php

namespace Domains\Invoice\Models;

use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceMatchResult extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'supplier_invoice_id',
        'supplier_invoice_line_id',
        'purchase_order_line_id',
        'match_type',
        'match_level',
        'dimension',
        'expected_value',
        'actual_value',
        'tolerance_percent_applied',
        'tolerance_floor_applied',
        'tolerance_cap_applied',
        'result',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'tolerance_percent_applied' => 'decimal:4',
            'tolerance_floor_applied' => 'decimal:4',
            'tolerance_cap_applied' => 'decimal:4',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function supplierInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoiceLine::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }
}

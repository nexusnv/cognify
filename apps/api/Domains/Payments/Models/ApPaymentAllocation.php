<?php

namespace Domains\Payments\Models;

use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApPaymentAllocation extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ap_payment_allocations';

    protected $fillable = [
        'tenant_id',
        'ap_payment_handoff_id',
        'supplier_invoice_id',
        'allocated_amount',
        'allocation_date',
        'payment_reference',
        'settlement_amount',
        'settlement_currency',
        'voided_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:4',
            'allocation_date' => 'date',
            'settlement_amount' => 'decimal:4',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function handoff(): BelongsTo
    {
        return $this->belongsTo(ApPaymentHandoff::class, 'ap_payment_handoff_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $allocation): void {
            $handoff = $allocation->handoff;
            $invoice = $allocation->invoice;

            if ($handoff !== null && $invoice !== null && (int) $handoff->tenant_id !== (int) $invoice->tenant_id) {
                throw new \InvalidArgumentException('Handoff and invoice must belong to the same tenant.');
            }
        });
    }
}

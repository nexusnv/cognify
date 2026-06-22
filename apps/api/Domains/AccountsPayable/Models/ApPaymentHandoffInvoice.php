<?php

namespace Domains\AccountsPayable\Models;

use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

class ApPaymentHandoffInvoice extends Model
{
    use AsPivot, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'ap_payment_handoff_id',
        'supplier_invoice_id',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Tenancy\Tenant::class);
    }

    /**
     * @return BelongsTo<ApPaymentHandoff, $this>
     */
    public function handoff(): BelongsTo
    {
        return $this->belongsTo(ApPaymentHandoff::class, 'ap_payment_handoff_id');
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }
}

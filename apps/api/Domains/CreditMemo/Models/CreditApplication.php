<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditApplication extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'credit_applications';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'supplier_invoice_id',
        'applied_amount',
        'application_date',
        'applied_by_user_id',
        'notes',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'applied_amount' => 'decimal:4',
            'application_date' => 'date',
            'voided_at' => 'datetime',
            'lock_version' => 'integer',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $application): void {
            $creditMemo = $application->creditMemo;
            $invoice = $application->invoice;

            if ($creditMemo !== null && $invoice !== null && (int) $creditMemo->tenant_id !== (int) $invoice->tenant_id) {
                throw new \InvalidArgumentException('Credit memo and invoice must belong to the same tenant.');
            }
        });
    }
}

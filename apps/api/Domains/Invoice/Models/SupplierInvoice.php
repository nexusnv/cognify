<?php

namespace Domains\Invoice\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class SupplierInvoice extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'vendor_id',
        'number',
        'invoice_number',
        'invoice_number_normalized',
        'status',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'total_amount',
        'notes',
        'captured_by_user_id',
        'captured_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'invoice_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'subtotal_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'freight_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'captured_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if ($invoice->purchase_order_id !== null && $invoice->isDirty(['purchase_order_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($invoice->purchase_order_id)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice purchase order must belong to the same tenant.');
                }
            }

            if ($invoice->vendor_id !== null && $invoice->isDirty(['vendor_id', 'tenant_id'])) {
                $belongsToTenant = Vendor::query()
                    ->whereKey($invoice->vendor_id)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice vendor must belong to the same tenant.');
                }
            }

            if ($invoice->captured_by_user_id !== null && $invoice->isDirty(['captured_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($invoice->captured_by_user_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($invoice->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice capturer must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function capturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('line_number');
    }

    public function statusState(): SupplierInvoiceStatus
    {
        return $this->status instanceof SupplierInvoiceStatus
            ? $this->status
            : SupplierInvoiceStatus::from((string) $this->getAttribute('status'));
    }
}

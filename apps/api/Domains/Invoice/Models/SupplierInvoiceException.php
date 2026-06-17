<?php

namespace Domains\Invoice\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoiceException extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'supplier_invoice_id',
        'dimension',
        'match_type',
        'supplier_invoice_line_id',
        'purchase_order_line_id',
        'expected_value',
        'actual_value',
        'status',
        'resolution_type',
        'resolution_data',
        'resolved_by_user_id',
        'resolved_at',
        'escalated_to_user_id',
        'escalated_by_user_id',
        'escalated_at',
        'escalation_note',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'expected_value' => 'decimal:4',
            'actual_value' => 'decimal:4',
            'resolution_data' => 'array',
            'resolved_at' => 'datetime',
            'escalated_at' => 'datetime',
            'lock_version' => 'integer',
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

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function escalatedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }

    public function escalatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by_user_id');
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Exception was updated by another user. Refresh and try again.');
        }
    }
}

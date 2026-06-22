<?php

namespace Domains\Payments\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoff;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Payments\States\ApPaymentImportStatus;
use Domains\Payments\States\ApPaymentImportTargetStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApPaymentImport extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ap_payment_imports';

    protected $fillable = [
        'tenant_id', 'batch_id', 'row_index', 'handoff_number', 'invoice_number',
        'payment_reference', 'allocated_amount', 'mark_full', 'settlement_amount',
        'settlement_currency', 'paid_at', 'settlement_method', 'target_status',
        'failure_code', 'failure_reason', 'void_reason', 'status', 'match_error',
        'matched_handoff_id', 'matched_invoice_id', 'reconciled_at',
        'reconciled_by_user_id', 'imported_by_user_id', 'imported_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'mark_full' => 'boolean',
            'allocated_amount' => 'decimal:4',
            'settlement_amount' => 'decimal:4',
            'paid_at' => 'date',
            'reconciled_at' => 'datetime',
            'imported_at' => 'datetime',
            'lock_version' => 'integer',
            'status' => ApPaymentImportStatus::class,
            'target_status' => ApPaymentImportTargetStatus::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function matchedHandoff(): BelongsTo
    {
        return $this->belongsTo(ApPaymentHandoff::class, 'matched_handoff_id');
    }

    public function matchedInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'matched_invoice_id');
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by_user_id');
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The AP payment import row has changed. Reload and try again.');
        }
    }
}

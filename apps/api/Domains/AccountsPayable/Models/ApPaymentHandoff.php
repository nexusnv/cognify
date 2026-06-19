<?php

namespace Domains\AccountsPayable\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\AccountsPayable\Models\ApPaymentHandoffInvoice;
use Domains\AccountsPayable\States\ApPaymentHandoffStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApPaymentHandoff extends Model
{
    use HasUuids;

    public function statusState(): ApPaymentHandoffStatus
    {
        return $this->status instanceof ApPaymentHandoffStatus
            ? $this->status
            : ApPaymentHandoffStatus::from((string) $this->getAttribute('status'));
    }

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'number',
        'status',
        'effective_payment_date',
        'notes',
        'currency',
        'total_amount',
        'remittance_reference',
        'created_by_user_id',
        'ready_by_user_id',
        'ready_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancelled_reason',
        'last_exported_by_user_id',
        'last_exported_at',
        'last_export_format',
        'snapshot',
        'readiness_warnings',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApPaymentHandoffStatus::class,
            'snapshot' => 'array',
            'readiness_warnings' => 'array',
            'lock_version' => 'integer',
            'total_amount' => 'decimal:4',
            'effective_payment_date' => 'date',
            'ready_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_exported_at' => 'datetime',
        ];
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The AP payment handoff has changed. Reload and try again.');
        }
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsToMany<SupplierInvoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            \Domains\Invoice\Models\SupplierInvoice::class,
            'ap_payment_handoff_invoice',
            'ap_payment_handoff_id',
            'supplier_invoice_id',
        )->using(ApPaymentHandoffInvoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function readyByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastExportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_exported_by_user_id');
    }
}

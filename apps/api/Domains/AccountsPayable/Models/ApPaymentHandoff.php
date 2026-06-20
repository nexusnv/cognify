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
        'tenant_id', 'number', 'status', 'effective_payment_date', 'notes',
        'currency', 'total_amount', 'remittance_reference', 'created_by_user_id',
        'ready_by_user_id', 'ready_at', 'cancelled_by_user_id', 'cancelled_at',
        'cancelled_reason', 'last_exported_by_user_id', 'last_exported_at',
        'last_export_format', 'snapshot', 'readiness_warnings', 'lock_version',
        'scheduled_by_user_id', 'scheduled_at', 'scheduled_for_date', 'payment_reference',
        'paid_by_user_id', 'paid_at', 'remittance_advice_sent_at',
        'failed_by_user_id', 'failed_at', 'failure_code', 'failure_reason',
        'voided_by_user_id', 'voided_at', 'void_reason',
        'variance_amount', 'variance_reason', 'variance_closed_by_user_id', 'variance_closed_at',
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
            'scheduled_for_date' => 'date',
            'scheduled_at' => 'datetime',
            'paid_at' => 'datetime',
            'remittance_advice_sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'voided_at' => 'datetime',
            'variance_amount' => 'decimal:4',
            'variance_closed_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function scheduledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function failedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'failed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function varianceClosedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'variance_closed_by_user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Domains\Payments\Models\ApPaymentAllocation>
     */
    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Domains\Payments\Models\ApPaymentAllocation::class, 'ap_payment_handoff_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $handoff): void {
            $tenantId = (int) $handoff->tenant_id;
            $userIds = array_filter([
                $handoff->scheduled_by_user_id,
                $handoff->paid_by_user_id,
                $handoff->failed_by_user_id,
                $handoff->voided_by_user_id,
                $handoff->variance_closed_by_user_id,
            ]);

            foreach ($userIds as $userId) {
                $exists = \App\Models\User::query()
                    ->whereKey($userId)
                    ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                    ->exists();

                if (! $exists) {
                    throw new \InvalidArgumentException("User {$userId} does not belong to tenant {$tenantId}.");
                }
            }
        });
    }
}

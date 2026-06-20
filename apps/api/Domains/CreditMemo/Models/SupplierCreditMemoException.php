<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierCreditMemoException extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memo_exceptions';

    protected $fillable = [
        'tenant_id',
        'supplier_credit_memo_id',
        'exception_type',
        'severity',
        'description',
        'resolution_type',
        'resolution_notes',
        'resolved_by_user_id',
        'resolved_at',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'escalated_by_user_id',
        'escalated_at',
        'expected_value',
        'adjusted_value',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'severity' => 'string',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'expected_value' => 'decimal:4',
            'adjusted_value' => 'decimal:4',
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

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function escalatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by_user_id');
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Credit memo exception was updated by another user. Refresh and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $exception): void {
            $tenantId = (int) $exception->tenant_id;

            $creditMemo = $exception->creditMemo;

            if ($creditMemo !== null && (int) $creditMemo->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Credit memo does not belong to the exception tenant.');
            }
        });
    }
}

<?php

namespace Domains\CreditMemo\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierCreditMemo extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'supplier_credit_memos';

    protected $fillable = [
        'tenant_id',
        'number',
        'vendor_credit_memo_number',
        'vendor_id',
        'original_invoice_id',
        'status',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'total_amount',
        'credit_date',
        'notes',
        'captured_by_user_id',
        'captured_at',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'voided_by_user_id',
        'voided_at',
        'void_reason',
        'approval_instance_id',
        'stp_eligible',
        'stp_processed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierCreditMemoStatus::class,
            'subtotal_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'freight_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'credit_date' => 'date',
            'captured_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'stp_eligible' => 'boolean',
            'stp_processed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): SupplierCreditMemoStatus
    {
        return $this->status instanceof SupplierCreditMemoStatus
            ? $this->status
            : SupplierCreditMemoStatus::from((string) $this->getAttribute('status'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Credit memo was updated by another user. Refresh and try again.');
        }
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'original_invoice_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierCreditMemoLine::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(SupplierCreditMemoException::class);
    }

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    public function capturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $creditMemo): void {
            $tenantId = (int) $creditMemo->tenant_id;

            $vendor = $creditMemo->vendor;
            $originalInvoice = $creditMemo->originalInvoice;

            if ($vendor !== null && (int) $vendor->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Vendor does not belong to the credit memo tenant.');
            }

            if ($originalInvoice !== null && (int) $originalInvoice->tenant_id !== $tenantId) {
                throw new \InvalidArgumentException('Original invoice does not belong to the credit memo tenant.');
            }

            if ($originalInvoice !== null && $vendor !== null && (int) $originalInvoice->vendor_id !== (int) $vendor->id) {
                throw new \InvalidArgumentException('Original invoice vendor must match the credit memo vendor.');
            }

            $userIds = array_filter([
                $creditMemo->captured_by_user_id,
                $creditMemo->submitted_by_user_id,
                $creditMemo->approved_by_user_id,
                $creditMemo->posted_by_user_id,
                $creditMemo->voided_by_user_id,
            ]);

            foreach ($userIds as $userId) {
                $exists = User::query()
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

<?php

namespace Domains\PurchaseOrder\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderStatus;
use Domains\PurchaseOrder\States\PurchaseOrderChangeOrderType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrderChangeOrder extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'approval_instance_id',
        'number',
        'status',
        'change_type',
        'from_purchase_order_status',
        'to_purchase_order_status',
        'reason',
        'material_change',
        'requires_approval',
        'requested_by_user_id',
        'requested_at',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejected_reason',
        'changes_requested_by_user_id',
        'changes_requested_at',
        'changes_requested_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancelled_reason',
        'before_snapshot',
        'after_snapshot',
        'delta_snapshot',
        'supplier_version_number',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderChangeOrderStatus::class,
            'change_type' => PurchaseOrderChangeOrderType::class,
            'material_change' => 'boolean',
            'requires_approval' => 'boolean',
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'delta_snapshot' => 'array',
            'supplier_version_number' => 'integer',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): PurchaseOrderChangeOrderStatus
    {
        return $this->status instanceof PurchaseOrderChangeOrderStatus
            ? $this->status
            : PurchaseOrderChangeOrderStatus::from((string) $this->getAttribute('status'));
    }

    public function typeState(): PurchaseOrderChangeOrderType
    {
        return $this->change_type instanceof PurchaseOrderChangeOrderType
            ? $this->change_type
            : PurchaseOrderChangeOrderType::from((string) $this->getAttribute('change_type'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The purchase order change order has changed. Reload and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $changeOrder): void {
            $changeOrder->assertBelongsToTenant(
                relationKey: 'purchase_order_id',
                dirtyMessage: ['purchase_order_id', 'tenant_id'],
                query: PurchaseOrder::query(),
                error: 'Purchase order change order purchase order must belong to the same tenant.',
            );
            $changeOrder->assertBelongsToTenant(
                relationKey: 'approval_instance_id',
                dirtyMessage: ['approval_instance_id', 'tenant_id'],
                query: ApprovalInstance::query(),
                error: 'Purchase order change order approval instance must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'requested_by_user_id',
                dirtyMessage: ['requested_by_user_id', 'tenant_id'],
                error: 'Purchase order change order requester must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'submitted_by_user_id',
                dirtyMessage: ['submitted_by_user_id', 'tenant_id'],
                error: 'Purchase order change order submitter must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'approved_by_user_id',
                dirtyMessage: ['approved_by_user_id', 'tenant_id'],
                error: 'Purchase order change order approver must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'rejected_by_user_id',
                dirtyMessage: ['rejected_by_user_id', 'tenant_id'],
                error: 'Purchase order change order rejector must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'changes_requested_by_user_id',
                dirtyMessage: ['changes_requested_by_user_id', 'tenant_id'],
                error: 'Purchase order change order changes-requested actor must belong to the same tenant.',
            );
            $changeOrder->assertUserBelongsToTenant(
                userKey: 'cancelled_by_user_id',
                dirtyMessage: ['cancelled_by_user_id', 'tenant_id'],
                error: 'Purchase order change order cancellation actor must belong to the same tenant.',
            );
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

    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderChangeOrderLine::class)->orderBy('line_number');
    }

    private function assertBelongsToTenant(string $relationKey, array $dirtyMessage, mixed $query, string $error): void
    {
        if (! $this->isDirty($dirtyMessage)) {
            return;
        }

        $relationId = $this->getAttribute($relationKey);

        if ($relationId === null) {
            return;
        }

        $exists = $query
            ->whereKey($relationId)
            ->where('tenant_id', $this->tenant_id)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException($error);
        }
    }

    private function assertUserBelongsToTenant(string $userKey, array $dirtyMessage, string $error): void
    {
        if (! $this->isDirty($dirtyMessage)) {
            return;
        }

        $userId = $this->getAttribute($userKey);

        if ($userId === null) {
            return;
        }

        $exists = User::query()
            ->whereKey($userId)
            ->whereHas('tenants', fn ($query) => $query->where('tenants.id', $this->tenant_id))
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException($error);
        }
    }
}

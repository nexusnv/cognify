<?php

namespace Domains\Receiving\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Receiving\States\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class GoodsReceipt extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'number',
        'status',
        'receipt_date',
        'receipt_reference',
        'notes',
        'recorded_by_user_id',
        'recorded_at',
        'requester_confirmed_by_user_id',
        'requester_confirmed_at',
        'buyer_confirmed_by_user_id',
        'buyer_confirmed_at',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'receipt_date' => 'immutable_date',
            'recorded_at' => 'datetime',
            'requester_confirmed_at' => 'datetime',
            'buyer_confirmed_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $receipt): void {
            if ($receipt->purchase_order_id !== null && $receipt->isDirty(['purchase_order_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($receipt->purchase_order_id)
                    ->where('tenant_id', $receipt->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt purchase order must belong to the same tenant.');
                }
            }

            if ($receipt->recorded_by_user_id !== null && $receipt->isDirty(['recorded_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($receipt->recorded_by_user_id)
                    ->whereHas('tenants', fn ($q) => $q->whereKey($receipt->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Goods receipt recorder must belong to the same tenant.');
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

    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class)->orderBy('line_number');
    }

    public function recordedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function requesterConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_confirmed_by_user_id');
    }

    public function buyerConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_confirmed_by_user_id');
    }

    public function statusState(): GoodsReceiptStatus
    {
        return $this->status instanceof GoodsReceiptStatus
            ? $this->status
            : GoodsReceiptStatus::from((string) $this->getAttribute('status'));
    }
}

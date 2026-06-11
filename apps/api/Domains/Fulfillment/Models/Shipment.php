<?php

namespace Domains\Fulfillment\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Fulfillment\States\ShipmentStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Shipment extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'number',
        'status',
        'carrier_name',
        'tracking_reference',
        'shipment_date',
        'estimated_arrival_date',
        'actual_delivery_date',
        'notes',
        'created_by_user_id',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipment_date' => 'immutable_date',
            'estimated_arrival_date' => 'immutable_date',
            'actual_delivery_date' => 'immutable_date',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $shipment): void {
            if ($shipment->purchase_order_id !== null && $shipment->isDirty(['purchase_order_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($shipment->purchase_order_id)
                    ->where('tenant_id', $shipment->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Shipment purchase order must belong to the same tenant.');
                }
            }

            if ($shipment->created_by_user_id !== null && $shipment->isDirty(['created_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($shipment->created_by_user_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($shipment->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Shipment creator must belong to the same tenant.');
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
        return $this->hasMany(ShipmentLine::class)->orderBy('line_number');
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(FulfillmentTrackingEvent::class)->orderByDesc('occurred_at');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusState(): ShipmentStatus
    {
        return $this->status instanceof ShipmentStatus
            ? $this->status
            : ShipmentStatus::from((string) $this->getAttribute('status'));
    }
}

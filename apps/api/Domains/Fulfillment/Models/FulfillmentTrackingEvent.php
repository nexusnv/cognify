<?php

namespace Domains\Fulfillment\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Fulfillment\States\FulfillmentTrackingEventStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class FulfillmentTrackingEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'shipment_id',
        'status',
        'occurred_at',
        'location',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentTrackingEventStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $event): void {
            if ($event->shipment_id !== null && $event->isDirty(['shipment_id', 'tenant_id'])) {
                $belongsToTenant = Shipment::query()
                    ->whereKey($event->shipment_id)
                    ->where('tenant_id', $event->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Tracking event shipment must belong to the same tenant.');
                }
            }

            if ($event->created_by_user_id !== null && $event->isDirty(['created_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($event->created_by_user_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($event->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Tracking event creator must belong to the same tenant.');
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function statusState(): FulfillmentTrackingEventStatus
    {
        return $this->status instanceof FulfillmentTrackingEventStatus
            ? $this->status
            : FulfillmentTrackingEventStatus::from((string) $this->getAttribute('status'));
    }
}

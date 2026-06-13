<?php

namespace Domains\Fulfillment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Fulfillment\Models\FulfillmentTrackingEvent;
use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\States\FulfillmentTrackingEventStatus;
use Domains\Fulfillment\States\ShipmentStatus;
use Illuminate\Support\Facades\DB;

class AddTrackingEvent
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Shipment $shipment, User $actor, array $payload): FulfillmentTrackingEvent
    {
        return DB::transaction(function () use ($shipment, $actor, $payload): FulfillmentTrackingEvent {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->where('tenant_id', $shipment->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            $event = FulfillmentTrackingEvent::query()->create([
                'tenant_id' => $shipment->tenant_id,
                'shipment_id' => $shipment->id,
                'status' => FulfillmentTrackingEventStatus::from((string) $payload['status']),
                'occurred_at' => $payload['occurredAt'],
                'location' => $payload['location'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);

            $nextStatus = match ($event->statusState()) {
                FulfillmentTrackingEventStatus::Delivered => ShipmentStatus::Delivered,
                FulfillmentTrackingEventStatus::Delayed => ShipmentStatus::Delayed,
                FulfillmentTrackingEventStatus::InTransit,
                FulfillmentTrackingEventStatus::OutForDelivery,
                FulfillmentTrackingEventStatus::Shipped,
                FulfillmentTrackingEventStatus::Arrived,
                FulfillmentTrackingEventStatus::Customs
                    => ShipmentStatus::InTransit,
                default => $shipment->statusState(),
            };

            $shipment->forceFill([
                'status' => $nextStatus,
                'actual_delivery_date' => $event->statusState() === FulfillmentTrackingEventStatus::Delivered
                    ? $event->occurred_at->toDateString()
                    : $shipment->actual_delivery_date,
                'lock_version' => $shipment->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $shipment->tenant,
                actor: $actor,
                action: 'fulfillment.shipment.tracking_event',
                subject: $shipment,
                metadata: [
                    'shipmentId' => (string) $shipment->id,
                    'shipmentNumber' => $shipment->number,
                    'trackingStatus' => $event->statusState()->value,
                ],
            ));

            return $event;
        });
    }
}

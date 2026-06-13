<?php

namespace Domains\Fulfillment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Fulfillment\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateShipment
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Shipment $shipment, User $actor, array $payload): Shipment
    {
        return DB::transaction(function () use ($shipment, $actor, $payload): Shipment {
            $shipment = Shipment::query()
                ->whereKey($shipment->id)
                ->where('tenant_id', $shipment->tenant_id)
                ->with('lines')
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $shipment->lock_version !== (int) $payload['lockVersion']) {
                throw new ConflictHttpException('The shipment has changed. Reload and try again.');
            }

            $shipment->forceFill([
                'carrier_name' => $payload['carrierName'] ?? $shipment->carrier_name,
                'tracking_reference' => $payload['trackingReference'] ?? $shipment->tracking_reference,
                'shipment_date' => $payload['shipmentDate'] ?? $shipment->shipment_date,
                'estimated_arrival_date' => $payload['estimatedArrivalDate'] ?? $shipment->estimated_arrival_date,
                'actual_delivery_date' => $payload['actualDeliveryDate'] ?? $shipment->actual_delivery_date,
                'notes' => $payload['notes'] ?? $shipment->notes,
                'lock_version' => $shipment->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $shipment->tenant,
                actor: $actor,
                action: 'fulfillment.shipment.updated',
                subject: $shipment,
                metadata: [
                    'shipmentId' => (string) $shipment->id,
                    'shipmentNumber' => $shipment->number,
                ],
            ));

            return $shipment;
        });
    }
}

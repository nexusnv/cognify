<?php

namespace Domains\Fulfillment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\States\ShipmentStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelShipment
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

            if (in_array($shipment->statusState(), [ShipmentStatus::Delivered, ShipmentStatus::Cancelled], true)) {
                throw new InvalidArgumentException('Delivered or cancelled shipments cannot be cancelled.');
            }

            if ((int) $shipment->lock_version !== (int) $payload['lockVersion']) {
                abort(409, 'The shipment has changed. Reload and try again.');
            }

            $shipment->forceFill([
                'status' => ShipmentStatus::Cancelled,
                'notes' => $payload['reason'] ?? $shipment->notes,
                'lock_version' => $shipment->lock_version + 1,
            ])->save();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $shipment->tenant,
                actor: $actor,
                action: 'fulfillment.shipment.cancelled',
                subject: $shipment,
                metadata: [
                    'shipmentId' => (string) $shipment->id,
                    'shipmentNumber' => $shipment->number,
                    'reason' => $payload['reason'] ?? null,
                ],
            ));

            return $shipment;
        });
    }
}

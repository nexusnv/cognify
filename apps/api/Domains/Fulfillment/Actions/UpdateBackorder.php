<?php

namespace Domains\Fulfillment\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use Domains\Fulfillment\Models\ShipmentLine;
use Illuminate\Support\Facades\DB;

class UpdateBackorder
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(ShipmentLine $line, User $actor, array $payload): ShipmentLine
    {
        return DB::transaction(function () use ($line, $actor, $payload): ShipmentLine {
            $line = ShipmentLine::query()
                ->whereKey($line->id)
                ->where('tenant_id', $line->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (bccomp((string) $payload['backorderQuantity'], '0', 4) < 0) {
                throw new \InvalidArgumentException('Backorder quantity cannot be negative.');
            }

            $line->forceFill([
                'backorder_quantity' => $payload['backorderQuantity'],
                'backorder_expected_at' => $payload['backorderExpectedAt'] ?? null,
                'notes' => $payload['notes'] ?? $line->notes,
            ])->save();

            $line->load('shipment');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $line->tenant,
                actor: $actor,
                action: 'fulfillment.shipment.backorder_updated',
                subject: $line->shipment,
                metadata: [
                    'shipmentId' => (string) $line->shipment_id,
                    'shipmentLineId' => (string) $line->id,
                    'backorderQuantity' => (string) $line->backorder_quantity,
                ],
            ));

            return $line;
        });
    }
}

<?php

namespace Domains\Fulfillment\Http\Controllers;

use Domains\Fulfillment\Actions\CancelShipment;
use Domains\Fulfillment\Actions\CreateShipment;
use Domains\Fulfillment\Actions\UpdateBackorder;
use Domains\Fulfillment\Actions\UpdateShipment;
use Domains\Fulfillment\Http\Requests\CreateShipmentRequest;
use Domains\Fulfillment\Http\Requests\UpdateBackorderRequest;
use Domains\Fulfillment\Http\Requests\UpdateShipmentRequest;
use Domains\Fulfillment\Http\Resources\ShipmentLineResource;
use Domains\Fulfillment\Http\Resources\ShipmentResource;
use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\Models\ShipmentLine;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ShipmentController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateShipment $createShipment,
        private readonly UpdateShipment $updateShipment,
        private readonly CancelShipment $cancelShipment,
        private readonly UpdateBackorder $updateBackorder,
    ) {}

    public function index(PurchaseOrder $purchaseOrder): ResourceCollection
    {
        $this->authorize('view', $purchaseOrder);

        $shipments = Shipment::query()
            ->where('tenant_id', $purchaseOrder->tenant_id)
            ->where('purchase_order_id', $purchaseOrder->id)
            ->with('lines')
            ->orderByDesc('shipment_date')
            ->orderByDesc('id')
            ->get();

        return ShipmentResource::collection($shipments);
    }

    public function store(CreateShipmentRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $shipment = $this->createShipment->handle($purchaseOrder, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'shipment' => [$exception->getMessage()],
            ]);
        }

        return (new ShipmentResource($shipment))->response()->setStatusCode(201);
    }

    public function show(Shipment $shipment): ShipmentResource
    {
        $this->authorize('view', $shipment);

        $shipment->load('lines');

        return new ShipmentResource($shipment);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        $shipment = $this->updateShipment->handle($shipment, $request->user(), $request->validated());

        return new ShipmentResource($shipment);
    }

    public function destroy(UpdateShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        try {
            $shipment = $this->cancelShipment->handle($shipment, $request->user(), $request->validated());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'shipment' => [$exception->getMessage()],
            ]);
        }

        return new ShipmentResource($shipment);
    }

    public function updateBackorder(UpdateBackorderRequest $request, Shipment $shipment, ShipmentLine $line): ShipmentLineResource
    {
        $this->authorize('updateBackorder', $shipment);

        if ((string) $line->shipment_id !== (string) $shipment->id) {
            abort(404);
        }

        $line = $this->updateBackorder->handle($line, $request->user(), $request->validated());

        return new ShipmentLineResource($line);
    }
}

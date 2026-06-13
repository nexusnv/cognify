<?php

namespace Domains\Fulfillment\Http\Controllers;

use Domains\Fulfillment\Actions\AddTrackingEvent;
use Domains\Fulfillment\Http\Requests\AddTrackingEventRequest;
use Domains\Fulfillment\Http\Resources\FulfillmentTrackingEventResource;
use Domains\Fulfillment\Models\FulfillmentTrackingEvent;
use Domains\Fulfillment\Models\Shipment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class FulfillmentTrackingEventController
{
    use AuthorizesRequests;

    public function __construct(private readonly AddTrackingEvent $addTrackingEvent) {}

    public function index(Shipment $shipment): ResourceCollection
    {
        $this->authorize('view', $shipment);

        $events = FulfillmentTrackingEvent::query()
            ->where('tenant_id', $shipment->tenant_id)
            ->where('shipment_id', $shipment->id)
            ->orderByDesc('occurred_at')
            ->get();

        return FulfillmentTrackingEventResource::collection($events);
    }

    public function store(AddTrackingEventRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('addTrackingEvent', $shipment);

        $event = $this->addTrackingEvent->handle($shipment, $request->user(), $request->validated());

        return (new FulfillmentTrackingEventResource($event))->response()->setStatusCode(201);
    }
}

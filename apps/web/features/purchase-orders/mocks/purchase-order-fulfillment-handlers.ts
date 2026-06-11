import { http, HttpResponse } from "msw";
import type {
  AddFulfillmentTrackingEventRequest,
  FulfillmentTrackingEvent,
  CreateShipmentRequest,
  FulfillmentStatus,
  Shipment,
  UpdateShipmentRequest,
  UpdateShipmentBackorderRequest,
} from "@cognify/api-client/schemas";
import { buildFulfillmentStatusFixture, buildShipmentFixture } from "./purchase-order-fulfillment-fixtures";

let fulfillmentStatusByPurchaseOrderId: Record<string, FulfillmentStatus> = {
  "po-1": buildFulfillmentStatusFixture(),
};
let shipmentsByPurchaseOrderId: Record<string, Shipment[]> = {
  "po-1": [],
};
let trackingEventsByShipmentId: Record<string, FulfillmentTrackingEvent[]> = {};
let shipmentIdCounter = 1;

export function resetFulfillmentMockState() {
  fulfillmentStatusByPurchaseOrderId = {
    "po-1": buildFulfillmentStatusFixture(),
  };
  shipmentsByPurchaseOrderId = {
    "po-1": [],
  };
  trackingEventsByShipmentId = {};
  shipmentIdCounter = 1;
}

export const purchaseOrderFulfillmentHandlers = [
  http.get("/api/purchase-orders/:purchaseOrderId/fulfillment", ({ params }) => {
    const purchaseOrderId = String(params.purchaseOrderId);
    const fulfillment = fulfillmentStatusByPurchaseOrderId[purchaseOrderId] ?? buildFulfillmentStatusFixture({ purchaseOrderId });

    return HttpResponse.json({ data: fulfillment });
  }),

  http.get("/api/purchase-orders/:purchaseOrderId/shipments", ({ params }) => {
    const purchaseOrderId = String(params.purchaseOrderId);

    return HttpResponse.json({
      data: shipmentsByPurchaseOrderId[purchaseOrderId] ?? [],
    });
  }),

  http.post("/api/purchase-orders/:purchaseOrderId/shipments", async ({ params, request }) => {
    const purchaseOrderId = String(params.purchaseOrderId);
    const body = (await request.json()) as CreateShipmentRequest;
    const shipmentNumber = `SH-2026-${String(shipmentIdCounter).padStart(6, "0")}`;
    const shipment = buildShipmentFixture({
      id: `shipment-${shipmentIdCounter}`,
      purchaseOrderId,
      number: shipmentNumber,
      status: "confirmed",
      carrierName: body.carrierName ?? null,
      trackingReference: body.trackingReference ?? null,
      shipmentDate: body.shipmentDate,
      estimatedArrivalDate: body.estimatedArrivalDate ?? null,
      notes: body.notes ?? null,
      lockVersion: 1,
      lines: body.lines.map((line, index) => ({
        id: `shipment-line-${shipmentIdCounter}-${index + 1}`,
        purchaseOrderLineId: line.purchaseOrderLineId,
        lineNumber: index + 1,
        quantityShipped: line.quantityShipped,
        quantityDelivered: "0.0000",
        backorderQuantity: line.backorderQuantity ?? "0.0000",
        backorderExpectedAt: line.backorderExpectedAt ?? null,
        notes: line.notes ?? null,
      })),
    });

    shipmentIdCounter += 1;
    shipmentsByPurchaseOrderId[purchaseOrderId] = [...(shipmentsByPurchaseOrderId[purchaseOrderId] ?? []), shipment];
    trackingEventsByShipmentId[shipment.id] = [];
    fulfillmentStatusByPurchaseOrderId[purchaseOrderId] = buildFulfillmentStatusFixture({
      purchaseOrderId,
      overallStatus: "awaiting_delivery",
      shipmentCount: shipmentsByPurchaseOrderId[purchaseOrderId].length,
      lineSummaries: [
        {
          purchaseOrderLineId: shipment.lines[0]?.purchaseOrderLineId ?? "po-line-1",
          lineNumber: 1,
          orderedQuantity: "10.0000",
          receivedQuantity: "0.0000",
          backorderQuantity: shipment.lines[0]?.backorderQuantity ?? "0.0000",
          expectedDeliveryDate: "2026-07-02",
          status: "awaiting_delivery",
          isDelayed: false,
        },
      ],
    });

    return HttpResponse.json({ data: shipment }, { status: 201 });
  }),

  http.get("/api/shipments/:shipmentId/tracking-events", ({ params }) => {
    const shipmentId = String(params.shipmentId);

    return HttpResponse.json({
      data: trackingEventsByShipmentId[shipmentId] ?? [],
    });
  }),

  http.post("/api/shipments/:shipmentId/tracking-events", async ({ params, request }) => {
    const shipmentId = String(params.shipmentId);
    const body = (await request.json()) as AddFulfillmentTrackingEventRequest;
    const event: FulfillmentTrackingEvent = {
      id: `tracking-event-${shipmentId}-${(trackingEventsByShipmentId[shipmentId]?.length ?? 0) + 1}`,
      shipmentId,
      status: body.status,
      occurredAt: body.occurredAt,
      location: body.location ?? null,
      notes: body.notes ?? null,
      createdByUserId: "user-1",
    };

    trackingEventsByShipmentId[shipmentId] = [...(trackingEventsByShipmentId[shipmentId] ?? []), event];

    for (const purchaseOrderId of Object.keys(shipmentsByPurchaseOrderId)) {
      const shipments = shipmentsByPurchaseOrderId[purchaseOrderId] ?? [];
      const shipmentIndex = shipments.findIndex((shipment) => shipment.id === shipmentId);

      if (shipmentIndex === -1) {
        continue;
      }

      const currentShipment = shipments[shipmentIndex];
      const nextShipment = {
        ...currentShipment,
        status:
          body.status === "delivered" ? "delivered" : body.status === "delayed" ? "delayed" : "in_transit",
        actualDeliveryDate: body.status === "delivered" ? body.occurredAt.slice(0, 10) : currentShipment.actualDeliveryDate,
        lockVersion: currentShipment.lockVersion + 1,
      } satisfies Shipment;

      shipmentsByPurchaseOrderId[purchaseOrderId] = shipments.map((item, index) =>
        index === shipmentIndex ? nextShipment : item,
      );
      break;
    }

    return HttpResponse.json({ data: event }, { status: 201 });
  }),

  http.patch("/api/shipments/:shipmentId", async ({ params, request }) => {
    const shipmentId = String(params.shipmentId);
    const body = (await request.json()) as UpdateShipmentRequest;

    for (const purchaseOrderId of Object.keys(shipmentsByPurchaseOrderId)) {
      const shipments = shipmentsByPurchaseOrderId[purchaseOrderId] ?? [];
      const shipmentIndex = shipments.findIndex((shipment) => shipment.id === shipmentId);

      if (shipmentIndex === -1) {
        continue;
      }

      const currentShipment = shipments[shipmentIndex];
      const nextShipment = {
        ...currentShipment,
        carrierName: body.carrierName ?? currentShipment.carrierName,
        trackingReference: body.trackingReference ?? currentShipment.trackingReference,
        shipmentDate: body.shipmentDate ?? currentShipment.shipmentDate,
        estimatedArrivalDate: body.estimatedArrivalDate ?? currentShipment.estimatedArrivalDate,
        actualDeliveryDate: body.actualDeliveryDate ?? currentShipment.actualDeliveryDate,
        notes: body.notes ?? currentShipment.notes,
        lockVersion: currentShipment.lockVersion + 1,
      } satisfies Shipment;

      shipmentsByPurchaseOrderId[purchaseOrderId] = shipments.map((item, index) =>
        index === shipmentIndex ? nextShipment : item,
      );

      return HttpResponse.json({ data: nextShipment });
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "Shipment not found." } },
      { status: 404 },
    );
  }),

  http.delete("/api/shipments/:shipmentId", async ({ params, request }) => {
    const shipmentId = String(params.shipmentId);
    const body = (await request.json()) as UpdateShipmentRequest;

    for (const purchaseOrderId of Object.keys(shipmentsByPurchaseOrderId)) {
      const shipments = shipmentsByPurchaseOrderId[purchaseOrderId] ?? [];
      const shipmentIndex = shipments.findIndex((shipment) => shipment.id === shipmentId);

      if (shipmentIndex === -1) {
        continue;
      }

      const currentShipment = shipments[shipmentIndex];
      const nextShipment = {
        ...currentShipment,
        status: "cancelled",
        notes: body.notes ?? currentShipment.notes,
        lockVersion: currentShipment.lockVersion + 1,
      } satisfies Shipment;

      shipmentsByPurchaseOrderId[purchaseOrderId] = shipments.map((item, index) =>
        index === shipmentIndex ? nextShipment : item,
      );

      return HttpResponse.json({ data: nextShipment });
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "Shipment not found." } },
      { status: 404 },
    );
  }),

  http.patch("/api/shipments/:shipmentId/lines/:lineId/backorder", async ({ params, request }) => {
    const shipmentId = String(params.shipmentId);
    const lineId = String(params.lineId);
    const body = (await request.json()) as UpdateShipmentBackorderRequest;

    for (const purchaseOrderId of Object.keys(shipmentsByPurchaseOrderId)) {
      const shipments = shipmentsByPurchaseOrderId[purchaseOrderId] ?? [];
      const shipmentIndex = shipments.findIndex((shipment) => shipment.id === shipmentId);

      if (shipmentIndex === -1) {
        continue;
      }

      const shipment = shipments[shipmentIndex];
      const lineIndex = shipment.lines.findIndex((line) => line.id === lineId);

      if (lineIndex === -1) {
        continue;
      }

      const nextLine = {
        ...shipment.lines[lineIndex],
        backorderQuantity: body.backorderQuantity,
        backorderExpectedAt: body.backorderExpectedAt ?? null,
        notes: body.notes ?? shipment.lines[lineIndex].notes ?? null,
      };
      const nextShipment = {
        ...shipment,
        lines: shipment.lines.map((line, index) => (index === lineIndex ? nextLine : line)),
      };

      shipmentsByPurchaseOrderId[purchaseOrderId] = shipments.map((item, index) =>
        index === shipmentIndex ? nextShipment : item,
      );
      fulfillmentStatusByPurchaseOrderId[purchaseOrderId] = buildFulfillmentStatusFixture({
        purchaseOrderId,
        overallStatus: "backordered",
        shipmentCount: shipmentsByPurchaseOrderId[purchaseOrderId].length,
        lineSummaries: [
          {
            purchaseOrderLineId: nextLine.purchaseOrderLineId,
            lineNumber: nextLine.lineNumber,
            orderedQuantity: "10.0000",
            receivedQuantity: "0.0000",
            backorderQuantity: nextLine.backorderQuantity,
            expectedDeliveryDate: "2026-07-02",
            status: "backordered",
            isDelayed: false,
          },
        ],
      });

      return HttpResponse.json({ data: nextLine });
    }

    return HttpResponse.json(
      { error: { code: "not_found", message: "Shipment line not found." } },
      { status: 404 },
    );
  }),
];

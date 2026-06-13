import type { FulfillmentStatus, Shipment } from "@cognify/api-client/schemas";

export function buildFulfillmentStatusFixture(
  overrides: Partial<FulfillmentStatus> = {},
): FulfillmentStatus {
  return {
    purchaseOrderId: "po-1",
    overallStatus: "pending_shipment",
    isDelayed: false,
    lateDeliveryCount: 0,
    totalLineCount: 1,
    deliveredLineCount: 0,
    shipmentCount: 0,
    lineSummaries: [
      {
        purchaseOrderLineId: "po-line-1",
        lineNumber: 1,
        orderedQuantity: "10.0000",
        receivedQuantity: "0.0000",
        backorderQuantity: "0.0000",
        expectedDeliveryDate: "2026-07-02",
        status: "pending_shipment",
        isDelayed: false,
      },
    ],
    ...overrides,
  };
}

export function buildShipmentFixture(overrides: Partial<Shipment> = {}): Shipment {
  return {
    id: "shipment-1",
    purchaseOrderId: "po-1",
    number: "SH-2026-000001",
    status: "confirmed",
    carrierName: "DHL Supply Chain",
    trackingReference: "TRACK-001",
    shipmentDate: "2026-06-11",
    estimatedArrivalDate: "2026-06-13",
    actualDeliveryDate: null,
    notes: null,
    createdByUserId: "user-1",
    lockVersion: 1,
    lines: [
      {
        id: "shipment-line-1",
        purchaseOrderLineId: "po-line-1",
        lineNumber: 1,
        quantityShipped: "4.0000",
        quantityDelivered: "0.0000",
        backorderQuantity: "0.0000",
        backorderExpectedAt: null,
        notes: null,
      },
    ],
    ...overrides,
  };
}

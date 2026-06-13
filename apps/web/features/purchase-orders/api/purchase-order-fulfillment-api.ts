"use client";

import {
  createShipmentTrackingEvent as createShipmentTrackingEventEndpoint,
  createPurchaseOrderShipment as createPurchaseOrderShipmentEndpoint,
  cancelShipment as cancelShipmentEndpoint,
  listShipmentTrackingEvents as listShipmentTrackingEventsEndpoint,
  listPurchaseOrderShipments as listPurchaseOrderShipmentsEndpoint,
  showPurchaseOrderFulfillment as showPurchaseOrderFulfillmentEndpoint,
  updateShipment as updateShipmentEndpoint,
  updateShipmentLineBackorder as updateShipmentLineBackorderEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  AddFulfillmentTrackingEventRequest,
  CreateShipmentRequest,
  FulfillmentStatus,
  FulfillmentTrackingEvent,
  Shipment,
  ShipmentLine,
  UpdateShipmentRequest,
  UpdateShipmentBackorderRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  const xsrfToken = getXsrfToken();

  return {
    credentials: "include",
    headers: {
      "X-Tenant-Id": tenantId,
      ...(xsrfToken ? { "X-XSRF-TOKEN": xsrfToken } : {}),
    },
  };
}

function unwrapOk(response: { status: number; data: unknown }, expectedStatus = 200): unknown {
  if (response.status !== expectedStatus) {
    throw response.data;
  }

  if (typeof response.data !== "object" || response.data === null || !("data" in response.data)) {
    throw new Error(`Expected response payload with a data property, received ${JSON.stringify(response.data)}`);
  }

  return (response.data as { data: unknown }).data;
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw normalizeErrorData((error as { data: unknown }).data);
  }

  throw error;
}

function normalizeErrorData(data: unknown): { message: string; code?: string } {
  if (typeof data === "object" && data !== null) {
    const payload = data as { message?: unknown; code?: unknown };
    const message =
      typeof payload.message === "string"
        ? payload.message
        : JSON.stringify(data);

    return {
      message,
      ...(typeof payload.code === "string" ? { code: payload.code } : {}),
    };
  }

  return { message: String(data) };
}

function getXsrfToken(): string | null {
  if (typeof document === "undefined") return null;

  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.split("=")[1];

  return token ? decodeURIComponent(token) : null;
}

export async function fetchPurchaseOrderFulfillment(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<FulfillmentStatus> {
  const response = await showPurchaseOrderFulfillmentEndpoint(
    purchaseOrderId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as FulfillmentStatus;
}

export async function fetchPurchaseOrderShipments(
  purchaseOrderId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Shipment[]> {
  const response = await listPurchaseOrderShipmentsEndpoint(
    purchaseOrderId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as Shipment[];
}

export async function createPurchaseOrderShipment(
  purchaseOrderId: string,
  payload: CreateShipmentRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Shipment> {
  const response = await createPurchaseOrderShipmentEndpoint(
    purchaseOrderId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response, 201) as Shipment;
}

export async function updateShipment(
  shipmentId: string,
  payload: UpdateShipmentRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Shipment> {
  const response = await updateShipmentEndpoint(shipmentId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as Shipment;
}

export async function cancelShipment(
  shipmentId: string,
  payload: UpdateShipmentRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Shipment> {
  const response = await cancelShipmentEndpoint(shipmentId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as Shipment;
}

export async function fetchShipmentTrackingEvents(
  shipmentId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<FulfillmentTrackingEvent[]> {
  const response = await listShipmentTrackingEventsEndpoint(
    shipmentId,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as FulfillmentTrackingEvent[];
}

export async function createShipmentTrackingEvent(
  shipmentId: string,
  payload: AddFulfillmentTrackingEventRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<FulfillmentTrackingEvent> {
  const response = await createShipmentTrackingEventEndpoint(
    shipmentId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response, 201) as FulfillmentTrackingEvent;
}

export async function updateShipmentLineBackorder(
  shipmentId: string,
  lineId: string,
  payload: UpdateShipmentBackorderRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ShipmentLine> {
  const response = await updateShipmentLineBackorderEndpoint(
    shipmentId,
    lineId,
    payload,
    withActiveTenantHeader(tenantId),
  ).catch(throwResponseData);

  return unwrapOk(response) as ShipmentLine;
}

"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  AddFulfillmentTrackingEventRequest,
  CreateShipmentRequest,
  UpdateShipmentBackorderRequest,
  UpdateShipmentRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  cancelShipment,
  createPurchaseOrderShipment,
  createShipmentTrackingEvent,
  fetchPurchaseOrderFulfillment,
  fetchPurchaseOrderShipments,
  fetchShipmentTrackingEvents,
  updateShipmentLineBackorder,
  updateShipment,
} from "../api/purchase-order-fulfillment-api";

export const purchaseOrderFulfillmentKeys = {
  all: ["purchase-orders", "fulfillment"] as const,
  summary: (tenantId: string, purchaseOrderId: string) =>
    [...purchaseOrderFulfillmentKeys.all, "summary", tenantId, purchaseOrderId] as const,
  shipments: (tenantId: string, purchaseOrderId: string) =>
    [...purchaseOrderFulfillmentKeys.all, "shipments", tenantId, purchaseOrderId] as const,
  trackingEvents: (tenantId: string, shipmentId: string) =>
    [...purchaseOrderFulfillmentKeys.all, "tracking-events", tenantId, shipmentId] as const,
};

export function usePurchaseOrderFulfillment(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchPurchaseOrderFulfillment(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function usePurchaseOrderShipments(purchaseOrderId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryPurchaseOrderId = purchaseOrderId || "no-purchase-order";

  return useQuery({
    queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, queryPurchaseOrderId),
    queryFn: () => fetchPurchaseOrderShipments(purchaseOrderId, tenantId),
    enabled: Boolean(tenantId && purchaseOrderId),
  });
}

export function useCreatePurchaseOrderShipment(purchaseOrderId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: CreateShipmentRequest) =>
      createPurchaseOrderShipment(purchaseOrderId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useUpdateShipment(purchaseOrderId: string, shipmentId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: UpdateShipmentRequest) => updateShipment(shipmentId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useCancelShipment(purchaseOrderId: string, shipmentId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: UpdateShipmentRequest) => cancelShipment(shipmentId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useShipmentTrackingEvents(shipmentId: string) {
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";
  const queryShipmentId = shipmentId || "no-shipment";

  return useQuery({
    queryKey: purchaseOrderFulfillmentKeys.trackingEvents(queryTenantId, queryShipmentId),
    queryFn: () => fetchShipmentTrackingEvents(shipmentId, tenantId),
    enabled: Boolean(tenantId && shipmentId),
  });
}

export function useCreateShipmentTrackingEvent(purchaseOrderId: string, shipmentId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: AddFulfillmentTrackingEventRequest) =>
      createShipmentTrackingEvent(shipmentId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.trackingEvents(queryTenantId, shipmentId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

export function useUpdateShipmentLineBackorder(purchaseOrderId: string, shipmentId: string, lineId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();
  const queryTenantId = tenantId ?? "no-tenant";

  return useMutation({
    mutationFn: (payload: UpdateShipmentBackorderRequest) =>
      updateShipmentLineBackorder(shipmentId, lineId, payload, tenantId),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.shipments(queryTenantId, purchaseOrderId),
        }),
        queryClient.invalidateQueries({
          queryKey: purchaseOrderFulfillmentKeys.summary(queryTenantId, purchaseOrderId),
        }),
      ]);
    },
  });
}

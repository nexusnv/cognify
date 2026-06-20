"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { AddApPaymentAllocationRequest } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  listPaymentAllocations,
  createPaymentAllocation,
} from "../api/accounts-payable-payment-allocation-api";
import { apPaymentHandoffKeys } from "./use-payment-handoffs";

export const apPaymentAllocationKeys = {
  all: ["accounts-payable", "payment-allocations"] as const,
  list: (tenantId: string, handoffId: string) =>
    [...apPaymentAllocationKeys.all, "list", tenantId, handoffId] as const,
};

function useInvalidateAllocationCaches() {
  const queryClient = useQueryClient();

  return async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: apPaymentAllocationKeys.all }),
      queryClient.invalidateQueries({ queryKey: apPaymentHandoffKeys.all }),
    ]);
  };
}

export function useApPaymentAllocations(handoffId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: apPaymentAllocationKeys.list(tenantId ?? "no-tenant", handoffId ?? "missing"),
    queryFn: () => {
      if (!handoffId) {
        throw new Error("handoffId is required");
      }
      return listPaymentAllocations(handoffId, tenantId);
    },
    enabled: Boolean(tenantId) && Boolean(handoffId),
  });
}

export function useCreateApPaymentAllocation(handoffId: string) {
  const invalidate = useInvalidateAllocationCaches();

  return useMutation({
    mutationFn: (payload: AddApPaymentAllocationRequest) => {
      const tenantId = getStoredActiveTenantId();
      return createPaymentAllocation(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

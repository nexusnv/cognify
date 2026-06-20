"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  ScheduleApPaymentHandoffRequest,
  MarkApPaymentHandoffPaidRequest,
  CloseApPaymentHandoffWithVarianceRequest,
  MarkApPaymentHandoffFailedRequest,
  VoidApPaymentHandoffRequest,
  RescheduleApPaymentHandoffRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  schedulePaymentHandoff,
  markPaymentHandoffPaid,
  closePaymentHandoffWithVariance,
  markPaymentHandoffFailed,
  voidPaymentHandoff,
  reschedulePaymentHandoff,
} from "../api/accounts-payable-payment-status-api";
import { apPaymentHandoffKeys } from "./use-payment-handoffs";

function useInvalidatePaymentCaches() {
  const queryClient = useQueryClient();

  return async () => {
    await queryClient.invalidateQueries({ queryKey: apPaymentHandoffKeys.all });
  };
}

export function useSchedulePaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: ScheduleApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return schedulePaymentHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useMarkPaymentHandoffPaid(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: MarkApPaymentHandoffPaidRequest) => {
      const tenantId = getStoredActiveTenantId();
      return markPaymentHandoffPaid(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useClosePaymentHandoffWithVariance(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: CloseApPaymentHandoffWithVarianceRequest) => {
      const tenantId = getStoredActiveTenantId();
      return closePaymentHandoffWithVariance(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useMarkPaymentHandoffFailed(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: MarkApPaymentHandoffFailedRequest) => {
      const tenantId = getStoredActiveTenantId();
      return markPaymentHandoffFailed(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useVoidPaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: VoidApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return voidPaymentHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

export function useReschedulePaymentHandoff(handoffId: string) {
  const invalidate = useInvalidatePaymentCaches();

  return useMutation({
    mutationFn: (payload: RescheduleApPaymentHandoffRequest) => {
      const tenantId = getStoredActiveTenantId();
      return reschedulePaymentHandoff(handoffId, payload, tenantId);
    },
    onSuccess: invalidate,
  });
}

"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  PlaceInvoiceOnHoldRequest,
  ReleaseInvoiceHoldRequest,
  RetryPaymentInductionRequest,
  SupplierInvoicePaymentResponseData,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  placeInvoiceOnPaymentHold,
  releaseInvoicePaymentHold,
  retryInvoicePaymentInduction,
} from "../api/accounts-payable-payment-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

/**
 * Place a payment-eligible supplier invoice on hold. Captures the optimistic
 * concurrency lock version and invalidates the invoice queue so the new
 * `on_hold` status and lock version propagate.
 */
export function usePlaceInvoicePaymentHold(invoiceId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: PlaceInvoiceOnHoldRequest) => {
      const tenantId = getStoredActiveTenantId();
      return placeInvoiceOnPaymentHold(invoiceId, payload, tenantId);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

/**
 * Release a supplier invoice that is currently on payment hold. Returns the
 * invoice to `payment_eligible` and refreshes the invoice queue.
 */
export function useReleaseInvoicePaymentHold(invoiceId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: ReleaseInvoiceHoldRequest) => {
      const tenantId = getStoredActiveTenantId();
      return releaseInvoicePaymentHold(invoiceId, payload, tenantId);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

/**
 * Manually retry payment induction for a ghost-approved invoice (approved but
 * never inducted into the payment pipeline). Idempotent: an invoice already
 * in a payment status is rejected by the API.
 */
export function useRetryInvoicePaymentInduction(invoiceId: string) {
  const queryClient = useQueryClient();

  return useMutation<SupplierInvoicePaymentResponseData, unknown, RetryPaymentInductionRequest>({
    mutationFn: (payload) => {
      const tenantId = getStoredActiveTenantId();
      return retryInvoicePaymentInduction(invoiceId, payload, tenantId);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

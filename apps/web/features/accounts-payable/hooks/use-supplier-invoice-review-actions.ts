"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  SupplierInvoiceCompleteReviewRequest,
  SupplierInvoiceNeedsInformationRequest,
  SupplierInvoiceStartReviewRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  completeReview,
  fetchSupplierInvoiceDetail,
  markNeedsInformation,
  startReview,
} from "../api/accounts-payable-invoices-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

export function useSupplierInvoiceDetail(invoiceId: string | null) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: accountsPayableInvoiceKeys.detail(tenantId ?? "no-tenant", invoiceId ?? "no-invoice"),
    queryFn: () => fetchSupplierInvoiceDetail(invoiceId ?? "", tenantId),
    enabled: Boolean(tenantId && invoiceId),
  });
}

export function useStartSupplierInvoiceReview(invoiceId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SupplierInvoiceStartReviewRequest) => startReview(invoiceId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

export function useMarkSupplierInvoiceNeedsInformation(invoiceId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SupplierInvoiceNeedsInformationRequest) => markNeedsInformation(invoiceId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

export function useCompleteSupplierInvoiceReview(invoiceId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (payload: SupplierInvoiceCompleteReviewRequest) => completeReview(invoiceId, payload, tenantId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

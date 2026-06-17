"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchInvoiceExceptions,
  resolveException,
  escalateException,
  type ResolveExceptionPayload,
  type EscalateExceptionPayload,
} from "../api/accounts-payable-invoice-exceptions-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

export const invoiceExceptionKeys = {
  all: ["accounts-payable", "invoice-exceptions"] as const,
  list: (tenantId: string, invoiceId: string) =>
    [...invoiceExceptionKeys.all, "list", tenantId, invoiceId] as const,
};

export function useInvoiceExceptions(supplierInvoiceId: string | undefined) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: invoiceExceptionKeys.list(tenantId ?? "no-tenant", supplierInvoiceId ?? "no-invoice"),
    queryFn: () => fetchInvoiceExceptions(supplierInvoiceId!, tenantId),
    enabled: Boolean(tenantId) && Boolean(supplierInvoiceId),
  });
}

export function useResolveException(supplierInvoiceId: string, onSuccess?: () => void) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: ResolveExceptionPayload;
    }) => resolveException(supplierInvoiceId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: invoiceExceptionKeys.all,
      });
      queryClient.invalidateQueries({
        queryKey: accountsPayableInvoiceKeys.all,
      });
      onSuccess?.();
    },
  });
}

export function useEscalateException(supplierInvoiceId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: EscalateExceptionPayload;
    }) => escalateException(supplierInvoiceId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: invoiceExceptionKeys.all,
      });
      queryClient.invalidateQueries({
        queryKey: accountsPayableInvoiceKeys.all,
      });
    },
  });
}

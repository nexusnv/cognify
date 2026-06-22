"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  AcknowledgeSupplierCreditMemoExceptionRequest,
  ResolveSupplierCreditMemoExceptionRequest,
} from "@cognify/api-client/schemas";
import {
  listCreditMemoExceptions,
  acknowledgeCreditMemoException,
  resolveCreditMemoException,
  escalateCreditMemoException,
} from "../api/accounts-payable-credit-memo-exception-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const creditMemoExceptionKeys = {
  all: ["accounts-payable", "credit-memo-exceptions"] as const,
  list: (creditMemoId: string) => [...creditMemoExceptionKeys.all, "list", creditMemoId] as const,
};

export function useSupplierCreditMemoExceptions(creditMemoId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: creditMemoExceptionKeys.list(creditMemoId ?? "missing"),
    queryFn: () => {
      if (!creditMemoId) throw new Error("creditMemoId required");
      return listCreditMemoExceptions(creditMemoId, tenantId);
    },
    enabled: Boolean(creditMemoId),
  });
}

export function useAcknowledgeCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: AcknowledgeSupplierCreditMemoExceptionRequest;
    }) => acknowledgeCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}

export function useResolveCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: ResolveSupplierCreditMemoExceptionRequest;
    }) => resolveCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}

export function useEscalateCreditMemoException(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      exceptionId,
      payload,
    }: {
      exceptionId: string;
      payload: AcknowledgeSupplierCreditMemoExceptionRequest;
    }) => escalateCreditMemoException(creditMemoId, exceptionId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditMemoExceptionKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) });
    },
  });
}

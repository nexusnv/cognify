"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  CreateCreditApplicationRequest,
  VoidCreditApplicationRequest,
} from "@cognify/api-client/schemas";
import {
  listCreditApplicationsForMemo,
  createCreditApplicationApi,
  voidCreditApplicationApi,
} from "../api/accounts-payable-credit-application-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const creditApplicationKeys = {
  all: ["accounts-payable", "credit-applications"] as const,
  list: (creditMemoId: string) => [...creditApplicationKeys.all, "list", creditMemoId] as const,
};

export function useCreditApplications(creditMemoId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: creditApplicationKeys.list(creditMemoId ?? "missing"),
    queryFn: () => {
      if (!creditMemoId) throw new Error("creditMemoId required");
      return listCreditApplicationsForMemo(creditMemoId, tenantId);
    },
    enabled: Boolean(creditMemoId),
  });
}

export function useCreateCreditApplication(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: CreateCreditApplicationRequest) =>
      createCreditApplicationApi(creditMemoId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditApplicationKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
      qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
    },
  });
}

export function useVoidCreditApplication(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      applicationId,
      payload,
    }: {
      applicationId: string;
      payload: VoidCreditApplicationRequest;
    }) => voidCreditApplicationApi(applicationId, payload, getStoredActiveTenantId()),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: creditApplicationKeys.list(creditMemoId) });
      qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
      qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
    },
  });
}

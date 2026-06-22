"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type {
  AddSupplierCreditMemoLineRequest,
  UpdateSupplierCreditMemoLineRequest,
} from "@cognify/api-client/schemas";
import {
  addCreditMemoLine,
  updateCreditMemoLine,
  removeCreditMemoLine,
} from "../api/accounts-payable-credit-memo-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function useAddSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: AddSupplierCreditMemoLineRequest) =>
      addCreditMemoLine(creditMemoId, payload, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}

export function useUpdateSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      lineId,
      payload,
    }: {
      lineId: string;
      payload: UpdateSupplierCreditMemoLineRequest;
    }) => updateCreditMemoLine(creditMemoId, lineId, payload, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}

export function useRemoveSupplierCreditMemoLine(creditMemoId: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ lineId, lockVersion }: { lineId: string; lockVersion: number }) =>
      removeCreditMemoLine(creditMemoId, lineId, lockVersion, getStoredActiveTenantId()),
    onSuccess: () => qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.detail(creditMemoId) }),
  });
}

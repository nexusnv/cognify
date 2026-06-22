"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type {
  ListSupplierCreditMemosParams,
  CreateSupplierCreditMemoRequest,
  UpdateSupplierCreditMemoRequest,
  SubmitSupplierCreditMemoForApprovalRequest,
  PostSupplierCreditMemoRequest,
  VoidSupplierCreditMemoRequest,
} from "@cognify/api-client/schemas";
import {
  listCreditMemos,
  createCreditMemo,
  updateCreditMemo,
  submitCreditMemoForApproval,
  postCreditMemo,
  voidCreditMemo,
} from "../api/accounts-payable-credit-memo-api";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export const supplierCreditMemoKeys = {
  all: ["accounts-payable", "credit-memos"] as const,
  list: (filters: object) => [...supplierCreditMemoKeys.all, "list", filters] as const,
  detail: (id: string) => [...supplierCreditMemoKeys.all, "detail", id] as const,
};

export function useSupplierCreditMemos(filters: ListSupplierCreditMemosParams = {}) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: supplierCreditMemoKeys.list(filters),
    queryFn: () => listCreditMemos(filters, tenantId),
  });
}

function useInvalidateCreditMemoCaches() {
  const qc = useQueryClient();
  return async () => {
    await qc.invalidateQueries({ queryKey: supplierCreditMemoKeys.all });
    await qc.invalidateQueries({ queryKey: ["accounts-payable", "invoices"] });
  };
}

export function useCreateSupplierCreditMemo() {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: CreateSupplierCreditMemoRequest) => createCreditMemo(payload),
    onSuccess: invalidate,
  });
}

export function useUpdateSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: UpdateSupplierCreditMemoRequest) => updateCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}

export function useSubmitSupplierCreditMemoForApproval(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: SubmitSupplierCreditMemoForApprovalRequest) =>
      submitCreditMemoForApproval(id, payload),
    onSuccess: invalidate,
  });
}

export function usePostSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: PostSupplierCreditMemoRequest) => postCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}

export function useVoidSupplierCreditMemo(id: string) {
  const invalidate = useInvalidateCreditMemoCaches();
  return useMutation({
    mutationFn: (payload: VoidSupplierCreditMemoRequest) => voidCreditMemo(id, payload),
    onSuccess: invalidate,
  });
}

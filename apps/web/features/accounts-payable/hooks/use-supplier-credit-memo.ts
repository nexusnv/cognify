"use client";

import { useQuery } from "@tanstack/react-query";
import { showCreditMemo } from "../api/accounts-payable-credit-memo-api";
import { supplierCreditMemoKeys } from "./use-supplier-credit-memos";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

export function useSupplierCreditMemo(id: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  return useQuery({
    queryKey: supplierCreditMemoKeys.detail(id ?? "missing"),
    queryFn: () => {
      if (!id) throw new Error("creditMemoId required");
      return showCreditMemo(id, tenantId);
    },
    enabled: Boolean(id),
  });
}

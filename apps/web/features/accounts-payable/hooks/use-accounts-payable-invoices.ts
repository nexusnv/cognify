"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchAccountsPayableInvoices,
  type AccountsPayableInvoiceFilters,
} from "../api/accounts-payable-invoices-api";

export const accountsPayableInvoiceKeys = {
  all: ["accounts-payable", "supplier-invoices"] as const,
  list: (tenantId: string, filters: AccountsPayableInvoiceFilters) =>
    [...accountsPayableInvoiceKeys.all, "list", tenantId, filters] as const,
  detail: (tenantId: string, invoiceId: string) =>
    [...accountsPayableInvoiceKeys.all, "detail", tenantId, invoiceId] as const,
  matchResults: (tenantId: string, invoiceId: string) =>
    [...accountsPayableInvoiceKeys.all, "match-results", tenantId, invoiceId] as const,
};

export function useAccountsPayableInvoices(filters: AccountsPayableInvoiceFilters) {
  const tenantId = getStoredActiveTenantId();

  return useQuery({
    queryKey: accountsPayableInvoiceKeys.list(tenantId ?? "no-tenant", filters),
    queryFn: () => fetchAccountsPayableInvoices(filters, tenantId),
    enabled: Boolean(tenantId),
  });
}

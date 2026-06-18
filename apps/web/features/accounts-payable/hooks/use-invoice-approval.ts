"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import type { SubmitSupplierInvoiceApprovalRequest, SupplierInvoice } from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import { submitForApproval } from "../api/accounts-payable-invoice-approval-api";
import { accountsPayableInvoiceKeys } from "./use-accounts-payable-invoices";

export type ApprovalStatus =
  | "stp_approved"
  | "approved"
  | "rejected"
  | "changes_requested"
  | "in_approval"
  | null;

export function computeApprovalStatus(invoice: SupplierInvoice): ApprovalStatus {
  if (invoice.stpProcessedAt && !invoice.approvalSubmittedAt) {
    return "stp_approved";
  }

  if (invoice.approvedAt) {
    return "approved";
  }

  if (invoice.rejectedAt) {
    return "rejected";
  }

  if (invoice.changesRequestedAt) {
    return "changes_requested";
  }

  if (invoice.approvalSubmittedAt) {
    return "in_approval";
  }

  return null;
}

export function canSubmitForApproval(invoice: SupplierInvoice): boolean {
  return invoice.status === "ready_for_approval" && !invoice.approvalSubmittedAt;
}

export function useSubmitInvoiceForApproval(invoiceId: string) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload: SubmitSupplierInvoiceApprovalRequest) => {
      const tenantId = getStoredActiveTenantId();
      return submitForApproval(invoiceId, payload, tenantId);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: accountsPayableInvoiceKeys.all });
    },
  });
}

"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardDescription, CardHeader, CardTitle } from "@cognify/ui";
import type { SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";
import { useSupplierInvoiceDetail } from "../hooks/use-supplier-invoice-review-actions";
import { InvoiceApprovalStatusBadge } from "./invoice-approval-status-badge";
import { canSubmitForApproval, computeApprovalStatus, useSubmitInvoiceForApproval } from "../hooks/use-invoice-approval";

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null) {
    const apiError = (error as { error?: { code?: string; message?: string } }).error;
    if (apiError?.message) {
      return apiError.message;
    }
  }

  return null;
}

export function InvoiceApprovalPanel({
  invoice,
  onMutationSettled,
}: {
  invoice: SupplierInvoiceQueueItem | null;
  onMutationSettled?: () => void;
}) {
  const [submitError, setSubmitError] = useState<string | null>(null);
  const detailQuery = useSupplierInvoiceDetail(invoice?.id ?? null);
  const submitMutation = useSubmitInvoiceForApproval(invoice?.id ?? "");

  const detail = detailQuery.data;
  const approvalStatus = detail ? computeApprovalStatus(detail) : null;
  const canSubmit = detail ? canSubmitForApproval(detail) : false;

  function handleSubmit() {
    if (!detail) {
      return;
    }

    setSubmitError(null);
    submitMutation.mutate(
      { lockVersion: detail.lockVersion },
      {
        onSettled: () => {
          onMutationSettled?.();
        },
        onError: (error) => {
          setSubmitError(errorToMessage(error) ?? "Failed to submit for approval.");
        },
      },
    );
  }

  if (!invoice) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Approval</CardTitle>
          <CardDescription>Select an invoice to view approval details.</CardDescription>
        </CardHeader>
      </Card>
    );
  }

  const isMismatch = invoice.status === "mismatch";

  return (
    <Card>
      <CardHeader>
        <CardTitle>Approval</CardTitle>
        <CardDescription>Invoice approval status and actions.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {detailQuery.isLoading && <p className="text-sm text-muted-foreground">Loading approval details...</p>}

        {detailQuery.isError && (
          <p className="text-sm text-destructive">
            {errorToMessage(detailQuery.error) ?? "Failed to load approval details."}
          </p>
        )}

        {detail && (
          <>
            {approvalStatus && (
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Status</span>
                <InvoiceApprovalStatusBadge status={approvalStatus} />
              </div>
            )}

            {detail.approvalSubmittedByUserId && (
              <div className="text-sm text-muted-foreground">
                Submitted by {detail.approvalSubmittedByUserId}
                {detail.approvalSubmittedAt && <> on {new Date(detail.approvalSubmittedAt).toLocaleDateString()}</>}
              </div>
            )}

            {detail.approvedByUserId && (
              <div className="text-sm text-muted-foreground">
                Approved by {detail.approvedByUserId}
                {detail.approvedAt && <> on {new Date(detail.approvedAt).toLocaleDateString()}</>}
              </div>
            )}

            {detail.rejectedByUserId && (
              <div className="text-sm text-muted-foreground">
                Rejected by {detail.rejectedByUserId}
                {detail.rejectedAt && <> on {new Date(detail.rejectedAt).toLocaleDateString()}</>}
                {detail.rejectedReason && <> — Reason: {detail.rejectedReason}</>}
              </div>
            )}

            {detail.changesRequestedByUserId && (
              <div className="text-sm text-muted-foreground">
                Changes requested by {detail.changesRequestedByUserId}
                {detail.changesRequestedAt && <> on {new Date(detail.changesRequestedAt).toLocaleDateString()}</>}
                {detail.changesRequestedReason && <> — {detail.changesRequestedReason}</>}
              </div>
            )}

            {!isMismatch && !approvalStatus && (
              <p className="text-sm text-muted-foreground">Run matching to determine approval eligibility.</p>
            )}

            {isMismatch && !approvalStatus && (
              <p className="text-sm text-muted-foreground">Resolve exceptions before approval.</p>
            )}

            {submitError && <p className="text-sm text-destructive">{submitError}</p>}

            {canSubmit && (
              <Button
                onClick={handleSubmit}
                disabled={submitMutation.isPending}
                className="w-full"
              >
                {submitMutation.isPending ? "Submitting..." : "Submit for approval"}
              </Button>
            )}
          </>
        )}
      </CardContent>
    </Card>
  );
}

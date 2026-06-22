"use client";

import { useParams } from "next/navigation";
import { Card, CardContent, Skeleton } from "@cognify/ui";
import { useSupplierCreditMemo } from "../hooks/use-supplier-credit-memo";
import { CreditMemoStatusBadge } from "../components/credit-memo-status-badge";
import { CreditMemoMathPreview } from "../components/credit-memo-math-preview";
import { CreditMemoLineEditor } from "../components/credit-memo-line-editor";
import { CreditMemoApplicationPanel } from "../components/credit-memo-application-panel";
import { CreditMemoExceptionPanel } from "../components/credit-memo-exception-panel";
import { CreditMemoApprovalPanel } from "../components/credit-memo-approval-panel";
import { CreditMemoAttachmentPanel } from "../components/credit-memo-attachment-panel";
import { CreditMemoActivityTimeline } from "../components/credit-memo-activity-timeline";
import { CreditMemoVoidPanel } from "../components/credit-memo-void-panel";
import { CreditMemoSubmitButton } from "../components/credit-memo-submit-button";

export function CreditMemoDetailWorkspace() {
  const params = useParams<{ id: string }>();
  const id = Array.isArray(params?.id) ? params.id[0] : params?.id;
  const { data: memo, isLoading, isError, error } = useSupplierCreditMemo(id);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (isError || !memo) {
    return <div className="text-destructive">{(error as Error)?.message ?? "Failed to load credit memo."}</div>;
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">{memo.number}</h1>
          <p className="text-sm text-muted-foreground">
            {memo.vendorName ?? memo.vendorId} · {memo.totalAmount} {memo.currency}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <CreditMemoStatusBadge status={memo.status} />
          {memo.permissions?.canSubmit && <CreditMemoSubmitButton creditMemoId={memo.id} lockVersion={memo.lockVersion} />}
          {memo.permissions?.canVoidCreditMemo && <CreditMemoVoidPanel creditMemoId={memo.id} lockVersion={memo.lockVersion} />}
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardContent className="pt-6 text-sm space-y-2">
              <p><span className="font-semibold">Original invoice:</span> {memo.originalInvoiceNumber ?? memo.originalInvoiceId ?? "—"}</p>
              <p><span className="font-semibold">Status:</span> {memo.status}</p>
              <p><span className="font-semibold">Currency:</span> {memo.currency}</p>
              <p><span className="font-semibold">Subtotal:</span> {memo.subtotalAmount ?? "—"}</p>
              <p><span className="font-semibold">Tax:</span> {memo.taxAmount ?? "—"}</p>
              <p><span className="font-semibold">Freight:</span> {memo.freightAmount ?? "—"}</p>
              <p><span className="font-semibold">Total:</span> {memo.totalAmount}</p>
              <p><span className="font-semibold">Applied:</span> {memo.appliedAmount ?? "0"}</p>
              <p><span className="font-semibold">Remaining:</span> {memo.remainingAmount ?? memo.totalAmount}</p>
            </CardContent>
          </Card>
          <CreditMemoMathPreview lines={memo.lines ?? []} />
          {memo.permissions?.canEdit && (
            <CreditMemoLineEditor
              creditMemoId={memo.id}
              lockVersion={memo.lockVersion}
              lines={memo.lines ?? []}
            />
          )}
          {memo.permissions?.canApply && <CreditMemoApplicationPanel creditMemoId={memo.id} lockVersion={memo.lockVersion} />}
          {memo.exceptions && memo.exceptions.length > 0 && <CreditMemoExceptionPanel creditMemoId={memo.id} />}
          <CreditMemoAttachmentPanel />
        </div>
        <div className="space-y-4">
          <CreditMemoApprovalPanel creditMemoId={memo.id} approvalInstanceId={memo.approvalInstanceId} />
          <CreditMemoActivityTimeline />
        </div>
      </div>
    </div>
  );
}

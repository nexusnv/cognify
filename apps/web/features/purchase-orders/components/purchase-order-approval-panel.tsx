"use client";

import { Button } from "@cognify/ui";
import type { PurchaseOrder } from "@cognify/api-client/schemas";
import { useSubmitPurchaseOrderApproval } from "../hooks/use-purchase-order-actions";

export function PurchaseOrderApprovalPanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const submitMutation = useSubmitPurchaseOrderApproval(purchaseOrder.id);
  const approval = purchaseOrder.approval;
  const canSubmit = purchaseOrder.permissions.canSubmitForApproval && !submitMutation.isPending;
  const state = approvalStateCopy(purchaseOrder);
  const errorMessage = errorToMessage(submitMutation.error);

  return (
    <section className="rounded-md border p-4" aria-label="Purchase order approval">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Approval review</h2>
          <p className="text-sm text-muted-foreground">{state.description}</p>
        </div>
        {purchaseOrder.permissions.canSubmitForApproval ? (
          <Button
            type="button"
            disabled={!canSubmit}
            onClick={() => submitMutation.mutate({ lockVersion: purchaseOrder.lockVersion })}
          >
            {submitMutation.isPending ? "Submitting" : "Submit for approval"}
          </Button>
        ) : null}
      </div>

      <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <ApprovalFact label="Status" value={state.label} />
        <ApprovalFact label="Instance" value={approval.approvalInstanceId ?? "Not routed"} />
        <ApprovalFact label="Updated" value={state.timestamp ?? "Not recorded"} />
      </dl>

      {purchaseOrder.status === "changes_requested" ? (
        <div className="mt-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
          <p className="font-medium">Changes requested</p>
          <p className="mt-1">{approval.changesRequestedReason ?? "Reviewer requested updates before approval."}</p>
          {approval.changesRequestedFields.length > 0 ? (
            <p className="mt-2 text-xs">Fields: {approval.changesRequestedFields.join(", ")}</p>
          ) : null}
        </div>
      ) : null}

      {purchaseOrder.status === "rejected" && approval.rejectedReason ? (
        <div className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          <p className="font-medium">Rejected</p>
          <p className="mt-1">{approval.rejectedReason}</p>
        </div>
      ) : null}

      {errorMessage ? (
        <div role="alert" className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {errorMessage}
        </div>
      ) : null}
    </section>
  );
}

function ApprovalFact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="mt-1 break-words font-medium">{value}</dd>
    </div>
  );
}

function approvalStateCopy(purchaseOrder: PurchaseOrder) {
  const approval = purchaseOrder.approval;
  switch (purchaseOrder.status) {
    case "ready_for_review":
      return {
        label: "Ready for review",
        description: "This purchase order is complete and can be routed to finance or procurement approval.",
        timestamp: approval.submittedAt ?? null,
      };
    case "in_review":
      return {
        label: "In review",
        description: "Approval tasks are active. Operational fields are locked while reviewers decide.",
        timestamp: approval.submittedAt ?? null,
      };
    case "changes_requested":
      return {
        label: "Changes requested",
        description: "Reviewers requested corrections. Update the draft fields and resubmit when ready.",
        timestamp: approval.changesRequestedAt ?? null,
      };
    case "approved":
    case "issued":
    case "acknowledged":
      return {
        label: "Approved",
        description: "This purchase order is approved for supplier issue.",
        // Issued and acknowledged are supplier states reached only after approval, so this panel keeps the approval decision timestamp.
        timestamp: approval.approvedAt ?? null,
      };
    case "rejected":
      return {
        label: "Rejected",
        description: "This purchase order was rejected during review.",
        timestamp: approval.rejectedAt ?? null,
      };
    default:
      return {
        label: "Draft",
        description: "Complete the draft fields and mark the purchase order ready before approval routing.",
        timestamp: null,
      };
  }
}

function errorToMessage(error: unknown) {
  if (error && typeof error === "object") {
    const message = (error as { error?: { message?: string }; message?: string }).error?.message ?? (error as { message?: string }).message;
    if (message) return message;
  }

  return null;
}

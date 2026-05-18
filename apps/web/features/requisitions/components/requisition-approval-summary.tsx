"use client";

import { useQuery } from "@tanstack/react-query";
import { ApprovalPolicyPreview } from "@/features/approvals/components/approval-policy-preview";
import { getRequisitionApprovalPreview } from "../api/requisitions-api";

export function RequisitionApprovalSummary({ requisitionId }: { requisitionId: string }) {
  const previewQuery = useQuery({
    queryKey: ["requisition", requisitionId, "approval-preview"],
    queryFn: () => getRequisitionApprovalPreview(requisitionId),
  });

  if (previewQuery.isLoading) {
    return (
      <section className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Approval summary</h2>
        <p className="mt-2 text-sm text-muted-foreground">Loading approval route preview.</p>
      </section>
    );
  }

  if (previewQuery.isError || !previewQuery.data) {
    return (
      <section className="rounded-md border border-red-300 bg-red-50 p-4">
        <h2 className="text-base font-semibold text-red-900">Approval summary</h2>
        <p className="mt-2 text-sm text-red-900">Approval preview could not be loaded.</p>
      </section>
    );
  }

  return (
    <ApprovalPolicyPreview
      preview={previewQuery.data}
      title="Approval summary"
      description="Read-only preview for the requisition approval path."
    />
  );
}

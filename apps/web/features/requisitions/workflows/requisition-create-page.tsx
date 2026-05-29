"use client";

import { RequisitionApprovalSummary } from "../components/requisition-approval-summary";
import { RequisitionForm } from "../forms/requisition-form";
import { useRequisition } from "../hooks/use-requisition";

export function RequisitionCreatePage({ requisitionId }: { requisitionId?: string } = {}) {
  const requisitionQuery = useRequisition(requisitionId);

  if (requisitionId && requisitionQuery.isLoading) {
    return (
      <div className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading requisition draft
      </div>
    );
  }

  if (requisitionId && (requisitionQuery.isError || !requisitionQuery.data)) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Requisition draft could not be loaded.
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {requisitionId && requisitionQuery.data ? (
        <RequisitionApprovalSummary requisitionId={requisitionId} />
      ) : null}
      <RequisitionForm initialRequisition={requisitionQuery.data} />
    </div>
  );
}

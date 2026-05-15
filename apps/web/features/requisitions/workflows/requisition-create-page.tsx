"use client";

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

  const initialRequisition =
    requisitionQuery.data && requisitionQuery.data.status === "changes_requested"
      ? { ...requisitionQuery.data, status: "draft" as const }
      : requisitionQuery.data;

  return <RequisitionForm initialRequisition={initialRequisition} />;
}

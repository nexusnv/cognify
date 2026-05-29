"use client";

import { RequisitionApprovalSummary } from "../components/requisition-approval-summary";
import { RequisitionForm } from "../forms/requisition-form";
import { useRequisition } from "../hooks/use-requisition";
import { Alert, AlertDescription, AlertTitle, Card, CardContent } from "@cognify/ui";

export function RequisitionCreatePage({ requisitionId }: { requisitionId?: string } = {}) {
  const requisitionQuery = useRequisition(requisitionId);

  if (requisitionId && requisitionQuery.isLoading) {
    return (
      <Card><CardContent className="pt-6 text-sm text-muted-foreground">Loading requisition draft</CardContent></Card>
    );
  }

  if (requisitionId && (requisitionQuery.isError || !requisitionQuery.data)) {
    return (
      <Alert variant="destructive"><AlertTitle>Requisition draft</AlertTitle><AlertDescription>Requisition draft could not be loaded.</AlertDescription></Alert>
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

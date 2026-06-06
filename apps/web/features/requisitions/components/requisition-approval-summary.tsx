"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { Alert, AlertDescription, AlertTitle, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { ApprovalPolicyPreview } from "@/features/approvals/components/approval-policy-preview";
import { ApprovalStatusBadge } from "@/features/approvals/components/approval-status-badge";
import { fetchRequisitionApprovalSummary } from "@/features/approvals/api/approvals-api";
import { getRequisitionApprovalPreview } from "../api/requisitions-api";

export function RequisitionApprovalSummary({ requisitionId }: { requisitionId: string }) {
  const summaryQuery = useQuery({
    queryKey: ["requisition", requisitionId, "approval-summary"],
    queryFn: () => fetchRequisitionApprovalSummary(requisitionId),
  });
  const previewQuery = useQuery({
    queryKey: ["requisition", requisitionId, "approval-preview"],
    queryFn: () => getRequisitionApprovalPreview(requisitionId),
    enabled: summaryQuery.isSuccess && summaryQuery.data === null,
  });

  if (summaryQuery.isLoading) {
    return (
      <Card>
        <CardHeader><CardTitle>Approval summary</CardTitle></CardHeader>
        <CardContent><p className="text-sm text-muted-foreground">Loading approval summary.</p></CardContent>
      </Card>
    );
  }

  if (summaryQuery.isError) {
    return <Alert variant="destructive"><AlertTitle>Approval summary</AlertTitle><AlertDescription>Approval summary could not be loaded.</AlertDescription></Alert>;
  }

  if (summaryQuery.data) {
    const summary = summaryQuery.data;

    return (
      <Card>
        <CardHeader className="pb-2">
        <div className="flex items-start justify-between gap-3">
          <CardTitle>Approval summary</CardTitle>
          <ApprovalStatusBadge status={summary.status} />
        </div>
        </CardHeader>
        <CardContent>
        <dl className="mt-3 space-y-3 text-sm">
          <div>
            <dt className="text-xs uppercase text-muted-foreground">Current stage</dt>
            <dd className="mt-1 font-medium">{summary.currentStage?.name ?? "Complete"}</dd>
          </div>
          <div>
            <dt className="text-xs uppercase text-muted-foreground">Active approvers</dt>
            <dd className="mt-1">
              {summary.activeApprovers.length > 0
                ? summary.activeApprovers.map((approver) => approver.name).join(", ")
                : "No active approvers"}
            </dd>
          </div>
          <div>
            <dt className="text-xs uppercase text-muted-foreground">Due state</dt>
            <dd className={summary.isOverdue ? "mt-1 font-medium text-red-700" : "mt-1"}>
              {summary.isOverdue ? "Overdue" : summary.dueAt ? `Due ${formatDate(summary.dueAt)}` : "No due date"}
            </dd>
          </div>
        </dl>
        {summary.completedDecisions.length > 0 ? (
          <ul className="mt-3 space-y-2 text-sm">
            {summary.completedDecisions.map((decision) => (
              <li key={decision.taskId} className="rounded-md border p-2">
                <span className="font-medium">{decision.decision?.replaceAll("_", " ")}</span>
                {decision.decidedBy ? <span> by {decision.decidedBy.name}</span> : null}
              </li>
            ))}
          </ul>
        ) : null}
        {summary.currentUserTaskId ? (
          <Button asChild variant="outline" className="mt-3">
            <Link href={`/approvals/tasks/${summary.currentUserTaskId}`}>Open my approval task</Link>
          </Button>
        ) : null}
        </CardContent>
      </Card>
    );
  }

  if (previewQuery.isError) {
    return <Alert variant="destructive"><AlertTitle>Approval summary</AlertTitle><AlertDescription>Approval route preview could not be loaded.</AlertDescription></Alert>;
  }

  if (previewQuery.isLoading) {
    return <Card><CardHeader><CardTitle>Approval summary</CardTitle></CardHeader><CardContent><p className="text-sm text-muted-foreground">Loading approval route preview.</p></CardContent></Card>;
  }

  if (!previewQuery.data) {
    return <Card><CardHeader><CardTitle>Approval summary</CardTitle></CardHeader><CardContent><p className="text-sm text-muted-foreground">No approval route preview is available.</p></CardContent></Card>;
  }

  return (
    <ApprovalPolicyPreview
      preview={previewQuery.data}
      title="Approval summary"
      description="Read-only preview for the requisition approval path."
    />
  );
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat("en", { month: "short", day: "numeric", year: "numeric" }).format(
    new Date(value),
  );
}

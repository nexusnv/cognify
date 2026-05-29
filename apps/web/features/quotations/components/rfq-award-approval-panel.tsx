"use client";

import Link from "next/link";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { getApiErrorMessage } from "@cognify/api-client";
import type { ApprovalSummary } from "@cognify/api-client/schemas";

type RfqAwardApprovalPanelProps = {
  recommendationStatus: string;
  canRoute: boolean;
  summary: ApprovalSummary | null;
  isLoading: boolean;
  error: unknown;
  isRouting: boolean;
  onRoute: () => void;
};

export function RfqAwardApprovalPanel({
  recommendationStatus,
  canRoute,
  summary,
  isLoading,
  error,
  isRouting,
  onRoute,
}: RfqAwardApprovalPanelProps) {
  const activeApprovers = summary?.activeApprovers ?? [];
  const completedDecision = summary?.completedDecisions[0] ?? null;

  return (
    <Card id="approval" role="region" aria-label="Approval route">
      <CardHeader>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <CardTitle className="text-base">Approval route</CardTitle>
          <p className="text-sm text-muted-foreground">Shared approval workflow for this award recommendation.</p>
        </div>
        {recommendationStatus === "pending_approval" && canRoute ? (
          <Button disabled={isRouting} onClick={onRoute}>
            Route for approval
          </Button>
        ) : null}
      </div>
      </CardHeader>
      <CardContent>

      {error ? (
        <Alert variant="destructive" className="mt-3">
          <AlertDescription>{getApprovalErrorMessage(error)}</AlertDescription>
        </Alert>
      ) : null}

      {isLoading ? <p className="mt-3 text-sm text-muted-foreground">Loading approval route</p> : null}

      {summary?.status === "active" ? (
        <dl className="mt-4 grid gap-3 text-sm md:grid-cols-3">
          <div>
            <dt className="text-muted-foreground">Current stage</dt>
            <dd className="font-medium">{summary.currentStage?.name ?? "Approval"}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Active approvers</dt>
            <dd className="font-medium">{activeApprovers.map((approver) => approver.name).join(", ") || "Unassigned"}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Due</dt>
            <dd className="font-medium">{summary.dueAt ? new Date(summary.dueAt).toLocaleDateString() : "No due date"}</dd>
          </div>
        </dl>
      ) : null}

      {summary?.status === "active" && summary.currentUserTaskId ? (
        <Button asChild variant="outline" className="mt-4">
          <Link href={`/approvals/tasks/${summary.currentUserTaskId}`}>Open approval task</Link>
        </Button>
      ) : null}

      {summary?.status && summary.status !== "active" ? (
        <div className="mt-4 rounded-md bg-muted/30 p-3 text-sm">
          <div className="font-medium">{approvalOutcomeLabel(summary.status)}</div>
          {completedDecision?.reason ? <p className="mt-1 text-muted-foreground">{completedDecision.reason}</p> : null}
        </div>
      ) : null}

      {!summary && !isLoading && recommendationStatus === "pending_approval" ? (
        <p className="mt-3 text-sm text-muted-foreground">No approval route has been started.</p>
      ) : null}
      </CardContent>
    </Card>
  );
}

function approvalOutcomeLabel(status: ApprovalSummary["status"]): string {
  if (status === "approved") return "Approved";
  if (status === "rejected") return "Rejected";
  if (status === "changes_requested") return "Changes requested";

  return status;
}

function getApprovalErrorMessage(error: unknown): string {
  const rawMessage = getRawErrorMessage(error);
  if (rawMessage) return rawMessage;

  return getApiErrorMessage(error);
}

function getRawErrorMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const message = (error as { error?: { message?: unknown } }).error?.message;
    return typeof message === "string" ? message : null;
  }

  return null;
}

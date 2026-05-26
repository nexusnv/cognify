"use client";

import Link from "next/link";
import { Button } from "@cognify/ui";
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
    <section id="approval" className="rounded-md border p-4" aria-label="Approval route">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">Approval route</h2>
          <p className="text-sm text-muted-foreground">Shared approval workflow for this award recommendation.</p>
        </div>
        {recommendationStatus === "pending_approval" && canRoute ? (
          <Button disabled={isRouting} onClick={onRoute}>
            Route for approval
          </Button>
        ) : null}
      </div>

      {error ? (
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {getApprovalErrorMessage(error)}
        </div>
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
        <Link
          className="mt-4 inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent"
          href={`/approvals/tasks/${summary.currentUserTaskId}`}
        >
          Open approval task
        </Link>
      ) : null}

      {summary?.status && summary.status !== "active" ? (
        <div className="mt-4 rounded-md border p-3 text-sm">
          <div className="font-medium">{approvalOutcomeLabel(summary.status)}</div>
          {completedDecision?.reason ? <p className="mt-1 text-muted-foreground">{completedDecision.reason}</p> : null}
        </div>
      ) : null}

      {!summary && !isLoading && recommendationStatus === "pending_approval" ? (
        <p className="mt-3 text-sm text-muted-foreground">No approval route has been started.</p>
      ) : null}
    </section>
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

"use client";

import type { ApprovalSlaSummary as ApprovalSlaSummaryData } from "@cognify/api-client/schemas";

export function ApprovalSlaSummary({
  summary,
  state = "idle",
}: {
  summary?: ApprovalSlaSummaryData | null;
  state?: "idle" | "loading" | "error";
}) {
  if (state === "loading") {
    return <p className="rounded-md border p-4 text-sm text-muted-foreground">Loading approval SLA summary</p>;
  }

  if (state === "error") {
    return <p className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Approval SLA summary could not be loaded.</p>;
  }

  if (!summary) {
    return null;
  }

  return (
    <section className="space-y-3" aria-label="Approval SLA summary">
      <div className="grid gap-3 md:grid-cols-5">
        <Metric label="Assigned" value={summary.assigned} />
        <Metric label="Due soon" value={summary.dueSoon} />
        <Metric label="Overdue" value={summary.overdue} />
        <Metric label="Escalated" value={summary.escalated} />
        <Metric label="Average age" value={`${summary.averageAgeMinutes} min`} />
      </div>
      {summary.oldestPendingApproval ? (
        <p className="text-sm text-muted-foreground">
          Oldest pending: {summary.oldestPendingApproval.title} · {summary.oldestPendingApproval.ageMinutes} min
        </p>
      ) : null}
    </section>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-md border p-3">
      <dt className="text-xs uppercase text-muted-foreground">{label}</dt>
      <dd className="mt-1 text-lg font-semibold">{value}</dd>
    </div>
  );
}

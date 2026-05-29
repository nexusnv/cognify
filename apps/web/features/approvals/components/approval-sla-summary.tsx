"use client";

import type { ApprovalSlaSummary as ApprovalSlaSummaryData } from "@cognify/api-client/schemas";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

export function ApprovalSlaSummary({
  summary,
  state = "idle",
}: {
  summary?: ApprovalSlaSummaryData | null;
  state?: "idle" | "loading" | "error";
}) {
  if (state === "loading") {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">
          Loading approval SLA summary
        </CardContent>
      </Card>
    );
  }

  if (state === "error") {
    return (
      <Card className="border-destructive/30 bg-destructive/5">
        <CardContent className="p-4 text-sm text-destructive">
          Approval SLA summary could not be loaded.
        </CardContent>
      </Card>
    );
  }

  if (!summary) {
    return null;
  }

  return (
    <Card role="region" aria-label="Approval SLA summary">
      <CardHeader>
        <CardTitle className="text-base">Approval SLA summary</CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
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
      </CardContent>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <Card>
      <CardContent className="space-y-1 p-3">
        <dt className="text-xs uppercase text-muted-foreground">{label}</dt>
        <dd className="text-lg font-semibold">{value}</dd>
      </CardContent>
    </Card>
  );
}

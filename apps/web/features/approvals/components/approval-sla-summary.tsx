"use client";

import {
  Alert,
  AlertDescription,
  AlertTitle,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Skeleton,
} from "@cognify/ui";
import type { ApprovalSlaSummary as ApprovalSlaSummaryData } from "@cognify/api-client/schemas";

export function ApprovalSlaSummary({
  summary,
  state = "idle",
}: {
  summary?: ApprovalSlaSummaryData | null;
  state?: "idle" | "loading" | "error";
}) {
  if (state === "loading") {
    return (
      <Card aria-label="Approval SLA summary">
        <CardHeader>
          <CardTitle>Approval SLA summary</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-5">
          {Array.from({ length: 5 }).map((_, index) => (
            <div key={index} className="space-y-2 rounded-lg border p-3">
              <Skeleton className="h-3 w-16" />
              <Skeleton className="h-6 w-12" />
            </div>
          ))}
        </CardContent>
      </Card>
    );
  }

  if (state === "error") {
    return (
      <Alert variant="destructive">
        <AlertTitle>Approval SLA summary unavailable</AlertTitle>
        <AlertDescription>Approval SLA summary could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  if (!summary) {
    return null;
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Approval SLA summary</CardTitle>
      </CardHeader>
      <CardContent>
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
      </CardContent>
    </Card>
  );
}

function Metric({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-lg border p-3">
      <dt className="text-xs uppercase text-muted-foreground">{label}</dt>
      <dd className="mt-1 text-lg font-semibold">{value}</dd>
    </div>
  );
}

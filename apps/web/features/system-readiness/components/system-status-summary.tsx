import type { SystemStatus } from "@cognify/api-client/schemas";
import { Card, CardContent, Progress } from "@cognify/ui";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemStatusSummary({ status }: { status: SystemStatus }) {
  const healthyChecks = status.checks.filter((check) => check.status === "ok").length;
  const readinessPercent =
    status.checks.length > 0 ? Math.round((healthyChecks / status.checks.length) * 100) : 0;

  return (
    <Card>
      <CardContent className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div className="space-y-1">
          <h1 className="text-2xl font-semibold tracking-normal">System Status</h1>
          <p className="text-sm text-muted-foreground">
            {status.service} · {status.environment} · v{status.version}
          </p>
          <p className="text-xs text-muted-foreground">
            Last checked <time dateTime={status.checkedAt}>{status.checkedAt}</time>
          </p>
          <div className="max-w-sm space-y-1 pt-2">
            <Progress value={readinessPercent} aria-label="Healthy checks" />
            <p className="text-xs text-muted-foreground">
              {healthyChecks} of {status.checks.length} checks healthy
            </p>
          </div>
        </div>
        <SystemStatusBadge status={status.status} />
      </CardContent>
    </Card>
  );
}

import type { SystemStatus } from "@cognify/api-client/schemas";
import { Badge, Card, CardContent, CardDescription, CardHeader, CardTitle, Progress } from "@cognify/ui";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemStatusSummary({ status }: { status: SystemStatus }) {
  return (
    <Card>
      <CardHeader className="gap-3">
        <div className="flex items-start justify-between gap-3">
          <div className="space-y-1">
            <h1 className="text-2xl font-semibold">System Status</h1>
            <CardDescription>
              {status.service} · {status.environment} · v{status.version}
            </CardDescription>
          </div>
          <SystemStatusBadge status={status.status} />
        </div>
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
            <span>Last checked</span>
            <time dateTime={status.checkedAt}>{status.checkedAt}</time>
          </div>
          <Progress value={status.status === "ok" ? 100 : status.status === "warning" ? 66 : 33} />
        </div>
      </CardHeader>
      <CardContent className="pt-0">
        <Badge variant="secondary">Operational readiness</Badge>
      </CardContent>
    </Card>
  );
}

import type { SystemStatus } from "@cognify/api-client/schemas";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemStatusSummary({ status }: { status: SystemStatus }) {
  return (
    <section className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold tracking-normal">System Status</h1>
        <p className="text-sm text-muted-foreground">
          {status.service} · {status.environment} · v{status.version}
        </p>
        <p className="text-xs text-muted-foreground">
          Last checked <time dateTime={status.checkedAt}>{status.checkedAt}</time>
        </p>
      </div>
      <SystemStatusBadge status={status.status} />
    </section>
  );
}

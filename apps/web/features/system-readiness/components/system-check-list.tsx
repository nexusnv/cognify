import type { SystemStatusCheck } from "@cognify/api-client/schemas";
import { SystemStatusBadge } from "./system-status-badge";

export function SystemCheckList({ checks }: { checks: SystemStatusCheck[] }) {
  return (
    <section aria-labelledby="system-checks-heading" className="space-y-3">
      <h2 id="system-checks-heading" className="text-base font-semibold">
        Checks
      </h2>
      <ul className="divide-y rounded-md border">
        {checks.map((check) => (
          <li key={check.id} className="grid gap-3 p-4 md:grid-cols-[12rem_minmax(0,1fr)_auto] md:items-start">
            <div className="space-y-1">
              <div className="font-medium">{check.label}</div>
              <div className="text-xs text-muted-foreground">{check.id}</div>
            </div>
            <div className="space-y-1 text-sm text-muted-foreground">
              <div>{check.message}</div>
              {check.remediation ? <div>{check.remediation}</div> : null}
            </div>
            <SystemStatusBadge status={check.status} />
          </li>
        ))}
      </ul>
    </section>
  );
}

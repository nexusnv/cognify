import { CheckCircle2, FileClock, Send } from "lucide-react";
import type { AuditEvent } from "@cognify/api-client/schemas";

export function RequisitionActivityTimeline({ events }: { events: AuditEvent[] }) {
  if (events.length === 0) {
    return <p className="text-sm text-muted-foreground">No activity has been recorded yet.</p>;
  }

  return (
    <ol className="space-y-3">
      {events.map((event) => {
        const Icon =
          event.action === "requisition.submitted"
            ? Send
            : event.action === "requisition.updated"
              ? CheckCircle2
              : FileClock;

        return (
          <li key={event.id} className="flex gap-3 rounded-md border p-3">
            <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md border bg-card">
              <Icon className="h-4 w-4" aria-hidden="true" />
            </span>
            <div>
              <p className="text-sm font-medium">{event.message}</p>
              <p className="text-sm text-muted-foreground">
                {event.actor?.name ?? "System"} · {new Date(event.occurredAt).toLocaleString()}
              </p>
            </div>
          </li>
        );
      })}
    </ol>
  );
}

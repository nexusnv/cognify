import Link from "next/link";
import { Badge } from "@cognify/ui";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";

export function ProcurementCalendarEventDetail({
  event,
}: {
  event: ProcurementCalendarEventViewModel | null;
}) {
  if (!event) {
    return (
      <aside aria-label="Event detail" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Event detail</h2>
        <p className="mt-2 text-sm text-muted-foreground">Select an event to inspect its source metadata.</p>
      </aside>
    );
  }

  return (
    <aside aria-label="Event detail" className="space-y-4 rounded-md border p-4">
      <div className="space-y-2 border-b pb-3">
        <h2 className="text-base font-semibold">{event.title}</h2>
        <div className="flex flex-wrap gap-2">
          <Badge variant="secondary">{event.statusLabel}</Badge>
          <Badge variant="outline">{event.sourceTypeLabel}</Badge>
        </div>
        {event.description ? <p className="text-sm text-muted-foreground">{event.description}</p> : null}
      </div>

      <dl className="grid gap-3 text-sm">
        <div className="grid gap-1">
          <dt className="text-muted-foreground">Date</dt>
          <dd>{event.dateKey}</dd>
        </div>
        <div className="grid gap-1">
          <dt className="text-muted-foreground">Time</dt>
          <dd>{event.timeLabel}</dd>
        </div>
        <div className="grid gap-1">
          <dt className="text-muted-foreground">Source</dt>
          <dd>{event.sourceTypeLabel}</dd>
        </div>
        <div className="grid gap-1">
          <dt className="text-muted-foreground">Source record ID</dt>
          <dd>{event.sourceId}</dd>
        </div>
        <div className="grid gap-1">
          <dt className="text-muted-foreground">Source label</dt>
          <dd>{event.sourceLabel}</dd>
        </div>
        {event.record ? (
          <>
            <div className="grid gap-1">
              <dt className="text-muted-foreground">Record type</dt>
              <dd>{event.record.type}</dd>
            </div>
            <div className="grid gap-1">
              <dt className="text-muted-foreground">Record label</dt>
              <dd>{event.record.label}</dd>
            </div>
          </>
        ) : null}
      </dl>

      {event.record ? (
        <Link
          href={event.record.href}
          className="inline-flex min-h-11 items-center rounded-md border px-4 text-sm font-medium"
        >
          Open source
        </Link>
      ) : null}
    </aside>
  );
}

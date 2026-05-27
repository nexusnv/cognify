"use client";

import { groupEventsByDate } from "../utils/procurement-calendar-date";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";

type ProcurementCalendarMonthViewProps = {
  events: ProcurementCalendarEventViewModel[];
  selectedEventId: string | null;
  onSelectEvent: (eventId: string) => void;
};

export function ProcurementCalendarMonthView({
  events,
  selectedEventId,
  onSelectEvent,
}: ProcurementCalendarMonthViewProps) {
  const groups = groupEventsByDate(events);

  return (
    <div className="space-y-4">
      {groups.map((group) => (
        <section key={group.dateKey} aria-label={group.dateKey} className="space-y-2 rounded-md border p-3">
          <header className="border-b pb-2">
            <h3 className="text-sm font-semibold">{group.heading}</h3>
          </header>
          <div className="space-y-2">
            {group.events.map((event) => (
              <button
                key={event.id}
                type="button"
                aria-pressed={selectedEventId === event.id}
                className={`flex w-full flex-col items-start gap-1 rounded-md border px-3 py-2 text-left ${
                  selectedEventId === event.id ? "border-foreground bg-accent" : ""
                }`}
                onClick={() => onSelectEvent(event.id)}
              >
                <span className="text-sm font-medium">{event.title}</span>
                <span className="text-xs text-muted-foreground">
                  {event.timeLabel} · {event.sourceTypeLabel}
                </span>
              </button>
            ))}
          </div>
        </section>
      ))}
    </div>
  );
}

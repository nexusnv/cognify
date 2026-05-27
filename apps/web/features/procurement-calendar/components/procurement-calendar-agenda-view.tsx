"use client";

import { sortCalendarEvents } from "../utils/procurement-calendar-date";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";

type ProcurementCalendarAgendaViewProps = {
  events: ProcurementCalendarEventViewModel[];
  selectedEventId: string | null;
  onSelectEvent: (eventId: string) => void;
};

export function ProcurementCalendarAgendaView({
  events,
  selectedEventId,
  onSelectEvent,
}: ProcurementCalendarAgendaViewProps) {
  const sortedEvents = sortCalendarEvents(events);

  return (
    <div className="space-y-2">
      {sortedEvents.map((event) => (
        <button
          key={event.id}
          type="button"
          data-testid="calendar-agenda-item"
          aria-pressed={selectedEventId === event.id}
          className={`flex w-full items-start justify-between gap-3 rounded-md border px-3 py-3 text-left ${
            selectedEventId === event.id ? "border-foreground bg-accent" : ""
          }`}
          onClick={() => onSelectEvent(event.id)}
        >
          <span className="min-w-0">
            <span className="block text-sm font-medium">{event.title}</span>
            <span className="mt-1 block text-xs text-muted-foreground">
              {event.dateKey} · {event.timeLabel}
            </span>
          </span>
          <span className="shrink-0 text-xs text-muted-foreground">{event.sourceTypeLabel}</span>
        </button>
      ))}
    </div>
  );
}

"use client";

import { formatDateHeading, getWeekDateKeys } from "../utils/procurement-calendar-date";
import type { ProcurementCalendarEventViewModel } from "../types/procurement-calendar-view-model";

type ProcurementCalendarWeekViewProps = {
  from: string;
  events: ProcurementCalendarEventViewModel[];
  selectedEventId: string | null;
  onSelectEvent: (eventId: string) => void;
};

export function ProcurementCalendarWeekView({
  from,
  events,
  selectedEventId,
  onSelectEvent,
}: ProcurementCalendarWeekViewProps) {
  const weekDays = getWeekDateKeys(from);

  return (
    <div className="grid gap-3 xl:grid-cols-2">
      {weekDays.map((dateKey) => {
        const dayEvents = events.filter((event) => event.dateKey === dateKey);

        return (
          <section key={dateKey} aria-label={dateKey} className="space-y-2 rounded-md border p-3">
            <header className="border-b pb-2">
              <h3 className="text-sm font-semibold">{formatDateHeading(dateKey)}</h3>
            </header>
            {dayEvents.length === 0 ? (
              <p className="text-sm text-muted-foreground">No events scheduled.</p>
            ) : (
              <div className="space-y-2">
                {dayEvents.map((event) => (
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
                      {event.timeLabel} · {event.statusLabel}
                    </span>
                  </button>
                ))}
              </div>
            )}
          </section>
        );
      })}
    </div>
  );
}

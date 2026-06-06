"use client";

import { Badge, Button, Card, CardContent } from "@cognify/ui";
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
    <Card>
      <CardContent className="space-y-2 pt-6">
        {sortedEvents.map((event) => (
          <Button
            key={event.id}
            type="button"
            data-testid="calendar-agenda-item"
            variant={selectedEventId === event.id ? "default" : "outline"}
            className="h-auto w-full justify-between gap-3 px-3 py-3 text-left"
            onClick={() => onSelectEvent(event.id)}
          >
            <span className="min-w-0 flex-1">
              <span className="block text-sm font-medium">{event.title}</span>
              <span className="mt-1 block text-xs text-muted-foreground">
                {event.dateKey} · {event.timeLabel}
              </span>
            </span>
            <Badge variant={selectedEventId === event.id ? "secondary" : "outline"} className="shrink-0">
              {event.sourceTypeLabel}
            </Badge>
          </Button>
        ))}
      </CardContent>
    </Card>
  );
}

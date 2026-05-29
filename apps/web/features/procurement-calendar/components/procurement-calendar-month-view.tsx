"use client";

import { Badge, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
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
        <Card key={group.dateKey} role="region" aria-label={group.dateKey}>
          <CardHeader className="border-b pb-4">
            <CardTitle className="text-sm">{group.heading}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 pt-4">
            {group.events.map((event) => (
              <Button
                key={event.id}
                type="button"
                variant={selectedEventId === event.id ? "default" : "outline"}
                className="h-auto w-full justify-start px-3 py-2 text-left"
                onClick={() => onSelectEvent(event.id)}
              >
                <span className="flex min-w-0 flex-1 flex-col items-start gap-1">
                  <span className="text-sm font-medium">{event.title}</span>
                  <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    <Badge variant={selectedEventId === event.id ? "secondary" : "outline"}>
                      {event.timeLabel}
                    </Badge>
                    <Badge variant="outline">{event.statusLabel}</Badge>
                  </span>
                </span>
              </Button>
            ))}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

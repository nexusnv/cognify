// shadcn-factory-exception: procurement calendar requires dense month/week event grid not provided by shadcn Calendar; primitives=Card,Badge,Button,Dialog,Popover; routes=calendar

"use client";

import { Badge, Button, Card, CardContent, CardHeader, CardTitle, ScrollArea } from "@cognify/ui";
import type { ProcurementCalendarEventViewModel } from "@/features/procurement-calendar/types/procurement-calendar-view-model";
import {
  formatDateHeading,
  getWeekDateKeys,
  groupEventsByDate,
  sortCalendarEvents,
} from "@/features/procurement-calendar/utils/procurement-calendar-date";

type ProcurementCalendarGridProps = {
  view: "month" | "week" | "agenda";
  from: string;
  events: ProcurementCalendarEventViewModel[];
  selectedEventId: string | null;
  onSelectEvent: (eventId: string) => void;
};

export function ProcurementCalendarGrid({
  view,
  from,
  events,
  selectedEventId,
  onSelectEvent,
}: ProcurementCalendarGridProps) {
  if (view === "agenda") {
    return (
      <ScrollArea className="h-[34rem] pr-3">
        <div className="space-y-2">
          {sortCalendarEvents(events).map((event) => (
            <CalendarEventButton
              key={event.id}
              event={event}
              selected={selectedEventId === event.id}
              onSelectEvent={onSelectEvent}
              testId="calendar-agenda-item"
              trailing={event.sourceTypeLabel}
              meta={`${event.dateKey} - ${event.timeLabel}`}
            />
          ))}
        </div>
      </ScrollArea>
    );
  }

  if (view === "week") {
    return (
      <div className="grid gap-3 xl:grid-cols-2">
        {getWeekDateKeys(from).map((dateKey) => {
          const dayEvents = events.filter((event) => event.dateKey === dateKey);

          return (
            <section key={dateKey} aria-label={dateKey}>
              <Card>
                <CardHeader className="border-b">
                  <CardTitle>{formatDateHeading(dateKey)}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                  {dayEvents.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No events scheduled.</p>
                  ) : (
                    dayEvents.map((event) => (
                      <CalendarEventButton
                        key={event.id}
                        event={event}
                        selected={selectedEventId === event.id}
                        onSelectEvent={onSelectEvent}
                        meta={`${event.timeLabel} - ${event.statusLabel}`}
                      />
                    ))
                  )}
                </CardContent>
              </Card>
            </section>
          );
        })}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {groupEventsByDate(events).map((group) => (
        <section key={group.dateKey} aria-label={group.dateKey}>
          <Card>
            <CardHeader className="border-b">
              <CardTitle>{group.heading}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              {group.events.map((event) => (
                <CalendarEventButton
                  key={event.id}
                  event={event}
                  selected={selectedEventId === event.id}
                  onSelectEvent={onSelectEvent}
                  meta={`${event.timeLabel} - ${event.sourceTypeLabel}`}
                />
              ))}
            </CardContent>
          </Card>
        </section>
      ))}
    </div>
  );
}

function CalendarEventButton({
  event,
  selected,
  onSelectEvent,
  testId,
  trailing,
  meta,
}: {
  event: ProcurementCalendarEventViewModel;
  selected: boolean;
  onSelectEvent: (eventId: string) => void;
  testId?: string;
  trailing?: string;
  meta: string;
}) {
  return (
    <Button
      type="button"
      variant={selected ? "secondary" : "outline"}
      size="lg"
      data-testid={testId}
      aria-pressed={selected}
      className="h-auto min-h-12 w-full justify-between gap-3 whitespace-normal px-3 py-2 text-left"
      onClick={() => onSelectEvent(event.id)}
    >
      <span className="min-w-0">
        <span className="block text-sm font-medium">{event.title}</span>
        <span className="mt-1 block text-xs text-muted-foreground">{meta}</span>
      </span>
      {trailing ? (
        <Badge variant="outline" className="shrink-0">
          {trailing}
        </Badge>
      ) : null}
    </Button>
  );
}

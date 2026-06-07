"use client";

import { ProcurementCalendarGrid } from "@/components/ui/graph/procurement-calendar-grid";
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
  return (
    <ProcurementCalendarGrid
      view="month"
      from={events[0]?.dateKey ?? ""}
      events={events}
      selectedEventId={selectedEventId}
      onSelectEvent={onSelectEvent}
    />
  );
}

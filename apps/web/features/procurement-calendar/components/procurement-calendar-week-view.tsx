"use client";

import { ProcurementCalendarGrid } from "@/components/ui/graph/procurement-calendar-grid";
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
  return (
    <ProcurementCalendarGrid
      view="week"
      from={from}
      events={events}
      selectedEventId={selectedEventId}
      onSelectEvent={onSelectEvent}
    />
  );
}

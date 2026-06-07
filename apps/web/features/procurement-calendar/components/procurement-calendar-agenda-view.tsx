"use client";

import { ProcurementCalendarGrid } from "@/components/ui/graph/procurement-calendar-grid";
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
  return (
    <ProcurementCalendarGrid
      view="agenda"
      from={events[0]?.dateKey ?? ""}
      events={events}
      selectedEventId={selectedEventId}
      onSelectEvent={onSelectEvent}
    />
  );
}

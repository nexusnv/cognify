"use client";

import { useMemo, useState } from "react";
import type { ReactNode } from "react";
import type {
  ProcurementCalendarEventStatus,
  ProcurementCalendarSourceType,
} from "@cognify/api-client/schemas";
import { Button } from "@cognify/ui";
import { useProcurementCalendarEvents } from "../hooks/use-procurement-calendar-events";
import { ProcurementCalendarAgendaView } from "../components/procurement-calendar-agenda-view";
import { ProcurementCalendarEventDetail } from "../components/procurement-calendar-event-detail";
import { ProcurementCalendarFilters } from "../components/procurement-calendar-filters";
import { ProcurementCalendarMonthView } from "../components/procurement-calendar-month-view";
import { ProcurementCalendarSummaryStrip } from "../components/procurement-calendar-summary";
import { ProcurementCalendarWeekView } from "../components/procurement-calendar-week-view";
import {
  getProcurementCalendarSourceLabel,
  getProcurementCalendarStatusLabel,
  type ProcurementCalendarEventViewModel,
  type ProcurementCalendarView,
} from "../types/procurement-calendar-view-model";
import {
  formatDateRangeSummary,
  formatEventTimeRange,
  getDefaultCalendarRange,
  getEventDateKey,
} from "../utils/procurement-calendar-date";

export function ProcurementCalendarPage() {
  const [view, setView] = useState<ProcurementCalendarView>("month");
  const initialRange = useMemo(() => getDefaultCalendarRange("month"), []);
  const [from, setFrom] = useState(initialRange.from);
  const [to, setTo] = useState(initialRange.to);
  const [search, setSearch] = useState("");
  const [selectedSourceTypes, setSelectedSourceTypes] = useState<ProcurementCalendarSourceType[]>([]);
  const [selectedStatuses, setSelectedStatuses] = useState<ProcurementCalendarEventStatus[]>([]);
  const [selectedEventId, setSelectedEventId] = useState<string | null>(null);

  const query = useMemo(
    () => ({
      from,
      to,
      view,
      q: search.trim() || undefined,
      sourceTypes: selectedSourceTypes.length > 0 ? selectedSourceTypes : undefined,
      statuses: selectedStatuses.length > 0 ? selectedStatuses : undefined,
      limit: view === "agenda" ? 200 : undefined,
    }),
    [from, search, selectedSourceTypes, selectedStatuses, to, view],
  );

  const calendarQuery = useProcurementCalendarEvents(query);
  const calendar = calendarQuery.data;
  const availableSources = calendar?.availableSources ?? [];

  const events = useMemo<ProcurementCalendarEventViewModel[]>(() => {
    if (!calendar) return [];

    return calendar.events.map((event) => ({
      ...event,
      dateKey: getEventDateKey(event),
      sourceTypeLabel: getProcurementCalendarSourceLabel(event.sourceType, calendar.availableSources),
      statusLabel: getProcurementCalendarStatusLabel(event.status),
      timeLabel: formatEventTimeRange(event),
    }));
  }, [calendar]);

  const selectedEvent = events.find((event) => event.id === selectedEventId) ?? null;
  const visibleSelectedEventId = selectedEvent?.id ?? null;
  const isFiltered = Boolean(search.trim() || selectedSourceTypes.length > 0 || selectedStatuses.length > 0);
  const rangeLabel = formatDateRangeSummary(from, to);

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-2 border-b pb-4 md:flex-row md:items-end md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Calendar</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Operational procurement dates across RFQs, approvals, requisitions, and handoffs.
          </p>
        </div>
        <p className="text-sm text-muted-foreground">{rangeLabel}</p>
      </div>

      <ProcurementCalendarFilters
        from={from}
        onFromChange={setFrom}
        to={to}
        onToChange={setTo}
        onToday={() => {
          const nextRange = getDefaultCalendarRange(view);
          setFrom(nextRange.from);
          setTo(nextRange.to);
        }}
        view={view}
        onViewChange={(nextView) => {
          setView(nextView);
          if (nextView === "week") {
            const nextRange = getDefaultCalendarRange("week");
            setFrom(nextRange.from);
            setTo(nextRange.to);
          }
        }}
        search={search}
        onSearchChange={setSearch}
        selectedSourceTypes={selectedSourceTypes}
        onSourceTypeToggle={(value) => setSelectedSourceTypes((current) => toggleItem(current, value))}
        selectedStatuses={selectedStatuses}
        onStatusToggle={(value) => setSelectedStatuses((current) => toggleItem(current, value))}
        availableSources={availableSources}
        onClearFilters={() => {
          setSearch("");
          setSelectedSourceTypes([]);
          setSelectedStatuses([]);
        }}
      />

      {calendarQuery.isLoading ? <StatePanel role="status" title="Loading calendar events." /> : null}
      {calendarQuery.isError ? (
        <StatePanel
          title="Unable to load calendar events."
          action={
            <Button variant="outline" onClick={() => calendarQuery.refetch()}>
              Retry
            </Button>
          }
        />
      ) : null}

      {calendar && !calendarQuery.isLoading && !calendarQuery.isError ? (
        <>
          <ProcurementCalendarSummaryStrip summary={calendar.summary} />

          {events.length === 0 ? (
            <StatePanel
              title={isFiltered ? "No events match the current filters." : "No calendar events in this range."}
              message={
                isFiltered
                  ? "Adjust source, status, or search terms to widen the result set."
                  : "Try a different date range or switch views."
              }
            />
          ) : (
            <div className="grid gap-4 xl:grid-cols-[minmax(0,2fr)_22rem]">
              <section aria-label={`Calendar ${view} view`} className="space-y-3">
                <header className="flex items-center justify-between gap-3">
                  <h2 className="text-base font-semibold">
                    {view === "month" ? "Month view" : view === "week" ? "Week view" : "Agenda view"}
                  </h2>
                  <p className="text-xs text-muted-foreground">{events.length} visible events</p>
                </header>

                {view === "month" ? (
                  <ProcurementCalendarMonthView
                    events={events}
                    selectedEventId={visibleSelectedEventId}
                    onSelectEvent={setSelectedEventId}
                  />
                ) : null}

                {view === "week" ? (
                  <ProcurementCalendarWeekView
                    from={from}
                    events={events}
                    selectedEventId={visibleSelectedEventId}
                    onSelectEvent={setSelectedEventId}
                  />
                ) : null}

                {view === "agenda" ? (
                  <ProcurementCalendarAgendaView
                    events={events}
                    selectedEventId={visibleSelectedEventId}
                    onSelectEvent={setSelectedEventId}
                  />
                ) : null}
              </section>

              <ProcurementCalendarEventDetail event={selectedEvent} />
            </div>
          )}
        </>
      ) : null}
    </section>
  );
}

function toggleItem<TValue extends string>(items: TValue[], value: TValue) {
  return items.includes(value) ? items.filter((item) => item !== value) : [...items, value];
}

function StatePanel({
  title,
  message,
  action,
  role,
}: {
  title: string;
  message?: string;
  action?: ReactNode;
  role?: "status";
}) {
  return (
    <div role={role} className="space-y-2 rounded-md border p-4 text-sm">
      <p className="font-medium">{title}</p>
      {message ? <p className="text-muted-foreground">{message}</p> : null}
      {action ? <div>{action}</div> : null}
    </div>
  );
}

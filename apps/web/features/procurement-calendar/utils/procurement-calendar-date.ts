import type { ProcurementCalendarEvent } from "@cognify/api-client/schemas";
import type { ProcurementCalendarEventViewModel, ProcurementCalendarView } from "../types/procurement-calendar-view-model";

const dateFormatter = new Intl.DateTimeFormat("en-CA", {
  year: "numeric",
  month: "2-digit",
  day: "2-digit",
});

const timeFormatter = new Intl.DateTimeFormat("en-US", {
  hour: "numeric",
  minute: "2-digit",
});

export function getTodayDateKey() {
  return toDateKey(new Date());
}

export function toDateKey(date: Date) {
  const parts = dateFormatter.formatToParts(date);
  const year = parts.find((part) => part.type === "year")?.value ?? "0000";
  const month = parts.find((part) => part.type === "month")?.value ?? "01";
  const day = parts.find((part) => part.type === "day")?.value ?? "01";

  return `${year}-${month}-${day}`;
}

export function parseDateKey(value: string) {
  return new Date(`${value}T00:00:00.000Z`);
}

export function addDays(dateKey: string, amount: number) {
  const next = parseDateKey(dateKey);
  next.setUTCDate(next.getUTCDate() + amount);
  return toDateKey(next);
}

export function getDefaultCalendarRange(view: ProcurementCalendarView) {
  const from = getTodayDateKey();
  const to = view === "week" ? addDays(from, 6) : addDays(from, 30);

  return { from, to };
}

export function getEventDateKey(event: ProcurementCalendarEvent) {
  return event.startsAt.slice(0, 10);
}

export function formatDateHeading(dateKey: string) {
  const date = parseDateKey(dateKey);
  return `${dateKey} · ${date.toLocaleDateString("en-US", { weekday: "short" })}`;
}

export function formatDateRangeSummary(from: string, to: string) {
  return `${from} to ${to}`;
}

export function formatEventTimeRange(event: ProcurementCalendarEvent) {
  if (event.allDay) return "All day";

  const startsAt = new Date(event.startsAt);
  const endsAt = event.endsAt ? new Date(event.endsAt) : null;
  const startLabel = timeFormatter.format(startsAt);

  if (!endsAt) return startLabel;

  return `${startLabel} - ${timeFormatter.format(endsAt)}`;
}

export function sortCalendarEvents<TEvent extends ProcurementCalendarEvent | ProcurementCalendarEventViewModel>(
  events: TEvent[],
) {
  return [...events].sort((left, right) => {
    const startDifference = new Date(left.startsAt).getTime() - new Date(right.startsAt).getTime();
    if (startDifference !== 0) return startDifference;

    return left.title.localeCompare(right.title);
  });
}

export function groupEventsByDate<TEvent extends ProcurementCalendarEventViewModel>(events: TEvent[]) {
  const groups = new Map<string, TEvent[]>();

  for (const event of sortCalendarEvents(events)) {
    const existing = groups.get(event.dateKey);
    if (existing) {
      existing.push(event);
      continue;
    }

    groups.set(event.dateKey, [event]);
  }

  return Array.from(groups.entries()).map(([dateKey, groupedEvents]) => ({
    dateKey,
    heading: formatDateHeading(dateKey),
    events: groupedEvents,
  }));
}

export function getWeekDateKeys(from: string) {
  return Array.from({ length: 7 }, (_, index) => addDays(from, index));
}

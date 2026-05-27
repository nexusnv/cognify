"use client";

import { Button } from "@cognify/ui";
import type {
  ProcurementCalendarAvailableSource,
  ProcurementCalendarEventSourceType,
  ProcurementCalendarEventStatus,
} from "@cognify/api-client/schemas";
import {
  procurementCalendarStatusOptions,
  procurementCalendarViewOptions,
  type ProcurementCalendarView,
} from "../types/procurement-calendar-view-model";

type ProcurementCalendarFiltersProps = {
  from: string;
  onFromChange: (value: string) => void;
  to: string;
  onToChange: (value: string) => void;
  onToday: () => void;
  view: ProcurementCalendarView;
  onViewChange: (value: ProcurementCalendarView) => void;
  search: string;
  onSearchChange: (value: string) => void;
  selectedSourceTypes: ProcurementCalendarEventSourceType[];
  onSourceTypeToggle: (value: ProcurementCalendarEventSourceType) => void;
  selectedStatuses: ProcurementCalendarEventStatus[];
  onStatusToggle: (value: ProcurementCalendarEventStatus) => void;
  availableSources: ProcurementCalendarAvailableSource[];
  onClearFilters: () => void;
};

export function ProcurementCalendarFilters({
  from,
  onFromChange,
  to,
  onToChange,
  onToday,
  view,
  onViewChange,
  search,
  onSearchChange,
  selectedSourceTypes,
  onSourceTypeToggle,
  selectedStatuses,
  onStatusToggle,
  availableSources,
  onClearFilters,
}: ProcurementCalendarFiltersProps) {
  return (
    <section className="space-y-4 rounded-md border p-4">
      <div className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
        <div className="grid gap-3 md:grid-cols-4">
          <label className="space-y-1.5 text-sm font-medium">
            From
            <input
              type="date"
              className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
              value={from}
              onChange={(event) => onFromChange(event.target.value)}
            />
          </label>
          <label className="space-y-1.5 text-sm font-medium">
            To
            <input
              type="date"
              className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
              value={to}
              onChange={(event) => onToChange(event.target.value)}
            />
          </label>
          <label className="space-y-1.5 text-sm font-medium md:col-span-2">
            Search
            <input
              className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
              value={search}
              onChange={(event) => onSearchChange(event.target.value)}
            />
          </label>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button variant="outline" onClick={onToday}>
            Today
          </Button>
          <Button variant="ghost" onClick={onClearFilters}>
            Clear filters
          </Button>
        </div>
      </div>

      <div className="flex flex-wrap gap-2" role="group" aria-label="Calendar view">
        {procurementCalendarViewOptions.map((option) => (
          <button
            key={option.value}
            type="button"
            aria-pressed={view === option.value}
            className={`min-h-11 rounded-md px-4 text-sm font-medium ${
              view === option.value ? "bg-foreground text-background" : "border bg-background"
            }`}
            onClick={() => onViewChange(option.value)}
          >
            {option.label}
          </button>
        ))}
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <fieldset className="space-y-2">
          <legend className="text-sm font-medium">Source</legend>
          <div className="flex flex-wrap gap-2">
            {availableSources.map((source) => {
              const checked = selectedSourceTypes.includes(source.sourceType);

              return (
                <label
                  key={source.sourceType}
                  className={`inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm ${
                    source.available ? "" : "cursor-not-allowed opacity-60"
                  }`}
                  title={source.reason ?? undefined}
                >
                  <input
                    type="checkbox"
                    className="h-4 w-4"
                    disabled={!source.available}
                    checked={checked}
                    onChange={() => onSourceTypeToggle(source.sourceType)}
                  />
                  <span>{source.label}</span>
                </label>
              );
            })}
          </div>
        </fieldset>

        <fieldset className="space-y-2">
          <legend className="text-sm font-medium">Status</legend>
          <div className="flex flex-wrap gap-2">
            {procurementCalendarStatusOptions.map((status) => (
              <label
                key={status.value}
                className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"
              >
                <input
                  type="checkbox"
                  className="h-4 w-4"
                  checked={selectedStatuses.includes(status.value)}
                  onChange={() => onStatusToggle(status.value)}
                />
                <span>{status.label}</span>
              </label>
            ))}
          </div>
        </fieldset>
      </div>
    </section>
  );
}

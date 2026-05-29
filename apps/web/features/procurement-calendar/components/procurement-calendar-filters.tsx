"use client";

import type { ReactNode } from "react";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Checkbox,
  Input,
  Popover,
  PopoverContent,
  PopoverTrigger,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@cognify/ui";
import type {
  ProcurementCalendarAvailableSource,
  ProcurementCalendarEventStatus,
  ProcurementCalendarSourceType,
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
  selectedSourceTypes: ProcurementCalendarSourceType[];
  onSourceTypeToggle: (value: ProcurementCalendarSourceType) => void;
  selectedStatuses: ProcurementCalendarEventStatus[];
  onStatusToggle: (value: ProcurementCalendarEventStatus) => void;
  availableSources: ProcurementCalendarAvailableSource[];
  onClearFilters: () => void;
  rangePreset: "month" | "week" | "custom";
  onRangePresetChange: (value: "month" | "week" | "custom") => void;
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
  rangePreset,
  onRangePresetChange,
}: ProcurementCalendarFiltersProps) {
  return (
    <Card>
      <CardHeader className="gap-2">
        <CardTitle className="text-base">Filters</CardTitle>
        <CardDescription>Refine the procurement calendar by date, source, and status.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_13rem]">
          <div className="grid gap-3 md:grid-cols-3">
            <label className="space-y-1.5 text-sm font-medium">
              From
              <Input
                type="date"
                className="min-h-11"
                value={from}
                onChange={(event) => {
                  onRangePresetChange("custom");
                  onFromChange(event.target.value);
                }}
              />
            </label>
            <label className="space-y-1.5 text-sm font-medium">
              To
              <Input
                type="date"
                className="min-h-11"
                value={to}
                onChange={(event) => {
                  onRangePresetChange("custom");
                  onToChange(event.target.value);
                }}
              />
            </label>
            <label className="space-y-1.5 text-sm font-medium">
              Search
              <Input
                value={search}
                onChange={(event) => {
                  onRangePresetChange("custom");
                  onSearchChange(event.target.value);
                }}
              />
            </label>
          </div>

          <div className="space-y-1.5 text-sm font-medium">
            Quick range
            <Select
              value={rangePreset}
              onValueChange={(value: "month" | "week" | "custom") => {
                onRangePresetChange(value);
                if (value === "month" || value === "week") {
                  onViewChange(value);
                }
                if (value === "custom") {
                  onRangePresetChange("custom");
                }
              }}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Choose range" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="month">This month</SelectItem>
                <SelectItem value="week">This week</SelectItem>
                <SelectItem value="custom">Custom range</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-wrap gap-2" role="group" aria-label="Calendar view">
            {procurementCalendarViewOptions.map((option) => (
              <Button
                key={option.value}
                type="button"
                variant={view === option.value ? "default" : "outline"}
                size="sm"
                onClick={() => onViewChange(option.value)}
              >
                {option.label}
              </Button>
            ))}
          </div>

          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={onToday}>
              Today
            </Button>
            <Button
              type="button"
              variant="ghost"
              onClick={() => {
                onClearFilters();
                onRangePresetChange("custom");
              }}
            >
              Clear filters
            </Button>
          </div>
        </div>

        <div className="grid gap-3 lg:grid-cols-2">
          <FilterPopover
            label="Source"
            count={selectedSourceTypes.length}
            contentClassName="w-96"
          >
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-medium">Source types</p>
                <Badge variant="secondary">{selectedSourceTypes.length}</Badge>
              </div>
              <div className="grid gap-2">
                {availableSources.map((source) => {
                  const checked = selectedSourceTypes.includes(source.sourceType);

                  return (
                    <label
                      key={source.sourceType}
                      className={`flex items-start gap-3 rounded-md bg-muted/30 p-3 text-sm ${
                        source.available ? "" : "cursor-not-allowed opacity-60"
                      }`}
                      title={source.reason ?? undefined}
                    >
                      <Checkbox
                        aria-label={source.label}
                        checked={checked}
                        disabled={!source.available}
                        onCheckedChange={() => onSourceTypeToggle(source.sourceType)}
                      />
                      <span className="grid gap-1">
                        <span className="font-medium">{source.label}</span>
                        {source.reason ? (
                          <span className="text-xs text-muted-foreground">{source.reason}</span>
                        ) : null}
                      </span>
                    </label>
                  );
                })}
              </div>
            </div>
          </FilterPopover>

          <FilterPopover
            label="Status"
            count={selectedStatuses.length}
            contentClassName="w-80"
          >
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <p className="text-sm font-medium">Status</p>
                <Badge variant="secondary">{selectedStatuses.length}</Badge>
              </div>
              <div className="grid gap-2">
                {procurementCalendarStatusOptions.map((status) => (
                  <label key={status.value} className="flex items-center gap-3 rounded-md bg-muted/30 p-3 text-sm">
                    <Checkbox
                      aria-label={status.label}
                      checked={selectedStatuses.includes(status.value)}
                      onCheckedChange={() => onStatusToggle(status.value)}
                    />
                    <span>{status.label}</span>
                  </label>
                ))}
              </div>
            </div>
          </FilterPopover>
        </div>
      </CardContent>
    </Card>
  );
}

function FilterPopover({
  label,
  count,
  contentClassName,
  children,
}: {
  label: string;
  count: number;
  contentClassName?: string;
  children: ReactNode;
}) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" className="justify-between" aria-label={`${label} ${count}`}>
          <span>{label}</span>
          <Badge variant={count > 0 ? "secondary" : "outline"}>{count}</Badge>
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className={contentClassName}>
        {children}
      </PopoverContent>
    </Popover>
  );
}

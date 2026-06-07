"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Checkbox,
  Input,
  Popover,
  PopoverContent,
  PopoverDescription,
  PopoverHeader,
  PopoverTitle,
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
  const activeFilterCount = selectedSourceTypes.length + selectedStatuses.length;

  return (
    <section>
      <Card>
        <CardHeader className="border-b">
          <CardTitle>Calendar controls</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
            <div className="grid gap-3 md:grid-cols-4">
              <label className="space-y-1.5 text-sm font-medium">
                From
                <Input
                  type="date"
                  className="min-h-10 text-base font-normal"
                  value={from}
                  onChange={(event) => onFromChange(event.target.value)}
                />
              </label>
              <label className="space-y-1.5 text-sm font-medium">
                To
                <Input
                  type="date"
                  className="min-h-10 text-base font-normal"
                  value={to}
                  onChange={(event) => onToChange(event.target.value)}
                />
              </label>
              <label className="space-y-1.5 text-sm font-medium md:col-span-2">
                Search
                <Input
                  className="min-h-10 text-base font-normal"
                  value={search}
                  onChange={(event) => onSearchChange(event.target.value)}
                />
              </label>
            </div>

            <div className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={onToday}>
                Today
              </Button>
              <Select
                value={view}
                onValueChange={(value) => onViewChange(value as ProcurementCalendarView)}
              >
                <SelectTrigger className="h-8 min-w-32">
                  <SelectValue aria-label="Calendar view" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="month">Month</SelectItem>
                  <SelectItem value="week">Week</SelectItem>
                  <SelectItem value="agenda">Agenda</SelectItem>
                </SelectContent>
              </Select>
              <Button variant="ghost" onClick={onClearFilters}>
                Clear filters
              </Button>
            </div>
          </div>

          <div className="flex flex-wrap gap-2" role="group" aria-label="Calendar view">
            {procurementCalendarViewOptions.map((option) => (
              <Button
                key={option.value}
                type="button"
                variant={view === option.value ? "default" : "outline"}
                aria-pressed={view === option.value}
                onClick={() => onViewChange(option.value)}
              >
                {option.label}
              </Button>
            ))}
          </div>

          <div className="flex flex-wrap gap-2">
            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline">
                  Source
                  {selectedSourceTypes.length > 0 ? ` (${selectedSourceTypes.length})` : ""}
                </Button>
              </PopoverTrigger>
              <PopoverContent align="start" className="w-80">
                <PopoverHeader>
                  <PopoverTitle>Source</PopoverTitle>
                  <PopoverDescription>
                    Filter by source availability and workflow type.
                  </PopoverDescription>
                </PopoverHeader>
                <div className="space-y-2">
                  {availableSources.map((source) => {
                    const checked = selectedSourceTypes.includes(source.sourceType);

                    return (
                      <label
                        key={source.sourceType}
                        className="flex min-h-9 items-start gap-2 rounded-md px-1.5 py-1 text-sm"
                      >
                        <Checkbox
                          disabled={!source.available}
                          checked={checked}
                          onCheckedChange={() => onSourceTypeToggle(source.sourceType)}
                          aria-label={source.label}
                        />
                        <span className={source.available ? "" : "cursor-not-allowed opacity-60"}>
                          <span className="block">{source.label}</span>
                          {!source.available && source.reason ? (
                            <span className="block text-xs text-muted-foreground">
                              {source.reason}
                            </span>
                          ) : null}
                        </span>
                      </label>
                    );
                  })}
                </div>
              </PopoverContent>
            </Popover>

            <Popover>
              <PopoverTrigger asChild>
                <Button variant="outline">
                  Status
                  {selectedStatuses.length > 0 ? ` (${selectedStatuses.length})` : ""}
                </Button>
              </PopoverTrigger>
              <PopoverContent align="start" className="w-72">
                <PopoverHeader>
                  <PopoverTitle>Status</PopoverTitle>
                  <PopoverDescription>Limit the calendar to operational states.</PopoverDescription>
                </PopoverHeader>
                <div className="space-y-2">
                  {procurementCalendarStatusOptions.map((status) => (
                    <label
                      key={status.value}
                      className="flex min-h-9 items-center gap-2 rounded-md px-1.5 py-1 text-sm"
                    >
                      <Checkbox
                        checked={selectedStatuses.includes(status.value)}
                        onCheckedChange={() => onStatusToggle(status.value)}
                        aria-label={status.label}
                      />
                      <span>{status.label}</span>
                    </label>
                  ))}
                </div>
              </PopoverContent>
            </Popover>

            {activeFilterCount > 0 ? (
              <Badge variant="secondary" className="min-h-8 rounded-md">
                {activeFilterCount} active filters
              </Badge>
            ) : null}
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
                      className="inline-flex min-h-9 items-center gap-2 rounded-md bg-muted/40 px-3 text-sm"
                    >
                      <Checkbox
                        disabled={!source.available}
                        checked={checked}
                        onCheckedChange={() => onSourceTypeToggle(source.sourceType)}
                        aria-label={source.label}
                      />
                      <span className={source.available ? "" : "cursor-not-allowed opacity-60"}>
                        {source.label}
                      </span>
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
                    className="inline-flex min-h-9 items-center gap-2 rounded-md bg-muted/40 px-3 text-sm"
                  >
                    <Checkbox
                      checked={selectedStatuses.includes(status.value)}
                      onCheckedChange={() => onStatusToggle(status.value)}
                      aria-label={status.label}
                    />
                    <span>{status.label}</span>
                  </label>
                ))}
              </div>
            </fieldset>
          </div>
        </CardContent>
      </Card>
    </section>
  );
}

"use client";

import { Button, Input, Label } from "@cognify/ui";
import { ApPaymentHandoffStatus } from "@cognify/api-client/schemas";

const statusOptions = [
  { value: ApPaymentHandoffStatus.draft, label: "Draft" },
  { value: ApPaymentHandoffStatus.ready, label: "Ready" },
  { value: ApPaymentHandoffStatus.exported, label: "Exported" },
  { value: ApPaymentHandoffStatus.scheduled, label: "Scheduled" },
  { value: ApPaymentHandoffStatus.paid, label: "Paid" },
  { value: ApPaymentHandoffStatus.failed, label: "Failed" },
  { value: ApPaymentHandoffStatus.voided, label: "Voided" },
  { value: ApPaymentHandoffStatus.cancelled, label: "Cancelled" },
];

interface PaymentStatusFiltersProps {
  selectedStatuses: string[];
  onChangeSelectedStatuses: (statuses: string[]) => void;
  searchQuery: string;
  onChangeSearchQuery: (query: string) => void;
  dateFrom: string;
  onChangeDateFrom: (date: string) => void;
  dateTo: string;
  onChangeDateTo: (date: string) => void;
}

export function PaymentStatusFilters({
  selectedStatuses,
  onChangeSelectedStatuses,
  searchQuery,
  onChangeSearchQuery,
  dateFrom,
  onChangeDateFrom,
  dateTo,
  onChangeDateTo,
}: PaymentStatusFiltersProps) {
  function toggleStatus(value: string) {
    if (selectedStatuses.includes(value)) {
      onChangeSelectedStatuses(selectedStatuses.filter((s) => s !== value));
    } else {
      onChangeSelectedStatuses([...selectedStatuses, value]);
    }
  }

  function clearFilters() {
    onChangeSelectedStatuses([]);
    onChangeSearchQuery("");
    onChangeDateFrom("");
    onChangeDateTo("");
  }

  const hasFilters =
    selectedStatuses.length > 0 || searchQuery.trim() || dateFrom || dateTo;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap gap-2">
        {statusOptions.map((option) => {
          const active = selectedStatuses.includes(option.value);
          return (
            <Button
              key={option.value}
              type="button"
              variant={active ? "default" : "outline"}
              size="sm"
              onClick={() => toggleStatus(option.value)}
              aria-pressed={active}
            >
              {option.label}
            </Button>
          );
        })}
      </div>

      <div className="grid gap-4 sm:grid-cols-[1fr_auto_auto_auto]">
        <div className="space-y-1.5">
          <Label htmlFor="handoff-search" className="text-xs">
            Search
          </Label>
          <Input
            id="handoff-search"
            placeholder="Search by handoff number or ID..."
            value={searchQuery}
            onChange={(e) => onChangeSearchQuery(e.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="date-from" className="text-xs">
            From
          </Label>
          <Input
            id="date-from"
            type="date"
            value={dateFrom}
            onChange={(e) => onChangeDateFrom(e.target.value)}
          />
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="date-to" className="text-xs">
            To
          </Label>
          <Input
            id="date-to"
            type="date"
            value={dateTo}
            onChange={(e) => onChangeDateTo(e.target.value)}
          />
        </div>
        <div className="flex items-end">
          {hasFilters && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={clearFilters}
            >
              Clear filters
            </Button>
          )}
        </div>
      </div>
    </div>
  );
}

"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useDataTableState } from "@/components/data-table/use-data-table-state";
import { useRequisitions } from "../hooks/use-requisitions";
import type { RequisitionQueuePreset, RequisitionStatus } from "../types/requisition-view-model";
import { RequisitionsTable } from "../tables/requisitions-table";

const queuePresets: Array<{ value: RequisitionQueuePreset; label: string }> = [
  { value: "all_visible", label: "All visible" },
  { value: "my_drafts", label: "My drafts" },
  { value: "submitted", label: "Submitted" },
  { value: "needs_my_correction", label: "Needs my correction" },
  { value: "buyer_review", label: "Buyer review" },
  { value: "stopped", label: "Stopped" },
];

export function RequisitionListPage() {
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [status, setStatus] = useState<RequisitionStatus | "">("");
  const [department, setDepartment] = useState("");
  const [amountMin, setAmountMin] = useState("");
  const [amountMax, setAmountMax] = useState("");
  const [updatedFrom, setUpdatedFrom] = useState("");
  const [updatedTo, setUpdatedTo] = useState("");
  const [queuePreset, setQueuePreset] = useState<RequisitionQueuePreset>("all_visible");
  const tableState = useDataTableState({ initialSort: { columnId: "title", direction: "asc" } });
  const query = useMemo(
    () => ({
      search: debouncedSearch,
      status,
      department,
      amountMin,
      amountMax,
      updatedFrom,
      updatedTo,
      queuePreset,
    }),
    [amountMax, amountMin, debouncedSearch, department, queuePreset, status, updatedFrom, updatedTo],
  );
  const { data, isLoading, isError, refetch } = useRequisitions(query);
  const sortedRequisitions = useMemo(() => {
    const requisitions = data?.data ?? [];
    const sort = tableState.sort;
    if (!sort) return requisitions;

    return [...requisitions].sort((first, second) => {
      const firstValue = getSortValue(first, sort.columnId);
      const secondValue = getSortValue(second, sort.columnId);
      const result = firstValue.localeCompare(secondValue);

      return sort.direction === "asc" ? result : -result;
    });
  }, [data?.data, tableState.sort]);
  const filtered = Boolean(
    search || status || department || amountMin || amountMax || updatedFrom || updatedTo,
  );
  const renderedState = isLoading
    ? "loading"
    : isError
      ? "error"
      : sortedRequisitions.length === 0
        ? "empty"
        : "idle";

  return (
    <section className="space-y-5">
      <div className="flex flex-col gap-3 border-b pb-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Requisitions</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Find drafts, submitted requests, and sourcing handoffs.
          </p>
        </div>
        <Link
          href="/requisitions/new"
          className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-foreground px-4 text-sm font-medium text-background"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          New requisition
        </Link>
      </div>

      <div className="flex flex-wrap gap-2">
        {queuePresets.map((preset) => (
          <button
            type="button"
            key={preset.value}
            className={`min-h-11 rounded-md px-4 text-sm font-medium ${
              queuePreset === preset.value
                ? "bg-foreground text-background"
                : "border bg-background"
            }`}
            aria-pressed={queuePreset === preset.value}
            onClick={() => setQueuePreset(preset.value)}
          >
            {preset.label}
          </button>
        ))}
      </div>

      <div className="grid gap-3 rounded-md border p-3 md:grid-cols-2 xl:grid-cols-4">
        <label className="space-y-1.5 text-sm font-medium">
          Search
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Status
          <select
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={status}
            onChange={(event) => setStatus(event.target.value as RequisitionStatus | "")}
          >
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
            <option value="changes_requested">Changes requested</option>
            <option value="withdrawn">Withdrawn</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Department
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            value={department}
            onChange={(event) => setDepartment(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Amount min
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            inputMode="decimal"
            value={amountMin}
            onChange={(event) => setAmountMin(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Amount max
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            inputMode="decimal"
            value={amountMax}
            onChange={(event) => setAmountMax(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Updated from
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            type="date"
            value={updatedFrom}
            onChange={(event) => setUpdatedFrom(event.target.value)}
          />
        </label>
        <label className="space-y-1.5 text-sm font-medium">
          Updated to
          <input
            className="min-h-11 w-full rounded-md border px-3 text-base font-normal"
            type="date"
            value={updatedTo}
            onChange={(event) => setUpdatedTo(event.target.value)}
          />
        </label>
        <button
          type="button"
          className="min-h-11 rounded-md border px-4 text-sm font-medium"
          onClick={() => {
            setSearch("");
            setStatus("");
            setDepartment("");
            setAmountMin("");
            setAmountMax("");
            setUpdatedFrom("");
            setUpdatedTo("");
            setQueuePreset("all_visible");
          }}
        >
          Clear
        </button>
      </div>

      <RequisitionsTable
        requisitions={sortedRequisitions}
        state={renderedState}
        filtered={filtered}
        pagination={data?.meta}
        onRetry={() => refetch()}
        sort={tableState.sort}
        onSortChange={tableState.setSort}
      />
    </section>
  );
}

function getSortValue(requisition: { title: string }, columnId: string) {
  if (columnId === "title") return requisition.title;

  return "";
}

function useDebouncedValue<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delay);

    return () => window.clearTimeout(timeout);
  }, [delay, value]);

  return debouncedValue;
}

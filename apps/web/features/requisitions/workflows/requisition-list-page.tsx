"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useDataTableState } from "@/components/data-table/use-data-table-state";
import { useRequisitions } from "../hooks/use-requisitions";
import { RequisitionsTable } from "../tables/requisitions-table";

export function RequisitionListPage() {
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [status, setStatus] = useState("");
  const tableState = useDataTableState({ initialSort: { columnId: "title", direction: "asc" } });
  const query = useMemo(() => ({ search: debouncedSearch, status }), [debouncedSearch, status]);
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
  const filtered = Boolean(search || status);
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

      <div className="grid gap-3 rounded-md border p-3 md:grid-cols-[minmax(0,1fr)_12rem_8rem]">
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
            onChange={(event) => setStatus(event.target.value)}
          >
            <option value="">All</option>
            <option value="draft">Draft</option>
            <option value="submitted">Submitted</option>
          </select>
        </label>
        <button
          type="button"
          className="min-h-11 self-end rounded-md border px-3 text-sm font-medium"
          onClick={() => {
            setSearch("");
            setStatus("");
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

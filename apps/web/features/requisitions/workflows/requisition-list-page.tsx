"use client";

import Link from "next/link";
import { Plus } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useRequisitions } from "../hooks/use-requisitions";
import { RequisitionsTable } from "../tables/requisitions-table";

export function RequisitionListPage() {
  const [search, setSearch] = useState("");
  const debouncedSearch = useDebouncedValue(search, 300);
  const [status, setStatus] = useState("");
  const query = useMemo(() => ({ search: debouncedSearch, status }), [debouncedSearch, status]);
  const { data, isLoading, isError, refetch } = useRequisitions(query);
  const requisitions = data?.data ?? [];
  const filtered = Boolean(search || status);

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

      {isLoading ? <TableSkeleton /> : null}
      {isError ? (
        <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          <p className="font-medium">Requisitions could not be loaded.</p>
          <button
            type="button"
            className="mt-3 min-h-11 rounded-md border bg-white px-3"
            onClick={() => refetch()}
          >
            Retry
          </button>
        </div>
      ) : null}
      {!isLoading && !isError && requisitions.length === 0 ? (
        <div className="rounded-md border p-6">
          <h2 className="text-base font-semibold">
            {filtered ? "No requisitions match these filters" : "No requisitions yet"}
          </h2>
          <p className="mt-1 text-sm text-muted-foreground">
            {filtered
              ? "Clear filters to see the full work queue."
              : "Create the first draft requisition for this tenant."}
          </p>
        </div>
      ) : null}
      {!isLoading && !isError && requisitions.length > 0 ? (
        <RequisitionsTable requisitions={requisitions} />
      ) : null}
    </section>
  );
}

function useDebouncedValue<T>(value: T, delay: number): T {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = window.setTimeout(() => setDebouncedValue(value), delay);

    return () => window.clearTimeout(timeout);
  }, [delay, value]);

  return debouncedValue;
}

function TableSkeleton() {
  return (
    <div className="space-y-2 rounded-md border p-3" aria-label="Loading requisitions">
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="h-12 rounded-md bg-card" />
      ))}
    </div>
  );
}

"use client";

import { useState } from "react";
import { ApprovalTasksTable } from "../tables/approval-tasks-table";
import { useApprovalTasks } from "../hooks/use-approval-tasks";
import type { ApprovalTaskFilters, ApprovalTaskScope } from "../types/approval-view-model";

const scopes: Array<{ value: ApprovalTaskScope; label: string }> = [
  { value: "assigned_to_me", label: "Assigned to me" },
  { value: "overdue", label: "Overdue" },
  { value: "due_soon", label: "Due soon" },
  { value: "completed_by_me", label: "Completed by me" },
  { value: "all", label: "All tenant approvals" },
];

export function ApprovalQueuePage() {
  const [filters, setFilters] = useState<ApprovalTaskFilters>({ scope: "assigned_to_me" });
  const tasksQuery = useApprovalTasks(filters);

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-normal">Approvals</h1>
        <p className="text-sm text-muted-foreground">
          Review assigned approval tasks and tenant approval activity.
        </p>
      </header>

      <section className="space-y-3" aria-label="Approval queue filters">
        <div className="flex flex-wrap gap-2">
          {scopes.map((scope) => (
            <button
              type="button"
              key={scope.value}
              className={`min-h-10 rounded-md border px-3 text-sm font-medium ${
                filters.scope === scope.value ? "bg-foreground text-background" : "bg-background"
              }`}
              onClick={() => setFilters((current) => ({ ...current, scope: scope.value }))}
            >
              {scope.label}
            </button>
          ))}
        </div>
        <div className="grid gap-3 md:grid-cols-4">
          <label className="text-sm font-medium">
            Status
            <select
              className="mt-1 min-h-10 w-full rounded-md border bg-background px-3 font-normal"
              value={filters.status ?? ""}
              onChange={(event) =>
                setFilters((current) => ({ ...current, status: event.target.value || undefined }))
              }
            >
              <option value="">Any status</option>
              <option value="active">Active</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="changes_requested">Changes requested</option>
            </select>
          </label>
          <FilterInput label="Due from" type="date" value={filters.dueFrom} onChange={(dueFrom) => setFilters((current) => ({ ...current, dueFrom }))} />
          <FilterInput label="Due to" type="date" value={filters.dueTo} onChange={(dueTo) => setFilters((current) => ({ ...current, dueTo }))} />
          <FilterInput label="Requester" value={filters.requesterId} onChange={(requesterId) => setFilters((current) => ({ ...current, requesterId }))} />
          <FilterInput label="Department" value={filters.department} onChange={(department) => setFilters((current) => ({ ...current, department }))} />
          <FilterInput label="Cost center" value={filters.costCenter} onChange={(costCenter) => setFilters((current) => ({ ...current, costCenter }))} />
          <FilterInput label="Project" value={filters.projectId} onChange={(projectId) => setFilters((current) => ({ ...current, projectId }))} />
          <FilterInput label="Amount min" type="number" value={filters.amountMin?.toString()} onChange={(amountMin) => setFilters((current) => ({ ...current, amountMin: amountMin ? Number(amountMin) : undefined }))} />
          <FilterInput label="Amount max" type="number" value={filters.amountMax?.toString()} onChange={(amountMax) => setFilters((current) => ({ ...current, amountMax: amountMax ? Number(amountMax) : undefined }))} />
          <FilterInput label="Updated from" type="date" value={filters.updatedFrom} onChange={(updatedFrom) => setFilters((current) => ({ ...current, updatedFrom }))} />
          <FilterInput label="Updated to" type="date" value={filters.updatedTo} onChange={(updatedTo) => setFilters((current) => ({ ...current, updatedTo }))} />
        </div>
      </section>

      <ApprovalTasksTable
        tasks={tasksQuery.data?.data ?? []}
        state={tasksQuery.isLoading ? "loading" : tasksQuery.isError ? "error" : (tasksQuery.data?.data.length ?? 0) === 0 ? "empty" : "idle"}
      />
    </div>
  );
}

function FilterInput({
  label,
  value,
  type = "text",
  onChange,
}: {
  label: string;
  value?: string;
  type?: string;
  onChange: (value: string | undefined) => void;
}) {
  return (
    <label className="text-sm font-medium">
      {label}
      <input
        type={type}
        className="mt-1 min-h-10 w-full rounded-md border bg-background px-3 font-normal"
        value={value ?? ""}
        onChange={(event) => onChange(event.target.value || undefined)}
      />
    </label>
  );
}

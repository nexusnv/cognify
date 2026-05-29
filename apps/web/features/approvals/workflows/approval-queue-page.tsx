"use client";

import { useState } from "react";
import { Button, Card, CardContent, Input, NativeSelect } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import { Toolbar } from "@/components/ui/toolbar";
import { ApprovalTasksTable } from "../tables/approval-tasks-table";
import { ApprovalSlaSummary } from "../components/approval-sla-summary";
import { useApprovalSlaSummary } from "../hooks/use-approval-sla-summary";
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
  const slaSummaryQuery = useApprovalSlaSummary();

  return (
    <div className="space-y-6">
      <PageHeader
        title="Approvals"
        description="Review assigned approval tasks and tenant approval activity."
      />

      <Card>
        <CardContent className="space-y-4 p-4" aria-label="Approval queue filters">
          <Toolbar label="Approval scopes">
            <div className="flex flex-wrap gap-2">
              {scopes.map((scope) => (
                <Button
                  type="button"
                  key={scope.value}
                  variant={filters.scope === scope.value ? "default" : "outline"}
                  onClick={() => setFilters((current) => ({ ...current, scope: scope.value }))}
                >
                  {scope.label}
                </Button>
              ))}
            </div>
          </Toolbar>
          <div className="grid gap-3 md:grid-cols-4">
            <label className="text-sm font-medium">
              Subject type
              <NativeSelect
                className="mt-1 w-full"
                value={filters.subjectType ?? ""}
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    subjectType: event.target.value
                      ? (event.target.value as ApprovalTaskFilters["subjectType"])
                      : undefined,
                  }))
                }
              >
                <option value="">Any subject</option>
                <option value="requisition">Requisition</option>
                <option value="rfq_award_recommendation">Award recommendation</option>
              </NativeSelect>
            </label>
            <label className="text-sm font-medium">
              Status
              <NativeSelect
                className="mt-1 w-full"
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
              </NativeSelect>
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
        </CardContent>
      </Card>

      <ApprovalSlaSummary
        summary={slaSummaryQuery.data}
        state={slaSummaryQuery.isLoading ? "loading" : slaSummaryQuery.isError ? "error" : "idle"}
      />

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
      <Input
        type={type}
        className="mt-1"
        value={value ?? ""}
        onChange={(event) => onChange(event.target.value || undefined)}
      />
    </label>
  );
}

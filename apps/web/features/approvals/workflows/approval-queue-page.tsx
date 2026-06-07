"use client";

import { useState } from "react";
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Field,
  FieldContent,
  FieldGroup,
  FieldLabel,
  Input,
  NativeSelect,
  Tabs,
  TabsList,
  TabsTrigger,
} from "@cognify/ui";
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
  const hasFilters =
    !!filters.subjectType ||
    !!filters.status ||
    !!filters.dueFrom ||
    !!filters.dueTo ||
    !!filters.requesterId ||
    !!filters.department ||
    !!filters.costCenter ||
    !!filters.projectId ||
    typeof filters.amountMin === "number" ||
    typeof filters.amountMax === "number" ||
    !!filters.updatedFrom ||
    !!filters.updatedTo;

  return (
    <div className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-normal">Approvals</h1>
        <p className="text-sm text-muted-foreground">
          Review assigned approval tasks and tenant approval activity.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Queue filters</CardTitle>
          <CardDescription>Slice the queue by scope, subject, timing, and business context.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Tabs
            value={filters.scope ?? "assigned_to_me"}
            onValueChange={(scope) =>
              setFilters((current) => ({ ...current, scope: scope as ApprovalTaskScope }))
            }
          >
            <TabsList aria-label="Approval scopes" className="flex h-auto w-full flex-wrap justify-start">
              {scopes.map((scope) => (
                <TabsTrigger key={scope.value} value={scope.value} className="min-h-11 px-3">
                  {scope.label}
                </TabsTrigger>
              ))}
            </TabsList>
          </Tabs>

          <FieldGroup className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <Field>
              <FieldLabel htmlFor="approval-subject-type">Subject type</FieldLabel>
              <FieldContent>
                <NativeSelect
                  id="approval-subject-type"
                  value={filters.subjectType ?? ""}
                  onChange={(event) =>
                    setFilters((current) => ({
                      ...current,
                      subjectType: event.target.value
                        ? event.target.value as ApprovalTaskFilters["subjectType"]
                        : undefined,
                    }))
                  }
                >
                  <option value="">Any subject</option>
                  <option value="requisition">Requisition</option>
                  <option value="rfq_award_recommendation">Award recommendation</option>
                </NativeSelect>
              </FieldContent>
            </Field>
            <Field>
              <FieldLabel htmlFor="approval-status">Status</FieldLabel>
              <FieldContent>
                <NativeSelect
                  id="approval-status"
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
              </FieldContent>
            </Field>
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
          </FieldGroup>
          {hasFilters ? (
            <div className="flex justify-end">
              <Button
                type="button"
                variant="outline"
                onClick={() =>
                  setFilters((current) => ({
                    scope: current.scope ?? "assigned_to_me",
                  }))
                }
              >
                Reset filters
              </Button>
            </div>
          ) : null}
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
    <Field>
      <FieldLabel htmlFor={label}>{label}</FieldLabel>
      <FieldContent>
        <Input
          id={label}
          type={type}
          value={value ?? ""}
          onChange={(event) => onChange(event.target.value || undefined)}
        />
      </FieldContent>
    </Field>
  );
}

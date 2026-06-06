"use client";

import Link from "next/link";
import { ExternalLink } from "lucide-react";
import { DataTable } from "@/components/ui/procurement-table";
import type { DataTableColumn, DataTablePagination, DataTableSort } from "@/components/ui/procurement-table";
import { ProjectStatusBadge } from "../components/project-status-badge";
import type { ProcurementProject } from "../types/project-view-model";

const columns: DataTableColumn<ProcurementProject>[] = [
  {
    id: "number",
    header: "Project",
    widthClassName: "w-44",
    cell: (project) => <span className="font-mono text-xs tabular-nums">{project.number}</span>,
  },
  {
    id: "name",
    header: "Name",
    sortable: true,
    cell: (project) => <span className="font-medium">{project.name}</span>,
  },
  {
    id: "status",
    header: "Status",
    widthClassName: "w-32",
    cell: (project) => <ProjectStatusBadge status={project.status} />,
  },
  {
    id: "owner",
    header: "Owner",
    widthClassName: "w-44",
    cell: (project) => <span className="text-muted-foreground">{project.owner.name}</span>,
  },
  {
    id: "budget",
    header: "Budget",
    widthClassName: "w-36",
    align: "right",
    cell: (project) => (
      <span className="font-mono tabular-nums">
        {formatMoney(project.budgetAmount ?? 0, project.currency)}
      </span>
    ),
  },
  {
    id: "linked",
    header: "Linked requisitions",
    widthClassName: "w-32",
    align: "center",
    cell: (project) => <span>{project.summary.linkedRequisitionCount}</span>,
  },
  {
    id: "updatedAt",
    header: "Updated",
    widthClassName: "w-36",
    cell: (project) => <span className="text-muted-foreground">{formatDate(project.updatedAt)}</span>,
  },
];

export function ProjectsTable({
  projects,
  state,
  filtered,
  onRetry,
  pagination,
  sort,
  onSortChange,
}: {
  projects: ProcurementProject[];
  state: "idle" | "loading" | "error" | "empty";
  filtered: boolean;
  onRetry?: () => void;
  pagination?: DataTablePagination;
  sort?: DataTableSort;
  onSortChange?: (sort: DataTableSort) => void;
}) {
  return (
    <DataTable
      caption="Projects"
      rows={projects}
      columns={columns}
      getRowId={(project) => project.id}
      state={state}
      loadingLabel="Loading projects"
      errorTitle="Projects could not be loaded."
      emptyTitle={filtered ? "No projects match these filters" : "No projects yet"}
      emptyDescription={
        filtered
          ? "Clear filters to see all projects."
          : "Create the first procurement project for this tenant."
      }
      onRetry={onRetry}
      pagination={pagination}
      sort={sort}
      onSortChange={onSortChange}
      renderRowActions={(project) => (
        <Link
          href={`/projects/${project.id}`}
          className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3"
        >
          Open
          <ExternalLink className="h-4 w-4" aria-hidden="true" />
        </Link>
      )}
      renderMobileRow={(project) => (
        <Link href={`/projects/${project.id}`} className="block rounded-md border p-3">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-medium">{project.name}</p>
              <p className="mt-1 font-mono text-xs text-muted-foreground">{project.number}</p>
            </div>
            <ProjectStatusBadge status={project.status} size="compact" />
          </div>
          <div className="mt-3 flex items-center justify-between text-sm">
            <span>{project.owner.name}</span>
            <span className="font-mono tabular-nums">
              {formatMoney(project.budgetAmount ?? 0, project.currency)}
            </span>
          </div>
        </Link>
      )}
    />
  );
}

function formatDate(value: string) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}

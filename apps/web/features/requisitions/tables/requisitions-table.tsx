"use client";

import { ExternalLink, PanelRightOpen } from "lucide-react";
import Link from "next/link";
import { Button } from "@cognify/ui";
import { DataTable } from "@/components/ui/procurement-table";
import type { DataTableColumn, DataTablePagination, DataTableSort } from "@/components/ui/procurement-table";
import { useRightPanel } from "@/components/right-panel/right-panel-provider";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import type { Requisition } from "../types/requisition-view-model";
import { formatMoney } from "../utils/requisition-totals";

const requisitionColumns: DataTableColumn<Requisition>[] = [
  {
    id: "number",
    header: "Number",
    widthClassName: "w-36",
    cell: (requisition) => (
      <span className="font-mono text-xs tabular-nums">{requisition.number}</span>
    ),
  },
  {
    id: "title",
    header: "Title",
    sortable: true,
    cell: (requisition) => <span className="font-medium">{requisition.title}</span>,
  },
  {
    id: "project",
    header: "Project",
    widthClassName: "w-56",
    cell: (requisition) =>
      requisition.projectSummary ? (
        <Link
          href={`/projects/${requisition.projectSummary.id}`}
          className="text-muted-foreground underline-offset-4 hover:underline"
        >
          {requisition.projectSummary.number}
        </Link>
      ) : (
        <span className="text-muted-foreground">No project</span>
      ),
  },
  {
    id: "status",
    header: "Status",
    widthClassName: "w-36",
    cell: (requisition) => <RequisitionStatusBadge status={requisition.status} />,
  },
  {
    id: "requester",
    header: "Requester",
    widthClassName: "w-36",
    cell: (requisition) => <span className="text-muted-foreground">{requisition.requester.name}</span>,
  },
  {
    id: "department",
    header: "Department",
    widthClassName: "w-36",
    cell: (requisition) => (
      <span className="text-muted-foreground">{requisition.department ?? "Unassigned"}</span>
    ),
  },
  {
    id: "neededByDate",
    header: "Needed by",
    widthClassName: "w-32",
    cell: (requisition) => <span className="tabular-nums">{requisition.neededByDate}</span>,
  },
  {
    id: "updatedAt",
    header: "Updated",
    widthClassName: "w-40",
    cell: (requisition) => (
      <span className="text-muted-foreground">{formatDateLabel(requisition.updatedAt)}</span>
    ),
  },
  {
    id: "estimatedTotal",
    header: "Estimated total",
    widthClassName: "w-36",
    align: "right",
    cell: (requisition) => (
      <span className="font-mono tabular-nums">
        {formatMoney(requisition.estimatedTotal, requisition.currency ?? "MYR")}
      </span>
    ),
  },
];

export function RequisitionsTable({
  requisitions,
  state = "idle",
  filtered = false,
  pagination,
  onRetry,
  sort,
  onSortChange,
}: {
  requisitions: Requisition[];
  state?: "idle" | "loading" | "error" | "empty";
  filtered?: boolean;
  pagination?: DataTablePagination;
  onRetry?: () => void;
  sort?: DataTableSort;
  onSortChange?: (sort: DataTableSort) => void;
}) {
  const rightPanel = useRightPanel();

  return (
    <DataTable
      caption="Requisitions"
      rows={requisitions}
      columns={requisitionColumns}
      getRowId={(requisition) => requisition.id}
      state={state}
      loadingLabel="Loading requisitions"
      errorTitle="Requisitions could not be loaded."
      emptyTitle={filtered ? "No requisitions match these filters" : "No requisitions yet"}
      emptyDescription={
        filtered
          ? "Clear filters to see the full work queue."
          : "Create the first draft requisition for this tenant."
      }
      onRetry={onRetry}
      sort={sort}
      onSortChange={onSortChange}
      pagination={pagination}
      renderRowActions={(requisition) => (
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="outline"
            className="min-h-11"
            onClick={() => rightPanel.openPanel(requisitionPanel(requisition))}
            aria-label={`Open details panel for ${requisition.number}`}
          >
            <PanelRightOpen className="h-4 w-4" aria-hidden="true" />
          </Button>
          <Link
            href={`/requisitions/${requisition.id}`}
            className="inline-flex min-h-11 items-center gap-2 rounded-md border px-3"
          >
            Open
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </Link>
        </div>
      )}
      renderMobileRow={(requisition) => (
        <Link href={`/requisitions/${requisition.id}`} className="block rounded-md border p-4">
          <div className="flex items-start justify-between gap-3">
            <div>
              <p className="font-medium">{requisition.title}</p>
              <p className="mt-1 font-mono text-xs text-muted-foreground">{requisition.number}</p>
            </div>
            <RequisitionStatusBadge status={requisition.status} size="compact" />
          </div>
          <div className="mt-3 flex items-center justify-between text-sm">
            <span>Needed {requisition.neededByDate}</span>
            <span className="font-mono tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency ?? "MYR")}
            </span>
          </div>
          <div className="mt-1 text-xs text-muted-foreground">
            {requisition.projectSummary ? requisition.projectSummary.number : "No project"}
          </div>
          <div className="mt-2 flex items-center justify-between text-xs text-muted-foreground">
            <span>{requisition.department ?? "No department"}</span>
            <span>Updated {formatDateLabel(requisition.updatedAt)}</span>
          </div>
        </Link>
      )}
    />
  );
}

function formatDateLabel(value: string) {
  const date = new Date(value);

  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function requisitionPanel(requisition: Requisition) {
  return {
    id: `requisition-${requisition.id}`,
    title: requisition.title,
    description: requisition.number,
    size: "md" as const,
    content: (
      <div className="space-y-4 text-sm">
        <div className="flex items-center justify-between gap-3">
          <span className="text-muted-foreground">Status</span>
          <RequisitionStatusBadge status={requisition.status} />
        </div>
        <dl className="grid gap-3">
          <div>
            <dt className="text-muted-foreground">Requester</dt>
            <dd className="font-medium">{requisition.requester.name}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Needed by</dt>
            <dd className="font-medium tabular-nums">{requisition.neededByDate}</dd>
          </div>
          <div>
            <dt className="text-muted-foreground">Estimated total</dt>
            <dd className="font-mono font-medium tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency ?? "MYR")}
            </dd>
          </div>
        </dl>
      </div>
    ),
    footer: (
      <div className="flex flex-col gap-2 sm:flex-row">
        <Link
          href={`/requisitions/${requisition.id}`}
          className="inline-flex min-h-11 items-center justify-center rounded-md bg-foreground px-3 text-sm font-medium text-background"
        >
          Open workspace
        </Link>
        {requisition.permissions.canUpdate ? (
          <Link
            href={`/requisitions/${requisition.id}/edit`}
            className="inline-flex min-h-11 items-center justify-center rounded-md border px-3 text-sm font-medium"
          >
            Edit draft
          </Link>
        ) : null}
      </div>
    ),
  };
}

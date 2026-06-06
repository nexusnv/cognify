"use client";

import Link from "next/link";
import { ExternalLink } from "lucide-react";
import { DataTable } from "@/components/data-table/data-table";
import { Button, Card, CardContent } from "@cognify/ui";
import type { DataTableColumn } from "@/components/data-table/data-table-types";
import { SourcingIntakeStatusBadge } from "../components/sourcing-intake-status-badge";
import type { SourcingIntakeReview } from "../types/sourcing-view-model";

const columns: DataTableColumn<SourcingIntakeReview>[] = [
  {
    id: "requisition",
    header: "Requisition",
    cell: (review) => (
      <div>
        <div className="font-medium">{review.requisition.title}</div>
        <div className="font-mono text-xs text-muted-foreground">{review.requisition.number}</div>
      </div>
    ),
  },
  {
    id: "requester",
    header: "Requester",
    widthClassName: "w-44",
    cell: (review) => <span>{review.requisition.requester?.name ?? "Unknown"}</span>,
  },
  {
    id: "department",
    header: "Department",
    widthClassName: "w-36",
    cell: (review) => <span className="text-muted-foreground">{review.requisition.department ?? "Not set"}</span>,
  },
  {
    id: "amount",
    header: "Estimated total",
    widthClassName: "w-36",
    align: "right",
    cell: (review) => (
      <span className="font-mono tabular-nums">
        {formatMoney(review.requisition.estimatedTotal, review.requisition.currency ?? "MYR")}
      </span>
    ),
  },
  {
    id: "neededBy",
    header: "Needed by",
    widthClassName: "w-32",
    cell: (review) => <span>{formatDate(review.requisition.neededByDate)}</span>,
  },
  {
    id: "status",
    header: "Status",
    widthClassName: "w-44",
    cell: (review) => <SourcingIntakeStatusBadge status={review.status} />,
  },
  {
    id: "buyer",
    header: "Buyer",
    widthClassName: "w-40",
    cell: (review) => <span>{review.assignedBuyer?.name ?? "Unassigned"}</span>,
  },
  {
    id: "target",
    header: "Target",
    widthClassName: "w-32",
    cell: (review) => <span>{formatDate(review.targetDecisionDate)}</span>,
  },
  {
    id: "updated",
    header: "Updated",
    widthClassName: "w-32",
    cell: (review) => <span>{formatDate(review.updatedAt)}</span>,
  },
];

export function SourcingIntakeTable({
  reviews,
  state,
  onRetry,
}: {
  reviews: SourcingIntakeReview[];
  state: "idle" | "loading" | "error" | "empty";
  onRetry?: () => void;
}) {
  return (
    <DataTable
      caption="Sourcing intake reviews"
      rows={reviews}
      columns={columns}
      getRowId={(review) => review.id}
      state={state}
      loadingLabel="Loading sourcing intake"
      errorTitle="Sourcing intake could not be loaded."
      emptyTitle="No sourcing intake reviews"
      emptyDescription="Try another queue preset or create intake from an eligible requisition."
      onRetry={onRetry}
      renderRowActions={(review) => (
        <Button asChild variant="outline" size="sm">
          <Link href={`/sourcing/intake/${review.id}`}>
            Open
            <ExternalLink className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Button>
      )}
      renderMobileRow={(review) => (
        <Card className="gap-0 py-0">
          <CardContent className="p-3">
            <Link href={`/sourcing/intake/${review.id}`} className="block">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-medium">{review.requisition.title}</p>
                  <p className="mt-1 font-mono text-xs text-muted-foreground">{review.requisition.number}</p>
                </div>
                <SourcingIntakeStatusBadge status={review.status} size="compact" />
              </div>
              <div className="mt-3 flex items-center justify-between gap-3 text-sm">
                <span>{review.assignedBuyer?.name ?? "Unassigned"}</span>
                <span className="font-mono tabular-nums">
                  {formatMoney(review.requisition.estimatedTotal, review.requisition.currency ?? "MYR")}
                </span>
              </div>
              <p className="mt-2 text-sm text-muted-foreground">Target {formatDate(review.targetDecisionDate)}</p>
            </Link>
          </CardContent>
        </Card>
      )}
    />
  );
}

function formatDate(value?: string | null) {
  if (!value) return "Not set";
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString();
}

function formatMoney(amount: number | string | null | undefined, currency: string) {
  const value = amount == null ? 0 : Number(amount);
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isNaN(value) ? 0 : value);
}

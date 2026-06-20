"use client";

import { useMemo, useState } from "react";
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { ApPaymentHandoffStatus } from "@cognify/api-client/schemas";
import { useApPaymentHandoffs } from "../hooks/use-payment-handoffs";
import { DataTable } from "@/components/ui/procurement-table/procurement-data-table";
import type { DataTableColumn, DataTableState } from "@/components/ui/procurement-table/data-table-types";
import { PaymentStatusFilters } from "./payment-status-filters";
import { HandoffSchedulePanel } from "./handoff-schedule-panel";
import { HandoffAllocationPanel } from "./handoff-allocation-panel";
import { HandoffPaymentActionsPanel } from "./handoff-payment-actions-panel";
import { HandoffFailureDetail } from "./handoff-failure-detail";
import { HandoffVarianceDetail } from "./handoff-variance-detail";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

const statusLabels: Record<string, string> = {
  draft: "Draft",
  ready: "Ready",
  exported: "Exported",
  cancelled: "Cancelled",
  scheduled: "Scheduled",
  paid: "Paid",
  failed: "Failed",
  voided: "Voided",
};

const statusVariants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
  draft: "secondary",
  ready: "default",
  exported: "outline",
  cancelled: "destructive",
  scheduled: "default",
  paid: "default",
  failed: "destructive",
  voided: "secondary",
};

export function PaymentStatusQueuePage() {
  const [selectedStatuses, setSelectedStatuses] = useState<string[]>([]);
  const [searchQuery, setSearchQuery] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [selectedHandoff, setSelectedHandoff] = useState<HandoffWithNumber | null>(null);

  const handoffsQuery = useApPaymentHandoffs();

  const filteredHandoffs = useMemo(() => {
    const rawHandoffs = (handoffsQuery.data?.handoffs as HandoffWithNumber[] | undefined) ?? [];
    return rawHandoffs.filter((handoff) => {
      if (selectedStatuses.length > 0 && !selectedStatuses.includes(handoff.status)) {
        return false;
      }
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const number = (handoff.number ?? handoff.id).toLowerCase();
        const match = number.includes(q);
        if (!match) return false;
      }
      if (dateFrom || dateTo) {
        const created = new Date(handoff.createdAt);
        if (dateFrom) {
          const from = new Date(dateFrom);
          from.setHours(0, 0, 0, 0);
          if (created < from) return false;
        }
        if (dateTo) {
          const to = new Date(dateTo);
          to.setHours(23, 59, 59, 999);
          if (created > to) return false;
        }
      }
      return true;
    });
  }, [handoffsQuery.data, selectedStatuses, searchQuery, dateFrom, dateTo]);

  const state: DataTableState = handoffsQuery.isLoading
    ? "loading"
    : handoffsQuery.isError
      ? "error"
      : filteredHandoffs.length === 0
        ? "empty"
        : "idle";

  return (
    <section className="space-y-6">
      <header className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-normal">Payment status</h1>
        <p className="text-sm text-muted-foreground">
          Track payment handoffs, allocations, and settlement status across the active tenant.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Handoff queue</CardTitle>
          <CardDescription>
            Payment handoffs and their current settlement status.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <PaymentStatusFilters
            selectedStatuses={selectedStatuses}
            onChangeSelectedStatuses={setSelectedStatuses}
            searchQuery={searchQuery}
            onChangeSearchQuery={setSearchQuery}
            dateFrom={dateFrom}
            onChangeDateFrom={setDateFrom}
            dateTo={dateTo}
            onChangeDateTo={setDateTo}
          />

          <div className="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_28rem]">
            <HandoffDataTable
              handoffs={filteredHandoffs}
              state={state}
              onSelect={setSelectedHandoff}
              errorTitle={queueErrorTitle(handoffsQuery.error)}
            />
            <div className="space-y-4">
              {selectedHandoff && (
                <HandoffSidePanel
                  handoff={selectedHandoff}
                  onMutationSettled={() => {
                    void handoffsQuery.refetch();
                    setSelectedHandoff(null);
                  }}
                />
              )}
              {!selectedHandoff && (
                <aside className="rounded-md border p-4 text-sm text-muted-foreground">
                  Select a handoff to view details and actions.
                </aside>
              )}
            </div>
          </div>
        </CardContent>
      </Card>
    </section>
  );
}

function HandoffDataTable({
  handoffs,
  state,
  onSelect,
  errorTitle,
}: {
  handoffs: HandoffWithNumber[];
  state: DataTableState;
  onSelect: (handoff: HandoffWithNumber) => void;
  errorTitle?: string;
}) {
  const columns: Array<DataTableColumn<HandoffWithNumber>> = [
    {
      id: "number",
      header: "Handoff",
      cell: (handoff) => (
        <div>
          <p className="font-medium">{handoff.number ?? handoff.id.slice(0, 8)}</p>
          <p className="font-mono text-xs text-muted-foreground">{handoff.id}</p>
        </div>
      ),
    },
    {
      id: "status",
      header: "Status",
      cell: (handoff) => (
        <Badge variant={statusVariants[handoff.status] ?? "secondary"}>
          {statusLabels[handoff.status] ?? handoff.status}
        </Badge>
      ),
    },
    {
      id: "invoices",
      header: "Invoices",
      cell: (handoff) => handoff.invoiceCount,
    },
    {
      id: "total",
      header: "Total",
      align: "right",
      cell: (handoff) => formatCurrency(Number.parseFloat(handoff.totalAmount), handoff.currency),
    },
    {
      id: "currency",
      header: "Currency",
      cell: (handoff) => handoff.currency,
    },
    {
      id: "created",
      header: "Created",
      cell: (handoff) => new Date(handoff.createdAt).toLocaleDateString(),
    },
  ];

  return (
    <DataTable
      caption="Payment handoffs"
      rows={handoffs}
      columns={columns}
      getRowId={(handoff) => handoff.id}
      state={state}
      loadingLabel="Loading payment handoffs"
      errorTitle={errorTitle ?? "Payment handoffs unavailable"}
      emptyTitle="No handoffs match the current filters."
      emptyDescription="Adjust filters or return when handoffs are created."
      renderRowActions={(handoff) => (
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onSelect(handoff)}
        >
          View details
        </Button>
      )}
    />
  );
}

function HandoffSidePanel({
  handoff,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}) {
  return (
    <aside className="space-y-4 rounded-md border p-4" aria-label="Handoff details">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">
            {handoff.number ?? `Handoff ${handoff.id.slice(0, 8)}`}
          </h2>
          <p className="text-sm text-muted-foreground">
            {handoff.invoiceCount} invoice{handoff.invoiceCount !== 1 ? "s" : ""}
          </p>
        </div>
        <Badge variant={statusVariants[handoff.status] ?? "secondary"}>
          {statusLabels[handoff.status] ?? handoff.status}
        </Badge>
      </div>

      <dl className="grid gap-2 text-sm sm:grid-cols-2">
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Total amount</dt>
          <dd className="font-mono text-xs">
            {formatCurrency(Number.parseFloat(handoff.totalAmount), handoff.currency)}
          </dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Currency</dt>
          <dd>{handoff.currency}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Created</dt>
          <dd>{new Date(handoff.createdAt).toLocaleDateString()}</dd>
        </div>
        <div>
          <dt className="text-xs uppercase text-muted-foreground">Effective date</dt>
          <dd>
            {handoff.effectivePaymentDate
              ? new Date(handoff.effectivePaymentDate).toLocaleDateString()
              : "\u2014"}
          </dd>
        </div>
        {handoff.remittanceReference && (
          <div>
            <dt className="text-xs uppercase text-muted-foreground">Remittance</dt>
            <dd>{handoff.remittanceReference}</dd>
          </div>
        )}
      </dl>

      <HandoffSchedulePanel
        handoff={handoff}
        onMutationSettled={onMutationSettled}
      />

      <HandoffAllocationPanel
        handoff={handoff}
        onMutationSettled={onMutationSettled}
      />

      <HandoffPaymentActionsPanel
        handoff={handoff}
        onMutationSettled={onMutationSettled}
      />

      {handoff.status === ApPaymentHandoffStatus.failed && (
        <HandoffFailureDetail handoff={handoff} />
      )}

      <HandoffVarianceDetail handoff={handoff} />
    </aside>
  );
}

function queueErrorTitle(error: unknown) {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { code?: string; message?: string } }).error;
    if (apiError?.code === "forbidden") {
      return "You are not allowed to perform this action.";
    }
    if (apiError?.message) {
      return apiError.message;
    }
  }
  return "Payment status queue unavailable";
}

function formatCurrency(value: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  } catch {
    return `${currency} ${value.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }
}

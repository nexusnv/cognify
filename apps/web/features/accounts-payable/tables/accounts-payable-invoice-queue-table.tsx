"use client";

import { Button } from "@cognify/ui";
import type { SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";
import { DataTable } from "@/components/ui/procurement-table/procurement-data-table";
import type { DataTableColumn, DataTableState } from "@/components/ui/procurement-table/data-table-types";
import { InvoiceReviewStatusBadge } from "../components/invoice-review-status-badge";

const columns: Array<DataTableColumn<SupplierInvoiceQueueItem>> = [
  {
    id: "invoice",
    header: "Invoice",
    cell: (invoice) => (
      <div>
        <p className="font-medium">{invoice.invoiceNumber}</p>
        <p className="font-mono text-xs text-muted-foreground">{invoice.number}</p>
      </div>
    ),
  },
  {
    id: "vendor",
    header: "Vendor",
    cell: (invoice) => invoice.vendor.name ?? "Unknown vendor",
  },
  {
    id: "purchaseOrder",
    header: "Purchase order",
    cell: (invoice) => invoice.purchaseOrder.number ?? invoice.purchaseOrder.id,
  },
  {
    id: "status",
    header: "Status",
    cell: (invoice) => <InvoiceReviewStatusBadge status={invoice.status} />,
  },
  {
    id: "dueDate",
    header: "Due date",
    cell: (invoice) => invoice.dueDate ?? "No due date",
  },
  {
    id: "total",
    header: "Total",
    align: "right",
    cell: (invoice) => formatMoney(invoice.totalAmount, invoice.currency),
  },
  {
    id: "attachment",
    header: "Attachment state",
    cell: (invoice) => invoice.attachmentCount > 0 ? `${invoice.attachmentCount} attached` : "Missing attachment",
  },
  {
    id: "checklist",
    header: "Checklist state",
    cell: (invoice) => `${invoice.reviewChecklistSummary.passed}/${invoice.reviewChecklistSummary.total} passed`,
  },
  {
    id: "lastReview",
    header: "Last review",
    cell: (invoice) => formatDate(invoice.reviewedAt ?? invoice.reviewStartedAt),
  },
];

export function AccountsPayableInvoiceQueueTable({
  invoices,
  state,
  onSelect,
}: {
  invoices: SupplierInvoiceQueueItem[];
  state: DataTableState;
  onSelect: (invoice: SupplierInvoiceQueueItem) => void;
}) {
  return (
    <DataTable
      caption="Accounts payable supplier invoices"
      rows={invoices}
      columns={columns}
      getRowId={(invoice) => invoice.id}
      state={state}
      loadingLabel="Loading invoice review queue"
      errorTitle="Invoice review queue unavailable"
      emptyTitle="No invoices match this review state."
      emptyDescription="Switch review state or return when supplier invoices are captured."
      renderRowActions={(invoice) => (
        <Button type="button" variant="outline" size="sm" onClick={() => onSelect(invoice)}>
          Review invoice
        </Button>
      )}
    />
  );
}

function formatMoney(amount: string, currency: string) {
  const numericAmount = Number(amount);

  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
}

function formatDate(value: string | null) {
  if (!value) return "-";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return parsed.toISOString().slice(0, 10);
}

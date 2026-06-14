import type { SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";

const metrics = [
  { key: "captured", label: "Needs review" },
  { key: "in_review", label: "In review" },
  { key: "needs_information", label: "Needs information" },
  { key: "reviewed", label: "Reviewed" },
];

export function InvoiceQueueSummary({ invoices }: { invoices: SupplierInvoiceQueueItem[] }) {
  return (
    <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
      {metrics.map((metric) => (
        <div key={metric.key} className="rounded-md border bg-card p-3">
          <dt className="text-xs font-medium uppercase text-muted-foreground">{metric.label}</dt>
          <dd className="mt-1 text-2xl font-semibold tabular-nums">
            {invoices.filter((invoice) => invoice.status === metric.key).length}
          </dd>
        </div>
      ))}
    </dl>
  );
}

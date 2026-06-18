"use client";

import { useState } from "react";
import { Button } from "@cognify/ui";
import type { CaptureSupplierInvoiceRequest, PurchaseOrder, SupplierInvoice } from "@cognify/api-client/schemas";
import {
  buildInitialSupplierInvoiceFormValues,
  PurchaseOrderSupplierInvoiceForm,
  type SupplierInvoiceFormValues,
} from "./purchase-order-supplier-invoice-form";
import { PurchaseOrderSupplierInvoiceAttachments } from "./purchase-order-supplier-invoice-attachments";
import {
  useCreatePurchaseOrderSupplierInvoice,
  usePurchaseOrderSupplierInvoices,
} from "../hooks/use-purchase-order-supplier-invoices";
import { errorToMessage } from "../utils/error-helpers";

export function PurchaseOrderSupplierInvoicePanel({ purchaseOrder }: { purchaseOrder: PurchaseOrder }) {
  const invoicesQuery = usePurchaseOrderSupplierInvoices(purchaseOrder.id);
  const createMutation = useCreatePurchaseOrderSupplierInvoice(purchaseOrder.id);
  const [showForm, setShowForm] = useState(false);
  const [formValues, setFormValues] = useState<SupplierInvoiceFormValues>(
    buildInitialSupplierInvoiceFormValues(purchaseOrder),
  );

  const invoices = invoicesQuery.data ?? [];
  const canCaptureInvoice =
    purchaseOrder.permissions.canCaptureInvoice && purchaseOrder.lines.length > 0 && !invoicesQuery.isError;
  const summary = buildInvoiceSummary(purchaseOrder, invoices);

  async function handleSubmit() {
    const payload: CaptureSupplierInvoiceRequest = {
      lockVersion: purchaseOrder.lockVersion,
      invoiceNumber: formValues.invoiceNumber,
      invoiceDate: formValues.invoiceDate,
      dueDate: formValues.dueDate || null,
      taxAmount: formValues.taxAmount || null,
      freightAmount: formValues.freightAmount || null,
      notes: formValues.notes || null,
      lines: purchaseOrder.lines
        .map((line) => ({
          purchaseOrderLineId: line.id,
          quantityInvoiced: formValues.lines[line.id]?.quantityInvoiced ?? "0.0000",
          unitPrice: formValues.lines[line.id]?.unitPrice ?? line.unitPrice,
          notes: formValues.lines[line.id]?.notes || null,
        }))
        .filter((line) => Number(line.quantityInvoiced) > 0),
    };

    try {
      await createMutation.mutateAsync(payload);
      setShowForm(false);
      setFormValues(buildInitialSupplierInvoiceFormValues(purchaseOrder, [...invoices, payloadToInvoiceStub(payload, purchaseOrder)]));
    } catch {
      return;
    }
  }

  return (
    <section id="supplier-invoices" className="rounded-md border p-4" aria-label="Supplier invoices">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h2 className="text-base font-semibold">Supplier invoices</h2>
          <p className="text-sm text-muted-foreground">
            {invoicesQuery.isLoading ? "Loading supplier invoices..." : `${summary.totalCount} invoice(s) captured`}
          </p>
          {!invoicesQuery.isLoading ? (
            <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span>Latest invoice date: {summary.latestInvoiceDate ?? "-"}</span>
              <span>Total invoiced: {formatMoney(summary.totalInvoicedAmount, summary.currency)}</span>
            </div>
          ) : null}
        </div>
        {canCaptureInvoice && !showForm ? (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => {
              setFormValues(buildInitialSupplierInvoiceFormValues(purchaseOrder, invoices));
              setShowForm(true);
            }}
          >
            Capture invoice
          </Button>
        ) : null}
      </div>

      {showForm ? (
        <>
          <PurchaseOrderSupplierInvoiceForm
            purchaseOrder={purchaseOrder}
            values={formValues}
            isSubmitting={createMutation.isPending}
            onChange={setFormValues}
            onCancel={() => setShowForm(false)}
            onSubmit={() => void handleSubmit()}
          />
          {createMutation.isError ? (
            <div role="alert" className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
              {errorToMessage(createMutation.error)}
            </div>
          ) : null}
        </>
      ) : null}

      {invoicesQuery.isError ? (
        <div role="alert" className="mt-4 rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">
          {errorToMessage(invoicesQuery.error) ?? "Supplier invoices could not be loaded."}
        </div>
      ) : null}

      {!invoicesQuery.isError && invoices.length > 0 ? (
        <div className="mt-4 space-y-3">
          {invoices.map((invoice) => (
            <SupplierInvoiceCard
              key={invoice.id}
              purchaseOrderId={purchaseOrder.id}
              invoice={invoice}
            />
          ))}
        </div>
      ) : null}

      {!invoicesQuery.isLoading && !invoicesQuery.isError && invoices.length === 0 && !showForm ? (
        <div className="mt-4 rounded-md border bg-muted/20 p-3 text-sm text-muted-foreground">
          No supplier invoices have been captured for this purchase order yet.
        </div>
      ) : null}

    </section>
  );
}

function SupplierInvoiceCard({
  invoice,
  purchaseOrderId,
}: {
  invoice: SupplierInvoice;
  purchaseOrderId: string;
}) {
  return (
    <div className="rounded-md border p-3 text-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="space-y-1">
          <p className="font-medium">{invoice.invoiceNumber}</p>
          <p className="text-xs text-muted-foreground">
            Internal ref {invoice.number}
          </p>
        </div>
        <span className="rounded-full border bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
          {invoice.status}
        </span>
      </div>

      <div className="mt-3 grid gap-2 md:grid-cols-2">
        <div className="space-y-1 text-xs text-muted-foreground">
          <p>Invoice date: {invoice.invoiceDate}</p>
          <p>Due date: {invoice.dueDate ?? "-"}</p>
          <p>
            Captured by {invoice.capturedByUserId} on {formatDateTime(invoice.capturedAt)}
          </p>
        </div>
        <div className="space-y-1 text-xs text-muted-foreground">
          <p>Subtotal: {formatMoney(invoice.subtotalAmount, invoice.currency)}</p>
          <p>Tax: {formatMoney(invoice.taxAmount, invoice.currency)}</p>
          <p>Freight: {formatMoney(invoice.freightAmount, invoice.currency)}</p>
          <p className="font-medium text-foreground">
            Total: {formatMoney(invoice.totalAmount, invoice.currency)}
          </p>
        </div>
      </div>

      <div className="mt-3 space-y-1">
        {invoice.lines.map((line) => (
          <div key={line.id} className="rounded-md border bg-muted/10 p-2 text-xs text-muted-foreground">
            <p className="font-medium text-foreground">
              Line {line.lineNumber}: {line.descriptionSnapshot}
            </p>
            <p>
              Quantity invoiced {line.quantityInvoiced} at {line.unitPrice}
            </p>
            {line.notes ? <p>{line.notes}</p> : null}
          </div>
        ))}
      </div>

      {invoice.notes ? <p className="mt-3 text-xs text-muted-foreground">{invoice.notes}</p> : null}

      <PurchaseOrderSupplierInvoiceAttachments
        supplierInvoiceId={invoice.id}
        purchaseOrderId={purchaseOrderId}
      />
    </div>
  );
}

function buildInvoiceSummary(purchaseOrder: PurchaseOrder, invoices: SupplierInvoice[]) {
  if (invoices.length === 0) {
    return {
      totalCount: purchaseOrder.invoiceSummary.totalInvoiceCount,
      latestInvoiceDate: purchaseOrder.invoiceSummary.latestInvoiceDate ?? null,
      totalInvoicedAmount: purchaseOrder.invoiceSummary.totalInvoicedAmount,
      currency: purchaseOrder.invoiceSummary.currency,
    };
  }

  const latestInvoiceDate = invoices
    .map((invoice) => invoice.invoiceDate)
    .filter((invoiceDate): invoiceDate is string => Boolean(invoiceDate))
    .sort((left, right) => right.localeCompare(left))[0];
  const totalInvoicedAmount = invoices
    .reduce((sum, invoice) => sum + Number(invoice.totalAmount), 0)
    .toFixed(2);

  return {
    totalCount: invoices.length,
    latestInvoiceDate,
    totalInvoicedAmount,
    currency: invoices[0]?.currency ?? purchaseOrder.currency,
  };
}

function payloadToInvoiceStub(payload: CaptureSupplierInvoiceRequest, purchaseOrder: PurchaseOrder): SupplierInvoice {
  return {
    id: "pending",
    purchaseOrderId: purchaseOrder.id,
    vendorId: purchaseOrder.vendor.id,
    number: "pending",
    invoiceNumber: payload.invoiceNumber,
    status: "captured",
    invoiceDate: payload.invoiceDate,
    dueDate: payload.dueDate ?? null,
    currency: purchaseOrder.currency,
    subtotalAmount: "0.0000",
    taxAmount: payload.taxAmount ?? "0.0000",
    freightAmount: payload.freightAmount ?? "0.0000",
    totalAmount: "0.0000",
    notes: payload.notes ?? null,
    capturedByUserId: null,
    capturedAt: null,
    purchaseOrder: {
      id: purchaseOrder.id,
      number: purchaseOrder.number,
    },
    vendor: {
      id: purchaseOrder.vendor.id,
      name: purchaseOrder.vendor.name,
    },
    attachmentCount: 0,
    reviewStartedByUserId: null,
    reviewStartedAt: null,
    reviewedByUserId: null,
    reviewedAt: null,
    reviewNotes: null,
    reviewChecklist: null,
    reviewChecklistSummary: {
      total: 5,
      passed: 0,
      needsAttention: 0,
      failed: 0,
    },
    reviewBlockers: [],
    reviewBlockerCount: 0,
    permissions: {
      canReview: purchaseOrder.permissions.canCaptureInvoice,
    },
    lockVersion: 1,
    paymentStatus: null,
    lines: payload.lines.map((line, index) => {
      const purchaseOrderLine = purchaseOrder.lines.find((item) => item.id === line.purchaseOrderLineId);

      return {
        id: `pending-${line.purchaseOrderLineId}`,
        purchaseOrderLineId: line.purchaseOrderLineId,
        lineNumber: purchaseOrderLine?.lineNumber ?? index + 1,
        descriptionSnapshot: purchaseOrderLine?.description ?? `Line ${index + 1}`,
        quantityOrdered: purchaseOrderLine?.quantity ?? "0.0000",
        quantityInvoiced: line.quantityInvoiced,
        unitPrice: line.unitPrice,
        lineSubtotal: "0.0000",
        notes: line.notes ?? null,
      };
    }),
  };
}

function formatMoney(amount: string, currency: string) {
  const numericAmount = Number(amount);

  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(numericAmount) ? numericAmount : 0);
}

function formatDateTime(value: string | null) {
  if (!value) {
    return "-";
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return value;
  }

  return parsed.toISOString().slice(0, 10);
}

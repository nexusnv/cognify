"use client";

import { Button, Input, Textarea } from "@cognify/ui";
import type { PurchaseOrder, SupplierInvoice } from "@cognify/api-client/schemas";

export type SupplierInvoiceLineFormValues = {
  quantityInvoiced: string;
  unitPrice: string;
  notes: string;
};

export type SupplierInvoiceFormValues = {
  invoiceNumber: string;
  invoiceDate: string;
  dueDate: string;
  taxAmount: string;
  freightAmount: string;
  notes: string;
  lines: Record<string, SupplierInvoiceLineFormValues>;
};

export function buildInitialSupplierInvoiceFormValues(
  purchaseOrder: PurchaseOrder,
  existingInvoices: SupplierInvoice[] = [],
): SupplierInvoiceFormValues {
  return {
    invoiceNumber: "",
    invoiceDate: "",
    dueDate: "",
    taxAmount: "",
    freightAmount: "",
    notes: "",
    lines: Object.fromEntries(
      purchaseOrder.lines.map((line) => [
        line.id,
        {
          quantityInvoiced: remainingQuantityForLine(line.id, line.quantity, existingInvoices),
          unitPrice: line.unitPrice,
          notes: "",
        },
      ]),
    ),
  };
}

function remainingQuantityForLine(
  purchaseOrderLineId: string,
  orderedQuantity: string,
  existingInvoices: SupplierInvoice[],
) {
  const previouslyInvoiced = existingInvoices.reduce((sum, invoice) => {
    const lineTotal = invoice.lines
      .filter((line) => line.purchaseOrderLineId === purchaseOrderLineId)
      .reduce((lineSum, line) => lineSum + Number(line.quantityInvoiced), 0);

    return sum + lineTotal;
  }, 0);
  const remainingQuantity = Math.max(Number(orderedQuantity) - previouslyInvoiced, 0);

  return Number.isFinite(remainingQuantity) ? remainingQuantity.toFixed(4) : "0.0000";
}

type PurchaseOrderSupplierInvoiceFormProps = {
  purchaseOrder: PurchaseOrder;
  values: SupplierInvoiceFormValues;
  isSubmitting: boolean;
  onChange: (nextValues: SupplierInvoiceFormValues) => void;
  onCancel: () => void;
  onSubmit: () => void;
};

export function PurchaseOrderSupplierInvoiceForm({
  purchaseOrder,
  values,
  isSubmitting,
  onChange,
  onCancel,
  onSubmit,
}: PurchaseOrderSupplierInvoiceFormProps) {
  const hasAtLeastOneLine = purchaseOrder.lines.length > 0;
  const hasPositiveLineQuantity = Object.values(values.lines).some(
    (line) => Number(line.quantityInvoiced) > 0,
  );

  function updateField<K extends Exclude<keyof SupplierInvoiceFormValues, "lines">>(
    field: K,
    nextValue: SupplierInvoiceFormValues[K],
  ) {
    onChange({ ...values, [field]: nextValue });
  }

  function updateLineField(
    lineId: string,
    field: keyof SupplierInvoiceLineFormValues,
    nextValue: string,
  ) {
    onChange({
      ...values,
      lines: {
        ...values.lines,
        [lineId]: {
          ...values.lines[lineId],
          [field]: nextValue,
        },
      },
    });
  }

  return (
    <div className="mt-4 space-y-4 rounded-md border bg-muted/20 p-4">
      <h3 className="text-sm font-semibold">Capture supplier invoice</h3>
      <div className="grid gap-3 md:grid-cols-2">
        <label className="space-y-1 text-sm">
          <span className="font-medium">Invoice number</span>
          <Input
            value={values.invoiceNumber}
            onChange={(event) => updateField("invoiceNumber", event.target.value)}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Invoice date</span>
          <Input
            type="date"
            value={values.invoiceDate}
            onChange={(event) => updateField("invoiceDate", event.target.value)}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Due date</span>
          <Input
            type="date"
            value={values.dueDate}
            onChange={(event) => updateField("dueDate", event.target.value)}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Tax amount</span>
          <Input
            inputMode="decimal"
            value={values.taxAmount}
            onChange={(event) => updateField("taxAmount", event.target.value)}
          />
        </label>
        <label className="space-y-1 text-sm">
          <span className="font-medium">Freight amount</span>
          <Input
            inputMode="decimal"
            value={values.freightAmount}
            onChange={(event) => updateField("freightAmount", event.target.value)}
          />
        </label>
      </div>

      <label className="space-y-1 text-sm">
        <span className="font-medium">Invoice notes</span>
        <Textarea
          value={values.notes}
          onChange={(event) => updateField("notes", event.target.value)}
        />
      </label>

      <div className="space-y-3">
        <div>
          <h4 className="text-sm font-semibold">Invoice lines</h4>
          <p className="text-xs text-muted-foreground">
            Allocate the captured invoice by purchase order line.
          </p>
        </div>
        {purchaseOrder.lines.map((line) => {
          const lineValues = values.lines[line.id];

          return (
            <div key={line.id} className="space-y-3 rounded-md border bg-background p-3">
              <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p className="text-sm font-medium">
                    Line {line.lineNumber}: {line.description}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Ordered {line.quantity} {line.unit} at {line.unitPrice} {purchaseOrder.currency}
                  </p>
                </div>
              </div>
              <div className="grid gap-3 md:grid-cols-2">
                <label className="space-y-1 text-sm">
                  <span className="font-medium">{line.description} quantity invoiced</span>
                  <Input
                    inputMode="decimal"
                    value={lineValues?.quantityInvoiced ?? ""}
                    onChange={(event) =>
                      updateLineField(line.id, "quantityInvoiced", event.target.value)
                    }
                  />
                </label>
                <label className="space-y-1 text-sm">
                  <span className="font-medium">{line.description} unit price</span>
                  <Input
                    inputMode="decimal"
                    value={lineValues?.unitPrice ?? ""}
                    onChange={(event) => updateLineField(line.id, "unitPrice", event.target.value)}
                  />
                </label>
              </div>
              <label className="space-y-1 text-sm">
                <span className="font-medium">{line.description} line notes</span>
                <Textarea
                  value={lineValues?.notes ?? ""}
                  onChange={(event) => updateLineField(line.id, "notes", event.target.value)}
                />
              </label>
            </div>
          );
        })}
      </div>

      <div className="flex gap-2">
        <Button
          type="button"
          disabled={
            !hasAtLeastOneLine ||
            !hasPositiveLineQuantity ||
            !values.invoiceNumber ||
            !values.invoiceDate ||
            isSubmitting
          }
          onClick={onSubmit}
        >
          {isSubmitting ? "Saving invoice..." : "Save invoice"}
        </Button>
        <Button type="button" variant="outline" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </div>
  );
}

"use client";

import { Plus, Trash2 } from "lucide-react";
import { Button, NativeSelect, Textarea } from "@cognify/ui";
import type { QuotationManualEntryFormValues } from "../schemas/quotation-manual-entry-schema";

export function QuotationLineItemsEditor({
  lineItems,
  onChange,
  disabled,
}: {
  lineItems: QuotationManualEntryFormValues["lineItems"];
  onChange: (lineItems: QuotationManualEntryFormValues["lineItems"]) => void;
  disabled?: boolean;
}) {
  function updateLineItem(
    index: number,
    patch: Partial<QuotationManualEntryFormValues["lineItems"][number]>,
  ) {
    onChange(lineItems.map((lineItem, lineIndex) => (lineIndex === index ? { ...lineItem, ...patch } : lineItem)));
  }

  function addLineItem() {
    onChange([
      ...lineItems,
      {
        rfqLineItemId: null,
        description: "",
        quantity: "",
        unit: "",
        unitPrice: "",
        subtotalAmount: "",
        taxAmount: "",
        totalAmount: "",
        leadTimeDays: null,
        manufacturer: "",
        modelNumber: "",
        alternateOffered: false,
        complianceStatus: null,
        notes: "",
      },
    ]);
  }

  function removeLineItem(index: number) {
    onChange(lineItems.filter((_, lineIndex) => lineIndex !== index));
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">Capture quoted items, pricing, and compliance notes.</p>
        <Button type="button" variant="outline" size="sm" onClick={addLineItem} disabled={disabled}>
          <Plus className="h-4 w-4" aria-hidden="true" />
          Add quoted line
        </Button>
      </div>

      <div className="overflow-x-auto rounded-md border">
        <table className="min-w-[66rem] w-full border-separate border-spacing-0 text-sm">
          <thead className="bg-muted/40">
            <tr>
              <th className="border-b px-3 py-2 text-left font-medium">Description</th>
              <th className="border-b px-3 py-2 text-left font-medium">Quantity</th>
              <th className="border-b px-3 py-2 text-left font-medium">Unit</th>
              <th className="border-b px-3 py-2 text-left font-medium">Unit price</th>
              <th className="border-b px-3 py-2 text-left font-medium">Total amount</th>
              <th className="border-b px-3 py-2 text-left font-medium">Compliance</th>
              <th className="border-b px-3 py-2 text-left font-medium">Notes</th>
              <th className="border-b px-3 py-2 text-left font-medium">Action</th>
            </tr>
          </thead>
          <tbody>
            {lineItems.length === 0 ? (
              <tr>
                <td colSpan={8} className="px-3 py-5 text-sm text-muted-foreground">
                  No structured line items recorded yet.
                </td>
              </tr>
            ) : null}

            {lineItems.map((lineItem, index) => (
              <tr key={buildRowKey(lineItem, index)} className="align-top">
                <td className="border-b px-3 py-3">
                  <input
                    aria-label={`Line ${index + 1} description`}
                    className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    value={lineItem.description ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { description: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <input
                    aria-label={`Line ${index + 1} quantity`}
                    className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    value={lineItem.quantity ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { quantity: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <input
                    aria-label={`Line ${index + 1} unit`}
                    className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    value={lineItem.unit ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { unit: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <input
                    aria-label={`Line ${index + 1} unit price`}
                    className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    value={lineItem.unitPrice ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { unitPrice: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <input
                    aria-label={`Line ${index + 1} total amount`}
                    className="min-h-11 w-full rounded-md border border-input bg-background px-3 text-base outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    value={lineItem.totalAmount ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { totalAmount: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <NativeSelect
                    aria-label={`Line ${index + 1} compliance status`}
                    value={lineItem.complianceStatus ?? ""}
                    disabled={disabled}
                    onChange={(event) =>
                      updateLineItem(index, {
                        complianceStatus: event.target.value
                          ? (event.target.value as QuotationManualEntryFormValues["lineItems"][number]["complianceStatus"])
                          : null,
                      })
                    }
                  >
                    <option value="">Select status</option>
                    <option value="compliant">Compliant</option>
                    <option value="partial">Partial</option>
                    <option value="non_compliant">Non-compliant</option>
                    <option value="alternate">Alternate offered</option>
                  </NativeSelect>
                </td>
                <td className="border-b px-3 py-3">
                  <Textarea
                    aria-label={`Line ${index + 1} notes`}
                    className="min-h-20"
                    value={lineItem.notes ?? ""}
                    disabled={disabled}
                    onChange={(event) => updateLineItem(index, { notes: event.target.value })}
                  />
                </td>
                <td className="border-b px-3 py-3">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => removeLineItem(index)}
                    disabled={disabled}
                    aria-label={`Remove line ${index + 1}`}
                  >
                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                    Remove
                  </Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function buildRowKey(
  lineItem: QuotationManualEntryFormValues["lineItems"][number],
  index: number,
) {
  return `${lineItem.rfqLineItemId ?? "line"}-${index}`;
}

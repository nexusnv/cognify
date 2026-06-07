"use client";

import { Plus, Trash2 } from "lucide-react";
import { Button, Input, NativeSelect, Table, TableBody, TableCell, TableHead, TableHeader, TableRow, Textarea } from "@cognify/ui";
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

      <Table className="min-w-[66rem] text-sm">
        <TableHeader className="bg-muted/40">
          <TableRow>
            <TableHead className="border-b px-3 py-2">Description</TableHead>
            <TableHead className="border-b px-3 py-2">Quantity</TableHead>
            <TableHead className="border-b px-3 py-2">Unit</TableHead>
            <TableHead className="border-b px-3 py-2">Unit price</TableHead>
            <TableHead className="border-b px-3 py-2">Total amount</TableHead>
            <TableHead className="border-b px-3 py-2">Compliance</TableHead>
            <TableHead className="border-b px-3 py-2">Notes</TableHead>
            <TableHead className="border-b px-3 py-2">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {lineItems.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="px-3 py-5 text-sm text-muted-foreground">
                No structured line items recorded yet.
              </TableCell>
            </TableRow>
          ) : null}

          {lineItems.map((lineItem, index) => (
            <TableRow key={buildRowKey(lineItem, index)} className="align-top">
              <TableCell className="border-b px-3 py-3">
                <Input
                  aria-label={`Line ${index + 1} description`}
                  className="h-11 w-full px-3 text-base"
                  value={lineItem.description ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { description: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
                <Input
                  aria-label={`Line ${index + 1} quantity`}
                  className="h-11 w-full px-3 text-base"
                  value={lineItem.quantity ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { quantity: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
                <Input
                  aria-label={`Line ${index + 1} unit`}
                  className="h-11 w-full px-3 text-base"
                  value={lineItem.unit ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { unit: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
                <Input
                  aria-label={`Line ${index + 1} unit price`}
                  className="h-11 w-full px-3 text-base"
                  value={lineItem.unitPrice ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { unitPrice: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
                <Input
                  aria-label={`Line ${index + 1} total amount`}
                  className="h-11 w-full px-3 text-base"
                  value={lineItem.totalAmount ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { totalAmount: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
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
              </TableCell>
              <TableCell className="border-b px-3 py-3">
                <Textarea
                  aria-label={`Line ${index + 1} notes`}
                  className="min-h-20"
                  value={lineItem.notes ?? ""}
                  disabled={disabled}
                  onChange={(event) => updateLineItem(index, { notes: event.target.value })}
                />
              </TableCell>
              <TableCell className="border-b px-3 py-3">
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
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

function buildRowKey(
  lineItem: QuotationManualEntryFormValues["lineItems"][number],
  index: number,
) {
  return `${lineItem.rfqLineItemId ?? "line"}-${index}`;
}

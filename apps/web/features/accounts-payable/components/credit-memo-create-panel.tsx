"use client";

import { useState } from "react";
import { Button, Card, CardContent, CardHeader, CardTitle, Input, Label } from "@cognify/ui";
import { useCreateSupplierCreditMemo } from "../hooks/use-supplier-credit-memos";
import { CreditMemoMathPreview } from "./credit-memo-math-preview";
import type { SupplierCreditMemoLine } from "@cognify/api-client/schemas";

interface CreditMemoCreatePanelProps {
  onSuccess: (id: string) => void;
  onCancel: () => void;
}

export function CreditMemoCreatePanel({ onSuccess, onCancel }: CreditMemoCreatePanelProps) {
  const createMutation = useCreateSupplierCreditMemo();

  const [vendorId, setVendorId] = useState("");
  const [vendorCreditMemoNumber, setVendorCreditMemoNumber] = useState("");
  const [creditDate, setCreditDate] = useState("");
  const [currency, setCurrency] = useState("USD");
  const [originalInvoiceId, setOriginalInvoiceId] = useState("");
  const [notes, setNotes] = useState("");

  const [lineDescription, setLineDescription] = useState("");
  const [lineQuantity, setLineQuantity] = useState("1");
  const [lineUnitPrice, setLineUnitPrice] = useState("0");
  const [lineTaxCode, setLineTaxCode] = useState("");
  const [lineTaxAmount, setLineTaxAmount] = useState("");

  const [lines, setLines] = useState<
    Array<{ lineNumber: number; description: string; quantity: string; unitPrice: string; taxCode?: string; taxAmount?: string }>
  >([]);

  const previewLines: SupplierCreditMemoLine[] = lines.map((l, i) => ({
    id: `preview-${i}`,
    supplierCreditMemoId: "",
    lineNumber: l.lineNumber,
    description: l.description,
    quantity: l.quantity,
    unitPrice: l.unitPrice,
    lineSubtotal: (Number(l.quantity) * Number(l.unitPrice)).toFixed(4),
    taxCode: l.taxCode,
    taxAmount: l.taxAmount,
  }));

  const subtotal = previewLines.reduce((acc, l) => acc + Number(l.lineSubtotal), 0);
  const tax = previewLines.reduce((acc, l) => acc + Number(l.taxAmount ?? 0), 0);
  const total = subtotal + tax;

  function addLine() {
    if (!lineDescription) return;
    setLines((prev) => [
      ...prev,
      {
        lineNumber: prev.length + 1,
        description: lineDescription,
        quantity: lineQuantity,
        unitPrice: lineUnitPrice,
        taxCode: lineTaxCode || undefined,
        taxAmount: lineTaxAmount || undefined,
      },
    ]);
    setLineDescription("");
    setLineQuantity("1");
    setLineUnitPrice("0");
    setLineTaxCode("");
    setLineTaxAmount("");
  }

  function removeLine(index: number) {
    setLines((prev) => prev.filter((_, i) => i !== index).map((l, i) => ({ ...l, lineNumber: i + 1 })));
  }

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    createMutation.mutate(
      {
        vendorId: Number(vendorId),
        vendorCreditMemoNumber,
        creditDate,
        currency,
        originalInvoiceId: originalInvoiceId || undefined,
        subtotalAmount: subtotal.toFixed(4),
        taxAmount: tax.toFixed(4),
        freightAmount: "0.0000",
        totalAmount: total.toFixed(4),
        notes: notes || undefined,
        lines: lines.map((l) => ({
          lineNumber: l.lineNumber,
          description: l.description,
          quantity: l.quantity,
          unitPrice: l.unitPrice,
          taxCode: l.taxCode,
          taxAmount: l.taxAmount,
        })),
      },
      {
        onSuccess: (memo) => {
          onSuccess(memo.id);
        },
      },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Credit memo details</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1">
            <Label htmlFor="vendor-id">Vendor ID</Label>
            <Input id="vendor-id" value={vendorId} onChange={(e) => setVendorId(e.target.value)} required />
          </div>
          <div className="space-y-1">
            <Label htmlFor="vendor-cm-number">Vendor credit memo number</Label>
            <Input id="vendor-cm-number" value={vendorCreditMemoNumber} onChange={(e) => setVendorCreditMemoNumber(e.target.value)} required maxLength={255} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="credit-date">Credit date</Label>
            <Input id="credit-date" type="date" value={creditDate} onChange={(e) => setCreditDate(e.target.value)} required />
          </div>
          <div className="space-y-1">
            <Label htmlFor="currency">Currency</Label>
            <Input id="currency" value={currency} onChange={(e) => setCurrency(e.target.value)} minLength={3} maxLength={3} />
          </div>
          <div className="space-y-1">
            <Label htmlFor="original-invoice-id">Original invoice ID</Label>
            <Input id="original-invoice-id" value={originalInvoiceId} onChange={(e) => setOriginalInvoiceId(e.target.value)} />
          </div>
          <div className="space-y-1 sm:col-span-2">
            <Label htmlFor="notes">Notes</Label>
            <Input id="notes" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Line items</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {lines.length > 0 && (
            <div className="space-y-2">
              {lines.map((line, i) => (
                <div key={i} className="flex items-center gap-2 rounded border p-2 text-sm">
                  <span className="flex-1">{line.description}</span>
                  <span className="text-muted-foreground">Qty: {line.quantity}</span>
                  <span className="text-muted-foreground">@ {line.unitPrice}</span>
                  <Button type="button" size="sm" variant="ghost" onClick={() => removeLine(i)}>
                    Remove
                  </Button>
                </div>
              ))}
            </div>
          )}

          <div className="flex items-end gap-2">
            <div className="flex-1 space-y-1">
              <Label htmlFor="line-description">Description</Label>
              <Input id="line-description" value={lineDescription} onChange={(e) => setLineDescription(e.target.value)} />
            </div>
            <div className="w-20 space-y-1">
              <Label htmlFor="line-quantity">Qty</Label>
              <Input id="line-quantity" value={lineQuantity} onChange={(e) => setLineQuantity(e.target.value)} />
            </div>
            <div className="w-28 space-y-1">
              <Label htmlFor="line-unit-price">Unit price</Label>
              <Input id="line-unit-price" value={lineUnitPrice} onChange={(e) => setLineUnitPrice(e.target.value)} />
            </div>
            <div className="w-24 space-y-1">
              <Label htmlFor="line-tax-code">Tax code</Label>
              <Input id="line-tax-code" value={lineTaxCode} onChange={(e) => setLineTaxCode(e.target.value)} />
            </div>
            <div className="w-24 space-y-1">
              <Label htmlFor="line-tax-amount">Tax amount</Label>
              <Input id="line-tax-amount" value={lineTaxAmount} onChange={(e) => setLineTaxAmount(e.target.value)} />
            </div>
            <Button type="button" size="sm" onClick={addLine} disabled={!lineDescription}>
              Add line
            </Button>
          </div>
        </CardContent>
      </Card>

      <CreditMemoMathPreview lines={previewLines} />

      {createMutation.isError && (
        <p className="text-sm text-destructive">
          {(createMutation.error as Error)?.message ?? "Failed to create credit memo."}
        </p>
      )}

      <div className="flex gap-2">
        <Button type="submit" disabled={createMutation.isPending || lines.length === 0}>
          {createMutation.isPending ? "Creating…" : "Create credit memo"}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    </form>
  );
}

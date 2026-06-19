"use client";

import { useCallback, useMemo, useState } from "react";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Button,
  Checkbox,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  Input,
  Label,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  Textarea,
} from "@cognify/ui";
import { AlertCircle } from "lucide-react";
import type { SupplierInvoiceQueueItem } from "@cognify/api-client/schemas";
import { toast } from "sonner";
import { useCreateApPaymentHandoff } from "../hooks/use-payment-handoffs";

interface PaymentHandoffCreateDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  eligibleInvoices: SupplierInvoiceQueueItem[];
}

export function PaymentHandoffCreateDialog({
  open,
  onOpenChange,
  eligibleInvoices,
}: PaymentHandoffCreateDialogProps) {
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());
  const [effectivePaymentDate, setEffectivePaymentDate] = useState(
    () => new Date().toISOString().split("T")[0],
  );
  const [notes, setNotes] = useState("");
  const [error, setError] = useState<string | null>(null);

  const createMutation = useCreateApPaymentHandoff();

  const handleOpenChange = useCallback(
    (newOpen: boolean) => {
      if (!newOpen) {
        setSelectedIds(new Set());
        setEffectivePaymentDate(new Date().toISOString().split("T")[0]);
        setNotes("");
        setError(null);
      }
      onOpenChange(newOpen);
    },
    [onOpenChange],
  );

  const selectedInvoices = useMemo(
    () => eligibleInvoices.filter((inv) => selectedIds.has(inv.id)),
    [eligibleInvoices, selectedIds],
  );

  const selectedCount = selectedInvoices.length;

  const currencies = useMemo(
    () => new Set(selectedInvoices.map((inv) => inv.currency)),
    [selectedInvoices],
  );

  const hasMixedCurrencies = currencies.size > 1;

  const totalAmount = useMemo(
    () =>
      selectedInvoices.reduce((sum, inv) => sum + parseFloat(inv.totalAmount), 0),
    [selectedInvoices],
  );

  const displayedCurrency =
    currencies.size === 1 ? Array.from(currencies)[0] : "";

  const allSelected =
    eligibleInvoices.length > 0 && selectedIds.size === eligibleInvoices.length;

  const canCreate =
    selectedCount > 0 && !hasMixedCurrencies && !createMutation.isPending;

  const toggleInvoice = useCallback((id: string) => {
    setSelectedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  }, []);

  const toggleAll = useCallback(() => {
    if (allSelected) {
      setSelectedIds(new Set());
    } else {
      setSelectedIds(new Set(eligibleInvoices.map((inv) => inv.id)));
    }
  }, [eligibleInvoices, allSelected]);

  const handleCreate = useCallback(() => {
    if (!canCreate) return;
    setError(null);

    createMutation.mutate(
      {
        invoiceIds: Array.from(selectedIds),
        notes: notes.trim() || null,
        effectivePaymentDate: effectivePaymentDate || null,
      },
      {
        onSuccess: () => {
          toast.success("Payment handoff created successfully");
          handleOpenChange(false);
        },
        onError: (err) => {
          const message =
            typeof err === "object" && err !== null
              ? (err as { error?: { message?: string } }).error?.message ??
                "Failed to create payment handoff."
              : "Failed to create payment handoff.";
          setError(message);
        },
      },
    );
  }, [
    canCreate,
    selectedIds,
    notes,
    effectivePaymentDate,
    createMutation,
    handleOpenChange,
  ]);

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Create Payment Handoff</DialogTitle>
          <DialogDescription>
            Select invoices to include in the payment handoff.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {selectedCount > 0 && (
            <div className="flex items-center justify-between rounded-md bg-muted px-3 py-2 text-sm">
              <span className="font-medium">
                {selectedCount} invoice{selectedCount !== 1 ? "s" : ""} selected
              </span>
              <span className="font-medium">
                Total: {formatCurrency(totalAmount, displayedCurrency)}
              </span>
            </div>
          )}

          {hasMixedCurrencies && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertTitle>Mixed currencies</AlertTitle>
              <AlertDescription>
                Selected invoices have different currencies. All invoices in a
                handoff must share the same currency.
              </AlertDescription>
            </Alert>
          )}

          <div className="max-h-64 overflow-y-auto rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10">
                    <Checkbox
                      checked={allSelected}
                      onCheckedChange={toggleAll}
                      aria-label="Select all invoices"
                    />
                  </TableHead>
                  <TableHead>Invoice</TableHead>
                  <TableHead>Vendor</TableHead>
                  <TableHead className="text-right">Amount</TableHead>
                  <TableHead>Currency</TableHead>
                  <TableHead>Due date</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {eligibleInvoices.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={6}
                      className="text-center text-muted-foreground"
                    >
                      No eligible invoices
                    </TableCell>
                  </TableRow>
                ) : (
                  eligibleInvoices.map((inv) => (
                    <TableRow key={inv.id}>
                      <TableCell>
                        <Checkbox
                          checked={selectedIds.has(inv.id)}
                          onCheckedChange={() => toggleInvoice(inv.id)}
                          aria-label={`Select invoice ${inv.invoiceNumber}`}
                        />
                      </TableCell>
                      <TableCell className="font-medium">
                        {inv.invoiceNumber}
                      </TableCell>
                      <TableCell>{inv.vendor.name}</TableCell>
                      <TableCell className="text-right font-mono text-xs">
                        {formatAmount(inv.totalAmount)}
                      </TableCell>
                      <TableCell>{inv.currency}</TableCell>
                      <TableCell>
                        {inv.dueDate
                          ? new Date(inv.dueDate).toLocaleDateString()
                          : "\u2014"}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          <div className="space-y-2">
            <Label htmlFor="effective-date">
              Effective payment date (optional)
            </Label>
            <Input
              id="effective-date"
              type="date"
              value={effectivePaymentDate}
              onChange={(e) => setEffectivePaymentDate(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="handoff-notes">Notes (optional)</Label>
            <Textarea
              id="handoff-notes"
              placeholder="Add notes about this payment handoff..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
            />
          </div>

          {error && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => handleOpenChange(false)}
            disabled={createMutation.isPending}
          >
            Cancel
          </Button>
          <Button onClick={handleCreate} disabled={!canCreate}>
            {createMutation.isPending ? "Creating..." : "Create handoff"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function formatAmount(value: string): string {
  const num = parseFloat(value);
  if (isNaN(num)) return value;
  return num.toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatCurrency(value: number, currency: string): string {
  if (!currency) {
    return value.toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }
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

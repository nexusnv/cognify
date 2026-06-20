"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  Button,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  Input,
  Label,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { toast } from "sonner";
import {
  useApPaymentAllocations,
  useCreateApPaymentAllocation,
} from "../hooks/use-ap-payment-allocations";

type HandoffWithNumber = ApPaymentHandoff & { number?: string };

interface HandoffAllocationPanelProps {
  handoff: HandoffWithNumber;
  onMutationSettled: () => void;
}

export function HandoffAllocationPanel({
  handoff,
  onMutationSettled,
}: HandoffAllocationPanelProps) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const allocationsQuery = useApPaymentAllocations(handoff.id);
  const allocations = allocationsQuery.data?.allocations ?? [];

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-semibold">Allocations</h3>
        <Button type="button" variant="outline" size="sm" onClick={() => setDialogOpen(true)}>
          Add allocation
        </Button>
      </div>

      {allocationsQuery.isLoading && (
        <div className="space-y-2">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-3/4" />
        </div>
      )}

      {allocationsQuery.isError && (
        <Alert variant="destructive">
          <AlertDescription>Failed to load allocations.</AlertDescription>
        </Alert>
      )}

      {!allocationsQuery.isLoading && !allocationsQuery.isError && (
        <div className="overflow-auto rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Invoice</TableHead>
                <TableHead className="text-right">Allocated</TableHead>
                <TableHead>Reference</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {allocations.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={3} className="text-center text-muted-foreground">
                    No allocations
                  </TableCell>
                </TableRow>
              ) : (
                allocations.map((allocation) => (
                  <TableRow key={allocation.id}>
                    <TableCell className="font-medium">
                      {allocation.supplierInvoiceNumber ?? allocation.supplierInvoiceId}
                    </TableCell>
                    <TableCell className="text-right font-mono text-xs">
                      {formatCurrency(
                        Number.parseFloat(allocation.allocatedAmount),
                        handoff.currency,
                      )}
                    </TableCell>
                    <TableCell>{allocation.paymentReference ?? "\u2014"}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      )}

      <AddAllocationDialog
        handoff={handoff}
        open={dialogOpen}
        onOpenChange={setDialogOpen}
        onMutationSettled={onMutationSettled}
      />
    </div>
  );
}

function AddAllocationDialog({
  handoff,
  open,
  onOpenChange,
  onMutationSettled,
}: {
  handoff: HandoffWithNumber;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onMutationSettled: () => void;
}) {
  const [supplierInvoiceId, setSupplierInvoiceId] = useState("");
  const [allocatedAmount, setAllocatedAmount] = useState("");
  const [allocationDate, setAllocationDate] = useState(() => new Date().toISOString().split("T")[0]);
  const [paymentReference, setPaymentReference] = useState("");
  const [error, setError] = useState<string | null>(null);

  const createMutation = useCreateApPaymentAllocation(handoff.id);

  function handleOpenChange(nextOpen: boolean) {
    if (!nextOpen) {
      setSupplierInvoiceId("");
      setAllocatedAmount("");
      setAllocationDate(new Date().toISOString().split("T")[0]);
      setPaymentReference("");
      setError(null);
    }
    onOpenChange(nextOpen);
  }

  function handleConfirm() {
    setError(null);

    if (!supplierInvoiceId.trim()) {
      setError("Supplier invoice ID is required.");
      return;
    }
    if (!allocatedAmount.trim() || isNaN(Number(allocatedAmount))) {
      setError("Allocated amount must be a valid number.");
      return;
    }

    createMutation.mutate(
      {
        lockVersion: handoff.lockVersion,
        supplierInvoiceId: supplierInvoiceId.trim(),
        allocatedAmount: allocatedAmount.trim(),
        allocationDate: allocationDate || new Date().toISOString().split("T")[0],
        paymentReference: paymentReference.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Allocation added successfully");
          handleOpenChange(false);
          onMutationSettled();
        },
        onError: (err) => {
          const message = errorToMessage(err) ?? "Failed to add allocation.";
          setError(message);
        },
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Add allocation</DialogTitle>
          <DialogDescription>
            Allocate a supplier invoice to this payment handoff.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="allocation-invoice-id">Supplier invoice ID</Label>
            <Input
              id="allocation-invoice-id"
              placeholder="Invoice ID"
              value={supplierInvoiceId}
              onChange={(e) => setSupplierInvoiceId(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="allocation-amount">Allocated amount</Label>
            <Input
              id="allocation-amount"
              type="number"
              step="0.01"
              placeholder="0.00"
              value={allocatedAmount}
              onChange={(e) => setAllocatedAmount(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="allocation-date">Allocation date</Label>
            <Input
              id="allocation-date"
              type="date"
              value={allocationDate}
              onChange={(e) => setAllocationDate(e.target.value)}
            />
          </div>

          <div className="space-y-2">
            <Label htmlFor="allocation-reference">Payment reference</Label>
            <Input
              id="allocation-reference"
              placeholder="Optional reference"
              value={paymentReference}
              onChange={(e) => setPaymentReference(e.target.value)}
            />
          </div>

          {error && (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          )}
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => handleOpenChange(false)} disabled={createMutation.isPending}>
            Cancel
          </Button>
          <Button onClick={handleConfirm} disabled={createMutation.isPending}>
            {createMutation.isPending ? "Adding..." : "Add allocation"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }
  if (error instanceof Error) return error.message;
  return null;
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

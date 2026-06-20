"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Input,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import { toast } from "sonner";
import type { ApPaymentImportRow } from "@cognify/api-client/schemas";
import {
  usePaymentImportBatch,
  useUpdatePaymentImportRow,
  useDiscardPaymentImportRow,
} from "../hooks/use-ap-payment-import";

interface PaymentImportPreviewPanelProps {
  batchId: string;
  onReconcile: () => void;
}

export function PaymentImportPreviewPanel({
  batchId,
  onReconcile,
}: PaymentImportPreviewPanelProps) {
  const batchQuery = usePaymentImportBatch(batchId);
  const batch = batchQuery.data;
  const rows = batch?.rows ?? [];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Review import rows</CardTitle>
        <CardDescription>
          Review and edit imported payment rows before reconciliation.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {batchQuery.isLoading && (
          <div className="space-y-2">
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-full" />
            <Skeleton className="h-4 w-3/4" />
          </div>
        )}

        {batchQuery.isError && (
          <Alert variant="destructive">
            <AlertDescription>
              {errorToMessage(batchQuery.error) ?? "Failed to load import batch."}
            </AlertDescription>
          </Alert>
        )}

        {!batchQuery.isLoading && !batchQuery.isError && (
          <>
            <div className="overflow-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Row</TableHead>
                    <TableHead>Handoff</TableHead>
                    <TableHead>Invoice</TableHead>
                    <TableHead className="text-right">Amount</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Match error</TableHead>
                    <TableHead className="w-32">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.length === 0 ? (
                    <TableRow>
                      <TableCell
                        colSpan={7}
                        className="text-center text-muted-foreground"
                      >
                        No rows in this batch.
                      </TableCell>
                    </TableRow>
                  ) : (
                    rows.map((row) => (
                      <ImportRowEditor
                        key={row.id}
                        row={row}
                        batchId={batchId}
                      />
                    ))
                  )}
                </TableBody>
              </Table>
            </div>

            <div className="flex justify-end">
              <Button
                type="button"
                onClick={onReconcile}
                disabled={rows.length === 0}
              >
                Proceed to reconcile
              </Button>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  );
}

function ImportRowEditor({
  row,
  batchId,
}: {
  row: ApPaymentImportRow;
  batchId: string;
}) {
  const [isEditing, setIsEditing] = useState(false);
  const [handoffNumber, setHandoffNumber] = useState(row.handoffNumber ?? "");
  const [invoiceNumber, setInvoiceNumber] = useState(row.invoiceNumber ?? "");
  const [allocatedAmount, setAllocatedAmount] = useState(row.allocatedAmount ?? "");
  const [error, setError] = useState<string | null>(null);

  const updateMutation = useUpdatePaymentImportRow(row.id);
  const discardMutation = useDiscardPaymentImportRow(row.id);

  function handleSave() {
    setError(null);
    updateMutation.mutate(
      {
        lockVersion: row.lockVersion,
        handoffNumber: handoffNumber.trim() || undefined,
        invoiceNumber: invoiceNumber.trim() || undefined,
        allocatedAmount: allocatedAmount.trim() || undefined,
      },
      {
        onSuccess: () => {
          toast.success("Row updated");
          setIsEditing(false);
        },
        onError: (err) => {
          setError(errorToMessage(err) ?? "Failed to update row.");
        },
      },
    );
  }

  function handleDiscard() {
    setError(null);
    discardMutation.mutate(undefined, {
      onSuccess: () => {
        toast.success("Row discarded");
      },
      onError: (err) => {
        toast.error(errorToMessage(err) ?? "Failed to discard row.");
      },
    });
  }

  if (isEditing) {
    return (
      <TableRow>
        <TableCell>{row.rowIndex}</TableCell>
        <TableCell>
          <Input
            size={1}
            value={handoffNumber}
            onChange={(e) => setHandoffNumber(e.target.value)}
            placeholder="Handoff #"
            className="h-8 text-xs"
          />
        </TableCell>
        <TableCell>
          <Input
            size={1}
            value={invoiceNumber}
            onChange={(e) => setInvoiceNumber(e.target.value)}
            placeholder="Invoice #"
            className="h-8 text-xs"
          />
        </TableCell>
        <TableCell>
          <Input
            size={1}
            type="number"
            step="0.01"
            value={allocatedAmount}
            onChange={(e) => setAllocatedAmount(e.target.value)}
            placeholder="0.00"
            className="h-8 text-xs"
          />
        </TableCell>
        <TableCell>
          <span className="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
            {row.status}
          </span>
        </TableCell>
        <TableCell className="text-xs text-muted-foreground">
          {row.matchError ?? "\u2014"}
        </TableCell>
        <TableCell>
          <div className="flex flex-col gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-7 px-2 text-xs"
              onClick={handleSave}
              disabled={updateMutation.isPending}
            >
              {updateMutation.isPending ? "Saving..." : "Save"}
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-7 px-2 text-xs"
              onClick={() => setIsEditing(false)}
              disabled={updateMutation.isPending}
            >
              Cancel
            </Button>
            {error && (
              <span className="text-xs text-destructive">{error}</span>
            )}
          </div>
        </TableCell>
      </TableRow>
    );
  }

  return (
    <TableRow>
      <TableCell>{row.rowIndex}</TableCell>
      <TableCell className="font-medium">{row.handoffNumber ?? "\u2014"}</TableCell>
      <TableCell>{row.invoiceNumber ?? "\u2014"}</TableCell>
      <TableCell className="text-right font-mono text-xs">
        {row.allocatedAmount ?? "\u2014"}
      </TableCell>
      <TableCell>
        <span className="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2">
          {row.status}
        </span>
      </TableCell>
      <TableCell className="text-xs text-muted-foreground">
        {row.matchError ?? "\u2014"}
      </TableCell>
      <TableCell>
        <div className="flex flex-col gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 px-2 text-xs"
            onClick={() => setIsEditing(true)}
          >
            Edit
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="h-7 px-2 text-xs text-destructive hover:text-destructive"
            onClick={handleDiscard}
            disabled={discardMutation.isPending}
          >
            {discardMutation.isPending ? "Discarding..." : "Discard"}
          </Button>
        </div>
      </TableCell>
    </TableRow>
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

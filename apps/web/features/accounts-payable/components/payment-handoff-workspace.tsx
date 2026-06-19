"use client";

import { useState } from "react";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  Label,
  Separator,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  Textarea,
} from "@cognify/ui";
import {
  AlertCircle,
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  Download,
  RefreshCw,
  XCircle,
} from "lucide-react";
import type { ApPaymentHandoff } from "@cognify/api-client/schemas";
import { ApPaymentHandoffStatus } from "@cognify/api-client/schemas";
import { toast } from "sonner";
import {
  useApPaymentHandoff,
  useCancelApPaymentHandoff,
  useExportApPaymentHandoffCsv,
  useExportApPaymentHandoffJson,
  useMarkApPaymentHandoffReady,
  useRefreshApPaymentHandoffSnapshot,
} from "../hooks/use-payment-handoffs";

interface PaymentHandoffWorkspaceProps {
  handoffId: string;
  onBack?: () => void;
}

type HandoffWithExtra = ApPaymentHandoff & {
  number?: string;
  readinessWarnings?: string[];
};

function errorToMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null) {
    const apiError = (error as { error?: { code?: string; message?: string } }).error;
    if (apiError?.message) {
      return apiError.message;
    }
    if (error instanceof Error) {
      return error.message;
    }
  }
  return null;
}

function triggerFileDownload(content: string, filename: string, mimeType: string) {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}

export function PaymentHandoffWorkspace({
  handoffId,
  onBack,
}: PaymentHandoffWorkspaceProps) {
  const {
    data: rawHandoff,
    isLoading,
    isError,
    error,
  } = useApPaymentHandoff(handoffId);

  const refreshMutation = useRefreshApPaymentHandoffSnapshot(handoffId);
  const markReadyMutation = useMarkApPaymentHandoffReady(handoffId);
  const cancelMutation = useCancelApPaymentHandoff(handoffId);
  const exportJsonMutation = useExportApPaymentHandoffJson(handoffId);
  const exportCsvMutation = useExportApPaymentHandoffCsv(handoffId);

  const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelError, setCancelError] = useState<string | null>(null);

  if (isLoading) {
    return (
      <Card>
        <CardHeader>
          <Skeleton className="h-5 w-48" />
          <Skeleton className="mt-1 h-4 w-24" />
        </CardHeader>
        <CardContent className="space-y-4">
          <Skeleton className="h-4 w-full" />
          <Skeleton className="h-4 w-3/4" />
          <Skeleton className="h-4 w-5/6" />
        </CardContent>
      </Card>
    );
  }

  if (isError) {
    const message = errorToMessage(error) ?? "Failed to load payment handoff.";
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Error</AlertTitle>
        <AlertDescription>{message}</AlertDescription>
      </Alert>
    );
  }

  if (!rawHandoff) {
    return (
      <Alert variant="destructive">
        <AlertCircle className="h-4 w-4" />
        <AlertTitle>Not found</AlertTitle>
        <AlertDescription>
          Payment handoff &ldquo;{handoffId}&rdquo; was not found.
        </AlertDescription>
      </Alert>
    );
  }

  const handoff = rawHandoff as HandoffWithExtra;
  const status = handoff.status;
  const isDraft = status === ApPaymentHandoffStatus.draft;
  const isReady = status === ApPaymentHandoffStatus.ready;
  const isExported = status === ApPaymentHandoffStatus.exported;
  const canCancel = isDraft || isReady;

  const invoices = handoff.invoices ?? [];

  const anyMutationPending =
    refreshMutation.isPending ||
    markReadyMutation.isPending ||
    cancelMutation.isPending ||
    exportJsonMutation.isPending ||
    exportCsvMutation.isPending;

  function handleRefreshSnapshot() {
    refreshMutation.mutate(
      { lockVersion: handoff.lockVersion },
      {
        onSuccess: () => {
          toast.success("Snapshot refreshed");
        },
        onError: (err) => {
          toast.error(errorToMessage(err) ?? "Failed to refresh snapshot.");
        },
      },
    );
  }

  function handleMarkReady() {
    markReadyMutation.mutate(
      { lockVersion: handoff.lockVersion },
      {
        onSuccess: () => {
          toast.success("Handoff marked as ready");
        },
        onError: (err) => {
          toast.error(errorToMessage(err) ?? "Failed to mark handoff as ready.");
        },
      },
    );
  }

  function handleExportJson() {
    exportJsonMutation.mutate(undefined, {
      onSuccess: (data) => {
        const jsonStr = JSON.stringify(data, null, 2);
        triggerFileDownload(
          jsonStr,
          `handoff-${handoff.number ?? handoff.id}.json`,
          "application/json",
        );
        toast.success("Handoff exported as JSON");
      },
      onError: (err) => {
        toast.error(errorToMessage(err) ?? "Failed to export handoff as JSON.");
      },
    });
  }

  function handleExportCsv() {
    exportCsvMutation.mutate(undefined, {
      onSuccess: (csv) => {
        triggerFileDownload(
          csv,
          `handoff-${handoff.number ?? handoff.id}.csv`,
          "text/csv",
        );
        toast.success("Handoff exported as CSV");
      },
      onError: (err) => {
        toast.error(errorToMessage(err) ?? "Failed to export handoff as CSV.");
      },
    });
  }

  function handleOpenCancelDialog() {
    setCancelReason("");
    setCancelError(null);
    setCancelDialogOpen(true);
  }

  function handleConfirmCancel() {
    if (cancelReason.trim().length < 5) {
      setCancelError("Cancellation reason must be at least 5 characters.");
      return;
    }
    setCancelError(null);

    cancelMutation.mutate(
      { reason: cancelReason.trim(), lockVersion: handoff.lockVersion },
      {
        onSuccess: () => {
          setCancelDialogOpen(false);
          toast.success("Handoff cancelled");
        },
        onError: (err) => {
          setCancelError(errorToMessage(err) ?? "Failed to cancel handoff.");
        },
      },
    );
  }

  const statusBadgeVariant: "default" | "secondary" | "destructive" | "outline" =
    status === ApPaymentHandoffStatus.draft
      ? "secondary"
      : status === ApPaymentHandoffStatus.ready
        ? "default"
        : status === ApPaymentHandoffStatus.exported
          ? "outline"
          : "destructive";

  const statusLabel = status.charAt(0).toUpperCase() + status.slice(1);

  const readinessWarnings: string[] | undefined =
    "readinessWarnings" in handoff
      ? (handoff as { readinessWarnings: string[] }).readinessWarnings
      : undefined;

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between">
        <div className="flex items-center gap-3">
          {onBack && (
            <Button variant="ghost" size="icon" onClick={onBack} aria-label="Go back">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          )}
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-lg font-semibold">
                {handoff.number ?? `Handoff ${handoff.id.slice(0, 8)}`}
              </h2>
              <Badge variant={statusBadgeVariant}>{statusLabel}</Badge>
            </div>
            <p className="mt-1 text-sm text-muted-foreground">
              Created {new Date(handoff.createdAt).toLocaleDateString()}
              {handoff.createdBy?.name ? ` by ${handoff.createdBy.name}` : ""}
            </p>
          </div>
        </div>
      </div>

      <Separator />

      <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div>
          <Label className="text-xs text-muted-foreground">Currency</Label>
          <p className="mt-0.5 font-medium">{handoff.currency}</p>
        </div>
        <div>
          <Label className="text-xs text-muted-foreground">Total amount</Label>
          <p className="mt-0.5 font-mono font-medium text-sm">
            {formatCurrency(parseFloat(handoff.totalAmount), handoff.currency)}
          </p>
        </div>
        <div>
          <Label className="text-xs text-muted-foreground">Invoices</Label>
          <p className="mt-0.5 font-medium">{handoff.invoiceCount}</p>
        </div>
        <div>
          <Label className="text-xs text-muted-foreground">Effective date</Label>
          <p className="mt-0.5 font-medium">
            {handoff.effectivePaymentDate
              ? new Date(handoff.effectivePaymentDate).toLocaleDateString()
              : "\u2014"}
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Invoices ({invoices.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Invoice number</TableHead>
                <TableHead>Total amount</TableHead>
                <TableHead>Currency</TableHead>
                <TableHead>Due date</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {invoices.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="text-center text-muted-foreground">
                    No invoice data
                  </TableCell>
                </TableRow>
              ) : (
                invoices.map((inv) => (
                  <TableRow key={inv.id}>
                    <TableCell className="font-medium">{inv.invoiceNumber}</TableCell>
                    <TableCell className="text-right font-mono text-xs">
                      {formatCurrency(parseFloat(inv.totalAmount), inv.currency)}
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
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Readiness</CardTitle>
        </CardHeader>
        <CardContent>
          {readinessWarnings && readinessWarnings.length > 0 ? (
            <Alert variant="destructive">
              <AlertTriangle className="h-4 w-4" />
              <AlertTitle>Warnings</AlertTitle>
              <AlertDescription>
                <ul className="mt-1 list-inside list-disc space-y-1">
                  {readinessWarnings.map((w, i) => (
                    <li key={i}>{w}</li>
                  ))}
                </ul>
              </AlertDescription>
            </Alert>
          ) : (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <CheckCircle2 className="h-4 w-4 text-green-500" />
              No warnings
            </div>
          )}
        </CardContent>
      </Card>

      {handoff.notes && (
        <Card>
          <CardHeader>
            <CardTitle>Notes</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
              {handoff.notes}
            </p>
          </CardContent>
        </Card>
      )}

      <div className="flex flex-wrap gap-2">
        {isDraft && (
          <>
            <Button
              variant="outline"
              onClick={handleRefreshSnapshot}
              disabled={refreshMutation.isPending || anyMutationPending}
            >
              <RefreshCw
                className={`mr-2 h-4 w-4 ${refreshMutation.isPending ? "animate-spin" : ""}`}
              />
              {refreshMutation.isPending ? "Refreshing..." : "Refresh snapshot"}
            </Button>
            <Button
              onClick={handleMarkReady}
              disabled={markReadyMutation.isPending || anyMutationPending}
            >
              {markReadyMutation.isPending ? "Marking..." : "Mark ready"}
            </Button>
            {canCancel && (
              <Button
                variant="outline"
                onClick={handleOpenCancelDialog}
                disabled={cancelMutation.isPending || anyMutationPending}
                className="text-destructive hover:text-destructive"
              >
                <XCircle className="mr-2 h-4 w-4" />
                Cancel handoff
              </Button>
            )}
          </>
        )}

        {isReady && (
          <>
            <Button
              variant="outline"
              onClick={handleExportJson}
              disabled={exportJsonMutation.isPending || anyMutationPending}
            >
              <Download className="mr-2 h-4 w-4" />
              {exportJsonMutation.isPending ? "Exporting..." : "Export JSON"}
            </Button>
            <Button
              variant="outline"
              onClick={handleExportCsv}
              disabled={exportCsvMutation.isPending || anyMutationPending}
            >
              <Download className="mr-2 h-4 w-4" />
              {exportCsvMutation.isPending ? "Exporting..." : "Export CSV"}
            </Button>
            {canCancel && (
              <Button
                variant="outline"
                onClick={handleOpenCancelDialog}
                disabled={cancelMutation.isPending || anyMutationPending}
                className="text-destructive hover:text-destructive"
              >
                <XCircle className="mr-2 h-4 w-4" />
                Cancel handoff
              </Button>
            )}
          </>
        )}

        {isExported && (
          <>
            <Button
              variant="outline"
              onClick={handleExportJson}
              disabled={exportJsonMutation.isPending || anyMutationPending}
            >
              <Download className="mr-2 h-4 w-4" />
              {exportJsonMutation.isPending ? "Exporting..." : "Export JSON"}
            </Button>
            <Button
              variant="outline"
              onClick={handleExportCsv}
              disabled={exportCsvMutation.isPending || anyMutationPending}
            >
              <Download className="mr-2 h-4 w-4" />
              {exportCsvMutation.isPending ? "Exporting..." : "Export CSV"}
            </Button>
          </>
        )}
      </div>

      <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Cancel payment handoff</DialogTitle>
            <DialogDescription>
              This action cannot be undone. All invoices in this handoff will be
              released back to the payment queue.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="cancel-reason">Cancellation reason</Label>
              <Textarea
                id="cancel-reason"
                placeholder="Explain why this handoff is being cancelled..."
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                rows={3}
              />
              <p className="text-xs text-muted-foreground">
                Reason must be at least 5 characters.
              </p>
            </div>

            {cancelError && (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{cancelError}</AlertDescription>
              </Alert>
            )}
          </div>

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setCancelDialogOpen(false)}
              disabled={cancelMutation.isPending}
            >
              Keep handoff
            </Button>
            <Button
              variant="destructive"
              onClick={handleConfirmCancel}
              disabled={cancelReason.trim().length < 5 || cancelMutation.isPending}
            >
              {cancelMutation.isPending ? "Cancelling..." : "Confirm cancellation"}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
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

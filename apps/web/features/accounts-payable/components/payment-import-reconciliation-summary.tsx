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
  Skeleton,
} from "@cognify/ui";
import { CheckCircle2, XCircle, AlertTriangle, Info } from "lucide-react";
import { toast } from "sonner";
import {
  usePaymentImportBatch,
  useReconcilePaymentImportBatch,
} from "../hooks/use-ap-payment-import";

interface PaymentImportReconciliationSummaryProps {
  batchId: string;
}

export function PaymentImportReconciliationSummary({
  batchId,
}: PaymentImportReconciliationSummaryProps) {
  const batchQuery = usePaymentImportBatch(batchId);
  const reconcileMutation = useReconcilePaymentImportBatch(batchId);
  const [showCommitMessage, setShowCommitMessage] = useState(false);

  const batch = batchQuery.data;
  const summary = batch?.summary;

  const total = summary?.total ?? 0;
  const reconciled = summary?.reconciled ?? 0;
  const failed = summary?.failed ?? 0;
  const pending = summary?.pending ?? 0;
  const discarded = summary?.discarded ?? 0;

  function handleReconcile() {
    reconcileMutation.mutate(undefined, {
      onSuccess: (result) => {
        toast.success(`Reconciliation complete: ${result.reconciledCount} reconciled, ${result.failedCount} failed`);
      },
      onError: (err) => {
        toast.error(errorToMessage(err) ?? "Failed to reconcile batch.");
      },
    });
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Reconciliation summary</CardTitle>
          <CardDescription>
            Overview of the import batch before final reconciliation.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {batchQuery.isLoading && (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[1, 2, 3, 4].map((i) => (
                <Skeleton key={i} className="h-24 w-full" />
              ))}
            </div>
          )}

          {batchQuery.isError && (
            <Alert variant="destructive">
              <AlertDescription>
                {errorToMessage(batchQuery.error) ?? "Failed to load batch summary."}
              </AlertDescription>
            </Alert>
          )}

          {!batchQuery.isLoading && !batchQuery.isError && summary && (
            <>
              <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                  icon={<Info className="h-5 w-5 text-blue-500" />}
                  label="Total rows"
                  value={total}
                />
                <StatCard
                  icon={<CheckCircle2 className="h-5 w-5 text-green-500" />}
                  label="Reconciled"
                  value={reconciled}
                />
                <StatCard
                  icon={<XCircle className="h-5 w-5 text-red-500" />}
                  label="Failed"
                  value={failed}
                />
                <StatCard
                  icon={<AlertTriangle className="h-5 w-5 text-amber-500" />}
                  label="Pending"
                  value={pending}
                />
              </div>

              {discarded > 0 && (
                <p className="text-sm text-muted-foreground">
                  {discarded} row{discarded !== 1 ? "s" : ""} discarded.
                </p>
              )}

              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  onClick={handleReconcile}
                  disabled={reconcileMutation.isPending || total === 0}
                >
                  {reconcileMutation.isPending ? "Reconciling..." : "Reconcile batch"}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setShowCommitMessage(true)}
                >
                  Commit
                </Button>
              </div>

              {showCommitMessage && (
                <Alert>
                  <Info className="h-4 w-4" />
                  <AlertDescription>
                    Commit functionality will be available in P1-49.
                  </AlertDescription>
                </Alert>
              )}

              {reconcileMutation.isError && (
                <Alert variant="destructive">
                  <AlertDescription>
                    {errorToMessage(reconcileMutation.error) ?? "Reconciliation failed."}
                  </AlertDescription>
                </Alert>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function StatCard({
  icon,
  label,
  value,
}: {
  icon: React.ReactNode;
  label: string;
  value: number;
}) {
  return (
    <div className="flex items-center gap-3 rounded-lg border p-4">
      {icon}
      <div>
        <p className="text-2xl font-semibold">{value}</p>
        <p className="text-xs text-muted-foreground">{label}</p>
      </div>
    </div>
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

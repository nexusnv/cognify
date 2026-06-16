"use client";

import { AlertCircle, CheckCircle2, AlertTriangle } from "lucide-react";
import { Alert, AlertDescription, Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Skeleton } from "@cognify/ui";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import { useInvoiceMatchSummary, useRunInvoiceMatching } from "../hooks/use-invoice-matching";
import { InvoiceMatchingStatusBadge } from "./invoice-matching-status-badge";

interface InvoiceMatchResultsPanelProps {
  invoiceId: string;
  lockVersion: number;
  invoiceStatus: string;
  matchingStatus: "pending" | "matched" | "mismatch" | null | undefined;
}

export function InvoiceMatchResultsPanel({
  invoiceId,
  lockVersion,
  invoiceStatus,
  matchingStatus,
}: InvoiceMatchResultsPanelProps) {
  const { summary, results, isLoading, isError } = useInvoiceMatchSummary(invoiceId);
  const runMatching = useRunInvoiceMatching(invoiceId);

  const canRunMatching =
    invoiceStatus === "reviewed" &&
    (!matchingStatus || matchingStatus === "mismatch" || matchingStatus === "pending" || matchingStatus === null);

  const handleRunMatching = () => {
    runMatching.mutate({ lockVersion });
  };

  const runError = runMatching.error;

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="text-lg">Matching Results</CardTitle>
          <CardDescription>
            Two-way and three-way invoice matching status
          </CardDescription>
        </div>
        <div className="flex items-center gap-3">
          <InvoiceMatchingStatusBadge matchingStatus={matchingStatus} />
          {canRunMatching && (
            <Button
              size="sm"
              onClick={handleRunMatching}
              disabled={runMatching.isPending}
            >
              {runMatching.isPending ? "Running..." : "Run Matching"}
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {runError ? (
          <Alert variant="destructive" role="alert" className="mb-3">
            <AlertDescription>{errorToMessage(runError)}</AlertDescription>
          </Alert>
        ) : null}

        {isLoading && (
          <div className="space-y-2">
            <Skeleton className="h-4 w-48" />
            <Skeleton className="h-8 w-full" />
            <Skeleton className="h-8 w-full" />
          </div>
        )}

        {isError && (
          <div className="flex items-center gap-2 text-red-600">
            <AlertCircle className="h-4 w-4" />
            <p className="text-sm">Failed to load match results.</p>
          </div>
        )}

        {!isLoading && !isError && summary && (
          <>
            <div className="mb-3 flex gap-4 text-sm">
              <span className="text-muted-foreground">
                {summary.matchedLines} of {summary.totalLines} lines matched
              </span>
              {summary.mismatchLines > 0 && (
                <span className="font-medium text-red-600">
                  Issues in: {summary.dimensionsWithIssues.join(", ")}
                </span>
              )}
            </div>

            {results && results.length > 0 && (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Line</TableHead>
                    <TableHead>Level</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Dimension</TableHead>
                    <TableHead>Expected</TableHead>
                    <TableHead>Actual</TableHead>
                    <TableHead>Result</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {results.map((r) => (
                    <TableRow key={r.id} className={r.result === "fail" ? "bg-red-50" : ""}>
                      <TableCell>{r.lineNumber ?? "\u2014"}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{r.matchLevel}</TableCell>
                      <TableCell className="text-xs">{r.matchType === "two_way" ? "2W" : "3W"}</TableCell>
                      <TableCell className="font-medium">{r.dimension}</TableCell>
                      <TableCell className="font-mono text-xs">{r.expectedValue ?? "\u2014"}</TableCell>
                      <TableCell className="font-mono text-xs">{r.actualValue ?? "\u2014"}</TableCell>
                      <TableCell>
                        {r.result === "pass" && (
                          <CheckCircle2 className="h-4 w-4 text-green-600" />
                        )}
                        {r.result === "fail" && (
                          <span title={r.notes ?? ""}>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                          </span>
                        )}
                        {r.result === "not_applicable" && (
                          <span className="text-xs text-muted-foreground">N/A</span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </>
        )}

        {!isLoading && !isError && !summary && (
          <p className="text-sm text-muted-foreground">
            No match results yet. Run matching to compare this invoice against the purchase order and receipts.
          </p>
        )}
      </CardContent>
    </Card>
  );
}

function errorToMessage(error: unknown) {
  if (typeof error === "object" && error !== null && "error" in error) {
    const apiError = (error as { error?: { message?: string } }).error;
    if (apiError?.message) return apiError.message;
  }

  if (error instanceof Error) return error.message;

  return "Invoice matching could not be completed.";
}

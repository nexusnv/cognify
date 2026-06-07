"use client";

import Link from "next/link";
import { RotateCw } from "lucide-react";
import { Button, Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@cognify/ui";
import type { QuotationNormalizationSummary } from "@cognify/api-client/schemas";
import { formatDistanceToNowLabel, getUpdatedAt } from "../utils/quotation-normalization-ui";
import {
  formatQuotationNormalizationStatus,
  QuotationNormalizationStatusBadge,
} from "../components/quotation-normalization-status-badge";

export function QuotationNormalizationQueueTable({
  rows,
  onRetry,
  retryingVersionId,
}: {
  rows: QuotationNormalizationSummary[];
  onRetry: (row: QuotationNormalizationSummary) => Promise<void>;
  retryingVersionId: string | null;
}) {
  return (
    <div className="overflow-hidden rounded-md border">
      <Table className="min-w-full text-sm">
        <TableHeader className="bg-muted/40 text-left">
          <TableRow>
            <TableHead className="px-3 py-2 font-medium">Status</TableHead>
            <TableHead className="px-3 py-2 font-medium">Vendor</TableHead>
            <TableHead className="px-3 py-2 font-medium">RFQ</TableHead>
            <TableHead className="px-3 py-2 font-medium">Version</TableHead>
            <TableHead className="px-3 py-2 font-medium">Issues</TableHead>
            <TableHead className="px-3 py-2 font-medium">Updated</TableHead>
            <TableHead className="px-3 py-2 font-medium">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((row) => {
            const updatedAt = getUpdatedAt(row);
            const versionId = row.source.quotationVersionId ?? null;
            const lastJobError = row.status === "failed" ? row.lastJobError : null;

            return (
              <TableRow key={row.id} className="align-top">
                <TableCell className="px-3 py-3">
                  <QuotationNormalizationStatusBadge status={row.status} />
                </TableCell>
                <TableCell className="px-3 py-3">
                  <div className="font-medium">{row.source.vendorName ?? "Unknown vendor"}</div>
                  <div className="text-muted-foreground">{row.source.quotationNumber ?? row.source.quotationId}</div>
                </TableCell>
                <TableCell className="px-3 py-3">{row.source.rfqNumber ?? row.source.rfqId ?? "No RFQ"}</TableCell>
                <TableCell className="px-3 py-3">Version {row.source.versionNumber ?? "?"}</TableCell>
                <TableCell className="px-3 py-3">
                  <div>{row.summary.blockingIssueCount} blocking</div>
                  <div className="text-muted-foreground">{row.summary.warningIssueCount} warning</div>
                </TableCell>
                <TableCell className="px-3 py-3">{updatedAt ? formatDistanceToNowLabel(updatedAt) : "Unknown"}</TableCell>
                <TableCell className="px-3 py-3">
                  <div className="flex flex-wrap items-center gap-2">
                    <Link
                      href={`/quotations/normalizations/${row.id}`}
                      className="rounded-md border px-3 py-2 font-medium hover:bg-muted"
                      aria-label={`Open normalization workspace for ${row.source.vendorName ?? row.id}`}
                    >
                      Open
                    </Link>
                    {row.permissions.canRetry && versionId ? (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={retryingVersionId === versionId}
                        onClick={() => void onRetry(row)}
                      >
                        <RotateCw className="mr-1 h-4 w-4" aria-hidden="true" />
                        Retry normalization
                      </Button>
                    ) : null}
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">{formatQuotationNormalizationStatus(row.status)}</p>
                  {lastJobError ? <p className="mt-1 text-xs text-red-700">{lastJobError}</p> : null}
                </TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </div>
  );
}

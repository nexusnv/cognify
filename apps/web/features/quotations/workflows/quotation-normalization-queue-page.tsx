"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import type { QuotationNormalizationSummary } from "@cognify/api-client/schemas";
import { retryQuotationVersionNormalization } from "../api/quotation-normalization-api";
import { useQuotationNormalizations } from "../hooks/use-quotation-normalization-queue";
import { quotationNormalizationKeys } from "../hooks/use-quotation-normalization-queue";
import { QuotationNormalizationQueueTable } from "../tables/quotation-normalization-queue-table";

export function QuotationNormalizationQueuePage() {
  const queryClient = useQueryClient();
  const queueQuery = useQuotationNormalizations();
  const [retryVersionNumber, setRetryVersionNumber] = useState<number | null>(null);

  async function handleRetry(row: QuotationNormalizationSummary) {
    if (!row.source.versionNumber) return;
    setRetryVersionNumber(row.source.versionNumber);
    await retryQuotationVersionNormalization(row.source.versionNumber);
    await queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.all() });
  }

  if (queueQuery.isLoading) {
    return (
      <div aria-label="Loading quotation normalization queue" className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading quotation normalization queue
      </div>
    );
  }

  if (queueQuery.isError) {
    const nestedCode =
      typeof queueQuery.error === "object" &&
      queueQuery.error !== null &&
      "error" in queueQuery.error &&
      typeof queueQuery.error.error === "object" &&
      queueQuery.error.error !== null &&
      "code" in queueQuery.error.error
        ? String(queueQuery.error.error.code)
        : null;
    const nestedMessage =
      typeof queueQuery.error === "object" &&
      queueQuery.error !== null &&
      "error" in queueQuery.error &&
      typeof queueQuery.error.error === "object" &&
      queueQuery.error.error !== null &&
      "message" in queueQuery.error.error
        ? String(queueQuery.error.error.message)
        : null;
    const code = nestedCode ?? getApiErrorCode(queueQuery.error);
    const message = code === "forbidden"
      ? (nestedMessage ?? "You do not have access to quotation normalizations.")
      : (nestedMessage ?? getApiErrorMessage(queueQuery.error));

    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {message}
      </div>
    );
  }

  const rows = queueQuery.data ?? [];

  return (
    <section className="space-y-5">
      <div className="space-y-1">
        <h1 className="text-2xl font-semibold">Quotation normalizations</h1>
        <p className="text-sm text-muted-foreground">Buyer and admin review queue for current quotation version normalization records.</p>
      </div>

      {rows.length > 0 ? (
        <QuotationNormalizationQueueTable
          rows={rows}
          retryingVersionNumber={retryVersionNumber}
          onRetry={handleRetry}
        />
      ) : (
        <div className="rounded-md border p-4 text-sm text-muted-foreground">
          No quotation normalizations need review right now.
        </div>
      )}
    </section>
  );
}

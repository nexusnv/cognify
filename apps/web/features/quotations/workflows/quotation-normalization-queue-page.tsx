"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import type { QuotationNormalizationSummary } from "@cognify/api-client/schemas";
import { retryQuotationVersionNormalization } from "../api/quotation-normalization-api";
import { useQuotationNormalizations } from "../hooks/use-quotation-normalization-queue";
import { quotationNormalizationKeys } from "../hooks/use-quotation-normalization-queue";
import { QuotationNormalizationQueueTable } from "../tables/quotation-normalization-queue-table";
import { getQuotationNormalizationQueueErrorMessage } from "../utils/quotation-normalization-ui";

export function QuotationNormalizationQueuePage() {
  const queryClient = useQueryClient();
  const queueQuery = useQuotationNormalizations();
  const [retryVersionId, setRetryVersionId] = useState<string | null>(null);
  const [retryError, setRetryError] = useState<string | null>(null);

  async function handleRetry(row: QuotationNormalizationSummary) {
    const versionId = row.source.quotationVersionId;
    if (!versionId) return;

    setRetryVersionId(versionId);
    setRetryError(null);

    try {
      await retryQuotationVersionNormalization(Number(versionId));
      await queryClient.invalidateQueries({ queryKey: quotationNormalizationKeys.all() });
    } catch (error) {
      setRetryError(getQuotationNormalizationQueueErrorMessage(error));
    } finally {
      setRetryVersionId(null);
    }
  }

  if (queueQuery.isLoading) {
    return (
      <div aria-label="Loading quotation normalization queue" className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading quotation normalization queue
      </div>
    );
  }

  if (queueQuery.isError) {
    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {getQuotationNormalizationQueueErrorMessage(queueQuery.error)}
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

      {retryError ? (
        <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          Retry failed: {retryError}
        </div>
      ) : null}

      {rows.length > 0 ? (
        <QuotationNormalizationQueueTable
          rows={rows}
          retryingVersionId={retryVersionId}
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

"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import type { QuotationNormalizationSummary } from "@cognify/api-client/schemas";
import { Alert, AlertDescription, Card, CardContent } from "@cognify/ui";
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
      <Card aria-label="Loading quotation normalization queue">
        <CardContent className="py-4 text-sm text-muted-foreground">Loading quotation normalization queue</CardContent>
      </Card>
    );
  }

  if (queueQuery.isError) {
    return (
      <Alert variant="destructive"><AlertDescription>{getQuotationNormalizationQueueErrorMessage(queueQuery.error)}</AlertDescription></Alert>
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
        <Alert variant="destructive"><AlertDescription>Retry failed: {retryError}</AlertDescription></Alert>
      ) : null}

      {rows.length > 0 ? (
        <QuotationNormalizationQueueTable
          rows={rows}
          retryingVersionId={retryVersionId}
          onRetry={handleRetry}
        />
      ) : (
        <Card><CardContent className="py-4 text-sm text-muted-foreground">No quotation normalizations need review right now.</CardContent></Card>
      )}
    </section>
  );
}

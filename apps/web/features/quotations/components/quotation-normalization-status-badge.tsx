"use client";

import { Badge } from "@cognify/ui";
import type { QuotationNormalizationSummaryStatus } from "@cognify/api-client/schemas";

const variants: Record<NonNullable<QuotationNormalizationSummaryStatus>, "default" | "secondary" | "destructive" | "outline"> = {
  pending: "outline",
  processing: "secondary",
  needs_review: "destructive",
  ready_for_approval: "default",
  approved: "default",
  approved_with_warnings: "secondary",
  failed: "destructive",
  superseded: "outline",
};

const labels: Record<NonNullable<QuotationNormalizationSummaryStatus>, string> = {
  pending: "pending",
  processing: "processing",
  needs_review: "needs review",
  ready_for_approval: "ready for approval",
  approved: "approved",
  approved_with_warnings: "approved with warnings",
  failed: "failed",
  superseded: "superseded",
};

export function QuotationNormalizationStatusBadge({
  status,
}: {
  status: QuotationNormalizationSummaryStatus;
}) {
  if (!status) return null;

  return <Badge variant={variants[status]}>{labels[status]}</Badge>;
}

export function formatQuotationNormalizationStatus(status: QuotationNormalizationSummaryStatus) {
  return status ? labels[status] : "unknown";
}

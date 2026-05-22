"use client";

import { Badge } from "@cognify/ui";
import type {
  QuotationNormalizationIssueSeverity,
  QuotationNormalizationIssueStatus,
} from "@cognify/api-client/schemas";

const severityVariants: Record<NonNullable<QuotationNormalizationIssueSeverity>, "default" | "secondary" | "destructive" | "outline"> = {
  blocking: "destructive",
  warning: "secondary",
  info: "outline",
};

export function QuotationNormalizationIssueBadge({
  severity,
  status,
}: {
  severity: QuotationNormalizationIssueSeverity;
  status?: QuotationNormalizationIssueStatus;
}) {
  if (!severity) return null;

  return (
    <span className="inline-flex items-center gap-2">
      <Badge variant={severityVariants[severity]}>{severity}</Badge>
      {status ? <span className="text-xs text-muted-foreground">{status.replaceAll("_", " ")}</span> : null}
    </span>
  );
}

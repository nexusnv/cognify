"use client";

import type { QuotationNormalizationIssue } from "@cognify/api-client/schemas";
import { QuotationNormalizationIssueBadge } from "./quotation-normalization-issue-badge";

export function QuotationNormalizationIssueList({
  issues,
}: {
  issues: QuotationNormalizationIssue[];
}) {
  return (
    <section id="issues" className="rounded-md border p-4">
      <div className="space-y-1">
        <h2 className="text-base font-semibold">Issues</h2>
        <p className="text-sm text-muted-foreground">Blocking issues must be resolved before approval. Warnings can be acknowledged with an approval note.</p>
      </div>

      {issues.length > 0 ? (
        <div className="mt-4 space-y-3">
          {issues.map((issue) => (
            <article key={issue.id} className="rounded-md border p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{issue.message}</p>
                <QuotationNormalizationIssueBadge severity={issue.severity} status={issue.status} />
              </div>
              <p className="mt-1 text-sm text-muted-foreground">{issue.fieldPath ?? "General issue"}</p>
            </article>
          ))}
        </div>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">No normalization issues remain.</p>
      )}
    </section>
  );
}

"use client";

import type { QuotationNormalizationIssue } from "@cognify/api-client/schemas";
import { Card, CardContent, CardHeader, CardTitle, ScrollArea } from "@cognify/ui";
import { QuotationNormalizationIssueBadge } from "./quotation-normalization-issue-badge";

export function QuotationNormalizationIssueList({
  issues,
}: {
  issues: QuotationNormalizationIssue[];
}) {
  return (
    <Card id="issues">
      <CardHeader>
        <CardTitle className="text-base">Issues</CardTitle>
        <p className="text-sm text-muted-foreground">Blocking issues must be resolved before approval. Warnings can be acknowledged with an approval note.</p>
      </CardHeader>
      <CardContent>

      {issues.length > 0 ? (
        <ScrollArea className="mt-1 max-h-80 pr-2">
          <div className="space-y-3">
          {issues.map((issue) => (
            <article key={issue.id} className="rounded-md bg-muted/30 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{issue.message}</p>
                <QuotationNormalizationIssueBadge severity={issue.severity} status={issue.status} />
              </div>
              <p className="mt-1 text-sm text-muted-foreground">{issue.fieldPath ?? "General issue"}</p>
            </article>
          ))}
          </div>
        </ScrollArea>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">No normalization issues remain.</p>
      )}
      </CardContent>
    </Card>
  );
}

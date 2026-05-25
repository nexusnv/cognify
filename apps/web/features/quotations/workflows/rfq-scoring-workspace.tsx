"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Button } from "@cognify/ui";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { useQuotationScoringTemplates } from "../hooks/use-quotation-scoring-templates";
import { useRfqScorecard } from "../hooks/use-rfq-scorecard";
import {
  useCompleteRfqScorecard,
  useCreateRfqScorecard,
  useReopenRfqScorecard,
  useUpdateRfqScorecardScores,
} from "../hooks/use-rfq-scorecard-actions";
import { RfqScorecardCompletionBanner } from "../components/rfq-scorecard-completion-banner";
import { RfqScorecardComparisonContext } from "../components/rfq-scorecard-comparison-context";
import { RfqScorecardMatrix, scorecardDrafts } from "../components/rfq-scorecard-matrix";
import { RfqScorecardTemplatePicker } from "../components/rfq-scorecard-template-picker";
import { RfqScorecardVendorSummary } from "../components/rfq-scorecard-vendor-summary";

export function RfqScoringWorkspace({ rfqId }: { rfqId: string }) {
  const scorecardQuery = useRfqScorecard(rfqId);
  const templatesQuery = useQuotationScoringTemplates();
  const createScorecard = useCreateRfqScorecard(rfqId);
  const updateScores = useUpdateRfqScorecardScores(rfqId);
  const completeScorecard = useCompleteRfqScorecard(rfqId);
  const reopenScorecard = useReopenRfqScorecard(rfqId);
  const scorecard = scorecardQuery.data;
  const [drafts, setDrafts] = useState<Record<string, { score: string; note: string }>>({});

  useEffect(() => {
    if (scorecard) setDrafts(scorecardDrafts(scorecard));
  }, [scorecard]);

  if (scorecardQuery.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading RFQ scoring workspace</div>;
  }

  const noScorecard = scorecardQuery.isError && errorCode(scorecardQuery.error) === "not_found";
  if (noScorecard) {
    return (
      <div className="space-y-6">
        <header className="space-y-2">
          <Link className="text-sm font-medium underline-offset-4 hover:underline" href={`/quotations/comparisons/${rfqId}`}>
            Back to comparison
          </Link>
          <h1 className="text-2xl font-semibold">RFQ scoring</h1>
        </header>
        <RfqScorecardTemplatePicker
          templates={templatesQuery.data ?? []}
          isPending={createScorecard.isPending}
          onApply={(templateId) => createScorecard.mutate(templateId)}
        />
      </div>
    );
  }

  if (scorecardQuery.isError || !scorecard) {
    return (
      <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        {getApiErrorMessage(scorecardQuery.error)}
      </div>
    );
  }

  const completed = scorecard.scorecard.status === "completed";
  const readyToComplete = scorecard.completion.missingRequiredScoreCount === 0;

  return (
    <RecordWorkspaceLayout
      backHref={scorecard.comparisonContext.comparisonPath}
      backLabel="Back to comparison"
      eyebrow={scorecard.rfq.number}
      title={scorecard.rfq.title}
      status={<span className="rounded-full border px-2 py-1 text-xs font-medium">Scoring</span>}
      metadata={[
        { id: "status", label: "Scorecard status", value: scorecard.scorecard.status },
        { id: "template", label: "Template", value: scorecard.scorecard.templateName },
        { id: "vendors", label: "Vendors", value: String(scorecard.vendors.length) },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "matrix", label: "Score matrix" },
        { id: "comparison", label: "Comparison context" },
      ]}
      sidebar={<RfqScorecardComparisonContext scorecard={scorecard} />}
    >
      <div className="flex flex-wrap gap-2">
        <Link
          className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent"
          href={scorecard.comparisonContext.comparisonPath}
        >
          Back to comparison
        </Link>
        {!completed ? (
          <Button disabled={!readyToComplete || completeScorecard.isPending} onClick={() => completeScorecard.mutate()}>
            Complete scoring
          </Button>
        ) : (
          <Button variant="secondary" disabled={reopenScorecard.isPending} onClick={() => reopenScorecard.mutate()}>
            Reopen scoring
          </Button>
        )}
      </div>
      <RfqScorecardCompletionBanner scorecard={scorecard} />
      <RfqScorecardVendorSummary scorecard={scorecard} />
      <RfqScorecardMatrix
        scorecard={scorecard}
        drafts={drafts}
        readOnly={completed}
        isSaving={updateScores.isPending}
        onDraftChange={(key, draft) => setDrafts((current) => ({ ...current, [key]: draft }))}
        onSave={(entries) => updateScores.mutate(entries)}
      />
    </RecordWorkspaceLayout>
  );
}

function errorCode(error: unknown): string | null {
  return getApiErrorCode(error) ?? (typeof error === "object" && error !== null && "error" in error
    ? ((error as { error?: { code?: string } }).error?.code ?? null)
    : null);
}

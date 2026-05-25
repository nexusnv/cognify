"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Button, Textarea } from "@cognify/ui";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import type { RfqAwardRecommendation, RfqAwardRecommendationEvidenceReferenceInput } from "@cognify/api-client/schemas";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { RfqAwardDecisionSummary } from "../components/rfq-award-decision-summary";
import { RfqAwardEvidenceSelector } from "../components/rfq-award-evidence-selector";
import { RfqAwardRationaleForm } from "../components/rfq-award-rationale-form";
import { RfqAwardVendorOptionList } from "../components/rfq-award-vendor-option-list";
import { useSaveRfqAwardRecommendation, useSubmitRfqAwardRecommendation, useWithdrawRfqAwardRecommendation } from "../hooks/use-rfq-award-recommendation-actions";
import { useRfqAwardRecommendation } from "../hooks/use-rfq-award-recommendation";

type DraftState = {
  recommendedVendorId: string | null;
  recommendedQuotationId: string | null;
  recommendedQuotationVersionId: string | null;
  scorecardId: string | null;
  rationale: string;
  tradeoffSummary: string;
  riskSummary: string;
  exceptionSummary: string;
  evidenceReferences: RfqAwardRecommendationEvidenceReferenceInput[];
};

export function RfqAwardRecommendationWorkspace({ rfqId }: { rfqId: string }) {
  const query = useRfqAwardRecommendation(rfqId);
  const save = useSaveRfqAwardRecommendation(rfqId);
  const submit = useSubmitRfqAwardRecommendation(rfqId);
  const withdraw = useWithdrawRfqAwardRecommendation(rfqId);
  const [draft, setDraft] = useState<DraftState | null>(null);
  const [isDirty, setIsDirty] = useState(false);
  const [lastSyncedKey, setLastSyncedKey] = useState<string | null>(null);
  const [withdrawReason, setWithdrawReason] = useState("");
  const [lastMutation, setLastMutation] = useState<"save" | "submit" | "withdraw" | null>(null);

  useEffect(() => {
    if (!query.data) return;
    const nextKey = recommendationSyncKey(query.data);
    const shouldForceSync = save.isSuccess || submit.isSuccess || withdraw.isSuccess;
    const changedOnServer = nextKey !== lastSyncedKey;

    if (changedOnServer && (!isDirty || shouldForceSync || !draft)) {
      setDraft(buildDraftState(query.data));
      setIsDirty(false);
      setLastSyncedKey(nextKey);
      if (shouldForceSync) {
        save.reset();
        submit.reset();
        withdraw.reset();
      }
    }
  }, [query.data, draft, isDirty, lastSyncedKey, save, submit, withdraw]);

  if (query.isLoading) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading RFQ award recommendation workspace</div>;
  }

  if (query.isError || !query.data) {
    const code = getRawErrorCode(query.error) ?? getApiErrorCode(query.error);
    const message = code === "forbidden"
      ? "You do not have access to this award recommendation."
      : code === "not_found"
        ? "This award recommendation could not be found."
        : getRawErrorMessage(query.error) ?? getApiErrorMessage(query.error);
    return <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">{message}</div>;
  }

  if (!draft) {
    return <div className="rounded-md border p-4 text-sm text-muted-foreground">Loading award recommendation</div>;
  }

  const { data: context } = query;
  const recommendationStatus = context.recommendation?.status ?? "draft";
  const isPending = recommendationStatus === "pending_approval";
  const isReadOnly = isPending || !context.permissions.canManageAwardRecommendation;
  const blockingReason = submitBlockingReason(context, draft, isReadOnly);
  const selectedOption = context.vendorOptions.find((option) => option.vendorId === draft.recommendedVendorId);
  const hasVendors = context.vendorOptions.length > 0;

  const mutationError = lastMutation === "save"
    ? save.error
    : lastMutation === "submit"
      ? submit.error
      : lastMutation === "withdraw"
        ? withdraw.error
        : null;

  return (
    <RecordWorkspaceLayout
      backHref={context.links.comparison}
      backLabel="Back to comparison"
      eyebrow={context.rfq.number ?? context.rfq.id}
      title="Award recommendation"
      status={<span className="rounded-full border px-2 py-1 text-xs font-medium">Award recommendation</span>}
      metadata={[
        { id: "rfq-title", label: "RFQ title", value: context.rfq.title ?? "Unknown RFQ" },
        { id: "status", label: "Recommendation status", value: recommendationStatus },
        { id: "vendors", label: "Vendors", value: String(context.vendorOptions.length) },
        { id: "readiness", label: "Comparison", value: context.readiness.comparisonStatus },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "rationale", label: "Rationale" },
        { id: "evidence", label: "Evidence" },
      ]}
      sidebar={(
        <RfqAwardDecisionSummary
          context={context}
          submitBlockReason={blockingReason}
          draftSelection={{
            recommendedVendorId: draft.recommendedVendorId,
            recommendedQuotationVersionId: draft.recommendedQuotationVersionId,
          }}
        />
      )}
    >
      <section id="overview" className="space-y-5">
        {!hasVendors ? <div className="rounded-md border p-4 text-sm text-muted-foreground">No vendor quotations are available for recommendation.</div> : null}
        <div className="flex flex-wrap gap-2">
        <Link className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent" href={context.links.comparison}>
          Open comparison
        </Link>
        <Link className="inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium hover:bg-accent" href={context.links.scoring}>
          Open scoring
        </Link>
        <Button
          disabled={isReadOnly || save.isPending || !context.permissions.canManageAwardRecommendation}
          onClick={() => {
            setLastMutation("save");
            submit.reset();
            withdraw.reset();
            save.mutate({
              recommendedVendorId: draft.recommendedVendorId,
              recommendedQuotationId: draft.recommendedQuotationId,
              recommendedQuotationVersionId: draft.recommendedQuotationVersionId,
              scorecardId: draft.scorecardId,
              rationale: draft.rationale,
              tradeoffSummary: draft.tradeoffSummary || null,
              riskSummary: draft.riskSummary || null,
              exceptionSummary: draft.exceptionSummary || null,
              evidenceReferences: draft.evidenceReferences,
            });
          }}
        >
          Save draft
        </Button>
        <Button
          disabled={isReadOnly || !context.permissions.canSubmitAwardRecommendation || Boolean(blockingReason) || submit.isPending}
          onClick={() => {
            setLastMutation("submit");
            save.reset();
            withdraw.reset();
            submit.mutate({
              recommendedVendorId: draft.recommendedVendorId,
              recommendedQuotationId: draft.recommendedQuotationId,
              recommendedQuotationVersionId: draft.recommendedQuotationVersionId,
              scorecardId: draft.scorecardId,
              rationale: draft.rationale,
              tradeoffSummary: draft.tradeoffSummary || null,
              riskSummary: draft.riskSummary || null,
              exceptionSummary: draft.exceptionSummary || null,
              evidenceReferences: draft.evidenceReferences,
            });
          }}
        >
          Submit for approval
        </Button>
        </div>
      </section>
      {mutationError ? <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-900">{getMutationErrorMessage(mutationError)}</div> : null}
      {blockingReason ? <p className="text-sm text-amber-700">{blockingReason}</p> : null}
      <RfqAwardVendorOptionList
        options={context.vendorOptions}
        selectedVendorId={draft.recommendedVendorId}
        readOnly={isReadOnly}
        onSelect={(vendorId) => {
          const option = context.vendorOptions.find((item) => item.vendorId === vendorId);
          if (!option) return;
          setDraft((current) => current ? {
            ...current,
            recommendedVendorId: option.vendorId,
            recommendedQuotationId: option.quotationId,
            recommendedQuotationVersionId: option.quotationVersionId,
            scorecardId: context.scorecard?.id ?? null,
          } : current);
          setIsDirty(true);
        }}
      />
      <section id="rationale">
        <RfqAwardRationaleForm
          rationale={draft.rationale}
          tradeoffSummary={draft.tradeoffSummary}
          riskSummary={draft.riskSummary}
          exceptionSummary={draft.exceptionSummary}
          readOnly={isReadOnly}
          onChange={(field, value) => {
            setDraft((current) => current ? { ...current, [field]: value } : current);
            setIsDirty(true);
          }}
        />
      </section>
      <section id="evidence">
        <RfqAwardEvidenceSelector
          references={context.evidenceReferences}
          selected={draft.evidenceReferences}
          readOnly={isReadOnly}
          onChange={(selected) => {
            setDraft((current) => current ? { ...current, evidenceReferences: selected } : current);
            setIsDirty(true);
          }}
        />
      </section>
      {isPending && context.permissions.canWithdrawAwardRecommendation ? (
        <section className="rounded-md border p-4" aria-label="Withdraw recommendation">
          <h2 className="text-base font-semibold">Withdraw pending recommendation</h2>
          <Textarea aria-label="Withdrawal reason" value={withdrawReason} onChange={(event) => setWithdrawReason(event.target.value)} />
          <Button
            className="mt-3"
            disabled={!withdrawReason.trim() || withdraw.isPending}
            onClick={() => {
              setLastMutation("withdraw");
              save.reset();
              submit.reset();
              withdraw.mutate({ reason: withdrawReason.trim() });
            }}
          >
            Withdraw recommendation
          </Button>
        </section>
      ) : null}
      {selectedOption?.scorecard?.missingRequiredCount && selectedOption.scorecard.missingRequiredCount > 0 ? (
        <p className="text-sm text-amber-700">Selected vendor has missing required scores.</p>
      ) : null}
    </RecordWorkspaceLayout>
  );
}

function submitBlockingReason(
  context: NonNullable<ReturnType<typeof useRfqAwardRecommendation>["data"]>,
  draft: DraftState,
  isReadOnly: boolean,
): string | null {
  if (!context.permissions.canSubmitAwardRecommendation && !isReadOnly) {
    return "You do not have permission to submit this recommendation.";
  }
  if (!draft.recommendedVendorId || !draft.recommendedQuotationId || !draft.recommendedQuotationVersionId) {
    return "Select a recommended vendor before submitting.";
  }
  if (!draft.rationale.trim()) {
    return "Provide rationale before submitting.";
  }
  if (context.readiness.comparisonStatus !== "ready") {
    return "Comparison must be ready before submitting.";
  }
  if (context.scorecard && context.scorecard.completion.status !== "complete") {
    return "Scorecard must be completed before submission.";
  }
  return null;
}

function buildDraftState(context: RfqAwardRecommendation): DraftState {
  const recommendation = context.recommendation;
  return {
    recommendedVendorId: recommendation?.recommendedVendorId ?? null,
    recommendedQuotationId: recommendation?.recommendedQuotationId ?? null,
    recommendedQuotationVersionId: recommendation?.recommendedQuotationVersionId ?? null,
    scorecardId: recommendation?.scorecardId ?? null,
    rationale: recommendation?.rationale ?? "",
    tradeoffSummary: recommendation?.tradeoffSummary ?? "",
    riskSummary: recommendation?.riskSummary ?? "",
    exceptionSummary: recommendation?.exceptionSummary ?? "",
    evidenceReferences: context.evidenceReferences.filter((item) => item.selected).map((item) => ({
      type: item.type,
      id: item.id,
      label: item.label,
    })),
  };
}

function recommendationSyncKey(context: RfqAwardRecommendation): string {
  return JSON.stringify({
    recommendation: context.recommendation,
    selectedEvidence: context.evidenceReferences.filter((item) => item.selected).map((item) => `${item.type}:${item.id}`),
  });
}

function getMutationErrorMessage(error: unknown): string {
  const message = getRawErrorMessage(error) ?? getApiErrorMessage(error);
  if (message && message !== "Something went wrong.") {
    return message;
  }

  if (
    typeof error === "object"
    && error !== null
    && "error" in error
    && typeof (error as { error?: { message?: unknown } }).error?.message === "string"
  ) {
    return (error as { error: { message: string } }).error.message;
  }

  return message;
}

function getRawErrorCode(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const code = (error as { error?: { code?: unknown } }).error?.code;
    return typeof code === "string" ? code : null;
  }

  return null;
}

function getRawErrorMessage(error: unknown): string | null {
  if (typeof error === "object" && error !== null && "error" in error) {
    const message = (error as { error?: { message?: unknown } }).error?.message;
    return typeof message === "string" ? message : null;
  }

  return null;
}

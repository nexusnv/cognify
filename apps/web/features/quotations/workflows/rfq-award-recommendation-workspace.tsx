"use client";

import Link from "next/link";
import { useState } from "react";
import { Alert, AlertDescription, Badge, Button, Card, CardContent, CardHeader, CardTitle, Textarea } from "@cognify/ui";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import type { RfqAwardRecommendation, RfqAwardRecommendationEvidenceReferenceInput } from "@cognify/api-client/schemas";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { RfqAwardApprovalPanel } from "../components/rfq-award-approval-panel";
import { RfqAwardDecisionSummary } from "../components/rfq-award-decision-summary";
import { RfqAwardEvidenceSelector } from "../components/rfq-award-evidence-selector";
import { RfqAwardPoHandoffPanel } from "../components/rfq-award-po-handoff-panel";
import { RfqAwardRationaleForm } from "../components/rfq-award-rationale-form";
import { RfqAwardVendorOptionList } from "../components/rfq-award-vendor-option-list";
import {
  useRouteRfqAwardRecommendationApproval,
  useSaveRfqAwardRecommendation,
  useSubmitRfqAwardRecommendation,
  useWithdrawRfqAwardRecommendation,
} from "../hooks/use-rfq-award-recommendation-actions";
import {
  useRfqAwardRecommendation,
  useRfqAwardRecommendationApprovalSummary,
  useRfqAwardRecommendationPoHandoff,
} from "../hooks/use-rfq-award-recommendation";

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

  if (query.isLoading) {
    return <Card><CardContent className="py-4 text-sm text-muted-foreground">Loading RFQ award recommendation workspace</CardContent></Card>;
  }

  if (query.isError || !query.data) {
    const code = getRawErrorCode(query.error) ?? getApiErrorCode(query.error);
    const message = code === "forbidden"
      ? "You do not have access to this award recommendation."
      : code === "not_found"
        ? "This award recommendation could not be found."
        : getRawErrorMessage(query.error) ?? getApiErrorMessage(query.error);
    return <Alert variant="destructive"><AlertDescription>{message}</AlertDescription></Alert>;
  }

  return <RfqAwardRecommendationWorkspaceContent key={rfqId} initialContext={query.data} rfqId={rfqId} />;
}

function RfqAwardRecommendationWorkspaceContent({
  initialContext,
  rfqId,
}: {
  initialContext: RfqAwardRecommendation;
  rfqId: string;
}) {
  const save = useSaveRfqAwardRecommendation(rfqId);
  const submit = useSubmitRfqAwardRecommendation(rfqId);
  const withdraw = useWithdrawRfqAwardRecommendation(rfqId);
  const routeApproval = useRouteRfqAwardRecommendationApproval(rfqId);
  const approvalSummary = useRfqAwardRecommendationApprovalSummary(rfqId);
  const [context, setContext] = useState(initialContext);
  const [draft, setDraft] = useState<DraftState>(() => buildDraftState(initialContext));
  const [withdrawReason, setWithdrawReason] = useState("");
  const [lastMutation, setLastMutation] = useState<"save" | "submit" | "withdraw" | "route" | null>(null);

  const recommendationStatus = context.recommendation?.status ?? "draft";
  const showPoHandoff = recommendationStatus === "approved";
  const poHandoff = useRfqAwardRecommendationPoHandoff(rfqId, showPoHandoff);
  const isPending = recommendationStatus === "pending_approval";
  const isApprovalLocked = ["pending_approval", "approval_routed", "approved", "rejected", "changes_requested"].includes(recommendationStatus);
  const isReadOnly = isApprovalLocked || !context.permissions.canManageAwardRecommendation;
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
      status={<Badge variant="outline">Award recommendation</Badge>}
      metadata={[
        { id: "rfq-title", label: "RFQ title", value: context.rfq.title ?? "Unknown RFQ" },
        { id: "status", label: "Recommendation status", value: recommendationStatus },
        { id: "vendors", label: "Vendors", value: String(context.vendorOptions.length) },
        { id: "readiness", label: "Comparison", value: context.readiness.comparisonStatus },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "approval", label: "Approval" },
        ...(showPoHandoff ? [{ id: "po-handoff", label: "PO handoff" }] : []),
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
        {!hasVendors ? <Card><CardContent className="py-4 text-sm text-muted-foreground">No vendor quotations are available for recommendation.</CardContent></Card> : null}
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
            routeApproval.reset();
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
            }, {
              onSuccess: (nextContext) => {
                setContext(nextContext);
                setDraft(buildDraftState(nextContext));
              },
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
            routeApproval.reset();
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
            }, {
              onSuccess: (nextContext) => {
                setContext(nextContext);
                setDraft(buildDraftState(nextContext));
              },
            });
          }}
        >
          Submit for approval
        </Button>
        </div>
      </section>
      {mutationError ? <Alert variant="destructive"><AlertDescription>{getMutationErrorMessage(mutationError)}</AlertDescription></Alert> : null}
      {blockingReason ? <p className="text-sm text-amber-700">{blockingReason}</p> : null}
      <RfqAwardApprovalPanel
        recommendationStatus={recommendationStatus}
        canRoute={context.permissions.canManageAwardRecommendation}
        summary={approvalSummary.data ?? null}
        isLoading={approvalSummary.isLoading || approvalSummary.isPending}
        error={approvalSummary.error ?? (lastMutation === "route" ? routeApproval.error : null)}
        isRouting={routeApproval.isPending}
        onRoute={() => {
          setLastMutation("route");
          save.reset();
          submit.reset();
          withdraw.reset();
          routeApproval.mutate(undefined, {
            onSuccess: (summary) => {
              setContext((current) => ({
                ...current,
                recommendation: current.recommendation
                  ? {
                      ...current.recommendation,
                      status: "approval_routed",
                      approvalInstanceId: summary.instanceId,
                    }
                  : current.recommendation,
              }));
            },
          });
        }}
      />
      {showPoHandoff ? (
        <RfqAwardPoHandoffPanel
          key={`${poHandoff.data?.id ?? "none"}:${poHandoff.data?.lockVersion ?? "loading"}`}
          rfqId={rfqId}
          handoff={poHandoff.data}
          isLoading={poHandoff.isLoading || poHandoff.isPending}
          error={poHandoff.error}
        />
      ) : null}
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
          }}
        />
      </section>
      {isPending && context.permissions.canWithdrawAwardRecommendation ? (
        <Card aria-label="Withdraw recommendation">
          <CardHeader>
            <CardTitle className="text-base">Withdraw pending recommendation</CardTitle>
          </CardHeader>
          <CardContent>
          <Textarea aria-label="Withdrawal reason" value={withdrawReason} onChange={(event) => setWithdrawReason(event.target.value)} />
          <Button
            className="mt-3"
            disabled={!withdrawReason.trim() || withdraw.isPending}
            onClick={() => {
              setLastMutation("withdraw");
              save.reset();
              submit.reset();
              routeApproval.reset();
              withdraw.mutate({ reason: withdrawReason.trim() }, {
                onSuccess: (nextContext) => {
                  setContext(nextContext);
                  setDraft(buildDraftState(nextContext));
                  setWithdrawReason("");
                },
              });
            }}
          >
            Withdraw recommendation
          </Button>
          </CardContent>
        </Card>
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

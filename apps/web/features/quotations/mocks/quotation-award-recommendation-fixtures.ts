import type {
  RfqAwardRecommendation,
  RfqAwardRecommendationDecision,
  RfqAwardRecommendationEvidenceReference,
  RfqAwardRecommendationEvidenceReferenceInput,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";

type AwardRecommendationFixtureState = {
  payload: RfqAwardRecommendation;
};

const states = new Map<string, AwardRecommendationFixtureState>();

export function resetQuotationAwardRecommendationMockState(): void {
  states.clear();
  states.set("rfq-ready", { payload: buildFixture("rfq-ready") });
  states.set("rfq-draft-recommendation", { payload: buildFixture("rfq-draft-recommendation", { recommendationStatus: "draft" }) });
  states.set("rfq-pending-recommendation", {
    payload: buildFixture("rfq-pending-recommendation", { recommendationStatus: "pending_approval" }),
  });
  states.set("rfq-incomplete-scorecard", {
    payload: buildFixture("rfq-incomplete-scorecard", { scorecardStatus: "in_progress", scorecardCompletionStatus: "incomplete" }),
  });
  states.set("rfq-no-scorecard", { payload: buildFixture("rfq-no-scorecard", { withoutScorecard: true }) });
  states.set("rfq-no-vendors", { payload: buildFixture("rfq-no-vendors", { withoutVendors: true }) });
}

export function getQuotationAwardRecommendationFixture(rfqId: string): RfqAwardRecommendation | null {
  const state = states.get(rfqId);
  if (!state) return null;

  return structuredClone(state.payload);
}

export function saveQuotationAwardRecommendationFixture(
  rfqId: string,
  payload: SaveRfqAwardRecommendationRequest,
): RfqAwardRecommendation {
  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");
  if (state.payload.recommendation?.status === "pending_approval") {
    throw new Error("Pending award recommendations cannot be edited.");
  }

  const current = state.payload.recommendation ?? createEmptyRecommendation(rfqId);
  const has = (key: keyof SaveRfqAwardRecommendationRequest) => Object.prototype.hasOwnProperty.call(payload, key);
  const recommendation: RfqAwardRecommendationDecision = {
    ...current,
    status: "draft",
    recommendedVendorId: has("recommendedVendorId") ? (payload.recommendedVendorId ?? null) : current.recommendedVendorId,
    recommendedQuotationId: has("recommendedQuotationId")
      ? (payload.recommendedQuotationId ?? null)
      : current.recommendedQuotationId,
    recommendedQuotationVersionId: has("recommendedQuotationVersionId")
      ? (payload.recommendedQuotationVersionId ?? null)
      : current.recommendedQuotationVersionId,
    scorecardId: has("scorecardId") ? (payload.scorecardId ?? null) : current.scorecardId,
    rationale: has("rationale") ? (payload.rationale ?? null) : current.rationale,
    tradeoffSummary: has("tradeoffSummary") ? (payload.tradeoffSummary ?? null) : current.tradeoffSummary,
    riskSummary: has("riskSummary") ? (payload.riskSummary ?? null) : current.riskSummary,
    exceptionSummary: has("exceptionSummary") ? (payload.exceptionSummary ?? null) : current.exceptionSummary,
    updatedAt: "2026-05-26T09:30:00.000000Z",
  };

  state.payload.recommendation = recommendation;
  if (has("evidenceReferences")) {
    state.payload.evidenceReferences = applyEvidenceSelection(
      state.payload.evidenceReferences,
      payload.evidenceReferences ?? [],
      recommendation,
    );
  }

  return structuredClone(state.payload);
}

export function submitQuotationAwardRecommendationFixture(
  rfqId: string,
  payload?: SubmitRfqAwardRecommendationRequest,
): RfqAwardRecommendation {
  if (payload && Object.keys(payload).length > 0) {
    saveQuotationAwardRecommendationFixture(rfqId, payload);
  }

  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");

  if (state.payload.recommendation?.status === "pending_approval") {
    throw new Error("Recommendation already pending approval.");
  }

  if (state.payload.scorecard && state.payload.scorecard.completion.status !== "complete") {
    throw new Error("Scorecard must be complete before submitting.");
  }

  const recommendation = state.payload.recommendation;
  if (!recommendation?.recommendedVendorId || !recommendation.rationale?.trim()) {
    throw new TypeError("Recommended vendor and rationale are required.");
  }

  recommendation.status = "pending_approval";
  recommendation.submittedByUserId = "buyer-1";
  recommendation.submittedAt = "2026-05-26T10:00:00.000000Z";
  recommendation.updatedAt = "2026-05-26T10:00:00.000000Z";
  state.payload.readiness.blockingMessages = [];

  return structuredClone(state.payload);
}

export function withdrawQuotationAwardRecommendationFixture(rfqId: string, reason: string): RfqAwardRecommendation {
  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");

  const recommendation = state.payload.recommendation;
  if (!recommendation || recommendation.status !== "pending_approval") {
    throw new Error("Only pending approval recommendations can be withdrawn.");
  }

  recommendation.status = "withdrawn";
  recommendation.withdrawalReason = reason;
  recommendation.withdrawnByUserId = "buyer-1";
  recommendation.withdrawnAt = "2026-05-26T10:30:00.000000Z";
  recommendation.updatedAt = "2026-05-26T10:30:00.000000Z";

  return structuredClone(state.payload);
}

function buildFixture(
  rfqId: string,
  options?: {
    recommendationStatus?: "draft" | "pending_approval";
    scorecardStatus?: "in_progress" | "completed";
    scorecardCompletionStatus?: "incomplete" | "complete";
    withoutScorecard?: boolean;
    withoutVendors?: boolean;
  },
): RfqAwardRecommendation {
  const vendorOptions = options?.withoutVendors
    ? []
    : [
        {
          vendorId: "101",
          vendorName: "Northwind Traders",
          quotationId: "201",
          quotationNumber: "QT-2026-0101",
          quotationVersionId: "301",
          readiness: "ready",
          currency: "USD",
          totalAmount: "125000.00",
          leadTimeDays: 14,
          paymentTerms: "Net 30",
          deliveryTerms: "DDP",
          warrantyTerms: "3 years",
          complianceNotes: "No critical issues.",
          issueCounts: { blocking: 0, warning: 1, info: 2 },
          scorecard: options?.withoutScorecard ? null : { rawTotal: "84.00", weightedTotal: "86.50", missingRequiredCount: 0 },
          links: { quotationVersion: "/quotations/201/versions/301", normalization: "/quotations/normalizations/norm-101" },
        },
        {
          vendorId: "102",
          vendorName: "Contoso Supply",
          quotationId: "202",
          quotationNumber: "QT-2026-0102",
          quotationVersionId: "302",
          readiness: "ready",
          currency: "USD",
          totalAmount: "127000.00",
          leadTimeDays: 11,
          paymentTerms: "Net 45",
          deliveryTerms: "DAP",
          warrantyTerms: "2 years",
          complianceNotes: "Minor warranty exclusion.",
          issueCounts: { blocking: 0, warning: 2, info: 1 },
          scorecard: options?.withoutScorecard ? null : { rawTotal: "82.00", weightedTotal: "83.25", missingRequiredCount: 0 },
          links: { quotationVersion: "/quotations/202/versions/302", normalization: "/quotations/normalizations/norm-102" },
        },
      ];

  const recommendation =
    options?.recommendationStatus === undefined
      ? null
      : ({
          ...createEmptyRecommendation(rfqId),
          status: options.recommendationStatus,
          recommendedVendorId: "101",
          recommendedQuotationId: "201",
          recommendedQuotationVersionId: "301",
          scorecardId: options?.withoutScorecard ? null : "scorecard-1",
          rationale: "Best value overall with strong delivery confidence.",
          tradeoffSummary: "Slightly higher price with reduced implementation risk.",
          riskSummary: "No blocking risk after normalization.",
          submittedByUserId: options.recommendationStatus === "pending_approval" ? "buyer-1" : null,
          submittedAt: options.recommendationStatus === "pending_approval" ? "2026-05-26T08:30:00.000000Z" : null,
        } satisfies RfqAwardRecommendationDecision);

  const scorecard = options?.withoutScorecard
    ? null
    : {
        id: "scorecard-1",
        status: options?.scorecardStatus ?? "completed",
        completedAt: options?.scorecardStatus === "in_progress" ? null : "2026-05-25T14:20:00.000000Z",
        completion: {
          status: options?.scorecardCompletionStatus ?? "complete",
          requiredScoreCount: 8,
          completedRequiredScoreCount: options?.scorecardCompletionStatus === "incomplete" ? 6 : 8,
          missingRequiredScoreCount: options?.scorecardCompletionStatus === "incomplete" ? 2 : 0,
          scoreableVendorCount: vendorOptions.length,
        },
        vendorTotals: [
          { vendorId: "101", quotationId: "201", rawTotal: "84.00", weightedTotal: "86.50", missingRequiredCount: 0 },
          { vendorId: "102", quotationId: "202", rawTotal: "82.00", weightedTotal: "83.25", missingRequiredCount: 0 },
        ],
      };

  const evidenceReferences: RfqAwardRecommendationEvidenceReference[] = [
    { type: "quotation_version", id: "301", label: "Northwind evaluated quotation version", selected: true, vendorId: "101", quotationId: "201" },
    { type: "scorecard", id: "scorecard-1", label: "Completed scoring matrix", selected: true },
    { type: "comparison_note", id: "comparison-note-1", label: "Commercial tradeoff note", selected: false, vendorId: "101", quotationId: "201" },
  ];

  return {
    rfq: {
      id: rfqId,
      number: "RFQ-2026-0101",
      title: "Enterprise laptop refresh",
      status: "issued",
      responseDueAt: "2026-06-08T00:00:00.000000Z",
      scopeSummary: "Select preferred vendor for the 2026 refresh program.",
      requisition: null,
      project: null,
    },
    recommendation,
    vendorOptions,
    scorecard,
    readiness: {
      comparisonStatus: options?.withoutVendors ? "incomplete" : "ready",
      scoringStatus: options?.withoutScorecard
        ? "not_started"
        : scorecard?.completion.status === "complete"
          ? "complete"
          : "in_progress",
      blockingMessages: options?.withoutVendors
        ? ["No vendor quotations are available for recommendation."]
        : scorecard && scorecard.completion.status !== "complete"
          ? ["Scorecard is incomplete and must be finished before submit."]
          : [],
    },
    evidenceReferences,
    links: {
      comparison: `/quotations/comparisons/${rfqId}`,
      scoring: `/quotations/scoring/${rfqId}`,
    },
    permissions: {
      canViewAwardRecommendation: true,
      canManageAwardRecommendation: true,
      canSubmitAwardRecommendation: true,
      canWithdrawAwardRecommendation: true,
    },
  };
}

function createEmptyRecommendation(rfqId: string): RfqAwardRecommendationDecision {
  return {
    id: `award-recommendation-${rfqId}`,
    status: "draft",
    recommendedVendorId: null,
    recommendedQuotationId: null,
    recommendedQuotationVersionId: null,
    scorecardId: null,
    rationale: null,
    tradeoffSummary: null,
    riskSummary: null,
    exceptionSummary: null,
    withdrawalReason: null,
    submittedByUserId: null,
    submittedAt: null,
    withdrawnByUserId: null,
    withdrawnAt: null,
    updatedAt: "2026-05-26T08:00:00.000000Z",
  };
}

function applyEvidenceSelection(
  current: RfqAwardRecommendationEvidenceReference[],
  submitted: RfqAwardRecommendationEvidenceReferenceInput[],
  recommendation: RfqAwardRecommendationDecision,
) {
  const selectedKeys = new Set(submitted.map((item) => `${item.type}:${item.id}`));

  return current.map((item) => {
    const selected = selectedKeys.has(`${item.type}:${item.id}`);
    if (!selected) return { ...item, selected: false };

    const selectedVendorId = recommendation.recommendedVendorId ?? item.vendorId;
    const selectedQuotationId = recommendation.recommendedQuotationId ?? item.quotationId;
    const linkToSelectedVendor = item.type === "quotation_version" || item.type === "quotation_attachment";

    return {
      ...item,
      label: submitted.find((entry) => entry.type === item.type && entry.id === item.id)?.label ?? item.label,
      selected: true,
      vendorId: linkToSelectedVendor ? selectedVendorId : item.vendorId,
      quotationId: linkToSelectedVendor ? selectedQuotationId : item.quotationId,
    };
  });
}

resetQuotationAwardRecommendationMockState();

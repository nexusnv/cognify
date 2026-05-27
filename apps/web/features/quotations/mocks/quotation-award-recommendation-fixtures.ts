import type {
  ApprovalPreview,
  ApprovalSummary,
  CancelPurchaseOrderRequestHandoffRequest,
  MarkPurchaseOrderRequestHandoffReadyRequest,
  PurchaseOrderRequestHandoff,
  RfqAwardRecommendation,
  RfqAwardRecommendationDecision,
  RfqAwardRecommendationEvidenceReference,
  RfqAwardRecommendationEvidenceReferenceInput,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  UpdatePurchaseOrderRequestHandoffRequest,
} from "@cognify/api-client/schemas";

type AwardRecommendationFixtureState = {
  payload: RfqAwardRecommendation;
  handoff: PurchaseOrderRequestHandoff | null;
};

const states = new Map<string, AwardRecommendationFixtureState>();

export function resetQuotationAwardRecommendationMockState(): void {
  states.clear();
  states.set("rfq-ready", { payload: buildFixture("rfq-ready"), handoff: null });
  states.set("rfq-draft-recommendation", {
    payload: buildFixture("rfq-draft-recommendation", { recommendationStatus: "draft" }),
    handoff: null,
  });
  states.set("rfq-pending-recommendation", {
    payload: buildFixture("rfq-pending-recommendation", { recommendationStatus: "pending_approval" }),
    handoff: null,
  });
  states.set("rfq-routed-recommendation", {
    payload: buildFixture("rfq-routed-recommendation", { recommendationStatus: "approval_routed" }),
    handoff: null,
  });
  states.set("rfq-approved-recommendation", {
    payload: buildFixture("rfq-approved-recommendation", { recommendationStatus: "approved" }),
    handoff: buildPoHandoffFixture("rfq-approved-recommendation"),
  });
  states.set("rfq-rejected-recommendation", {
    payload: buildFixture("rfq-rejected-recommendation", { recommendationStatus: "rejected" }),
    handoff: null,
  });
  states.set("rfq-changes-requested-recommendation", {
    payload: buildFixture("rfq-changes-requested-recommendation", { recommendationStatus: "changes_requested" }),
    handoff: null,
  });
  states.set("rfq-no-award-policy", {
    payload: buildFixture("rfq-no-award-policy", { recommendationStatus: "pending_approval" }),
    handoff: null,
  });
  states.set("rfq-incomplete-scorecard", {
    payload: buildFixture("rfq-incomplete-scorecard", { scorecardStatus: "in_progress", scorecardCompletionStatus: "incomplete" }),
    handoff: null,
  });
  states.set("rfq-no-scorecard", { payload: buildFixture("rfq-no-scorecard", { withoutScorecard: true }), handoff: null });
  states.set("rfq-no-vendors", { payload: buildFixture("rfq-no-vendors", { withoutVendors: true }), handoff: null });
}

export function getQuotationAwardRecommendationFixture(rfqId: string): RfqAwardRecommendation | null {
  const state = states.get(rfqId);
  if (!state) return null;

  return structuredClone(state.payload);
}

export function getPurchaseOrderRequestHandoffFixture(rfqId: string): PurchaseOrderRequestHandoff | null {
  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");
  if (state.payload.recommendation?.status !== "approved") {
    throw new Error("Only approved award recommendations can create PO handoffs.");
  }

  state.handoff ??= buildPoHandoffFixture(rfqId);

  return structuredClone(state.handoff);
}

export function createPurchaseOrderRequestHandoffFixture(rfqId: string): PurchaseOrderRequestHandoff {
  const handoff = getPurchaseOrderRequestHandoffFixture(rfqId);
  if (!handoff) throw new Error("PO handoff could not be created.");

  return handoff;
}

export function updatePurchaseOrderRequestHandoffFixture(
  handoffId: string,
  payload: UpdatePurchaseOrderRequestHandoffRequest,
): PurchaseOrderRequestHandoff {
  const state = findStateByHandoffId(handoffId);
  const handoff = state.handoff;
  if (!handoff) throw new Error("PO handoff not found.");
  if (handoff.status !== "draft") throw new Error("Only draft PO handoffs can be updated.");
  assertLockVersion(handoff, payload.lockVersion);

  handoff.review = {
    requestedPoDate: Object.prototype.hasOwnProperty.call(payload, "requestedPoDate")
      ? (payload.requestedPoDate ?? null)
      : handoff.review.requestedPoDate,
    deliveryAttention: Object.prototype.hasOwnProperty.call(payload, "deliveryAttention")
      ? (payload.deliveryAttention ?? null)
      : handoff.review.deliveryAttention,
    financeNote: Object.prototype.hasOwnProperty.call(payload, "financeNote")
      ? (payload.financeNote ?? null)
      : handoff.review.financeNote,
    exportMemo: Object.prototype.hasOwnProperty.call(payload, "exportMemo")
      ? (payload.exportMemo ?? null)
      : handoff.review.exportMemo,
  };
  handoff.lockVersion += 1;

  return structuredClone(handoff);
}

export function markPurchaseOrderRequestHandoffReadyFixture(
  handoffId: string,
  payload: MarkPurchaseOrderRequestHandoffReadyRequest,
): PurchaseOrderRequestHandoff {
  const state = findStateByHandoffId(handoffId);
  const handoff = state.handoff;
  if (!handoff) throw new Error("PO handoff not found.");
  if (handoff.status !== "draft") throw new Error("Only draft PO handoffs can be marked ready.");
  assertLockVersion(handoff, payload.lockVersion);

  handoff.status = "ready";
  handoff.readyByUserId = "buyer-1";
  handoff.readyAt = "2026-05-26T12:30:00.000000Z";
  handoff.lockVersion += 1;
  handoff.permissions = { canUpdate: false, canMarkReady: false, canExport: true, canCancel: true };

  return structuredClone(handoff);
}

export function cancelPurchaseOrderRequestHandoffFixture(
  handoffId: string,
  payload: CancelPurchaseOrderRequestHandoffRequest,
): PurchaseOrderRequestHandoff {
  const state = findStateByHandoffId(handoffId);
  const handoff = state.handoff;
  if (!handoff) throw new Error("PO handoff not found.");
  assertLockVersion(handoff, payload.lockVersion);

  handoff.status = "cancelled";
  handoff.cancelledReason = payload.reason;
  handoff.lockVersion += 1;
  handoff.permissions = { canUpdate: false, canMarkReady: false, canExport: false, canCancel: false };

  return structuredClone(handoff);
}

export function exportPurchaseOrderRequestHandoffJsonFixture(handoffId: string) {
  const handoff = markHandoffExported(handoffId, "json");

  return {
    format: "json",
    exportedAt: handoff.lastExportedAt,
    handoff,
  };
}

export function exportPurchaseOrderRequestHandoffCsvFixture(handoffId: string): string {
  const handoff = markHandoffExported(handoffId, "csv");
  const source = handoff.source as {
    rfq?: { number?: string };
    vendor?: { name?: string };
  };

  return [
    "handoff_number,handoff_status,rfq_number,vendor_name,description,line_total",
    `${handoff.number},${handoff.status},${source.rfq?.number ?? ""},${source.vendor?.name ?? ""},${handoff.lines[0]?.description ?? ""},${handoff.lines[0]?.lineTotal ?? ""}`,
  ].join("\n");
}

export function saveQuotationAwardRecommendationFixture(
  rfqId: string,
  payload: SaveRfqAwardRecommendationRequest,
): RfqAwardRecommendation {
  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");
  if (state.payload.recommendation && state.payload.recommendation.status !== "draft") {
    throw new Error("Only draft award recommendations can be edited.");
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

export function previewQuotationAwardRecommendationApprovalFixture(rfqId: string): ApprovalPreview {
  const state = states.get(rfqId);
  if (!state?.payload.recommendation) throw new Error("RFQ award recommendation not found.");
  if (rfqId === "rfq-no-award-policy") throw new Error("No matching approval policy");

  return {
    matchedPolicy: {
      id: "award-policy-1",
      tenantId: "tenant-1",
      name: "Award recommendation approval",
      subjectType: "rfq_award_recommendation",
      status: "active",
    },
    matchedVersion: {
      id: "award-policy-version-1",
      tenantId: "tenant-1",
      policyId: "award-policy-1",
      versionNumber: 1,
      status: "published",
      priority: 100,
      rules: [{ field: "recommendedAmount", operator: "gte", value: 1 }],
      routeTemplate: {
        stages: [
          {
            name: "Commercial approval",
            completionRule: "all",
            approvers: [{ type: "user", userId: "user-2", label: "Priya Buyer" }],
          },
        ],
      },
      slaRules: [{ stage: "Commercial approval", dueInHours: 48 }],
    },
    matchedConditions: [
      { field: "recommendedAmount", operator: "gte", value: 1, matched: true, summary: "recommendedAmount gte 1 matched" },
    ],
    stages: [
      {
        name: "Commercial approval",
        completionRule: "all",
        approvers: [{ type: "user", userId: "user-2", label: "Priya Buyer" }],
        fallbackApprovers: [{ type: "role", role: "approver", label: "Approver fallback" }],
        dueAt: "2026-05-28T10:00:00.000Z",
        warnings: [],
      },
    ],
    warnings: [],
    estimatedDueAt: "2026-05-28T10:00:00.000Z",
    createsTasks: false,
    context: {
      tenantId: "tenant-1",
      subjectType: "rfq_award_recommendation",
      requisitionId: null,
      requesterId: null,
      amount: 125000,
      currency: "USD",
      department: null,
      costCenter: null,
      projectId: null,
      lineItemCategories: [],
      riskClassification: null,
      vendorId: state.payload.recommendation.recommendedVendorId,
      awardRecommendationId: state.payload.recommendation.id,
      rfqId,
      rfqNumber: state.payload.rfq.number,
      recommendedVendorId: state.payload.recommendation.recommendedVendorId,
      recommendedVendorName: "Northwind Traders",
      recommendedQuotationId: state.payload.recommendation.recommendedQuotationId,
      recommendedQuotationVersionId: state.payload.recommendation.recommendedQuotationVersionId,
      recommendedAmount: 125000,
      recommendedCurrency: "USD",
      scorecardId: state.payload.recommendation.scorecardId,
      scorecardWeightedTotal: 86.5,
      riskSummaryPresent: Boolean(state.payload.recommendation.riskSummary),
      exceptionSummaryPresent: Boolean(state.payload.recommendation.exceptionSummary),
    },
  };
}

export function routeQuotationAwardRecommendationApprovalFixture(rfqId: string): ApprovalSummary {
  const state = states.get(rfqId);
  if (!state?.payload.recommendation) throw new Error("RFQ award recommendation not found.");
  if (rfqId === "rfq-no-award-policy") throw new Error("No matching approval policy");

  const recommendation = state.payload.recommendation;
  if (recommendation.status !== "pending_approval" && recommendation.status !== "approval_routed") {
    throw new Error("Only pending award recommendations can be routed for approval.");
  }

  recommendation.status = "approval_routed";
  recommendation.approvalInstanceId = "award-approval-instance-1";
  recommendation.updatedAt = "2026-05-26T11:00:00.000000Z";

  return approvalSummaryForStatus("active");
}

export function getQuotationAwardRecommendationApprovalSummaryFixture(rfqId: string): ApprovalSummary | null {
  const state = states.get(rfqId);
  if (!state) throw new Error("RFQ award recommendation not found.");
  if (!state.payload.recommendation) return null;

  const status = state.payload.recommendation.status;
  if (status === "pending_approval" || status === "draft" || status === "withdrawn") return null;

  return approvalSummaryForStatus(
    status === "approval_routed"
      ? "active"
      : status === "approved"
        ? "approved"
        : status === "rejected"
          ? "rejected"
          : "changes_requested",
  );
}

function buildFixture(
  rfqId: string,
  options?: {
    recommendationStatus?: "draft" | "pending_approval" | "approval_routed" | "approved" | "rejected" | "changes_requested";
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
          approvalInstanceId: ["approval_routed", "approved", "rejected", "changes_requested"].includes(options.recommendationStatus)
            ? "award-approval-instance-1"
            : null,
          recommendedVendorId: "101",
          recommendedQuotationId: "201",
          recommendedQuotationVersionId: "301",
          scorecardId: options?.withoutScorecard ? null : "scorecard-1",
          rationale: "Best value overall with strong delivery confidence.",
          tradeoffSummary: "Slightly higher price with reduced implementation risk.",
          riskSummary: "No blocking risk after normalization.",
          submittedByUserId: options.recommendationStatus === "pending_approval" ? "buyer-1" : null,
          submittedAt: options.recommendationStatus !== "draft" ? "2026-05-26T08:30:00.000000Z" : null,
          approvedByUserId: options.recommendationStatus === "approved" ? "user-2" : null,
          approvedAt: options.recommendationStatus === "approved" ? "2026-05-26T12:00:00.000000Z" : null,
          rejectedByUserId: options.recommendationStatus === "rejected" ? "user-2" : null,
          rejectedAt: options.recommendationStatus === "rejected" ? "2026-05-26T12:00:00.000000Z" : null,
          decisionReason: options.recommendationStatus === "rejected" ? "Rationale needs commercial clarification." : null,
          changesRequestedByUserId: options.recommendationStatus === "changes_requested" ? "user-2" : null,
          changesRequestedAt: options.recommendationStatus === "changes_requested" ? "2026-05-26T12:00:00.000000Z" : null,
          changesRequestedReason: options.recommendationStatus === "changes_requested" ? "Attach final clarification." : null,
          changesRequestedFields: options.recommendationStatus === "changes_requested" ? ["evidence"] : [],
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
    approvalInstanceId: null,
    rationale: null,
    tradeoffSummary: null,
    riskSummary: null,
    exceptionSummary: null,
    withdrawalReason: null,
    submittedByUserId: null,
    submittedAt: null,
    withdrawnByUserId: null,
    withdrawnAt: null,
    approvedByUserId: null,
    approvedAt: null,
    rejectedByUserId: null,
    rejectedAt: null,
    decisionReason: null,
    changesRequestedByUserId: null,
    changesRequestedAt: null,
    changesRequestedReason: null,
    changesRequestedFields: [],
    updatedAt: "2026-05-26T08:00:00.000000Z",
  };
}

function buildPoHandoffFixture(rfqId: string): PurchaseOrderRequestHandoff {
  return {
    id: "po-handoff-1",
    number: "POH-2026-000001",
    status: "draft",
    rfqId,
    recommendationId: `award-recommendation-${rfqId}`,
    vendorId: "101",
    currency: "USD",
    totalAmount: "125000.00",
    source: {
      rfq: { number: "RFQ-2026-001" },
      vendor: { name: "Northwind Traders" },
      quotation: { number: "QT-2026-0101" },
      quotationVersion: { versionNumber: 1 },
      award: { rationale: "Best value overall with strong delivery confidence." },
    },
    lines: [
      {
        lineNumber: 1,
        description: "Managed services",
        quantity: "1.0000",
        unitOfMeasure: "EA",
        unitPrice: "125000.00",
        lineTotal: "125000.00",
        currency: "USD",
      },
    ],
    approval: { finalDecision: "approved" },
    evidence: [],
    review: {
      requestedPoDate: null,
      deliveryAttention: null,
      financeNote: null,
      exportMemo: null,
    },
    readinessWarnings: [],
    readyByUserId: null,
    readyAt: null,
    cancelledReason: null,
    lastExportFormat: null,
    lastExportedAt: null,
    lockVersion: 1,
    permissions: { canUpdate: true, canMarkReady: true, canExport: false, canCancel: true },
  };
}

function findStateByHandoffId(handoffId: string): AwardRecommendationFixtureState {
  for (const state of states.values()) {
    if (state.handoff?.id === handoffId) return state;
  }

  throw new Error("PO handoff not found.");
}

function assertLockVersion(handoff: PurchaseOrderRequestHandoff, submittedLockVersion: number): void {
  if (handoff.lockVersion !== submittedLockVersion) {
    throw new Error("The PO handoff has changed. Reload and try again.");
  }
}

function markHandoffExported(handoffId: string, format: "json" | "csv"): PurchaseOrderRequestHandoff {
  const state = findStateByHandoffId(handoffId);
  const handoff = state.handoff;
  if (!handoff) throw new Error("PO handoff not found.");
  if (handoff.status !== "ready" && handoff.status !== "exported") {
    throw new Error("Only ready or exported PO handoffs can be exported.");
  }

  handoff.status = "exported";
  handoff.lastExportFormat = format;
  handoff.lastExportedAt = "2026-05-26T12:45:00.000000Z";
  handoff.lockVersion += 1;
  handoff.permissions = { canUpdate: false, canMarkReady: false, canExport: true, canCancel: true };

  return structuredClone(handoff);
}

function approvalSummaryForStatus(status: ApprovalSummary["status"]): ApprovalSummary {
  return {
    id: "award-approval-instance-1",
    instanceId: "award-approval-instance-1",
    status,
    currentStage:
          status === "active"
        ? {
            id: "award-stage-1",
            sequence: 1,
            name: "Commercial approval",
            status: "active",
            completionRule: "all",
            dueAt: "2026-05-28T11:00:00.000Z",
            isOverdue: false,
          }
        : null,
    activeApprovers:
      status === "active"
        ? [{ id: "user-2", name: "Priya Buyer", email: "priya.buyer@acme.test", taskId: "award-task-1" }]
        : [],
    completedDecisions:
      status === "active"
        ? []
        : [
            {
              taskId: "award-task-1",
              decision: status === "changes_requested" ? "changes_requested" : status,
              reason: status === "approved" ? null : "Approval decision reason.",
              decidedAt: "2026-05-26T12:00:00.000Z",
              decidedBy: { id: "user-2", name: "Priya Buyer", email: "priya.buyer@acme.test" },
            },
          ],
    dueAt: status === "active" ? "2026-05-28T11:00:00.000Z" : null,
    isOverdue: false,
    currentUserTaskId: status === "active" ? "award-task-1" : null,
    startedAt: "2026-05-26T11:00:00.000Z",
    completedAt: status === "active" ? null : "2026-05-26T12:00:00.000Z",
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

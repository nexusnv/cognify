import { HttpResponse, http } from "msw";
import type {
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import {
  getQuotationAwardRecommendationApprovalSummaryFixture,
  getQuotationAwardRecommendationFixture,
  previewQuotationAwardRecommendationApprovalFixture,
  resetQuotationAwardRecommendationMockState,
  routeQuotationAwardRecommendationApprovalFixture,
  saveQuotationAwardRecommendationFixture,
  submitQuotationAwardRecommendationFixture,
  withdrawQuotationAwardRecommendationFixture,
} from "./quotation-award-recommendation-fixtures";

export { resetQuotationAwardRecommendationMockState };

function notFound(message = "RFQ award recommendation not found.") {
  return HttpResponse.json({ error: { code: "not_found", message } }, { status: 404 });
}

function invalidState(message: string) {
  return HttpResponse.json({ error: { code: "invalid_state", message } }, { status: 409 });
}

function validationFailed(message: string) {
  return HttpResponse.json({ error: { code: "validation_failed", message } }, { status: 422 });
}

function errorMessage(error: unknown, fallback: string): string {
  return error instanceof Error ? error.message : String(error || fallback);
}

function invalidStateOrNotFound(error: unknown, fallback: string) {
  const message = errorMessage(error, fallback);
  if (message.includes("RFQ award recommendation not found.")) {
    return notFound(message);
  }

  return invalidState(message);
}

async function readJson<T>(request: Request): Promise<T | null> {
  try {
    return (await request.json()) as T;
  } catch {
    return null;
  }
}

export const quotationAwardRecommendationHandlers = [
  http.get("/api/rfqs/:rfq/award-recommendation", ({ params }) => {
    const payload = getQuotationAwardRecommendationFixture(String(params.rfq));
    if (!payload) return notFound();

    return HttpResponse.json({ data: payload });
  }),

  http.put("/api/rfqs/:rfq/award-recommendation", async ({ params, request }) => {
    const payload = await readJson<SaveRfqAwardRecommendationRequest>(request);
    if (!payload) {
      return validationFailed("Award recommendation payload is required.");
    }

    try {
      return HttpResponse.json({ data: saveQuotationAwardRecommendationFixture(String(params.rfq), payload) });
    } catch (error) {
      return invalidStateOrNotFound(error, "Recommendation could not be saved.");
    }
  }),

  http.post("/api/rfqs/:rfq/award-recommendation/submit", async ({ params, request }) => {
    const payload = await readJson<SubmitRfqAwardRecommendationRequest>(request);
    const rfqId = String(params.rfq);
    const context = getQuotationAwardRecommendationFixture(rfqId);
    if (!context) return notFound();
    const draft = context.recommendation;
    const has = (key: keyof SubmitRfqAwardRecommendationRequest) =>
      payload !== null && Object.prototype.hasOwnProperty.call(payload, key);
    const merged = {
      recommendedVendorId: has("recommendedVendorId") ? (payload?.recommendedVendorId ?? null) : draft?.recommendedVendorId ?? null,
      recommendedQuotationId: has("recommendedQuotationId") ? (payload?.recommendedQuotationId ?? null) : draft?.recommendedQuotationId ?? null,
      recommendedQuotationVersionId: has("recommendedQuotationVersionId") ? (payload?.recommendedQuotationVersionId ?? null) : draft?.recommendedQuotationVersionId ?? null,
      rationale: has("rationale") ? (payload?.rationale ?? null) : draft?.rationale ?? null,
    };

    if (context.scorecard?.completion.status !== "complete") {
      return invalidState("Scorecard must be complete before submitting.");
    }

    if (
      !merged.recommendedVendorId
      || !merged.recommendedQuotationId
      || !merged.recommendedQuotationVersionId
      || !merged.rationale?.trim()
    ) {
      return validationFailed("Recommended vendor, quotation, quotation version, and rationale are required.");
    }

    if (draft?.status === "pending_approval") {
      return invalidState("Recommendation already pending approval.");
    }

    try {
      return HttpResponse.json({ data: submitQuotationAwardRecommendationFixture(rfqId, payload ?? undefined) });
    } catch (error) {
      if (error instanceof Error && error.message === "RFQ award recommendation not found.") {
        return notFound(error.message);
      }

      return invalidState(error instanceof Error ? error.message : "Recommendation could not be submitted.");
    }
  }),

  http.post("/api/rfqs/:rfq/award-recommendation/withdraw", async ({ params, request }) => {
    const payload = await readJson<WithdrawRfqAwardRecommendationRequest>(request);
    const reason = payload?.reason?.trim();
    if (!reason) {
      return validationFailed("A withdrawal reason is required.");
    }

    try {
      return HttpResponse.json({ data: withdrawQuotationAwardRecommendationFixture(String(params.rfq), reason) });
    } catch (error) {
      return invalidStateOrNotFound(error, "Recommendation could not be withdrawn.");
    }
  }),

  http.post("/api/rfqs/:rfq/award-recommendation/approval-route", ({ params }) => {
    try {
      return HttpResponse.json({ data: routeQuotationAwardRecommendationApprovalFixture(String(params.rfq)) });
    } catch (error) {
      return invalidStateOrNotFound(error, "Recommendation could not be routed for approval.");
    }
  }),

  http.get("/api/rfqs/:rfq/award-recommendation/approval-summary", ({ params }) => {
    try {
      return HttpResponse.json({ data: getQuotationAwardRecommendationApprovalSummaryFixture(String(params.rfq)) });
    } catch (error) {
      return invalidStateOrNotFound(error, "Approval summary could not be loaded.");
    }
  }),

  http.get("/api/rfqs/:rfq/award-recommendation/approval-preview", ({ params }) => {
    try {
      return HttpResponse.json({ data: previewQuotationAwardRecommendationApprovalFixture(String(params.rfq)) });
    } catch (error) {
      return invalidStateOrNotFound(error, "Approval route could not be previewed.");
    }
  }),
];

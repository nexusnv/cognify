import { HttpResponse, http } from "msw";
import type {
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import {
  getQuotationAwardRecommendationFixture,
  resetQuotationAwardRecommendationMockState,
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
      return invalidState(error instanceof Error ? error.message : "Recommendation could not be saved.");
    }
  }),

  http.post("/api/rfqs/:rfq/award-recommendation/submit", async ({ params, request }) => {
    const payload = await readJson<SubmitRfqAwardRecommendationRequest>(request);
    const rfqId = String(params.rfq);
    const context = getQuotationAwardRecommendationFixture(rfqId);
    if (!context) return notFound();
    const draft = context.recommendation;
    const merged = {
      recommendedVendorId: payload?.recommendedVendorId ?? draft?.recommendedVendorId ?? null,
      rationale: payload?.rationale ?? draft?.rationale ?? null,
    };

    if (context.scorecard?.completion.status !== "complete") {
      return invalidState("Scorecard must be complete before submitting.");
    }

    if (!merged.recommendedVendorId || !merged.rationale?.trim()) {
      return validationFailed("Recommended vendor and rationale are required.");
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
      return invalidState(error instanceof Error ? error.message : "Recommendation could not be withdrawn.");
    }
  }),
];

import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import {
  saveRfqAwardRecommendation,
  showRfqAwardRecommendation,
  submitRfqAwardRecommendation,
  withdrawRfqAwardRecommendation,
} from "../api/quotation-award-recommendation-api";
import {
  quotationAwardRecommendationHandlers,
  resetQuotationAwardRecommendationMockState,
} from "../mocks/quotation-award-recommendation-handlers";

describe("quotation award recommendation api", () => {
  beforeEach(() => {
    resetQuotationAwardRecommendationMockState();
    window.localStorage.clear();
    server.use(...quotationAwardRecommendationHandlers);
  });

  it("sends active tenant header when loading recommendation context", async () => {
    storeActiveTenantId("tenant-42");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/rfqs/:rfq/award-recommendation", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: { id: "ok" } });
      }),
    );

    const result = await showRfqAwardRecommendation("rfq-ready");

    expect(result).toEqual({ id: "ok" });
    expect(tenantHeader).toBe("tenant-42");
  });

  it("supports save, submit, and withdraw lifecycle", async () => {
    const saved = await saveRfqAwardRecommendation(
      "rfq-ready",
      {
        recommendedVendorId: "101",
        recommendedQuotationId: "201",
        recommendedQuotationVersionId: "301",
        scorecardId: "scorecard-1",
        rationale: "Best overall value and risk posture.",
        tradeoffSummary: "Slightly higher commercial total than lowest bidder.",
        riskSummary: "No blocking risk remains after normalization.",
        exceptionSummary: null,
        evidenceReferences: [
          { type: "quotation_version", id: "301", label: "Northwind evaluated quotation" },
          { type: "scorecard", id: "scorecard-1", label: "Completed scorecard summary" },
        ],
      },
      "tenant-1",
    );
    expect(saved.recommendation?.status).toBe("draft");
    expect(saved.recommendation?.recommendedVendorId).toBe("101");
    expect(saved.evidenceReferences).toEqual(
      expect.arrayContaining([expect.objectContaining({ type: "quotation_version", id: "301", selected: true })]),
    );

    const submitted = await submitRfqAwardRecommendation("rfq-ready", undefined, "tenant-1");
    expect(submitted.recommendation?.status).toBe("pending_approval");
    expect(submitted.recommendation?.submittedByUserId).toBe("buyer-1");

    const withdrawn = await withdrawRfqAwardRecommendation(
      "rfq-ready",
      { reason: "Additional commercial clarification required." },
      "tenant-1",
    );
    expect(withdrawn.recommendation?.status).toBe("withdrawn");
    expect(withdrawn.recommendation?.withdrawalReason).toBe("Additional commercial clarification required.");
    expect(withdrawn.recommendation?.withdrawnByUserId).toBe("buyer-1");
  });
});

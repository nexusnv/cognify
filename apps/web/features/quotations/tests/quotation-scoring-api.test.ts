import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import {
  completeRfqScorecard,
  createRfqScorecard,
  listScoringTemplates,
  reopenRfqScorecard,
  updateRfqScorecardScores,
} from "../api/quotation-scoring-api";
import { quotationScoringHandlers, resetQuotationScoringMockState } from "../mocks/quotation-scoring-handlers";

describe("quotation scoring api", () => {
  beforeEach(() => {
    resetQuotationScoringMockState();
    window.localStorage.clear();
    server.use(...quotationScoringHandlers);
  });

  it("loads scoring templates through the generated client", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/quotation-scoring/templates", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: [] });
      }),
    );

    const templates = await listScoringTemplates();

    expect(templates).toEqual([]);
    expect(tenantHeader).toBe("tenant-1");
  });

  it("applies an RFQ scorecard template", async () => {
    const scorecard = await createRfqScorecard("rfq-no-scorecard", "template-balanced", "tenant-1");

    expect(scorecard.scorecard.templateId).toBe("template-balanced");
    expect(scorecard.criteria.length).toBeGreaterThan(0);
    expect(scorecard.rfq.id).toBe("rfq-no-scorecard");
  });

  it("updates score entries", async () => {
    const scorecard = await updateRfqScorecardScores(
      "rfq-ready",
      [
        {
          criterionId: "criterion-cost",
          vendorId: "vendor-1",
          quotationId: "quotation-1",
          quotationVersionId: "version-1",
          score: 9,
          note: "Strong commercial position.",
        },
      ],
      "tenant-1",
    );

    expect(scorecard.entries).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          criterionId: "criterion-cost",
          vendorId: "vendor-1",
          score: "9.00",
          note: "Strong commercial position.",
        }),
      ]),
    );
  });

  it("completes and reopens the scorecard", async () => {
    const completed = await completeRfqScorecard("rfq-ready", "tenant-1");
    expect(completed.scorecard.status).toBe("completed");
    expect(completed.scorecard.completedAt).not.toBeNull();

    const reopened = await reopenRfqScorecard("rfq-ready", "tenant-1");
    expect(reopened.scorecard.status).toBe("in_progress");
    expect(reopened.scorecard.completedAt).toBeNull();
  });
});

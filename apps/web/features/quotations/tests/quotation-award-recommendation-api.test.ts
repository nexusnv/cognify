import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import { server } from "@/tests/msw/server";
import {
  fetchRfqAwardRecommendationApprovalSummary,
  cancelRfqAwardRecommendationPoHandoff,
  createRfqAwardRecommendationPoHandoffForRfq,
  downloadPurchaseOrderRequestHandoffCsv,
  exportRfqAwardRecommendationPoHandoffJson,
  fetchRfqAwardRecommendationPoHandoff,
  markRfqAwardRecommendationPoHandoffReady,
  previewRfqAwardRecommendationRoute,
  routeRfqAwardRecommendationApproval,
  saveRfqAwardRecommendation,
  showRfqAwardRecommendation,
  submitRfqAwardRecommendation,
  updateRfqAwardRecommendationPoHandoff,
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

  it("supports award approval route preview summary and route wrappers", async () => {
    const preview = await previewRfqAwardRecommendationRoute("rfq-pending-recommendation", "tenant-1");
    expect(preview.context).toMatchObject({ subjectType: "rfq_award_recommendation" });
    expect(preview.stages[0]?.name).toBe("Commercial approval");

    await expect(fetchRfqAwardRecommendationApprovalSummary("rfq-pending-recommendation", "tenant-1")).resolves.toBeNull();

    await expect(routeRfqAwardRecommendationApproval("rfq-pending-recommendation", "tenant-1")).resolves.toMatchObject({
      status: "active",
      currentStage: expect.objectContaining({ name: "Commercial approval" }),
    });

    await expect(fetchRfqAwardRecommendationApprovalSummary("rfq-pending-recommendation", "tenant-1")).resolves.toMatchObject({
      currentStage: expect.any(Object),
    });
  });

  it("supports PO handoff review ready and export wrappers", async () => {
    const handoff = await fetchRfqAwardRecommendationPoHandoff("rfq-approved-recommendation", "tenant-1");
    expect(handoff).toMatchObject({ id: "po-handoff-1", status: "draft", number: "POH-2026-000001" });

    await expect(createRfqAwardRecommendationPoHandoffForRfq("rfq-approved-recommendation", "tenant-1")).resolves.toMatchObject({
      id: "po-handoff-1",
    });

    const updated = await updateRfqAwardRecommendationPoHandoff(
      "po-handoff-1",
      {
        lockVersion: handoff?.lockVersion ?? 1,
        requestedPoDate: "2026-06-15",
        deliveryAttention: "Warehouse receiving",
        financeNote: "Charge to expansion budget.",
        exportMemo: "Upload to ERP batch MY-0626.",
      },
      "tenant-1",
    );
    expect(updated.review.financeNote).toBe("Charge to expansion budget.");

    const ready = await markRfqAwardRecommendationPoHandoffReady(
      "po-handoff-1",
      { lockVersion: updated.lockVersion },
      "tenant-1",
    );
    expect(ready.status).toBe("ready");

    const jsonExport = await exportRfqAwardRecommendationPoHandoffJson("po-handoff-1", "tenant-1");
    expect(jsonExport).toMatchObject({ format: "json", handoff: expect.objectContaining({ number: "POH-2026-000001" }) });

    await expect(downloadPurchaseOrderRequestHandoffCsv("po-handoff-1", "tenant-1")).resolves.toBeInstanceOf(Blob);
  });

  it("surfaces PO handoff stale lock conflicts", async () => {
    await expect(
      markRfqAwardRecommendationPoHandoffReady("po-handoff-1", { lockVersion: 99 }, "tenant-1"),
    ).rejects.toMatchObject({
      error: expect.objectContaining({ message: "The PO handoff has changed. Reload and try again." }),
    });
  });

  it("supports PO handoff cancellation wrapper", async () => {
    const handoff = await fetchRfqAwardRecommendationPoHandoff("rfq-approved-recommendation", "tenant-1");

    await expect(
      cancelRfqAwardRecommendationPoHandoff(
        "po-handoff-1",
        { lockVersion: handoff?.lockVersion ?? 1, reason: "Award replaced by corrected recommendation." },
        "tenant-1",
      ),
    ).resolves.toMatchObject({ status: "cancelled", cancelledReason: "Award replaced by corrected recommendation." });
  });
});

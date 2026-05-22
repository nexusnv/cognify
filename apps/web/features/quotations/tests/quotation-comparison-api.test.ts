import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import {
  createQuotationComparisonNote,
  deleteQuotationComparisonNote,
  showQuotationComparison,
  updateQuotationComparisonNote,
} from "../api/quotation-comparison-api";
import {
  quotationComparisonHandlers,
  resetQuotationComparisonMockState,
} from "../mocks/quotation-comparison-handlers";

describe("quotation comparison api", () => {
  beforeEach(() => {
    resetQuotationComparisonMockState();
    window.localStorage.clear();
    server.use(...quotationComparisonHandlers);
  });

  it("sends the active tenant header when loading a quotation comparison", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/rfqs/rfq-ready/comparison", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: { rfq: { id: "rfq-ready" } } });
      }),
    );

    const comparison = await showQuotationComparison("rfq-ready");

    expect(comparison).toEqual({ rfq: { id: "rfq-ready" } });
    expect(tenantHeader).toBe("tenant-1");
  });

  it("throws the backend payload for failed comparison requests", async () => {
    server.use(
      http.get("*/api/rfqs/rfq-ready/comparison", () => {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Comparison has changed." } },
          { status: 409 },
        );
      }),
    );

    await expect(showQuotationComparison("rfq-ready")).rejects.toEqual({
      error: { code: "conflict", message: "Comparison has changed." },
    });
  });

  it("creates, updates, and deletes comparison notes", async () => {
    const created = await createQuotationComparisonNote("rfq-ready", {
      section: "overall",
      note: "Include a commercial summary in the comparison.",
    });

    expect(created.note).toBe("Include a commercial summary in the comparison.");
    expect(created.section).toBe("overall");

    const updated = await updateQuotationComparisonNote("rfq-ready", created.id, {
      section: "risk",
      note: "Reframe this as a risk note.",
      vendorId: "vendor-1",
    });

    expect(updated.id).toBe(created.id);
    expect(updated.section).toBe("risk");
    expect(updated.vendorId).toBe("vendor-1");

    await deleteQuotationComparisonNote("rfq-ready", created.id);

    const comparison = await showQuotationComparison("rfq-ready");
    expect(comparison.notes.some((note) => note.id === created.id)).toBe(false);
  });

  it("returns validation payloads for malformed note requests", async () => {
    const response = await fetch("/api/rfqs/rfq-ready/comparison/notes", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ section: "overall", note: null }),
    });

    await expect(response.json()).resolves.toEqual({
      error: {
        code: "validation_failed",
        message: "A comparison note is required.",
      },
    });
    expect(response.status).toBe(422);
  });
});

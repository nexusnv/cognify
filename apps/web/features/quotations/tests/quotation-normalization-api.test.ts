import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";
import { server } from "@/tests/msw/server";
import { storeActiveTenantId } from "@/features/identity/api/identity-api";
import {
  listQuotationNormalizations,
  showQuotationNormalization,
} from "../api/quotation-normalization-api";

describe("quotation normalization api", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it("sends the active tenant header when listing normalizations", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/quotation-normalizations", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json({ data: [{ id: "norm-1" }] });
      }),
    );

    const normalizations = await listQuotationNormalizations();

    expect(normalizations).toEqual([{ id: "norm-1" }]);
    expect(tenantHeader).toBe("tenant-1");
  });

  it("throws the backend payload for failed normalization requests", async () => {
    server.use(
      http.get("*/api/quotation-normalizations/norm-1", () => {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Normalization has changed." } },
          { status: 409 },
        );
      }),
    );

    await expect(showQuotationNormalization("norm-1")).rejects.toEqual({
      error: { code: "conflict", message: "Normalization has changed." },
    });
  });
});

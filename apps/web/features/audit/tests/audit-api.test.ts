import { http, HttpResponse } from "msw";
import { beforeEach, describe, expect, it } from "vitest";

import {
  getApiErrorCode,
  getApiErrorMessage,
  getApiValidationErrors,
  type ApiClientError,
} from "@cognify/api-client";

import { storeActiveTenantId } from "../../identity/api/identity-api";
import { fetchAuditEvents } from "../api/audit-api";
import { auditEventsFixture } from "../mocks/audit-fixtures";
import { server } from "../../../tests/msw/server";

describe("audit api", () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it("fetches OpenAPI-shaped audit events", async () => {
    const response = await fetchAuditEvents({ action: "requisition.submitted" });

    expect(response.data).toHaveLength(1);
    expect(response.data[0]?.action).toBe("requisition.submitted");
    expect(response.data[0]?.subject.type).toBe("requisition");
  });

  it("sends the active tenant header", async () => {
    storeActiveTenantId("tenant-1");
    let tenantHeader: string | null = null;

    server.use(
      http.get("*/api/audit/events", ({ request }) => {
        tenantHeader = request.headers.get("X-Tenant-Id");
        return HttpResponse.json(auditEventsFixture);
      }),
    );

    await fetchAuditEvents();

    expect(tenantHeader).toBe("tenant-1");
  });

  it("parses normalized validation errors", () => {
    const error: ApiClientError = {
      status: 422,
      headers: new Headers(),
      data: {
        error: {
          code: "validation_failed",
          message: "The given data was invalid.",
          details: {
            fields: {
              title: ["The title field is required."],
            },
          },
          requestId: "req_test_123",
        },
      },
    };

    expect(getApiErrorCode(error)).toBe("validation_failed");
    expect(getApiErrorMessage(error)).toBe("The given data was invalid.");
    expect(getApiValidationErrors(error)).toEqual({
      title: ["The title field is required."],
    });
  });
});

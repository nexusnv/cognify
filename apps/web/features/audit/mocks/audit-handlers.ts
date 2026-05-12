import { http, HttpResponse } from "msw";
import type { AuditEventListResponse } from "@cognify/api-client/schemas";

import { auditEventsFixture } from "./audit-fixtures";

export const auditHandlers = [
  http.get("*/api/audit/events", ({ request }) => {
    const url = new URL(request.url);
    const action = url.searchParams.get("action");

    if (action && action !== "requisition.submitted") {
      const response = {
        data: [],
        meta: {
          currentPage: 1,
          perPage: 25,
          total: 0,
          lastPage: 1,
        },
      } satisfies AuditEventListResponse;

      return HttpResponse.json(response);
    }

    return HttpResponse.json(auditEventsFixture);
  }),
];

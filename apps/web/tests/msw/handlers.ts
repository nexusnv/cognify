import { http, HttpResponse } from "msw";
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";
import { auditHandlers } from "../../features/audit/mocks/audit-handlers";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...auditHandlers,
];

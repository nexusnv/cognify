import { http, HttpResponse } from "msw";
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";
import { auditHandlers } from "../../features/audit/mocks/audit-handlers";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { notificationHandlers } from "../../features/notifications/mocks/notification-handlers";
import { systemReadinessHandlers } from "@/features/system-readiness/mocks/system-readiness-handlers";
import { searchHandlers } from "../../features/search/mocks/search-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";
import { projectHandlers } from "@/features/projects/mocks/project-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...requisitionsHandlers,
  ...projectHandlers,
  ...searchHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...notificationHandlers,
  ...systemReadinessHandlers,
  ...auditHandlers,
];

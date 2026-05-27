import { http, HttpResponse } from "msw";
import { approvalHandlers } from "@/features/approvals/mocks/approval-handlers";
import { attachmentHandlers } from "../../features/attachments/mocks/attachments-handlers";
import { auditHandlers } from "../../features/audit/mocks/audit-handlers";
import { identityHandlers } from "../../features/identity/mocks/identity-handlers";
import { notificationHandlers } from "../../features/notifications/mocks/notification-handlers";
import { systemReadinessHandlers } from "@/features/system-readiness/mocks/system-readiness-handlers";
import { searchHandlers } from "../../features/search/mocks/search-handlers";
import { requisitionsHandlers } from "../../features/requisitions/mocks/requisitions-handlers";
import { projectHandlers } from "@/features/projects/mocks/project-handlers";
import { vendorPortalHandlers } from "@/features/vendor-portal/mocks/vendor-portal-handlers";
import { sourcingHandlers } from "@/features/sourcing/mocks/sourcing-handlers";
import { rfqHandlers } from "@/features/sourcing/mocks/rfq-handlers";
import { vendorHandlers } from "@/features/sourcing/mocks/vendor-handlers";
import { rfqInvitationHandlers } from "@/features/sourcing/mocks/rfq-invitation-handlers";
import { quotationNormalizationHandlers } from "@/features/quotations/mocks/quotation-normalization-handlers";
import { procurementCalendarHandlers } from "@/features/procurement-calendar/mocks/procurement-calendar-handlers";

export const handlers = [
  http.get("/api/health", () => {
    return HttpResponse.json({
      status: "ok",
      service: "cognify-api",
    });
  }),
  ...approvalHandlers,
  ...requisitionsHandlers,
  ...projectHandlers,
  ...vendorPortalHandlers,
  ...sourcingHandlers,
  ...vendorHandlers,
  ...rfqHandlers,
  ...rfqInvitationHandlers,
  ...quotationNormalizationHandlers,
  ...procurementCalendarHandlers,
  ...searchHandlers,
  ...attachmentHandlers,
  ...identityHandlers,
  ...notificationHandlers,
  ...systemReadinessHandlers,
  ...auditHandlers,
];

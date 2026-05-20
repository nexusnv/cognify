import type { RfqInvitation } from "@cognify/api-client/schemas";
import { vendorPickerFixtures } from "./vendor-fixtures";

const northwind = vendorPickerFixtures[0];

export const rfqInvitationFixtures: RfqInvitation[] = [
  {
    id: "invitation-1",
    tenantId: "1",
    rfqId: "rfq-1",
    status: "sent",
    vendor: northwind,
    contactName: northwind.defaultContact.name,
    contactEmail: northwind.defaultContact.email,
    message: "Please review the RFQ package and confirm your interest.",
    responseDueAt: "2026-06-30T17:00:00.000000Z",
    sentAt: "2026-05-19T10:00:00.000000Z",
    acknowledgedAt: null,
    declinedAt: null,
    expiredAt: null,
    cancelledAt: null,
    cancelReason: null,
    createdAt: "2026-05-19T10:00:00.000000Z",
    updatedAt: "2026-05-19T10:00:00.000000Z",
    permissions: {
      canResend: true,
      canCancel: true,
      canUpdateStatus: true,
    },
  },
];

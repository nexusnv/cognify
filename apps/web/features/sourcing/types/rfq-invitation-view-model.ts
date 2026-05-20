import type { RfqInvitation, RfqInvitationStatus } from "@cognify/api-client/schemas";

export type RfqInvitationViewModel = RfqInvitation & {
  statusLabel: string;
  activitySummary: string;
  contactSummary: string;
};

export function toRfqInvitationViewModel(invitation: RfqInvitation): RfqInvitationViewModel {
  return {
    ...invitation,
    statusLabel: formatRfqInvitationStatus(invitation.status),
    activitySummary: invitationActivitySummary(invitation.status),
    contactSummary: [invitation.contactName, invitation.contactEmail].filter(Boolean).join(" · ") || "No contact recorded",
  };
}

export function formatRfqInvitationStatus(status: RfqInvitationStatus | string) {
  return status.replaceAll("_", " ");
}

export function invitationActivitySummary(status: RfqInvitationStatus | string) {
  if (status === "pending" || status === "sent") return "Invitation recorded";
  if (status === "acknowledged") return "Vendor acknowledged";
  if (status === "declined") return "Vendor declined";
  if (status === "expired") return "Invitation expired";
  if (status === "cancelled") return "Invitation cancelled";

  return "Invitation updated";
}

export function isActiveRfqInvitationStatus(status: RfqInvitationStatus | string) {
  return status === "pending" || status === "sent";
}

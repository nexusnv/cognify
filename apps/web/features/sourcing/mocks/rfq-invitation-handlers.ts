import { http, HttpResponse } from "msw";
import type {
  CancelRfqInvitationRequest,
  CreateRfqInvitationsRequest,
  CreateRfqInvitationsRequestContactOverridesOneOfItem,
  RfqInvitation,
  UpdateRfqInvitationStatusRequest,
} from "@cognify/api-client/schemas";
import { isActiveRfqInvitationStatus } from "../types/rfq-invitation-view-model";
import { vendorPickerFixtures } from "./vendor-fixtures";
import { rfqInvitationFixtures } from "./rfq-invitation-fixtures";

let rfqInvitations = structuredClone(rfqInvitationFixtures);
let invitationSequence = rfqInvitationFixtures.length;

export function resetRfqInvitationMockState() {
  rfqInvitations = structuredClone(rfqInvitationFixtures);
  invitationSequence = rfqInvitationFixtures.length;
}

function cloneInvitation(invitation: RfqInvitation): RfqInvitation {
  return structuredClone(invitation);
}

function notFound() {
  return HttpResponse.json({ error: { code: "not_found", message: "RFQ invitation not found." } }, { status: 404 });
}

function conflict(message: string) {
  return HttpResponse.json({ error: { code: "conflict", message } }, { status: 409 });
}

function validationFailed(message: string) {
  return HttpResponse.json({ error: { code: "validation_failed", message } }, { status: 422 });
}

function forbidden(message: string) {
  return HttpResponse.json({ error: { code: "forbidden", message } }, { status: 403 });
}

function buildInvitation(
  rfqId: string,
  vendorId: string,
  overrides: Partial<RfqInvitation> = {},
  contactOverrides: CreateRfqInvitationsRequestContactOverridesOneOfItem[] | null = null,
  requestValues?: Pick<CreateRfqInvitationsRequest, "message" | "responseDueAt">,
): RfqInvitation {
  const vendor = vendorPickerFixtures.find((item) => item.id === vendorId);
  if (!vendor) {
    throw new Error(`Missing vendor fixture for ${vendorId}`);
  }

  const contactOverride = contactOverrides?.find((item) => item.vendorId === vendorId);
  const id = `invitation-${++invitationSequence}`;
  const now = "2026-05-19T12:00:00.000000Z";

  return {
    id,
    tenantId: "1",
    rfqId,
    status: "sent",
    vendor,
    contactName: contactOverride?.contactName ?? vendor.defaultContact.name,
    contactEmail: contactOverride?.contactEmail ?? vendor.defaultContact.email,
    message: requestValues?.message ?? null,
    responseDueAt: requestValues?.responseDueAt ?? null,
    sentAt: now,
    acknowledgedAt: null,
    declinedAt: null,
    expiredAt: null,
    cancelledAt: null,
    cancelReason: null,
    portalAccess: {
      hasToken: true,
      expiresAt: requestValues?.responseDueAt ?? "2026-06-30T17:00:00.000000Z",
      lastViewedAt: null,
      viewCount: 0,
    },
    createdAt: now,
    updatedAt: now,
    permissions: {
      canResend: true,
      canCancel: true,
      canUpdateStatus: true,
    },
    ...overrides,
  };
}

function listInvitations(rfqId: string) {
  return rfqInvitations.filter((invitation) => invitation.rfqId === rfqId).map(cloneInvitation);
}

export const rfqInvitationHandlers = [
  http.get("/api/rfqs/:rfqId/invitations", ({ params }) => {
    return HttpResponse.json({ data: listInvitations(String(params.rfqId)) });
  }),

  http.post("/api/rfqs/:rfqId/invitations", async ({ params, request }) => {
    const rfqId = String(params.rfqId);
    const payload = (await request.json()) as CreateRfqInvitationsRequest;

    if (!payload.vendorIds?.length) {
      return validationFailed("Select at least one vendor.");
    }

    const duplicateVendor = payload.vendorIds.find((vendorId) =>
      rfqInvitations.some(
        (invitation) =>
          invitation.rfqId === rfqId &&
          invitation.vendor.id === vendorId &&
          isActiveRfqInvitationStatus(invitation.status),
      ),
    );

    if (duplicateVendor) {
      const vendor = vendorPickerFixtures.find((item) => item.id === duplicateVendor);
      return conflict(
        `${vendor?.name ?? "This vendor"} already has an active invitation for this RFQ.`,
      );
    }

    const created = payload.vendorIds.map((vendorId) =>
      buildInvitation(rfqId, vendorId, {}, payload.contactOverrides, payload),
    );
    rfqInvitations = [...created, ...rfqInvitations];

    return HttpResponse.json({ data: listInvitations(rfqId) }, { status: 201 });
  }),

  http.post("/api/rfq-invitations/:invitationId/resend", ({ params }) => {
    const existing = rfqInvitations.find((invitation) => invitation.id === params.invitationId);
    if (!existing) return notFound();
    if (!existing.permissions.canResend) {
      return forbidden("This invitation cannot be resent.");
    }

    const resent: RfqInvitation = {
      ...existing,
      status: "sent",
      sentAt: "2026-05-19T12:30:00.000000Z",
      updatedAt: "2026-05-19T12:30:00.000000Z",
      permissions: {
        canResend: true,
        canCancel: true,
        canUpdateStatus: true,
      },
    };

    rfqInvitations = rfqInvitations.map((invitation) =>
      invitation.id === resent.id ? resent : invitation,
    );

    return HttpResponse.json({ data: cloneInvitation(resent) });
  }),

  http.post("/api/rfq-invitations/:invitationId/portal-link", ({ params }) => {
    const existing = rfqInvitations.find((invitation) => invitation.id === params.invitationId);
    if (!existing) return notFound();
    if (!["sent", "acknowledged"].includes(existing.status)) {
      return conflict("This invitation is not available in the vendor portal.");
    }

    const expiresAt = existing.portalAccess?.expiresAt ?? existing.responseDueAt ?? "2026-06-30T17:00:00.000000Z";
    const updated: RfqInvitation = {
      ...existing,
      portalAccess: {
        hasToken: true,
        expiresAt,
        lastViewedAt: existing.portalAccess?.lastViewedAt ?? null,
        viewCount: existing.portalAccess?.viewCount ?? 0,
      },
    };

    rfqInvitations = rfqInvitations.map((invitation) =>
      invitation.id === updated.id ? updated : invitation,
    );

    return HttpResponse.json({
      data: {
        invitationId: updated.id,
        token: "vendor-portal-valid-token",
        portalUrl: "/vendor/rfq-invitations/vendor-portal-valid-token",
        expiresAt,
      },
    });
  }),

  http.post("/api/rfq-invitations/:invitationId/cancel", async ({ params, request }) => {
    const existing = rfqInvitations.find((invitation) => invitation.id === params.invitationId);
    if (!existing) return notFound();
    if (!existing.permissions.canCancel) {
      return forbidden("This invitation cannot be cancelled.");
    }

    const payload = (await request.json()) as CancelRfqInvitationRequest;
    if (!payload.cancelReason?.trim()) {
      return validationFailed("Cancel reason is required.");
    }

    const cancelled: RfqInvitation = {
      ...existing,
      status: "cancelled",
      cancelReason: payload.cancelReason,
      cancelledAt: "2026-05-19T12:45:00.000000Z",
      updatedAt: "2026-05-19T12:45:00.000000Z",
      permissions: {
        canResend: false,
        canCancel: false,
        canUpdateStatus: false,
      },
    };

    rfqInvitations = rfqInvitations.map((invitation) =>
      invitation.id === cancelled.id ? cancelled : invitation,
    );

    return HttpResponse.json({ data: cloneInvitation(cancelled) });
  }),

  http.patch("/api/rfq-invitations/:invitationId/status", async ({ params, request }) => {
    const existing = rfqInvitations.find((invitation) => invitation.id === params.invitationId);
    if (!existing) return notFound();
    if (!existing.permissions.canUpdateStatus) {
      return forbidden("This invitation status cannot be updated.");
    }

    const payload = (await request.json()) as UpdateRfqInvitationStatusRequest;
    if (!["acknowledged", "declined", "expired"].includes(payload.status)) {
      return validationFailed("Select a valid invitation status.");
    }
    const timestamp = "2026-05-19T13:00:00.000000Z";

    const updated: RfqInvitation = {
      ...existing,
      status: payload.status,
      acknowledgedAt:
        payload.status === "acknowledged" ? timestamp : existing.acknowledgedAt,
      declinedAt: payload.status === "declined" ? timestamp : existing.declinedAt,
      expiredAt: payload.status === "expired" ? timestamp : existing.expiredAt,
      updatedAt: timestamp,
      permissions: {
        canResend: false,
        canCancel: false,
        canUpdateStatus: false,
      },
    };

    rfqInvitations = rfqInvitations.map((invitation) =>
      invitation.id === updated.id ? updated : invitation,
    );

    return HttpResponse.json({ data: cloneInvitation(updated) });
  }),
];

import { http, HttpResponse } from "msw";
import type {
  CancelRfqInvitationRequest,
  Attachment,
  CreateRfqInvitationsRequest,
  CreateRfqInvitationsRequestContactOverridesOneOfItem,
  Quotation,
  QuotationVersion,
  RfqInvitation,
  SaveQuotationManualEntryRequest,
  SaveQuotationLineItemRequest,
  UpdateRfqInvitationStatusRequest,
} from "@cognify/api-client/schemas";
import { isActiveRfqInvitationStatus } from "../types/rfq-invitation-view-model";
import { vendorPickerFixtures } from "./vendor-fixtures";
import { rfqInvitationFixtures } from "./rfq-invitation-fixtures";

let rfqInvitations = structuredClone(rfqInvitationFixtures);
let invitationSequence = rfqInvitationFixtures.length;
let quotationSequence = 0;
let quotationAttachmentSequence = 0;
type QuotationState = {
  quotation: Quotation | null;
  versions: QuotationVersion[];
};

let quotationByInvitationId = new Map<string, QuotationState>();

export function resetRfqInvitationMockState() {
  rfqInvitations = structuredClone(rfqInvitationFixtures);
  invitationSequence = rfqInvitationFixtures.length;
  quotationSequence = 0;
  quotationAttachmentSequence = 0;
  quotationByInvitationId = new Map(
    rfqInvitationFixtures.map((invitation) => [invitation.id, { quotation: null, versions: [] }] as const),
  );
}

resetRfqInvitationMockState();

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

function findInvitation(invitationId: string) {
  return rfqInvitations.find((invitation) => invitation.id === invitationId) ?? null;
}

function findQuotationById(quotationId: string) {
  for (const state of quotationByInvitationId.values()) {
    if (state.quotation?.id === quotationId) return state.quotation;
  }

  return null;
}

function findQuotationStateByQuotationId(quotationId: string) {
  for (const state of quotationByInvitationId.values()) {
    if (state.quotation?.id === quotationId) return state;
  }

  return null;
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
    created.forEach((invitation) => {
      quotationByInvitationId.set(invitation.id, { quotation: null, versions: [] });
    });

    return HttpResponse.json({ data: listInvitations(rfqId) }, { status: 201 });
  }),

  http.get("/api/rfq-invitations/:invitationId/quotation", ({ params }) => {
    const invitation = findInvitation(String(params.invitationId));
    if (!invitation) return notFound();

    return HttpResponse.json({
      data: cloneQuotation(quotationByInvitationId.get(invitation.id)?.quotation ?? null),
    });
  }),

  http.get("/api/quotations/:quotationId/versions", ({ params }) => {
    const state = findQuotationStateByQuotationId(String(params.quotationId));
    if (!state) return quotationNotFound();

    return HttpResponse.json({ data: structuredClone(state.versions) });
  }),

  http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
    const state = findQuotationStateByQuotationId(String(params.quotationId));
    if (!state) return quotationNotFound();

    const versionNumber = Number(params.versionId);
    const version =
      state.versions.find((candidate) => candidate.versionNumber === versionNumber) ??
      state.versions.find((candidate) => candidate.id === String(params.versionId)) ??
      null;
    if (!version) return quotationVersionNotFound();

    return HttpResponse.json({ data: structuredClone(version) });
  }),

  http.put("/api/rfq-invitations/:invitationId/quotation/manual-entry", async ({ params, request }) => {
    const invitation = findInvitation(String(params.invitationId));
    if (!invitation) return notFound();
    if (!["sent", "acknowledged"].includes(invitation.status)) {
      return forbidden("Structured quotation entry is only available for sent or acknowledged invitations.");
    }

    const payload = (await request.json()) as SaveQuotationManualEntryRequest;
    const state = quotationByInvitationId.get(invitation.id) ?? { quotation: null, versions: [] };

    if (!state.quotation && isBlankManualEntryPayload(payload)) {
      const quotation = buildManualEntryQuotation(++quotationSequence, invitation);
      const decoratedQuotation = decorateQuotationWithVersionSummary(quotation, []);

      quotationByInvitationId.set(invitation.id, {
        quotation: decoratedQuotation,
        versions: [],
      });

      return HttpResponse.json({ data: structuredClone(decoratedQuotation) });
    }

    const quotation = state.quotation ?? buildManualEntryQuotation(++quotationSequence, invitation);
    const updated = updateQuotationManualEntry(quotation, payload, "buyer_upload");
    const versions = appendQuotationVersion(state.versions, updated, "buyer_upload");
    const decoratedQuotation = decorateQuotationWithVersionSummary(updated, versions);

    quotationByInvitationId.set(updated.rfqInvitationId, {
      quotation: decoratedQuotation,
      versions,
    });

    return HttpResponse.json({ data: structuredClone(decoratedQuotation) });
  }),

  http.get("/api/quotations/:quotationId/attachments", ({ params }) => {
    const quotation = findQuotationById(String(params.quotationId));
    if (!quotation) return notFound();

    return HttpResponse.json({ data: quotation.attachments.map((attachment) => structuredClone(attachment)) });
  }),

  http.put("/api/quotations/:quotationId/manual-entry", async ({ params, request }) => {
    const quotationId = String(params.quotationId);
    const payload = (await request.json()) as SaveQuotationManualEntryRequest;
    const existingQuotation = findQuotationById(quotationId);

    if (!existingQuotation) return notFound();
    const invitation = findInvitation(existingQuotation.rfqInvitationId);
    if (!invitation) return notFound();
    if (!["sent", "acknowledged"].includes(invitation.status)) {
      return forbidden("Structured quotation entry is only available for sent or acknowledged invitations.");
    }

    const state = quotationByInvitationId.get(existingQuotation.rfqInvitationId) ?? { quotation: existingQuotation, versions: [] };
    const updated = updateQuotationManualEntry(existingQuotation, payload, "buyer_upload");
    const versions = appendQuotationVersion(state.versions, updated, "buyer_upload");
    const decoratedQuotation = decorateQuotationWithVersionSummary(updated, versions);
    quotationByInvitationId.set(updated.rfqInvitationId, {
      quotation: decoratedQuotation,
      versions,
    });

    return HttpResponse.json({ data: structuredClone(decoratedQuotation) });
  }),

  http.post("/api/rfq-invitations/:invitationId/quotation/attachments", async ({ params, request }) => {
    const invitation = findInvitation(String(params.invitationId));
    if (!invitation) return notFound();
    if (!["sent", "acknowledged"].includes(invitation.status)) {
      return forbidden("Quotation uploads are only available for sent or acknowledged invitations.");
    }

    const upload = await parseQuotationUpload(request);
    if (!upload) {
      return validationFailed("File is required.");
    }

    const state = quotationByInvitationId.get(invitation.id) ?? { quotation: null, versions: [] };
    const now = "2026-05-19T12:15:00.000000Z";
    const quotationId = state.quotation?.id ?? String(quotationSequence + 1);
    const attachment = buildQuotationAttachment(++quotationAttachmentSequence, quotationId, upload, now);
    const quotation = state.quotation
      ? updateQuotation(state.quotation, attachment, now)
      : buildQuotation(++quotationSequence, invitation, attachment, now);
    const versions = appendQuotationVersion(state.versions, quotation, "buyer_upload");
    const decoratedQuotation = decorateQuotationWithVersionSummary(quotation, versions);

    quotationByInvitationId.set(invitation.id, {
      quotation: decoratedQuotation,
      versions,
    });

    return HttpResponse.json({ data: cloneQuotation(decoratedQuotation) }, { status: 201 });
  }),

  http.post("/api/rfq-invitations/:invitationId/resend", ({ params }) => {
    const existing = findInvitation(String(params.invitationId));
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
    const existing = findInvitation(String(params.invitationId));
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
    const existing = findInvitation(String(params.invitationId));
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
    const existing = findInvitation(String(params.invitationId));
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

function buildQuotation(
  sequence: number,
  invitation: RfqInvitation,
  attachment: Attachment,
  now: string,
): Quotation {
  return {
    id: String(sequence),
    rfqId: invitation.rfqId,
    vendorId: invitation.vendor.id,
    rfqInvitationId: invitation.id,
    number: `Q-${String(sequence).padStart(4, "0")}`,
    status: "received",
    submissionSource: "buyer_upload",
    submittedAt: now,
    latestReceivedAt: now,
    fileCount: 1,
    submittedByUser: {
      id: "user-1",
      name: "Maya Tan",
    },
    submittedByVendorContact: null,
    attachments: [attachment],
    manualEntry: emptyManualEntry(),
    lineItems: [],
    completeness: {
      isComplete: false,
      missingFields: ["currency", "totalAmount", "lineItems"],
      lineItemCount: 0,
    },
    permissions: {
      canUploadAttachment: true,
      canViewAttachments: true,
      canEditManualEntry: true,
      canCreateRevision: true,
    },
    versionCount: 0,
    currentVersion: null,
  };
}

function updateQuotation(existing: Quotation, attachment: Attachment, now: string): Quotation {
  const attachments = [attachment, ...existing.attachments];

  return {
    ...existing,
    latestReceivedAt: now,
    fileCount: attachments.length,
    attachments,
    versionCount: existing.versionCount,
    currentVersion: existing.currentVersion,
  };
}

function buildManualEntryQuotation(sequence: number, invitation: RfqInvitation): Quotation {
  const now = "2026-05-19T12:20:00.000000Z";

  return {
    id: String(sequence),
    rfqId: invitation.rfqId,
    vendorId: invitation.vendor.id,
    rfqInvitationId: invitation.id,
    number: `Q-${String(sequence).padStart(4, "0")}`,
    status: "received",
    submissionSource: "buyer_upload",
    submittedAt: now,
    latestReceivedAt: now,
    fileCount: 0,
    submittedByUser: {
      id: "user-1",
      name: "Maya Tan",
    },
    submittedByVendorContact: null,
    attachments: [],
    manualEntry: emptyManualEntry(),
    lineItems: [],
    completeness: {
      isComplete: false,
      missingFields: ["currency", "totalAmount", "lineItems"],
      lineItemCount: 0,
    },
    permissions: {
      canUploadAttachment: true,
      canViewAttachments: true,
      canEditManualEntry: true,
      canCreateRevision: true,
    },
    versionCount: 0,
    currentVersion: null,
  };
}

function updateQuotationManualEntry(
  quotation: Quotation,
  payload: SaveQuotationManualEntryRequest,
  submissionSource: Quotation["submissionSource"],
): Quotation {
  const now = "2026-05-19T12:20:00.000000Z";
  const lineItems = buildQuotationLineItems(payload.lineItems ?? []);
  const completeness = buildCompleteness(payload, lineItems.length);

  return {
    ...quotation,
    status: "received",
    submissionSource,
    submittedAt: quotation.submittedAt ?? now,
    latestReceivedAt: now,
    manualEntry: {
      quotationReference: payload.quotationReference ?? null,
      quotedAt: payload.quotedAt ?? null,
      validUntil: payload.validUntil ?? null,
      currency: payload.currency ?? null,
      subtotalAmount: payload.subtotalAmount ?? null,
      taxAmount: payload.taxAmount ?? null,
      freightAmount: payload.freightAmount ?? null,
      discountAmount: payload.discountAmount ?? null,
      totalAmount: payload.totalAmount ?? null,
      paymentTerms: payload.paymentTerms ?? null,
      deliveryTerms: payload.deliveryTerms ?? null,
      leadTimeDays: payload.leadTimeDays ?? null,
      warrantyTerms: payload.warrantyTerms ?? null,
      exclusions: payload.exclusions ?? null,
      complianceNotes: payload.complianceNotes ?? null,
      buyerNotes: payload.buyerNotes ?? null,
      vendorNotes: payload.vendorNotes ?? null,
    },
    lineItems,
    completeness,
  };
}

function appendQuotationVersion(
  versions: QuotationVersion[],
  quotation: Quotation,
  source: QuotationVersion["source"],
) {
  const now = "2026-05-19T12:20:00.000000Z";
  const previousVersion = versions[versions.length - 1] ?? null;
  const nextVersionNumber = previousVersion ? previousVersion.versionNumber + 1 : 1;
  const nextVersion = buildQuotationVersion(quotation, nextVersionNumber, previousVersion?.id ?? null, now, source);
  const previousVersionIndex = previousVersion
    ? versions.findIndex((version) => version.id === previousVersion.id)
    : -1;
  const supersededVersions = previousVersionIndex >= 0
    ? [
        ...versions.slice(0, previousVersionIndex),
        {
          ...versions[previousVersionIndex],
          isCurrent: false,
          status: "superseded" as const,
          supersededAt: now,
        },
        ...versions.slice(previousVersionIndex + 1),
      ]
    : versions;

  return [...supersededVersions, nextVersion];
}

function buildQuotationVersion(
  quotation: Quotation,
  versionNumber: number,
  previousVersionId: string | null,
  submittedAt: string,
  source: QuotationVersion["source"],
): QuotationVersion {
  return {
    id: `quotation-version-${versionNumber}`,
    quotationId: quotation.id,
    versionNumber,
    status: "received",
    source,
    submittedAt,
    submittedByUser: {
      id: "user-1",
      name: "Buyer User",
    },
    submittedByVendorContact: null,
    isCurrent: true,
    supersededAt: null,
    previousVersionId,
    manualEntry: structuredClone(quotation.manualEntry),
    lineItems: structuredClone(quotation.lineItems),
    attachments: quotation.attachments.map(toVersionAttachmentSnapshot),
    attachmentCount: quotation.attachments.length,
    completeness: structuredClone(quotation.completeness),
    permissions: {
      canEdit: false,
      canCreateRevision: true,
    },
  };
}

function decorateQuotationWithVersionSummary(quotation: Quotation, versions: QuotationVersion[]): Quotation {
  const currentVersion = versions.find((version) => version.isCurrent) ?? null;

  return {
    ...quotation,
    versionCount: versions.length,
    currentVersion: currentVersion
      ? {
          id: currentVersion.id,
          versionNumber: currentVersion.versionNumber,
          isCurrent: currentVersion.isCurrent,
          attachmentCount: currentVersion.attachmentCount,
        }
      : null,
  };
}

function toVersionAttachmentSnapshot(attachment: Attachment): QuotationVersion["attachments"][number] {
  return {
    id: attachment.id,
    filename: attachment.filename,
    mimeType: attachment.mimeType,
    extension: attachment.extension,
    sizeBytes: attachment.sizeBytes,
    checksumSha256: null,
    previewable: attachment.permissions.canPreview,
    uploadedBy: attachment.uploadedBy ? structuredClone(attachment.uploadedBy) : null,
    createdAt: attachment.createdAt,
    available: true,
  };
}

function buildQuotationLineItems(lineItems: SaveQuotationLineItemRequest[]) {
  return lineItems.map((lineItem, index) => ({
    id: `quotation-line-${index + 1}`,
    rfqLineItemId: lineItem.rfqLineItemId ?? null,
    description: lineItem.description,
    quantity: lineItem.quantity,
    unit: lineItem.unit ?? null,
    unitPrice: lineItem.unitPrice ?? null,
    subtotalAmount: lineItem.subtotalAmount ?? null,
    taxAmount: lineItem.taxAmount ?? null,
    totalAmount: lineItem.totalAmount ?? null,
    leadTimeDays: lineItem.leadTimeDays ?? null,
    manufacturer: lineItem.manufacturer ?? null,
    modelNumber: lineItem.modelNumber ?? null,
    alternateOffered: lineItem.alternateOffered ?? false,
    complianceStatus: lineItem.complianceStatus ?? null,
    notes: lineItem.notes ?? null,
  }));
}

function buildCompleteness(payload: SaveQuotationManualEntryRequest, lineItemCount: number) {
  const missingFields: string[] = [];

  if (!payload.currency?.trim()) {
    missingFields.push("currency");
  }

  if (!payload.totalAmount?.trim()) {
    missingFields.push("totalAmount");
  }

  if (lineItemCount === 0) {
    missingFields.push("lineItems");
  }

  return {
    isComplete: missingFields.length === 0,
    missingFields,
    lineItemCount,
  };
}

function emptyManualEntry(): Quotation["manualEntry"] {
  return {
    quotationReference: null,
    quotedAt: null,
    validUntil: null,
    currency: null,
    subtotalAmount: null,
    taxAmount: null,
    freightAmount: null,
    discountAmount: null,
    totalAmount: null,
    paymentTerms: null,
    deliveryTerms: null,
    leadTimeDays: null,
    warrantyTerms: null,
    exclusions: null,
    complianceNotes: null,
    buyerNotes: null,
    vendorNotes: null,
  };
}

function buildQuotationAttachment(
  sequence: number,
  quotationId: string,
  upload: { filename: string; mimeType: string; sizeBytes: number },
  createdAt: string,
): Attachment {
  const previewable = upload.mimeType === "application/pdf" || upload.mimeType.startsWith("image/");

  return {
    id: `quotation-att-${sequence}`,
    parentType: "quotation",
    parentId: quotationId,
    filename: upload.filename,
    mimeType: upload.mimeType,
    extension: fileExtension(upload.filename),
    sizeBytes: upload.sizeBytes,
    previewable,
    uploadedBy: {
      id: "user-1",
      name: "Maya Tan",
    },
    createdAt,
    permissions: {
      canPreview: previewable,
      canDownload: true,
      canDelete: true,
    },
  };
}

function cloneQuotation(quotation: Quotation | null): Quotation | null {
  return quotation ? structuredClone(quotation) : null;
}

function quotationNotFound() {
  return HttpResponse.json({ error: { code: "not_found", message: "Quotation not found." } }, { status: 404 });
}

function quotationVersionNotFound() {
  return HttpResponse.json(
    { error: { code: "not_found", message: "Quotation version not found." } },
    { status: 404 },
  );
}

function isBlankManualEntryPayload(payload: SaveQuotationManualEntryRequest) {
  return (
    !payload.quotationReference?.trim() &&
    !payload.quotedAt?.trim() &&
    !payload.validUntil?.trim() &&
    !payload.currency?.trim() &&
    !payload.subtotalAmount?.trim() &&
    !payload.taxAmount?.trim() &&
    !payload.freightAmount?.trim() &&
    !payload.discountAmount?.trim() &&
    !payload.totalAmount?.trim() &&
    !payload.paymentTerms?.trim() &&
    !payload.deliveryTerms?.trim() &&
    payload.leadTimeDays == null &&
    !payload.warrantyTerms?.trim() &&
    !payload.exclusions?.trim() &&
    !payload.complianceNotes?.trim() &&
    !payload.buyerNotes?.trim() &&
    !payload.vendorNotes?.trim() &&
    (payload.lineItems?.length ?? 0) === 0
  );
}

async function parseQuotationUpload(request: Request) {
  const formDataUpload = await parseFormDataUpload(request.clone());
  if (formDataUpload && isHelpfulFilename(formDataUpload.filename)) return formDataUpload;

  const textUpload = parseMultipartUploadText(await request.clone().text());
  if (formDataUpload && textUpload?.filename) {
    return {
      filename: textUpload.filename,
      mimeType: formDataUpload.mimeType,
      sizeBytes: formDataUpload.sizeBytes,
    };
  }

  return formDataUpload ?? textUpload;
}

async function parseFormDataUpload(request: Request) {
  try {
    const formData = await request.formData();
    const file = formData.get("file");
    if (!isFileLike(file)) return null;

    const filename = formDataString(formData, "file.filename") ?? file.name;
    const mimeType = formDataString(formData, "file.mimeType") ?? (file.type || "application/octet-stream");
    const metadataSize = formDataString(formData, "file.sizeBytes");
    const sizeBytes = parseSizeBytes(metadataSize) ?? file.size;

    return {
      filename,
      mimeType,
      sizeBytes,
    };
  } catch {
    return null;
  }
}

function formDataString(formData: FormData, key: string): string | null {
  const value = formData.get(key);
  return typeof value === "string" ? value : null;
}

function isHelpfulFilename(filename: string | null | undefined) {
  return Boolean(filename && filename !== "blob");
}

function parseMultipartUploadText(body: string) {
  const filename =
    extractMultipartField(body, "file.filename") ?? extractMultipartField(body, "filename");
  const mimeType =
    extractMultipartField(body, "file.mimeType") ?? extractMultipartField(body, "mimeType");
  const sizeBytes =
    extractMultipartField(body, "file.sizeBytes") ?? extractMultipartField(body, "sizeBytes");

  if (filename && mimeType && sizeBytes) {
    const parsedSizeBytes = parseSizeBytes(sizeBytes);
    if (parsedSizeBytes === null) return null;

    return {
      filename,
      mimeType,
      sizeBytes: parsedSizeBytes,
    };
  }

  const filenameMatch = body.match(/filename="([^"]+)"/);
  if (!filenameMatch) return null;

  const mimeTypeMatch = body.match(/Content-Type:\s*([^\r\n]+)/);
  const mimeTypeValue = mimeTypeMatch?.[1]?.trim() ?? "application/octet-stream";

  const lines = body.split(/\r?\n/);
  const boundaryLine = lines[0] ?? "";
  const headerBreak = body.match(/\r?\n\r?\n/);
  const contentStart = headerBreak ? body.indexOf(headerBreak[0]) + headerBreak[0].length : -1;
  const contentEnd = boundaryLine ? body.indexOf(boundaryLine, contentStart) : -1;
  const content = contentStart >= 0 && contentEnd >= 0 ? body.slice(contentStart, contentEnd) : "";

  return {
    filename: filenameMatch[1],
    mimeType: mimeTypeValue,
    sizeBytes: content.length,
  };
}

function fileExtension(filename: string): string | null {
  const extensionIndex = filename.lastIndexOf(".");
  return extensionIndex >= 0 ? filename.slice(extensionIndex + 1) : null;
}

function parseSizeBytes(value: string | null): number | null {
  if (!value) return null;

  const parsed = Number(value);
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : null;
}

function extractMultipartField(body: string, fieldName: string) {
  const fieldMatch = body.match(
    new RegExp(`name="${fieldName.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}"\\r?\\n\\r?\\n([^\\r\\n]+)`),
  );
  return fieldMatch?.[1]?.trim() ?? null;
}

function isFileLike(value: FormDataEntryValue | null): value is File {
  return (
    typeof value === "object" &&
    value !== null &&
    "name" in value &&
    "type" in value &&
    "size" in value
  );
}

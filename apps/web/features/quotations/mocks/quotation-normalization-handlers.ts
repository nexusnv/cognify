import { delay, http, HttpResponse } from "msw";
import type {
  ApproveQuotationNormalizationRequest,
  ApproveQuotationNormalizationWithWarningsRequest,
  ListQuotationNormalizationsParams,
  SaveQuotationNormalizationCorrectionsRequest,
  SaveQuotationNormalizationLineMappingsRequest,
} from "@cognify/api-client/schemas";
import {
  buildQuotationNormalizationSummaries,
  createQuotationNormalizationFixtureState,
  type QuotationNormalizationFixture,
} from "./quotation-normalization-fixtures";

let normalizations = createQuotationNormalizationFixtureState();

export function resetQuotationNormalizationMockState() {
  normalizations = createQuotationNormalizationFixtureState();
}

resetQuotationNormalizationMockState();

function forbidden(message: string) {
  return HttpResponse.json({ error: { code: "forbidden", message } }, { status: 403 });
}

function notFound() {
  return HttpResponse.json({ error: { code: "not_found", message: "Quotation normalization not found." } }, { status: 404 });
}

function conflict(message: string) {
  return HttpResponse.json({ error: { code: "conflict", message } }, { status: 409 });
}

function findNormalization(normalizationId: string) {
  return normalizations.find((fixture) => fixture.id === normalizationId) ?? null;
}

function isBlockingOpen(normalization: QuotationNormalizationFixture) {
  return normalization.issues.some((issue) => issue.severity === "blocking" && issue.status !== "resolved");
}

function hasUnresolvedWarnings(normalization: QuotationNormalizationFixture) {
  return normalization.issues.some((issue) => issue.severity === "warning" && issue.status !== "resolved");
}

function refreshSummary(normalization: QuotationNormalizationFixture) {
  normalization.summary = {
    blockingIssueCount: normalization.issues.filter((issue) => issue.severity === "blocking").length,
    warningIssueCount: normalization.issues.filter((issue) => issue.severity === "warning").length,
    infoIssueCount: normalization.issues.filter((issue) => issue.severity === "info").length,
  };
}

function refreshStatus(normalization: QuotationNormalizationFixture) {
  if (normalization.status === "approved" || normalization.status === "approved_with_warnings") {
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: true,
    };
    return;
  }

  if (normalization.status === "failed") {
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: true,
      canCreateRevision: false,
    };
    return;
  }

  if (normalization.status === "processing" || normalization.status === "pending") {
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: false,
    };
    return;
  }

  const blockingOpen = isBlockingOpen(normalization);
  const warningOpen = hasUnresolvedWarnings(normalization);
  normalization.status = blockingOpen ? "needs_review" : "ready_for_approval";
  normalization.permissions = {
    canEdit: true,
    canApprove: !blockingOpen,
    canApproveWithWarnings: !blockingOpen && warningOpen,
    canRetry: false,
    canCreateRevision: false,
  };
}

function touch(normalization: QuotationNormalizationFixture, timestamp: string) {
  normalization.updatedAt = timestamp;
  refreshSummary(normalization);
  refreshStatus(normalization);
}

export const quotationNormalizationHandlers = [
  http.get("/api/quotation-normalizations", async ({ request }) => {
    const params = Object.fromEntries(new URL(request.url).searchParams.entries()) as ListQuotationNormalizationsParams;
    const statusFilter = Array.isArray(params.status)
      ? params.status
      : typeof params.status === "string"
        ? [params.status]
        : [];
    const data = statusFilter.length > 0
      ? normalizations.filter((fixture) => statusFilter.includes(fixture.status))
      : normalizations;

    return HttpResponse.json({ data: buildQuotationNormalizationSummaries(data) });
  }),

  http.get("/api/quotation-normalizations/:normalizationId", ({ params }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),

  http.get("/api/quotations/:quotationId/versions/:versionId", ({ params }) => {
    const quotationId = String(params.quotationId);
    const versionNumber = Number(params.versionId);
    const normalization = normalizations.find(
      (fixture) =>
        fixture.source.quotationId === quotationId &&
        Number(fixture.source.versionNumber) === versionNumber,
    );
    if (!normalization) return notFound();

    return HttpResponse.json({
      data: {
        id: normalization.source.quotationVersionId,
        quotationId,
        versionNumber,
        status: "received",
        source: "buyer_upload",
        submittedAt: normalization.updatedAt,
        submittedByUser: {
          id: "buyer-1",
          name: "Priya Buyer",
        },
        submittedByVendorContact: null,
        isCurrent: true,
        supersededAt: null,
        previousVersionId: "quotation-version-1",
        manualEntry: {
          quotationReference: normalization.source.quotationNumber,
          quotedAt: "2026-05-22",
          validUntil: "2026-06-30",
          currency: "USD",
          subtotalAmount: "12470.00",
          taxAmount: "0.00",
          freightAmount: "0.00",
          discountAmount: "0.00",
          totalAmount: "12470.00",
          paymentTerms: null,
          deliveryTerms: "DDP",
          leadTimeDays: 14,
          warrantyTerms: "3 years",
          exclusions: null,
          complianceNotes: null,
          buyerNotes: null,
          vendorNotes: null,
        },
        lineItems: structuredClone(normalization.currentVersionLines),
        attachments: [],
        attachmentCount: 0,
        completeness: {
          isComplete: true,
          missingFields: [],
          lineItemCount: normalization.currentVersionLines.length,
        },
        permissions: {
          canEdit: false,
          canCreateRevision: true,
        },
      },
    });
  }),

  http.post("/api/quotation-normalizations/:normalizationId/corrections", async ({ params, request }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();
    if (!normalization.permissions.canEdit) return forbidden("You do not have permission to edit this normalization.");

    const payload = (await request.json()) as SaveQuotationNormalizationCorrectionsRequest;
    for (const correction of payload.corrections) {
      const field = normalization.fields.find((entry) => entry.fieldPath === correction.fieldPath);
      if (field) {
        field.normalizedValue = correction.correctedValue;
      }

      if (correction.issueId) {
        const issue = normalization.issues.find((entry) => entry.id === correction.issueId);
        if (issue) {
          issue.status = "resolved";
          issue.resolutionNote = correction.resolutionNote ?? correction.correctionNote;
          issue.resolvedAt = "2026-05-22T10:05:00.000Z";
          issue.resolvedByUserId = "buyer-1";
        }
      }
    }

    touch(normalization, "2026-05-22T10:05:00.000Z");

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),

  http.post("/api/quotation-normalizations/:normalizationId/line-mappings", async ({ params, request }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();
    if (!normalization.permissions.canEdit) return forbidden("You do not have permission to edit this normalization.");

    const payload = (await request.json()) as SaveQuotationNormalizationLineMappingsRequest;
    normalization.lineGroups = payload.lineGroups.map((group, index) => ({
      id: `line-group-${index + 1}`,
      groupNumber: group.groupNumber,
      pricingMode: group.pricingMode,
      description: group.description,
      currency: group.currency ?? null,
      bundleTotalAmount:
        group.bundleTotalAmount === undefined || group.bundleTotalAmount === null
          ? null
          : String(group.bundleTotalAmount),
      notes: group.notes ?? null,
      mappings: group.mappings.map((mapping, mappingIndex) => ({
        id: `line-mapping-${index + 1}-${mappingIndex + 1}`,
        rfqLineItemId: mapping.rfqLineItemId ?? null,
        quotationVersionLineItemId: mapping.quotationVersionLineItemId ?? null,
        mappingType: mapping.mappingType,
        quantity:
          mapping.quantity === undefined || mapping.quantity === null
            ? null
            : String(mapping.quantity),
        unit: mapping.unit ?? null,
        unitPrice:
          mapping.unitPrice === undefined || mapping.unitPrice === null
            ? null
            : String(mapping.unitPrice),
        lineTotal:
          mapping.lineTotal === undefined || mapping.lineTotal === null
            ? null
            : String(mapping.lineTotal),
        buyerNote: mapping.buyerNote ?? null,
      })),
    }));

    const issue = normalization.issues.find((entry) => entry.id === "issue-line-mapping");
    if (issue) {
      issue.status = "resolved";
      issue.resolutionNote = "Buyer mapped quotation version lines to RFQ lines.";
      issue.resolvedAt = "2026-05-22T10:10:00.000Z";
      issue.resolvedByUserId = "buyer-1";
    }

    touch(normalization, "2026-05-22T10:10:00.000Z");

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),

  http.post("/api/quotation-normalizations/:normalizationId/approve", async ({ params, request }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();

    const payload = (await request.json()) as ApproveQuotationNormalizationRequest;
    if (!normalization.permissions.canApprove) {
      return conflict("Blocking issues must be resolved before approval.");
    }

    normalization.status = "approved";
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: true,
    };
    normalization.issues = normalization.issues.map((issue) =>
      issue.severity === "warning" && issue.status !== "resolved"
        ? {
            ...issue,
            status: "resolved",
            resolutionNote: issue.resolutionNote ?? payload.approvalNote ?? "Approved for downstream comparison.",
          }
        : issue,
    );
    touch(normalization, "2026-05-22T10:15:00.000Z");

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),

  http.post("/api/quotation-normalizations/:normalizationId/approve-with-warnings", async ({ params, request }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();

    const payload = (await request.json()) as ApproveQuotationNormalizationWithWarningsRequest;
    if (!payload.approvalNote?.trim()) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "An acknowledgement note is required." } },
        { status: 422 },
      );
    }
    if (!normalization.permissions.canApproveWithWarnings) {
      return conflict("This normalization cannot be approved with warnings.");
    }

    normalization.status = "approved_with_warnings";
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: true,
    };
    normalization.issues = normalization.issues.map((issue) =>
      issue.severity === "warning" && issue.status !== "resolved"
        ? {
            ...issue,
            status: "acknowledged",
            resolutionNote: payload.approvalNote,
            resolvedAt: "2026-05-22T10:20:00.000Z",
            resolvedByUserId: "buyer-1",
          }
        : issue,
    );
    touch(normalization, "2026-05-22T10:20:00.000Z");

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),

  http.post("/api/quotation-normalizations/:normalizationId/revisions", ({ params }) => {
    const normalization = findNormalization(String(params.normalizationId));
    if (!normalization) return notFound();

    const revision = structuredClone(normalization);
    revision.id = `${normalization.id}-revision-2`;
    revision.normalizationRevision = normalization.normalizationRevision + 1;
    revision.status = "needs_review";
    revision.permissions = {
      canEdit: true,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: false,
    };
    revision.updatedAt = "2026-05-22T10:25:00.000Z";
    normalizations = [revision, ...normalizations];

    return HttpResponse.json({ data: structuredClone(revision) }, { status: 201 });
  }),

  http.post("/api/quotation-versions/:version/normalization/retry", async ({ params }) => {
    await delay(50);
    const normalization = normalizations.find(
      (fixture) => String(fixture.source.versionNumber) === String(params.version) && fixture.status === "failed",
    );
    if (!normalization) return notFound();

    normalization.status = "processing";
    normalization.lastJobError = null;
    normalization.permissions = {
      canEdit: false,
      canApprove: false,
      canApproveWithWarnings: false,
      canRetry: false,
      canCreateRevision: false,
    };
    touch(normalization, "2026-05-22T10:30:00.000Z");

    return HttpResponse.json({ data: structuredClone(normalization) });
  }),
];

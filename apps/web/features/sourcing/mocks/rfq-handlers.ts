import { http, HttpResponse } from "msw";
import type {
  Rfq,
  RfqCancelRequest,
  RfqLineItem,
  RfqUpdateRequest,
} from "@cognify/api-client/schemas";
import { rfqDraftFixture } from "./rfq-fixtures";
import { sourcingIntakeFixtures } from "./sourcing-fixtures";

const initialRfq = structuredClone(rfqDraftFixture);

let rfqs = new Map<string, Rfq>([[initialRfq.id, initialRfq]]);

function notFound() {
  return HttpResponse.json(
    { error: { code: "not_found", message: "RFQ not found." } },
    { status: 404 },
  );
}

function conflict(message: string) {
  return HttpResponse.json(
    { error: { code: "conflict", message } },
    { status: 409 },
  );
}

function forbidden(message: string) {
  return HttpResponse.json(
    { error: { code: "forbidden", message } },
    { status: 403 },
  );
}

function cloneRfq(rfq: Rfq): Rfq {
  return structuredClone(rfq);
}

function buildLineItems(existing: Rfq, lineItems: RfqUpdateRequest["lineItems"]): RfqLineItem[] {
  if (lineItems === null) return [];
  if (lineItems === undefined) return existing.lineItems;

  const currency = existing.lineItems[0]?.currency ?? "MYR";

  return lineItems.map((item) => {
    const matchedLine = existing.lineItems.find((existingLine) => {
      const existingKey = `${existingLine.description ?? existingLine.name}|${existingLine.unit}`;
      const incomingKey = `${item.description}|${item.unit}`;
      return existingKey === incomingKey;
    });

    return {
      name: item.description,
      description: item.description,
      quantity: item.quantity,
      unit: item.unit,
      notes: item.notes ?? undefined,
      unitOfMeasure: item.unit,
      estimatedUnitPrice: matchedLine?.estimatedUnitPrice ?? null,
      currency,
    };
  });
}

export function resetRfqMockState() {
  rfqs = new Map<string, Rfq>([[initialRfq.id, cloneRfq(initialRfq)]]);
}

export const rfqHandlers = [
  http.post("/api/sourcing/intake-reviews/:reviewId/rfq", ({ params }) => {
    const reviewId = String(params.reviewId);
    if (reviewId === "sourcing-forbidden") {
      return forbidden("You do not have access to create this RFQ.");
    }
    const review = sourcingIntakeFixtures.find((item) => item.id === reviewId);
    if (!review) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Sourcing intake review was not found." } },
        { status: 404 },
      );
    }
    if (review.status !== "ready_for_rfq") {
      return conflict("Review is not ready for RFQ.");
    }

    const current = rfqs.get(initialRfq.id);
    if (current) {
      return HttpResponse.json({ data: cloneRfq(current) });
    }

    const created = cloneRfq(initialRfq);
    rfqs.set(created.id, created);
    return HttpResponse.json({ data: created }, { status: 201 });
  }),

  http.get("/api/rfqs/:rfqId", ({ params }) => {
    const rfq = rfqs.get(String(params.rfqId));
    if (String(params.rfqId) === "rfq-forbidden") {
      return forbidden("You do not have access to this RFQ.");
    }
    if (!rfq) return notFound();
    return HttpResponse.json({ data: cloneRfq(rfq) });
  }),

  http.patch("/api/rfqs/:rfqId", async ({ params, request }) => {
    if (String(params.rfqId) === "rfq-forbidden") {
      return forbidden("You do not have access to this RFQ.");
    }
    const existing = rfqs.get(String(params.rfqId));
    if (!existing) return notFound();
    if (existing.status === "cancelled") {
      return conflict("Cancelled RFQs cannot be edited.");
    }

    const payload = (await request.json()) as RfqUpdateRequest;
    const updated: Rfq = {
      ...existing,
      title: payload.title ?? existing.title,
      scopeSummary:
        "scopeSummary" in payload ? (payload.scopeSummary ?? null) : existing.scopeSummary,
      responseDueAt:
        "responseDueAt" in payload ? (payload.responseDueAt ?? null) : existing.responseDueAt,
      responseInstructions:
        "responseInstructions" in payload
          ? (payload.responseInstructions ?? null)
          : existing.responseInstructions,
      requiredDocuments:
        "requiredDocuments" in payload
          ? (payload.requiredDocuments ?? [])
          : existing.requiredDocuments,
      lineItems: "lineItems" in payload ? buildLineItems(existing, payload.lineItems) : existing.lineItems,
      evaluationNotes:
        "evaluationNotes" in payload ? (payload.evaluationNotes ?? null) : existing.evaluationNotes,
      internalNotes:
        "internalNotes" in payload ? (payload.internalNotes ?? null) : existing.internalNotes,
      updatedAt: "2026-05-19T10:00:00.000000Z",
    };

    rfqs.set(updated.id, updated);
    return HttpResponse.json({ data: cloneRfq(updated) });
  }),

  http.post("/api/rfqs/:rfqId/cancel", async ({ params, request }) => {
    if (String(params.rfqId) === "rfq-forbidden") {
      return forbidden("You do not have access to this RFQ.");
    }
    const existing = rfqs.get(String(params.rfqId));
    if (!existing) return notFound();
    if (existing.status === "cancelled") {
      return conflict("Cancelled RFQs cannot be cancelled again.");
    }

    const payload = (await request.json()) as RfqCancelRequest;
    if (!payload.cancelReason) {
      return HttpResponse.json(
        { error: { code: "validation_failed", message: "Cancel reason is required." } },
        { status: 422 },
      );
    }

    const cancelled: Rfq = {
      ...existing,
      status: "cancelled",
      cancelReason: payload.cancelReason,
      cancelledAt: "2026-05-19T11:00:00.000000Z",
      updatedAt: "2026-05-19T11:00:00.000000Z",
      permissions: {
        ...existing.permissions,
        canUpdate: false,
        canCancel: false,
        canInviteVendors: false,
      },
    };

    rfqs.set(cancelled.id, cancelled);
    return HttpResponse.json({ data: cloneRfq(cancelled) });
  }),
];

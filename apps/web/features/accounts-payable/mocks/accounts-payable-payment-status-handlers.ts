import { http, HttpResponse } from "msw";
import { apPaymentHandoffStatusFixtures } from "./accounts-payable-payment-status-fixtures";
import type { ApPaymentHandoffStatusFixture } from "./accounts-payable-payment-status-fixtures";

let statusHandoffs: ApPaymentHandoffStatusFixture[] = [];

export function resetAccountsPayablePaymentStatusMockState() {
  statusHandoffs = apPaymentHandoffStatusFixtures.map((h) => structuredClone(h));
}

resetAccountsPayablePaymentStatusMockState();

function findHandoff(id: string): ApPaymentHandoffStatusFixture | undefined {
  return statusHandoffs.find((h) => h.id === id);
}

function updateHandoff(id: string, updates: Partial<ApPaymentHandoffStatusFixture>): ApPaymentHandoffStatusFixture | undefined {
  const idx = statusHandoffs.findIndex((h) => h.id === id);
  if (idx === -1) return undefined;
  statusHandoffs[idx] = { ...statusHandoffs[idx], ...updates };
  return statusHandoffs[idx];
}

export const accountsPayablePaymentStatusHandlers = [
  http.post("/api/ap-payment-handoffs/:handoff/schedule", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; scheduledForDate?: string; paymentReference?: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "exported") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only exported handoffs can be scheduled." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "scheduled",
      scheduledForDate: body.scheduledForDate ?? null,
      paymentReference: body.paymentReference ?? null,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/mark-paid", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; remittanceReference?: string; remittanceAdviceSentAt?: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "scheduled") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only scheduled handoffs can be marked paid." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "paid",
      remittanceReference: body.remittanceReference ?? handoff.remittanceReference,
      paidAt: now,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/close-with-variance", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; varianceReason: string; remittanceReference?: string; remittanceAdviceSentAt?: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "scheduled") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only scheduled handoffs can be closed with variance." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "paid",
      remittanceReference: body.remittanceReference ?? handoff.remittanceReference,
      paidAt: now,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/mark-failed", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; failureCode: string; failureReason: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "scheduled") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only scheduled handoffs can be marked failed." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "failed",
      failedAt: now,
      failureCode: body.failureCode,
      failureReason: body.failureReason,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/void", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; voidReason: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "scheduled" && handoff.status !== "paid") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only scheduled or paid handoffs can be voided." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "voided",
      voidedAt: now,
      voidReason: body.voidReason,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/reschedule", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number; scheduledForDate?: string; paymentReference?: string };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if (handoff.status !== "failed") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only failed handoffs can be rescheduled." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "scheduled",
      scheduledForDate: body.scheduledForDate ?? handoff.scheduledForDate,
      paymentReference: body.paymentReference ?? handoff.paymentReference,
      failedAt: undefined,
      failureCode: undefined,
      failureReason: undefined,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),
];

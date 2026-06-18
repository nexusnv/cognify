import { http, HttpResponse } from "msw";
import {
  apPaymentHandoffFixtures,
  paymentStatusInvoiceFixtures,
} from "./accounts-payable-payment-fixtures";
import type { ApPaymentHandoffFixture, PaymentStatusInvoiceFixture } from "./accounts-payable-payment-fixtures";

let handoffs: ApPaymentHandoffFixture[] = [];
let paymentInvoices: Record<string, PaymentStatusInvoiceFixture> = {};

export function resetAccountsPayablePaymentMockState() {
  handoffs = apPaymentHandoffFixtures.map((h) => structuredClone(h));
  paymentInvoices = Object.fromEntries(
    Object.entries(paymentStatusInvoiceFixtures).map(([id, inv]) => [id, structuredClone(inv)]),
  );
}

resetAccountsPayablePaymentMockState();

function findHandoff(id: string): ApPaymentHandoffFixture | undefined {
  return handoffs.find((h) => h.id === id);
}

function updateHandoff(id: string, updates: Partial<ApPaymentHandoffFixture>): ApPaymentHandoffFixture | undefined {
  const idx = handoffs.findIndex((h) => h.id === id);
  if (idx === -1) return undefined;
  handoffs[idx] = { ...handoffs[idx], ...updates };
  return handoffs[idx];
}

function findPaymentInvoice(id: string): PaymentStatusInvoiceFixture | undefined {
  return paymentInvoices[id];
}

function updatePaymentInvoice(id: string, updates: Partial<PaymentStatusInvoiceFixture>): PaymentStatusInvoiceFixture | undefined {
  const invoice = paymentInvoices[id];
  if (!invoice) return undefined;
  paymentInvoices[id] = { ...invoice, ...updates };
  return paymentInvoices[id];
}

function generateHandoffNumber(): string {
  const maxNum = handoffs.reduce((max, h) => {
    const match = h.number.match(/APH-2026-(\d{6})/);
    return match ? Math.max(max, parseInt(match[1], 10)) : max;
  }, 0);
  return `APH-2026-${String(maxNum + 1).padStart(6, "0")}`;
}

export const accountsPayablePaymentHandlers = [
  // ─── Place Hold ────────────────────────────────────────────────────────────
  http.post("/api/supplier-invoices/:supplierInvoice/place-hold", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const body = (await request.json()) as { reason: string; lockVersion: number };
    const invoice = findPaymentInvoice(id);

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== invoice.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (invoice.paymentStatus !== "payment_eligible" && invoice.paymentStatus !== null) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Invoice cannot be placed on hold from the current payment status." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updatePaymentInvoice(id, {
      paymentStatus: "on_hold",
      paymentOnHoldByUserId: "buyer-1",
      paymentOnHoldAt: now,
      paymentOnHoldReason: body.reason,
      lockVersion: invoice.lockVersion + 1,
    })!;

    return HttpResponse.json({
      data: {
        id: updated.id,
        paymentStatus: updated.paymentStatus,
        paymentOnHoldByUserId: updated.paymentOnHoldByUserId,
        paymentOnHoldAt: updated.paymentOnHoldAt,
        paymentOnHoldReason: updated.paymentOnHoldReason,
        lockVersion: updated.lockVersion,
      },
    });
  }),

  // ─── Release Hold ──────────────────────────────────────────────────────────
  http.post("/api/supplier-invoices/:supplierInvoice/release-hold", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const body = (await request.json()) as { releaseNote: string; lockVersion: number };
    const invoice = findPaymentInvoice(id);

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== invoice.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (invoice.paymentStatus !== "on_hold") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Invoice is not currently on hold." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updatePaymentInvoice(id, {
      paymentStatus: "payment_eligible",
      paymentEligibleAt: now,
      holdReleasedByUserId: "buyer-1",
      holdReleasedAt: now,
      holdReleaseNote: body.releaseNote,
      paymentOnHoldByUserId: null,
      paymentOnHoldAt: null,
      paymentOnHoldReason: null,
      lockVersion: invoice.lockVersion + 1,
    })!;

    return HttpResponse.json({
      data: {
        id: updated.id,
        paymentStatus: updated.paymentStatus,
        paymentEligibleAt: updated.paymentEligibleAt,
        holdReleasedByUserId: updated.holdReleasedByUserId,
        holdReleasedAt: updated.holdReleasedAt,
        holdReleaseNote: updated.holdReleaseNote,
        lockVersion: updated.lockVersion,
      },
    });
  }),

  // ─── Retry Payment Induction ───────────────────────────────────────────────
  http.post("/api/supplier-invoices/:supplierInvoice/retry-payment-induction", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const body = (await request.json()) as { lockVersion: number };
    const invoice = findPaymentInvoice(id);

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== invoice.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (invoice.paymentStatus !== null) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Payment induction can only be retried on invoices not yet inducted." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updatePaymentInvoice(id, {
      paymentStatus: "payment_eligible",
      paymentEligibleAt: now,
      lockVersion: invoice.lockVersion + 1,
    })!;

    return HttpResponse.json({
      data: {
        id: updated.id,
        paymentStatus: updated.paymentStatus,
        paymentEligibleAt: updated.paymentEligibleAt,
        lockVersion: updated.lockVersion,
      },
    });
  }),

  // ─── List Handoffs ─────────────────────────────────────────────────────────
  http.get("/api/ap-payment-handoffs", ({ request }) => {
    const url = new URL(request.url);
    const status = url.searchParams.get("status");
    const filtered = status ? handoffs.filter((h) => h.status === status) : handoffs;

    return HttpResponse.json({
      data: filtered,
      meta: { total: filtered.length, perPage: 20, currentPage: 1 },
    });
  }),

  // ─── Create Handoff ────────────────────────────────────────────────────────
  http.post("/api/ap-payment-handoffs", async ({ request }) => {
    const body = (await request.json()) as {
      invoiceIds: string[];
      effectivePaymentDate?: string;
      notes?: string;
    };

    const invoices = body.invoiceIds.map((id) => findPaymentInvoice(id));
    const missing = invoices.some((inv) => !inv);
    if (missing) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "One or more invoices not found." } },
        { status: 404 },
      );
    }

    const notEligible = invoices.some((inv) => inv!.paymentStatus !== "payment_eligible");
    if (notEligible) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "All invoices must be payment eligible." } },
        { status: 422 },
      );
    }

    const inActiveHandoff = invoices.some((inv) => inv!.activeHandoffId !== null);
    if (inActiveHandoff) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "One or more invoices are already in an active handoff." } },
        { status: 422 },
      );
    }

    const currencies = new Set(invoices.map((inv) => inv!.currency));
    if (currencies.size > 1) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "All invoices must have the same currency." } },
        { status: 422 },
      );
    }

    const totalAmount = invoices
      .reduce((sum, inv) => sum + parseFloat(inv!.totalAmount), 0)
      .toFixed(4);

    const currency = invoices[0]!.currency;
    const newHandoffId = `handoff-${Date.now()}`;
    const newHandoffNumber = generateHandoffNumber();
    const now = new Date().toISOString();

    const newHandoff: ApPaymentHandoffFixture = {
      id: newHandoffId,
      number: newHandoffNumber,
      status: "draft",
      effectivePaymentDate: body.effectivePaymentDate ?? null,
      notes: body.notes ?? null,
      currency,
      totalAmount,
      remittanceReference: null,
      snapshot: {
        invoiceCount: body.invoiceIds.length,
        totalAmount,
        currency,
        invoices: body.invoiceIds.map((id) => {
          const inv = paymentInvoices[id]!;
          return { id, number: id, amount: inv.totalAmount };
        }),
      },
      readinessWarnings: [],
      createdByUserId: "buyer-1",
      readyByUserId: null,
      readyAt: null,
      cancelledByUserId: null,
      cancelledAt: null,
      cancelledReason: null,
      lastExportedByUserId: null,
      lastExportedAt: null,
      lastExportFormat: null,
      lockVersion: 1,
      invoiceCount: body.invoiceIds.length,
      createdAt: now,
      updatedAt: now,
    };

    handoffs.push(newHandoff);

    for (const id of body.invoiceIds) {
      updatePaymentInvoice(id, {
        paymentStatus: "payment_ready",
        activeHandoffId: newHandoffId,
        activeHandoffNumber: newHandoffNumber,
        paymentReadyAt: now,
        lockVersion: paymentInvoices[id].lockVersion + 1,
      });
    }

    return HttpResponse.json({ data: newHandoff }, { status: 201 });
  }),

  // ─── Show Handoff ──────────────────────────────────────────────────────────
  http.get("/api/ap-payment-handoffs/:handoff", ({ params }) => {
    const handoff = findHandoff(String(params.handoff));

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: handoff });
  }),

  // ─── Update Handoff Notes ──────────────────────────────────────────────────
  http.patch("/api/ap-payment-handoffs/:handoff", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { notes?: string; lockVersion: number };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status !== "draft") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only draft handoffs can be updated." } },
        { status: 422 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    const updated = updateHandoff(id, {
      notes: body.notes ?? handoff.notes,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: new Date().toISOString(),
    })!;

    return HttpResponse.json({ data: updated });
  }),

  // ─── Refresh Handoff Snapshot ──────────────────────────────────────────────
  http.post("/api/ap-payment-handoffs/:handoff/refresh", async ({ params, request }) => {
    const id = String(params.handoff);
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status !== "draft") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only draft handoff snapshots can be refreshed." } },
        { status: 422 },
      );
    }

    // lockVersion is optional on refresh; when supplied it must match the
    // current draft to guard against concurrent edits.
    const body = (await request.json().catch(() => null)) as { lockVersion?: number } | null;
    if (body?.lockVersion !== undefined && body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      snapshot: { ...handoff.snapshot, refreshedAt: now },
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  // ─── Mark Handoff Ready ────────────────────────────────────────────────────
  http.post("/api/ap-payment-handoffs/:handoff/ready", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { lockVersion: number };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status !== "draft") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only draft handoffs can be marked ready." } },
        { status: 422 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    if ((handoff.invoiceCount ?? 0) === 0) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Cannot mark a handoff with no invoices as ready." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "ready",
      readyByUserId: "buyer-1",
      readyAt: now,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  // ─── Cancel Handoff ────────────────────────────────────────────────────────
  http.post("/api/ap-payment-handoffs/:handoff/cancel", async ({ params, request }) => {
    const id = String(params.handoff);
    const body = (await request.json()) as { reason: string; lockVersion: number };
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status === "cancelled") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Payment handoff is already cancelled." } },
        { status: 422 },
      );
    }

    if (body.lockVersion !== handoff.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Payment handoff was updated by another user." } },
        { status: 409 },
      );
    }

    const snapshotInvoices = handoff.snapshot?.invoices as Array<{ id: string }> ?? [];
    for (const invRef of snapshotInvoices) {
      const inv = findPaymentInvoice(invRef.id);
      if (inv && inv.activeHandoffId === id) {
        updatePaymentInvoice(invRef.id, {
          paymentStatus: "payment_eligible",
          activeHandoffId: null,
          activeHandoffNumber: null,
          paymentReadyAt: null,
          lockVersion: inv.lockVersion + 1,
        });
      }
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "cancelled",
      cancelledByUserId: "buyer-1",
      cancelledAt: now,
      cancelledReason: body.reason,
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  // ─── Export JSON ───────────────────────────────────────────────────────────
  http.get("/api/ap-payment-handoffs/:handoff/export.json", ({ params }) => {
    const handoff = findHandoff(String(params.handoff));

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({
      exportedAt: new Date().toISOString(),
      format: "json",
      handoff,
    });
  }),

  // ─── Export CSV ────────────────────────────────────────────────────────────
  http.get("/api/ap-payment-handoffs/:handoff/export.csv", ({ params }) => {
    const handoff = findHandoff(String(params.handoff));

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    const snapshotInvoices = (handoff.snapshot?.invoices as Array<{
      id: string;
      number?: string;
      amount?: string;
    }>) ?? [];

    const header = "HandoffNumber,InvoiceId,InvoiceNumber,Amount,Currency,EffectivePaymentDate";
    const rows = snapshotInvoices.map((inv) => {
      const amount = inv.amount ?? "0.0000";
      return [
        handoff.number,
        inv.id,
        inv.number ?? inv.id,
        amount,
        handoff.currency,
        handoff.effectivePaymentDate ?? "",
      ].join(",");
    });

    const bom = "\uFEFF";
    const csvContent = bom + header + "\n" + rows.join("\n");

    return new HttpResponse(csvContent, {
      headers: { "Content-Type": "text/csv" },
    });
  }),

  // ─── Record JSON Export ────────────────────────────────────────────────────
  // Mirrors ExportApPaymentHandoff with $recordExport=true: transitions a ready
  // handoff to exported and stamps the export metadata.
  http.post("/api/ap-payment-handoffs/:handoff/export.json", async ({ params }) => {
    const id = String(params.handoff);
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status !== "ready" && handoff.status !== "exported") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only ready or exported handoffs can be exported." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "exported",
      lastExportedByUserId: "buyer-1",
      lastExportedAt: now,
      lastExportFormat: "json",
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({
      exportedAt: now,
      format: "json",
      handoff: updated,
    });
  }),

  // ─── Record CSV Export ─────────────────────────────────────────────────────
  http.post("/api/ap-payment-handoffs/:handoff/export.csv", async ({ params }) => {
    const id = String(params.handoff);
    const handoff = findHandoff(id);

    if (!handoff) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment handoff not found." } },
        { status: 404 },
      );
    }

    if (handoff.status !== "ready" && handoff.status !== "exported") {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Only ready or exported handoffs can be exported." } },
        { status: 422 },
      );
    }

    const now = new Date().toISOString();
    const updated = updateHandoff(id, {
      status: "exported",
      lastExportedByUserId: "buyer-1",
      lastExportedAt: now,
      lastExportFormat: "csv",
      lockVersion: handoff.lockVersion + 1,
      updatedAt: now,
    })!;

    return HttpResponse.json({
      exportedAt: now,
      format: "csv",
      handoff: updated,
    });
  }),
];

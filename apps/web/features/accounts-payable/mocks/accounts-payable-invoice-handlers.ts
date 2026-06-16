import { http, HttpResponse } from "msw";
import type {
  SupplierInvoice,
  SupplierInvoiceQueueItem,
  SupplierInvoiceReviewActionRequest,
} from "@cognify/api-client/schemas";
import {
  accountsPayableInvoiceDetails,
  accountsPayableInvoiceRows,
} from "./accounts-payable-invoice-fixtures";
import { mockMatchedResults, mockMismatchedResults } from "./invoice-matching-fixtures";

let rows: SupplierInvoiceQueueItem[] = [];
let details: Record<string, SupplierInvoice> = {};

export function resetAccountsPayableInvoiceMockState() {
  rows = accountsPayableInvoiceRows.map((row) => structuredClone(row));
  details = Object.fromEntries(
    Object.entries(accountsPayableInvoiceDetails).map(([id, invoice]) => [id, structuredClone(invoice)]),
  );
}

resetAccountsPayableInvoiceMockState();

function isStartAllowed(status: SupplierInvoice["status"]) {
  return status === "captured" || status === "needs_information";
}

function isReviewActionAllowed(status: SupplierInvoice["status"]) {
  return status === "in_review";
}

export const accountsPayableInvoiceHandlers = [
  http.get("/api/supplier-invoices", ({ request }) => {
    const url = new URL(request.url);
    const status = url.searchParams.get("status");
    const filteredRows = status ? rows.filter((row) => row.status === status) : rows;

    return HttpResponse.json({ data: filteredRows });
  }),
  http.get("/api/supplier-invoices/:supplierInvoice", ({ params }) => {
    const invoice = details[String(params.supplierInvoice)];

    if (!invoice) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: invoice });
  }),
  http.post("/api/supplier-invoices/:supplierInvoice/start-review", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const payload = await request.json() as SupplierInvoiceReviewActionRequest;
    const detail = details[id];

    if (!detail) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (payload.lockVersion !== detail.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (!isStartAllowed(detail.status)) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Invoice cannot be started from the current status." } },
        { status: 422 },
      );
    }

    const next: SupplierInvoice = {
      ...detail,
      status: "in_review",
      reviewStartedByUserId: "buyer-1",
      reviewStartedAt: "2026-06-13T03:00:00.000Z",
      lockVersion: detail.lockVersion + 1,
    };

    details[id] = next;
    rows = rows.map((row) =>
      row.id === id
        ? {
            ...row,
            status: next.status,
            reviewStartedAt: next.reviewStartedAt,
            lockVersion: next.lockVersion,
          }
        : row,
    );

    return HttpResponse.json({ data: next });
  }),
  http.post("/api/supplier-invoices/:supplierInvoice/needs-information", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const payload = await request.json() as SupplierInvoiceReviewActionRequest;
    const detail = details[id];

    if (!detail) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (payload.lockVersion !== detail.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (!isReviewActionAllowed(detail.status)) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Invoice is not currently in review." } },
        { status: 422 },
      );
    }

    const blockers = Object.entries(payload.checklist ?? {})
      .filter(([, item]) => item.status !== "pass")
      .map(([key, item]) => ({ key, status: item.status, note: item.note ?? null }));

    const next: SupplierInvoice = {
      ...detail,
      status: "needs_information",
      reviewNotes: payload.notes ?? null,
      reviewChecklist: detail.reviewChecklist,
      reviewChecklistSummary: {
        total: 5,
        passed: Object.values(payload.checklist ?? {}).filter((item) => item.status === "pass").length,
        needsAttention: Object.values(payload.checklist ?? {}).filter((item) => item.status === "needs_attention").length,
        failed: Object.values(payload.checklist ?? {}).filter((item) => item.status === "fail").length,
      },
      reviewBlockers: blockers,
      reviewBlockerCount: blockers.length,
      lockVersion: detail.lockVersion + 1,
    };

    if (payload.checklist) {
      next.reviewChecklist = payload.checklist;
    }

    details[id] = next;
    rows = rows.map((row) =>
      row.id === id
        ? {
            ...row,
            status: next.status,
            reviewChecklistSummary: next.reviewChecklistSummary,
            reviewBlockerCount: blockers.length,
            lockVersion: next.lockVersion,
          }
        : row,
    );

    return HttpResponse.json({ data: next });
  }),
  http.post("/api/supplier-invoices/:supplierInvoice/run-matching", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const payload = await request.json() as { lockVersion: number };
    const detail = details[id];

    if (!detail) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Invoice not found" } },
        { status: 404 },
      );
    }

    if (detail.status !== "reviewed") {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Matching can only be run on reviewed invoices." } },
        { status: 409 },
      );
    }

    if (payload.lockVersion !== detail.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    const isMatched = id === "invoice-4";
    const updatedLockVersion = detail.lockVersion + 1;
    const next: SupplierInvoice = {
      ...detail,
      matchingStatus: isMatched ? "matched" : "mismatch",
      matchSummary: isMatched
        ? {
            totalLines: 1,
            matchedLines: 1,
            mismatchLines: 0,
            dimensionsWithIssues: [],
          }
        : {
            totalLines: 1,
            matchedLines: 0,
            mismatchLines: 1,
            dimensionsWithIssues: ["quantity", "unit_price"],
          },
      lockVersion: updatedLockVersion,
    };

    details[id] = next;
    rows = rows.map((row) =>
      row.id === id
        ? { ...row, matchingStatus: "mismatch" as const, lockVersion: updatedLockVersion }
        : row,
    );

    return HttpResponse.json({ data: next });
  }),

  http.get("/api/supplier-invoices/:supplierInvoice/match-results", ({ params }) => {
    const id = String(params.supplierInvoice);
    const detail = details[id];

    if (!detail) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Invoice not found" } },
        { status: 404 },
      );
    }

    const results = id === "invoice-4" ? mockMatchedResults : mockMismatchedResults;
    return HttpResponse.json({ data: results });
  }),

  http.post("/api/supplier-invoices/:supplierInvoice/complete-review", async ({ params, request }) => {
    const id = String(params.supplierInvoice);
    const payload = await request.json() as SupplierInvoiceReviewActionRequest;
    const detail = details[id];

    if (!detail) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Supplier invoice not found." } },
        { status: 404 },
      );
    }

    if (payload.lockVersion !== detail.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Supplier invoice was updated by another user." } },
        { status: 409 },
      );
    }

    if (!isReviewActionAllowed(detail.status)) {
      return HttpResponse.json(
        { error: { code: "invalid_state", message: "Invoice is not currently in review." } },
        { status: 422 },
      );
    }

    const next: SupplierInvoice = {
      ...detail,
      status: "reviewed",
      reviewedByUserId: "buyer-1",
      reviewedAt: "2026-06-13T03:10:00.000Z",
      reviewNotes: payload.notes ?? null,
      reviewChecklist: payload.checklist ?? detail.reviewChecklist,
      reviewChecklistSummary: {
        total: 5,
        passed: Object.values(payload.checklist ?? {}).filter((item) => item.status === "pass").length,
        needsAttention: 0,
        failed: 0,
      },
      reviewBlockers: [],
      reviewBlockerCount: 0,
      lockVersion: detail.lockVersion + 1,
    };

    details[id] = next;
    rows = rows.map((row) =>
      row.id === id
        ? {
            ...row,
            status: next.status,
            reviewedAt: next.reviewedAt,
            reviewChecklistSummary: next.reviewChecklistSummary,
            reviewBlockerCount: 0,
            lockVersion: next.lockVersion,
          }
        : row,
    );

    return HttpResponse.json({ data: next });
  }),
];

import { http, HttpResponse } from "msw";
import { apPaymentImportBatchFixtures } from "./accounts-payable-payment-import-fixtures";
import type { ApPaymentImportBatchFixture } from "./accounts-payable-payment-import-fixtures";

let importBatches: ApPaymentImportBatchFixture[] = [];

export function resetAccountsPayablePaymentImportMockState() {
  importBatches = apPaymentImportBatchFixtures.map((b) => structuredClone(b));
}

resetAccountsPayablePaymentImportMockState();

function findBatch(batchId: string): ApPaymentImportBatchFixture | undefined {
  return importBatches.find((b) => b.batchId === batchId);
}

function updateRow(batchId: string, rowId: string, updates: Partial<ApPaymentImportBatchFixture["rows"][number]>) {
  const batch = findBatch(batchId);
  if (!batch) return undefined;
  const idx = batch.rows.findIndex((r) => r.id === rowId);
  if (idx === -1) return undefined;
  batch.rows[idx] = { ...batch.rows[idx], ...updates };
  return batch.rows[idx];
}

function updateSummary(batch: ApPaymentImportBatchFixture) {
  batch.summary = {
    total: batch.rows.length,
    pending: batch.rows.filter((r) => r.status === "pending").length,
    reconciled: batch.rows.filter((r) => r.status === "reconciled").length,
    failed: batch.rows.filter((r) => r.status === "failed").length,
    discarded: batch.rows.filter((r) => r.status === "discarded").length,
  };
}

export const accountsPayablePaymentImportHandlers = [
  http.post("/api/accounts-payable/payment-imports/upload", async ({ request }) => {
    const formData = await request.formData();
    const file = formData.get("file");

    if (!file) {
      return HttpResponse.json(
        { error: { code: "unprocessable_entity", message: "File is required." } },
        { status: 422 },
      );
    }

    const batchId = `import-batch-${Date.now()}`;
    const newBatch: ApPaymentImportBatchFixture = {
      batchId,
      rows: [
        {
          id: `import-row-${Date.now()}-1`,
          batchId,
          rowIndex: 1,
          handoffNumber: "APH-2026-000010",
          invoiceNumber: "INV-2026-000010",
          paymentReference: "PMT-UPLOAD-001",
          allocatedAmount: "5000.0000",
          markFull: true,
          settlementAmount: "5000.0000",
          settlementCurrency: "USD",
          targetStatus: "paid",
          status: "pending",
          importedByUserId: "buyer-1",
          importedAt: new Date().toISOString(),
          lockVersion: 1,
        },
      ],
      summary: {
        total: 1,
        pending: 1,
        reconciled: 0,
        failed: 0,
        discarded: 0,
      },
    };

    importBatches.push(newBatch);

    return HttpResponse.json({ data: newBatch }, { status: 201 });
  }),

  http.get("/api/accounts-payable/payment-imports/:batchId", ({ params }) => {
    const batch = findBatch(String(params.batchId));

    if (!batch) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Import batch not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: batch });
  }),

  http.patch("/api/accounts-payable/payment-imports/:_import", async ({ params, request }) => {
    const rowId = String(params._import);
    const body = (await request.json()) as {
      lockVersion: number;
      handoffNumber?: string;
      invoiceNumber?: string;
      allocatedAmount?: string;
      markFull?: boolean;
      settlementAmount?: string;
      settlementCurrency?: string;
      paidAt?: string;
      settlementMethod?: string;
      failureCode?: string;
      failureReason?: string;
      voidReason?: string;
    };

    let foundBatch: ApPaymentImportBatchFixture | undefined;
    let foundRow: ApPaymentImportBatchFixture["rows"][number] | undefined;

    for (const batch of importBatches) {
      const row = batch.rows.find((r) => r.id === rowId);
      if (row) {
        foundBatch = batch;
        foundRow = row;
        break;
      }
    }

    if (!foundRow || !foundBatch) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Import row not found." } },
        { status: 404 },
      );
    }

    if (body.lockVersion !== foundRow.lockVersion) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Import row was updated by another user." } },
        { status: 409 },
      );
    }

    const updated = updateRow(foundBatch.batchId, rowId, {
      handoffNumber: body.handoffNumber ?? foundRow.handoffNumber,
      invoiceNumber: body.invoiceNumber ?? foundRow.invoiceNumber,
      allocatedAmount: body.allocatedAmount ?? foundRow.allocatedAmount,
      markFull: body.markFull ?? foundRow.markFull,
      settlementAmount: body.settlementAmount ?? foundRow.settlementAmount,
      settlementCurrency: body.settlementCurrency ?? foundRow.settlementCurrency,
      paidAt: body.paidAt ?? foundRow.paidAt,
      settlementMethod: body.settlementMethod ?? foundRow.settlementMethod,
      failureCode: (body.failureCode as typeof foundRow.failureCode) ?? foundRow.failureCode,
      failureReason: body.failureReason ?? foundRow.failureReason,
      voidReason: body.voidReason ?? foundRow.voidReason,
      lockVersion: foundRow.lockVersion + 1,
    })!;

    return HttpResponse.json({ data: updated });
  }),

  http.post("/api/accounts-payable/payment-imports/:batchId/reconcile", async ({ params, request }) => {
    const batchId = String(params.batchId);
    const body = (await request.json().catch(() => null)) as { lockVersions?: number[] } | null;
    const batch = findBatch(batchId);

    if (!batch) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Import batch not found." } },
        { status: 404 },
      );
    }

    let reconciledCount = 0;
    let failedCount = 0;
    let skippedCount = 0;
    const errors: Array<{ rowIndex: number; message: string }> = [];

    for (const row of batch.rows) {
      if (row.status !== "pending") {
        skippedCount++;
        continue;
      }

      if (body?.lockVersions && !body.lockVersions.includes(row.lockVersion)) {
        failedCount++;
        errors.push({ rowIndex: row.rowIndex, message: "Lock version mismatch." });
        continue;
      }

      if (row.handoffNumber) {
        row.status = "reconciled";
        row.reconciledAt = new Date().toISOString();
        row.reconciledByUserId = "buyer-1";
        row.lockVersion = row.lockVersion + 1;
        reconciledCount++;
      } else {
        failedCount++;
        errors.push({ rowIndex: row.rowIndex, message: "Handoff number is required for reconciliation." });
      }
    }

    updateSummary(batch);

    return HttpResponse.json({
      data: {
        reconciledCount,
        failedCount,
        skippedCount,
        errors: errors.length > 0 ? errors : undefined,
      },
    });
  }),

  http.post("/api/accounts-payable/payment-imports/:_import/discard", async ({ params }) => {
    const rowId = String(params._import);

    let foundBatch: ApPaymentImportBatchFixture | undefined;
    let foundRow: ApPaymentImportBatchFixture["rows"][number] | undefined;

    for (const batch of importBatches) {
      const row = batch.rows.find((r) => r.id === rowId);
      if (row) {
        foundBatch = batch;
        foundRow = row;
        break;
      }
    }

    if (!foundRow || !foundBatch) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Import row not found." } },
        { status: 404 },
      );
    }

    const updated = updateRow(foundBatch.batchId, rowId, {
      status: "discarded",
      lockVersion: foundRow.lockVersion + 1,
    })!;

    updateSummary(foundBatch);

    return HttpResponse.json({ data: updated });
  }),
];

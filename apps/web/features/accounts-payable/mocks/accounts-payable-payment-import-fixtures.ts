import type { ApPaymentImportRow } from "@cognify/api-client/schemas";

export interface ApPaymentImportBatchFixture {
  batchId: string;
  rows: ApPaymentImportRow[];
  summary: {
    total?: number;
    pending?: number;
    reconciled?: number;
    failed?: number;
    discarded?: number;
  };
}

export const apPaymentImportBatchFixtures: ApPaymentImportBatchFixture[] = [
  {
    batchId: "import-batch-1",
    rows: [
      {
        id: "import-row-1",
        batchId: "import-batch-1",
        rowIndex: 1,
        handoffNumber: "APH-2026-000010",
        invoiceNumber: "INV-2026-000010",
        paymentReference: "PMT-001",
        allocatedAmount: "8500.0000",
        markFull: true,
        settlementAmount: "8500.0000",
        settlementCurrency: "USD",
        paidAt: "2026-06-20T10:00:00.000Z",
        settlementMethod: "wire",
        targetStatus: "paid",
        status: "pending",
        importedByUserId: "buyer-1",
        importedAt: "2026-06-20T09:00:00.000Z",
        lockVersion: 1,
      },
      {
        id: "import-row-2",
        batchId: "import-batch-1",
        rowIndex: 2,
        handoffNumber: "APH-2026-000012",
        invoiceNumber: "INV-2026-000013",
        paymentReference: "PMT-002",
        allocatedAmount: "5600.0000",
        markFull: false,
        settlementAmount: "5600.0000",
        settlementCurrency: "USD",
        targetStatus: "failed",
        failureCode: "bank_rejected",
        failureReason: "Beneficiary account closed",
        status: "pending",
        importedByUserId: "buyer-1",
        importedAt: "2026-06-20T09:00:00.000Z",
        lockVersion: 1,
      },
      {
        id: "import-row-3",
        batchId: "import-batch-1",
        rowIndex: 3,
        handoffNumber: "APH-2026-000013",
        invoiceNumber: "INV-2026-000014",
        paymentReference: "PMT-003",
        allocatedAmount: "3200.0000",
        markFull: true,
        settlementAmount: "3200.0000",
        settlementCurrency: "USD",
        targetStatus: "voided",
        voidReason: "Duplicate payment detected",
        status: "pending",
        importedByUserId: "buyer-1",
        importedAt: "2026-06-20T09:00:00.000Z",
        lockVersion: 1,
      },
    ],
    summary: {
      total: 3,
      pending: 3,
      reconciled: 0,
      failed: 0,
      discarded: 0,
    },
  },
];

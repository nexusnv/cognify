export interface ApPaymentHandoffStatusFixture {
  id: string;
  number: string;
  status: "draft" | "ready" | "exported" | "cancelled" | "scheduled" | "paid" | "failed" | "voided";
  effectivePaymentDate: string | null;
  notes: string | null;
  currency: string;
  totalAmount: string;
  remittanceReference: string | null;
  snapshot: Record<string, unknown>;
  readinessWarnings: Array<{ severity: string; message: string; context?: string }>;
  createdByUserId: string;
  readyByUserId: string | null;
  readyAt: string | null;
  cancelledByUserId: string | null;
  cancelledAt: string | null;
  cancelledReason: string | null;
  lastExportedByUserId: string | null;
  lastExportedAt: string | null;
  lastExportFormat: string | null;
  lockVersion: number;
  invoiceCount?: number;
  createdAt: string;
  updatedAt: string;
  scheduledForDate?: string | null;
  paymentReference?: string | null;
  paidAt?: string | null;
  failedAt?: string | null;
  failureCode?: string | null;
  failureReason?: string | null;
  voidedAt?: string | null;
  voidReason?: string | null;
  varianceReason?: string | null;
  varianceAmount?: string | null;
}

export const apPaymentHandoffStatusFixtures: ApPaymentHandoffStatusFixture[] = [
  {
    id: "handoff-scheduled-1",
    number: "APH-2026-000010",
    status: "scheduled",
    effectivePaymentDate: "2026-07-15",
    notes: "Scheduled for mid-month payment run.",
    currency: "USD",
    totalAmount: "8500.0000",
    remittanceReference: null,
    snapshot: {
      invoiceCount: 1,
      totalAmount: "8500.0000",
      currency: "USD",
      invoices: [{ id: "invoice-sched-1", number: "INV-2026-000010", amount: "8500.0000" }],
    },
    readinessWarnings: [],
    createdByUserId: "buyer-1",
    readyByUserId: "buyer-1",
    readyAt: "2026-06-18T10:00:00.000Z",
    cancelledByUserId: null,
    cancelledAt: null,
    cancelledReason: null,
    lastExportedByUserId: "buyer-1",
    lastExportedAt: "2026-06-18T11:00:00.000Z",
    lastExportFormat: "json",
    lockVersion: 3,
    invoiceCount: 1,
    createdAt: "2026-06-16T08:00:00.000Z",
    updatedAt: "2026-06-18T11:00:00.000Z",
    scheduledForDate: "2026-07-15",
    paymentReference: "PMT-SCHED-001",
  },
  {
    id: "handoff-paid-1",
    number: "APH-2026-000011",
    status: "paid",
    effectivePaymentDate: "2026-06-20",
    notes: "Paid in full via wire transfer.",
    currency: "USD",
    totalAmount: "12300.0000",
    remittanceReference: "WIRE-2026-001",
    snapshot: {
      invoiceCount: 2,
      totalAmount: "12300.0000",
      currency: "USD",
      invoices: [
        { id: "invoice-paid-1", number: "INV-2026-000011", amount: "7800.0000" },
        { id: "invoice-paid-2", number: "INV-2026-000012", amount: "4500.0000" },
      ],
    },
    readinessWarnings: [],
    createdByUserId: "buyer-1",
    readyByUserId: "buyer-1",
    readyAt: "2026-06-17T10:00:00.000Z",
    cancelledByUserId: null,
    cancelledAt: null,
    cancelledReason: null,
    lastExportedByUserId: "buyer-1",
    lastExportedAt: "2026-06-17T11:00:00.000Z",
    lastExportFormat: "json",
    lockVersion: 4,
    invoiceCount: 2,
    createdAt: "2026-06-15T08:00:00.000Z",
    updatedAt: "2026-06-20T14:00:00.000Z",
    scheduledForDate: "2026-06-20",
    paymentReference: "WIRE-2026-001",
    paidAt: "2026-06-20T14:00:00.000Z",
  },
  {
    id: "handoff-failed-1",
    number: "APH-2026-000012",
    status: "failed",
    effectivePaymentDate: "2026-06-18",
    notes: "Bank rejected the payment.",
    currency: "USD",
    totalAmount: "5600.0000",
    remittanceReference: null,
    snapshot: {
      invoiceCount: 1,
      totalAmount: "5600.0000",
      currency: "USD",
      invoices: [{ id: "invoice-fail-1", number: "INV-2026-000013", amount: "5600.0000" }],
    },
    readinessWarnings: [],
    createdByUserId: "buyer-1",
    readyByUserId: "buyer-1",
    readyAt: "2026-06-16T10:00:00.000Z",
    cancelledByUserId: null,
    cancelledAt: null,
    cancelledReason: null,
    lastExportedByUserId: "buyer-1",
    lastExportedAt: "2026-06-16T11:00:00.000Z",
    lastExportFormat: "json",
    lockVersion: 4,
    invoiceCount: 1,
    createdAt: "2026-06-14T08:00:00.000Z",
    updatedAt: "2026-06-18T09:30:00.000Z",
    scheduledForDate: "2026-06-18",
    paymentReference: "PMT-FAIL-001",
    failedAt: "2026-06-18T09:30:00.000Z",
    failureCode: "bank_rejected",
    failureReason: "Beneficiary account closed",
  },
  {
    id: "handoff-voided-1",
    number: "APH-2026-000013",
    status: "voided",
    effectivePaymentDate: "2026-06-19",
    notes: "Voided due to duplicate payment.",
    currency: "USD",
    totalAmount: "3200.0000",
    remittanceReference: null,
    snapshot: {
      invoiceCount: 1,
      totalAmount: "3200.0000",
      currency: "USD",
      invoices: [{ id: "invoice-void-1", number: "INV-2026-000014", amount: "3200.0000" }],
    },
    readinessWarnings: [],
    createdByUserId: "buyer-1",
    readyByUserId: "buyer-1",
    readyAt: "2026-06-17T10:00:00.000Z",
    cancelledByUserId: null,
    cancelledAt: null,
    cancelledReason: null,
    lastExportedByUserId: "buyer-1",
    lastExportedAt: "2026-06-17T11:00:00.000Z",
    lastExportFormat: "json",
    lockVersion: 4,
    invoiceCount: 1,
    createdAt: "2026-06-13T08:00:00.000Z",
    updatedAt: "2026-06-19T16:00:00.000Z",
    scheduledForDate: "2026-06-19",
    paymentReference: "PMT-VOID-001",
    voidedAt: "2026-06-19T16:00:00.000Z",
    voidReason: "Duplicate payment detected",
  },
];

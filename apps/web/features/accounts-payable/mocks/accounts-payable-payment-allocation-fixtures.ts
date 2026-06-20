export interface ApPaymentAllocationFixture {
  id: string;
  apPaymentHandoffId: string;
  supplierInvoiceId: string;
  supplierInvoiceNumber?: string;
  allocatedAmount: string;
  allocationDate: string;
  paymentReference?: string;
  settlementAmount?: string;
  settlementCurrency?: string;
  voidedAt?: string;
  lockVersion: number;
}

export const apPaymentAllocationFixtures: ApPaymentAllocationFixture[] = [
  {
    id: "alloc-1",
    apPaymentHandoffId: "handoff-paid-1",
    supplierInvoiceId: "invoice-paid-1",
    supplierInvoiceNumber: "INV-2026-000011",
    allocatedAmount: "7800.0000",
    allocationDate: "2026-06-20",
    paymentReference: "WIRE-2026-001",
    settlementAmount: "7800.0000",
    settlementCurrency: "USD",
    lockVersion: 1,
  },
  {
    id: "alloc-2",
    apPaymentHandoffId: "handoff-paid-1",
    supplierInvoiceId: "invoice-paid-2",
    supplierInvoiceNumber: "INV-2026-000012",
    allocatedAmount: "4500.0000",
    allocationDate: "2026-06-20",
    paymentReference: "WIRE-2026-001",
    settlementAmount: "4500.0000",
    settlementCurrency: "USD",
    lockVersion: 1,
  },
];

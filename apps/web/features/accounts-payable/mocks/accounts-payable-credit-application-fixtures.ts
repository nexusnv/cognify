import type { CreditApplication } from "@cognify/api-client/schemas";

const _applications: CreditApplication[] = [
  {
    id: "ca-1",
    supplierCreditMemoId: "cm-3",
    supplierCreditMemoNumber: "CM-2026-000003",
    supplierInvoiceId: "inv-3",
    supplierInvoiceNumber: "INV-2026-000044",
    appliedAmount: "500.00",
    applicationDate: "2026-06-19",
    appliedByUserId: "user-1",
    notes: "First application",
    voidedAt: null,
    voidedByUserId: null,
    voidReason: null,
    lockVersion: 1,
  },
];

export const creditApplicationFixtures = {
  all: () => _applications,
  setApplications: (next: CreditApplication[]) => {
    _applications.length = 0;
    _applications.push(...next);
  },
  addApplication: (application: CreditApplication) => {
    _applications.push(application);
  },
};

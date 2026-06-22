import { http, HttpResponse } from "msw";
import { creditApplicationFixtures } from "./accounts-payable-credit-application-fixtures";

const seedApplications = [
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

export function resetCreditApplicationMockState() {
  creditApplicationFixtures.setApplications(seedApplications.map((a) => ({ ...a })));
}

resetCreditApplicationMockState();

export const accountsPayableCreditApplicationHandlers = [
  http.get("/api/supplier-credit-memos/:creditMemo/applications", () => {
    return HttpResponse.json({ data: creditApplicationFixtures.all() });
  }),

  http.get("/api/credit-applications/:id", ({ params }) => {
    const application = creditApplicationFixtures.all().find((a) => a.id === params.id);
    if (!application) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: application });
  }),

  http.post("/api/supplier-credit-memos/:creditMemo/applications", async ({ request }) => {
    const body = (await request.json()) as {
      appliedAmount: string;
      supplierInvoiceId: string;
      applicationDate: string;
    };
    const newApplication = {
      id: `ca-${Date.now()}`,
      supplierCreditMemoId: "cm-3",
      supplierCreditMemoNumber: "CM-2026-000003",
      supplierInvoiceId: body.supplierInvoiceId,
      supplierInvoiceNumber: "INV-2026-000044",
      appliedAmount: body.appliedAmount,
      applicationDate: body.applicationDate,
      appliedByUserId: "user-1",
      notes: null,
      voidedAt: null,
      voidedByUserId: null,
      voidReason: null,
      lockVersion: 1,
    };
    creditApplicationFixtures.addApplication(newApplication);
    return HttpResponse.json({ data: newApplication }, { status: 201 });
  }),

  http.delete("/api/credit-applications/:id", async ({ params, request }) => {
    const body = (await request.json().catch(() => ({}))) as {
      voidReason?: string;
    };
    const application = creditApplicationFixtures.all().find((a) => a.id === params.id);
    if (!application) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({
      data: {
        ...application,
        voidedAt: new Date().toISOString(),
        voidReason: body.voidReason ?? "Test void",
      },
    });
  }),
];

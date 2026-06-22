import { http, HttpResponse } from "msw";
import { creditMemoFixtures } from "./accounts-payable-credit-memo-fixtures";

let _nextNumber = 4;

export function resetCreditMemoMockState() {
  _nextNumber = 4;
  creditMemoFixtures.setMemos([
    {
      id: "cm-1",
      tenantId: "tenant-1",
      number: "CM-2026-000001",
      vendorCreditMemoNumber: "VCM-001",
      vendorId: "vendor-1",
      vendorName: "Acme Supplies",
      originalInvoiceId: "inv-1",
      originalInvoiceNumber: "INV-2026-000042",
      status: "draft",
      currency: "USD",
      subtotalAmount: "1000.00",
      taxAmount: "80.00",
      freightAmount: "0.00",
      totalAmount: "1080.00",
      appliedAmount: "0.00",
      remainingAmount: "1080.00",
      creditDate: "2026-06-15",
      lockVersion: 1,
      lines: [
        {
          id: "cml-1",
          supplierCreditMemoId: "cm-1",
          lineNumber: 1,
          description: "Widget A return",
          quantity: "10.0000",
          unitPrice: "100.0000",
          lineSubtotal: "1000.0000",
          taxCode: "TX_STD",
          taxAmount: "80.0000",
          purchaseOrderLineId: null,
          originalInvoiceLineId: null,
          notes: null,
        },
      ],
      applications: [],
      exceptions: [],
      permissions: {
        canEdit: true,
        canSubmit: true,
        canPost: false,
        canApply: false,
        canVoidApplication: false,
        canVoidCreditMemo: true,
        canResolveException: true,
      },
    },
    {
      id: "cm-2",
      tenantId: "tenant-1",
      number: "CM-2026-000002",
      vendorCreditMemoNumber: "VCM-002",
      vendorId: "vendor-1",
      vendorName: "Acme Supplies",
      originalInvoiceId: "inv-2",
      originalInvoiceNumber: "INV-2026-000043",
      status: "open",
      currency: "USD",
      subtotalAmount: "500.00",
      taxAmount: "40.00",
      freightAmount: "0.00",
      totalAmount: "540.00",
      appliedAmount: "0.00",
      remainingAmount: "540.00",
      creditDate: "2026-06-16",
      lockVersion: 1,
      lines: [],
      applications: [],
      exceptions: [],
      permissions: {
        canEdit: false,
        canSubmit: false,
        canPost: false,
        canApply: true,
        canVoidApplication: true,
        canVoidCreditMemo: true,
        canResolveException: true,
      },
    },
    {
      id: "cm-3",
      tenantId: "tenant-1",
      number: "CM-2026-000003",
      vendorCreditMemoNumber: "VCM-003",
      vendorId: "vendor-2",
      vendorName: "Beta Logistics",
      originalInvoiceId: "inv-3",
      originalInvoiceNumber: "INV-2026-000044",
      status: "partially_applied",
      currency: "USD",
      subtotalAmount: "1000.00",
      taxAmount: "0.00",
      freightAmount: "0.00",
      totalAmount: "1000.00",
      appliedAmount: "500.00",
      remainingAmount: "500.00",
      creditDate: "2026-06-18",
      lockVersion: 3,
      lines: [],
      applications: [
        {
          id: "ca-1",
          supplierCreditMemoId: "cm-3",
          supplierCreditMemoNumber: "CM-2026-000003",
          supplierInvoiceId: "inv-3",
          supplierInvoiceNumber: "INV-2026-000044",
          appliedAmount: "500.00",
          applicationDate: "2026-06-19",
          appliedByUserId: "user-1",
          notes: null,
          voidedAt: null,
          voidedByUserId: null,
          voidReason: null,
          lockVersion: 1,
        },
      ],
      exceptions: [],
      permissions: {
        canEdit: false,
        canSubmit: false,
        canPost: false,
        canApply: true,
        canVoidApplication: true,
        canVoidCreditMemo: true,
        canResolveException: true,
      },
    },
  ]);
}

resetCreditMemoMockState();

export const accountsPayableCreditMemoHandlers = [
  http.get("/api/supplier-credit-memos", ({ request }) => {
    const url = new URL(request.url);
    const status = url.searchParams.get("status");
    const all = creditMemoFixtures.all().filter((m) => (status ? m.status === status : true));
    return HttpResponse.json({
      data: all,
      meta: { total: all.length, perPage: 25, currentPage: 1 },
    });
  }),

  http.get("/api/supplier-credit-memos/:id", ({ params }) => {
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    return HttpResponse.json({ data: memo });
  }),

  http.post("/api/supplier-credit-memos", async ({ request }) => {
    const body = (await request.json()) as {
      vendorId: string;
      totalAmount: string;
    };
    const newMemo = {
      id: `cm-${_nextNumber}`,
      tenantId: "tenant-1",
      number: `CM-2026-00000${_nextNumber}`,
      vendorCreditMemoNumber: null,
      vendorId: body.vendorId,
      vendorName: "Mock Vendor",
      originalInvoiceId: null,
      originalInvoiceNumber: null,
      status: "draft" as const,
      currency: "USD",
      subtotalAmount: body.totalAmount,
      taxAmount: "0.00",
      freightAmount: "0.00",
      totalAmount: body.totalAmount,
      appliedAmount: "0.00",
      remainingAmount: body.totalAmount,
      creditDate: new Date().toISOString().split("T")[0],
      lockVersion: 1,
      lines: [],
      applications: [],
      exceptions: [],
      permissions: {
        canEdit: true,
        canSubmit: true,
        canPost: false,
        canApply: false,
        canVoidApplication: false,
        canVoidCreditMemo: true,
        canResolveException: true,
      },
    };
    _nextNumber += 1;
    creditMemoFixtures.setMemos([...creditMemoFixtures.all(), newMemo]);
    return HttpResponse.json({ data: newMemo }, { status: 201 });
  }),

  http.patch("/api/supplier-credit-memos/:id", async ({ params, request }) => {
    const body = (await request.json()) as { lockVersion: number };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.lockVersion !== body.lockVersion) {
      return new HttpResponse(JSON.stringify({ message: "Stale lock version" }), { status: 409 });
    }
    return HttpResponse.json({
      data: { ...memo, lockVersion: memo.lockVersion + 1 },
    });
  }),

  http.post("/api/supplier-credit-memos/:id/submit", async ({ params, request }) => {
    const body = (await request.json()) as { lockVersion: number };
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status !== "draft") {
      return new HttpResponse(JSON.stringify({ message: "Not in draft state" }), {
        status: 409,
      });
    }
    if (memo.lockVersion !== body.lockVersion) {
      return new HttpResponse(JSON.stringify({ message: "Stale lock version" }), {
        status: 409,
      });
    }
    return HttpResponse.json({
      data: { ...memo, status: "pending_approval", lockVersion: memo.lockVersion + 1 },
    });
  }),

  http.post("/api/supplier-credit-memos/:id/post", async ({ params }) => {
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status !== "approved") {
      return new HttpResponse(JSON.stringify({ message: "Not in approved state" }), {
        status: 409,
      });
    }
    return HttpResponse.json({
      data: { ...memo, status: "open", lockVersion: memo.lockVersion + 1 },
    });
  }),

  http.post("/api/supplier-credit-memos/:id/void", async ({ params }) => {
    const memo = creditMemoFixtures.findById(String(params.id));
    if (!memo) return new HttpResponse(null, { status: 404 });
    if (memo.status === "closed" || memo.status === "voided") {
      return new HttpResponse(JSON.stringify({ message: "Not voidable" }), { status: 409 });
    }
    return HttpResponse.json({
      data: { ...memo, status: "voided", lockVersion: memo.lockVersion + 1 },
    });
  }),
];

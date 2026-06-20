import { http, HttpResponse } from "msw";
import { apPaymentAllocationFixtures } from "./accounts-payable-payment-allocation-fixtures";
import type { ApPaymentAllocationFixture } from "./accounts-payable-payment-allocation-fixtures";

let allocations: ApPaymentAllocationFixture[] = [];

export function resetAccountsPayablePaymentAllocationMockState() {
  allocations = apPaymentAllocationFixtures.map((a) => structuredClone(a));
}

resetAccountsPayablePaymentAllocationMockState();

function findAllocation(id: string): ApPaymentAllocationFixture | undefined {
  return allocations.find((a) => a.id === id);
}

function findAllocationsByHandoff(handoffId: string): ApPaymentAllocationFixture[] {
  return allocations.filter((a) => a.apPaymentHandoffId === handoffId);
}

export const accountsPayablePaymentAllocationHandlers = [
  http.get("/api/ap-payment-handoffs/:handoff/allocations", ({ params }) => {
    const handoffId = String(params.handoff);
    const list = findAllocationsByHandoff(handoffId);

    return HttpResponse.json({ data: list });
  }),

  http.post("/api/ap-payment-handoffs/:handoff/allocations", async ({ params, request }) => {
    const handoffId = String(params.handoff);
    const body = (await request.json()) as {
      lockVersion: number;
      supplierInvoiceId: string;
      allocatedAmount: string;
      allocationDate: string;
      paymentReference?: string;
      settlementAmount?: string;
      settlementCurrency?: string;
    };

    const newAllocation: ApPaymentAllocationFixture = {
      id: `alloc-${Date.now()}`,
      apPaymentHandoffId: handoffId,
      supplierInvoiceId: body.supplierInvoiceId,
      allocatedAmount: body.allocatedAmount,
      allocationDate: body.allocationDate,
      paymentReference: body.paymentReference,
      settlementAmount: body.settlementAmount,
      settlementCurrency: body.settlementCurrency,
      lockVersion: 1,
    };

    allocations.push(newAllocation);

    return HttpResponse.json({ data: newAllocation }, { status: 201 });
  }),

  http.get("/api/ap-payment-allocations/:allocation", ({ params }) => {
    const allocation = findAllocation(String(params.allocation));

    if (!allocation) {
      return HttpResponse.json(
        { error: { code: "not_found", message: "Payment allocation not found." } },
        { status: 404 },
      );
    }

    return HttpResponse.json({ data: allocation });
  }),
];

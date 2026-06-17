import { http, HttpResponse } from "msw";
import { invoiceExceptionFixtures } from "./invoice-exception-fixtures";
import type { SupplierInvoiceException } from "@cognify/api-client/schemas";

let exceptions: SupplierInvoiceException[] = [];

export function resetInvoiceExceptionMockState() {
  exceptions = invoiceExceptionFixtures.exceptions.map((e) => ({ ...e }));
}

resetInvoiceExceptionMockState();

export const invoiceExceptionHandlers = [
  http.get("/api/supplier-invoices/:supplierInvoice/exceptions", () => {
    return HttpResponse.json({ data: exceptions });
  }),

  http.post(
    "/api/supplier-invoices/:supplierInvoice/exceptions/:exception/resolve",
    async ({ params, request }) => {
      const body = (await request.json()) as {
        lockVersion: number;
        resolutionType: string;
        adjustedValue?: string;
        explanation?: string;
      };
      const idx = exceptions.findIndex((e) => e.id === params.exception);
      if (idx === -1) return new HttpResponse(null, { status: 404 });
      if (exceptions[idx].lockVersion !== body.lockVersion) {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        );
      }
      exceptions[idx] = {
        ...exceptions[idx],
        status: "resolved" as const,
        resolutionType: body.resolutionType as "value_adjustment" | "explanation",
        resolutionData: {
          ...(body.adjustedValue ? { adjusted_value: body.adjustedValue } : {}),
          ...(body.explanation ? { explanation: body.explanation } : {}),
        },
        resolvedByUserId: "user-1",
        resolvedAt: new Date().toISOString(),
        lockVersion: exceptions[idx].lockVersion + 1,
      };
      return HttpResponse.json({ data: exceptions[idx] });
    },
  ),

  http.post(
    "/api/supplier-invoices/:supplierInvoice/exceptions/:exception/escalate",
    async ({ params, request }) => {
      const body = (await request.json()) as {
        lockVersion: number;
        escalatedToUserId: string;
        note?: string;
      };
      const idx = exceptions.findIndex((e) => e.id === params.exception);
      if (idx === -1) return new HttpResponse(null, { status: 404 });
      if (exceptions[idx].lockVersion !== body.lockVersion) {
        return HttpResponse.json(
          { error: { code: "conflict", message: "Exception was updated by another user." } },
          { status: 409 },
        );
      }
      exceptions[idx] = {
        ...exceptions[idx],
        status: "escalated" as const,
        escalatedToUserId: body.escalatedToUserId,
        escalatedByUserId: "user-1",
        escalatedAt: new Date().toISOString(),
        escalationNote: body.note ?? null,
        lockVersion: exceptions[idx].lockVersion + 1,
      };
      return HttpResponse.json({ data: exceptions[idx] });
    },
  ),
];

import { http, HttpResponse } from "msw";
import { creditMemoExceptionFixtures } from "./accounts-payable-credit-memo-exception-fixtures";

export function resetCreditMemoExceptionMockState() {
  creditMemoExceptionFixtures.setExceptions([]);
}

resetCreditMemoExceptionMockState();

export const accountsPayableCreditMemoExceptionHandlers = [
  http.get("/api/supplier-credit-memos/:creditMemo/exceptions", () => {
    return HttpResponse.json({ data: creditMemoExceptionFixtures.all() });
  }),

  http.post(
    "/api/supplier-credit-memos/:creditMemo/exceptions/:exception/acknowledge",
    async ({ params }) => {
      const exception = creditMemoExceptionFixtures
        .all()
        .find((e) => e.id === params.exception);
      if (!exception) return new HttpResponse(null, { status: 404 });
      return HttpResponse.json({
        data: { ...exception, acknowledgedAt: new Date().toISOString() },
      });
    },
  ),

  http.post(
    "/api/supplier-credit-memos/:creditMemo/exceptions/:exception/resolve",
    async ({ params }) => {
      const exception = creditMemoExceptionFixtures
        .all()
        .find((e) => e.id === params.exception);
      if (!exception) return new HttpResponse(null, { status: 404 });
      return HttpResponse.json({
        data: { ...exception, resolvedAt: new Date().toISOString() },
      });
    },
  ),

  http.post(
    "/api/supplier-credit-memos/:creditMemo/exceptions/:exception/escalate",
    async ({ params }) => {
      const exception = creditMemoExceptionFixtures
        .all()
        .find((e) => e.id === params.exception);
      if (!exception) return new HttpResponse(null, { status: 404 });
      return HttpResponse.json({
        data: { ...exception, escalatedAt: new Date().toISOString() },
      });
    },
  ),
];

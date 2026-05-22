import { HttpResponse, delay, http } from "msw";
import type { SaveQuotationComparisonNoteRequest } from "@cognify/api-client/schemas";
import {
  createQuotationComparisonNoteFixture,
  deleteQuotationComparisonNoteFixture,
  getQuotationComparisonFixture,
  resetQuotationComparisonMockState,
  updateQuotationComparisonNoteFixture,
} from "./quotation-comparison-fixtures";

export { resetQuotationComparisonMockState };

function forbidden(message: string) {
  return HttpResponse.json({ error: { code: "forbidden", message } }, { status: 403 });
}

function notFound(message = "Quotation comparison not found.") {
  return HttpResponse.json({ error: { code: "not_found", message } }, { status: 404 });
}

function validationFailed(message: string) {
  return HttpResponse.json({ error: { code: "validation_failed", message } }, { status: 422 });
}

async function readNotePayload(request: Request): Promise<SaveQuotationComparisonNoteRequest | null> {
  try {
    const payload = (await request.json()) as Partial<SaveQuotationComparisonNoteRequest> | null;
    if (typeof payload?.note !== "string" || payload.note.trim() === "") return null;

    return {
      ...payload,
      note: payload.note.trim(),
    } as SaveQuotationComparisonNoteRequest;
  } catch {
    return null;
  }
}

export const quotationComparisonHandlers = [
  http.get("/api/rfqs/:rfq/comparison", ({ params }) => {
    const comparison = getQuotationComparisonFixture(String(params.rfq));
    if (!comparison) return notFound();

    return HttpResponse.json({ data: comparison });
  }),

  http.post("/api/rfqs/:rfq/comparison/notes", async ({ params, request }) => {
    const payload = await readNotePayload(request);
    if (!payload) {
      return validationFailed("A comparison note is required.");
    }

    const rfqId = String(params.rfq);
    try {
      const note = createQuotationComparisonNoteFixture(rfqId, payload);
      return HttpResponse.json({ data: note }, { status: 201 });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.patch("/api/rfqs/:rfq/comparison/notes/:note", async ({ params, request }) => {
    const payload = await readNotePayload(request);
    if (!payload) {
      return validationFailed("A comparison note is required.");
    }

    try {
      const note = updateQuotationComparisonNoteFixture(String(params.rfq), String(params.note), payload);
      return HttpResponse.json({ data: note });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.delete("/api/rfqs/:rfq/comparison/notes/:note", async ({ params }) => {
    await delay(10);

    try {
      deleteQuotationComparisonNoteFixture(String(params.rfq), String(params.note));
      return new HttpResponse(null, { status: 204 });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.get("/api/rfqs/:rfq/comparison/forbidden", () => forbidden("You do not have access to quotation comparisons.")),
];

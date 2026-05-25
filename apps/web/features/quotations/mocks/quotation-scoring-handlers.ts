import { HttpResponse, http } from "msw";
import type { SaveQuotationScoringTemplateRequest, UpdateRfqScorecardScoresRequest } from "@cognify/api-client/schemas";
import {
  completeRfqScorecardFixture,
  createRfqScorecardFixture,
  deactivateScoringTemplateFixture,
  getRfqScorecardFixture,
  getScoringTemplateFixture,
  listScoringTemplateFixtures,
  reopenRfqScorecardFixture,
  resetQuotationScoringMockState,
  saveScoringTemplateFixture,
  updateRfqScorecardScoresFixture,
} from "./quotation-scoring-fixtures";

export { resetQuotationScoringMockState };

function notFound(message = "Quotation scoring resource not found.") {
  return HttpResponse.json({ error: { code: "not_found", message } }, { status: 404 });
}

function validationFailed(message: string) {
  return HttpResponse.json({ error: { code: "validation_failed", message } }, { status: 422 });
}

function invalidState(message: string) {
  return HttpResponse.json({ error: { code: "invalid_state", message } }, { status: 409 });
}

async function readJson<T>(request: Request): Promise<T | null> {
  try {
    return (await request.json()) as T;
  } catch {
    return null;
  }
}

export const quotationScoringHandlers = [
  http.get("/api/quotation-scoring/templates", () => {
    return HttpResponse.json({ data: listScoringTemplateFixtures() });
  }),

  http.post("/api/quotation-scoring/templates", async ({ request }) => {
    const payload = await readJson<SaveQuotationScoringTemplateRequest>(request);
    if (!payload?.name || !Array.isArray(payload.criteria) || payload.criteria.length === 0) {
      return validationFailed("A scoring template requires a name and at least one criterion.");
    }

    return HttpResponse.json({ data: saveScoringTemplateFixture(payload) });
  }),

  http.get("/api/quotation-scoring/templates/:template", ({ params }) => {
    const template = getScoringTemplateFixture(String(params.template));
    if (!template) return notFound("Scoring template not found.");

    return HttpResponse.json({ data: template });
  }),

  http.patch("/api/quotation-scoring/templates/:template", async ({ params, request }) => {
    const payload = await readJson<SaveQuotationScoringTemplateRequest>(request);
    if (!payload?.name || !Array.isArray(payload.criteria) || payload.criteria.length === 0) {
      return validationFailed("A scoring template requires a name and at least one criterion.");
    }

    return HttpResponse.json({ data: saveScoringTemplateFixture(payload, String(params.template)) });
  }),

  http.post("/api/quotation-scoring/templates/:template/deactivate", ({ params }) => {
    try {
      return HttpResponse.json({ data: deactivateScoringTemplateFixture(String(params.template)) });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.get("/api/rfqs/:rfq/scorecard", ({ params }) => {
    const scorecard = getRfqScorecardFixture(String(params.rfq));
    if (!scorecard) return notFound("RFQ scorecard not found.");

    return HttpResponse.json({ data: scorecard });
  }),

  http.post("/api/rfqs/:rfq/scorecard", async ({ params, request }) => {
    const payload = await readJson<{ templateId?: string }>(request);
    if (!payload?.templateId) return validationFailed("A scoring template is required.");

    try {
      return HttpResponse.json({ data: createRfqScorecardFixture(String(params.rfq), payload.templateId) });
    } catch (error) {
      return invalidState(error instanceof Error ? error.message : "Scorecard could not be created.");
    }
  }),

  http.patch("/api/rfqs/:rfq/scorecard/scores", async ({ params, request }) => {
    const payload = await readJson<UpdateRfqScorecardScoresRequest>(request);
    if (!payload?.entries?.length) return validationFailed("At least one score entry is required.");

    try {
      return HttpResponse.json({ data: updateRfqScorecardScoresFixture(String(params.rfq), payload.entries) });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.post("/api/rfqs/:rfq/scorecard/complete", ({ params }) => {
    try {
      return HttpResponse.json({ data: completeRfqScorecardFixture(String(params.rfq)) });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),

  http.post("/api/rfqs/:rfq/scorecard/reopen", ({ params }) => {
    try {
      return HttpResponse.json({ data: reopenRfqScorecardFixture(String(params.rfq)) });
    } catch (error) {
      return notFound(error instanceof Error ? error.message : undefined);
    }
  }),
];

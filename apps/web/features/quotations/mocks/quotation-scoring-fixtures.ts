import type {
  QuotationScoringTemplate,
  RfqScorecard,
  SaveQuotationScoringTemplateRequest,
  UpdateRfqScorecardScoreEntryRequest,
} from "@cognify/api-client/schemas";

let templates: QuotationScoringTemplate[] = [];
let scorecards: Record<string, RfqScorecard | null> = {};
let templateSequence = 10;

export function resetQuotationScoringMockState(): void {
  templateSequence = 10;
  templates = [
    scoringTemplate("template-balanced", "Balanced RFQ Evaluation", true, 2, 0),
    scoringTemplate("template-technical", "Technical Fit", true, 2, 4),
    scoringTemplate("template-retired", "Legacy Cost Only", false, 1, 7),
  ];
  scorecards = {
    "rfq-ready": buildScorecard("rfq-ready", "in_progress"),
    "rfq-incomplete": buildScorecard("rfq-incomplete", "in_progress", true),
    "rfq-completed": buildScorecard("rfq-completed", "completed"),
    "rfq-no-scorecard": null,
  };
}

export function listScoringTemplateFixtures(): QuotationScoringTemplate[] {
  return templates.map(clone);
}

export function getScoringTemplateFixture(templateId: string): QuotationScoringTemplate | null {
  return clone(templates.find((template) => template.id === templateId) ?? null);
}

export function saveScoringTemplateFixture(
  payload: SaveQuotationScoringTemplateRequest,
  templateId?: string,
): QuotationScoringTemplate {
  const existing = templateId ? templates.find((template) => template.id === templateId) : null;
  const template: QuotationScoringTemplate = {
    id: existing?.id ?? `template-${templateSequence++}`,
    name: payload.name,
    description: payload.description ?? null,
    active: existing?.active ?? true,
    criteria: payload.criteria.map((criterion, index) => ({
      id: existing?.criteria[index]?.id ?? `criterion-${templateSequence}-${index + 1}`,
      category: criterion.category,
      label: criterion.label,
      guidance: criterion.guidance ?? null,
      weight: String(Number(criterion.weight).toFixed(2)),
      maxScore: criterion.maxScore,
      required: criterion.required,
      displayOrder: criterion.displayOrder,
    })),
    usageCount: existing?.usageCount ?? 0,
    permissions: {
      canView: true,
      canUpdate: true,
      canDeactivate: true,
    },
  };

  templates = existing
    ? templates.map((item) => (item.id === existing.id ? template : item))
    : [template, ...templates];

  return clone(template);
}

export function updateScoringTemplateFixture(
  templateId: string,
  payload: SaveQuotationScoringTemplateRequest,
): QuotationScoringTemplate {
  if (!templates.some((template) => template.id === templateId)) {
    throw new Error("Scoring template not found.");
  }

  return saveScoringTemplateFixture(payload, templateId);
}

export function deactivateScoringTemplateFixture(templateId: string): QuotationScoringTemplate {
  const template = templates.find((item) => item.id === templateId);
  if (!template) throw new Error("Scoring template not found.");
  template.active = false;

  return clone(template);
}

export function getRfqScorecardFixture(rfqId: string): RfqScorecard | null | undefined {
  return clone(scorecards[rfqId]);
}

export function createRfqScorecardFixture(rfqId: string, templateId: string): RfqScorecard {
  const template = templates.find((item) => item.id === templateId && item.active);
  if (!template) throw new Error("Active scoring template not found.");

  const scorecard = buildScorecard(rfqId, "in_progress", true);
  scorecard.scorecard.templateId = template.id;
  scorecard.scorecard.templateName = template.name;
  scorecard.scorecard.templateDescription = template.description;
  scorecard.criteria = template.criteria.map((criterion) => ({
    id: `scorecard-${criterion.id}`,
    sourceTemplateCriterionId: criterion.id,
    category: criterion.category,
    label: criterion.label,
    guidance: criterion.guidance,
    weight: criterion.weight,
    maxScore: criterion.maxScore,
    required: criterion.required,
    displayOrder: criterion.displayOrder,
  }));
  recalculate(scorecard);
  scorecards[rfqId] = scorecard;

  return clone(scorecard);
}

export function updateRfqScorecardScoresFixture(
  rfqId: string,
  entries: UpdateRfqScorecardScoreEntryRequest[],
): RfqScorecard {
  const scorecard = scorecards[rfqId];
  if (!scorecard) throw new Error("RFQ scorecard not found.");

  for (const entry of entries) {
    const existing = scorecard.entries.find(
      (item) => item.criterionId === entry.criterionId && item.vendorId === entry.vendorId,
    );
    const next = {
      criterionId: entry.criterionId,
      vendorId: entry.vendorId,
      quotationId: entry.quotationId ?? scorecard.vendors.find((vendor) => vendor.vendorId === entry.vendorId)?.quotationId ?? null,
      quotationVersionId: entry.quotationVersionId ?? scorecard.vendors.find((vendor) => vendor.vendorId === entry.vendorId)?.quotationVersionId ?? null,
      score: entry.score == null ? null : String(Number(entry.score).toFixed(2)),
      note: entry.note ?? null,
      weightedContribution: null,
      scoredAt: "2026-05-24T08:00:00.000000Z",
    };
    if (existing) Object.assign(existing, next);
    else scorecard.entries.push(next);
  }

  recalculate(scorecard);
  return clone(scorecard);
}

export function completeRfqScorecardFixture(rfqId: string): RfqScorecard {
  const scorecard = scorecards[rfqId];
  if (!scorecard) throw new Error("RFQ scorecard not found.");
  scorecard.scorecard.status = "completed";
  scorecard.scorecard.completedAt = "2026-05-24T09:00:00.000000Z";

  return clone(scorecard);
}

export function reopenRfqScorecardFixture(rfqId: string): RfqScorecard {
  const scorecard = scorecards[rfqId];
  if (!scorecard) throw new Error("RFQ scorecard not found.");
  scorecard.scorecard.status = "in_progress";
  scorecard.scorecard.completedAt = null;

  return clone(scorecard);
}

function scoringTemplate(
  id: string,
  name: string,
  active: boolean,
  criterionCount: number,
  usageCount: number,
): QuotationScoringTemplate {
  return {
    id,
    name,
    description: `${name} reusable scoring template.`,
    active,
    usageCount,
    permissions: {
      canView: true,
      canUpdate: true,
      canDeactivate: active,
    },
    criteria: Array.from({ length: criterionCount }, (_, index) => ({
      id: `${id}-criterion-${index + 1}`,
      category: index === 0 ? "cost" : "delivery",
      label: index === 0 ? "Total evaluated cost" : "Delivery confidence",
      guidance: index === 0 ? "Score against normalized total cost." : "Score against delivery evidence.",
      weight: index === 0 ? "50.00" : "50.00",
      maxScore: 10,
      required: true,
      displayOrder: index + 1,
    })),
  };
}

function buildScorecard(rfqId: string, status: "in_progress" | "completed", incomplete = false): RfqScorecard {
  const scorecard: RfqScorecard = {
    rfq: {
      id: rfqId,
      number: "RFQ-2026-0007",
      title: rfqId === "rfq-incomplete" ? "Server refresh evaluation" : "Laptop refresh program",
      status: "issued",
      responseDueAt: "2026-06-01T00:00:00.000000Z",
      scopeSummary: "Evaluate current quotation responses.",
      requisition: null,
      project: null,
    },
    scorecard: {
      id: `scorecard-${rfqId}`,
      templateId: "template-balanced",
      templateName: "Balanced RFQ Evaluation",
      templateDescription: "Balanced RFQ Evaluation reusable scoring template.",
      status,
      appliedAt: "2026-05-24T07:00:00.000000Z",
      completedAt: status === "completed" ? "2026-05-24T09:00:00.000000Z" : null,
    },
    criteria: [
      criterion("criterion-cost", "cost", "Total evaluated cost", "50.00", 1),
      criterion("criterion-delivery", "delivery", "Delivery confidence", "30.00", 2),
      criterion("criterion-quality", "quality", "Quality evidence", "20.00", 3),
    ],
    vendors: [
      vendor("vendor-1", "Northwind Traders", "quotation-1", "version-1"),
      vendor("vendor-2", "Contoso Supply", "quotation-2", "version-2"),
    ],
    entries: [],
    completion: {
      status: incomplete ? "incomplete" : "complete",
      requiredScoreCount: 6,
      completedRequiredScoreCount: incomplete ? 2 : 6,
      missingRequiredScoreCount: incomplete ? 4 : 0,
      scoreableVendorCount: 2,
    },
    comparisonContext: {
      comparisonPath: `/quotations/comparisons/${rfqId}`,
      normalizationPaths: {
        "vendor-1": "/quotations/normalizations/normalization-1",
        "vendor-2": "/quotations/normalizations/normalization-2",
      },
      quotationVersionPaths: {
        "vendor-1": "/quotations/quotation-1/versions/version-1",
        "vendor-2": "/quotations/quotation-2/versions/version-2",
      },
      readiness: {
        responseCount: 2,
        approvedNormalizationCount: 2,
        pendingNormalizationCount: 0,
        missingResponseCount: 0,
        mixedCurrency: false,
      },
      vendors: [],
      lineRows: [],
      commercialTerms: [],
      notes: [],
      noteGroups: [],
    },
    permissions: {
      canViewScorecard: true,
      canApplyScorecard: true,
      canManageScores: status !== "completed",
      canManageScoringTemplates: true,
    },
  };

  if (!incomplete) {
    for (const vendorItem of scorecard.vendors) {
      for (const criterionItem of scorecard.criteria) {
        scorecard.entries.push({
          criterionId: criterionItem.id,
          vendorId: vendorItem.vendorId,
          quotationId: vendorItem.quotationId,
          quotationVersionId: vendorItem.quotationVersionId ?? null,
          score: vendorItem.vendorId === "vendor-1" ? "8.00" : "7.00",
          note: null,
          weightedContribution: null,
          scoredAt: "2026-05-24T08:00:00.000000Z",
        });
      }
    }
  } else {
    const vendorItem = scorecard.vendors[0];
    for (const criterionItem of scorecard.criteria.slice(0, 2)) {
      scorecard.entries.push({
        criterionId: criterionItem.id,
        vendorId: vendorItem.vendorId,
        quotationId: vendorItem.quotationId,
        quotationVersionId: vendorItem.quotationVersionId ?? null,
        score: "8.00",
        note: null,
        weightedContribution: null,
        scoredAt: "2026-05-24T08:00:00.000000Z",
      });
    }
  }

  recalculate(scorecard);

  return scorecard;
}

function criterion(id: string, category: "cost" | "delivery" | "quality", label: string, weight: string, displayOrder: number) {
  return {
    id,
    sourceTemplateCriterionId: `${id}-template`,
    category,
    label,
    guidance: `Score ${label.toLowerCase()}.`,
    weight,
    maxScore: 10,
    required: true,
    displayOrder,
  };
}

function vendor(vendorId: string, vendorName: string, quotationId: string, quotationVersionId: string) {
  return {
    vendorId,
    vendorName,
    quotationId,
    quotationVersionId,
    scoreable: true,
    rawTotal: "0.00",
    weightedTotal: "0.00",
    missingRequiredCount: 0,
    readiness: "ready",
    currency: "USD",
    totalAmount: vendorId === "vendor-1" ? "12470.00" : "12900.00",
    leadTimeDays: vendorId === "vendor-1" ? "14" : "21",
    paymentTerms: "Net 30",
    deliveryTerms: "Delivered",
    warrantyTerms: "36 months",
    complianceNotes: "Meets baseline requirements.",
    issueCounts: { blocking: 0, warning: 0, info: 1 },
    links: {
      quotationVersion: `/quotations/${quotationId}/versions/${quotationVersionId}`,
      normalization: `/quotations/normalizations/normalization-${vendorId}`,
    },
  };
}

function recalculate(scorecard: RfqScorecard): void {
  let missingRequiredScoreCount = 0;

  for (const vendorItem of scorecard.vendors) {
    const entries = scorecard.entries.filter((entry) => entry.vendorId === vendorItem.vendorId);
    let raw = 0;
    let weighted = 0;
    let missing = 0;

    for (const criterionItem of scorecard.criteria) {
      const entry = entries.find((item) => item.criterionId === criterionItem.id);
      const contribution = weightedContribution(entry?.score ?? null, criterionItem.maxScore, criterionItem.weight);

      if (entry) {
        entry.weightedContribution = contribution === null ? null : contribution.toFixed(2);
      }

      if (entry?.score != null) {
        raw += Number(entry.score);
      } else if (criterionItem.required) {
        missing++;
      }

      weighted += contribution ?? 0;
    }

    missingRequiredScoreCount += missing;
    vendorItem.rawTotal = raw.toFixed(2);
    vendorItem.weightedTotal = weighted.toFixed(2);
    vendorItem.missingRequiredCount = missing;
  }

  const requiredCriterionCount = scorecard.criteria.filter((criterionItem) => criterionItem.required).length;
  const requiredScoreCount = requiredCriterionCount * scorecard.vendors.length;
  scorecard.completion = {
    status: missingRequiredScoreCount === 0 ? "complete" : "incomplete",
    requiredScoreCount,
    completedRequiredScoreCount: requiredScoreCount - missingRequiredScoreCount,
    missingRequiredScoreCount,
    scoreableVendorCount: scorecard.vendors.length,
  };
}

function weightedContribution(score: string | number | null, maxScore: string | number | null, weight: string | number | null): number | null {
  if (score == null || maxScore == null || weight == null) return null;

  const numericScore = Number(score);
  const numericMaxScore = Number(maxScore);
  const numericWeight = Number(weight);

  if (!Number.isFinite(numericScore) || !Number.isFinite(numericMaxScore) || !Number.isFinite(numericWeight) || numericMaxScore <= 0) {
    return null;
  }

  return (numericScore / numericMaxScore) * numericWeight;
}

function clone<T>(value: T): T {
  return value == null ? value : JSON.parse(JSON.stringify(value));
}

resetQuotationScoringMockState();

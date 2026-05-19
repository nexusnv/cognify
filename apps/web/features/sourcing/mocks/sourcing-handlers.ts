import { http, HttpResponse } from "msw";
import type { SourcingIntakeReview } from "../types/sourcing-view-model";
import { sourcingIntakeFixtures } from "./sourcing-fixtures";

let reviews: SourcingIntakeReview[] = structuredClone(sourcingIntakeFixtures);

function findReview(reviewId: string) {
  return reviews.find((review) => review.id === reviewId);
}

function notFound() {
  return HttpResponse.json(
    { error: { code: "not_found", message: "Sourcing intake review was not found." } },
    { status: 404 },
  );
}

function buildStatusCounts() {
  return {
    open: reviews.filter((review) => review.status === "open").length,
    in_review: reviews.filter((review) => review.status === "in_review").length,
    clarification_requested: reviews.filter((review) => review.status === "clarification_requested").length,
    ready_for_rfq: reviews.filter((review) => review.status === "ready_for_rfq").length,
    direct_award_recorded: reviews.filter((review) => review.status === "direct_award_recorded").length,
    closed: reviews.filter((review) => review.status === "closed").length,
    unassigned: reviews.filter((review) => review.assignedBuyer === null).length,
    mine: reviews.filter((review) => review.assignedBuyer?.id === "buyer-1").length,
  };
}

function applyFilters(source: SourcingIntakeReview[], request: Request) {
  const url = new URL(request.url);
  const preset = url.searchParams.get("preset");
  const status = url.searchParams.get("status");
  const assignedBuyer = url.searchParams.get("assignedBuyer");
  const department = url.searchParams.get("department");
  const search = url.searchParams.get("search")?.toLowerCase().trim();

  return source
    .filter((review) => {
      if (preset === "unassigned") return review.assignedBuyer === null;
      if (preset === "mine") return review.assignedBuyer?.id === "buyer-1";
      if (preset === "needs_clarification") return review.status === "clarification_requested";
      if (preset === "ready_for_rfq") return review.status === "ready_for_rfq";
      if (preset === "closed") return review.status === "closed" || review.status === "direct_award_recorded";
      return true;
    })
    .filter((review) => !status || review.status === status)
    .filter((review) => !assignedBuyer || review.assignedBuyer?.id === assignedBuyer)
    .filter((review) => !department || review.requisition.department === department)
    .filter((review) => {
      if (!search) return true;
      const haystack = [
        review.requisition.number,
        review.requisition.title,
        review.category,
        review.subcategory,
        review.decisionReason,
        review.assignedBuyer?.name,
      ]
        .filter(Boolean)
        .join(" ")
        .toLowerCase();
      return haystack.includes(search);
    });
}

function sortReviews(source: SourcingIntakeReview[], sort: string | null) {
  const data = [...source];

  if (sort === "target_date_asc") {
    return data.sort((a, b) => compareStrings(a.targetDecisionDate, b.targetDecisionDate));
  }

  if (sort === "needed_by_asc") {
    return data.sort((a, b) => compareStrings(a.requisition.neededByDate ?? null, b.requisition.neededByDate ?? null));
  }

  if (sort === "amount_desc") {
    return data.sort((a, b) => compareNumbers(b.requisition.estimatedTotal, a.requisition.estimatedTotal));
  }

  return data.sort((a, b) => compareStrings(b.updatedAt, a.updatedAt));
}

function compareStrings(left: string | null | undefined, right: string | null | undefined) {
  if (left === right) return 0;
  if (left == null) return 1;
  if (right == null) return -1;
  return left.localeCompare(right);
}

function compareNumbers(left: number | string | null | undefined, right: number | string | null | undefined) {
  const leftValue = left == null ? Number.NEGATIVE_INFINITY : Number(left);
  const rightValue = right == null ? Number.NEGATIVE_INFINITY : Number(right);
  return leftValue - rightValue;
}

export function resetSourcingMockState() {
  reviews = structuredClone(sourcingIntakeFixtures);
}

export const sourcingHandlers = [
  http.get("/api/sourcing/intake-reviews", ({ request }) => {
    const url = new URL(request.url);
    const page = Math.max(1, Number(url.searchParams.get("page") ?? 1) || 1);
    const perPage = Math.min(100, Math.max(1, Number(url.searchParams.get("perPage") ?? 25) || 25));
    const filtered = sortReviews(applyFilters(reviews, request), url.searchParams.get("sort"));
    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / perPage));
    const start = (page - 1) * perPage;
    const data = filtered.slice(start, start + perPage);

    return HttpResponse.json({
      data,
      meta: {
        currentPage: page,
        perPage,
        total,
        lastPage,
        statusCounts: buildStatusCounts(),
      },
    });
  }),
  http.get("/api/sourcing/intake-reviews/:reviewId", ({ params }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/claim", ({ params }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    review.status = "in_review";
    review.assignedBuyer = { id: "buyer-1", name: "Priya Buyer", email: "priya.buyer@acme.test" };
    review.claimedAt = "2026-05-19T08:00:00.000Z";
    review.permissions.canClaim = false;
    review.permissions.canRecordDecision = true;
    review.permissions.canReassign = true;
    review.updatedAt = "2026-05-19T08:00:00.000Z";
    return HttpResponse.json({ data: review });
  }),
  http.patch("/api/sourcing/intake-reviews/:reviewId", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    if (["ready_for_rfq", "direct_award_recorded", "closed"].includes(review.status)) {
      return HttpResponse.json(
        { error: { code: "conflict", message: "Decided intake reviews cannot be edited." } },
        { status: 409 },
      );
    }

    const payload = (await request.json()) as Partial<SourcingIntakeReview>;
    if ("category" in payload) review.category = payload.category ?? null;
    if ("subcategory" in payload) review.subcategory = payload.subcategory ?? null;
    if ("urgency" in payload) review.urgency = payload.urgency ?? null;
    if ("complexity" in payload) review.complexity = payload.complexity ?? null;
    if ("targetDecisionDate" in payload) review.targetDecisionDate = payload.targetDecisionDate ?? null;
    if ("checklist" in payload) review.checklist = payload.checklist ?? [];
    if ("internalNotes" in payload) review.internalNotes = payload.internalNotes ?? null;
    review.updatedAt = "2026-05-19T08:05:00.000Z";
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/decision", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    const payload = (await request.json()) as {
      sourcingPath: string;
      decisionReason: string;
      clarificationMessage?: string | null;
      clarificationFields?: string[];
    };

    review.sourcingPath = payload.sourcingPath as SourcingIntakeReview["sourcingPath"];
    review.decisionReason = payload.decisionReason;
    review.clarificationMessage = payload.clarificationMessage ?? null;
    review.status =
      payload.sourcingPath === "needs_rfq"
        ? "ready_for_rfq"
        : payload.sourcingPath === "needs_clarification"
          ? "clarification_requested"
          : "direct_award_recorded";
    review.permissions.canUpdate = false;
    review.permissions.canRecordDecision = false;
    review.permissions.canCreateRfq = payload.sourcingPath === "needs_rfq";
    review.updatedAt = "2026-05-19T08:10:00.000Z";
    review.decidedAt = "2026-05-19T08:10:00.000Z";
    return HttpResponse.json({ data: review });
  }),
  http.post("/api/sourcing/intake-reviews/:reviewId/close", async ({ params, request }) => {
    const review = findReview(String(params.reviewId));
    if (!review) return notFound();
    const payload = (await request.json()) as {
      sourcingPath: "no_sourcing_required";
      decisionReason: string;
    };

    review.status = "closed";
    review.sourcingPath = payload.sourcingPath;
    review.decisionReason = payload.decisionReason;
    review.closedAt = "2026-05-19T08:15:00.000Z";
    review.permissions.canUpdate = false;
    review.permissions.canRecordDecision = false;
    review.permissions.canClose = false;
    review.permissions.canClaim = false;
    review.updatedAt = "2026-05-19T08:15:00.000Z";
    return HttpResponse.json({ data: review });
  }),
];

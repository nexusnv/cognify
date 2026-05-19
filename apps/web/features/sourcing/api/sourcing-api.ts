import {
  claimSourcingIntakeReview as claimSourcingIntakeReviewEndpoint,
  closeSourcingIntakeReview as closeSourcingIntakeReviewEndpoint,
  createRequisitionSourcingIntake as createRequisitionSourcingIntakeEndpoint,
  getSourcingIntakeReview as getSourcingIntakeReviewEndpoint,
  listSourcingIntakeReviews as listSourcingIntakeReviewsEndpoint,
  recordSourcingIntakeReviewDecision as recordSourcingIntakeReviewDecisionEndpoint,
  reassignSourcingIntakeReview as reassignSourcingIntakeReviewEndpoint,
  updateSourcingIntakeReview as updateSourcingIntakeReviewEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ListSourcingIntakeReviewsParams,
  SourcingIntakeReview as ApiSourcingIntakeReview,
  SourcingIntakeReviewCloseRequest,
  SourcingIntakeReviewDecisionRequest,
  SourcingIntakeReviewListResponse as ApiSourcingIntakeReviewListResponse,
  SourcingIntakeReviewReassignRequest,
  SourcingIntakeReviewUpdateRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type {
  SourcingIntakeListResponse,
  SourcingIntakeQuery,
  SourcingIntakeReview,
} from "../types/sourcing-view-model";
import type {
  SourcingIntakeDecisionValues,
  SourcingIntakeReviewFormValues,
} from "../schemas/sourcing-intake-schema";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export async function fetchSourcingIntakeReviews(query: SourcingIntakeQuery = {}) {
  const params = Object.fromEntries(
    Object.entries(query).filter(([, value]) => value !== ""),
  ) as ListSourcingIntakeReviewsParams;
  const response = await listSourcingIntakeReviewsEndpoint(params, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapListResponse(response.data);
}

export async function fetchSourcingIntakeReview(reviewId: string) {
  const response = await getSourcingIntakeReviewEndpoint(reviewId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function createSourcingIntakeForRequisition(requisitionId: string) {
  const response = await createRequisitionSourcingIntakeEndpoint(
    requisitionId,
    withActiveTenantHeader(),
  );
  if (response.status !== 200 && response.status !== 201) throw response.data;
  return mapReview(response.data.data);
}

export async function claimIntakeReview(reviewId: string) {
  const response = await claimSourcingIntakeReviewEndpoint(reviewId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function reassignIntakeReview(reviewId: string, buyerId: string) {
  const request: SourcingIntakeReviewReassignRequest = { buyerId };
  const response = await reassignSourcingIntakeReviewEndpoint(
    reviewId,
    request,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function saveIntakeReview(reviewId: string, values: SourcingIntakeReviewFormValues) {
  const request = values satisfies SourcingIntakeReviewUpdateRequest;
  const response = await updateSourcingIntakeReviewEndpoint(
    reviewId,
    request,
    withActiveTenantHeader(),
  );
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export async function decideIntakeReview(reviewId: string, values: SourcingIntakeDecisionValues) {
  const request = values satisfies SourcingIntakeReviewDecisionRequest;
  const response =
    values.sourcingPath === "no_sourcing_required"
      ? await closeSourcingIntakeReviewEndpoint(
          reviewId,
          {
            sourcingPath: "no_sourcing_required",
            decisionReason: values.decisionReason,
          } satisfies SourcingIntakeReviewCloseRequest,
          withActiveTenantHeader(),
        )
      : await recordSourcingIntakeReviewDecisionEndpoint(
          reviewId,
          request,
          withActiveTenantHeader(),
        );
  if (response.status !== 200) throw response.data;
  return mapReview(response.data.data);
}

export function mapReview(review: ApiSourcingIntakeReview): SourcingIntakeReview {
  return {
    id: review.id,
    tenantId: review.tenantId,
    status: review.status,
    sourcingPath: review.sourcingPath ?? null,
    category: review.category ?? null,
    subcategory: review.subcategory ?? null,
    urgency: review.urgency ?? null,
    complexity: review.complexity ?? null,
    targetDecisionDate: review.targetDecisionDate ?? null,
    checklist: review.checklist ?? [],
    internalNotes: review.internalNotes ?? null,
    decisionReason: review.decisionReason ?? null,
    clarificationMessage: review.clarificationMessage ?? null,
    assignedBuyer: review.assignedBuyer ?? null,
    requisition: {
      id: review.requisition.id,
      number: review.requisition.number,
      title: review.requisition.title,
      status: review.requisition.status,
      requester: review.requisition.requester ?? null,
      department: review.requisition.department ?? null,
      neededByDate: review.requisition.neededByDate ?? null,
      estimatedTotal: review.requisition.estimatedTotal ?? null,
      currency: review.requisition.currency ?? null,
    },
    project: review.project ?? null,
    permissions: review.permissions,
    claimedAt: review.claimedAt ?? null,
    decidedAt: review.decidedAt ?? null,
    closedAt: review.closedAt ?? null,
    createdAt: review.createdAt ?? null,
    updatedAt: review.updatedAt ?? null,
  };
}

export function mapListResponse(
  response: ApiSourcingIntakeReviewListResponse,
): SourcingIntakeListResponse {
  const statusCounts = {
    open: response.meta.statusCounts.open ?? 0,
    in_review: response.meta.statusCounts.in_review ?? 0,
    clarification_requested: response.meta.statusCounts.clarification_requested ?? 0,
    ready_for_rfq: response.meta.statusCounts.ready_for_rfq ?? 0,
    direct_award_recorded: response.meta.statusCounts.direct_award_recorded ?? 0,
    closed: response.meta.statusCounts.closed ?? 0,
    unassigned: response.meta.statusCounts.unassigned ?? 0,
    mine: response.meta.statusCounts.mine ?? 0,
  } satisfies SourcingIntakeListResponse["meta"]["statusCounts"];

  return {
    data: response.data.map(mapReview),
    meta: {
      currentPage: response.meta.currentPage,
      perPage: response.meta.perPage,
      total: response.meta.total,
      statusCounts,
    },
  };
}

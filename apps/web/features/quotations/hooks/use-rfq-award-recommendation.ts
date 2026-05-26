"use client";

import { useQuery } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  fetchRfqAwardRecommendationApprovalSummary,
  previewRfqAwardRecommendationRoute,
  showRfqAwardRecommendation,
} from "../api/quotation-award-recommendation-api";

export function rfqAwardRecommendationQueryKey(rfqId: string, tenantId?: string | null) {
  return ["rfq-award-recommendation", tenantId ?? "no-tenant", rfqId] as const;
}

export function useRfqAwardRecommendation(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: rfqAwardRecommendationQueryKey(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot load RFQ award recommendation without an RFQ id.");
      }

      return showRfqAwardRecommendation(rfqId, tenantId);
    },
    enabled: Boolean(rfqId && tenantId),
  });
}

export function rfqAwardRecommendationApprovalSummaryQueryKey(rfqId: string, tenantId?: string | null) {
  return ["rfq-award-recommendation-approval-summary", tenantId ?? "no-tenant", rfqId] as const;
}

export function useRfqAwardRecommendationApprovalSummary(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: rfqAwardRecommendationApprovalSummaryQueryKey(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot load award approval summary without an RFQ id.");
      }

      return fetchRfqAwardRecommendationApprovalSummary(rfqId, tenantId);
    },
    enabled: Boolean(rfqId && tenantId),
  });
}

export function rfqAwardRecommendationApprovalPreviewQueryKey(rfqId: string, tenantId?: string | null) {
  return ["rfq-award-recommendation-approval-preview", tenantId ?? "no-tenant", rfqId] as const;
}

export function useRfqAwardRecommendationApprovalPreview(rfqId: string | null | undefined) {
  const tenantId = getStoredActiveTenantId();
  const queryRfqId = rfqId ?? "no-rfq";

  return useQuery({
    queryKey: rfqAwardRecommendationApprovalPreviewQueryKey(queryRfqId, tenantId),
    queryFn: () => {
      if (!rfqId) {
        throw new Error("Cannot preview award approval without an RFQ id.");
      }

      return previewRfqAwardRecommendationRoute(rfqId, tenantId);
    },
    enabled: Boolean(rfqId && tenantId),
  });
}

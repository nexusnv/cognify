import {
  cancelRfqDraft as cancelRfqDraftEndpoint,
  createSourcingIntakeRfq as createSourcingIntakeRfqEndpoint,
  getRfq as getRfqEndpoint,
  updateRfqDraft as updateRfqDraftEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  Rfq as ApiRfq,
  RfqCancelRequest,
  RfqUpdateRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import type {
  RfqCancelValues,
  RfqDraftFormValues,
} from "../schemas/rfq-draft-schema";
import { toRfqDraftViewModel, type RfqDraft } from "../types/rfq-view-model";

function withActiveTenantHeader(): RequestInit | undefined {
  const tenantId = getStoredActiveTenantId();
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export async function fetchRfqDraft(rfqId: string): Promise<RfqDraft> {
  const response = await getRfqEndpoint(rfqId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return toRfqDraftViewModel(response.data.data as ApiRfq);
}

export async function createRfqDraftFromIntake(reviewId: string): Promise<RfqDraft> {
  const response = await createSourcingIntakeRfqEndpoint(reviewId, withActiveTenantHeader());
  if (response.status !== 200 && response.status !== 201) throw response.data;
  return toRfqDraftViewModel(response.data.data as ApiRfq);
}

export async function saveRfqDraft(
  rfqId: string,
  values: RfqDraftFormValues,
): Promise<RfqDraft> {
  const request = values satisfies RfqUpdateRequest;
  const response = await updateRfqDraftEndpoint(rfqId, request, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return toRfqDraftViewModel(response.data.data as ApiRfq);
}

export async function cancelRfqDraft(rfqId: string, values: RfqCancelValues): Promise<RfqDraft> {
  const request = values satisfies RfqCancelRequest;
  const response = await cancelRfqDraftEndpoint(rfqId, request, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;
  return toRfqDraftViewModel(response.data.data as ApiRfq);
}

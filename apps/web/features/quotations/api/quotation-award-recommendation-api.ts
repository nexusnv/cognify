import {
  cancelPurchaseOrderRequestHandoff,
  createRfqAwardRecommendationPoHandoff,
  exportPurchaseOrderRequestHandoffJson,
  getRfqAwardRecommendationApprovalSummary,
  markPurchaseOrderRequestHandoffReady,
  previewRfqAwardRecommendationApproval,
  routeRfqAwardRecommendationForApproval,
  saveRfqAwardRecommendation as saveRfqAwardRecommendationEndpoint,
  showRfqAwardRecommendation as showRfqAwardRecommendationEndpoint,
  showRfqAwardRecommendationPoHandoff,
  submitRfqAwardRecommendation as submitRfqAwardRecommendationEndpoint,
  updatePurchaseOrderRequestHandoff,
  withdrawRfqAwardRecommendation as withdrawRfqAwardRecommendationEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  ApprovalPreview,
  ApprovalSummary,
  CancelPurchaseOrderRequestHandoffRequest,
  MarkPurchaseOrderRequestHandoffReadyRequest,
  PurchaseOrderRequestHandoff,
  PurchaseOrderRequestHandoffExport,
  RfqAwardRecommendation,
  SaveRfqAwardRecommendationRequest,
  SubmitRfqAwardRecommendationRequest,
  UpdatePurchaseOrderRequestHandoffRequest,
  WithdrawRfqAwardRecommendationRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

function throwResponseData(error: unknown): never {
  if (typeof error === "object" && error !== null && "data" in error) {
    throw (error as { data: unknown }).data;
  }

  throw error;
}

function unwrapOk(response: { status: number; data: unknown }, expectedStatus = 200): unknown {
  if (response.status !== expectedStatus) throw response.data;

  return (response.data as { data: unknown }).data;
}

export async function showRfqAwardRecommendation(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await showRfqAwardRecommendationEndpoint(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function saveRfqAwardRecommendation(
  rfqId: string,
  payload: SaveRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await saveRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function submitRfqAwardRecommendation(
  rfqId: string,
  payload?: SubmitRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await submitRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function withdrawRfqAwardRecommendation(
  rfqId: string,
  payload: WithdrawRfqAwardRecommendationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqAwardRecommendation> {
  const response = await withdrawRfqAwardRecommendationEndpoint(rfqId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as RfqAwardRecommendation;
}

export async function routeRfqAwardRecommendationApproval(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApprovalSummary> {
  const response = await routeRfqAwardRecommendationForApproval(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as ApprovalSummary;
}

export async function fetchRfqAwardRecommendationApprovalSummary(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApprovalSummary | null> {
  const response = await getRfqAwardRecommendationApprovalSummary(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as ApprovalSummary | null;
}

export async function previewRfqAwardRecommendationRoute(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<ApprovalPreview> {
  const response = await previewRfqAwardRecommendationApproval(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as ApprovalPreview;
}

export async function fetchRfqAwardRecommendationPoHandoff(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoff | null> {
  const response = await showRfqAwardRecommendationPoHandoff(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as PurchaseOrderRequestHandoff | null;
}

export async function createRfqAwardRecommendationPoHandoffForRfq(
  rfqId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoff> {
  const response = await createRfqAwardRecommendationPoHandoff(rfqId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as PurchaseOrderRequestHandoff;
}

export async function updateRfqAwardRecommendationPoHandoff(
  handoffId: string,
  payload: UpdatePurchaseOrderRequestHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoff> {
  const response = await updatePurchaseOrderRequestHandoff(handoffId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as PurchaseOrderRequestHandoff;
}

export async function markRfqAwardRecommendationPoHandoffReady(
  handoffId: string,
  payload: MarkPurchaseOrderRequestHandoffReadyRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoff> {
  const response = await markPurchaseOrderRequestHandoffReady(handoffId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as PurchaseOrderRequestHandoff;
}

export async function cancelRfqAwardRecommendationPoHandoff(
  handoffId: string,
  payload: CancelPurchaseOrderRequestHandoffRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoff> {
  const response = await cancelPurchaseOrderRequestHandoff(handoffId, payload, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  return unwrapOk(response) as PurchaseOrderRequestHandoff;
}

export async function exportRfqAwardRecommendationPoHandoffJson(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<PurchaseOrderRequestHandoffExport> {
  const response = await exportPurchaseOrderRequestHandoffJson(handoffId, withActiveTenantHeader(tenantId)).catch(
    throwResponseData,
  );

  if (response.status !== 200) throw response.data;

  return response.data as PurchaseOrderRequestHandoffExport;
}

export async function downloadPurchaseOrderRequestHandoffCsv(
  handoffId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<Blob> {
  const response = await fetch(`/api/po-handoffs/${handoffId}/export.csv`, {
    credentials: "include",
    headers: tenantId ? { "X-Tenant-Id": tenantId } : undefined,
  });

  if (!response.ok) {
    throw await response.json().catch(() => ({ error: { message: "CSV export failed." } }));
  }

  return response.blob();
}

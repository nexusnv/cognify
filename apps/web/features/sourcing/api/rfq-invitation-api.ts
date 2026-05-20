import {
  cancelRfqInvitation as cancelRfqInvitationEndpoint,
  createRfqInvitations as createRfqInvitationsEndpoint,
  listRfqInvitations as listRfqInvitationsEndpoint,
  resendRfqInvitation as resendRfqInvitationEndpoint,
  updateRfqInvitationStatus as updateRfqInvitationStatusEndpoint,
} from "@cognify/api-client/endpoints";
import type {
  CancelRfqInvitationRequest,
  CreateRfqInvitationsRequest,
  RfqInvitation as ApiRfqInvitation,
  UpdateRfqInvitationStatusRequest,
} from "@cognify/api-client/schemas";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  toRfqInvitationViewModel,
  type RfqInvitationViewModel,
} from "../types/rfq-invitation-view-model";

function withActiveTenantHeader(tenantId: string | null = getStoredActiveTenantId()): RequestInit | undefined {
  if (!tenantId) return undefined;

  return {
    headers: {
      "X-Tenant-Id": tenantId,
    },
  };
}

export async function fetchRfqInvitations(rfqId: string): Promise<RfqInvitationViewModel[]> {
  const response = await listRfqInvitationsEndpoint(rfqId, withActiveTenantHeader());
  if (response.status !== 200) throw response.data;

  return response.data.data.map((invitation: ApiRfqInvitation) => toRfqInvitationViewModel(invitation));
}

export async function createRfqInvitations(
  rfqId: string,
  values: CreateRfqInvitationsRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqInvitationViewModel[]> {
  const response = await createRfqInvitationsEndpoint(rfqId, values, withActiveTenantHeader(tenantId));
  if (response.status !== 201) throw response.data;

  return response.data.data.map((invitation: ApiRfqInvitation) => toRfqInvitationViewModel(invitation));
}

export async function resendRfqInvitation(
  invitationId: string,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqInvitationViewModel> {
  const response = await resendRfqInvitationEndpoint(invitationId, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return toRfqInvitationViewModel(response.data.data as ApiRfqInvitation);
}

export async function cancelRfqInvitation(
  invitationId: string,
  values: CancelRfqInvitationRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqInvitationViewModel> {
  const response = await cancelRfqInvitationEndpoint(invitationId, values, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return toRfqInvitationViewModel(response.data.data as ApiRfqInvitation);
}

export async function updateRfqInvitationStatus(
  invitationId: string,
  values: UpdateRfqInvitationStatusRequest,
  tenantId: string | null = getStoredActiveTenantId(),
): Promise<RfqInvitationViewModel> {
  const response = await updateRfqInvitationStatusEndpoint(invitationId, values, withActiveTenantHeader(tenantId));
  if (response.status !== 200) throw response.data;

  return toRfqInvitationViewModel(response.data.data as ApiRfqInvitation);
}

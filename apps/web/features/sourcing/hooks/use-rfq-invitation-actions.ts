import { useMutation, useQueryClient } from "@tanstack/react-query";
import { getStoredActiveTenantId } from "@/features/identity/api/identity-api";
import {
  cancelRfqInvitation,
  createRfqInvitations,
  resendRfqInvitation,
  updateRfqInvitationStatus,
} from "../api/rfq-invitation-api";
import type {
  RfqInvitationCancelValues,
  RfqInvitationCreateValues,
  RfqInvitationStatusValues,
} from "../schemas/rfq-invitation-schema";
import { rfqInvitationKeys } from "./use-rfq-invitations";

function replaceInvitationInList<T extends { id: string }>(list: T[] | undefined, updated: T) {
  if (!list) return [updated];
  return list.map((item) => (item.id === updated.id ? updated : item));
}

export function useCreateRfqInvitations(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (values: RfqInvitationCreateValues) =>
      createRfqInvitations(rfqId, values as Parameters<typeof createRfqInvitations>[1], tenantId),
    onSuccess: (invitations) => {
      queryClient.setQueryData(rfqInvitationKeys.list(rfqId, tenantId), invitations);
    },
  });
}

export function useResendRfqInvitation(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: (invitationId: string) => resendRfqInvitation(invitationId, tenantId),
    onSuccess: (updatedInvitation) => {
      queryClient.setQueryData(rfqInvitationKeys.list(rfqId, tenantId), (current: unknown) =>
        replaceInvitationInList(
          Array.isArray(current) ? (current as Array<{ id: string }>) : undefined,
          updatedInvitation,
        ),
      );
    },
  });
}

export function useCancelRfqInvitation(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({
      invitationId,
      values,
    }: {
      invitationId: string;
      values: RfqInvitationCancelValues;
    }) => cancelRfqInvitation(invitationId, values as Parameters<typeof cancelRfqInvitation>[1], tenantId),
    onSuccess: (updatedInvitation) => {
      queryClient.setQueryData(rfqInvitationKeys.list(rfqId, tenantId), (current: unknown) =>
        replaceInvitationInList(
          Array.isArray(current) ? (current as Array<{ id: string }>) : undefined,
          updatedInvitation,
        ),
      );
    },
  });
}

export function useUpdateRfqInvitationStatus(rfqId: string) {
  const queryClient = useQueryClient();
  const tenantId = getStoredActiveTenantId();

  return useMutation({
    mutationFn: ({
      invitationId,
      values,
    }: {
      invitationId: string;
      values: RfqInvitationStatusValues;
    }) => updateRfqInvitationStatus(invitationId, values as Parameters<typeof updateRfqInvitationStatus>[1], tenantId),
    onSuccess: (updatedInvitation) => {
      queryClient.setQueryData(rfqInvitationKeys.list(rfqId, tenantId), (current: unknown) =>
        replaceInvitationInList(
          Array.isArray(current) ? (current as Array<{ id: string }>) : undefined,
          updatedInvitation,
        ),
      );
    },
  });
}

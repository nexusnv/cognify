"use client";

import { useMemo, useState } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import { Button, Textarea } from "@cognify/ui";
import { RfqInvitationDialog } from "./rfq-invitation-dialog";
import { RfqInvitationStatusBadge } from "./rfq-invitation-status-badge";
import { VendorPicker } from "./vendor-picker";
import {
  useCancelRfqInvitation,
  useCreateRfqInvitations,
  useResendRfqInvitation,
  useUpdateRfqInvitationStatus,
} from "../hooks/use-rfq-invitation-actions";
import { useRfqInvitations } from "../hooks/use-rfq-invitations";
import {
  rfqInvitationCancelSchema,
  rfqInvitationCreateSchema,
  rfqInvitationStatusSchema,
} from "../schemas/rfq-invitation-schema";
import type { RfqInvitationViewModel } from "../types/rfq-invitation-view-model";

export function RfqInvitationPanel({
  rfqId,
  canInvite,
  readOnlyReason,
}: {
  rfqId: string;
  canInvite: boolean;
  readOnlyReason?: string;
}) {
  const invitationQuery = useRfqInvitations(rfqId);
  const createMutation = useCreateRfqInvitations(rfqId);
  const resendMutation = useResendRfqInvitation(rfqId);
  const cancelMutation = useCancelRfqInvitation(rfqId);
  const updateStatusMutation = useUpdateRfqInvitationStatus(rfqId);

  const [createOpen, setCreateOpen] = useState(false);
  const [createSearch, setCreateSearch] = useState("");
  const [selectedVendorIds, setSelectedVendorIds] = useState<string[]>([]);
  const [createError, setCreateError] = useState<string | null>(null);

  const [cancelTarget, setCancelTarget] = useState<RfqInvitationViewModel | null>(null);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelError, setCancelError] = useState<string | null>(null);

  const invitations = useMemo(
    () =>
      [...(invitationQuery.data ?? [])].sort(
        (left, right) => new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime(),
      ),
    [invitationQuery.data],
  );

  const readOnlyMessage =
    readOnlyReason ?? (!canInvite ? "Vendor invitations are read-only for this RFQ." : null);

  function openCreateDialog() {
    setCreateError(null);
    setCreateSearch("");
    setSelectedVendorIds([]);
    setCreateOpen(true);
  }

  function closeCreateDialog() {
    setCreateOpen(false);
    setCreateError(null);
    setCreateSearch("");
    setSelectedVendorIds([]);
  }

  function toggleVendor(vendorId: string) {
    setCreateError(null);
    setSelectedVendorIds((current) =>
      current.includes(vendorId)
        ? current.filter((selectedVendorId) => selectedVendorId !== vendorId)
        : [...current, vendorId],
    );
  }

  async function submitCreateInvitations() {
    const parsed = rfqInvitationCreateSchema.safeParse({ vendorIds: selectedVendorIds });
    if (!parsed.success) {
      setCreateError(parsed.error.issues[0]?.message ?? "Select at least one vendor.");
      return;
    }

    try {
      await createMutation.mutateAsync(parsed.data);
      closeCreateDialog();
    } catch (error) {
      setCreateError(getApiErrorMessage(error));
    }
  }

  function openCancelDialog(invitation: RfqInvitationViewModel) {
    setCancelTarget(invitation);
    setCancelReason("");
    setCancelError(null);
  }

  function closeCancelDialog() {
    setCancelTarget(null);
    setCancelReason("");
    setCancelError(null);
  }

  async function submitCancelInvitation() {
    const parsed = rfqInvitationCancelSchema.safeParse({ cancelReason: cancelReason.trim() });
    if (!parsed.success) {
      setCancelError(parsed.error.issues[0]?.message ?? "Cancel reason is required.");
      return;
    }
    if (!cancelTarget) return;

    try {
      await cancelMutation.mutateAsync({
        invitationId: cancelTarget.id,
        values: parsed.data,
      });
      closeCancelDialog();
    } catch (error) {
      setCancelError(getApiErrorMessage(error));
    }
  }

  async function markInvitationStatus(invitationId: string, status: "acknowledged" | "declined" | "expired") {
    const parsed = rfqInvitationStatusSchema.safeParse({ status });
    if (!parsed.success) return;

    await updateStatusMutation.mutateAsync({
      invitationId,
      values: parsed.data,
    });
  }

  if (invitationQuery.isLoading && invitations.length === 0) {
    return (
      <section id="vendor-invitations" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Vendor invitations</h2>
        <p className="mt-2 text-sm text-muted-foreground">Loading invitation records...</p>
      </section>
    );
  }

  if (invitationQuery.isError) {
    return (
      <section id="vendor-invitations" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Vendor invitations</h2>
        <div role="alert" className="mt-3 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
          {getApiErrorMessage(invitationQuery.error)}
        </div>
      </section>
    );
  }

  return (
    <section id="vendor-invitations" className="rounded-md border p-4">
      <div className="flex items-start justify-between gap-4">
        <div className="space-y-2">
          <div className="space-y-1">
            <h2 className="text-base font-semibold">Vendor invitations</h2>
            <p className="text-sm text-muted-foreground">
              Track invitation records for this RFQ. Email delivery and vendor portal access arrive later.
            </p>
          </div>
          {readOnlyMessage ? <p className="text-sm text-amber-700">{readOnlyMessage}</p> : null}
        </div>

        {canInvite ? (
          <Button onClick={openCreateDialog}>Invite vendors</Button>
        ) : null}
      </div>

      <div className="mt-4 space-y-3">
        {invitations.length > 0 ? (
          invitations.map((invitation) => (
            <article
              key={invitation.id}
              data-testid="rfq-invitation-card"
              className="rounded-md border p-4"
            >
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="space-y-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="text-sm font-semibold">{invitation.vendor.name}</h3>
                    <RfqInvitationStatusBadge status={invitation.status} />
                  </div>
                  <p className="text-sm text-muted-foreground">{invitation.contactSummary}</p>
                  <p className="text-sm text-muted-foreground">
                    {invitation.responseDueAt ? `Response due ${formatDateTime(invitation.responseDueAt)}` : "No response due date recorded"}
                  </p>
                  <p className="text-sm text-muted-foreground">{invitation.activitySummary}</p>
                  {invitation.message ? <p className="text-sm text-muted-foreground">{invitation.message}</p> : null}
                  {invitation.cancelReason ? (
                    <p className="text-sm text-muted-foreground">Cancel reason: {invitation.cancelReason}</p>
                  ) : null}
                </div>

                <div className="flex flex-wrap gap-2">
                  {canInvite && invitation.permissions.canResend ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void resendMutation.mutateAsync(invitation.id)}
                      disabled={resendMutation.isPending}
                    >
                      Resend
                    </Button>
                  ) : null}

                  {canInvite && invitation.permissions.canUpdateStatus ? (
                    <>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void markInvitationStatus(invitation.id, "acknowledged")}
                        disabled={updateStatusMutation.isPending}
                      >
                        Mark acknowledged
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void markInvitationStatus(invitation.id, "declined")}
                        disabled={updateStatusMutation.isPending}
                      >
                        Mark declined
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => void markInvitationStatus(invitation.id, "expired")}
                        disabled={updateStatusMutation.isPending}
                      >
                        Mark expired
                      </Button>
                    </>
                  ) : null}

                  {canInvite && invitation.permissions.canCancel ? (
                    <Button variant="destructive" size="sm" onClick={() => openCancelDialog(invitation)}>
                      Cancel invitation
                    </Button>
                  ) : null}
                </div>
              </div>
            </article>
          ))
        ) : (
          <div className="rounded-md border border-dashed p-4 text-sm text-muted-foreground">
            No invitations recorded yet. Invite vendors to track response status here.
          </div>
        )}
      </div>

      <RfqInvitationDialog
        open={createOpen}
        title="Invite vendors to RFQ"
        description="Record the invitation against this RFQ. This does not send email or create vendor portal access."
        confirmLabel="Create invitations"
        confirmDisabled={selectedVendorIds.length === 0}
        confirmVariant="default"
        error={createError}
        isPending={createMutation.isPending}
        onOpenChange={(nextOpen) => {
          if (!nextOpen) {
            closeCreateDialog();
          } else {
            setCreateOpen(true);
          }
        }}
        onConfirm={submitCreateInvitations}
        footerNote={selectedVendorIds.length > 0 ? `${selectedVendorIds.length} vendor${selectedVendorIds.length === 1 ? "" : "s"} selected.` : "Select at least one vendor to continue."}
      >
        <VendorPicker
          search={createSearch}
          selectedVendorIds={selectedVendorIds}
          onSearchChange={(value) => setCreateSearch(value)}
          onToggleVendor={toggleVendor}
        />
      </RfqInvitationDialog>

      <RfqInvitationDialog
        open={Boolean(cancelTarget)}
        title="Cancel invitation"
        description={
          cancelTarget ? `Cancel the invitation recorded for ${cancelTarget.vendor.name}.` : "Cancel the selected invitation."
        }
        confirmLabel="Confirm cancel"
        confirmVariant="destructive"
        error={cancelError}
        isPending={cancelMutation.isPending}
        onOpenChange={(nextOpen) => {
          if (!nextOpen) {
            closeCancelDialog();
          }
        }}
        onConfirm={submitCancelInvitation}
      >
        <label className="block text-sm font-medium">
          Invitation cancel reason
          <Textarea
            className="mt-1"
            id="invitation-cancel-reason"
            value={cancelReason}
            onChange={(event) => {
              setCancelError(null);
              setCancelReason(event.target.value);
            }}
          />
        </label>
      </RfqInvitationDialog>
    </section>
  );
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

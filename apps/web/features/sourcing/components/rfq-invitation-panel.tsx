"use client";

import { useMemo, useRef, useState } from "react";
import { getApiErrorMessage } from "@cognify/api-client";
import { Button, Input, Textarea } from "@cognify/ui";
import { RfqInvitationDialog } from "./rfq-invitation-dialog";
import { QuotationEvidencePanel } from "./quotation-evidence-panel";
import { RfqInvitationStatusBadge } from "./rfq-invitation-status-badge";
import { VendorPicker } from "./vendor-picker";
import {
  useCancelRfqInvitation,
  useCreateRfqInvitations,
  useRegenerateRfqInvitationPortalLink,
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
  responseInstructions,
  responseDueAt,
}: {
  rfqId: string;
  canInvite: boolean;
  readOnlyReason?: string;
  responseInstructions?: string | null;
  responseDueAt?: string | null;
}) {
  const invitationQuery = useRfqInvitations(rfqId);
  const createMutation = useCreateRfqInvitations(rfqId);
  const resendMutation = useResendRfqInvitation(rfqId);
  const portalLinkMutation = useRegenerateRfqInvitationPortalLink(rfqId);
  const cancelMutation = useCancelRfqInvitation(rfqId);
  const updateStatusMutation = useUpdateRfqInvitationStatus(rfqId);

  const [createOpen, setCreateOpen] = useState(false);
  const [createSearch, setCreateSearch] = useState("");
  const [selectedVendorIds, setSelectedVendorIds] = useState<string[]>([]);
  const [createMessage, setCreateMessage] = useState("");
  const [createResponseDueAt, setCreateResponseDueAt] = useState("");
  const [createError, setCreateError] = useState<string | null>(null);

  const [cancelTarget, setCancelTarget] = useState<RfqInvitationViewModel | null>(null);
  const [cancelReason, setCancelReason] = useState("");
  const [cancelError, setCancelError] = useState<string | null>(null);
  const [invitationActionErrors, setInvitationActionErrors] = useState<Record<string, string>>({});
  const [portalLinkByInvitationId, setPortalLinkByInvitationId] = useState<Record<string, string>>({});
  const createTriggerRef = useRef<HTMLButtonElement | null>(null);
  const panelRef = useRef<HTMLElement | null>(null);

  const invitations = useMemo(
    () =>
      [...(invitationQuery.data ?? [])].sort(
        (left, right) => new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime(),
      ),
    [invitationQuery.data],
  );
  const invitationSummary = useMemo(() => buildInvitationSummary(invitations), [invitations]);

  const readOnlyMessage =
    readOnlyReason ?? (!canInvite ? "Vendor invitations are read-only for this RFQ." : null);

  function openCreateDialog() {
    setCreateError(null);
    setCreateSearch("");
    setSelectedVendorIds([]);
    setCreateMessage(responseInstructions ?? "");
    setCreateResponseDueAt(toDateTimeLocalValue(responseDueAt));
    setCreateOpen(true);
  }

  function closeCreateDialog() {
    setCreateOpen(false);
    setCreateError(null);
    setCreateSearch("");
    setSelectedVendorIds([]);
    setCreateMessage("");
    setCreateResponseDueAt("");
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
    const responseDueAtResult = parseResponseDueAt(createResponseDueAt);
    if (!responseDueAtResult.ok) {
      setCreateError(responseDueAtResult.error);
      return;
    }

    const parsed = rfqInvitationCreateSchema.safeParse({
      vendorIds: selectedVendorIds,
      message: emptyStringToNull(createMessage),
      responseDueAt: responseDueAtResult.value,
    });
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

  async function resendInvitation(invitationId: string) {
    setInvitationActionErrors((current) => {
      if (!current[invitationId]) return current;
      const next = { ...current };
      delete next[invitationId];
      return next;
    });

    try {
      await resendMutation.mutateAsync(invitationId);
    } catch (error) {
      setInvitationActionErrors((current) => ({
        ...current,
        [invitationId]: getApiErrorMessage(error),
      }));
    }
  }

  async function markInvitationStatus(invitationId: string, status: "acknowledged" | "declined" | "expired") {
    const parsed = rfqInvitationStatusSchema.safeParse({ status });
    if (!parsed.success) return;

    setInvitationActionErrors((current) => {
      if (!current[invitationId]) return current;
      const next = { ...current };
      delete next[invitationId];
      return next;
    });

    try {
      await updateStatusMutation.mutateAsync({
        invitationId,
        values: parsed.data,
      });
    } catch (error) {
      setInvitationActionErrors((current) => ({
        ...current,
        [invitationId]: getApiErrorMessage(error),
      }));
    }
  }

  async function generatePortalLink(invitationId: string) {
    setInvitationActionErrors((current) => {
      if (!current[invitationId]) return current;
      const next = { ...current };
      delete next[invitationId];
      return next;
    });

    try {
      const portalLink = await portalLinkMutation.mutateAsync(invitationId);
      const portalUrl = `${window.location.origin}${portalLink.portalUrl}`;

      if (!navigator.clipboard?.writeText) {
        throw new Error("Clipboard copy is unavailable. Copy the portal link manually from a supported browser.");
      }

      await navigator.clipboard.writeText(portalUrl);
      setPortalLinkByInvitationId((current) => ({ ...current, [invitationId]: portalLink.portalUrl }));
    } catch (error) {
      setInvitationActionErrors((current) => ({
        ...current,
        [invitationId]: error instanceof Error ? error.message : getApiErrorMessage(error),
      }));
    }
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
    <section id="vendor-invitations" ref={panelRef} tabIndex={-1} className="rounded-md border p-4">
      <div className="flex items-start justify-between gap-4">
        <div className="space-y-2">
          <div className="space-y-1">
            <h2 className="text-base font-semibold">Vendor invitations</h2>
            <p className="text-sm text-muted-foreground">
              Track invitation records for this RFQ. Generate portal links here for manual vendor sharing; email delivery is not enabled.
            </p>
          </div>
          <InvitationSummary summary={invitationSummary} />
          {readOnlyMessage ? <p className="text-sm text-amber-700">{readOnlyMessage}</p> : null}
        </div>

        {canInvite ? (
          <Button
            ref={createTriggerRef}
            onClick={openCreateDialog}
          >
            Invite vendors
          </Button>
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
                  <p className="text-sm text-muted-foreground">
                    {invitation.portalAccess.hasToken
                      ? `Portal access expires ${formatDateTime(invitation.portalAccess.expiresAt ?? invitation.responseDueAt ?? invitation.createdAt)}`
                      : "Portal access has not been generated."}
                  </p>
                  <p className="text-sm text-muted-foreground">{invitation.activitySummary}</p>
                  <QuotationEvidencePanel invitationId={invitation.id} invitationStatus={invitation.status} />
                  {invitation.message ? <p className="text-sm text-muted-foreground">{invitation.message}</p> : null}
                  {invitation.cancelReason ? (
                    <p className="text-sm text-muted-foreground">Cancel reason: {invitation.cancelReason}</p>
                  ) : null}
                  {portalLinkByInvitationId[invitation.id] ? (
                    <p className="text-sm text-green-700">
                      Portal link copied. Manual sharing only; email delivery is not enabled.
                    </p>
                  ) : null}
                  {invitationActionErrors[invitation.id] ? (
                    <p role="alert" className="text-sm text-red-700">
                      {invitationActionErrors[invitation.id]}
                    </p>
                  ) : null}
                </div>

                <div className="flex flex-wrap gap-2">
                  {canInvite && ["sent", "acknowledged"].includes(invitation.status) ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void generatePortalLink(invitation.id)}
                      disabled={portalLinkMutation.isPending}
                    >
                      Generate portal link
                    </Button>
                  ) : null}

                  {canInvite && invitation.permissions.canResend ? (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => void resendInvitation(invitation.id)}
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
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => openCancelDialog(invitation)}
                    >
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
        restoreFocusRef={createTriggerRef}
      >
        <div className="grid gap-4 lg:grid-cols-2">
          <label className="block text-sm font-medium lg:col-span-2">
            Buyer message / instructions
            <Textarea
              className="mt-1 min-h-28"
              value={createMessage}
              onChange={(event) => {
                setCreateError(null);
                setCreateMessage(event.target.value);
              }}
            />
          </label>
          <label className="block text-sm font-medium">
            Response due date
            <Input
              aria-label="Response due date"
              className="mt-1 h-11 w-full px-3 text-base font-normal"
              type="datetime-local"
              value={createResponseDueAt}
              onChange={(event) => {
                setCreateError(null);
                setCreateResponseDueAt(event.target.value);
              }}
            />
          </label>
        </div>
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
        restoreFocusRef={panelRef}
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

function InvitationSummary({ summary }: { summary: ReturnType<typeof buildInvitationSummary> }) {
  return (
    <div className="flex flex-wrap items-center gap-2 text-sm">
      <span className="font-medium text-foreground">
        {summary.total} invitation{summary.total === 1 ? "" : "s"} recorded
      </span>
      <span className="text-muted-foreground">{summary.statusSummary}</span>
    </div>
  );
}

function buildInvitationSummary(invitations: RfqInvitationViewModel[]) {
  const counts = invitations.reduce(
    (accumulator, invitation) => {
      accumulator[invitation.status] = (accumulator[invitation.status] ?? 0) + 1;
      return accumulator;
    },
    {} as Record<string, number>,
  );

  const statusSummaryParts = invitationStatusOrder
    .map((status) => counts[status])
    .flatMap((count, index) => {
      if (!count) return [];
      const status = invitationStatusOrder[index];
      return `${count} ${status}`;
    });

  return {
    total: invitations.length,
    statusSummary: statusSummaryParts.length > 0 ? statusSummaryParts.join(" · ") : "No statuses recorded yet.",
  };
}

function parseResponseDueAt(value: string): { ok: true; value: string | null } | { ok: false; error: string } {
  const trimmed = value.trim();
  if (!trimmed) return { ok: true, value: null };

  const parsed = new Date(trimmed);
  if (Number.isNaN(parsed.getTime())) return { ok: false, error: "Enter a valid response due date and time." };

  return { ok: true, value: parsed.toISOString() };
}

function emptyStringToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : null;
}

const invitationStatusOrder = ["pending", "sent", "acknowledged", "declined", "expired", "cancelled"] as const;

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

function toDateTimeLocalValue(value: string | null | undefined): string {
  if (!value) return "";

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";

  const offsetMs = date.getTimezoneOffset() * 60_000;
  const localDate = new Date(date.getTime() - offsetMs);
  return localDate.toISOString().slice(0, 16);
}

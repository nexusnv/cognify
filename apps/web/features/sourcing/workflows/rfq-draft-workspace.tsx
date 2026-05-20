"use client";

import Link from "next/link";
import { getApiErrorCode, getApiErrorMessage } from "@cognify/api-client";
import { RfqDraftForm } from "../components/rfq-draft-form";
import { RfqInvitationPanel } from "../components/rfq-invitation-panel";
import { RfqStatusBadge } from "../components/rfq-status-badge";
import { useCancelRfqDraft, useSaveRfqDraft } from "../hooks/use-rfq-draft-actions";
import { useRfqDraft } from "../hooks/use-rfq-draft";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";

export function RfqDraftWorkspace({ rfqId }: { rfqId: string }) {
  const rfqQuery = useRfqDraft(rfqId);
  const saveMutation = useSaveRfqDraft(rfqId);
  const cancelMutation = useCancelRfqDraft(rfqId);
  const rfq = rfqQuery.data;

  if (rfqQuery.isLoading) {
    return <div aria-label="Loading RFQ workspace" className="rounded-md border p-4 text-sm text-muted-foreground">Loading RFQ workspace</div>;
  }

  if (rfqQuery.isError || !rfq) {
    const code = getApiErrorCode(rfqQuery.error);
    const message =
      code === "forbidden"
        ? "You do not have access to this RFQ."
        : code === "not_found"
          ? "This RFQ could not be found."
          : getApiErrorMessage(rfqQuery.error);

    return <div role="alert" className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">{message}</div>;
  }

  const requisition = rfq.requisition;
  const intakeReview = rfq.intakeReview;
  const project = rfq.project;
  const sortedAuditSummary = [...rfq.auditSummary].sort(
    (left, right) => new Date(right.occurredAt).getTime() - new Date(left.occurredAt).getTime(),
  );

  return (
    <RecordWorkspaceLayout
      backHref="/sourcing/intake"
      backLabel="Back to sourcing intake"
      eyebrow={rfq.number}
      title={rfq.title}
      status={<RfqStatusBadge status={rfq.status} />}
      metadata={[
        {
          id: "requester",
          label: "Requester",
          value: requisition?.requester?.name ?? "No requisition linked",
        },
        {
          id: "needed-by",
          label: "Needed by",
          value: requisition?.neededByDate ?? "Not set",
        },
        {
          id: "project",
          label: "Project",
          value: project ? (
            <Link className="font-medium underline-offset-4 hover:underline" href={`/projects/${project.id}`}>
              {project.number} - {project.name}
            </Link>
          ) : (
            "No project"
          ),
        },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "line-items", label: "Line items" },
        { id: "documents", label: "Required documents" },
        { id: "notes", label: "Notes" },
        { id: "vendor-invitations", label: "Vendor invitations" },
      ]}
      primaryActions={
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <RfqStatusBadge status={rfq.status} size="compact" />
            <span className="text-sm font-medium">Draft controls</span>
          </div>
          <p className="text-sm text-muted-foreground">
            Edit the draft below, then save. Cancellation is terminal.
          </p>
          {rfq.status === "cancelled" ? (
            <p className="text-sm text-red-700">This RFQ is read-only because it was cancelled.</p>
          ) : null}
        </div>
      }
      sidebar={
        <>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Source requisition</h2>
            {requisition ? (
              <dl className="mt-3 grid gap-3 text-sm">
                <div>
                  <dt className="text-muted-foreground">Number</dt>
                  <dd className="font-medium">
                    <Link className="underline-offset-4 hover:underline" href={`/requisitions/${requisition.id}`}>
                      {requisition.number}
                    </Link>
                  </dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Title</dt>
                  <dd className="font-medium">{requisition.title}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Department</dt>
                  <dd>{requisition.department ?? "Not set"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Status</dt>
                  <dd>{requisition.status.replaceAll("_", " ")}</dd>
                </div>
              </dl>
            ) : (
              <p className="mt-2 text-sm text-muted-foreground">This RFQ is not linked to a requisition.</p>
            )}
          </section>

          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Source intake</h2>
            {intakeReview ? (
              <dl className="mt-3 grid gap-3 text-sm">
                <div>
                  <dt className="text-muted-foreground">Review</dt>
                  <dd className="font-medium">
                    <Link className="underline-offset-4 hover:underline" href={`/sourcing/intake/${intakeReview.id}`}>
                      {intakeReview.id}
                    </Link>
                  </dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Status</dt>
                  <dd>{intakeReview.status.replaceAll("_", " ")}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Buyer</dt>
                  <dd>{intakeReview.assignedBuyer?.name ?? "Unassigned"}</dd>
                </div>
                <div>
                  <dt className="text-muted-foreground">Decision</dt>
                  <dd>{intakeReview.decisionReason ?? "No decision reason recorded"}</dd>
                </div>
              </dl>
            ) : (
              <p className="mt-2 text-sm text-muted-foreground">This RFQ is not linked to an intake review.</p>
            )}
          </section>

          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Activity summary</h2>
            {sortedAuditSummary.length > 0 ? (
              <div className="mt-3 space-y-3 text-sm">
                {sortedAuditSummary.slice(0, 4).map((entry) => (
                  <div key={`${entry.eventType}-${entry.occurredAt}`} className="space-y-1 border-b pb-2 last:border-b-0 last:pb-0">
                    <p className="font-medium">{entry.action}</p>
                    <p className="text-xs text-muted-foreground">
                      {entry.eventType} - {formatDateTime(entry.occurredAt)}
                    </p>
                  </div>
                ))}
              </div>
            ) : (
              <p className="mt-2 text-sm text-muted-foreground">No audit events have been recorded yet.</p>
            )}
          </section>
        </>
      }
    >
      <RfqDraftForm
        key={`${rfq.id}-${rfq.updatedAt}`}
        rfq={rfq}
        canUpdate={rfq.permissions.canUpdate}
        canCancel={rfq.permissions.canCancel}
        isSaving={saveMutation.isPending}
        isCancelling={cancelMutation.isPending}
        onSave={async (values) => {
          await saveMutation.mutateAsync(values);
        }}
        onCancel={async (values) => {
          await cancelMutation.mutateAsync(values);
        }}
      />

      <RfqInvitationPanel
        rfqId={rfq.id}
        canInvite={rfq.status === "draft" && rfq.permissions.canInviteVendors}
        responseInstructions={rfq.responseInstructions}
        responseDueAt={rfq.responseDueAt}
        readOnlyReason={
          rfq.status === "cancelled"
            ? "Vendor invitations are read-only because this RFQ is cancelled."
            : rfq.status !== "draft"
              ? "Vendor invitations are available only while the RFQ is a draft."
              : rfq.permissions.canInviteVendors
                ? undefined
                : "You do not have permission to invite vendors."
        }
      />
    </RecordWorkspaceLayout>
  );
}

function formatDateTime(value: string) {
  return new Intl.DateTimeFormat(undefined, {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}

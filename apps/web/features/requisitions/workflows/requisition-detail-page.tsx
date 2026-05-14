"use client";

import Link from "next/link";
import { useEffect } from "react";
import { Pencil, Send } from "lucide-react";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { AttachmentList } from "@/features/attachments/components/attachment-list";
import { AttachmentUploader } from "@/features/attachments/components/attachment-uploader";
import { rememberRecentRecord } from "@/features/search/hooks/use-recent-records";
import { RequisitionActivityTimeline } from "../components/requisition-activity-timeline";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useRequisition, useRequisitionActivity } from "../hooks/use-requisition";
import { formatMoney } from "../utils/requisition-totals";

export function RequisitionDetailPage({ requisitionId }: { requisitionId: string }) {
  const requisitionQuery = useRequisition(requisitionId);
  const activityQuery = useRequisitionActivity(requisitionId);
  const requisition = requisitionQuery.data;

  useEffect(() => {
    if (!requisition) return;

    rememberRecentRecord({
      type: "requisition",
      id: requisition.id,
      title: requisition.title,
      subtitle: requisition.number,
      status: requisition.status,
      href: `/requisitions/${requisition.id}`,
      updatedAt: requisition.updatedAt,
    });
  }, [requisition]);

  if (requisitionQuery.isLoading) {
    return (
      <div className="rounded-md border p-4 text-sm text-muted-foreground">
        Loading requisition workspace
      </div>
    );
  }

  if (requisitionQuery.isError || !requisition) {
    return (
      <div className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">
        Requisition could not be loaded.
      </div>
    );
  }

  const actions = (
    <>
      {requisition.permissions.canSubmit ? (
        <Link
          href={`/requisitions/${requisition.id}/edit`}
          className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md bg-foreground px-3 text-sm font-medium text-background"
        >
          <Send className="h-4 w-4" aria-hidden="true" />
          Review and submit
        </Link>
      ) : null}
      {requisition.permissions.canUpdate ? (
        <Link
          href={`/requisitions/${requisition.id}/edit`}
          className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"
        >
          <Pencil className="h-4 w-4" aria-hidden="true" />
          Edit draft
        </Link>
      ) : null}
      {!requisition.permissions.canSubmit && !requisition.permissions.canUpdate ? (
        <p className="text-sm text-muted-foreground">
          Requester editing is locked after submission.
        </p>
      ) : null}
    </>
  );

  return (
    <RecordWorkspaceLayout
      backHref="/requisitions"
      backLabel="Back to requisitions"
      eyebrow={requisition.number}
      title={requisition.title}
      status={<RequisitionStatusBadge status={requisition.status} />}
      metadata={[
        {
          id: "estimated-total",
          label: "Estimated total",
          value: (
            <span className="font-mono tabular-nums">
              {formatMoney(requisition.estimatedTotal, requisition.currency)}
            </span>
          ),
        },
        { id: "needed-by", label: "Needed by", value: requisition.neededByDate },
        { id: "requester", label: "Requester", value: requisition.requester.name },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "line-items", label: "Line items" },
        { id: "evidence", label: "Evidence" },
        { id: "activity", label: "Activity" },
      ]}
      primaryActions={actions}
      sidebar={
        <>
          <SubmissionChecklist
            values={{
              title: requisition.title,
              businessJustification: requisition.businessJustification,
              neededByDate: requisition.neededByDate,
              department: requisition.department ?? "",
              costCenter: requisition.costCenter ?? "",
              deliveryLocation: requisition.deliveryLocation ?? "",
              currency: requisition.currency,
              lineItems: requisition.lineItems,
            }}
          />
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Approval readiness</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Approval routing will attach here after the submitted workflow is active.
            </p>
          </section>
          <section className="rounded-md border p-4">
            <h2 className="text-base font-semibold">Evidence</h2>
            <p className="mt-2 text-sm text-muted-foreground">
              Upload supporting files directly in the requisition workspace.
            </p>
            <a
              href="#evidence"
              className="mt-3 inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium"
            >
              Jump to evidence
            </a>
          </section>
        </>
      }
    >
      <section id="overview" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Overview</h2>
        <p className="mt-2 text-sm leading-6">{requisition.businessJustification}</p>
      </section>

      <section id="line-items" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Line items</h2>
        <div className="mt-3 space-y-2">
          {requisition.lineItems.map((item, index) => (
            <div
              key={item.id ?? `${item.name}-${index}`}
              className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_7rem_8rem]"
            >
              <span className="font-medium">{item.name}</span>
              <span className="tabular-nums">
                {item.quantity} {item.unit}
              </span>
              <span className="font-mono tabular-nums">
                {formatMoney(
                  item.estimatedLineTotal ?? item.quantity * item.estimatedUnitPrice,
                  item.currency,
                )}
              </span>
            </div>
          ))}
        </div>
      </section>

      <section id="evidence" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Evidence</h2>
        <div className="mt-3 space-y-3">
          <AttachmentUploader requisitionId={requisition.id} />
          <AttachmentList requisitionId={requisition.id} />
        </div>
      </section>

      <section id="activity" className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Activity</h2>
        <div className="mt-3">
          <RequisitionActivityTimeline events={activityQuery.data?.data ?? []} />
        </div>
      </section>
    </RecordWorkspaceLayout>
  );
}

"use client";

import Link from "next/link";
import { useEffect } from "react";
import { Pencil, Send } from "lucide-react";
import { toast } from "sonner";
import { Alert, AlertDescription, AlertTitle, Button, Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";
import { RecordWorkspaceLayout } from "@/components/workspace/record-workspace-layout";
import { AttachmentList } from "@/features/attachments/components/attachment-list";
import { AttachmentUploader } from "@/features/attachments/components/attachment-uploader";
import { rememberRecentRecord } from "@/features/search/hooks/use-recent-records";
import { RequisitionActionDialog } from "../components/requisition-action-dialog";
import { RequisitionActivityTimeline } from "../components/requisition-activity-timeline";
import { RequisitionComments } from "../components/requisition-comments";
import { RequisitionCorrectionPanel } from "../components/requisition-correction-panel";
import { RequisitionApprovalSummary } from "../components/requisition-approval-summary";
import { RequisitionStatusBadge } from "../components/requisition-status-badge";
import { SubmissionChecklist } from "../components/submission-checklist";
import { useRequisition, useRequisitionActivity } from "../hooks/use-requisition";
import {
  useCancelRequisition,
  useRequestRequisitionChanges,
  useResubmitRequisition,
  useWithdrawRequisition,
} from "../hooks/use-requisition-actions";
import { formatMoney } from "../utils/requisition-totals";

export function RequisitionDetailPage({ requisitionId }: { requisitionId: string }) {
  const requisitionQuery = useRequisition(requisitionId);
  const activityQuery = useRequisitionActivity(requisitionId);
  const requisition = requisitionQuery.data;
  const requestChangesMutation = useRequestRequisitionChanges(requisitionId);
  const resubmitMutation = useResubmitRequisition(requisitionId);
  const withdrawMutation = useWithdrawRequisition(requisitionId);
  const cancelMutation = useCancelRequisition(requisitionId);

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
      <Card><CardContent className="pt-6 text-sm text-muted-foreground">Loading requisition workspace</CardContent></Card>
    );
  }

  if (requisitionQuery.isError || !requisition) {
    return (
      <Alert variant="destructive"><AlertTitle>Requisition</AlertTitle><AlertDescription>Requisition could not be loaded.</AlertDescription></Alert>
    );
  }

  const hasPendingWorkflowAction = Boolean(
    requisition.permissions.canResubmit ||
    requisition.permissions.canRequestChanges ||
    requisition.permissions.canWithdraw ||
    requisition.permissions.canCancel,
  );

  const actions = (
    <>
      {requisition.permissions.canSubmit ? (
        <Button asChild className="min-h-11 w-full">
          <Link href={`/requisitions/${requisition.id}/edit`}>
            <Send className="h-4 w-4" aria-hidden="true" />
            Review and submit
          </Link>
        </Button>
      ) : null}
      {requisition.permissions.canUpdate ? (
        <Button asChild variant="outline" className="min-h-11 w-full">
          <Link href={`/requisitions/${requisition.id}/edit`}>
            <Pencil className="h-4 w-4" aria-hidden="true" />
            Edit draft
          </Link>
        </Button>
      ) : null}
      {requisition.permissions.canResubmit ? (
        <Button
          type="button"
          onClick={() =>
            resubmitMutation.mutate(undefined, {
              onSuccess: () => toast.success("Requisition resubmitted"),
              onError: () => toast.error("Unable to resubmit the requisition."),
            })
          }
          disabled={resubmitMutation.isPending}
        >
          {resubmitMutation.isPending ? "Resubmitting" : "Resubmit"}
        </Button>
      ) : null}
      {requisition.permissions.canRequestChanges ? (
        <RequisitionActionDialog
          action="request-changes"
          title="Request changes?"
          description="Explain what the requester should update before this requisition can move forward."
          confirmLabel="Confirm request changes"
          triggerLabel="Request changes"
          requireRequestedFields
          isPending={requestChangesMutation.isPending}
          onSubmit={async ({ reason, requestedFields }) => {
            await requestChangesMutation.mutateAsync(
              { reason, requestedFields },
              {
                onSuccess: () => toast.success("Changes requested"),
                onError: () => toast.error("Unable to request changes."),
              },
            );
          }}
        />
      ) : null}
      {requisition.permissions.canWithdraw ? (
        <RequisitionActionDialog
          action="withdraw"
          title="Withdraw requisition?"
          description="This stops the requisition and keeps the record available for audit history."
          confirmLabel="Confirm withdrawal"
          triggerLabel="Withdraw"
          isPending={withdrawMutation.isPending}
          onSubmit={async ({ reason }) => {
            await withdrawMutation.mutateAsync(
              { reason },
              {
                onSuccess: () => toast.success("Requisition withdrawn"),
                onError: () => toast.error("Unable to withdraw the requisition."),
              },
            );
          }}
        />
      ) : null}
      {requisition.permissions.canCancel ? (
        <RequisitionActionDialog
          action="cancel"
          title="Cancel requisition?"
          description="Use cancellation only when the requisition should be stopped by an administrator."
          confirmLabel="Confirm cancellation"
          triggerLabel="Cancel"
          triggerVariant="destructive"
          isPending={cancelMutation.isPending}
          onSubmit={async ({ reason }) => {
            await cancelMutation.mutateAsync(
              { reason },
              {
                onSuccess: () => toast.success("Requisition cancelled"),
                onError: () => toast.error("Unable to cancel the requisition."),
              },
            );
          }}
        />
      ) : null}
      {!requisition.permissions.canSubmit &&
      !requisition.permissions.canUpdate &&
      !hasPendingWorkflowAction ? (
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
              {formatMoney(requisition.estimatedTotal, requisition.currency ?? "MYR")}
            </span>
          ),
        },
        { id: "needed-by", label: "Needed by", value: requisition.neededByDate },
        {
          id: "project",
          label: "Project",
          value: requisition.projectSummary ? (
            <Link
              href={`/projects/${requisition.projectSummary.id}`}
              className="font-medium underline-offset-4 hover:underline"
            >
              {requisition.projectSummary.number} - {requisition.projectSummary.name}
            </Link>
          ) : (
            "No project"
          ),
        },
        { id: "requester", label: "Requester", value: requisition.requester.name },
      ]}
      sections={[
        { id: "overview", label: "Overview" },
        { id: "line-items", label: "Line items" },
        { id: "evidence", label: "Evidence" },
        { id: "comments", label: "Comments" },
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
              projectId: requisition.projectId ?? "",
              costCenter: requisition.costCenter ?? "",
              deliveryLocation: requisition.deliveryLocation ?? "",
              currency: requisition.currency ?? "MYR",
              lineItems: requisition.lineItems,
            }}
          />
          <RequisitionApprovalSummary requisitionId={requisition.id} />
          <Card>
            <CardHeader><CardTitle>Quotation readiness</CardTitle></CardHeader>
            <CardContent className="text-sm text-muted-foreground">
              Buyer intake, sourcing packages, and quotation comparisons are deferred to later
              workflow slices.
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>Evidence</CardTitle></CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">
              Upload supporting files directly in the requisition workspace.
              </p>
              <Button asChild variant="outline" className="mt-3">
                <Link href="#evidence">Jump to evidence</Link>
              </Button>
            </CardContent>
          </Card>
        </>
      }
    >
      <RequisitionCorrectionPanel requisition={requisition} />

      <Card id="overview">
        <CardHeader><CardTitle>Overview</CardTitle></CardHeader>
        <CardContent><p className="text-sm leading-6">{requisition.businessJustification}</p></CardContent>
      </Card>

      <Card id="line-items">
        <CardHeader><CardTitle>Line items</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {requisition.lineItems.map((item, index) => (
            <Card key={item.id ?? `${item.name}-${index}`}>
              <CardContent className="grid gap-2 pt-4 text-sm sm:grid-cols-[minmax(0,1fr)_7rem_8rem]">
              <span className="font-medium">{item.name}</span>
              <span className="tabular-nums">
                {item.quantity} {item.unit}
              </span>
              <span className="font-mono tabular-nums">
                {formatMoney(
                  item.estimatedLineTotal ?? item.quantity * item.estimatedUnitPrice,
                  item.currency ?? requisition.currency ?? "MYR",
                )}
              </span>
              </CardContent>
            </Card>
          ))}
        </CardContent>
      </Card>

      <Card id="evidence">
        <CardHeader><CardTitle>Evidence</CardTitle></CardHeader>
        <CardContent className="space-y-3">
          <AttachmentUploader requisitionId={requisition.id} />
          <AttachmentList requisitionId={requisition.id} />
        </CardContent>
      </Card>

      <Card id="comments">
        <CardHeader><CardTitle>Comments</CardTitle></CardHeader>
        <CardContent>
          <RequisitionComments
            requisitionId={requisition.id}
            canComment={requisition.permissions.canComment}
            canMention={requisition.permissions.canMention}
          />
        </CardContent>
      </Card>

      <Card id="activity">
        <CardHeader><CardTitle>Activity</CardTitle></CardHeader>
        <CardContent>
          <RequisitionActivityTimeline events={activityQuery.data?.data ?? []} />
        </CardContent>
      </Card>
    </RecordWorkspaceLayout>
  );
}

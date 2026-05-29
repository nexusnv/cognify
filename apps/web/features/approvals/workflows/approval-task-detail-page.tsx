"use client";

import Link from "next/link";
import { toast } from "sonner";
import { Alert, AlertDescription, Button, Card, CardContent } from "@cognify/ui";
import { PageHeader } from "@/components/ui/page-header";
import type {
  ApprovalAwardRecommendationSubjectMetadata,
  ApprovalRequisitionSubjectMetadata,
} from "@cognify/api-client/schemas";
import { ApprovalActionDialog } from "../components/approval-action-dialog";
import { ApprovalDelegationDialog } from "../components/approval-delegation-dialog";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { ApprovalTaskComments } from "../components/approval-task-comments";
import { useApprovalTaskActions } from "../hooks/use-approval-task-actions";
import { useApprovalTask } from "../hooks/use-approval-tasks";

export function ApprovalTaskDetailPage({ taskId }: { taskId: string }) {
  const taskQuery = useApprovalTask(taskId);
  const actions = useApprovalTaskActions(taskId);
  const task = taskQuery.data;

  if (taskQuery.isLoading) {
    return (
      <Card>
        <CardContent className="p-4 text-sm text-muted-foreground">Loading approval task</CardContent>
      </Card>
    );
  }

  if (taskQuery.isError || !task) {
    return (
      <Alert variant="destructive">
        <AlertDescription>Approval task could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  const isAwardRecommendation = task.subject.type === "rfq_award_recommendation";
  const awardMetadata = task.subject.metadata as ApprovalAwardRecommendationSubjectMetadata;
  const requisitionMetadata = task.subject.metadata as ApprovalRequisitionSubjectMetadata;
  const canApprove = task.permissions.canApprove;
  const canReject = task.permissions.canReject;
  const canRequestChanges = task.permissions.canRequestChanges;
  const hasDecisionAction = canApprove || canReject || canRequestChanges;

  return (
    <div className="space-y-6">
      <PageHeader
        eyebrow={task.subject.number}
        title={task.subject.title}
        actions={
          <>
            <ApprovalStatusBadge status={task.status} />
            <Button asChild variant="outline">
              <Link href="/approvals">Back to approvals</Link>
            </Button>
          </>
        }
      />

      <Card>
        <CardContent className="grid gap-3 p-4 text-sm md:grid-cols-3">
          <Metric label="Stage" value={task.stage.name ?? "Current stage"} />
          <Metric label="Assignee" value={task.assignee?.name ?? "Unassigned"} />
          <Metric label="Due" value={formatDate(task.dueAt)} />
          {task.originalAssignee && task.originalAssignee.id !== task.assignee?.id ? (
            <Metric label="Delegated from" value={task.originalAssignee.name} />
          ) : null}
          {isAwardRecommendation ? (
            <>
              <Metric label="Recommended vendor" value={awardMetadata.recommendedVendorName ?? task.subject.primaryParty ?? "Unknown"} />
              <Metric label="RFQ" value={awardMetadata.rfqNumber ?? task.subject.number ?? "Unknown RFQ"} />
              <Metric label="Weighted score" value={formatNumber(awardMetadata.scorecardWeightedTotal)} />
            </>
          ) : (
            <>
              <Metric label="Requester" value={requisitionMetadata.requester?.name ?? task.subject.primaryParty ?? "Unknown"} />
              <Metric label="Department" value={requisitionMetadata.department ?? "Unassigned"} />
              <Metric label="Cost center" value={requisitionMetadata.costCenter ?? "Unassigned"} />
            </>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-4">
          <h2 className="text-base font-semibold">Decision</h2>
          {task.status === "active" && hasDecisionAction ? (
            <div className="mt-4 flex flex-wrap gap-2">
            {canApprove ? (
              <ApprovalActionDialog
                action="approve"
                triggerLabel="Approve"
                title="Approve task?"
                confirmLabel="Confirm approval"
                lockVersion={task.lockVersion}
                isPending={actions.approve.isPending}
                onSubmit={async ({ lockVersion }) => {
                  await actions.approve.mutateAsync(
                    { lockVersion },
                    { onSuccess: () => toast.success("Approval recorded") },
                  );
                }}
              />
            ) : null}
            {canReject ? (
              <ApprovalActionDialog
                action="reject"
                triggerLabel="Reject"
                title="Reject task?"
                confirmLabel="Confirm rejection"
                lockVersion={task.lockVersion}
                isPending={actions.reject.isPending}
                onSubmit={async ({ lockVersion, reason }) => {
                  await actions.reject.mutateAsync(
                    { lockVersion, reason: reason ?? "" },
                    { onSuccess: () => toast.success(rejectionSuccessMessage(task.subject.type)) },
                  );
                }}
              />
            ) : null}
            {canRequestChanges ? (
              <ApprovalActionDialog
                action="request-changes"
                triggerLabel="Request changes"
                title="Request changes?"
                confirmLabel="Confirm request changes"
                lockVersion={task.lockVersion}
                isPending={actions.requestChanges.isPending}
                onSubmit={async ({ lockVersion, reason, requestedFields }) => {
                  await actions.requestChanges.mutateAsync(
                    { lockVersion, reason: reason ?? "", requestedFields },
                    { onSuccess: () => toast.success("Changes requested") },
                  );
                }}
              />
            ) : null}
            <ApprovalDelegationDialog taskId={task.id} lockVersion={task.lockVersion} />
          </div>
          ) : (
            <p className="mt-2 text-sm text-muted-foreground">
              {task.decision ? `Decision recorded: ${task.decision.replaceAll("_", " ")}` : "No decision recorded."}
            </p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-4">
          <h2 className="text-base font-semibold">{isAwardRecommendation ? "Award recommendation" : "Requisition"}</h2>
          <p className="mt-2 text-sm text-muted-foreground">
            {task.subject.title} is currently {task.subject.status?.replaceAll("_", " ")}.
          </p>
          {isAwardRecommendation ? (
            <div className="mt-4 grid gap-3 text-sm md:grid-cols-2">
              <Metric label="Rationale" value={awardMetadata.rationale ?? "No rationale provided"} />
              <Metric label="Tradeoff summary" value={awardMetadata.tradeoffSummary ?? "Not provided"} />
              <Metric label="Risk summary" value={awardMetadata.riskSummary ?? "Not provided"} />
              <Metric label="Exception summary" value={awardMetadata.exceptionSummary ?? "Not provided"} />
            </div>
          ) : null}
          <Button asChild variant="outline" className="mt-3">
            <Link
              href={isAwardRecommendation ? awardRecommendationHref(task, awardMetadata) : `/requisitions/${task.subject.id}`}
            >
              {isAwardRecommendation ? "Open award recommendation" : "Open requisition"}
            </Link>
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-4">
          <h2 className="text-base font-semibold">Comments</h2>
          <div className="mt-4">
          <ApprovalTaskComments taskId={task.id} />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-xs uppercase text-muted-foreground">{label}</dt>
      <dd className="mt-1 font-medium">{value}</dd>
    </div>
  );
}

function formatDate(value?: string | null) {
  if (!value) return "No due date";
  return new Intl.DateTimeFormat("en", { month: "short", day: "numeric", year: "numeric" }).format(
    new Date(value),
  );
}

function formatNumber(value?: number | null) {
  if (typeof value !== "number") return "Not scored";
  return new Intl.NumberFormat("en", { maximumFractionDigits: 2 }).format(value);
}

function rejectionSuccessMessage(subjectType: string) {
  if (subjectType === "rfq_award_recommendation") return "Award recommendation rejected";
  if (subjectType === "requisition") return "Requisition rejected";
  return "Approval rejected";
}

function awardRecommendationHref(
  task: { subject: { href?: string | null } },
  metadata: Pick<ApprovalAwardRecommendationSubjectMetadata, "rfqId">,
) {
  if (task.subject.href) return task.subject.href;
  if (metadata.rfqId) return `/quotations/awards/${metadata.rfqId}`;

  return "/quotations";
}

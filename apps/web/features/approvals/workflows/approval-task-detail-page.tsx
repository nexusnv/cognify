"use client";

import Link from "next/link";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Skeleton,
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@cognify/ui";
import { toast } from "sonner";
import type {
  ApprovalAwardRecommendationSubjectMetadata,
  ApprovalPurchaseOrderSubjectMetadata,
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
      <div className="space-y-4">
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-48 w-full" />
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  if (taskQuery.isError || !task) {
    return (
      <Alert variant="destructive">
        <AlertTitle>Approval task unavailable</AlertTitle>
        <AlertDescription>Approval task could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  const isAwardRecommendation = task.subject.type === "rfq_award_recommendation";
  const isPurchaseOrder = task.subject.type === "purchase_order";
  const awardMetadata = task.subject.metadata as ApprovalAwardRecommendationSubjectMetadata;
  const purchaseOrderMetadata = task.subject.metadata as ApprovalPurchaseOrderSubjectMetadata;
  const requisitionMetadata = task.subject.metadata as ApprovalRequisitionSubjectMetadata;
  const canApprove = task.permissions.canApprove;
  const canReject = task.permissions.canReject;
  const canRequestChanges = task.permissions.canRequestChanges;
  const hasDecisionAction = canApprove || canReject || canRequestChanges;

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="gap-3">
          <div className="flex items-center justify-between gap-3">
            <Button variant="ghost" asChild className="px-0">
              <Link href="/approvals">Back to approvals</Link>
            </Button>
            <ApprovalStatusBadge status={task.status} />
          </div>
          <div className="space-y-2">
            <Badge variant="outline" className="font-mono text-[11px]">
              {task.subject.number}
            </Badge>
            <CardTitle>
              <h1 className="text-2xl font-semibold tracking-normal">{task.subject.title}</h1>
            </CardTitle>
            <CardDescription>
              {task.title} in {task.stage.name ?? "Current stage"}
            </CardDescription>
          </div>
        </CardHeader>
      </Card>

      <Tabs defaultValue="summary">
        <TabsList aria-label="Approval task sections">
          <TabsTrigger value="summary" className="min-h-11 px-3">Summary</TabsTrigger>
          <TabsTrigger value="decision" className="min-h-11 px-3">Decision</TabsTrigger>
          <TabsTrigger value="comments" className="min-h-11 px-3">Comments</TabsTrigger>
        </TabsList>

        <TabsContent value="summary" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>
                <h2>Task context</h2>
              </CardTitle>
              <CardDescription>Current assignee, stage timing, and subject metadata.</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 text-sm md:grid-cols-3">
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
              ) : isPurchaseOrder ? (
                <>
                  <Metric label="Vendor" value={purchaseOrderMetadata.vendorName ?? task.subject.primaryParty ?? "Unknown"} />
                  <Metric label="RFQ" value={purchaseOrderMetadata.rfqNumber ?? "Not sourced from RFQ"} />
                  <Metric label="Payment terms" value={purchaseOrderMetadata.paymentTerms ?? "Not recorded"} />
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
            <CardHeader>
              <CardTitle>
                <h2>{subjectSectionTitle(task.subject.type)}</h2>
              </CardTitle>
              <CardDescription>
                {task.subject.title} is currently {task.subject.status?.replaceAll("_", " ")}.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {isAwardRecommendation ? (
                <div className="grid gap-3 text-sm md:grid-cols-2">
                  <Metric label="Rationale" value={awardMetadata.rationale ?? "No rationale provided"} />
                  <Metric label="Tradeoff summary" value={awardMetadata.tradeoffSummary ?? "Not provided"} />
                  <Metric label="Risk summary" value={awardMetadata.riskSummary ?? "Not provided"} />
                  <Metric label="Exception summary" value={awardMetadata.exceptionSummary ?? "Not provided"} />
                </div>
              ) : isPurchaseOrder ? (
                <div className="grid gap-3 text-sm md:grid-cols-2">
                  <Metric label="Vendor" value={purchaseOrderMetadata.vendorName ?? task.subject.primaryParty ?? "Unknown"} />
                  <Metric label="Payment terms" value={purchaseOrderMetadata.paymentTerms ?? "Not recorded"} />
                  <Metric label="Delivery terms" value={purchaseOrderMetadata.deliveryTerms ?? "Not recorded"} />
                  <Metric label="Amount" value={formatMoney(task.subject.amount, task.subject.currency)} />
                </div>
              ) : null}
              <Button asChild variant="outline">
                <Link href={subjectHref(task, awardMetadata)}>
                  {subjectLinkLabel(task.subject.type)}
                </Link>
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="decision" className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>
                <h2>Decision</h2>
              </CardTitle>
              <CardDescription>Approve, reject, request changes, or delegate the task.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              {task.decision ? (
                <Alert>
                  <AlertTitle>Decision recorded</AlertTitle>
                  <AlertDescription>{task.decision.replaceAll("_", " ")}</AlertDescription>
                </Alert>
              ) : null}
              {task.status === "active" && hasDecisionAction ? (
                <div className="flex flex-wrap gap-2">
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
                <Alert>
                  <AlertDescription>
                    {task.decision ? `Decision recorded: ${task.decision.replaceAll("_", " ")}` : "No decision recorded."}
                  </AlertDescription>
                </Alert>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="comments">
          <Card>
            <CardHeader>
              <CardTitle>
                <h2>Comments</h2>
              </CardTitle>
              <CardDescription>Capture decision context and follow-up requests.</CardDescription>
            </CardHeader>
            <CardContent>
              <ApprovalTaskComments taskId={task.id} />
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-lg border p-3">
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

function formatMoney(amount?: number | null, currency?: string | null) {
  if (typeof amount !== "number") return "Not recorded";
  return new Intl.NumberFormat("en", { style: "currency", currency: currency ?? "MYR" }).format(amount);
}

function subjectSectionTitle(subjectType: string) {
  if (subjectType === "rfq_award_recommendation") return "Award recommendation";
  if (subjectType === "purchase_order") return "Purchase order";
  return "Requisition";
}

function subjectLinkLabel(subjectType: string) {
  if (subjectType === "rfq_award_recommendation") return "Open award recommendation";
  if (subjectType === "purchase_order") return "Open purchase order";
  return "Open requisition";
}

function subjectHref(
  task: { subject: { type: string; href?: string | null; id: string } },
  awardMetadata: Pick<ApprovalAwardRecommendationSubjectMetadata, "rfqId">,
) {
  if (task.subject.href) return task.subject.href;
  if (task.subject.type === "rfq_award_recommendation") return awardRecommendationHref(task, awardMetadata);
  if (task.subject.type === "purchase_order") return `/purchase-orders/${task.subject.id}`;
  return `/requisitions/${task.subject.id}`;
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

"use client";

import Link from "next/link";
import { toast } from "sonner";
import { ApprovalActionDialog } from "../components/approval-action-dialog";
import { ApprovalDelegationDialog } from "../components/approval-delegation-dialog";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import { useApprovalTaskActions } from "../hooks/use-approval-task-actions";
import { useApprovalTask } from "../hooks/use-approval-tasks";

export function ApprovalTaskDetailPage({ taskId }: { taskId: string }) {
  const taskQuery = useApprovalTask(taskId);
  const actions = useApprovalTaskActions(taskId);
  const task = taskQuery.data;

  if (taskQuery.isLoading) {
    return <p className="rounded-md border p-4 text-sm text-muted-foreground">Loading approval task</p>;
  }

  if (taskQuery.isError || !task) {
    return <p className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Approval task could not be loaded.</p>;
  }

  return (
    <div className="space-y-6">
      <header className="space-y-3">
        <Link href="/approvals" className="text-sm font-medium underline-offset-4 hover:underline">
          Back to approvals
        </Link>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p className="font-mono text-xs text-muted-foreground">{task.subject.number}</p>
            <h1 className="text-2xl font-semibold tracking-normal">{task.subject.title}</h1>
          </div>
          <ApprovalStatusBadge status={task.status} />
        </div>
      </header>

      <section className="grid gap-3 rounded-md border p-4 text-sm md:grid-cols-3">
        <Metric label="Stage" value={task.stage.name ?? "Current stage"} />
        <Metric label="Assignee" value={task.assignee?.name ?? "Unassigned"} />
        <Metric label="Due" value={formatDate(task.dueAt)} />
        {task.originalAssignee && task.originalAssignee.id !== task.assignee?.id ? (
          <Metric label="Delegated from" value={task.originalAssignee.name} />
        ) : null}
        <Metric label="Requester" value={task.subject.requester?.name ?? "Unknown"} />
        <Metric label="Department" value={task.subject.department ?? "Unassigned"} />
        <Metric label="Cost center" value={task.subject.costCenter ?? "Unassigned"} />
      </section>

      <section className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Decision</h2>
        {task.status === "active" ? (
          <div className="mt-4 flex flex-wrap gap-2">
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
                  { onSuccess: () => toast.success("Requisition rejected") },
                );
              }}
            />
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
            <ApprovalDelegationDialog taskId={task.id} lockVersion={task.lockVersion} />
          </div>
        ) : (
          <p className="mt-2 text-sm text-muted-foreground">
            {task.decision ? `Decision recorded: ${task.decision.replaceAll("_", " ")}` : "No decision recorded."}
          </p>
        )}
      </section>

      <section className="rounded-md border p-4">
        <h2 className="text-base font-semibold">Requisition</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          {task.subject.title} is currently {task.subject.status?.replaceAll("_", " ")}.
        </p>
        <Link
          href={`/requisitions/${task.subject.id}`}
          className="mt-3 inline-flex min-h-10 items-center rounded-md border px-3 text-sm font-medium"
        >
          Open requisition
        </Link>
      </section>
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

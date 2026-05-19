"use client";

import Link from "next/link";
import { ExternalLink } from "lucide-react";
import { ApprovalStatusBadge } from "../components/approval-status-badge";
import type { ApprovalTask } from "../types/approval-view-model";

export function ApprovalTasksTable({
  tasks,
  state = "idle",
}: {
  tasks: ApprovalTask[];
  state?: "idle" | "loading" | "error" | "empty";
}) {
  if (state === "loading") {
    return <p className="rounded-md border p-4 text-sm text-muted-foreground">Loading approvals</p>;
  }

  if (state === "error") {
    return <p className="rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900">Approval tasks could not be loaded.</p>;
  }

  if (state === "empty" || tasks.length === 0) {
    return <p className="rounded-md border p-4 text-sm text-muted-foreground">No approval tasks match these filters.</p>;
  }

  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full min-w-[900px] text-sm" aria-label="Approval tasks">
        <thead className="bg-muted/50 text-left">
          <tr>
            <th className="px-3 py-2 font-medium">Requisition</th>
            <th className="px-3 py-2 font-medium">Stage</th>
            <th className="px-3 py-2 font-medium">Assignee</th>
            <th className="px-3 py-2 font-medium">Requester</th>
            <th className="px-3 py-2 font-medium">Status</th>
            <th className="px-3 py-2 font-medium">Due</th>
            <th className="px-3 py-2 font-medium">Updated</th>
            <th className="px-3 py-2 text-right font-medium">Action</th>
          </tr>
        </thead>
        <tbody>
          {tasks.map((task) => (
            <tr key={task.id} className="border-t">
              <td className="px-3 py-3">
                <div className="font-medium">{task.subject.title}</div>
                <div className="font-mono text-xs text-muted-foreground">{task.subject.number}</div>
              </td>
              <td className="px-3 py-3">{task.stage.name}</td>
              <td className="px-3 py-3 text-muted-foreground">{task.assignee?.name ?? "Unassigned"}</td>
              <td className="px-3 py-3 text-muted-foreground">{task.subject.requester?.name ?? "Unknown"}</td>
              <td className="px-3 py-3"><ApprovalStatusBadge status={task.status} /></td>
              <td className="px-3 py-3 tabular-nums">{formatDate(task.dueAt)}</td>
              <td className="px-3 py-3 tabular-nums text-muted-foreground">{formatDate(task.updatedAt)}</td>
              <td className="px-3 py-3 text-right">
                <Link
                  href={`/approvals/tasks/${task.id}`}
                  className="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"
                >
                  <ExternalLink className="h-4 w-4" aria-hidden="true" />
                  Open
                </Link>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function formatDate(value?: string | null) {
  if (!value) return "No due date";
  return new Intl.DateTimeFormat("en", { month: "short", day: "numeric", year: "numeric" }).format(
    new Date(value),
  );
}

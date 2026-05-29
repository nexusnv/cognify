"use client";

import Link from "next/link";
import { ExternalLink } from "lucide-react";
import {
  Button,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import type { ApprovalRequisitionSubjectMetadata } from "@cognify/api-client/schemas";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { LoadingState } from "@/components/ui/loading-state";
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
    return <LoadingState label="Loading approvals" rows={4} />;
  }

  if (state === "error") {
    return <ErrorState title="Approval tasks could not be loaded." />;
  }

  if (state === "empty" || tasks.length === 0) {
    return <EmptyState title="No approval tasks match these filters." />;
  }

  return (
    <div className="rounded-md border">
      <Table className="min-w-[900px] text-sm" aria-label="Approval tasks">
        <TableHeader className="bg-muted/50 text-left">
          <TableRow>
            <TableHead className="px-3 py-2 font-medium">Subject</TableHead>
            <TableHead className="px-3 py-2 font-medium">Stage</TableHead>
            <TableHead className="px-3 py-2 font-medium">Assignee</TableHead>
            <TableHead className="px-3 py-2 font-medium">Requester</TableHead>
            <TableHead className="px-3 py-2 font-medium">Status</TableHead>
            <TableHead className="px-3 py-2 font-medium">Due</TableHead>
            <TableHead className="px-3 py-2 font-medium">Updated</TableHead>
            <TableHead className="px-3 py-2 text-right font-medium">Action</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {tasks.map((task) => (
            <TableRow key={task.id}>
              <TableCell className="px-3 py-3">
                <div className="font-medium">{task.subject.title ?? "Approval subject"}</div>
                <div className="font-mono text-xs text-muted-foreground">{task.subject.number ?? task.subject.type}</div>
              </TableCell>
              <TableCell className="px-3 py-3">{task.stage.name}</TableCell>
              <TableCell className="px-3 py-3 text-muted-foreground">{task.assignee?.name ?? "Unassigned"}</TableCell>
              <TableCell className="px-3 py-3 text-muted-foreground">{subjectRequester(task) ?? task.subject.primaryParty ?? "Unknown"}</TableCell>
              <TableCell className="px-3 py-3"><ApprovalStatusBadge status={task.status} /></TableCell>
              <TableCell className="px-3 py-3 tabular-nums">{formatDate(task.dueAt)}</TableCell>
              <TableCell className="px-3 py-3 tabular-nums text-muted-foreground">{formatDate(task.updatedAt)}</TableCell>
              <TableCell className="px-3 py-3 text-right">
                <Button asChild variant="outline" size="sm">
                  <Link href={`/approvals/tasks/${task.id}`}>
                    Open
                    <ExternalLink className="h-4 w-4" aria-hidden="true" />
                  </Link>
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

function formatDate(value?: string | null) {
  if (!value) return "No due date";
  return new Intl.DateTimeFormat("en", { month: "short", day: "numeric", year: "numeric" }).format(
    new Date(value),
  );
}

function subjectRequester(task: ApprovalTask): string | null {
  if (task.subject.type !== "requisition") return null;

  const requester = (task.subject.metadata as ApprovalRequisitionSubjectMetadata).requester;

  return requester?.name ?? null;
}

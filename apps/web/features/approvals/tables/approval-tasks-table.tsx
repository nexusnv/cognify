"use client";

import Link from "next/link";
import { ExternalLink, MoreHorizontal } from "lucide-react";
import {
  Alert,
  AlertDescription,
  AlertTitle,
  Button,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
  Skeleton,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import type { ApprovalRequisitionSubjectMetadata } from "@cognify/api-client/schemas";
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
    return (
      <div className="space-y-2 rounded-lg border p-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="grid grid-cols-[2fr_repeat(5,minmax(0,1fr))_auto] gap-3">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-14 justify-self-end" />
          </div>
        ))}
      </div>
    );
  }

  if (state === "error") {
    return (
      <Alert variant="destructive">
        <AlertTitle>Approval queue unavailable</AlertTitle>
        <AlertDescription>Approval tasks could not be loaded.</AlertDescription>
      </Alert>
    );
  }

  if (state === "empty" || tasks.length === 0) {
    return (
      <Empty className="rounded-lg border">
        <EmptyHeader>
          <EmptyTitle>No approval tasks match these filters.</EmptyTitle>
          <EmptyDescription>Adjust the queue filters or switch scope to review more work.</EmptyDescription>
        </EmptyHeader>
      </Empty>
    );
  }

  return (
    <div className="overflow-hidden rounded-lg border">
      <Table aria-label="Approval tasks" className="min-w-[900px]">
        <TableHeader className="bg-muted/50">
          <TableRow>
            <TableHead>Subject</TableHead>
            <TableHead>Stage</TableHead>
            <TableHead>Assignee</TableHead>
            <TableHead>Requester</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Due</TableHead>
            <TableHead>Updated</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {tasks.map((task) => (
            <TableRow key={task.id}>
              <TableCell>
                <div className="font-medium">{task.subject.title ?? "Approval subject"}</div>
                <div className="font-mono text-xs text-muted-foreground">{task.subject.number ?? task.subject.type}</div>
              </TableCell>
              <TableCell>{task.stage.name}</TableCell>
              <TableCell className="text-muted-foreground">{task.assignee?.name ?? "Unassigned"}</TableCell>
              <TableCell className="text-muted-foreground">{subjectRequester(task) ?? task.subject.primaryParty ?? "Unknown"}</TableCell>
              <TableCell><ApprovalStatusBadge status={task.status} /></TableCell>
              <TableCell className="tabular-nums">{formatDate(task.dueAt)}</TableCell>
              <TableCell className="tabular-nums text-muted-foreground">{formatDate(task.updatedAt)}</TableCell>
              <TableCell className="text-right">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline" size="icon" aria-label={`Open actions for ${task.subject.title ?? task.id}`}>
                      <MoreHorizontal className="h-4 w-4" aria-hidden="true" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    {task.permissions.canView ? (
                      <DropdownMenuItem asChild>
                        <Link href={`/approvals/tasks/${task.id}`} className="min-h-11">
                          <ExternalLink className="h-4 w-4" aria-hidden="true" />
                          Open task
                        </Link>
                      </DropdownMenuItem>
                    ) : null}
                    {task.subject.href ? (
                      <DropdownMenuItem asChild>
                        <Link href={task.subject.href} className="min-h-11">
                          <ExternalLink className="h-4 w-4" aria-hidden="true" />
                          {subjectLinkLabel(task.subject.type)}
                        </Link>
                      </DropdownMenuItem>
                    ) : null}
                  </DropdownMenuContent>
                </DropdownMenu>
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

function subjectLinkLabel(subjectType: string) {
  if (subjectType === "rfq_award_recommendation") return "Open award recommendation";
  if (subjectType === "purchase_order") return "Open purchase order";
  return "Open requisition";
}

function subjectRequester(task: ApprovalTask): string | null {
  if (task.subject.type !== "requisition") return null;

  const requester = (task.subject.metadata as ApprovalRequisitionSubjectMetadata).requester;

  return requester?.name ?? null;
}

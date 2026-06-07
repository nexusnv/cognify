"use client";

import Link from "next/link";
import { useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import {
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
  NativeSelect,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@cognify/ui";
import { useRequisitions } from "@/features/requisitions/hooks/use-requisitions";
import type { ProjectPermissions, ProjectRequisition } from "../types/project-view-model";
import { useLinkProjectRequisition, useUnlinkProjectRequisition } from "../hooks/use-project-requisitions";

const groups = [
  { id: "draft", label: "Draft" },
  { id: "submitted", label: "Submitted" },
  { id: "changes_requested", label: "Changes requested" },
  { id: "stopped", label: "Stopped" },
] as const;

const linkableStatuses = new Set(["draft", "submitted", "changes_requested"]);

export function ProjectRequisitionPipeline({
  projectId,
  requisitions,
  permissions,
}: {
  projectId: string;
  requisitions: ProjectRequisition[];
  permissions: Pick<ProjectPermissions, "canLinkRequisitions" | "canUnlinkRequisitions">;
}) {
  const [selectedRequisitionId, setSelectedRequisitionId] = useState("");
  const requisitionsQuery = useRequisitions();
  const linkMutation = useLinkProjectRequisition(projectId);
  const unlinkMutation = useUnlinkProjectRequisition(projectId);

  const availableRequisitions = useMemo(
    () =>
      (requisitionsQuery.data?.data ?? [])
        .filter((item) => !item.projectId && linkableStatuses.has(item.status))
        .sort((first, second) => first.number.localeCompare(second.number)),
    [requisitionsQuery.data?.data],
  );

  async function handleLinkRequisition(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!selectedRequisitionId) return;

    try {
      await linkMutation.mutateAsync(selectedRequisitionId);
      toast.success("Requisition linked");
      setSelectedRequisitionId("");
    } catch {
      toast.error("Unable to link requisition right now.");
    }
  }

  async function handleUnlinkRequisition(requisitionId: string) {
    try {
      await unlinkMutation.mutateAsync(requisitionId);
      toast.success("Requisition unlinked");
    } catch {
      toast.error("Unable to unlink requisition right now.");
    }
  }

  return (
    <Card className="py-0">
      <CardHeader className="border-b bg-muted/30">
        <CardTitle>Requisition pipeline</CardTitle>
        <CardDescription>
          Track linked requisitions and move work in and out of this project.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 py-4">
        {permissions.canLinkRequisitions ? (
          <form className="grid gap-2 sm:max-w-md" onSubmit={handleLinkRequisition}>
            <label className="space-y-1.5 text-sm font-medium">
              Link requisition
              <NativeSelect
                value={selectedRequisitionId}
                onChange={(event) => setSelectedRequisitionId(event.target.value)}
              >
                <option value="">Select an unlinked requisition</option>
                {availableRequisitions.map((requisition) => (
                  <option key={requisition.id} value={requisition.id}>
                    {requisition.number} - {requisition.title}
                  </option>
                ))}
              </NativeSelect>
            </label>
            <div className="flex justify-end">
              <Button
                type="submit"
                size="sm"
                disabled={!selectedRequisitionId || linkMutation.isPending}
              >
                {linkMutation.isPending ? "Linking" : "Link requisition"}
              </Button>
            </div>
          </form>
        ) : null}

        {permissions.canLinkRequisitions &&
        requisitionsQuery.isSuccess &&
        availableRequisitions.length === 0 ? (
          <p className="text-sm text-muted-foreground">No unlinked requisitions are available.</p>
        ) : null}

        <div className="space-y-4">
          {groups.map((group) => {
            const rows = requisitions.filter((item) => groupForStatus(item.status) === group.id);

            return (
              <section key={group.id} className="space-y-2">
                <h3 className="text-sm font-medium text-muted-foreground">{group.label}</h3>
                {rows.length === 0 ? (
                  <Empty className="rounded-md border border-dashed p-4">
                    <EmptyHeader className="gap-0.5">
                      <EmptyTitle>No requisitions in this stage</EmptyTitle>
                      <EmptyDescription>
                        Move work into this project to populate the {group.label.toLowerCase()} queue.
                      </EmptyDescription>
                    </EmptyHeader>
                  </Empty>
                ) : (
                  <Table aria-label={`${group.label} requisitions`}>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Requisition</TableHead>
                        <TableHead>Requester</TableHead>
                        <TableHead>Total</TableHead>
                        <TableHead>Status</TableHead>
                        {permissions.canUnlinkRequisitions ? (
                          <TableHead className="text-right">Action</TableHead>
                        ) : null}
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {rows.map((row) => (
                        <TableRow key={row.id}>
                          <TableCell>
                            <div className="space-y-1">
                              <Link
                                href={`/requisitions/${row.id}`}
                                className="font-mono text-xs tabular-nums hover:underline"
                              >
                                {row.number}
                              </Link>
                              <div>
                                <Link
                                  href={`/requisitions/${row.id}`}
                                  className="font-medium hover:underline"
                                >
                                  {row.title}
                                </Link>
                              </div>
                            </div>
                          </TableCell>
                          <TableCell>{row.requester?.name ?? "Unknown"}</TableCell>
                          <TableCell className="font-mono tabular-nums">
                            {formatMoney(row.estimatedTotal, "MYR")}
                          </TableCell>
                          <TableCell>{formatStatus(row.status)}</TableCell>
                          {permissions.canUnlinkRequisitions ? (
                            <TableCell className="text-right">
                              <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={unlinkMutation.isPending}
                                onClick={() => void handleUnlinkRequisition(row.id)}
                              >
                                Unlink
                              </Button>
                            </TableCell>
                          ) : null}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </section>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}

function groupForStatus(status: string) {
  if (status === "draft") return "draft";
  if (status === "submitted") return "submitted";
  if (status === "changes_requested") return "changes_requested";
  return "stopped";
}

function formatStatus(status: string) {
  if (status === "changes_requested") return "Changes requested";
  if (status === "on_hold") return "On hold";
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatMoney(amount: number, currency: string) {
  return new Intl.NumberFormat(undefined, {
    style: "currency",
    currency,
    maximumFractionDigits: 2,
  }).format(amount);
}

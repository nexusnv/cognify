"use client";

import Link from "next/link";
import { useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import { Button, NativeSelect } from "@cognify/ui";
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
    <section id="pipeline" className="rounded-md border p-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-base font-semibold">Requisition pipeline</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Track linked requisitions and move work in and out of this project.
          </p>
        </div>
        {permissions.canLinkRequisitions ? (
          <form className="flex flex-col gap-2 sm:w-[24rem]" onSubmit={handleLinkRequisition}>
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
              <Button type="submit" size="sm" disabled={!selectedRequisitionId || linkMutation.isPending}>
                {linkMutation.isPending ? "Linking" : "Link requisition"}
              </Button>
            </div>
          </form>
        ) : null}
      </div>

      {permissions.canLinkRequisitions && availableRequisitions.length === 0 ? (
        <p className="mt-3 text-sm text-muted-foreground">No unlinked requisitions are available.</p>
      ) : null}

      <div className="mt-3 space-y-4">
        {groups.map((group) => {
          const rows = requisitions.filter((item) => groupForStatus(item.status) === group.id);
          return (
            <div key={group.id} className="space-y-2">
              <h3 className="text-sm font-medium text-muted-foreground">{group.label}</h3>
              {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">No requisitions in this stage.</p>
              ) : (
                rows.map((row) => (
                  <div
                    key={row.id}
                    className="grid gap-2 rounded-md border p-3 text-sm sm:grid-cols-[8.5rem_minmax(0,1fr)_10rem_8rem_7rem_auto] sm:items-center"
                  >
                    <Link href={`/requisitions/${row.id}`} className="font-mono text-xs tabular-nums hover:underline">
                      {row.number}
                    </Link>
                    <Link href={`/requisitions/${row.id}`} className="font-medium hover:underline">
                      {row.title}
                    </Link>
                    <span className="text-muted-foreground">{row.requester?.name ?? "Unknown"}</span>
                    <span className="font-mono tabular-nums">{formatMoney(row.estimatedTotal, "MYR")}</span>
                    <span className="text-muted-foreground">{formatStatus(row.status)}</span>
                    {permissions.canUnlinkRequisitions ? (
                      <div className="flex justify-start sm:justify-end">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={unlinkMutation.isPending}
                          onClick={() => void handleUnlinkRequisition(row.id)}
                        >
                          Unlink
                        </Button>
                      </div>
                    ) : null}
                  </div>
                ))
              )}
            </div>
          );
        })}
      </div>
    </section>
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

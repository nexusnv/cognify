"use client";

import { formatSystemStatusLabel } from "@/features/system-readiness/utils/system-status-label";
import { formatWorkspaceLabel } from "./shell-utils";

export interface ShellFooterProps {
  tenantName: string;
  canViewSystemStatus?: boolean;
  readinessStatus?: "ok" | "warning" | "error";
}

export function ShellFooter({
  tenantName,
  canViewSystemStatus = false,
  readinessStatus,
}: ShellFooterProps) {
  const workspaceLabel = formatWorkspaceLabel(tenantName);
  const readinessLabel = formatSystemStatusLabel(readinessStatus);
  const productLabel =
    canViewSystemStatus && readinessLabel ? `Cognify · Local demo · ${readinessLabel}` : "Cognify";

  return (
    <footer className="border-t px-4 py-3 text-xs text-muted-foreground md:px-6">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span>{productLabel}</span>
        <span className="truncate" title={`Workspace: ${workspaceLabel}`}>
          Workspace: {workspaceLabel}
        </span>
      </div>
    </footer>
  );
}

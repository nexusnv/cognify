export interface ShellFooterProps {
  tenantName: string;
}

export function ShellFooter({ tenantName }: ShellFooterProps) {
  const workspaceLabel = tenantName.trim() || "Operational workspace";

  return (
    <footer className="border-t px-4 py-3 text-xs text-muted-foreground md:px-6">
      <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <span>Cognify</span>
        <span className="truncate" title={`Workspace: ${workspaceLabel}`}>
          Workspace: {workspaceLabel}
        </span>
      </div>
    </footer>
  );
}

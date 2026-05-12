import { WorkspaceShell } from "@/components/shell/workspace-shell";
import { SessionGate } from "@/features/identity/workflows/session-gate";

export default function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionGate>
      <WorkspaceShell>{children}</WorkspaceShell>
    </SessionGate>
  );
}
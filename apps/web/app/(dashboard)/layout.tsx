import { AppShell } from "@/components/shell/app-shell";
import { SessionGate } from "@/features/identity/workflows/session-gate";

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionGate>
      <AppShell>
        <div className="mx-auto w-full max-w-7xl">{children}</div>
      </AppShell>
    </SessionGate>
  );
}

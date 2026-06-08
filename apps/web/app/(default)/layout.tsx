import { DefaultAppShell } from "@/components/default-shell/default-app-shell";
import { SessionGate } from "@/features/identity/workflows/session-gate";

export default function DefaultLayout({ children }: { children: React.ReactNode }) {
  return (
    <SessionGate>
      <DefaultAppShell>{children}</DefaultAppShell>
    </SessionGate>
  );
}

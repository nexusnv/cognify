import type { SystemStatusState } from "@cognify/api-client/schemas";

const statusLabels: Record<SystemStatusState, string> = {
  ok: "Healthy",
  warning: "Warning",
  error: "Needs attention",
};

export function formatSystemStatusLabel(status: SystemStatusState | null | undefined): string | null {
  if (!status) {
    return null;
  }

  return statusLabels[status];
}

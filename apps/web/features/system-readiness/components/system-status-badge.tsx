import type { SystemStatusState } from "@cognify/api-client/schemas";

const badgeStyles: Record<SystemStatusState, string> = {
  ok: "border-emerald-300 bg-emerald-50 text-emerald-950",
  warning: "border-amber-300 bg-amber-50 text-amber-950",
  error: "border-red-300 bg-red-50 text-red-950",
};

const badgeLabels: Record<SystemStatusState, string> = {
  ok: "Healthy",
  warning: "Warning",
  error: "Needs attention",
};

export function SystemStatusBadge({ status }: { status: SystemStatusState }) {
  return (
    <span
      className={`inline-flex min-h-7 items-center rounded-md border px-2.5 text-xs font-medium ${badgeStyles[status]}`}
    >
      {badgeLabels[status]}
    </span>
  );
}

import { Badge } from "@cognify/ui";
import type { BadgeProps } from "@cognify/ui";
import type { WorkflowStateConfig, WorkflowTone } from "./workflow-state";

const workflowToneToBadgeVariant: Record<WorkflowTone, NonNullable<BadgeProps["variant"]>> = {
  neutral: "secondary",
  draft: "outline",
  info: "outline",
  success: "default",
  warning: "outline",
  danger: "destructive",
  locked: "secondary",
};

export function StatusBadge<TStatus extends string>({
  status,
  config,
  size = "default",
}: {
  status: TStatus;
  config: WorkflowStateConfig<TStatus>;
  size?: "default" | "compact";
}) {
  const state = config[status];
  const Icon = state.icon;

  return (
    <Badge
      variant={workflowToneToBadgeVariant[state.tone]}
      className={size === "compact" ? "min-h-6 gap-1 px-2 py-0.5 text-[0.75rem]" : "min-h-7 gap-1.5 px-2.5 py-1 text-xs"}
    >
      <Icon className={size === "compact" ? "h-3 w-3" : "h-3.5 w-3.5"} aria-hidden="true" />
      <span>{state.label}</span>
      <span className="sr-only">{state.description}</span>
    </Badge>
  );
}

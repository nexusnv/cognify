// shadcn-factory-exception: Workflow status tones require shared procurement state mapping beyond shadcn Badge variants; primitives=Badge; routes=requisitions,sourcing

import type { WorkflowStateConfig } from "./workflow-state";
import { workflowToneClassNames } from "./workflow-state";

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
  const sizeClassName =
    size === "compact" ? "min-h-6 gap-1 px-2 text-[0.75rem]" : "min-h-7 gap-1.5 px-2.5 text-xs";

  return (
    <span
      className={`inline-flex items-center rounded-md border font-medium ${sizeClassName} ${
        workflowToneClassNames[state.tone]
      }`}
    >
      <Icon className={size === "compact" ? "h-3 w-3" : "h-3.5 w-3.5"} aria-hidden="true" />
      <span>{state.label}</span>
      <span className="sr-only">{state.description}</span>
    </span>
  );
}

// shadcn-factory-exception: Workflow status tones require shared procurement state mapping beyond shadcn Badge variants; primitives=Badge; routes=requisitions,sourcing

import type { LucideIcon } from "lucide-react";

export type WorkflowTone =
  | "neutral"
  | "draft"
  | "info"
  | "success"
  | "warning"
  | "danger"
  | "locked";

export type WorkflowStateConfig<TStatus extends string> = Record<
  TStatus,
  {
    label: string;
    description: string;
    tone: WorkflowTone;
    icon: LucideIcon;
  }
>;

export const workflowToneClassNames: Record<WorkflowTone, string> = {
  neutral: "border-slate-300 bg-slate-50 text-slate-900",
  draft: "border-amber-300 bg-amber-50 text-amber-950",
  info: "border-blue-300 bg-blue-50 text-blue-950",
  success: "border-emerald-300 bg-emerald-50 text-emerald-950",
  warning: "border-orange-300 bg-orange-50 text-orange-950",
  danger: "border-red-300 bg-red-50 text-red-950",
  locked: "border-zinc-300 bg-zinc-100 text-zinc-950",
};

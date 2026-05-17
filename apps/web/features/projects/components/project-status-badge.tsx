import { Badge } from "@cognify/ui";
import type { ProjectStatus } from "../types/project-view-model";

const labels: Record<ProjectStatus, string> = {
  draft: "Draft",
  active: "Active",
  on_hold: "On hold",
  completed: "Completed",
  cancelled: "Cancelled",
};

export function ProjectStatusBadge({
  status,
  size = "default",
}: {
  status: ProjectStatus;
  size?: "default" | "compact";
}) {
  return (
    <Badge
      variant={status === "cancelled" ? "destructive" : "secondary"}
      className={size === "compact" ? "px-2 py-0.5 text-xs" : undefined}
    >
      {labels[status]}
    </Badge>
  );
}

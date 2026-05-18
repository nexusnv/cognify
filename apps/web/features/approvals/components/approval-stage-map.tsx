import type { ApprovalPreviewStage } from "../types/approval-view-model";

export function ApprovalStageMap({
  stages,
}: {
  stages: ApprovalPreviewStage[];
}) {
  if (stages.length === 0) {
    return <p className="text-sm text-muted-foreground">No approval stages configured.</p>;
  }

  return (
    <ol className="space-y-3">
      {stages.map((stage, index) => {
        const isActionable = index === 0;
        const approverLabels = stage.approvers
          .map((approver) => approver.label ?? approver.role ?? approver.userId ?? approver.type)
          .join(", ");
        const fallbackLabels = stage.fallbackApprovers
          .map((approver) => approver.label ?? approver.role ?? approver.userId ?? approver.type)
          .join(", ");
        return (
          <li key={`${stage.name}-${index}`} className="rounded-md border p-3">
            <div className="flex items-center justify-between gap-3">
              <h3 className="text-sm font-semibold">{stage.name}</h3>
              <span className="text-xs uppercase text-muted-foreground">
                {isActionable ? stage.completionRule : "blocked"}
              </span>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
              <span className="block">{approverLabels || "No approvers"}</span>
              <span className="block">
                Fallback: {fallbackLabels || "No fallback approver configured"}
              </span>
            </p>
            {!isActionable ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Blocked until the prior stage completes.
              </p>
            ) : null}
            {stage.dueAt ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Due at {stage.dueAt}
              </p>
            ) : null}
            {stage.warnings.length > 0 ? (
              <ul className="mt-2 space-y-1 text-xs text-amber-700">
                {stage.warnings.map((warning) => (
                  <li key={`${stage.name}-${warning.code}`}>{warning.message}</li>
                ))}
              </ul>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}

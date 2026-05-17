import type { ApprovalRouteStage, ApprovalSlaRule } from "../types/approval-view-model";

export function ApprovalStageMap({
  stages,
  slaRules = [],
}: {
  stages: ApprovalRouteStage[];
  slaRules?: ApprovalSlaRule[];
}) {
  if (stages.length === 0) {
    return <p className="text-sm text-muted-foreground">No approval stages configured.</p>;
  }

  return (
    <ol className="space-y-3">
      {stages.map((stage, index) => {
        const sla = slaRules.find((rule) => rule.stage === stage.name);
        return (
          <li key={`${stage.name}-${index}`} className="rounded-md border p-3">
            <div className="flex items-center justify-between gap-3">
              <h3 className="text-sm font-semibold">{stage.name}</h3>
              <span className="text-xs uppercase text-muted-foreground">{stage.completionRule}</span>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">
              {stage.approvers.map((approver) => approver.label ?? approver.role ?? approver.type).join(", ")}
            </p>
            {sla ? (
              <p className="mt-2 text-xs text-muted-foreground">
                Due in {sla.dueInHours}h
                {sla.escalateAfterHours ? `, escalate after ${sla.escalateAfterHours}h` : ""}
              </p>
            ) : null}
          </li>
        );
      })}
    </ol>
  );
}

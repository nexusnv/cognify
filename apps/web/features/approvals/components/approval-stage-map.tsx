import { Badge, Card, CardContent, CardHeader, CardTitle, Separator } from "@cognify/ui";
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
        const fallbackLabels = (stage.fallbackApprovers ?? [])
          .map((approver) => approver.label ?? approver.role ?? approver.userId ?? approver.type)
          .join(", ");
        return (
          <li key={`${stage.name}-${index}`}>
            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-3">
                  <CardTitle className="text-sm">{stage.name}</CardTitle>
                  <Badge variant={isActionable ? "secondary" : "outline"}>
                    <span>{stage.completionRule}</span>
                    {!isActionable ? <span> blocked</span> : null}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <p className="text-muted-foreground">
                  <span className="block">{approverLabels || "No approvers"}</span>
                  <span className="block">
                    Fallback: {fallbackLabels || "No fallback approver configured"}
                  </span>
                </p>
                {!isActionable ? (
                  <p className="text-xs text-muted-foreground">
                    Blocked until the prior stage completes.
                  </p>
                ) : null}
                {stage.dueAt ? (
                  <p className="text-xs text-muted-foreground">Due at {stage.dueAt}</p>
                ) : null}
                {stage.warnings.length > 0 ? (
                  <>
                    <Separator />
                    <ul className="space-y-1 text-xs text-amber-700">
                      {stage.warnings.map((warning) => (
                        <li key={`${stage.name}-${warning.code}`}>{warning.message}</li>
                      ))}
                    </ul>
                  </>
                ) : null}
              </CardContent>
            </Card>
          </li>
        );
      })}
    </ol>
  );
}

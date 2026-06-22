import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

interface CreditMemoApprovalPanelProps {
  creditMemoId: string;
  approvalInstanceId?: string | null;
}

export function CreditMemoApprovalPanel({ creditMemoId, approvalInstanceId }: CreditMemoApprovalPanelProps) {
  if (!approvalInstanceId) return null;

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Approval</CardTitle>
      </CardHeader>
      <CardContent className="text-sm">
        <p>Credit memo <span className="font-mono">{creditMemoId.slice(0, 8)}</span> is pending approval.</p>
        <p className="text-muted-foreground mt-1">Approval instance: {approvalInstanceId}</p>
      </CardContent>
    </Card>
  );
}

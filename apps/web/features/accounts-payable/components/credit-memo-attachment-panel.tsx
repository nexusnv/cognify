import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

export function CreditMemoAttachmentPanel() {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Attachments</CardTitle>
      </CardHeader>
      <CardContent className="text-sm text-muted-foreground">
        Attachments coming in P1-50.
      </CardContent>
    </Card>
  );
}

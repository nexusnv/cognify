"use client";

import type { QuotationNormalizationAttachment } from "@cognify/api-client/schemas";
import { Alert, AlertDescription, Button, Card, CardContent, CardHeader, CardTitle, Progress } from "@cognify/ui";

export function QuotationNormalizationAttachmentPanel({
  attachments,
}: {
  attachments: QuotationNormalizationAttachment[];
}) {
  return (
    <Card id="attachments">
      <CardHeader>
        <CardTitle className="text-base">Attachments</CardTitle>
        <p className="text-sm text-muted-foreground">Evidence snapshots captured from the current quotation version.</p>
      </CardHeader>
      <CardContent>
      <Progress value={attachments.length > 0 ? 100 : 0} />

      {attachments.length > 0 ? (
        <ul className="mt-4 space-y-3">
          {attachments.map((attachment) => (
            <li key={attachment.id} className="rounded-md bg-muted/30 p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{attachment.filename}</p>
                <p className="text-xs text-muted-foreground">{attachment.evidenceRole ?? "evidence"}</p>
              </div>
              <p className="mt-1 text-sm text-muted-foreground">{attachment.issueSummary ?? "No attachment issues."}</p>
              <Button type="button" variant="outline" size="sm" className="mt-2" disabled>
                View attachment
              </Button>
            </li>
          ))}
        </ul>
      ) : (
        <Alert className="mt-4"><AlertDescription>No attachments were captured for this version.</AlertDescription></Alert>
      )}
      </CardContent>
    </Card>
  );
}

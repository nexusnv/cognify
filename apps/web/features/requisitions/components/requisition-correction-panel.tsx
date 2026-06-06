"use client";

import { Alert, AlertDescription, AlertTitle, Button, Card, CardContent, CardDescription, CardHeader, CardTitle, Textarea } from "@cognify/ui";
import type { Requisition } from "../types/requisition-view-model";

function formatTimestamp(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
}

export function RequisitionCorrectionPanel({ requisition }: { requisition: Requisition }) {
  if (requisition.status !== "changes_requested") return null;

  const formattedChangesRequestedAt = formatTimestamp(requisition.changesRequestedAt);

  return (
    <Card className="border-amber-300 bg-amber-50">
      <CardHeader>
        <CardTitle>Changes requested</CardTitle>
        <CardDescription className="text-amber-950">
          {requisition.changesRequestedBy
            ? `Requested by ${requisition.changesRequestedBy.name}`
            : "Requested by a reviewer"}
          {formattedChangesRequestedAt ? ` on ${formattedChangesRequestedAt}` : ""}. Update the draft,
          then resubmit from this workspace when you are ready.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <Alert className="border-amber-300 bg-background/70 text-amber-950">
          <AlertTitle>Reviewer reason</AlertTitle>
          <AlertDescription className="whitespace-pre-wrap">
            {requisition.changeRequestReason ??
              "Please review the requested updates before resubmitting."}
          </AlertDescription>
        </Alert>
        {requisition.changeRequestFields?.length ? (
          <div>
            <p className="font-medium text-sm">Requested fields</p>
            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm">
              {requisition.changeRequestFields.map((field) => (
                <li key={field}>{field}</li>
              ))}
            </ul>
          </div>
        ) : null}
        <div>
          <p className="text-sm font-medium mb-2">Correction notes</p>
          <Textarea readOnly value={requisition.changeRequestReason ?? ""} className="min-h-24 bg-background/70" />
        </div>
        <Button type="button" variant="outline">
          Review requisition details
        </Button>
      </CardContent>
    </Card>
  );
}

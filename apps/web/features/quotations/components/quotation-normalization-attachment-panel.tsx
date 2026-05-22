"use client";

import type { QuotationNormalizationAttachment } from "@cognify/api-client/schemas";

export function QuotationNormalizationAttachmentPanel({
  attachments,
}: {
  attachments: QuotationNormalizationAttachment[];
}) {
  return (
    <section id="attachments" className="rounded-md border p-4">
      <div className="space-y-1">
        <h2 className="text-base font-semibold">Attachments</h2>
        <p className="text-sm text-muted-foreground">Evidence snapshots captured from the current quotation version.</p>
      </div>

      {attachments.length > 0 ? (
        <ul className="mt-4 space-y-3">
          {attachments.map((attachment) => (
            <li key={attachment.id} className="rounded-md border p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium">{attachment.filename}</p>
                <p className="text-xs text-muted-foreground">{attachment.evidenceRole ?? "evidence"}</p>
              </div>
              <p className="mt-1 text-sm text-muted-foreground">{attachment.issueSummary ?? "No attachment issues."}</p>
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-4 text-sm text-muted-foreground">No attachments were captured for this version.</p>
      )}
    </section>
  );
}

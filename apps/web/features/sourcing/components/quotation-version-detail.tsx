"use client";

import type { QuotationVersion } from "@cognify/api-client/schemas";
import { Badge } from "@cognify/ui";

export function QuotationVersionDetail({ version }: { version: QuotationVersion | null }) {
  if (!version) {
    return null;
  }

  return (
    <section className="space-y-3 rounded-md border p-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-1">
          <h4 className="text-sm font-semibold">Version {version.versionNumber}</h4>
          <p className="text-sm text-muted-foreground">
            {version.isCurrent ? "Current quotation version" : "Previous quotation version"}
          </p>
        </div>
        <div className="flex flex-col items-end gap-1">
          <p data-testid="quotation-version-total" className="text-sm font-medium">
            {version.manualEntry.totalAmount ?? "No total recorded"}
          </p>
          <Badge variant={version.isCurrent ? "secondary" : "outline"} className="px-2 py-0.5">
            {version.isCurrent ? "current" : "previous"}
          </Badge>
        </div>
      </div>

      <dl className="grid gap-3 text-sm sm:grid-cols-3">
        <div>
          <dt className="text-muted-foreground">Reference</dt>
          <dd className="font-medium">{version.manualEntry.quotationReference ?? "Not recorded"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Currency</dt>
          <dd className="font-medium">{version.manualEntry.currency ?? "Not recorded"}</dd>
        </div>
        <div>
          <dt className="text-muted-foreground">Attachments</dt>
          <dd className="font-medium">{version.attachmentCount}</dd>
        </div>
      </dl>

      <div className="space-y-2">
        <h5 className="text-sm font-medium">Evidence files</h5>
        {version.attachments.length === 0 ? (
          <p className="text-sm text-muted-foreground">No attachments captured for this version.</p>
        ) : (
          <ul className="divide-y rounded-md border text-sm">
            {version.attachments.map((attachment) => (
              <li key={attachment.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                <span className="font-medium">{attachment.filename}</span>
                <span className="text-muted-foreground">
                  {attachment.extension?.toUpperCase() ?? attachment.mimeType ?? "file"}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="overflow-x-auto rounded-md border">
        <table className="min-w-full text-sm">
          <thead className="bg-muted/40">
            <tr>
              <th className="border-b px-3 py-2 text-left font-medium">Description</th>
              <th className="border-b px-3 py-2 text-left font-medium">Quantity</th>
              <th className="border-b px-3 py-2 text-left font-medium">Unit price</th>
              <th className="border-b px-3 py-2 text-left font-medium">Total</th>
            </tr>
          </thead>
          <tbody>
            {version.lineItems.length === 0 ? (
              <tr>
                <td colSpan={4} className="px-3 py-4 text-muted-foreground">
                  No line items captured for this version.
                </td>
              </tr>
            ) : null}
            {version.lineItems.map((lineItem) => (
              <tr key={lineItem.id}>
                <td className="border-b px-3 py-2">{lineItem.description}</td>
                <td className="border-b px-3 py-2">{lineItem.quantity}</td>
                <td className="border-b px-3 py-2">{lineItem.unitPrice ?? "-"}</td>
                <td className="border-b px-3 py-2">{lineItem.totalAmount ?? "-"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}

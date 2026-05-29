"use client";

import type { VendorQuotationVersion } from "@cognify/api-client/schemas";
import { Badge } from "@cognify/ui";
import { Card, CardContent, CardHeader, CardTitle } from "@cognify/ui";

export function VendorQuotationVersionHistory({ versions }: { versions: VendorQuotationVersion[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">Quotation versions</CardTitle>
      </CardHeader>
      <CardContent>
        {versions.length === 0 ? (
          <p className="text-sm text-muted-foreground">No quotation versions have been submitted yet.</p>
        ) : (
          <ul className="space-y-2 text-sm">
            {versions.map((version) => (
              <li key={version.id} className="rounded-md border px-3 py-2">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                  <span className="font-medium">
                    Version {version.versionNumber}
                    {version.isCurrent ? " current" : ""}
                  </span>
                  <span>{version.manualEntry.totalAmount ?? "No total recorded"}</span>
                </div>
                <div className="mt-1 flex flex-wrap gap-2 text-muted-foreground">
                  <span>{version.manualEntry.quotationReference ?? "No reference recorded"}</span>
                  {version.isCurrent ? <Badge variant="secondary">current</Badge> : null}
                </div>
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

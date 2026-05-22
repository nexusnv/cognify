"use client";

import Link from "next/link";
import type { QuotationVersion } from "@cognify/api-client/schemas";
import { Badge, Button } from "@cognify/ui";

export function QuotationVersionHistory({
  versions,
  selectedVersionId,
  onSelectVersion,
}: {
  versions: QuotationVersion[];
  selectedVersionId: string | null;
  onSelectVersion: (versionId: string) => void;
}) {
  if (versions.length === 0) {
    return <p className="text-sm text-muted-foreground">No quotation versions recorded yet.</p>;
  }

  return (
    <div className="space-y-2">
      <h4 className="text-sm font-semibold">Version history</h4>
      <div className="flex flex-wrap gap-2" role="group" aria-label="Quotation versions">
        {versions.map((version) => {
          const activeNormalization = version.activeNormalization;

          return (
            <div key={version.id} className="flex items-center gap-2">
              <Button
                type="button"
                variant={selectedVersionId === version.id ? "default" : "outline"}
                size="sm"
                aria-pressed={selectedVersionId === version.id}
                aria-current={version.isCurrent ? "step" : undefined}
                onClick={() => onSelectVersion(version.id)}
                className="h-auto min-h-0 py-2"
              >
                <span>Version {version.versionNumber}</span>
                {" "}
                {version.isCurrent ? (
                  <Badge variant="secondary" className="px-1.5 py-0 text-[11px] font-medium">
                    current
                  </Badge>
                ) : null}
              </Button>
              {version.isCurrent && activeNormalization ? (
                <Link
                  href={`/quotations/normalizations/${activeNormalization.id}`}
                  className="text-sm font-medium underline-offset-4 hover:underline"
                >
                  Review normalization
                </Link>
              ) : null}
            </div>
          );
        })}
      </div>
    </div>
  );
}

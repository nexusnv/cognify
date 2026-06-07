"use client";

import { Button } from "@cognify/ui";

export function RequisitionSaveConflictPanel({ onReload }: { onReload: () => void }) {
  return (
    <div
      role="alert"
      className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950"
    >
      <p className="font-semibold">This draft changed elsewhere.</p>
      <p className="mt-1">
        Your local edits are still on screen. Reload the latest server copy before deciding what to
        reapply.
      </p>
      <Button type="button" variant="outline" className="mt-3 border-amber-400" onClick={onReload}>
        Reload latest draft
      </Button>
    </div>
  );
}

"use client";

import { Alert, AlertDescription, AlertTitle, Button } from "@cognify/ui";

export function RequisitionSaveConflictPanel({ onReload }: { onReload: () => void }) {
  return (
    <Alert className="border-amber-300 bg-amber-50 text-amber-950">
      <AlertTitle>This draft changed elsewhere.</AlertTitle>
      <AlertDescription className="mt-1">
        Your local edits are still on screen. Reload the latest server copy before deciding what to
        reapply.
      </AlertDescription>
      <Button type="button" variant="outline" className="mt-3 border-amber-400" onClick={onReload}>
        Reload latest draft
      </Button>
    </Alert>
  );
}

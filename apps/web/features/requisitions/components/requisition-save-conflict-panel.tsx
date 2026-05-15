"use client";

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
      <button
        type="button"
        className="mt-3 min-h-10 rounded-md border border-amber-400 px-3 font-medium"
        onClick={onReload}
      >
        Reload latest draft
      </button>
    </div>
  );
}

"use client";

import { Search } from "lucide-react";

export function CommandPaletteHost() {
  return (
    <button
      type="button"
      className="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm text-muted-foreground"
      aria-label="Open command palette"
    >
      <Search data-icon="inline-start" aria-hidden="true" />
      Search
    </button>
  );
}

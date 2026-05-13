"use client";

import { Search } from "lucide-react";

export function CommandPaletteHost() {
  // Command execution is intentionally deferred to the Command Palette epic.
  return (
    <button
      type="button"
      className="inline-flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm text-muted-foreground hover:text-foreground"
      aria-label="Open command palette"
    >
      <Search className="h-4 w-4" aria-hidden="true" />
      <span className="hidden sm:inline">Search</span>
    </button>
  );
}

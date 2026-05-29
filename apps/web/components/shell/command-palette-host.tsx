"use client";

import { Button } from "@cognify/ui";
import { Search } from "lucide-react";
import { useEffect, useState } from "react";
import { CommandPalette } from "@/features/search/components/command-palette";

export function CommandPaletteHost() {
  const [open, setOpen] = useState(false);

  useEffect(() => {
    function handleKeyDown(event: KeyboardEvent) {
      if (!(event.metaKey || event.ctrlKey)) return;
      if (event.key.toLowerCase() !== "k") return;

      event.preventDefault();
      setOpen(true);
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, []);

  return (
    <>
      <Button
        type="button"
        variant="outline"
        className="min-h-10 text-muted-foreground"
        aria-label="Open command palette"
        onClick={() => setOpen((current) => !current)}
      >
        <Search className="h-4 w-4" aria-hidden="true" />
        <span className="hidden sm:inline">Search</span>
      </Button>
      {open ? <CommandPalette open={open} onOpenChange={setOpen} /> : null}
    </>
  );
}

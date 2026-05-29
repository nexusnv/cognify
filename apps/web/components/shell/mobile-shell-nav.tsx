"use client";

import {
  Button,
  ScrollArea,
  Sheet,
  SheetClose,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@cognify/ui";
import { Menu, X } from "lucide-react";
import { useEffect } from "react";
import { ShellNav } from "./shell-nav";
import type { ShellNavGroup } from "./shell-types";

export function MobileShellNav({
  groups,
  pathname,
  open,
  onOpenChange,
}: {
  groups: ShellNavGroup[];
  pathname: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [open]);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetTrigger asChild>
        <Button
          type="button"
          variant="outline"
          size="icon"
          className="md:hidden"
          aria-label="Open navigation"
          aria-expanded={open}
        >
          <Menu className="h-4 w-4" aria-hidden="true" />
        </Button>
      </SheetTrigger>
      <SheetContent
        side="left"
        showCloseButton={false}
        aria-label="Navigation"
        aria-modal="true"
        aria-describedby={undefined}
        className="w-[min(22rem,90vw)] p-0 sm:max-w-none"
      >
        <SheetHeader className="gap-3 border-b pb-3">
          <div className="flex items-center justify-between gap-3">
            <div className="min-w-0">
              <SheetTitle>Navigation</SheetTitle>
              <p className="text-sm text-muted-foreground">Cognify</p>
            </div>
            <SheetClose asChild>
              <Button type="button" variant="outline" size="icon" aria-label="Close navigation">
                <X className="h-4 w-4" aria-hidden="true" />
              </Button>
            </SheetClose>
          </div>
        </SheetHeader>
        <ScrollArea className="min-h-0 flex-1 px-4 pb-4">
          <div className="pt-4">
            <ShellNav groups={groups} pathname={pathname} onNavigate={() => onOpenChange(false)} />
          </div>
        </ScrollArea>
      </SheetContent>
    </Sheet>
  );
}

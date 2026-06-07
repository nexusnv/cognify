"use client";

import { Menu, X } from "lucide-react";
import { Button } from "@cognify/ui/components/button";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@cognify/ui/components/sheet";
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
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetTrigger asChild>
        <Button
          type="button"
          className="md:hidden"
          variant="outline"
          size="icon-lg"
          aria-label="Open navigation"
        >
          <Menu className="h-4 w-4" aria-hidden="true" />
        </Button>
      </SheetTrigger>
      <SheetContent
        side="left"
        aria-label="Navigation"
        className="w-[min(22rem,90vw)] p-0 md:hidden"
        showCloseButton={false}
      >
        <SheetHeader className="flex-row items-center justify-between border-b px-4 py-3">
          <div>
            <div className="text-base font-semibold">Cognify</div>
            <SheetTitle className="sr-only">Navigation</SheetTitle>
            <SheetDescription className="sr-only">Primary workspace navigation</SheetDescription>
          </div>
          <SheetClose asChild>
            <Button type="button" variant="outline" size="icon-lg" aria-label="Close navigation">
              <X className="h-4 w-4" aria-hidden="true" />
            </Button>
          </SheetClose>
        </SheetHeader>
        <div className="overflow-y-auto p-4">
          <ShellNav groups={groups} pathname={pathname} onNavigate={() => onOpenChange(false)} />
        </div>
      </SheetContent>
    </Sheet>
  );
}

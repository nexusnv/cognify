"use client";

import {
  Button,
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@cognify/ui";
import { X } from "lucide-react";
import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { useOptionalRightPanel } from "./right-panel-provider";
import type { RightPanelDefinition, RightPanelSize } from "./right-panel-types";

const widthClassBySize: Record<RightPanelSize, string> = {
  sm: "sm:max-w-96",
  md: "sm:max-w-[32rem]",
  lg: "sm:max-w-[40rem]",
};

export function RightPanelRoot() {
  const rightPanel = useOptionalRightPanel();
  const pathname = usePathname();
  const previousPathname = useRef(pathname);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const previousPanelRef = useRef<RightPanelDefinition | null>(null);
  const panel = rightPanel?.panel ?? null;
  const closePanel = rightPanel?.closePanel;

  useEffect(() => {
    const wasOpen = Boolean(previousPanelRef.current);

    if (panel && !wasOpen) {
      previousFocusRef.current =
        document.activeElement instanceof HTMLElement ? document.activeElement : null;
    }

    previousPanelRef.current = panel;
  }, [panel]);

  useEffect(() => {
    if (previousPathname.current !== pathname && closePanel) {
      previousPathname.current = pathname;
      closePanel();
    }
  }, [closePanel, pathname]);

  if (!panel) return null;

  const Icon = panel.icon;
  const widthClassName = widthClassBySize[panel.size ?? "md"];

  return (
    <Sheet
      open={Boolean(panel)}
      onOpenChange={(open) => {
        if (!open) closePanel?.();
      }}
    >
      <SheetContent
        side="right"
        showCloseButton={false}
        className={`w-full p-0 ${widthClassName}`}
        onCloseAutoFocus={(event) => {
          event.preventDefault();
          previousFocusRef.current?.focus();
          previousFocusRef.current = null;
        }}
      >
        <SheetHeader className="flex-row items-start justify-between gap-3 border-b p-4 text-left">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              {Icon ? <Icon className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              <SheetTitle className="truncate text-base font-semibold">
                {panel.title}
              </SheetTitle>
            </div>
            {panel.description ? (
              <SheetDescription className="mt-1 text-sm text-muted-foreground">
                {panel.description}
              </SheetDescription>
            ) : null}
          </div>
          <SheetClose asChild>
            <Button
              type="button"
              variant="outline"
              size="icon"
              className="min-h-11 min-w-11"
              aria-label="Close panel"
            >
              <X className="h-4 w-4" aria-hidden="true" />
            </Button>
          </SheetClose>
        </SheetHeader>
        <div className="min-h-0 flex-1 overflow-y-auto p-4">{panel.content}</div>
        {panel.footer ? <SheetFooter className="border-t p-4">{panel.footer}</SheetFooter> : null}
      </SheetContent>
    </Sheet>
  );
}

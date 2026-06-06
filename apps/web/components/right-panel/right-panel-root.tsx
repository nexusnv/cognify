"use client";

import { X } from "lucide-react";
import { usePathname } from "next/navigation";
import { Button, ScrollArea, Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from "@cognify/ui";
import { useEffect, useRef } from "react";
import { useOptionalRightPanel } from "./right-panel-provider";
import type { RightPanelDefinition, RightPanelSize } from "./right-panel-types";

const widthClassBySize: Record<RightPanelSize, string> = {
  sm: "w-full sm:max-w-96",
  md: "w-full sm:max-w-[32rem]",
  lg: "w-full sm:max-w-[40rem]",
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

    if (!panel && wasOpen) {
      previousFocusRef.current?.focus();
      previousFocusRef.current = null;
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

  const titleId = `${panel.id}-title`;
  const descriptionId = panel.description ? `${panel.id}-description` : undefined;
  const Icon = panel.icon;
  const widthClassName = widthClassBySize[panel.size ?? "md"];

  return (
    <Sheet
      open={Boolean(panel)}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          closePanel?.();
        }
      }}
    >
      <SheetContent
        side="right"
        showCloseButton={false}
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={panel.description ? descriptionId : undefined}
        className={`gap-0 p-0 ${widthClassName}`}
      >
        <SheetHeader className="gap-3 border-b">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              {Icon ? <Icon className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              <SheetTitle id={titleId} className="truncate text-base">
                {panel.title}
              </SheetTitle>
            </div>
            {panel.description ? (
              <SheetDescription id={descriptionId} className="mt-1">
                {panel.description}
              </SheetDescription>
            ) : null}
          </div>
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={closePanel}
            aria-label="Close panel"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </Button>
        </SheetHeader>
        <ScrollArea className="min-h-0 flex-1">
          <div className="p-4">{panel.content}</div>
        </ScrollArea>
        {panel.footer ? (
          <SheetFooter className="border-t p-4 sm:flex-col sm:justify-start">{panel.footer}</SheetFooter>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

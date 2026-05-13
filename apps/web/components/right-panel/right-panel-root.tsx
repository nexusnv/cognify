"use client";

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
  const panelRef = useRef<HTMLElement>(null);
  const panel = rightPanel?.panel ?? null;
  const closePanel = rightPanel?.closePanel;

  useEffect(() => {
    if (!panel) return undefined;

    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    return () => {
      document.body.style.overflow = previousOverflow;
    };
  }, [panel]);

  useEffect(() => {
    if (!panel || !closePanel) return undefined;

    function handleKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        closePanel?.();
        return;
      }

      if (event.key !== "Tab") return;

      const focusable = getFocusableElements(panelRef.current);
      if (focusable.length === 0) {
        event.preventDefault();
        panelRef.current?.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
        return;
      }

      if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [closePanel, panel]);

  useEffect(() => {
    const wasOpen = Boolean(previousPanelRef.current);

    if (panel) {
      if (!wasOpen) {
        previousFocusRef.current =
          document.activeElement instanceof HTMLElement ? document.activeElement : null;
      }

      window.setTimeout(() => {
        const firstFocusable = getFocusableElements(panelRef.current)[0];
        (firstFocusable ?? panelRef.current)?.focus();
      }, 0);
    } else if (wasOpen) {
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
    <div className="fixed inset-0 z-50 flex justify-end">
      <button
        type="button"
        data-testid="right-panel-overlay"
        aria-label="Close panel overlay"
        className="absolute inset-0 cursor-default bg-black/30"
        onClick={closePanel}
      />
      <section
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
        tabIndex={-1}
        className={`relative flex h-full w-full flex-col border-l bg-background shadow-xl ${widthClassName}`}
      >
        <header className="flex items-start justify-between gap-3 border-b p-4">
          <div className="min-w-0">
            <div className="flex items-center gap-2">
              {Icon ? <Icon className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              <h2 id={titleId} className="truncate text-base font-semibold">
                {panel.title}
              </h2>
            </div>
            {panel.description ? (
              <p id={descriptionId} className="mt-1 text-sm text-muted-foreground">
                {panel.description}
              </p>
            ) : null}
          </div>
          <button
            type="button"
            className="inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border"
            onClick={closePanel}
            aria-label="Close panel"
          >
            <X className="h-4 w-4" aria-hidden="true" />
          </button>
        </header>
        <div className="min-h-0 flex-1 overflow-y-auto p-4">{panel.content}</div>
        {panel.footer ? <footer className="border-t p-4">{panel.footer}</footer> : null}
      </section>
    </div>
  );
}

function getFocusableElements(root: HTMLElement | null) {
  if (!root) return [];

  return Array.from(
    root.querySelectorAll<HTMLElement>(
      'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => !element.hasAttribute("disabled") && element.getAttribute("aria-hidden") !== "true");
}

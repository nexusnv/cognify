"use client";

import { useEffect, useRef } from "react";
import { Menu, X } from "lucide-react";
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
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const dialogRef = useRef<HTMLDivElement>(null);
  const onOpenChangeRef = useRef(onOpenChange);
  const triggerRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    onOpenChangeRef.current = onOpenChange;
  }, [onOpenChange]);

  useEffect(() => {
    if (!open) return;

    const previousOverflow = document.body.style.overflow;
    const triggerElement = triggerRef.current;
    document.body.style.overflow = "hidden";
    closeButtonRef.current?.focus();

    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape") {
        onOpenChangeRef.current(false);
        return;
      }

      if (event.key !== "Tab") return;

      const focusableElements = Array.from(
        dialogRef.current?.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ) ?? [],
      );

      if (focusableElements.length === 0) {
        event.preventDefault();
        return;
      }

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];

      if (event.shiftKey && document.activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
      } else if (!event.shiftKey && document.activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
      }
    }

    window.addEventListener("keydown", onKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      window.removeEventListener("keydown", onKeyDown);
      triggerElement?.focus();
    };
  }, [open]);

  return (
    <>
      <button
        ref={triggerRef}
        type="button"
        className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border md:hidden"
        aria-label="Open navigation"
        aria-expanded={open}
        onClick={() => onOpenChange(true)}
      >
        <Menu className="h-4 w-4" aria-hidden="true" />
      </button>
      {open ? (
        <div className="fixed inset-0 z-40 md:hidden">
          <button
            type="button"
            className="absolute inset-0 bg-black/30"
            aria-label="Close navigation"
            onClick={() => onOpenChange(false)}
          />
          <div
            ref={dialogRef}
            role="dialog"
            aria-modal="true"
            aria-label="Navigation"
            className="absolute inset-y-0 left-0 flex w-[min(22rem,90vw)] flex-col border-r bg-background p-4 shadow-lg"
          >
            <div className="flex items-center justify-between border-b pb-3">
              <div className="text-base font-semibold">Cognify</div>
              <button
                ref={closeButtonRef}
                type="button"
                className="inline-flex min-h-10 w-10 items-center justify-center rounded-md border"
                aria-label="Close navigation"
                onClick={() => onOpenChange(false)}
              >
                <X className="h-4 w-4" aria-hidden="true" />
              </button>
            </div>
            <div className="mt-4 overflow-y-auto">
              <ShellNav
                groups={groups}
                pathname={pathname}
                onNavigate={() => onOpenChange(false)}
              />
            </div>
          </div>
        </div>
      ) : null}
    </>
  );
}

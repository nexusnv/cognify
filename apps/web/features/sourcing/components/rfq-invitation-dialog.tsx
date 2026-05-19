"use client";

import type { KeyboardEvent, MouseEvent, ReactNode } from "react";
import { useEffect, useId, useRef } from "react";
import { Button } from "@cognify/ui";

export function RfqInvitationDialog({
  open,
  title,
  description,
  confirmLabel,
  confirmVariant = "default",
  confirmDisabled = false,
  isPending = false,
  error = null,
  onOpenChange,
  onConfirm,
  children,
  footerNote,
}: {
  open: boolean;
  title: string;
  description: ReactNode;
  confirmLabel: string;
  confirmVariant?: "default" | "outline" | "destructive";
  confirmDisabled?: boolean;
  isPending?: boolean;
  error?: string | null;
  onOpenChange: (open: boolean) => void;
  onConfirm: () => Promise<void> | void;
  children: ReactNode;
  footerNote?: ReactNode;
}) {
  const modalRef = useRef<HTMLDivElement | null>(null);
  const triggerRef = useRef<HTMLElement | null>(null);
  const titleId = useId();

  useEffect(() => {
    if (!open) {
      triggerRef.current?.focus();
      triggerRef.current = null;
      return;
    }

    const focusableElements = getFocusableElements(modalRef.current);
    (focusableElements[0] ?? modalRef.current)?.focus();
  }, [open]);

  function handleBackdropClick(event: MouseEvent<HTMLDivElement>) {
    if (event.target === event.currentTarget) {
      onOpenChange(false);
    }
  }

  function handleDialogKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === "Escape") {
      event.preventDefault();
      onOpenChange(false);
      return;
    }

    if (event.key !== "Tab") return;

    const focusableElements = getFocusableElements(modalRef.current);
    if (focusableElements.length === 0) {
      event.preventDefault();
      modalRef.current?.focus();
      return;
    }

    const firstElement = focusableElements[0];
    const lastElement = focusableElements[focusableElements.length - 1];

    if (event.shiftKey && document.activeElement === firstElement) {
      event.preventDefault();
      lastElement.focus();
      return;
    }

    if (!event.shiftKey && document.activeElement === lastElement) {
      event.preventDefault();
      firstElement.focus();
    }
  }

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" onClick={handleBackdropClick}>
      <div
        ref={modalRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        onKeyDown={handleDialogKeyDown}
        className="w-full max-w-2xl rounded-md border bg-background p-5 shadow-lg"
      >
        <div className="space-y-2">
          <h2 id={titleId} className="text-lg font-semibold">
            {title}
          </h2>
          <p className="text-sm text-muted-foreground">{description}</p>
        </div>

        <div className="mt-4 space-y-4">{children}</div>

        {error ? <p role="alert" className="mt-3 text-sm text-red-700">{error}</p> : null}
        {footerNote ? <div className="mt-3 text-xs text-muted-foreground">{footerNote}</div> : null}

        <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button
            ref={(node) => {
              if (node && !triggerRef.current) {
                triggerRef.current = node;
              }
            }}
            variant="outline"
            onClick={() => onOpenChange(false)}
          >
            Close
          </Button>
          <Button
            variant={confirmVariant}
            onClick={() => void onConfirm()}
            disabled={confirmDisabled || isPending}
          >
            {isPending ? "Working" : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}

function getFocusableElements(container: HTMLElement | null) {
  if (!container) return [];

  return Array.from(
    container.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => !element.hasAttribute("disabled") && element.getAttribute("aria-hidden") !== "true");
}

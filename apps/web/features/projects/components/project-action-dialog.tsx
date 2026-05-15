"use client";

import type { KeyboardEvent, MouseEvent } from "react";
import { useEffect, useId, useRef, useState } from "react";
import { Button, Textarea } from "@cognify/ui";

export function ProjectActionDialog({
  action,
  title,
  description,
  confirmLabel,
  triggerLabel,
  triggerVariant = "outline",
  isPending,
  onSubmit,
}: {
  action: "activate" | "hold" | "resume" | "complete" | "cancel";
  title: string;
  description: string;
  confirmLabel: string;
  triggerLabel: string;
  triggerVariant?: "default" | "outline" | "destructive";
  isPending: boolean;
  onSubmit: (values: { reason?: string }) => Promise<void> | void;
}) {
  const [open, setOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const triggerRef = useRef<HTMLButtonElement | null>(null);
  const modalRef = useRef<HTMLDivElement | null>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const titleId = useId();

  useEffect(() => {
    if (!open) {
      previousFocusRef.current?.focus();
      previousFocusRef.current = null;
      return;
    }

    const focusableElements = getFocusableElements(modalRef.current);
    (focusableElements[0] ?? modalRef.current)?.focus();
  }, [open]);

  async function handleSubmit() {
    if (action === "cancel" && !reason.trim()) {
      setError("Reason is required.");
      return;
    }

    setError(null);
    await onSubmit({ reason: reason.trim() || undefined });
    setOpen(false);
    setReason("");
  }

  function handleDialogKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === "Escape") {
      event.preventDefault();
      setOpen(false);
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

  function handleBackdropClick(event: MouseEvent<HTMLDivElement>) {
    if (event.target === event.currentTarget) {
      setOpen(false);
    }
  }

  return (
    <>
      <Button
        ref={triggerRef}
        variant={triggerVariant}
        onClick={() => {
          previousFocusRef.current =
            document.activeElement instanceof HTMLElement ? document.activeElement : triggerRef.current;
          setOpen(true);
        }}
      >
        {triggerLabel}
      </Button>
      {!open ? null : (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          onClick={handleBackdropClick}
        >
          <div
            ref={modalRef}
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            tabIndex={-1}
            onKeyDown={handleDialogKeyDown}
            className="w-full max-w-lg rounded-md border bg-background p-5 shadow-lg"
          >
            <div className="space-y-2">
              <h2 id={titleId} className="text-lg font-semibold">
                {title}
              </h2>
              <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            {action === "cancel" ? (
              <label className="mt-4 block text-sm font-medium">
                Reason
                <Textarea
                  aria-label="Reason"
                  className="mt-1"
                  value={reason}
                  onChange={(event) => setReason(event.target.value)}
                />
              </label>
            ) : null}
            {error ? <p className="mt-3 text-sm text-red-700">{error}</p> : null}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={() => setOpen(false)}>
                Keep editing
              </Button>
              <Button
                variant={triggerVariant === "destructive" ? "destructive" : "default"}
                onClick={() => void handleSubmit()}
                disabled={isPending}
              >
                {isPending ? "Working" : confirmLabel}
              </Button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

function getFocusableElements(container: HTMLElement | null) {
  if (!container) return [];

  return Array.from(
    container.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    ),
  ).filter((element) => !element.hasAttribute("disabled") && !element.getAttribute("aria-hidden"));
}

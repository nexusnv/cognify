"use client";

import { useEffect, useRef, type ReactNode, type RefObject } from "react";
import { Button, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@cognify/ui";

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
  restoreFocusRef,
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
  restoreFocusRef?: RefObject<HTMLElement | null>;
}) {
  const wasOpenRef = useRef(false);
  const restoreFocusTargetRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    const wasOpen = wasOpenRef.current;

    if (open && !wasOpen) {
      const activeElement = document.activeElement;
      restoreFocusTargetRef.current =
        activeElement instanceof HTMLElement && activeElement !== document.body && activeElement !== document.documentElement
          ? activeElement
          : null;
    }

    if (!open && wasOpen) {
      const restoreTarget =
        (restoreFocusTargetRef.current?.isConnected ? restoreFocusTargetRef.current : null) ?? restoreFocusRef?.current ?? null;
      restoreTarget?.focus();
      restoreFocusTargetRef.current = null;
    }

    wasOpenRef.current = open;
  }, [open, restoreFocusRef]);

  if (!open) return null;

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (isPending && !nextOpen) return;
        onOpenChange(nextOpen);
      }}
    >
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">{children}</div>

        {error ? <p role="alert" className="text-sm text-red-700">{error}</p> : null}
        {footerNote ? <div className="text-xs text-muted-foreground">{footerNote}</div> : null}

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={isPending}>
            Close
          </Button>
          <Button
            variant={confirmVariant}
            onClick={() => void onConfirm()}
            disabled={confirmDisabled || isPending}
          >
            {isPending ? "Working" : confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

"use client";

import type { ReactNode, RefObject } from "react";
import { useEffect } from "react";
import { Alert, AlertDescription, Button, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@cognify/ui";

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
  useEffect(() => {
    if (!open && restoreFocusRef?.current) {
      restoreFocusRef.current.focus();
    }
  }, [open, restoreFocusRef]);

  if (!open) return null;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl" onEscapeKeyDown={(event) => isPending && event.preventDefault()} onInteractOutside={(event) => isPending && event.preventDefault()}>
        <div className="space-y-4">
          <DialogHeader>
            <DialogTitle>{title}</DialogTitle>
            <DialogDescription>{description}</DialogDescription>
          </DialogHeader>

          <div className="space-y-4">{children}</div>

          {error ? (
            <Alert variant="destructive">
              <AlertDescription>{error}</AlertDescription>
            </Alert>
          ) : null}
          {footerNote ? <div className="text-xs text-muted-foreground">{footerNote}</div> : null}

          <DialogFooter className="pt-1">
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
        </div>
      </DialogContent>
    </Dialog>
  );
}

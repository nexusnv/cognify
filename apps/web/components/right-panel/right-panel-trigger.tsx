"use client";

import { PanelRightOpen } from "lucide-react";
import type { ReactNode } from "react";
import { useRightPanel } from "./right-panel-provider";
import type { RightPanelDefinition } from "./right-panel-types";

export function RightPanelTrigger({
  panel,
  children,
  className,
  ariaLabel,
}: {
  panel: RightPanelDefinition;
  children?: ReactNode;
  className?: string;
  ariaLabel?: string;
}) {
  const rightPanel = useRightPanel();

  return (
    <button
      type="button"
      className={
        className ??
        "inline-flex min-h-11 items-center justify-center gap-2 rounded-md border px-3 text-sm font-medium"
      }
      onClick={() => rightPanel.openPanel(panel)}
      aria-label={ariaLabel}
    >
      {children ?? (
        <>
          <PanelRightOpen className="h-4 w-4" aria-hidden="true" />
          Open panel
        </>
      )}
    </button>
  );
}

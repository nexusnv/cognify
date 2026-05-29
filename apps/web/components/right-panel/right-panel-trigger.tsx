"use client";

import { Button } from "@cognify/ui";
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
    <Button
      type="button"
      variant="outline"
      className={
        className ?? "min-h-11"
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
    </Button>
  );
}

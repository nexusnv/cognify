"use client";

import { createContext, useCallback, useContext, useMemo, useState } from "react";
import type { ReactNode } from "react";
import type { RightPanelContextValue, RightPanelDefinition } from "./right-panel-types";

const RightPanelContext = createContext<RightPanelContextValue | undefined>(undefined);

export function RightPanelProvider({ children }: { children: ReactNode }) {
  const [panel, setPanel] = useState<RightPanelDefinition | null>(null);
  const openPanel = useCallback((nextPanel: RightPanelDefinition) => setPanel(nextPanel), []);
  const closePanel = useCallback(() => setPanel(null), []);

  const value = useMemo<RightPanelContextValue>(
    () => ({
      panel,
      openPanel,
      closePanel,
    }),
    [closePanel, openPanel, panel],
  );

  return <RightPanelContext.Provider value={value}>{children}</RightPanelContext.Provider>;
}

export function useRightPanel() {
  const context = useOptionalRightPanel();

  if (!context) {
    throw new Error("useRightPanel must be used within RightPanelProvider.");
  }

  return context;
}

export function useOptionalRightPanel() {
  return useContext(RightPanelContext);
}

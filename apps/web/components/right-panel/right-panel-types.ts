import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";

export type RightPanelSize = "sm" | "md" | "lg";

export type RightPanelDefinition = {
  id: string;
  title: string;
  description?: string;
  icon?: LucideIcon;
  size?: RightPanelSize;
  content: ReactNode;
  footer?: ReactNode;
};

export type RightPanelContextValue = {
  panel: RightPanelDefinition | null;
  openPanel: (panel: RightPanelDefinition) => void;
  closePanel: () => void;
};

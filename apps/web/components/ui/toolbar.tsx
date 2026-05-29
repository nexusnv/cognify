import { type ReactNode } from "react";

type ToolbarProps = {
  children: ReactNode;
  label?: string;
  className?: string;
};

export function Toolbar({ children, label, className }: ToolbarProps) {
  return (
    <div
      role="toolbar"
      aria-label={label}
      className={`flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between ${className ?? ""}`.trim()}
    >
      {children}
    </div>
  );
}

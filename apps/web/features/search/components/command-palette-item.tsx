"use client";

import { Command } from "cmdk";
import type { LucideIcon } from "lucide-react";
import { ChevronRight } from "lucide-react";
import type { ReactNode } from "react";

export function CommandPaletteItem({
  value,
  keywords,
  icon: Icon,
  label,
  description,
  trailing,
  onSelect,
  disabled = false,
}: {
  value: string;
  keywords?: string[];
  icon?: LucideIcon;
  label: string;
  description?: string | null;
  trailing?: ReactNode;
  onSelect: () => void;
  disabled?: boolean;
}) {
  return (
    <Command.Item
      value={value}
      keywords={keywords}
      onSelect={onSelect}
      disabled={disabled}
      className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-3 py-2 text-sm outline-none aria-selected:bg-accent aria-selected:text-accent-foreground data-[disabled=true]:cursor-not-allowed data-[disabled=true]:opacity-50"
    >
      {Icon ? <Icon className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" /> : null}
      <div className="min-w-0 flex-1">
        <div className="flex min-w-0 items-center gap-2">
          <span className="truncate font-medium">{label}</span>
          {trailing ? <span className="shrink-0 text-xs text-muted-foreground">{trailing}</span> : null}
        </div>
        {description ? (
          <p className="truncate text-xs text-muted-foreground">{description}</p>
        ) : null}
      </div>
      <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
    </Command.Item>
  );
}

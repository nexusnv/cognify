"use client";

import { type ReactNode } from "react";
import { Button, Popover, PopoverContent, PopoverTrigger } from "@cognify/ui";

type FilterPopoverProps = {
  label: string;
  children: ReactNode;
};

export function FilterPopover({ label, children }: FilterPopoverProps) {
  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline">
          {label}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80">
        {children}
      </PopoverContent>
    </Popover>
  );
}

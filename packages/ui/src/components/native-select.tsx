import * as React from "react";
import { cn } from "../lib/utils";

export type NativeSelectProps = React.SelectHTMLAttributes<HTMLSelectElement>;

export const NativeSelect = React.forwardRef<HTMLSelectElement, NativeSelectProps>(
  ({ className, ...props }, ref) =>
    React.createElement("select", {
      ref,
      className: cn(
        "min-h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50",
        className,
      ),
      ...props,
    }),
);

NativeSelect.displayName = "NativeSelect";

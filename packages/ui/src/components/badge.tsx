import * as React from "react";
import { cn } from "../lib/utils";

type BadgeVariant = "default" | "secondary" | "outline" | "destructive";

const variantClasses: Record<BadgeVariant, string> = {
  default: "border-transparent bg-foreground text-background",
  secondary: "border-transparent bg-secondary text-secondary-foreground",
  outline: "text-foreground",
  destructive: "border-transparent bg-destructive text-destructive-foreground",
};

export type BadgeProps = React.HTMLAttributes<HTMLSpanElement> & {
  variant?: BadgeVariant;
};

export function Badge({ className, variant = "default", ...props }: BadgeProps) {
  return React.createElement("span", {
    className: cn(
        "inline-flex items-center rounded-md border border-border px-2 py-0.5 text-xs font-medium",
        variantClasses[variant],
        className,
      ),
    ...props,
  });
}

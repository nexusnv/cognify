import * as React from "react";
import { cn } from "../lib/utils";

type AlertVariant = "default" | "destructive" | "success";

const alertVariants: Record<AlertVariant, string> = {
  default: "bg-card text-card-foreground",
  destructive:
    "border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive",
  success: "border-emerald-500/40 text-emerald-700 dark:text-emerald-300",
};

function Alert({
  className,
  variant = "default",
  ...props
}: React.ComponentProps<"div"> & { variant?: AlertVariant }) {
  return React.createElement("div", {
    role: "alert",
    "data-slot": "alert",
    className: cn("relative w-full rounded-lg border px-4 py-3 text-sm", alertVariants[variant], className),
    ...props,
  });
}

function AlertTitle({ className, ...props }: React.ComponentProps<"div">) {
  return React.createElement("div", {
    "data-slot": "alert-title",
    className: cn("mb-1 font-medium leading-none tracking-tight", className),
    ...props,
  });
}

function AlertDescription({ className, ...props }: React.ComponentProps<"div">) {
  return React.createElement("div", {
    "data-slot": "alert-description",
    className: cn("text-sm [&_p]:leading-relaxed", className),
    ...props,
  });
}

export { Alert, AlertTitle, AlertDescription };

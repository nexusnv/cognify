"use client";
import * as React from "react";
import * as ProgressPrimitive from "@radix-ui/react-progress";
import { cn } from "../lib/utils";
function Progress({
  className,
  value,
  ...props
}: React.ComponentProps<typeof ProgressPrimitive.Root>) {
  return React.createElement(
    ProgressPrimitive.Root,
    Object.assign(
      {},
      {
        "data-slot": "progress",
        className: cn("relative h-2 w-full overflow-hidden rounded-full bg-primary/20", className),
        ...props,
      },
      {
        children: React.createElement(
          ProgressPrimitive.Indicator,
          Object.assign(
            {},
            {
              "data-slot": "progress-indicator",
              className: "h-full w-full flex-1 bg-primary transition-all",
              style: { transform: `translateX(-${100 - (value || 0)}%)` },
            },
          ),
        ),
      },
    ),
  );
}
export { Progress };

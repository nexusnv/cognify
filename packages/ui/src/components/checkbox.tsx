import * as React from "react";
import { cn } from "../lib/utils";

export type CheckboxProps = Omit<React.ComponentProps<"input">, "type">;

const Checkbox = React.forwardRef<HTMLInputElement, CheckboxProps>(
  ({ className, ...props }, ref) =>
    React.createElement(
      "span",
      { className: "relative inline-flex size-4 shrink-0 items-center justify-center" },
      React.createElement("input", {
        ref,
        type: "checkbox",
        "data-slot": "checkbox",
        className: cn(
          "peer size-4 shrink-0 appearance-none rounded-[4px] border border-input bg-background shadow-xs transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 checked:border-primary checked:bg-primary",
          className,
        ),
        ...props,
      }),
      React.createElement(
        "svg",
        {
          "aria-hidden": "true",
          viewBox: "0 0 14 14",
          className:
            "pointer-events-none absolute size-3 text-primary-foreground opacity-0 peer-checked:opacity-100",
        },
        React.createElement("path", {
          d: "M11.5 3.75 5.75 9.5 2.5 6.25",
          fill: "none",
          stroke: "currentColor",
          strokeLinecap: "round",
          strokeLinejoin: "round",
          strokeWidth: "2",
        }),
      ),
    ),
);

Checkbox.displayName = "Checkbox";

export { Checkbox };

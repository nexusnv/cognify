"use client";
import * as React from "react";
import { CircleIcon } from "lucide-react";
import * as RadioGroupPrimitive from "@radix-ui/react-radio-group";
import { cn } from "../lib/utils";
function RadioGroup({
  className,
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Root>) {
  return React.createElement(
    RadioGroupPrimitive.Root,
    Object.assign(
      {},
      {
        "data-slot": "radio-group",
        className: cn("grid gap-3", className),
        ...props,
      },
    ),
  );
}
function RadioGroupItem({
  className,
  ...props
}: React.ComponentProps<typeof RadioGroupPrimitive.Item>) {
  return React.createElement(
    RadioGroupPrimitive.Item,
    Object.assign(
      {},
      {
        "data-slot": "radio-group-item",
        className: cn(
          "aspect-square size-4 shrink-0 rounded-full border border-input text-primary shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:ring-destructive/40",
          className,
        ),
        ...props,
      },
      {
        children: React.createElement(
          RadioGroupPrimitive.Indicator,
          Object.assign(
            {},
            {
              "data-slot": "radio-group-indicator",
              className: "relative flex items-center justify-center",
            },
            {
              children: React.createElement(
                CircleIcon,
                Object.assign(
                  {},
                  {
                    className:
                      "absolute top-1/2 left-1/2 size-2 -translate-x-1/2 -translate-y-1/2 fill-primary",
                  },
                ),
              ),
            },
          ),
        ),
      },
    ),
  );
}
export { RadioGroup, RadioGroupItem };

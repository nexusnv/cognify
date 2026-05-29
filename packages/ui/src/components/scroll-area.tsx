"use client";
import * as React from "react";
import * as ScrollAreaPrimitive from "@radix-ui/react-scroll-area";
import { cn } from "../lib/utils";
function ScrollArea({
  className,
  children,
  ...props
}: React.ComponentProps<typeof ScrollAreaPrimitive.Root>) {
  return React.createElement(
    ScrollAreaPrimitive.Root,
    Object.assign(
      {},
      {
        "data-slot": "scroll-area",
        className: cn("relative", className),
        ...props,
      },
      {
        children: [
          React.createElement(
            ScrollAreaPrimitive.Viewport,
            Object.assign(
              {},
              {
                "data-slot": "scroll-area-viewport",
                className:
                  "size-full rounded-[inherit] transition-[color,box-shadow] outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-1",
              },
              {
                children: children,
              },
            ),
          ),
          React.createElement(ScrollBar, null),
          React.createElement(ScrollAreaPrimitive.Corner, null),
        ],
      },
    ),
  );
}
function ScrollBar({
  className,
  orientation = "vertical",
  ...props
}: React.ComponentProps<typeof ScrollAreaPrimitive.ScrollAreaScrollbar>) {
  return React.createElement(
    ScrollAreaPrimitive.ScrollAreaScrollbar,
    Object.assign(
      {},
      {
        "data-slot": "scroll-area-scrollbar",
        orientation: orientation,
        className: cn(
          "flex touch-none p-px transition-colors select-none",
          orientation === "vertical" && "h-full w-2.5 border-l border-l-transparent",
          orientation === "horizontal" && "h-2.5 flex-col border-t border-t-transparent",
          className,
        ),
        ...props,
      },
      {
        children: React.createElement(
          ScrollAreaPrimitive.ScrollAreaThumb,
          Object.assign(
            {},
            {
              "data-slot": "scroll-area-thumb",
              className: "relative flex-1 rounded-full bg-border",
            },
          ),
        ),
      },
    ),
  );
}
export { ScrollArea, ScrollBar };

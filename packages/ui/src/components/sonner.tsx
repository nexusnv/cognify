"use client";
import * as React from "react";
import {
  CircleCheckIcon,
  InfoIcon,
  Loader2Icon,
  OctagonXIcon,
  TriangleAlertIcon,
} from "lucide-react";
import { useTheme } from "next-themes";
import { Toaster as Sonner, type ToasterProps } from "sonner";
function Toaster({ ...props }: ToasterProps) {
  const { theme = "system" } = useTheme();
  return React.createElement(
    Sonner,
    Object.assign(
      {},
      {
        theme: theme as ToasterProps["theme"],
        className: "toaster group",
        icons: {
          success: React.createElement(
            CircleCheckIcon,
            Object.assign(
              {},
              {
                className: "size-4",
              },
            ),
          ),
          info: React.createElement(
            InfoIcon,
            Object.assign(
              {},
              {
                className: "size-4",
              },
            ),
          ),
          warning: React.createElement(
            TriangleAlertIcon,
            Object.assign(
              {},
              {
                className: "size-4",
              },
            ),
          ),
          error: React.createElement(
            OctagonXIcon,
            Object.assign(
              {},
              {
                className: "size-4",
              },
            ),
          ),
          loading: React.createElement(
            Loader2Icon,
            Object.assign(
              {},
              {
                className: "size-4 animate-spin",
              },
            ),
          ),
        },
        style: {
          "--normal-bg": "var(--popover)",
          "--normal-text": "var(--popover-foreground)",
          "--normal-border": "var(--border)",
          "--border-radius": "var(--radius)",
        } as React.CSSProperties,
        ...props,
      },
    ),
  );
}
export { Toaster };

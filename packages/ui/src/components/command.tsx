"use client";
import * as React from "react";
import { Command as CommandPrimitive } from "cmdk";
import { SearchIcon } from "lucide-react";
import { cn } from "../lib/utils";
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from "./dialog";
function Command({ className, ...props }: React.ComponentProps<typeof CommandPrimitive>) {
  return React.createElement(
    CommandPrimitive,
    Object.assign(
      {},
      {
        "data-slot": "command",
        className: cn(
          "flex h-full w-full flex-col overflow-hidden rounded-md bg-popover text-popover-foreground",
          className,
        ),
        ...props,
      },
    ),
  );
}
function CommandDialog({
  title = "Command Palette",
  description = "Search for a command to run...",
  children,
  className,
  showCloseButton = true,
  ...props
}: React.ComponentProps<typeof Dialog> & {
  title?: string;
  description?: string;
  className?: string;
  showCloseButton?: boolean;
}) {
  return React.createElement(
    Dialog,
    Object.assign(
      {},
      {
        ...props,
      },
      {
        children: [
          React.createElement(
            DialogContent,
            Object.assign(
              {},
              {
                className: cn("overflow-hidden p-0", className),
                showCloseButton: showCloseButton,
              },
              {
                children: [
                  React.createElement(
                    DialogHeader,
                    Object.assign(
                      {},
                      {
                        key: "header",
                        className: "sr-only",
                      },
                      {
                        children: [
                          React.createElement(
                            DialogTitle,
                            Object.assign(
                              {},
                              {
                                key: "title",
                                children: title,
                              },
                            ),
                          ),
                          React.createElement(
                            DialogDescription,
                            Object.assign(
                              {},
                              {
                                key: "description",
                                children: description,
                              },
                            ),
                          ),
                        ],
                      },
                    ),
                  ),
                  React.createElement(
                    Command,
                    Object.assign(
                      {},
                      {
                        key: "command",
                        className:
                          "**:data-[slot=command-input-wrapper]:h-12 [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground [&_[cmdk-group]]:px-2 [&_[cmdk-group]:not([hidden])_~[cmdk-group]]:pt-0 [&_[cmdk-input-wrapper]_svg]:h-5 [&_[cmdk-input-wrapper]_svg]:w-5 [&_[cmdk-input]]:h-12 [&_[cmdk-item]]:px-2 [&_[cmdk-item]]:py-3 [&_[cmdk-item]_svg]:h-5 [&_[cmdk-item]_svg]:w-5",
                      },
                      {
                        children: children,
                      },
                    ),
                  ),
                ],
              },
            ),
          ),
        ],
      },
    ),
  );
}
function CommandInput({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Input>) {
  return React.createElement(
    "div",
    {
      "data-slot": "command-input-wrapper",
      className: "flex h-9 items-center gap-2 border-b px-3",
    },
    React.createElement(
      SearchIcon,
      Object.assign(
        {},
        {
          className: "size-4 shrink-0 opacity-50",
        },
      ),
    ),
    React.createElement(
      CommandPrimitive.Input,
      Object.assign(
        {},
        {
          "data-slot": "command-input",
          className: cn(
            "flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50",
            className,
          ),
          ...props,
        },
      ),
    ),
  );
}
function CommandList({ className, ...props }: React.ComponentProps<typeof CommandPrimitive.List>) {
  return React.createElement(
    CommandPrimitive.List,
    Object.assign(
      {},
      {
        "data-slot": "command-list",
        className: cn("max-h-[300px] scroll-py-1 overflow-x-hidden overflow-y-auto", className),
        ...props,
      },
    ),
  );
}
function CommandEmpty({ ...props }: React.ComponentProps<typeof CommandPrimitive.Empty>) {
  return React.createElement(
    CommandPrimitive.Empty,
    Object.assign(
      {},
      {
        "data-slot": "command-empty",
        className: "py-6 text-center text-sm",
        ...props,
      },
    ),
  );
}
function CommandGroup({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Group>) {
  return React.createElement(
    CommandPrimitive.Group,
    Object.assign(
      {},
      {
        "data-slot": "command-group",
        className: cn(
          "overflow-hidden p-1 text-foreground [&_[cmdk-group-heading]]:px-2 [&_[cmdk-group-heading]]:py-1.5 [&_[cmdk-group-heading]]:text-xs [&_[cmdk-group-heading]]:font-medium [&_[cmdk-group-heading]]:text-muted-foreground",
          className,
        ),
        ...props,
      },
    ),
  );
}
function CommandSeparator({
  className,
  ...props
}: React.ComponentProps<typeof CommandPrimitive.Separator>) {
  return React.createElement(
    CommandPrimitive.Separator,
    Object.assign(
      {},
      {
        "data-slot": "command-separator",
        className: cn("-mx-1 h-px bg-border", className),
        ...props,
      },
    ),
  );
}
function CommandItem({ className, ...props }: React.ComponentProps<typeof CommandPrimitive.Item>) {
  return React.createElement(
    CommandPrimitive.Item,
    Object.assign(
      {},
      {
        "data-slot": "command-item",
        className: cn(
          "relative flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50 data-[selected=true]:bg-accent data-[selected=true]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
          className,
        ),
        ...props,
      },
    ),
  );
}
function CommandShortcut({ className, ...props }: React.ComponentProps<"span">) {
  return React.createElement("span", {
    "data-slot": "command-shortcut",
    className: cn("ml-auto text-xs tracking-widest text-muted-foreground", className),
    ...props,
  });
}
export {
  Command,
  CommandDialog,
  CommandInput,
  CommandList,
  CommandEmpty,
  CommandGroup,
  CommandItem,
  CommandShortcut,
  CommandSeparator,
};

import * as React from "react";
import { ChevronRight, MoreHorizontal } from "lucide-react";
import { Slot } from "@radix-ui/react-slot";
import { cn } from "../lib/utils";
function Breadcrumb({ ...props }: React.ComponentProps<"nav">) {
  return React.createElement("nav", {
    "aria-label": "breadcrumb",
    "data-slot": "breadcrumb",
    ...props,
  });
}
function BreadcrumbList({ className, ...props }: React.ComponentProps<"ol">) {
  return React.createElement("ol", {
    "data-slot": "breadcrumb-list",
    className: cn(
      "flex flex-wrap items-center gap-1.5 text-sm break-words text-muted-foreground sm:gap-2.5",
      className,
    ),
    ...props,
  });
}
function BreadcrumbItem({ className, ...props }: React.ComponentProps<"li">) {
  return React.createElement("li", {
    "data-slot": "breadcrumb-item",
    className: cn("inline-flex items-center gap-1.5", className),
    ...props,
  });
}
function BreadcrumbLink({
  asChild,
  className,
  ...props
}: React.ComponentProps<"a"> & {
  asChild?: boolean;
}) {
  const Comp = asChild ? Slot : "a";
  return React.createElement(
    Comp,
    Object.assign(
      {},
      {
        "data-slot": "breadcrumb-link",
        className: cn("transition-colors hover:text-foreground", className),
        ...props,
      },
    ),
  );
}
function BreadcrumbPage({ className, ...props }: React.ComponentProps<"span">) {
  return React.createElement("span", {
    "data-slot": "breadcrumb-page",
    role: "link",
    "aria-disabled": "true",
    "aria-current": "page",
    className: cn("font-normal text-foreground", className),
    ...props,
  });
}
function BreadcrumbSeparator({ children, className, ...props }: React.ComponentProps<"li">) {
  return React.createElement(
    "li",
    {
      "data-slot": "breadcrumb-separator",
      role: "presentation",
      "aria-hidden": "true",
      className: cn("[&>svg]:size-3.5", className),
      ...props,
    },
    children ?? React.createElement(ChevronRight, null),
  );
}
function BreadcrumbEllipsis({ className, ...props }: React.ComponentProps<"span">) {
  return React.createElement(
    "span",
    {
      "data-slot": "breadcrumb-ellipsis",
      role: "presentation",
      "aria-hidden": "true",
      className: cn("flex size-9 items-center justify-center", className),
      ...props,
    },
    React.createElement(
      MoreHorizontal,
      Object.assign(
        {},
        {
          className: "size-4",
        },
      ),
    ),
    React.createElement(
      "span",
      {
        className: "sr-only",
      },
      "More",
    ),
  );
}
export {
  Breadcrumb,
  BreadcrumbList,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbPage,
  BreadcrumbSeparator,
  BreadcrumbEllipsis,
};

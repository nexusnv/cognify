import * as React from "react";
import { ChevronLeftIcon, ChevronRightIcon, MoreHorizontalIcon } from "lucide-react";
import { cn } from "../lib/utils";
import { Button, buttonVariants } from "./button";
function Pagination({ className, ...props }: React.ComponentProps<"nav">) {
  return React.createElement("nav", {
    role: "navigation",
    "aria-label": "pagination",
    "data-slot": "pagination",
    className: cn("mx-auto flex w-full justify-center", className),
    ...props,
  });
}
function PaginationContent({ className, ...props }: React.ComponentProps<"ul">) {
  return React.createElement("ul", {
    "data-slot": "pagination-content",
    className: cn("flex flex-row items-center gap-1", className),
    ...props,
  });
}
function PaginationItem({ ...props }: React.ComponentProps<"li">) {
  return React.createElement("li", {
    "data-slot": "pagination-item",
    ...props,
  });
}
type PaginationLinkProps = {
  isActive?: boolean;
} & Pick<React.ComponentProps<typeof Button>, "size"> &
  React.ComponentProps<"a">;
function PaginationLink({ className, isActive, size = "icon", ...props }: PaginationLinkProps) {
  return React.createElement("a", {
    "aria-current": isActive ? "page" : undefined,
    "data-slot": "pagination-link",
    "data-active": isActive,
    className: cn(
      buttonVariants({
        variant: isActive ? "outline" : "ghost",
        size,
      }),
      className,
    ),
    ...props,
  });
}
function PaginationPrevious({ className, ...props }: React.ComponentProps<typeof PaginationLink>) {
  return React.createElement(
    PaginationLink,
    Object.assign(
      {},
      {
        "aria-label": "Go to previous page",
        size: "default" as const,
        className: cn("gap-1 px-2.5 sm:pl-2.5", className),
        ...props,
      },
      {
        children: [
          React.createElement(ChevronLeftIcon, null),
          React.createElement(
            "span",
            {
              className: "hidden sm:block",
            },
            "Previous",
          ),
        ],
      },
    ),
  );
}
function PaginationNext({ className, ...props }: React.ComponentProps<typeof PaginationLink>) {
  return React.createElement(
    PaginationLink,
    Object.assign(
      {},
      {
        "aria-label": "Go to next page",
        size: "default" as const,
        className: cn("gap-1 px-2.5 sm:pr-2.5", className),
        ...props,
      },
      {
        children: [
          React.createElement(
            "span",
            {
              className: "hidden sm:block",
            },
            "Next",
          ),
          React.createElement(ChevronRightIcon, null),
        ],
      },
    ),
  );
}
function PaginationEllipsis({ className, ...props }: React.ComponentProps<"span">) {
  return React.createElement(
    "span",
    {
      "aria-hidden": true,
      "data-slot": "pagination-ellipsis",
      className: cn("flex size-9 items-center justify-center", className),
      ...props,
    },
    React.createElement(
      MoreHorizontalIcon,
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
      "More pages",
    ),
  );
}
export {
  Pagination,
  PaginationContent,
  PaginationLink,
  PaginationItem,
  PaginationPrevious,
  PaginationNext,
  PaginationEllipsis,
};

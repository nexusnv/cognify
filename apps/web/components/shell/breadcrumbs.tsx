import Link from "next/link";
import * as React from "react";
import {
  Breadcrumb,
  BreadcrumbItem as BreadcrumbPrimitiveItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbSeparator,
} from "@cognify/ui";
import type { BreadcrumbItem } from "./shell-types";

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  return (
    <Breadcrumb aria-label="Breadcrumb" className="min-w-0">
      <BreadcrumbList className="min-w-0 flex-nowrap">
        {items.map((item, index) => {
          const current = index === items.length - 1;
          const key = item.id ?? item.href ?? `${item.label}-${index}`;

          return (
            <React.Fragment key={key}>
              {index > 0 ? <BreadcrumbSeparator className="shrink-0" /> : null}
              <BreadcrumbPrimitiveItem className="min-w-0">
                {item.href && !current ? (
                  <BreadcrumbLink asChild className="truncate">
                    <Link href={item.href}>{item.label}</Link>
                  </BreadcrumbLink>
                ) : (
                  <span className="truncate text-foreground" aria-current={current ? "page" : undefined}>
                    {item.label}
                  </span>
                )}
              </BreadcrumbPrimitiveItem>
            </React.Fragment>
          );
        })}
      </BreadcrumbList>
    </Breadcrumb>
  );
}

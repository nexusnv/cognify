import Link from "next/link";
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
            <BreadcrumbPrimitiveItem key={key} className="min-w-0">
              {index > 0 ? <BreadcrumbSeparator className="shrink-0" /> : null}
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
          );
        })}
      </BreadcrumbList>
    </Breadcrumb>
  );
}

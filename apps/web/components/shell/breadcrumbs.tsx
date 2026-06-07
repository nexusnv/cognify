import { Fragment } from "react";
import Link from "next/link";
import {
  Breadcrumb,
  BreadcrumbItem as BreadcrumbPrimitiveItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from "@cognify/ui/components/breadcrumb";
import type { BreadcrumbItem } from "./shell-types";

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  return (
    <Breadcrumb aria-label="Breadcrumb" className="min-w-0 text-sm text-muted-foreground">
      <BreadcrumbList className="flex-nowrap">
        {items.map((item, index) => {
          const current = index === items.length - 1;
          const key = item.id ?? item.href ?? `${item.label}-${index}`;

          return (
            <Fragment key={key}>
              {index > 0 ? <BreadcrumbSeparator /> : null}
              <BreadcrumbPrimitiveItem className="min-w-0">
                {item.href && !current ? (
                  <BreadcrumbLink asChild className="truncate">
                    <Link href={item.href}>{item.label}</Link>
                  </BreadcrumbLink>
                ) : (
                  <BreadcrumbPage className="truncate">{item.label}</BreadcrumbPage>
                )}
              </BreadcrumbPrimitiveItem>
            </Fragment>
          );
        })}
      </BreadcrumbList>
    </Breadcrumb>
  );
}

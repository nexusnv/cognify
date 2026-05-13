import Link from "next/link";
import { ChevronRight } from "lucide-react";
import type { BreadcrumbItem } from "./shell-types";

export function Breadcrumbs({ items }: { items: BreadcrumbItem[] }) {
  return (
    <nav aria-label="Breadcrumb" className="min-w-0 text-sm text-muted-foreground">
      <ol className="flex min-w-0 items-center gap-1">
        {items.map((item, index) => {
          const current = index === items.length - 1;
          const key = item.id ?? item.href ?? `${item.label}-${index}`;

          return (
            <li key={key} className="flex min-w-0 items-center gap-1">
              {index > 0 ? <ChevronRight className="h-4 w-4 shrink-0" aria-hidden="true" /> : null}
              {item.href && !current ? (
                <Link className="truncate hover:text-foreground" href={item.href}>
                  {item.label}
                </Link>
              ) : (
                <span
                  className="truncate text-foreground"
                  aria-current={current ? "page" : undefined}
                >
                  {item.label}
                </span>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

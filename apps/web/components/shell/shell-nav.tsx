import Link from "next/link";
import { Button, buttonVariants } from "@cognify/ui";
import type { ShellNavGroup } from "./shell-types";
import { isActivePath } from "./shell-utils";

export function ShellNav({
  groups,
  pathname,
  onNavigate,
}: {
  groups: ShellNavGroup[];
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <nav aria-label="Primary" className="space-y-6">
      {groups.map((group) => (
        <div key={group.id}>
          <h2 className="px-2 text-xs font-semibold uppercase tracking-normal text-muted-foreground">
            {group.label}
          </h2>
          <div className="mt-2 space-y-1">
            {group.items.map((item) => {
              const Icon = item.icon;
              const active = isActivePath(item.href, pathname);

              if (!item.implemented) {
                return (
                  <span
                    key={item.href}
                    role="link"
                    tabIndex={-1}
                    className={buttonVariants({
                      variant: "ghost",
                      className:
                        "w-full justify-start px-2 text-muted-foreground opacity-70 hover:bg-transparent hover:text-muted-foreground",
                    })}
                    aria-disabled="true"
                  >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                    {item.label}
                  </span>
                );
              }

              return (
                <Button
                  key={item.href}
                  asChild
                  variant={active ? "default" : "ghost"}
                  className="min-h-10 w-full justify-start px-2"
                >
                  <Link
                    href={item.href}
                    onClick={onNavigate}
                    aria-current={active ? "page" : undefined}
                  >
                    <Icon className="h-4 w-4" aria-hidden="true" />
                    {item.label}
                  </Link>
                </Button>
              );
            })}
          </div>
        </div>
      ))}
    </nav>
  );
}

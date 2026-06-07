import Link from "next/link";
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuItem,
} from "@cognify/ui/components/sidebar";
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
        <SidebarGroup key={group.id} className="px-0 py-0">
          <SidebarGroupLabel asChild>
            <h2 className="px-2 text-xs font-semibold uppercase tracking-normal text-muted-foreground">
              {group.label}
            </h2>
          </SidebarGroupLabel>
          <SidebarGroupContent className="mt-2">
            <SidebarMenu>
              {group.items.map((item) => {
                const Icon = item.icon;
                const active = isActivePath(item.href, pathname);

                if (!item.implemented) {
                  return (
                    <SidebarMenuItem key={item.href}>
                      <span
                        role="link"
                        tabIndex={-1}
                        className="flex min-h-10 items-center gap-3 rounded-md px-2 text-sm text-muted-foreground opacity-70"
                        aria-disabled="true"
                      >
                        <Icon className="h-4 w-4" aria-hidden="true" />
                        {item.label}
                      </span>
                    </SidebarMenuItem>
                  );
                }

                return (
                  <SidebarMenuItem key={item.href}>
                    <Link
                      href={item.href}
                      onClick={onNavigate}
                      aria-current={active ? "page" : undefined}
                      className={[
                        "flex min-h-10 items-center gap-3 rounded-md px-2 text-sm font-medium",
                        active
                          ? "border border-foreground bg-foreground text-background"
                          : "text-muted-foreground hover:bg-card hover:text-foreground",
                      ].join(" ")}
                    >
                      <Icon className="h-4 w-4" aria-hidden="true" />
                      {item.label}
                    </Link>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      ))}
    </nav>
  );
}

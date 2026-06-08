import Link from "next/link";
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@cognify/ui/components/sidebar";
import type { ShellNavGroup, ShellPrimaryArea, ShellPrimaryNavItem } from "./shell-types";
import { isActivePath } from "./shell-utils";

export function PrimaryShellNav({
  items,
  activeArea,
  pathname,
  onNavigate,
}: {
  items: ShellPrimaryNavItem[];
  activeArea: ShellPrimaryArea;
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <nav aria-label="Primary product areas">
      <SidebarMenu>
        {items.map((item) => {
          const Icon = item.icon;
          const active = item.area === activeArea || isActivePath(item.href, pathname);

          if (!item.implemented) {
            return (
              <SidebarMenuItem key={item.href}>
                <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                  <a
                    href={item.href}
                    role="link"
                    aria-current={active ? "page" : undefined}
                    aria-disabled="true"
                    tabIndex={-1}
                    onClick={(event) => event.preventDefault()}
                  >
                    <Icon aria-hidden="true" />
                    <span>{item.label}</span>
                  </a>
                </SidebarMenuButton>
              </SidebarMenuItem>
            );
          }

          return (
            <SidebarMenuItem key={item.href}>
              <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                <Link
                  href={item.href}
                  onClick={onNavigate}
                  aria-current={active ? "page" : undefined}
                >
                  <Icon aria-hidden="true" />
                  <span>{item.label}</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          );
        })}
      </SidebarMenu>
    </nav>
  );
}

export function SecondaryShellNav({
  groups,
  pathname,
  onNavigate,
}: {
  groups: ShellNavGroup[];
  pathname: string;
  onNavigate?: () => void;
}) {
  return (
    <nav aria-label="Secondary workspace navigation" className="space-y-6">
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
                      <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                        <a
                          href={item.href}
                          role="link"
                          aria-current={active ? "page" : undefined}
                          aria-disabled="true"
                          tabIndex={-1}
                          onClick={(event) => event.preventDefault()}
                        >
                          <Icon aria-hidden="true" />
                          <span>{item.label}</span>
                        </a>
                      </SidebarMenuButton>
                    </SidebarMenuItem>
                  );
                }

                return (
                  <SidebarMenuItem key={item.href}>
                    <SidebarMenuButton asChild isActive={active} tooltip={item.label}>
                      <Link
                        href={item.href}
                        onClick={onNavigate}
                        aria-current={active ? "page" : undefined}
                      >
                        <Icon aria-hidden="true" />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
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

"use client";

import * as React from "react";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarRail,
} from "@cognify/ui/components/sidebar";
import type { CurrentUserContext } from "@/features/identity/types/identity-view-model";
import {
  getActiveNavigation,
  getVisibleNavigation,
  getVisibleSecondaryNavigation,
} from "./navigation";
import { NavMain } from "./nav-main";
import { NavSecondary } from "./nav-secondary";
import { NavUser } from "./nav-user";
import { formatTenantRole, formatWorkspaceLabel, getInitials } from "./shell-utils";
import { WorkspaceSwitcher } from "./workspace-switcher";

export function AppSidebar({
  context,
  pathname,
  logoutPending = false,
  onLogout,
  ...props
}: React.ComponentProps<typeof Sidebar> & {
  context: CurrentUserContext | undefined;
  pathname: string;
  logoutPending?: boolean;
  onLogout: () => void;
}) {
  const permissions = context?.permissions;
  const activeTenantId = context?.activeTenant?.id;
  const activeTenantName = formatWorkspaceLabel(context?.activeTenant?.name);
  const activeRole = formatTenantRole(context?.activeRole);
  const workspaces =
    context?.tenants.map((tenant) => ({
      id: tenant.id,
      name:
        tenant.id === activeTenantId
          ? activeTenantName
          : formatWorkspaceLabel(tenant.name),
      role: formatTenantRole(tenant.role),
      active: tenant.id === activeTenantId,
    })) ?? [
      {
        id: "workspace",
        name: activeTenantName,
        role: activeRole,
        active: true,
      },
    ];

  const primaryItems = getActiveNavigation(getVisibleNavigation(permissions), pathname);
  const secondaryItems = getVisibleSecondaryNavigation(permissions);
  const userName = context?.user.name?.trim() || "Account";
  const userEmail = context?.user.email?.trim() || "account@cognify.local";

  return (
    <Sidebar collapsible="icon" variant="inset" data-testid="default-app-sidebar" {...props}>
      <SidebarHeader>
        <WorkspaceSwitcher workspaces={workspaces} />
      </SidebarHeader>
      <SidebarContent>
        <nav aria-label="Primary">
          <NavMain items={primaryItems} />
        </nav>
        <NavSecondary items={secondaryItems} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser
          user={{
            name: userName,
            email: userEmail,
            avatar: context?.user.avatarUrl ?? "",
            initials: getInitials(userName),
          }}
          logoutPending={logoutPending}
          onLogout={onLogout}
        />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  );
}

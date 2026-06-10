"use client";

import * as React from "react";
import {
  RiArrowUpDownLine,
  RiBuilding4Line,
  RiCheckboxCircleLine,
} from "@remixicon/react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from "@cognify/ui/components/dropdown-menu";
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@cognify/ui/components/sidebar";

export type WorkspaceSwitcherItem = {
  id: string;
  name: string;
  role: string;
  active: boolean;
};

export function WorkspaceSwitcher({ workspaces }: { workspaces: WorkspaceSwitcherItem[] }) {
  const { isMobile } = useSidebar();
  const activeWorkspace =
    workspaces.find((workspace) => workspace.active) ?? workspaces[0] ?? {
      id: "workspace",
      name: "Operational workspace",
      role: "Member",
      active: true,
    };

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <RiBuilding4Line />
              </div>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{activeWorkspace.name}</span>
                <span className="truncate text-xs">{activeWorkspace.role}</span>
              </div>
              <RiArrowUpDownLine className="ml-auto" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-fit"
            align="start"
            side={isMobile ? "bottom" : "right"}
            sideOffset={4}
          >
            <DropdownMenuLabel className="text-xs text-muted-foreground">
              Workspaces
            </DropdownMenuLabel>
            {workspaces.map((workspace) => (
              <DropdownMenuItem key={workspace.id} className="gap-2 p-2">
                <div className="flex size-6 items-center justify-center rounded-md border">
                  {workspace.active ? <RiCheckboxCircleLine /> : <RiBuilding4Line />}
                </div>
                <div className="grid min-w-0 flex-1">
                  <span className="truncate">{workspace.name}</span>
                  <span className="truncate text-xs text-muted-foreground">{workspace.role}</span>
                </div>
              </DropdownMenuItem>
            ))}
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  );
}

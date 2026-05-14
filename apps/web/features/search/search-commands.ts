"use client";

import { Plus } from "lucide-react";
import { shellNavGroups } from "@/components/shell/shell-route-config";
import { getVisibleNavGroups } from "@/components/shell/shell-utils";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";
import type { SearchCommandViewModel } from "./types/search-view-model";

export function getSearchCommands(
  permissions?: IdentityPermissions | null,
): SearchCommandViewModel[] {
  const visibleGroups = permissions
    ? getVisibleNavGroups(shellNavGroups, permissions)
    : shellNavGroups.map((group) => ({
        ...group,
        items: [],
      }));

  const navigationCommands = visibleGroups.flatMap((group) =>
    group.items
      .filter((item) => item.implemented)
      .map((item) => ({
        id: `navigate:${item.href}`,
        group: "Navigation",
        label: `Open ${item.label.toLowerCase()}`,
        description: `Go to ${item.label.toLowerCase()}`,
        href: item.href,
        keywords: [item.label.toLowerCase(), group.label.toLowerCase()],
        icon: item.icon,
        enabled: true,
      })),
  );

  const createRequisitionCommand: SearchCommandViewModel = {
    id: "action:create-requisition",
    group: "Actions",
    label: "Create requisition",
    description: "Start a new requisition draft",
    href: "/requisitions/new",
    keywords: ["create", "new", "draft", "request"],
    icon: Plus,
    enabled: permissions?.canCreateRequisition ?? false,
  };

  return permissions ? [...navigationCommands, createRequisitionCommand] : navigationCommands;
}

"use client";

import {
  CalendarDays,
  CheckSquare,
  FileText,
  FolderKanban,
  Plus,
  ReceiptText,
  Settings,
} from "lucide-react";
import type { IdentityPermissions } from "@/features/identity/types/identity-view-model";
import type { SearchCommandViewModel } from "./types/search-view-model";

export function getSearchCommands(
  permissions?: IdentityPermissions | null,
): SearchCommandViewModel[] {
  if (!permissions) return [];

  const canUseRequisitions =
    permissions.canCreateRequisition ||
    permissions.canViewSubmittedRequisitions ||
    permissions.canUpdateOwnDraftRequisition ||
    permissions.canSubmitOwnDraftRequisition;
  const canUseCalendar =
    permissions.canAccessAdmin ||
    permissions.canManageSourcingIntake ||
    permissions.canReviewQuotationNormalization ||
    permissions.canViewSubmittedRequisitions;

  const navigationCommands: SearchCommandViewModel[] = [
    {
      id: "navigate:/requisitions",
      group: "Navigation",
      label: "Open requisitions",
      description: "Go to requisitions",
      href: "/requisitions",
      keywords: ["requisitions", "procurement"],
      icon: FileText,
      enabled: canUseRequisitions,
    },
    {
      id: "navigate:/projects",
      group: "Navigation",
      label: "Open projects",
      description: "Go to projects",
      href: "/projects",
      keywords: ["projects", "procurement"],
      icon: FolderKanban,
      enabled: canUseRequisitions,
    },
    {
      id: "navigate:/approvals",
      group: "Navigation",
      label: "Open approvals",
      description: "Go to approvals",
      href: "/approvals",
      keywords: ["approvals", "my work"],
      icon: CheckSquare,
      enabled: true,
    },
    {
      id: "navigate:/calendar",
      group: "Navigation",
      label: "Open calendar",
      description: "Go to calendar",
      href: "/calendar",
      keywords: ["calendar", "procurement"],
      icon: CalendarDays,
      enabled: canUseCalendar,
    },
    {
      id: "navigate:/sourcing/intake",
      group: "Navigation",
      label: "Open sourcing intake",
      description: "Go to sourcing intake",
      href: "/sourcing/intake",
      keywords: ["sourcing", "intake", "procurement"],
      icon: CheckSquare,
      enabled: permissions.canManageSourcingIntake,
    },
    {
      id: "navigate:/quotations/normalizations",
      group: "Navigation",
      label: "Open quotations",
      description: "Go to quotations",
      href: "/quotations/normalizations",
      keywords: ["quotations", "normalization", "procurement"],
      icon: ReceiptText,
      enabled: permissions.canReviewQuotationNormalization,
    },
    {
      id: "navigate:/system",
      group: "Navigation",
      label: "Open system",
      description: "Go to system",
      href: "/system",
      keywords: ["system", "admin"],
      icon: Settings,
      enabled: permissions.canAccessAdmin,
    },
  ].filter((command) => command.enabled);

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

  return [...navigationCommands, createRequisitionCommand].filter((command) => command.enabled);
}
